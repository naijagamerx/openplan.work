<?php
/**
 * Notes API Endpoint
 * CRUD operations for notes with tagging and search support
 */

require_once __DIR__ . '/../config.php';
require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/Auth.php';
require_once INCLUDES_PATH . '/Helpers.php';
require_once INCLUDES_PATH . '/NotesAPI.php';

// Require authentication
if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
    exit;
}

$db = new Database(getMasterPassword(), Auth::userId());
$notesAPI = new NotesAPI($db, 'notes');

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

// MCP bypass for CSRF validation (use consistent Auth::isMcp() method)
$isMCP = Auth::isMcp();

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $note = $notesAPI->find($id);
            if ($note) {
                successResponse($note);
            }
            $notesAPI->notFound('Note');
        }

        // Handle actions that return lists
        if ($action === 'tags') {
            $tags = $notesAPI->getAllTags();
            successResponse($tags);
        }

        if ($action === 'tag_stats') {
            $stats = $notesAPI->getTagStats();
            successResponse($stats);
        }

        if ($action === 'search' && !empty($_GET['query'])) {
            $results = $notesAPI->search($_GET['query']);
            successResponse($results);
        }

        // Get all notes with filters
        $filters = [];
        if (!empty($_GET['tag'])) {
            $filters['tag'] = $_GET['tag'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        if (!empty($_GET['isPinned'])) {
            $filters['isPinned'] = filter_var($_GET['isPinned'], FILTER_VALIDATE_BOOLEAN);
        }
        if (!empty($_GET['isFavorite'])) {
            $filters['isFavorite'] = filter_var($_GET['isFavorite'], FILTER_VALIDATE_BOOLEAN);
        }
        if (!empty($_GET['entityType']) && !empty($_GET['entityId'])) {
            $filters['linkedEntityType'] = $_GET['entityType'];
            $filters['linkedEntityId'] = $_GET['entityId'];
        }

        $notes = $notesAPI->findAll($filters);
        successResponse($notes);
        break;

    case 'POST':
        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation (skip for MCP)
        if (!$isMCP && !Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        // Handle action endpoints
        if ($action === 'pin' && $id) {
            $note = $notesAPI->togglePin($id);
            if ($note) {
                successResponse($note, 'Note pinned/unpinned');
            }
            $notesAPI->notFound('Note');
        }

        if ($action === 'favorite' && $id) {
            $note = $notesAPI->toggleFavorite($id);
            if ($note) {
                successResponse($note, 'Note favorited/unfavorited');
            }
            $notesAPI->notFound('Note');
        }

        if ($action === 'add_tag' && $id && !empty($body['tag'])) {
            $note = $notesAPI->addTag($id, $body['tag']);
            if ($note) {
                successResponse($note, 'Tag added');
            }
            $notesAPI->notFound('Note');
        }

        if ($action === 'remove_tag' && $id && !empty($body['tag'])) {
            $note = $notesAPI->removeTag($id, $body['tag']);
            if ($note) {
                successResponse($note, 'Tag removed');
            }
            $notesAPI->notFound('Note');
        }

        // Delete tag globally from all notes
        if ($action === 'delete_tag_global' && !empty($body['tag'])) {
            $affectedCount = $notesAPI->deleteTagGlobal($body['tag']);
            successResponse(['affected' => $affectedCount], "Tag '{$body['tag']}' removed from {$affectedCount} notes");
        }

        // Get tag statistics
        if ($action === 'tag_stats') {
            $stats = $notesAPI->getTagStats();
            successResponse($stats);
        }

        // Bulk import notes
        if ($action === 'bulk_import' && !empty($body['notes'])) {
            $result = $notesAPI->bulkImport($body['notes']);
            successResponse($result, "Imported {$result['created']} notes");
        }

        // Bulk delete notes
        if ($action === 'bulk_delete' && !empty($body['ids'])) {
            $ids = $body['ids'];
            if (!is_array($ids)) {
                errorResponse('IDs must be an array', 400, ERROR_VALIDATION);
            }
            $result = $notesAPI->bulkDelete($ids);
            if (empty($result['failed'])) {
                successResponse($result, "Deleted {$result['deleted']} notes successfully");
            } else {
                $failedCount = count($result['failed']);
                successResponse($result, "Deleted {$result['deleted']} notes, {$failedCount} failed");
            }
        }

        if ($action === 'convert_to_markdown') {
            $folderId = $body['folderId'] ?? '';
            $filename = $body['filename'] ?? '';
            $content = $body['content'] ?? '';
            $format = $body['format'] ?? 'markdown';

            if (empty($folderId)) {
                errorResponse('Folder ID is required', 400, ERROR_VALIDATION);
            }
            if (empty($filename)) {
                errorResponse('Filename is required', 400, ERROR_VALIDATION);
            }
            // Validate format
            if (!in_array($format, ['markdown', 'xml'])) {
                errorResponse('Format must be markdown or xml', 400, ERROR_VALIDATION);
            }
            // Validate filename extension matches format
            $expectedExtension = $format === 'markdown' ? '.md' : '.xml';
            if (!preg_match('/^[a-zA-Z0-9-]+\.(md|xml)$/', $filename)) {
                errorResponse('Filename must end with .md or .xml and contain only letters, numbers, and hyphens', 400, ERROR_VALIDATION);
            }
            // Check filename extension matches selected format
            $actualExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (($format === 'markdown' && $actualExtension !== 'md') ||
                ($format === 'xml' && $actualExtension !== 'xml')) {
                errorResponse("Filename extension must match format (expected: {$expectedExtension})", 400, ERROR_VALIDATION);
            }

            // Load knowledge base data
            $kbData = $db->load('knowledge-base', true);
            if ($kbData === null) {
                $kbData = ['folders' => [], 'files' => []];
            }

            // Verify folder exists and belongs to user
            $userId = Auth::userId();
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

            // Note: Frontend now handles duplicate checking and auto-renaming
            // But we still validate here as a safety check

            // Content is already formatted by frontend (markdown or XML)
            $fileContent = $content;

            // Encode content to base64
            $encodedContent = base64_encode($fileContent);

            // Determine file type
            $fileType = $format === 'markdown' ? 'markdown' : 'xml';

            // Create file record
            $newFile = [
                'id' => generateUuid(),
                'name' => $filename,
                'folderId' => $folderId,
                'type' => $fileType,
                'content' => $encodedContent,
                'size' => strlen($fileContent),
                'userId' => $userId,
                'createdAt' => gmdate('c'),
                'updatedAt' => gmdate('c')
            ];

            $kbData['files'][] = $newFile;

            // Save knowledge base data
            if (!$db->save('knowledge-base', $kbData)) {
                errorResponse('Failed to save to knowledge base', 500, ERROR_SERVER);
            }

            successResponse(['file' => $newFile], 'Note converted successfully');
        }

        if ($action === 'update' && $id) {
            $note = $notesAPI->update($id, $body);
            if ($note) {
                successResponse($note, 'Note updated');
            }
            $notesAPI->notFound('Note');
        }

        // Create new note
        $validationError = $notesAPI->validateRequired($body, ['title']);
        if ($validationError) {
            $notesAPI->validationError($validationError);
        }

        $note = $notesAPI->create($body);
        if ($note) {
            successResponse($note, 'Note created');
        }

        errorResponse('Failed to create note', 500, ERROR_SERVER);
        break;

    case 'PUT':
    case 'PATCH':
        if (!$id) {
            errorResponse('Note ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $csrfToken = $body['csrf_token'] ?? '';

        // CSRF validation (skip for MCP)
        if (!$isMCP && !Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        $note = $notesAPI->update($id, $body);
        if ($note) {
            successResponse($note, 'Note updated');
        }

        $notesAPI->notFound('Note');
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Note ID required', 400, ERROR_VALIDATION);
        }

        // Check for CSRF token in query string or headers for DELETE
        $csrfToken = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        // CSRF validation (skip for MCP)
        if (!$isMCP && !Auth::validateCsrf($csrfToken)) {
            errorResponse('Invalid CSRF token', 403);
        }

        if ($notesAPI->delete($id)) {
            successResponse(null, 'Note deleted');
        }

        $notesAPI->notFound('Note');
        break;
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

