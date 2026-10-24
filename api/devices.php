<?php
/**
 * Devices API — manage per-device mobile sessions (device tokens).
 *
 * Works for both auth modes:
 *  - Web session: the Settings → Devices screen (CSRF required).
 *  - Device token: the mobile app managing its own sessions (CSRF exempt — the
 *    bearer token is not an ambient cookie, so CSRF is structurally N/A).
 *
 * Actions:
 *  GET  ?action=list              → { devices: [...] }
 *  POST ?action=revoke  { id }    → revoke one device (scoped to current user)
 *  POST ?action=rename  { id,name}→ rename one device
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../data/php_error.log');
ini_set('display_errors', 0);

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$userId = Auth::userId();
if (!$userId) {
    errorResponse('Unauthorized', 401);
}

$action = $_GET['action'] ?? 'list';

// Read the request body once (php://input is not always re-readable).
$reqBody = (requestMethod() !== 'GET') ? getJsonBody() : [];

// CSRF for non-GET, except token-auth callers (mobile app with bearer token).
if (requestMethod() !== 'GET' && !Auth::isTokenAuth()) {
    $csrf = $reqBody['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($csrf)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

switch ($action) {
    case 'list':
        successResponse(['devices' => DeviceTokens::listForUser($userId)]);
        break;

    case 'revoke':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        $id = trim((string)($reqBody['id'] ?? ''));
        if ($id === '') {
            errorResponse('Device id required');
        }
        if (!DeviceTokens::revoke($userId, $id)) {
            errorResponse('Device not found', 404);
        }
        successResponse(null, 'Device revoked');
        break;

    case 'rename':
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        $id = trim((string)($reqBody['id'] ?? ''));
        $name = trim((string)($reqBody['name'] ?? ''));
        if ($id === '' || $name === '') {
            errorResponse('Device id and name required');
        }
        if (!DeviceTokens::rename($userId, $id, $name)) {
            errorResponse('Device not found', 404);
        }
        successResponse(null, 'Device renamed');
        break;

    default:
        errorResponse('Unknown action', 400);
}
