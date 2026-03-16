<?php
/**
 * Tasks API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/TasksAPI.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// Validate CSRF for non-GET requests (bypass for MCP)
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
    $db->load('projects', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$projectId = $_GET['projectId'] ?? $_GET['project_id'] ?? null;

$tasksAPI = new TasksAPI($db, 'projects');

switch (requestMethod()) {
    case 'GET':
        if ($action === 'templates') {
            successResponse($tasksAPI->getTemplates());
        } elseif ($id) {
            $task = $tasksAPI->find($id);
            if ($task) {
                successResponse($task);
            }
            $tasksAPI->notFound('Task');
        } else {
            $filters = [
                'projectId' => $projectId,
                'status' => $_GET['status'] ?? null,
                'priority' => $_GET['priority'] ?? null
            ];
            successResponse($tasksAPI->findAll($filters));
        }
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'add';

        if ($action === 'template') {
            $template = $tasksAPI->saveTemplate($body);
            if ($template) {
                successResponse($template, 'Template saved');
            }
            errorResponse('Template name is required', 400, ERROR_VALIDATION);
        } elseif ($action === 'create_from_template') {
            $templateId = $body['templateId'] ?? '';
            if ($templateId === '') {
                errorResponse('Template ID is required', 400, ERROR_VALIDATION);
            }
            $task = $tasksAPI->createFromTemplate($templateId, $body);
            if ($task) {
                successResponse($task, 'Task created from template');
            }
            errorResponse('Template not found or task creation failed', 404, ERROR_NOT_FOUND);
        } elseif ($action === 'bulk') {
            $taskIds = array_values(array_filter($body['taskIds'] ?? [], fn($taskId) => is_string($taskId) && $taskId !== ''));
            if (empty($taskIds)) {
                errorResponse('No tasks selected', 400, ERROR_VALIDATION);
            }

            $operation = $body['operation'] ?? '';
            if ($operation === 'delete') {
                $deletedCount = $tasksAPI->bulkDelete($taskIds);
                successResponse(['count' => $deletedCount], "Deleted {$deletedCount} tasks");
            }

            if ($operation === 'status') {
                $status = $body['status'] ?? '';
                if ($status === '') {
                    errorResponse('Status is required', 400, ERROR_VALIDATION);
                }
                $updatedCount = $tasksAPI->bulkUpdateStatus($taskIds, $status);
                successResponse(['count' => $updatedCount], "Updated {$updatedCount} tasks");
            }

            errorResponse('Invalid bulk operation', 400, ERROR_VALIDATION);
        } elseif ($action === 'subtask' && $id && $projectId) {
            // Check if we're toggling an existing subtask or adding a new one
            if (!empty($body['subtaskId'])) {
                // Toggle existing subtask
                $task = $tasksAPI->addSubtask($id, $body);
                if ($task) {
                    successResponse($task, 'Subtask updated');
                }
                $tasksAPI->notFound('Task');
            } else {
                // Add new subtask
                $validationError = $tasksAPI->validateRequired($body, ['title']);
                if ($validationError) {
                    $tasksAPI->validationError($validationError);
                }

                $task = $tasksAPI->addSubtask($id, $body);
                if ($task) {
                    successResponse($task, 'Subtask added');
                }
                $tasksAPI->notFound('Task');
            }
        } elseif ($action === 'update' && $id) {
            $task = $tasksAPI->update($id, $body);
            if ($task) {
                successResponse($task, 'Task updated');
            }
            $tasksAPI->notFound('Task');
        } else {
            // Create new task
            $validationError = $tasksAPI->validateRequired($body, ['title']);
            if ($validationError) {
                $tasksAPI->validationError($validationError);
            }

            $task = $tasksAPI->create($body);
            if ($task) {
                successResponse($task, 'Task added');
            }

            errorResponse('Failed to create task (Project not found?)', 400, ERROR_VALIDATION);
        }
        break;
        
    case 'PUT':
        if (!$id) {
            errorResponse('Task ID required', 400, ERROR_VALIDATION);
        }
        
        $body = getJsonBody();
        $task = $tasksAPI->update($id, $body);
        
        if ($task) {
            successResponse($task, 'Task updated');
        }
        
        $tasksAPI->notFound('Task');
        break;
        
    case 'DELETE':
        if ($action === 'template') {
            $templateId = $_GET['templateId'] ?? '';
            if ($templateId === '') {
                errorResponse('Template ID required', 400, ERROR_VALIDATION);
            }
            if ($tasksAPI->deleteTemplate($templateId)) {
                successResponse(null, 'Template deleted');
            }
            errorResponse('Template not found', 404, ERROR_NOT_FOUND);
        }

        if (!$id) {
            errorResponse('Task ID required', 400, ERROR_VALIDATION);
        }
        
        if ($tasksAPI->delete($id)) {
            successResponse(null, 'Task deleted');
        }
        
        $tasksAPI->notFound('Task');
        break;
}

