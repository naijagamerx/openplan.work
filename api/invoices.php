<?php
/**
 * Invoices API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BaseAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// CSRF validation for non-GET requests (bypass for MCP)
if (requestMethod() !== 'GET' && !Auth::isMcp()) {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());

// Verify master password against user key-check collection.
try {
    $db->load('key_check', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

// Recover invoices collection if only this collection is corrupted/legacy-encrypted.
$invoiceProbe = $db->safeLoad('invoices');
if (!$invoiceProbe['success']) {
    $invoiceFile = $db->getDataPath() . '/invoices.json.enc';
    if (is_file($invoiceFile)) {
        $backupPath = $db->getDataPath() . '/invoices.corrupt.' . date('Ymd-His') . '.bak';
        @copy($invoiceFile, $backupPath);
    }
    $db->save('invoices', []);
}

$config = $db->load('config', true);
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

/**
 * Invoices API - uses BaseAPI for consistent CRUD operations
 */
class InvoicesAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return ['status', 'notes', 'dueDate', 'clientId', 'projectId', 'lineItems', 'subtotal', 'taxRate', 'taxAmount', 'total', 'currency'];
    }

    /**
     * Generate next invoice number
     */
    public function generateInvoiceNumber(): string {
        $invoices = $this->findAll();
        $year = date('Y');
        $count = count(array_filter($invoices, fn($i) => str_starts_with($i['invoiceNumber'] ?? '', $year))) + 1;
        return $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create invoice with line items calculation
     */
    public function createWithItems(array $data, array $items, float $taxRate, string $currency): ?array {
        $lineItems = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $qty = floatval($item['quantity'] ?? 1);
            $rate = floatval($item['unitPrice'] ?? 0);
            $total = $qty * $rate;
            $subtotal += $total;

            $lineItems[] = [
                'description' => $item['description'] ?? '',
                'quantity' => $qty,
                'unitPrice' => $rate,
                'total' => $total
            ];
        }

        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $record = [
            'invoiceNumber' => $this->generateInvoiceNumber(),
            'clientId' => $data['clientId'],
            'projectId' => $data['projectId'] ?? null,
            'lineItems' => $lineItems,
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
            'total' => $total,
            'currency' => $currency,
            'status' => 'draft',
            'dueDate' => $data['dueDate'],
            'notes' => $data['notes'] ?? ''
        ];

        // Insert returns true/false, but ID is generated inside insert
        // We need to generate the ID first so we can retrieve the record
        $record['id'] = $this->db->generateId();

        if ($this->db->insert($this->collection, $record)) {
            return $this->db->findById($this->collection, $record['id']);
        }

        return null;
    }
}

$invoicesAPI = new InvoicesAPI($db, 'invoices');

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $invoice = $invoicesAPI->find($id);
            if ($invoice) {
                successResponse($invoice);
            }
            errorResponse('Invoice not found', 404, ERROR_NOT_FOUND);
        }
        successResponse($invoicesAPI->findAll());
        break;

    case 'POST':
        $body = getJsonBody();

        if (empty($body['clientId']) || empty($body['dueDate'])) {
            errorResponse('Client and due date are required', 400, ERROR_VALIDATION);
        }

        $items = $body['lineItems'] ?? $body['items'] ?? [];
        if (empty($items)) {
            errorResponse('At least one line item is required', 400, ERROR_VALIDATION);
        }

        $taxRate = floatval($config['taxRate'] ?? 0);
        $currency = $config['currency'] ?? 'USD';

        $invoice = $invoicesAPI->createWithItems($body, $items, $taxRate, $currency);
        if ($invoice) {
            successResponse($invoice, 'Invoice created');
        }

        errorResponse('Failed to create invoice', 500, ERROR_SERVER);
        break;

    case 'PUT':
        if (!$id) {
            errorResponse('Invoice ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $invoice = $invoicesAPI->update($id, $body);

        if ($invoice) {
            successResponse($invoice, 'Invoice updated');
        }

        errorResponse('Invoice not found', 404, ERROR_NOT_FOUND);
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Invoice ID required', 400, ERROR_VALIDATION);
        }

        if ($invoicesAPI->delete($id)) {
            successResponse(null, 'Invoice deleted');
        }

        errorResponse('Invoice not found', 404, ERROR_NOT_FOUND);
        break;

    default:
        errorResponse('Method not allowed', 405);
}

