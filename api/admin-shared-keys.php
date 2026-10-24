<?php
/**
 * Admin Shared AI Keys API
 *
 * GET  → returns masked key state (configured + lastFour). Admin only.
 * POST → persists keys (partial allowed). Admin only + CSRF.
 *
 * Raw key material NEVER leaves this server — only the AI flow reads it
 * via AISharedKeysAPI::getRawKey() at request time.
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/AISharedKeysAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}
if (!Auth::isAdmin()) {
    errorResponse('Administrator access required', 403, 'FORBIDDEN');
    exit;
}

$api = new AISharedKeysAPI();
$method = requestMethod();

if ($method === 'GET') {
    successResponse($api->getMaskedKeys());
    exit;
}

if ($method === 'POST') {
    $body = getJsonBody();
    $csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrf)) {
        errorResponse('Invalid CSRF token', 403);
        exit;
    }

    $action = $_GET['action'] ?? null;

    if ($action === 'import') {
        try {
            $result = $api->importFromAdminSettings(Auth::userId());
        } catch (Throwable $e) {
            errorResponse($e->getMessage(), 400, 'IMPORT_FAILED');
            exit;
        }
        if (class_exists('Audit')) {
            try {
                Audit::logData('update', 'admin_shared_keys', 'admin_shared_keys', ['imported' => $result]);
            } catch (Throwable $e) {
                error_log('admin-shared-keys import audit failed: ' . $e->getMessage());
            }
        }
        $payload = $api->getMaskedKeys();
        $payload['imported'] = $result;
        successResponse($payload, 'Imported settings from your account');
        exit;
    }

    $allowed = ['groqApiKey', 'openrouterApiKey', 'geminiApiKey'];
    $updates = array_intersect_key($body, array_flip($allowed));
    if (empty($updates)) {
        errorResponse('No key fields provided', 400, 'VALIDATION');
        exit;
    }

    $ok = $api->saveKeys($updates, Auth::userId());
    if (!$ok) {
        errorResponse('Failed to persist admin shared keys', 500, 'SERVER');
        exit;
    }

    if (class_exists('Audit')) {
        try {
            Audit::logData(
                'update',
                'admin_shared_keys',
                'admin_shared_keys',
                ['providers' => array_keys($updates)]
            );
        } catch (Throwable $e) {
            error_log('admin-shared-keys audit failed: ' . $e->getMessage());
        }
    }

    successResponse($api->getMaskedKeys(), 'Admin shared keys updated');
    exit;
}

errorResponse('Method not allowed', 405);
