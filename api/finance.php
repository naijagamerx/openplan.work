<?php
/**
 * Finance API Endpoint
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

// Verify database connection works
try {
    $db->load('finance', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

/**
 * Finance API - uses BaseAPI for consistent CRUD operations
 */
class FinanceAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return ['transactionNumber', 'type', 'description', 'amount', 'category', 'date', 'notes', 'projectId', 'clientId'];
    }

    /**
     * Generate next transaction number (format: YYYY-NNNN)
     */
    public function generateTransactionNumber(): string {
        $transactions = $this->findAll();
        $year = date('Y');
        // Count transactions starting with current year (PHP 7.x compatible)
        $count = 0;
        foreach ($transactions as $t) {
            if (isset($t['transactionNumber']) && strpos($t['transactionNumber'], $year) === 0) {
                $count++;
            }
        }
        $count++;
        return $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): ?array {
        $record = [];

        foreach ($this->getAllowedFields() as $field) {
            if (isset($data[$field])) {
                $record[$field] = $data[$field];
            }
        }

        // Generate transaction number if not provided
        if (!isset($record['transactionNumber'])) {
            $record['transactionNumber'] = $this->generateTransactionNumber();
        }

        // Set defaults
        if (!isset($record['type'])) {
            $record['type'] = 'expense';
        }
        if (!isset($record['date'])) {
            $record['date'] = date('c');
        }
        if (!isset($record['category'])) {
            $record['category'] = 'Other';
        }

        // Generate ID first so we can retrieve the record
        $record['id'] = $this->db->generateId();

        if ($this->db->insert($this->collection, $record)) {
            return $this->db->findById($this->collection, $record['id']);
        }

        return null;
    }

    public function update(string $id, array $data): ?array {
        $updates = [];

        foreach ($this->getAllowedFields() as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }

        if ($this->db->update($this->collection, $id, $updates)) {
            return $this->db->findById($this->collection, $id);
        }

        return null;
    }
}

$financeAPI = new FinanceAPI($db, 'finance');

switch (requestMethod()) {
    case 'GET':
        successResponse($financeAPI->findAll());
        break;

    case 'POST':
        $body = getJsonBody();

        if ($action === 'update' && $id) {
            $entry = $financeAPI->update($id, $body);
            if ($entry) {
                successResponse($entry, 'Entry updated');
            }
            errorResponse('Entry not found', 404, ERROR_NOT_FOUND);
        }

        // Create new entry
        if (empty($body['description']) || !isset($body['amount']) || $body['amount'] === '') {
            errorResponse('Description and amount are required', 400, ERROR_VALIDATION);
        }

        // Ensure amount is a number
        $body['amount'] = floatval($body['amount']);

        $entry = $financeAPI->create($body);
        if ($entry) {
            successResponse($entry, 'Entry created');
        }

        errorResponse('Failed to create entry', 500, ERROR_SERVER);
        break;

    case 'PUT':
        if (!$id) {
            errorResponse('Entry ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $entry = $financeAPI->update($id, $body);

        if ($entry) {
            successResponse($entry, 'Entry updated');
        }

        errorResponse('Entry not found', 404, ERROR_NOT_FOUND);
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Entry ID required', 400, ERROR_VALIDATION);
        }

        if ($financeAPI->delete($id)) {
            successResponse(null, 'Entry deleted');
        }

        errorResponse('Entry not found', 404, ERROR_NOT_FOUND);
        break;

    default:
        errorResponse('Method not allowed', 405);
}

