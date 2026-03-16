<?php
/**
 * Todos API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/TodosAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// CSRF validation for non-GET requests (bypass for MCP)
if (requestMethod() !== 'GET' && !Auth::isMcp()) {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());

// Verify database connection works
try {
    // If todos file doesn't exist, this might return empty, which is fine.
    // But if decryption fails due to password, it throws.
    $db->load('todos', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

$todosAPI = new TodosAPI($db, 'todos');

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $todo = $todosAPI->find($id);
            if ($todo) {
                successResponse($todo);
            }
            $todosAPI->notFound('Todo');
        }
        // Sort by createdAt desc by default
        $todos = $todosAPI->findAll();
        usort($todos, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
        successResponse($todos);
        break;

    case 'POST':
        $body = getJsonBody();

        if ($action === 'update' && $id) {
            $todo = $todosAPI->update($id, $body);
            if ($todo) {
                successResponse($todo, 'Todo updated');
            }
            $todosAPI->notFound('Todo');
        }

        // Create new todo
        $validationError = $todosAPI->validateRequired($body, ['title']);
        if ($validationError) {
            $todosAPI->validationError($validationError);
        }

        $todo = $todosAPI->create($body);
        if ($todo) {
            successResponse($todo, 'Todo added');
        }

        errorResponse('Failed to create todo', 500, ERROR_SERVER);
        break;

    case 'PATCH':
    case 'PUT':
        if (!$id) {
            errorResponse('Todo ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $todo = $todosAPI->update($id, $body);

        if ($todo) {
            successResponse($todo, 'Todo updated');
        }

        $todosAPI->notFound('Todo');
        break;

    case 'DELETE':
        if (!$id) {
            errorResponse('Todo ID required', 400, ERROR_VALIDATION);
        }

        if ($todosAPI->delete($id)) {
            successResponse(null, 'Todo deleted');
        }

        $todosAPI->notFound('Todo');
        break;

    default:
        errorResponse('Method not allowed', 405);
}

