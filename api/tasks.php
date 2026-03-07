<?php
/**
 * Tasks API Endpoint
 */

require_once __DIR__ . '/../config.php';

// Check authentication
if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// Validate CSRF for non-GET requests
if (requestMethod() !== 'GET') {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword());
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$projectId = $_GET['project_id'] ?? null;

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            // Get single task
            $projects = $db->load('projects');
            foreach ($projects as $project) {
                foreach ($project['tasks'] ?? [] as $task) {
                    if ($task['id'] === $id) {
                        successResponse($task);
                    }
                }
            }
            errorResponse('Task not found', 404);
        } else {
            // List tasks
            $projects = $db->load('projects');
            $allTasks = [];
            
            foreach ($projects as $project) {
                if ($projectId && $project['id'] !== $projectId) continue;
                
                foreach ($project['tasks'] ?? [] as $task) {
                    $task['projectName'] = $project['name'];
                    $task['projectId'] = $project['id'];
                    $allTasks[] = $task;
                }
            }
            
            // Apply filters
            $status = $_GET['status'] ?? null;
            $priority = $_GET['priority'] ?? null;
            
            if ($status) {
                $allTasks = array_filter($allTasks, fn($t) => ($t['status'] ?? '') === $status);
            }
            if ($priority) {
                $allTasks = array_filter($allTasks, fn($t) => ($t['priority'] ?? '') === $priority);
            }
            
            // Sort by due date
            usort($allTasks, function($a, $b) {
                $dateA = $a['dueDate'] ?? '9999-12-31';
                $dateB = $b['dueDate'] ?? '9999-12-31';
                return strcmp($dateA, $dateB);
            });
            
            successResponse(array_values($allTasks));
        }
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'add';
        
        if ($action === 'update' && $id) {
            $projects = $db->load('projects');
            $taskFound = false;
            foreach ($projects as $pKey => $project) {
                foreach ($project['tasks'] ?? [] as $tKey => $task) {
                    if ($task['id'] === $id) {
                        $taskFound = true;
                        $allowedFields = ['title', 'description', 'status', 'priority', 'dueDate', 'estimatedMinutes', 'actualMinutes', 'subtasks', 'linkedHabitId'];
                        foreach ($allowedFields as $field) {
                            if (isset($body[$field])) {
                                $projects[$pKey]['tasks'][$tKey][$field] = $body[$field];
                            }
                        }
                        $projects[$pKey]['tasks'][$tKey]['updatedAt'] = date('c');
                        if (($body['status'] ?? '') === 'done' && empty($task['completedAt'])) {
                            $projects[$pKey]['tasks'][$tKey]['completedAt'] = date('c');
                        }
                        $db->save('projects', $projects);
                        successResponse($projects[$pKey]['tasks'][$tKey], 'Task updated');
                        break 2;
                    }
                }
            }
            if (!$taskFound) errorResponse('Task not found', 404);
        } else {
            // New task
            if (empty($body['title']) || empty($body['projectId'])) {
                errorResponse('Title and project ID are required');
            }

            $projects = $db->load('projects');
            $found = false;
            foreach ($projects as $key => $project) {
                if ($project['id'] === $body['projectId']) {
                    $found = true;
                    $newTask = [
                        'id' => $db->generateId(),
                        'title' => $body['title'],
                        'description' => $body['description'] ?? '',
                        'status' => $body['status'] ?? 'todo',
                        'priority' => $body['priority'] ?? 'medium',
                        'dueDate' => $body['dueDate'] ?? null,
                        'estimatedMinutes' => (int)($body['estimatedMinutes'] ?? 0),
                        'actualMinutes' => 0,
                        'linkedHabitId' => $body['linkedHabitId'] ?? null,
                        'subtasks' => [],
                        'timeEntries' => [],
                        'createdAt' => date('c'),
                        'updatedAt' => date('c')
                    ];
                    $projects[$key]['tasks'][] = $newTask;
                    $db->save('projects', $projects);
                    successResponse($newTask, 'Task created');
                    break;
                }
            }
            if (!$found) errorResponse('Project not found', 404);
        }
        break;
        
    case 'PUT':
        if (!$id) {
            errorResponse('Task ID required');
        }
        
        $body = getJsonBody();
        $projects = $db->load('projects');
        $taskFound = false;
        
        foreach ($projects as $pKey => $project) {
            foreach ($project['tasks'] ?? [] as $tKey => $task) {
                if ($task['id'] === $id) {
                    $taskFound = true;

                    // Update allowed fields
                    $allowedFields = ['title', 'description', 'status', 'priority', 'dueDate', 'estimatedMinutes', 'actualMinutes', 'subtasks', 'linkedHabitId'];
                    foreach ($allowedFields as $field) {
                        if (isset($body[$field])) {
                            $projects[$pKey]['tasks'][$tKey][$field] = $body[$field];
                        }
                    }
                    $projects[$pKey]['tasks'][$tKey]['updatedAt'] = date('c');

                    // Handle completion
                    if (($body['status'] ?? '') === 'done' && empty($task['completedAt'])) {
                        $projects[$pKey]['tasks'][$tKey]['completedAt'] = date('c');
                    }

                    $db->save('projects', $projects);
                    successResponse($projects[$pKey]['tasks'][$tKey], 'Task updated');
                }
            }
        }
        
        if (!$taskFound) {
            errorResponse('Task not found', 404);
        }
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Task ID required');
        }
        
        $projects = $db->load('projects');
        $taskFound = false;
        
        foreach ($projects as $pKey => $project) {
            foreach ($project['tasks'] ?? [] as $tKey => $task) {
                if ($task['id'] === $id) {
                    $taskFound = true;
                    array_splice($projects[$pKey]['tasks'], $tKey, 1);
                    $db->save('projects', $projects);
                    successResponse(null, 'Task deleted');
                }
            }
        }
        
        if (!$taskFound) {
            errorResponse('Task not found', 404);
        }
        break;
        
    default:
        errorResponse('Method not allowed', 405);
}
