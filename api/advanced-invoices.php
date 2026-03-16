<?php
/**
 * Advanced Invoices API
 * REST API endpoint for Advanced Invoice Generator feature
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

// Verify/repair advanced_invoices collection availability.
$advancedInvoicesProbe = $db->safeLoad('advanced_invoices');
if (!($advancedInvoicesProbe['success'] ?? true)) {
    $collectionPath = DATA_PATH . '/advanced_invoices.json.enc';
    if (is_file($collectionPath)) {
        $backupPath = DATA_PATH . '/advanced_invoices.corrupt.' . date('Ymd_His') . '.json.enc.bak';
        @copy($collectionPath, $backupPath);
    }
    // Reinitialize collection so API remains usable even when old data is unreadable.
    $db->save('advanced_invoices', []);
}

$configResult = $db->safeLoad('config');
$config = is_array($configResult['data'] ?? null) ? $configResult['data'] : [];
$id = $_GET['id'] ?? null;

/**
 * Advanced Invoices API - uses BaseAPI for consistent CRUD operations
 */
class AdvancedInvoicesAPI extends BaseAPI {
    private function loadCollectionSafe(): array {
        $result = $this->db->safeLoad($this->collection);
        $data = $result['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    protected function getAllowedFields(): array {
        return [
            'invoiceNumber',
            'invoiceDate',
            'companyHeader',
            'customer',
            'lineItems',
            'paymentDetails',
            'totalDue',
            'currency',
            'status',
            'notes',
            'footerText',
            'customFields',
            'template'
        ];
    }

    public function find(string $id): ?array {
        $invoices = $this->loadCollectionSafe();
        foreach ($invoices as $invoice) {
            if (($invoice['id'] ?? '') === $id) {
                return $invoice;
            }
        }
        return null;
    }

    public function findAll(): array {
        return $this->loadCollectionSafe();
    }

    public function create(array $data): ?array {
        $allowedFields = $this->getAllowedFields();
        $record = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $record[$field] = $data[$field];
            }
        }
        if (!isset($record['id'])) {
            $record['id'] = $this->db->generateId();
        }
        $record['createdAt'] = date('c');
        $record['updatedAt'] = date('c');

        $invoices = $this->loadCollectionSafe();
        $invoices[] = $record;
        if ($this->db->save($this->collection, $invoices)) {
            return $record;
        }
        return null;
    }

    public function update(string $id, array $data): ?array {
        $allowedFields = $this->getAllowedFields();
        $updates = [];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }

        $invoices = $this->loadCollectionSafe();
        foreach ($invoices as $index => $invoice) {
            if (($invoice['id'] ?? '') === $id) {
                $invoices[$index] = array_merge($invoice, $updates);
                $invoices[$index]['updatedAt'] = date('c');
                if ($this->db->save($this->collection, $invoices)) {
                    return $invoices[$index];
                }
                return null;
            }
        }
        return null;
    }

    public function delete(string $id): bool {
        $invoices = $this->loadCollectionSafe();
        $originalCount = count($invoices);
        $invoices = array_values(array_filter($invoices, fn($invoice) => ($invoice['id'] ?? '') !== $id));
        if ($originalCount === count($invoices)) {
            return false;
        }
        return $this->db->save($this->collection, $invoices);
    }

    /**
     * Generate unique invoice number in INV-YYYY-#### format
     */
    public function generateInvoiceNumber(): string {
        $invoices = $this->findAll();
        $year = date('Y');
        $count = count(array_filter($invoices, function($i) use ($year) {
            return str_starts_with($i['invoiceNumber'] ?? '', "INV-$year");
        })) + 1;
        return "INV-$year-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create invoice with automatic calculations
     */
    public function createWithItems(array $data): ?array {
        // Calculate total from line items
        $total = 0;
        if (!empty($data['lineItems'])) {
            foreach ($data['lineItems'] as $item) {
                $total += floatval($item['amount'] ?? 0);
            }
        }
        $data['totalDue'] = $total;
        $data['invoiceNumber'] = $this->generateInvoiceNumber();

        return $this->create($data);
    }

    /**
     * Update invoice with recalculation
     */
    public function updateWithItems(string $id, array $data): ?array {
        // Recalculate total from line items
        if (isset($data['lineItems'])) {
            $total = 0;
            foreach ($data['lineItems'] as $item) {
                $total += floatval($item['amount'] ?? 0);
            }
            $data['totalDue'] = $total;
        }

        return $this->update($id, $data);
    }
}

$api = new AdvancedInvoicesAPI($db, 'advanced_invoices');

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $invoice = $api->find($id);
            if ($invoice) {
                successResponse($invoice);
            }
            errorResponse('Invoice not found', 404);
        }
        successResponse($api->findAll());
        break;

    case 'POST':
        $body = getJsonBody();

        // Validate required fields
        if (empty($body['companyHeader']) || empty($body['customer']) || empty($body['paymentDetails'])) {
            errorResponse('Company, customer, and payment details are required', 400);
        }

        $lineItems = $body['lineItems'] ?? [];
        if (!is_array($lineItems)) {
            errorResponse('Line items must be an array', 400);
        }

        $invoice = $api->createWithItems($body);
        if ($invoice) {
            successResponse($invoice, 'Invoice created successfully');
        }

        errorResponse('Failed to create invoice', 500);
        break;

    case 'PUT':
        if (!$id) {
            errorResponse('Invoice ID required', 400);
        }

        $body = getJsonBody();
        $invoice = $api->updateWithItems($id, $body);

        if ($invoice) {
            successResponse($invoice, 'Invoice updated successfully');
        }

        errorResponse('Invoice not found', 404);
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Invoice ID required', 400);
        }

        if ($api->delete($id)) {
            successResponse(null, 'Invoice deleted successfully');
        }

        errorResponse('Invoice not found', 404);
        break;

    default:
        errorResponse('Method not allowed', 405);
}

