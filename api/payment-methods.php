<?php
/**
 * Payment Methods API — global catalog of admin-configured payment methods
 * (crypto wallets + bank accounts).
 *
 * GET  → any logged-in user can list (for checkout).
 * POST / PUT / DELETE → admin only.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/PaymentMethodsAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$db = new Database(APP_NAME);
$methodsAPI = new PaymentMethodsAPI($db, 'payment_methods');
$method = requestMethod();
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($id) {
        $row = $methodsAPI->find($id);
        if ($row) {
            successResponse($row);
        }
        errorResponse('Payment method not found', 404, 'NOT_FOUND');
        exit;
    }
    $filters = [];
    if (!empty($_GET['activeOnly'])) {
        $filters['activeOnly'] = true;
    }
    if (!empty($_GET['type'])) {
        $filters['type'] = $_GET['type'];
    }
    successResponse($methodsAPI->findAll($filters));
    exit;
}

if (!Auth::isAdmin()) {
    errorResponse('Administrator access required', 403, 'FORBIDDEN');
    exit;
}

$body = getJsonBody();
$csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['csrf_token'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
    errorResponse('Invalid CSRF token', 403);
    exit;
}

switch ($method) {
    case 'POST':
        if ($action === 'update' && $id) {
            $row = $methodsAPI->update($id, $body);
            if ($row) {
                successResponse($row, 'Payment method updated');
            }
            errorResponse('Payment method not found', 404, 'NOT_FOUND');
            exit;
        }
        $err = $methodsAPI->validateRequired($body, ['type', 'label']);
        if ($err) {
            errorResponse($err, 400, 'VALIDATION');
        }
        $row = $methodsAPI->create($body);
        if ($row) {
            if (class_exists('Audit')) {
                Audit::logData('create', 'payment_method', $row['id'], ['type' => $row['type'], 'label' => $row['label']]);
            }
            successResponse($row, 'Payment method created');
        }
        errorResponse('Failed to create payment method', 500, 'SERVER');
        break;

    case 'PUT':
    case 'PATCH':
        if (!$id) {
            errorResponse('Payment method ID required', 400, 'VALIDATION');
        }
        $row = $methodsAPI->update($id, $body);
        if ($row) {
            if (class_exists('Audit')) {
                Audit::logData('update', 'payment_method', $id, array_keys($body));
            }
            successResponse($row, 'Payment method updated');
        }
        errorResponse('Payment method not found', 404, 'NOT_FOUND');
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Payment method ID required', 400, 'VALIDATION');
        }
        if ($methodsAPI->delete($id)) {
            if (class_exists('Audit')) {
                Audit::logData('delete', 'payment_method', $id);
            }
            successResponse(null, 'Payment method deleted');
        }
        errorResponse('Payment method not found', 404, 'NOT_FOUND');
        break;

    default:
        errorResponse('Method not allowed', 405);
}
