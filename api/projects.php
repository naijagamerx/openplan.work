<?php
/**
 * Projects API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

if (requestMethod() !== 'GET') {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;

switch (requestMethod()) {
    case 'GET':
        $projects = $db->load('projects');
        
        if ($id) {
            foreach ($projects as $project) {
                if ($project['id'] === $id) {
                    successResponse($project);
                }
            }
            errorResponse('Project not found', 404);
        }
        
        // Add task counts
        foreach ($projects as $key => $project) {
            $tasks = $project['tasks'] ?? [];
            $projects[$key]['taskCount'] = count($tasks);
            $projects[$key]['completedCount'] = count(array_filter($tasks, fn($t) => ($t['status'] ?? '') === 'done'));
        }
        
        successResponse($projects);
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'add';
        
        if ($action === 'update' && $id) {
            $projects = $db->load('projects');
            $found = false;
            foreach ($projects as $key => $project) {
                if ($project['id'] === $id) {
                    $allowedFields = ['name', 'description', 'clientId', 'status', 'color'];
                    foreach ($allowedFields as $field) {
                        if (isset($body[$field])) {
                            $projects[$key][$field] = $body[$field];
                        }
                    }
                    $projects[$key]['updatedAt'] = date('c');
                    $db->save('projects', $projects);
                    $found = true;
                    successResponse($projects[$key], 'Project updated');
                    break;
                }
            }
            if (!$found) errorResponse('Project not found', 404);
        } else {
            // New project
            if (empty($body['name'])) {
                errorResponse('Project name is required');
            }
            
            $projects = $db->load('projects');
            $newProject = [
                'id' => $db->generateId(),
                'name' => $body['name'],
                'description' => $body['description'] ?? '',
                'clientId' => $body['clientId'] ?? null,
                'status' => $body['status'] ?? 'planning',
                'color' => $body['color'] ?? '#000000',
                'tasks' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            
            $projects[] = $newProject;
            $db->save('projects', $projects);
            successResponse($newProject, 'Project created');
        }
        break;
        
    case 'PUT':
        if (!$id) {
            errorResponse('Project ID required');
        }
        
        $body = getJsonBody();
        $projects = $db->load('projects');
        
        foreach ($projects as $key => $project) {
            if ($project['id'] === $id) {
                $allowedFields = ['name', 'description', 'clientId', 'status', 'color'];
                foreach ($allowedFields as $field) {
                    if (isset($body[$field])) {
                        $projects[$key][$field] = $body[$field];
                    }
                }
                $projects[$key]['updatedAt'] = date('c');
                
                $db->save('projects', $projects);
                successResponse($projects[$key], 'Project updated');
            }
        }
        
        errorResponse('Project not found', 404);
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Project ID required');
        }
        
        $projects = $db->load('projects');
        $filtered = array_filter($projects, fn($p) => $p['id'] !== $id);
        
        if (count($filtered) === count($projects)) {
            errorResponse('Project not found', 404);
        }
        
        $db->save('projects', array_values($filtered));
        successResponse(null, 'Project deleted');
        break;
        
    default:
        errorResponse('Method not allowed', 405);
}
