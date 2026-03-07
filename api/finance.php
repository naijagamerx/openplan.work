<?php
/**
 * Finance API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

if (requestMethod() !== 'GET') {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;

switch (requestMethod()) {
    case 'GET':
        $finance = $db->load('finance');
        successResponse($finance);
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'add';
        
        if (empty($body['description']) || empty($body['amount'])) {
            errorResponse('Description and amount are required');
        }
        
        $finance = $db->load('finance');
        
        if ($action === 'update' && !empty($_GET['id'])) {
            $updated = false;
            foreach ($finance as &$entry) {
                if ($entry['id'] === $_GET['id']) {
                    $entry['type'] = $body['type'] ?? $entry['type'] ?? 'expense';
                    $entry['description'] = $body['description'];
                    $entry['amount'] = floatval($body['amount']);
                    $entry['category'] = $body['category'] ?? $entry['category'] ?? 'Other';
                    $entry['date'] = $body['date'] ?? $entry['date'] ?? date('Y-m-d');
                    $entry['notes'] = $body['notes'] ?? '';
                    $entry['updatedAt'] = date('c');
                    $updated = true;
                    $result = $entry;
                    break;
                }
            }
            if (!$updated) {
                errorResponse('Transaction not found', 404);
            }
        } else {
            $result = [
                'id' => $db->generateId(),
                'type' => $body['type'] ?? 'expense',
                'description' => $body['description'],
                'amount' => floatval($body['amount']),
                'category' => $body['category'] ?? 'Other',
                'date' => $body['date'] ?? date('Y-m-d'),
                'notes' => $body['notes'] ?? '',
                'createdAt' => date('c')
            ];
            $finance[] = $result;
        }
        
        $db->save('finance', $finance);
        successResponse($result, 'Success');
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Entry ID required');
        }
        
        $finance = $db->load('finance');
        $filtered = array_filter($finance, fn($e) => $e['id'] !== $id);
        
        if (count($filtered) === count($finance)) {
            errorResponse('Entry not found', 404);
        }
        
        $db->save('finance', array_values($filtered));
        successResponse(null, 'Entry deleted');
        break;
        
    default:
        errorResponse('Method not allowed', 405);
}
