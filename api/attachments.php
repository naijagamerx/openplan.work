<?php
/**
 * Attachments API Endpoint
 *
 * Handles file attachments for tasks with:
 * - Upload with validation
 * - Download with access control
 * - Delete operations
 * - Thumbnail generation for images
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Attachment.php';

if (!Auth::check()) {
    if (isAjax()) {
        errorResponse('Unauthorized', 401);
    }
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$db = new Database(getMasterPassword(), Auth::userId());
$attachment = new Attachment($db);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// CSRF validation for write operations
$writeActions = ['upload', 'delete', 'update'];
if (in_array($action, $writeActions)) {
    $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!Auth::validateCsrf($csrfToken)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

switch ($action) {
    case 'upload':
        handleUpload();
        break;

    case 'list':
        handleList();
        break;

    case 'download':
        handleDownload();
        break;

    case 'delete':
        handleDelete();
        break;

    case 'get':
        handleGet();
        break;

    default:
        errorResponse('Invalid action. Supported: upload, list, download, delete, get', 400);
}

/**
 * Handle file upload
 */
function handleUpload() {
    global $attachment;

    if (requestMethod() !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $taskId = $_POST['task_id'] ?? '';
    if (empty($taskId)) {
        errorResponse('Task ID is required', 400);
    }

    // Verify task exists
    $db = new Database(getMasterPassword(), Auth::userId());
    $projects = $db->load('projects', true);
    $taskExists = false;

    foreach ($projects as $project) {
        if (!empty($project['tasks'])) {
            foreach ($project['tasks'] as $task) {
                if ($task['id'] === $taskId) {
                    $taskExists = true;
                    break 2;
                }
            }
        }
    }

    if (!$taskExists) {
        errorResponse('Task not found', 404);
    }

    // Validate file
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMsg = upload_error_message($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE);
        errorResponse($errorMsg, 400);
    }

    $file = $_FILES['file'];

    // Validate file type
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain', 'text/csv', 'text/markdown',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-zip-compressed'
    ];

    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($file['size'] > $maxSize) {
        errorResponse('File size exceeds 10MB limit', 400);
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        errorResponse('File type not allowed', 400);
    }

    try {
        $result = $attachment->upload($file, $taskId);

        // Log the action
        Audit::logData('create', 'attachment', $result['id'], [
            'filename' => $result['filename'],
            'size' => $result['size'],
            'task_id' => $taskId
        ]);

        successResponse($result, 'File uploaded successfully');
    } catch (Exception $e) {
        errorResponse('Upload failed: ' . $e->getMessage(), 500);
    }
}

/**
 * List attachments for a task
 */
function handleList() {
    global $attachment;

    $taskId = $_GET['task_id'] ?? '';
    if (empty($taskId)) {
        errorResponse('Task ID is required', 400);
    }

    $attachments = $attachment->getByTaskId($taskId);
    successResponse($attachments, 'Attachments retrieved successfully');
}

/**
 * Download file
 */
function handleDownload() {
    global $attachment;

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        errorResponse('Attachment ID is required', 400);
    }

    $attachmentData = $attachment->getById($id);
    if (!$attachmentData) {
        errorResponse('Attachment not found', 404);
    }

    $attachment->download($id);
}

/**
 * Get single attachment
 */
function handleGet() {
    global $attachment;

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        errorResponse('Attachment ID is required', 400);
    }

    $attachmentData = $attachment->getById($id);
    if (!$attachmentData) {
        errorResponse('Attachment not found', 404);
    }

    successResponse($attachmentData, 'Attachment retrieved successfully');
}

/**
 * Delete attachment
 */
function handleDelete() {
    global $attachment;

    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        errorResponse('Attachment ID is required', 400);
    }

    $attachmentData = $attachment->getById($id);
    if (!$attachmentData) {
        errorResponse('Attachment not found', 404);
    }

    $result = $attachment->delete($id);

    Audit::logData('delete', 'attachment', $id, [
        'filename' => $attachmentData['filename']
    ]);

    successResponse(['deleted' => $result], 'Attachment deleted successfully');
}

/**
 * Get human-readable upload error message
 */
function upload_error_message($errorCode) {
    $messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
    ];

    return $messages[$errorCode] ?? 'Unknown upload error';
}

