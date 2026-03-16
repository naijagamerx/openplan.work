<?php
/**
 * Inventory API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BaseAPI.php';

ini_set('display_errors', 0);
error_reporting(0);

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// CSRF validation for non-GET requests
if (requestMethod() !== 'GET' && !Auth::isMcp()) {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());

// Verify database connection works
try {
    $db->load('inventory', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

function loadInventoryTransactions(Database $db): array
{
    $transactions = $db->load('inventory_transactions', true);
    return is_array($transactions) ? $transactions : [];
}

function computeInventorySummary(Database $db): array
{
    $transactions = loadInventoryTransactions($db);
    $totalIn = 0;
    $totalOut = 0;
    $totalExpense = 0;

    foreach ($transactions as $entry) {
        $qty = (int)($entry['quantity'] ?? 0);
        if (($entry['type'] ?? '') === 'in') {
            $totalIn += $qty;
            $totalExpense += (float)($entry['totalCost'] ?? 0);
        } elseif (($entry['type'] ?? '') === 'out') {
            $totalOut += $qty;
        }
    }

    return [
        'total_in' => $totalIn,
        'total_out' => $totalOut,
        'total_expense' => $totalExpense
    ];
}

/**
 * Inventory API - uses BaseAPI for consistent CRUD operations
 */
class InventoryAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return ['sku', 'name', 'description', 'category', 'quantity', 'unitPrice', 'costPrice', 'reorderPoint', 'supplier', 'notes', 'linkedTaskId'];
    }

    /**
     * Adjust stock quantity
     */
    public function adjustStock(string $id, int $adjustment): ?array {
        $product = $this->find($id);
        if (!$product) {
            return null;
        }

        $newQuantity = max(0, ($product['quantity'] ?? 0) + $adjustment);
        if ($this->db->update($this->collection, $id, ['quantity' => $newQuantity])) {
            return $this->find($id);
        }

        return null;
    }
}

$inventoryAPI = new InventoryAPI($db, 'inventory');

switch (requestMethod()) {
    case 'GET':
        if ($action === 'transactions') {
            $transactions = loadInventoryTransactions($db);
            $productId = $_GET['productId'] ?? null;
            if ($productId) {
                $transactions = array_filter($transactions, fn($entry) => ($entry['productId'] ?? '') === $productId);
            }
            $transactions = array_reverse(array_values($transactions));
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 0;
            if ($limit > 0) {
                $transactions = array_slice($transactions, 0, $limit);
            }
            successResponse($transactions);
        }
        if ($action === 'summary') {
            $summary = computeInventorySummary($db);
            $transactions = array_slice(array_reverse(loadInventoryTransactions($db)), 0, 10);
            $summary['recent'] = $transactions;
            successResponse($summary);
        }
        successResponse($inventoryAPI->findAll());
        break;

    case 'POST':
        $body = getJsonBody();

        // Stock adjustment
        if ($action === 'adjust') {
            $productId = $body['id'] ?? '';
            $adjustment = intval($body['adjustment'] ?? 0);
            $note = $body['note'] ?? '';
            $unitCostOverride = $body['unitCost'] ?? null;
            $unitCostOverride = is_numeric($unitCostOverride) ? (float)$unitCostOverride : null;

            $product = $inventoryAPI->adjustStock($productId, $adjustment);
            if ($product) {
                $type = $adjustment >= 0 ? 'in' : 'out';
                $quantity = abs($adjustment);
                $unitCost = $unitCostOverride;
                if ($unitCost === null) {
                    $unitCost = (float)($product['costPrice'] ?? 0);
                }

                $transaction = [
                    'id' => uniqid('inv_tx_'),
                    'productId' => $product['id'],
                    'productName' => $product['name'] ?? 'Unknown',
                    'type' => $type,
                    'quantity' => $quantity,
                    'unitCost' => $unitCost,
                    'totalCost' => $unitCost * $quantity,
                    'note' => $note,
                    'createdAt' => date('c')
                ];
                $transactions = $db->load('inventory_transactions', true) ?? [];
                $transactions[] = $transaction;
                $db->save('inventory_transactions', $transactions);

                successResponse($product, 'Stock updated');
            }
            errorResponse('Product not found', 404, ERROR_NOT_FOUND);
        }

        // Update product
        if ($action === 'update' && $id) {
            $product = $inventoryAPI->update($id, $body);
            if ($product) {
                successResponse($product, 'Product updated');
            }
            $inventoryAPI->notFound('Product');
        }

        // Create new product
        if (empty($body['name'])) {
            $inventoryAPI->validationError('Product name is required');
        }

        $product = $inventoryAPI->create($body);
        if ($product) {
            successResponse($product, 'Product added');
        }

        errorResponse('Failed to create product', 500, ERROR_SERVER);
        break;

    case 'PUT':
        if (!$id) {
            errorResponse('Product ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $product = $inventoryAPI->update($id, $body);

        if ($product) {
            successResponse($product, 'Product updated');
        }

        $inventoryAPI->notFound('Product');
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Product ID required', 400, ERROR_VALIDATION);
        }

        if ($inventoryAPI->delete($id)) {
            successResponse(null, 'Product deleted');
        }

        $inventoryAPI->notFound('Product');
        break;

    default:
        errorResponse('Method not allowed', 405);
}

