<?php
/**
 * Payments API
 *
 * POST   /api/payments.php (multipart) — submit a payment with proof file.
 *        Form fields: planId, methodId, proof (file), csrf_token.
 * GET    /api/payments.php — caller's own payments (admin can use ?all=1 + ?status=)
 * GET    /api/payments.php?id=X — single payment (owner or admin only)
 * POST   /api/payments.php?id=X&action=approve — admin only (note field optional)
 * POST   /api/payments.php?id=X&action=reject — admin only (reason field required)
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/PaymentsAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$api = new PaymentsAPI();
$method = requestMethod();
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($id) {
        $payment = $api->find($id);
        if (!$payment) {
            errorResponse('Payment not found', 404, 'NOT_FOUND');
            exit;
        }
        if (($payment['userId'] ?? '') !== Auth::userId() && !Auth::isAdmin()) {
            errorResponse('Forbidden', 403, 'FORBIDDEN');
            exit;
        }
        successResponse($payment);
        exit;
    }

    $isAdminAll = !empty($_GET['all']) && Auth::isAdmin();
    if ($isAdminAll) {
        $status = $_GET['status'] ?? null;
        successResponse($api->getAll($status));
    } else {
        successResponse($api->getByUser(Auth::userId()));
    }
    exit;
}

if ($method !== 'POST') {
    errorResponse('Method not allowed', 405);
    exit;
}

// Approval / rejection (admin)
if ($action === 'approve' || $action === 'reject') {
    if (!Auth::isAdmin()) {
        errorResponse('Administrator access required', 403, 'FORBIDDEN');
        exit;
    }
    if (!$id) {
        errorResponse('Payment ID required', 400, 'VALIDATION');
    }
    $body = getJsonBody();
    $csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
        errorResponse('Invalid CSRF token', 403);
        exit;
    }
    try {
        if ($action === 'approve') {
            $result = $api->approve($id, Auth::userId(), (string)($body['note'] ?? ''));
            successResponse($result, 'Payment approved');
        } else {
            $reason = trim((string)($body['reason'] ?? ''));
            if ($reason === '') {
                errorResponse('Rejection reason required', 400, 'VALIDATION');
            }
            $payment = $api->reject($id, Auth::userId(), $reason);
            successResponse($payment, 'Payment rejected');
        }
    } catch (Throwable $e) {
        errorResponse($e->getMessage(), 400, 'ERROR');
    }
    exit;
}

// Submit new payment (multipart form, NOT JSON)
$csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
    errorResponse('Invalid CSRF token', 403);
    exit;
}
$planId = (string)($_POST['planId'] ?? '');
$methodId = (string)($_POST['methodId'] ?? '');
if ($planId === '' || $methodId === '') {
    errorResponse('Plan and payment method are required', 400, 'VALIDATION');
}
if (empty($_FILES['proof'])) {
    errorResponse('Payment proof file is required', 400, 'VALIDATION');
}

try {
    $payment = $api->submit(Auth::userId(), $planId, $methodId, $_FILES['proof']);
    successResponse($payment, 'Payment submitted');
} catch (Throwable $e) {
    errorResponse($e->getMessage(), 400, 'ERROR');
}
