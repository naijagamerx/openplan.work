<?php
/**
 * Knowledge Base API
 *
 * RESTful API for managing knowledge base folders and files.
 * All data stored in encrypted JSON format.
 *
 * @author TaskManager
 * @version 1.0.0
 */

require_once __DIR__ . '/../config.php';

// Check authentication
if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// Validate CSRF for non-GET requests (bypass for MCP)
if (requestMethod() !== 'GET' && !Auth::isMcp()) {
    // For multipart/form-data (file uploads), check $_POST
    // For JSON requests, check the JSON body or header
    if (requestMethod() === 'POST' && isset($_FILES['file'])) {
        // File upload - token is in $_POST
        $token = $_POST['csrf_token'] ?? '';
    } else {
        // JSON request - token is in JSON body or header
        $body = getJsonBody();
        $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

// Initialize database
$db = new Database(getMasterPassword(), Auth::userId());

// Verify database connection works
try {
    $db->load('knowledge-base', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

// Get action and parameters
$action = $_GET['action'] ?? null;
$method = requestMethod();

// Route requests based on action
switch ($action) {
    // ==================== FOLDER OPERATIONS ====================

    case 'list_folders':
        if ($method !== 'GET') {
            errorResponse('Method not allowed', 405);
        }
        handleListFolders($db);
        break;

    case 'create_folder':
        if ($method !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        handleCreateFolder($db);
        break;

    case 'update_folder':
        if ($method !== 'PUT') {
            errorResponse('Method not allowed', 405);
        }
        handleUpdateFolder($db);
        break;

    case 'delete_folder':
        if ($method !== 'DELETE') {
            errorResponse('Method not allowed', 405);
        }
        handleDeleteFolder($db);
        break;

    // ==================== FILE OPERATIONS ====================

    case 'list_files':
        if ($method !== 'GET') {
            errorResponse('Method not allowed', 405);
        }
        handleListFiles($db);
        break;

    case 'upload_file':
        if ($method !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        handleUploadFile($db);
        break;

    case 'get_file':
        if ($method !== 'GET') {
            errorResponse('Method not allowed', 405);
        }
        handleGetFile($db);
        break;

    case 'update_file':
        if ($method !== 'PUT') {
            errorResponse('Method not allowed', 405);
        }
        handleUpdateFile($db);
        break;

    case 'delete_file':
        if ($method !== 'DELETE') {
            errorResponse('Method not allowed', 405);
        }
        handleDeleteFile($db);
        break;

    case 'move_file':
        if ($method !== 'POST') {
            errorResponse('Method not allowed', 405);
        }
        handleMoveFile($db);
        break;

    // ==================== SEARCH ====================

    case 'search':
        if ($method !== 'GET') {
            errorResponse('Method not allowed', 405);
        }
        handleSearch($db);
        break;

    default:
        errorResponse('Invalid action', 400, ERROR_VALIDATION);
}

// ==================== FOLDER HANDLERS ====================

/**
 * Handle list folders request
 */
function handleListFolders($db) {
    $parentId = $_GET['parentId'] ?? null;

    // For MCP requests, get first user ID to use for data access
    // MCP bypasses normal authentication but we still need a user context
    $userId = Auth::isMcp() ? getFirstUserId($db) : Auth::userId();

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Filter folders by user and optional parent
    $folders = array_filter($kbData['folders'], function($folder) use ($userId, $parentId) {
        if ($folder['userId'] !== $userId) {
            return false;
        }
        if ($parentId !== null) {
            return $folder['parentId'] === $parentId;
        }
        return true;
    });

    // Re-index array
    $folders = array_values($folders);

    // Add file counts
    foreach ($folders as &$folder) {
        $fileCount = count(array_filter($kbData['files'], function($file) use ($folder) {
            return $file['folderId'] === $folder['id'];
        }));
        $folder['fileCount'] = $fileCount;
    }

    successResponse(['folders' => $folders], 'Folders retrieved');
}

/**
 * Handle create folder request
 */
function handleCreateFolder($db) {
    $userId = getUserId($db);
    $body = getJsonBody();

    // Validate folder name
    $name = trim($body['name'] ?? '');
    if (empty($name)) {
        errorResponse('Folder name is required', 400, ERROR_VALIDATION);
    }

    if (strlen($name) > 100) {
        errorResponse('Folder name must be 100 characters or less', 400, ERROR_VALIDATION);
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        errorResponse('Folder name must contain only letters, numbers, hyphens, and underscores', 400, ERROR_VALIDATION);
    }

    $parentId = $body['parentId'] ?? null;

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Check for duplicate name in same parent
    $duplicate = array_filter($kbData['folders'], function($folder) use ($name, $parentId, $userId) {
        return $folder['name'] === $name &&
               $folder['parentId'] === $parentId &&
               $folder['userId'] === $userId;
    });

    if (count($duplicate) > 0) {
        errorResponse('A folder with this name already exists in this location', 409, ERROR_VALIDATION);
    }

    // Validate parent folder exists
    if ($parentId !== null) {
        $parentExists = false;
        foreach ($kbData['folders'] as $folder) {
            if ($folder['id'] === $parentId && $folder['userId'] === $userId) {
                $parentExists = true;
                break;
            }
        }
        if (!$parentExists) {
            errorResponse('Parent folder not found', 404, ERROR_NOT_FOUND);
        }
    }

    // Create new folder
    $newFolder = [
        'id' => generateUuid(),
        'name' => $name,
        'parentId' => $parentId,
        'userId' => $userId,
        'createdAt' => gmdate('c'),
        'updatedAt' => gmdate('c')
    ];

    $kbData['folders'][] = $newFolder;

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    successResponse(['folder' => $newFolder], 'Folder created');
}

/**
 * Handle update folder request
 */
function handleUpdateFolder($db) {
    $userId = getUserId($db);
    $body = getJsonBody();

    // Validate input
    $id = $body['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        errorResponse('Folder ID is required', 400, ERROR_VALIDATION);
    }

    $name = trim($body['name'] ?? '');
    if (empty($name)) {
        errorResponse('Folder name is required', 400, ERROR_VALIDATION);
    }

    if (strlen($name) > 100) {
        errorResponse('Folder name must be 100 characters or less', 400, ERROR_VALIDATION);
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        errorResponse('Folder name must contain only letters, numbers, hyphens, and underscores', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Find folder
    $folderIndex = null;
    $folder = null;
    foreach ($kbData['folders'] as $index => $f) {
        if ($f['id'] === $id && $f['userId'] === $userId) {
            $folderIndex = $index;
            $folder = &$kbData['folders'][$index];
            break;
        }
    }

    if ($folder === null) {
        errorResponse('Folder not found', 404, ERROR_NOT_FOUND);
    }

    // Check for duplicate name (excluding current folder)
    $duplicate = false;
    foreach ($kbData['folders'] as $f) {
        if ($f['id'] !== $id &&
            $f['name'] === $name &&
            $f['parentId'] === $folder['parentId'] &&
            $f['userId'] === $userId) {
            $duplicate = true;
            break;
        }
    }

    if ($duplicate) {
        errorResponse('A folder with this name already exists in this location', 409, ERROR_VALIDATION);
    }

    // Update folder
    $folder['name'] = $name;
    $folder['updatedAt'] = gmdate('c');

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    successResponse(['folder' => $folder], 'Folder updated');
}

/**
 * Handle delete folder request
 */
function handleDeleteFolder($db) {
    $userId = getUserId($db);
    $body = getJsonBody();

    $id = $body['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        errorResponse('Folder ID is required', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Find folder
    $folderIndex = null;
    $folder = null;
    foreach ($kbData['folders'] as $index => $f) {
        if ($f['id'] === $id && $f['userId'] === $userId) {
            $folderIndex = $index;
            $folder = $f;
            break;
        }
    }

    if ($folder === null) {
        errorResponse('Folder not found', 404, ERROR_NOT_FOUND);
    }

    // Check if folder has files
    $hasFiles = false;
    foreach ($kbData['files'] as $file) {
        if ($file['folderId'] === $id) {
            $hasFiles = true;
            break;
        }
    }

    if ($hasFiles) {
        errorResponse('Cannot delete folder containing files. Please delete or move files first.', 400, ERROR_VALIDATION);
    }

    // Check if folder has subfolders
    $hasSubfolders = false;
    foreach ($kbData['folders'] as $f) {
        if ($f['parentId'] === $id) {
            $hasSubfolders = true;
            break;
        }
    }

    if ($hasSubfolders) {
        errorResponse('Cannot delete folder containing subfolders. Please delete subfolders first.', 400, ERROR_VALIDATION);
    }

    // Delete folder
    array_splice($kbData['folders'], $folderIndex, 1);

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    successResponse(null, 'Folder deleted');
}

// ==================== FILE HANDLERS ====================

/**
 * Handle list files request
 */
function handleListFiles($db) {
    $userId = getUserId($db);
    $folderId = $_GET['folderId'] ?? null;

    if (!$folderId) {
        errorResponse('Folder ID is required', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Verify folder exists and belongs to user
    $folderExists = false;
    foreach ($kbData['folders'] as $folder) {
        if ($folder['id'] === $folderId && $folder['userId'] === $userId) {
            $folderExists = true;
            break;
        }
    }

    if (!$folderExists) {
        errorResponse('Folder not found', 404, ERROR_NOT_FOUND);
    }

    // Get files in folder
    $files = array_filter($kbData['files'], function($file) use ($folderId, $userId) {
        return $file['folderId'] === $folderId && $file['userId'] === $userId;
    });

    // Sort by name
    usort($files, function($a, $b) {
        return strcmp($a['name'], $b['name']);
    });

    // Remove content from response (metadata only)
    foreach ($files as &$file) {
        unset($file['content']);
    }

    successResponse(['files' => array_values($files)], 'Files retrieved');
}

/**
 * Handle upload file request
 */
function handleUploadFile($db) {
    $userId = getUserId($db);

    try {
        $body = getJsonBody();
    } catch (Exception $e) {
        errorResponse('Invalid JSON payload: ' . $e->getMessage(), 400, ERROR_VALIDATION);
        return;
    }

    $folderId = $_POST['folderId'] ?? $body['folderId'] ?? null;
    if (!$folderId) {
        errorResponse('Folder ID is required', 400, ERROR_VALIDATION);
    }

    $content = null;
    $filename = null;
    $fileType = null;
    $size = 0;

    try {
        // Check if it's a multipart upload or a JSON upload
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        $maxSize = 25 * 1024 * 1024; // 25MB
        if ($file['size'] > $maxSize) {
            errorResponse('File size exceeds maximum allowed size of 25MB', 413, ERROR_VALIDATION);
        }

        $filename = basename($file['name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['md', 'xml'])) {
            errorResponse('Only .md and .xml files are allowed', 400, ERROR_VALIDATION);
        }

        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            errorResponse('Failed to read file content', 500, ERROR_SERVER);
        }
        $size = strlen($content);
        $encodedContent = base64_encode($content);
        $fileType = $extension === 'md' ? 'markdown' : 'xml';
    } elseif (isset($body['content']) && isset($body['name'])) {
        // JSON-based upload (MCP)
        $filename = basename($body['name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, ['md', 'xml'])) {
            errorResponse('Only .md and .xml files are allowed', 400, ERROR_VALIDATION);
        }

        $encodedContent = $body['content'];
        $decoded = base64_decode($encodedContent, true);
        if ($decoded === false) {
            errorResponse('Invalid base64 content', 400, ERROR_VALIDATION);
        }
        $size = strlen($decoded);
        $fileType = $extension === 'md' ? 'markdown' : 'xml';
    } else {
        errorResponse('No file uploaded or invalid JSON payload', 400, ERROR_VALIDATION);
    }

    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Verify folder exists and belongs to user
    $folderExists = false;
    foreach ($kbData['folders'] as $folder) {
        if ($folder['id'] === $folderId && $folder['userId'] === $userId) {
            $folderExists = true;
            break;
        }
    }

    if (!$folderExists) {
        errorResponse('Folder not found', 404, ERROR_NOT_FOUND);
    }

    // Handle duplicate filenames
    $originalName = $filename;
    $counter = 1;
    while (fileExistsInFolder($kbData, $filename, $folderId, $userId)) {
        $pathInfo = pathinfo($originalName);
        $filename = $pathInfo['filename'] . '-' . $counter . '.' . $pathInfo['extension'];
        $counter++;
    }

    // Create file record
    $newFile = [
        'id' => generateUuid(),
        'name' => $filename,
        'folderId' => $folderId,
        'type' => $fileType,
        'content' => $encodedContent,
        'size' => $size,
        'userId' => $userId,
        'createdAt' => gmdate('c'),
        'updatedAt' => gmdate('c')
    ];

    $kbData['files'][] = $newFile;

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    // Return file without content
        unset($newFile['content']);

        successResponse(['file' => $newFile], 'File uploaded');
    } catch (Exception $e) {
        errorResponse('Upload failed: ' . $e->getMessage(), 500, ERROR_SERVER);
    }
}

/**
 * Handle get file request
 */
function handleGetFile($db) {
    $userId = getUserId($db);
    $id = $_GET['id'] ?? null;

    if (!$id) {
        errorResponse('File ID is required', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Find file
    $file = null;
    foreach ($kbData['files'] as $f) {
        if ($f['id'] === $id && $f['userId'] === $userId) {
            $file = $f;
            break;
        }
    }

    if ($file === null) {
        errorResponse('File not found', 404, ERROR_NOT_FOUND);
    }

    successResponse(['file' => $file], 'File retrieved');
}

/**
 * Handle update file request
 */
function handleUpdateFile($db) {
    $userId = getUserId($db);
    $body = getJsonBody();

    $id = $body['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        errorResponse('File ID is required', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Find file
    $fileIndex = null;
    $file = null;
    foreach ($kbData['files'] as $index => $f) {
        if ($f['id'] === $id && $f['userId'] === $userId) {
            $fileIndex = $index;
            $file = &$kbData['files'][$index];
            break;
        }
    }

    if ($file === null) {
        errorResponse('File not found', 404, ERROR_NOT_FOUND);
    }

    // Update name if provided
    if (isset($body['name'])) {
        $name = trim($body['name']);
        if (empty($name)) {
            errorResponse('File name is required', 400, ERROR_VALIDATION);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($extension, ['md', 'xml'])) {
            errorResponse('Only .md and .xml files are allowed', 400, ERROR_VALIDATION);
        }

        // Check for duplicate name in same folder
        $duplicate = false;
        foreach ($kbData['files'] as $f) {
            if ($f['id'] !== $id &&
                $f['name'] === $name &&
                $f['folderId'] === $file['folderId'] &&
                $f['userId'] === $userId) {
                $duplicate = true;
                break;
            }
        }

        if ($duplicate) {
            errorResponse('A file with this name already exists in this folder', 409, ERROR_VALIDATION);
        }

        $file['name'] = $name;
    }

    // Update content if provided
    if (isset($body['content'])) {
        $decodedContent = base64_decode($body['content'], true);
        if ($decodedContent === false) {
            errorResponse('Invalid content encoding', 400, ERROR_VALIDATION);
        }
        $file['content'] = $body['content'];
        $file['size'] = strlen($decodedContent);
    }

    $file['updatedAt'] = gmdate('c');

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    successResponse(['file' => $file], 'File updated');
}

/**
 * Handle delete file request
 */
function handleDeleteFile($db) {
    $userId = getUserId($db);
    $body = getJsonBody();

    $id = $body['id'] ?? $_GET['id'] ?? null;
    if (!$id) {
        errorResponse('File ID is required', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Find file
    $fileIndex = null;
    foreach ($kbData['files'] as $index => $f) {
        if ($f['id'] === $id && $f['userId'] === $userId) {
            $fileIndex = $index;
            break;
        }
    }

    if ($fileIndex === null) {
        errorResponse('File not found', 404, ERROR_NOT_FOUND);
    }

    // Delete file
    array_splice($kbData['files'], $fileIndex, 1);

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    successResponse(null, 'File deleted');
}

/**
 * Handle move file request
 */
function handleMoveFile($db) {
    $userId = getUserId($db);
    $body = getJsonBody();

    $id = $body['id'] ?? $_GET['id'] ?? null;
    $targetFolderId = $body['targetFolderId'] ?? null;

    if (!$id) {
        errorResponse('File ID is required', 400, ERROR_VALIDATION);
    }

    if (!$targetFolderId) {
        errorResponse('Target folder ID is required', 400, ERROR_VALIDATION);
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    // Find file
    $fileIndex = null;
    $file = null;
    foreach ($kbData['files'] as $index => $f) {
        if ($f['id'] === $id && $f['userId'] === $userId) {
            $fileIndex = $index;
            $file = &$kbData['files'][$index];
            break;
        }
    }

    if ($file === null) {
        errorResponse('File not found', 404, ERROR_NOT_FOUND);
    }

    // Verify target folder exists and belongs to user
    $targetFolderExists = false;
    foreach ($kbData['folders'] as $folder) {
        if ($folder['id'] === $targetFolderId && $folder['userId'] === $userId) {
            $targetFolderExists = true;
            break;
        }
    }

    if (!$targetFolderExists) {
        errorResponse('Target folder not found', 404, ERROR_NOT_FOUND);
    }

    // Check for duplicate name in target folder
    $duplicate = false;
    foreach ($kbData['files'] as $f) {
        if ($f['id'] !== $id &&
            $f['name'] === $file['name'] &&
            $f['folderId'] === $targetFolderId &&
            $f['userId'] === $userId) {
            $duplicate = true;
            break;
        }
    }

    if ($duplicate) {
        errorResponse('A file with this name already exists in the target folder', 409, ERROR_DUPLICATE);
    }

    // Move file
    $file['folderId'] = $targetFolderId;
    $file['updatedAt'] = gmdate('c');

    // Save knowledge base data
    if (!saveKnowledgeBase($db, $kbData)) {
        errorResponse('Failed to save knowledge base data', 500, ERROR_SERVER);
    }

    successResponse(['file' => $file], 'File moved');
}

// ==================== SEARCH HANDLER ====================

/**
 * Handle search request
 */
function handleSearch($db) {
    $userId = getUserId($db);
    $query = trim($_GET['q'] ?? '');

    if (empty($query)) {
        successResponse(['results' => []], 'Search results');
    }

    // Load knowledge base data
    $kbData = loadKnowledgeBase($db);

    $results = [];
    $queryLower = strtolower($query);

    // Search folders
    foreach ($kbData['folders'] as $folder) {
        if ($folder['userId'] !== $userId) {
            continue;
        }

        if (strpos(strtolower($folder['name']), $queryLower) !== false) {
            // Build path
            $path = buildFolderPath($kbData, $folder);
            $results[] = [
                'type' => 'folder',
                'id' => $folder['id'],
                'name' => $folder['name'],
                'path' => $path
            ];
        }
    }

    // Search files
    foreach ($kbData['files'] as $file) {
        if ($file['userId'] !== $userId) {
            continue;
        }

        if (strpos(strtolower($file['name']), $queryLower) !== false) {
            // Build path
            $path = buildFolderPath($kbData, $file, $kbData['folders']);
            $results[] = [
                'type' => 'file',
                'id' => $file['id'],
                'name' => $file['name'],
                'path' => $path
            ];
        }
    }

    // Sort by relevance (exact match first)
    usort($results, function($a, $b) use ($queryLower) {
        $aExact = strtolower($a['name']) === $queryLower;
        $bExact = strtolower($b['name']) === $queryLower;

        if ($aExact && !$bExact) return -1;
        if (!$aExact && $bExact) return 1;
        return strcmp($a['name'], $b['name']);
    });

    successResponse(['results' => $results], 'Search results');
}

// ==================== HELPER FUNCTIONS ====================

/**
 * Load knowledge base data from encrypted storage
 */
function loadKnowledgeBase($db) {
    $data = $db->load('knowledge-base', true);

    // Handle empty or missing data
    if (empty($data) || !is_array($data)) {
        return [
            'folders' => [],
            'files' => []
        ];
    }

    // Ensure folders and files arrays exist
    if (!isset($data['folders']) || !is_array($data['folders'])) {
        $data['folders'] = [];
    }
    if (!isset($data['files']) || !is_array($data['files'])) {
        $data['files'] = [];
    }

    return $data;
}

/**
 * Save knowledge base data to encrypted storage
 */
function saveKnowledgeBase($db, $data) {
    return $db->save('knowledge-base', $data);
}

/**
 * Generate UUID v4
 */
function generateUuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Check if file exists in folder
 */
function fileExistsInFolder($kbData, $filename, $folderId, $userId) {
    foreach ($kbData['files'] as $file) {
        if ($file['name'] === $filename &&
            $file['folderId'] === $folderId &&
            $file['userId'] === $userId) {
            return true;
        }
    }
    return false;
}

/**
 * Get first user ID for MCP requests
 * MCP bypasses normal authentication but we need a user context for data access
 */
function getFirstUserId($db) {
    try {
        $users = $db->load('users', true);
        if (!empty($users) && is_array($users)) {
            return $users[0]['id'];
        }
    } catch (Exception $e) {
        // If users collection doesn't exist or is empty, return null
    }
    return null;
}

/**
 * Get user ID handling both normal and MCP authentication
 */
function getUserId($db) {
    if (Auth::isMcp()) {
        return getFirstUserId($db);
    }
    return Auth::userId();
}

/**
 * Build folder path string
 */
function buildFolderPath($kbData, $item, $folders = null) {
    if ($folders === null) {
        $folders = $kbData['folders'];
    }

    $pathParts = [];
    $currentItem = $item;

    // If item is a file, get its folder
    if (isset($item['folderId'])) {
        $folderId = $item['folderId'];
        foreach ($folders as $folder) {
            if ($folder['id'] === $folderId) {
                $currentItem = $folder;
                break;
            }
        }
    }

    // Build path by walking up parent chain
    while ($currentItem !== null) {
        array_unshift($pathParts, $currentItem['name']);

        if (!isset($currentItem['parentId']) || $currentItem['parentId'] === null) {
            break;
        }

        $foundParent = false;
        foreach ($folders as $folder) {
            if ($folder['id'] === $currentItem['parentId']) {
                $currentItem = $folder;
                $foundParent = true;
                break;
            }
        }

        if (!$foundParent) {
            break;
        }
    }

    return implode(' > ', $pathParts);
}

