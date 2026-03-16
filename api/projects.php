<?php
/**
 * Projects API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ProjectsAPI.php';

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
    $db->load('projects', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

$projectsAPI = new ProjectsAPI($db, 'projects');

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $project = $projectsAPI->find($id);
            if ($project) {
                // Add counts for single project too
                $tasks = $project['tasks'] ?? [];
                $project['taskCount'] = count($tasks);
                $project['completedCount'] = count(array_filter($tasks, fn($t) => isTaskDone($t['status'] ?? '')));
                successResponse($project);
            }
            $projectsAPI->notFound('Project');
        }
        successResponse($projectsAPI->findAll());
        break;

    case 'POST':
        $body = getJsonBody();

        if ($action === 'update' && $id) {
            $project = $projectsAPI->update($id, $body);
            if ($project) {
                successResponse($project, 'Project updated');
            }
            $projectsAPI->notFound('Project');
        }

        // Create new project
        $validationError = $projectsAPI->validateRequired($body, ['name']);
        if ($validationError) {
            $projectsAPI->validationError($validationError);
        }

        $project = $projectsAPI->create($body);
        if ($project) {
            successResponse($project, 'Project created');
        }

        errorResponse('Failed to create project', 500, ERROR_SERVER);
        break;

    case 'PUT':
        if (!$id) {
            errorResponse('Project ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $project = $projectsAPI->update($id, $body);

        if ($project) {
            successResponse($project, 'Project updated');
        }

        $projectsAPI->notFound('Project');
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Project ID required', 400, ERROR_VALIDATION);
        }
        
        if ($projectsAPI->delete($id)) {
            successResponse(null, 'Project deleted');
        }
        
        $projectsAPI->notFound('Project');
        break;
}

