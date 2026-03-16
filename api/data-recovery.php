<?php
/**
 * Data Recovery API
 * Handles diagnostic and recovery operations for locked collections
 *
 * @author TaskManager
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';

// Check authentication
if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// Validate CSRF for non-GET requests
if (requestMethod() !== 'GET') {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$action = $_GET['action'] ?? null;
$method = requestMethod();

switch ($action) {
    case 'diagnostic':
        if ($method !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        handleDiagnostic();
        break;

    case 'recover':
        if ($method !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        handleRecover();
        break;

    default:
        errorResponse('Invalid action', 400);
}

/**
 * Handle diagnostic request
 * Checks which collections can be decrypted with current master password
 */
function handleDiagnostic() {
    $db = new Database(getMasterPassword(), Auth::userId());
    $collections = [
        'notes', 'knowledge-base', 'advanced_invoices',
        'pomodoro_sessions', 'pomodoro_music', 'todos'
    ];

    $results = [];

    foreach ($collections as $collection) {
        $filePath = DATA_PATH . '/' . $collection . '.json.enc';

        if (!file_exists($filePath)) {
            $results[] = [
                'collection' => $collection,
                'accessible' => true,
                'record_count' => 0,
                'file_exists' => false,
                'file_size' => 0
            ];
            continue;
        }

        $fileSize = filesize($filePath);

        // Try to load with current password
        try {
            $data = $db->load($collection);

            // Check if data is empty (could indicate decryption failure)
            if (empty($data) && $fileSize > 100) {
                // File exists and has content but load returned empty
                // This likely means decryption failed silently
                $results[] = [
                    'collection' => $collection,
                    'accessible' => false,
                    'record_count' => null,
                    'file_exists' => true,
                    'file_size' => $fileSize,
                    'error' => 'Locked with old password'
                ];
            } else {
                $results[] = [
                    'collection' => $collection,
                    'accessible' => true,
                    'record_count' => is_array($data) ? count($data) : 0,
                    'file_exists' => true,
                    'file_size' => $fileSize
                ];
            }
        } catch (Exception $e) {
            $results[] = [
                'collection' => $collection,
                'accessible' => false,
                'record_count' => null,
                'file_exists' => true,
                'file_size' => $fileSize,
                'error' => $e->getMessage()
            ];
        }
    }

    successResponse(['collections' => $results], 'Diagnostic complete');
}

/**
 * Handle recovery request
 * Recovers collections locked with old password
 */
function handleRecover() {
    $body = getJsonBody();
    $oldPassword = $body['old_password'] ?? '';
    $collections = $body['collections'] ?? [];

    if (empty($oldPassword)) {
        errorResponse('Old password is required', 400, ERROR_VALIDATION);
    }

    if (empty($collections)) {
        errorResponse('No collections specified', 400, ERROR_VALIDATION);
    }

    // Validate collections list
    $allowedCollections = [
        'notes', 'knowledge-base', 'advanced_invoices',
        'pomodoro_sessions', 'pomodoro_music', 'todos'
    ];

    foreach ($collections as $collection) {
        if (!in_array($collection, $allowedCollections)) {
            errorResponse("Invalid collection: {$collection}", 400, ERROR_VALIDATION);
        }
    }

    // Create database instances
    try {
        $oldDb = new Database($oldPassword);
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        // Provide more helpful error message
        if (strpos($errorMsg, 'Decryption failed') !== false || strpos($errorMsg, 'invalid key') !== false) {
            errorResponse('The password entered cannot decrypt any data files. Please enter the OLD master password that was originally used to encrypt the locked collection. If you never changed your master password, the file may be corrupted and cannot be recovered.', 400, ERROR_VALIDATION);
        }
        errorResponse('Failed to create database with old password: ' . $errorMsg, 400, ERROR_VALIDATION);
    }

    $newDb = new Database(getMasterPassword(), Auth::userId());

    $recovered = [];
    $failed = [];

    foreach ($collections as $collection) {
        try {
            // Load with old password
            $data = $oldDb->load($collection);

            if (empty($data)) {
                // Check if file exists but returns empty (decryption failed)
                $filePath = DATA_PATH . '/' . $collection . '.json.enc';
                if (file_exists($filePath) && filesize($filePath) > 100) {
                    $failed[] = [
                        'collection' => $collection,
                        'error' => 'Old password is incorrect or data is corrupted. Cannot recover this file.'
                    ];
                    continue;
                }
            }

            // Save with new password
            if ($newDb->save($collection, $data)) {
                $count = is_array($data) ? count($data) : 1;
                $recovered[] = [
                    'collection' => $collection,
                    'count' => $count
                ];
            } else {
                $failed[] = [
                    'collection' => $collection,
                    'error' => 'Failed to save with new password'
                ];
            }
        } catch (Exception $e) {
            $failed[] = [
                'collection' => $collection,
                'error' => $e->getMessage()
            ];
        }
    }

    if (count($recovered) > 0) {
        successResponse([
            'recovered' => $recovered,
            'failed' => $failed
        ], 'Recovery complete');
    } else {
        errorResponse('Recovery failed: ' . ($failed[0]['error'] ?? 'Unknown error'), 400, ERROR_SERVER);
    }
}
