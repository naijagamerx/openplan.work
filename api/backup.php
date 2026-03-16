<?php
/**
 * Backup API Endpoint
 *
 * Provides REST API for backup operations:
 *   GET  /api/backup.php?action=list           - List all backups
 *   GET  /api/backup.php?action=stats          - Get backup statistics
 *   POST /api/backup.php?action=create         - Create new backup
 *   POST /api/backup.php?action=restore        - Restore from backup
 *   POST /api/backup.php?action=upload_restore - Upload and restore backup file
 *   POST /api/backup.php?action=delete         - Delete a backup
 *   POST /api/backup.php?action=cleanup        - Run retention cleanup
 *   GET  /api/backup.php?action=download&file= - Download a backup
 */

require_once __DIR__ . '/../config.php';

$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput ?: 'null', true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

// Authentication check
if (!Auth::check()) {
    if (isAjax()) {
        errorResponse('Unauthorized', 401);
    }
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

// CSRF validation for write operations
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$writeActions = ['create', 'restore', 'upload_restore', 'delete', 'cleanup'];

if (in_array($action, $writeActions) && !Auth::isMcp()) {
    // Check GET, POST, header, and JSON body for CSRF token
    $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';

    if (empty($csrfToken)) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }

    if (empty($csrfToken)) {
        $csrfToken = $jsonInput['csrf_token'] ?? '';
    }

    if (!Auth::validateCsrf($csrfToken)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());
$backup = new Backup($db);

// Handle different actions
switch ($action) {
    case 'list':
        $backups = $backup->getBackupList();
        successResponse([
            'backups' => $backups,
            'count' => count($backups)
        ], 'Backups retrieved successfully');
        break;

    case 'stats':
        $stats = $backup->getStats();
        successResponse($stats, 'Statistics retrieved successfully');
        break;

    case 'create':
        $type = $_POST['type'] ?? $_GET['type'] ?? 'full';

        // Validate type
        if (!in_array($type, ['full', 'incremental'])) {
            $type = 'full';
        }

        $result = $backup->createBackup($type);

        if ($result['success']) {
            successResponse($result, 'Backup created successfully');
        } else {
            errorResponse($result['error'] ?? 'Failed to create backup', 500);
        }
        break;

    case 'restore':
        $filename = $jsonInput['filename'] ?? $_POST['filename'] ?? $_GET['filename'] ?? '';

        if (empty($filename)) {
            errorResponse('Filename is required', 400);
        }

        $result = $backup->restoreBackup($filename);

        if ($result['success']) {
            successResponse($result, 'Backup restored successfully');
        } else {
            errorResponse($result['error'] ?? 'Failed to restore backup', 500);
        }
        break;

    case 'upload_restore':
        // Handle file upload for external backup migration
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadError = $_FILES['backup_file']['error'] ?? null;
            $errorMsg = match($uploadError) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large. Maximum size is 100MB.',
                UPLOAD_ERR_NO_FILE => 'No file uploaded.',
                UPLOAD_ERR_PARTIAL => 'File upload was interrupted.',
                default => 'Invalid file upload.'
            };
            errorResponse($errorMsg, 400);
        }

        $uploadedFile = $_FILES['backup_file']['tmp_name'];
        $originalFilename = $_FILES['backup_file']['name'];

        $result = $backup->restoreFromUpload($uploadedFile, $originalFilename);

        if ($result['success']) {
            successResponse($result, 'Backup uploaded and restored successfully');
        } else {
            errorResponse($result['error'] ?? 'Failed to restore backup', 500);
        }
        break;

    case 'delete':
        $filename = $jsonInput['filename'] ?? $_POST['filename'] ?? $_GET['filename'] ?? '';

        if (empty($filename)) {
            errorResponse('Filename is required', 400);
        }

        $result = $backup->deleteBackup($filename);

        if ($result['success']) {
            successResponse($result, 'Backup deleted successfully');
        } else {
            errorResponse($result['error'] ?? 'Failed to delete backup', 500);
        }
        break;

    case 'cleanup':
        $keep = isset($jsonInput['keep']) && is_numeric($jsonInput['keep']) ? (int)$jsonInput['keep'] : 7;
        $typeRaw = strtolower(trim((string)($jsonInput['type'] ?? $_POST['type'] ?? $_GET['type'] ?? 'all')));

        if ($typeRaw === 'auto') {
            $deletedCount = $backup->cleanupOldBackups('auto', $keep);
            successResponse(['deleted_count' => $deletedCount, 'deleted_files' => []], 'Cleanup completed successfully');
            break;
        }

        $types = null;
        if ($typeRaw !== 'all') {
            $types = [$typeRaw];
        }

        $result = $backup->cleanupBackupsByType($keep, $types);
        successResponse($result, 'Cleanup completed successfully');
        break;

    case 'download':
        $filename = $_GET['filename'] ?? '';

        if (empty($filename)) {
            errorResponse('Filename is required', 400);
        }

        $filepath = DATA_PATH . '/backups/' . basename($filename);

        if (!file_exists($filepath)) {
            errorResponse('Backup file not found', 404);
        }

        // Send file for download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');

        readfile($filepath);
        exit;
        break;

    default:
        errorResponse('Invalid action. Supported actions: list, stats, create, restore, upload_restore, delete, cleanup, download', 400);
}
