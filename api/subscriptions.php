<?php
/**
 * Subscriptions API
 *
 * GET   /api/subscriptions.php — caller's subscriptions (admin: ?all=1 lists all).
 * GET   /api/subscriptions.php?id=X — specific sub (owner or admin).
 * POST  /api/subscriptions.php?id=X&action=cancel — admin only.
 * POST  /api/subscriptions.php?id=X&action=extend  body: { days } — admin only.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/SubscriptionsAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$api = new SubscriptionsAPI();
$method = requestMethod();
$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($id) {
        $sub = $api->find($id);
        if (!$sub) {
            errorResponse('Subscription not found', 404, 'NOT_FOUND');
            exit;
        }
        if (($sub['userId'] ?? '') !== Auth::userId() && !Auth::isAdmin()) {
            errorResponse('Forbidden', 403, 'FORBIDDEN');
            exit;
        }
        successResponse($sub);
        exit;
    }
    if (!empty($_GET['all']) && Auth::isAdmin()) {
        successResponse($api->getAll());
    } else {
        successResponse(['active' => $api->getActiveByUser(Auth::userId()), 'history' => $api->getByUser(Auth::userId())]);
    }
    exit;
}

if ($method !== 'POST') {
    errorResponse('Method not allowed', 405);
    exit;
}

if (!Auth::isAdmin()) {
    errorResponse('Administrator access required', 403, 'FORBIDDEN');
    exit;
}
if (!$id) {
    errorResponse('Subscription ID required', 400, 'VALIDATION');
}

$body = getJsonBody();
$csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
    errorResponse('Invalid CSRF token', 403);
    exit;
}

if ($action === 'cancel') {
    $sub = $api->cancel($id, Auth::userId());
    if ($sub) {
        successResponse($sub, 'Subscription cancelled');
    }
    errorResponse('Subscription not found', 404, 'NOT_FOUND');
    exit;
}

if ($action === 'extend') {
    $days = (int)($body['days'] ?? 0);
    if ($days <= 0) {
        errorResponse('Days must be a positive integer', 400, 'VALIDATION');
    }
    $sub = $api->extend($id, $days, Auth::userId());
    if ($sub) {
        successResponse($sub, "Subscription extended by {$days} days");
    }
    errorResponse('Subscription not found', 404, 'NOT_FOUND');
    exit;
}

errorResponse('Unknown action', 400, 'VALIDATION');
