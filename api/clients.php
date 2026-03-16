<?php
/**
 * Clients API Endpoint
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
    $db->load('clients', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

/**
 * Clients API - uses BaseAPI for consistent CRUD operations
 */
class ClientsAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return ['name', 'email', 'phone', 'company', 'address', 'website', 'notes'];
    }
}

$clientsAPI = new ClientsAPI($db, 'clients');

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $client = $clientsAPI->find($id);
            if ($client) {
                successResponse($client);
            }
            $clientsAPI->notFound('Client');
        }
        successResponse($clientsAPI->findAll());
        break;

    case 'POST':
        $body = getJsonBody();

        if ($action === 'update' && $id) {
            $client = $clientsAPI->update($id, $body);
            if ($client) {
                successResponse($client, 'Client updated');
            }
            $clientsAPI->notFound('Client');
        }

        // Create new client
        $validationError = $clientsAPI->validateRequired($body, ['name', 'email']);
        if ($validationError) {
            $clientsAPI->validationError($validationError);
        }

        $client = $clientsAPI->create($body);
        if ($client) {
            successResponse($client, 'Client added');
        }

        errorResponse('Failed to create client', 500, ERROR_SERVER);
        break;

    case 'PUT':
        if (!$id) {
            errorResponse('Client ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $client = $clientsAPI->update($id, $body);

        if ($client) {
            successResponse($client, 'Client updated');
        }

        $clientsAPI->notFound('Client');
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Client ID required', 400, ERROR_VALIDATION);
        }

        if ($clientsAPI->delete($id)) {
            successResponse(null, 'Client deleted');
        }

        $clientsAPI->notFound('Client');
        break;

    default:
        errorResponse('Method not allowed', 405);
}

