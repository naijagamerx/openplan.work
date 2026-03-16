<?php
/**
 * Deep Migration API (Admin only)
 *
 * Actions:
 * - GET  ?action=users
 * - GET  ?action=progress&job_id=...
 * - GET  ?action=export_user&user_id=...&include_shared_media=1
 * - POST ?action=preview   (multipart: package_file, source_master_password, target_user_id?)
 * - POST ?action=execute   (json: job_id, strategy, source_master_password, target_user_id?, include_shared_media?)
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401, ERROR_UNAUTHORIZED);
}
Auth::requireAdmin();

$masterPassword = getMasterPassword();
if ($masterPassword === '') {
    errorResponse('Master password not available in this session', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? '';
$migration = new DeepMigration($masterPassword);

try {
    if ($action === 'users') {
        successResponse(['users' => $migration->getUsers()], 'Users loaded');
    }

    if ($action === 'progress') {
        $jobId = trim((string)($_GET['job_id'] ?? ''));
        if ($jobId === '') {
            errorResponse('job_id is required', 400, ERROR_VALIDATION);
        }
        successResponse($migration->getProgress($jobId), 'Progress loaded');
    }

    if ($action === 'export_user') {
        $userId = trim((string)($_GET['user_id'] ?? ''));
        if ($userId === '') {
            errorResponse('user_id is required', 400, ERROR_VALIDATION);
        }

        $includeSharedMediaRaw = strtolower(trim((string)($_GET['include_shared_media'] ?? '1')));
        $includeSharedMedia = !in_array($includeSharedMediaRaw, ['0', 'false', 'no'], true);

        $result = $migration->exportUserPackage($userId, $includeSharedMedia);
        [$tempPath, $downloadName] = explode('::', $result, 2);
        if (!is_file($tempPath)) {
            errorResponse('Failed to prepare export file', 500, ERROR_SERVER);
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
        header('Content-Length: ' . filesize($tempPath));
        header('X-Content-Type-Options: nosniff');
        readfile($tempPath);
        @unlink($tempPath);
        exit;
    }

    if ($action === 'preview') {
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403, ERROR_FORBIDDEN);
        }

        if (!isset($_FILES['package_file']) || ($_FILES['package_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $uploadCode = $_FILES['package_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $message = match ($uploadCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Package is too large for this server upload limit',
                UPLOAD_ERR_PARTIAL => 'Upload was interrupted',
                default => 'Package file is required'
            };
            errorResponse($message, 400, ERROR_VALIDATION);
        }

        $sourceMasterPassword = trim((string)($_POST['source_master_password'] ?? ''));
        $targetUserId = trim((string)($_POST['target_user_id'] ?? ''));
        $targetUserId = $targetUserId === '' ? null : $targetUserId;

        $preview = $migration->previewUploadedPackage(
            $_FILES['package_file']['tmp_name'],
            (string)($_FILES['package_file']['name'] ?? 'migration.zip'),
            $sourceMasterPassword,
            $targetUserId
        );

        successResponse($preview, 'Preview complete');
    }

    if ($action === 'execute') {
        if (requestMethod() !== 'POST') {
            errorResponse('Method not allowed', 405);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403, ERROR_FORBIDDEN);
        }

        $jobId = trim((string)($body['job_id'] ?? ''));
        $strategy = trim((string)($body['strategy'] ?? ''));
        $sourceMasterPassword = trim((string)($body['source_master_password'] ?? ''));
        $targetUserId = trim((string)($body['target_user_id'] ?? ''));
        $targetUserId = $targetUserId === '' ? null : $targetUserId;

        $includeSharedMediaRaw = strtolower(trim((string)($body['include_shared_media'] ?? '1')));
        $includeSharedMedia = !in_array($includeSharedMediaRaw, ['0', 'false', 'no'], true);

        if ($jobId === '') {
            errorResponse('job_id is required', 400, ERROR_VALIDATION);
        }
        if ($sourceMasterPassword === '') {
            errorResponse('source_master_password is required', 400, ERROR_VALIDATION);
        }

        // Release session lock so progress polling can read state while this runs.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $result = $migration->executeMigration(
            $jobId,
            $strategy,
            $sourceMasterPassword,
            $targetUserId,
            $includeSharedMedia
        );
        successResponse($result, 'Migration completed');
    }

    errorResponse('Invalid action. Supported: users, progress, export_user, preview, execute', 400, ERROR_VALIDATION);
} catch (Exception $e) {
    errorResponse($e->getMessage(), 400, ERROR_VALIDATION);
}

