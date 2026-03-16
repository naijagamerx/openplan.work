<?php
/**
 * Wipe All Data API
 * DANGER: This permanently deletes ALL user data
 *
 * This endpoint should only be used with extreme caution.
 * Multiple safety mechanisms are in place to prevent accidental data loss.
 */

require_once __DIR__ . '/../config.php';

// Authentication check
if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// CSRF validation
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::validateCsrf($csrfToken)) {
    errorResponse('Invalid CSRF token', 403);
}

$method = requestMethod();
if ($method !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$body = getJsonBody();
$password = $body['password'] ?? '';
$confirmation = $body['confirmation'] ?? '';
$createBackup = $body['create_backup'] ?? false;
$keepMusicRaw = $body['keep_music'] ?? true;
$keepMusic = filter_var($keepMusicRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($keepMusic === null) {
    $keepMusic = true;
}

// Validate password
if (empty($password)) {
    errorResponse('Password is required', 400, ERROR_VALIDATION);
}

// Verify password against current user
$user = Auth::user();
if (!$user) {
    errorResponse('Unauthorized', 401, ERROR_UNAUTHORIZED);
}

$db = new Database(getMasterPassword(), Auth::userId());
$userRecords = $db->load('users', true);
$currentUserRecord = null;
foreach ($userRecords as $record) {
    if (($record['id'] ?? '') === ($user['id'] ?? '')) {
        $currentUserRecord = $record;
        break;
    }
}

if (!$currentUserRecord || empty($currentUserRecord['passwordHash']) || !Encryption::verifyPassword($password, $currentUserRecord['passwordHash'])) {
    errorResponse('Invalid password', 401, ERROR_UNAUTHORIZED);
}

// Validate confirmation text - must match exactly
if ($confirmation !== 'DELETE ALL DATA') {
    errorResponse('Confirmation text must be exactly "DELETE ALL DATA"', 400, ERROR_VALIDATION);
}

$backupFile = null;

// Create pre-wipe backup if requested
if ($createBackup) {
    try {
        $backup = new Backup($db);
        $result = $backup->createBackup('pre-wipe', 'Backup before data wipe');

        if (!$result['success']) {
            errorResponse('Backup creation failed: ' . ($result['error'] ?? 'Unknown error'), 500, ERROR_SERVER);
        }

        $backupFile = $result['filename'] ?? null;
    } catch (Exception $e) {
        errorResponse('Backup creation failed: ' . $e->getMessage(), 500, ERROR_SERVER);
    }
}

// Get all encrypted data files
$files = glob(DATA_PATH . '/*.json.enc');
$deleted = [];
$failed = [];

foreach ($files as $file) {
    $filename = basename($file);
    if ($keepMusic && $filename === 'pomodoro_music.json.enc') {
        continue;
    }
    if (unlink($file)) {
        $deleted[] = $filename;
    } else {
        $failed[] = $filename;
    }
}

$musicFilesDeleted = 0;
$musicFilesFailed = 0;
if (!$keepMusic) {
    $musicUploadDir = DATA_PATH . '/uploads/pomodoro';
    if (is_dir($musicUploadDir)) {
        $musicFiles = glob($musicUploadDir . '/*');
        foreach ($musicFiles ?: [] as $musicFile) {
            if (!is_file($musicFile)) {
                continue;
            }
            if (unlink($musicFile)) {
                $musicFilesDeleted++;
            } else {
                $musicFilesFailed++;
            }
        }
    }
}

// Log to audit if available
if (class_exists('Audit')) {
    $audit = new Audit($db);
    $audit->log('system.wipe_all_data', [
        'resource_type' => 'system',
        'resource_id' => 'wipe_all_data',
        'details' => [
            'user_id' => $user['id'],
            'user_email' => $user['email'],
            'user_name' => $user['name'] ?? '',
            'deleted_count' => count($deleted),
            'deleted_files' => $deleted,
            'failed_count' => count($failed),
            'failed_files' => $failed,
            'keep_music' => $keepMusic,
            'music_files_deleted' => $musicFilesDeleted,
            'music_files_failed' => $musicFilesFailed,
            'backup_created' => $backupFile !== null,
            'backup_file' => $backupFile,
            'timestamp' => gmdate('c')
        ]
    ]);
}

// Return success response
successResponse([
    'deleted' => count($deleted),
    'failed' => count($failed),
    'files' => $deleted,
    'keep_music' => $keepMusic,
    'music_files_deleted' => $musicFilesDeleted,
    'music_files_failed' => $musicFilesFailed,
    'backup_file' => $backupFile
], 'All data wiped successfully');

