<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/BaseAPI.php';

ini_set('display_errors', 0);
error_reporting(0);

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$method = requestMethod();

if ($method !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$body = getJsonBody();
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (!Auth::validateCsrf($token)) {
    errorResponse('Invalid CSRF token', 403);
}

$db = new Database(getMasterPassword(), Auth::userId());

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'link':
        $taskId = $body['taskId'] ?? '';
        $inventoryIds = $body['inventoryIds'] ?? [];

        if (empty($taskId)) {
            errorResponse('Task ID is required', 400, ERROR_VALIDATION);
        }

        if (empty($inventoryIds) || !is_array($inventoryIds)) {
            errorResponse('Inventory IDs array is required', 400, ERROR_VALIDATION);
        }

        try {
            $projects = $db->load('projects', true);
            $taskFound = false;

            foreach ($projects as $pKey => $project) {
                foreach ($project['tasks'] ?? [] as $tKey => $task) {
                    if ($task['id'] === $taskId) {
                        $projects[$pKey]['tasks'][$tKey]['linkedInventoryIds'] = $inventoryIds;
                        $projects[$pKey]['tasks'][$tKey]['updatedAt'] = date('c');
                        $taskFound = true;
                        break 2;
                    }
                }
            }

            if (!$taskFound) {
                errorResponse('Task not found', 404, ERROR_NOT_FOUND);
            }

            if (!$db->save('projects', $projects)) {
                errorResponse('Failed to update task', 500, ERROR_SERVER);
            }

            $inventory = $db->load('inventory', true);
            foreach ($inventoryIds as $inventoryId) {
                foreach ($inventory as $iKey => $item) {
                    if ($item['id'] === $inventoryId) {
                        $inventory[$iKey]['linkedTaskId'] = $taskId;
                        break;
                    }
                }
            }

            if (!$db->save('inventory', $inventory)) {
                errorResponse('Failed to update inventory items', 500, ERROR_SERVER);
            }

            successResponse([
                'taskId' => $taskId,
                'linkedInventoryIds' => $inventoryIds,
                'linkedCount' => count($inventoryIds)
            ], 'Task linked with inventory items');

        } catch (Exception $e) {
            errorResponse('Failed to link task: ' . $e->getMessage(), 500, ERROR_SERVER);
        }
        break;

    case 'unlink':
        $taskId = $body['taskId'] ?? '';
        $inventoryIds = $body['inventoryIds'] ?? [];

        if (empty($taskId) && empty($inventoryIds)) {
            errorResponse('Task ID or Inventory IDs required', 400, ERROR_VALIDATION);
        }

        try {
            $projects = $db->load('projects', true);

            if (!empty($taskId)) {
                foreach ($projects as $pKey => $project) {
                    foreach ($project['tasks'] ?? [] as $tKey => $task) {
                        if ($task['id'] === $taskId) {
                            if (empty($inventoryIds)) {
                                $projects[$pKey]['tasks'][$tKey]['linkedInventoryIds'] = [];
                            } else {
                                $currentIds = $projects[$pKey]['tasks'][$tKey]['linkedInventoryIds'] ?? [];
                                $projects[$pKey]['tasks'][$tKey]['linkedInventoryIds'] = array_values(array_diff($currentIds, $inventoryIds));
                            }
                            $projects[$pKey]['tasks'][$tKey]['updatedAt'] = date('c');
                            break 2;
                        }
                    }
                }

                if (!$db->save('projects', $projects)) {
                    errorResponse('Failed to update task', 500, ERROR_SERVER);
                }
            }

            $inventory = $db->load('inventory', true);
            $idsToUnlink = !empty($taskId) && empty($inventoryIds) ? null : $inventoryIds;

            foreach ($inventory as $iKey => $item) {
                if ($idsToUnlink === null) {
                    if (($item['linkedTaskId'] ?? '') === $taskId) {
                        $inventory[$iKey]['linkedTaskId'] = null;
                    }
                } else {
                    if (in_array($item['id'], $idsToUnlink)) {
                        $inventory[$iKey]['linkedTaskId'] = null;
                    }
                }
            }

            if (!$db->save('inventory', $inventory)) {
                errorResponse('Failed to update inventory items', 500, ERROR_SERVER);
            }

            successResponse(null, 'Unlinked successfully');

        } catch (Exception $e) {
            errorResponse('Failed to unlink: ' . $e->getMessage(), 500, ERROR_SERVER);
        }
        break;

    case 'create_shopping_task':
        $projectId = $body['projectId'] ?? '';
        $category = $body['category'] ?? 'Groceries';
        $taskTitle = $body['taskTitle'] ?? "Buy {$category}";

        if (empty($projectId)) {
            errorResponse('Project ID is required', 400, ERROR_VALIDATION);
        }

        try {
            $inventory = $db->load('inventory', true);
            $lowStockItems = [];

            foreach ($inventory as $item) {
                $stock = $item['quantity'] ?? 0;
                $reorderPoint = $item['reorderPoint'] ?? 5;
                $itemCategory = $item['category'] ?? '';

                if ($stock <= $reorderPoint && $itemCategory === $category) {
                    $lowStockItems[] = $item['id'];
                }
            }

            if (empty($lowStockItems)) {
                successResponse([
                    'message' => "No {$category} items need restocking",
                    'lowStockItems' => []
                ], 'No items to add');
            }

            $projects = $db->load('projects', true);
            $taskCreated = false;

            foreach ($projects as $pKey => $project) {
                if ($project['id'] === $projectId) {
                    $newTask = [
                        'id' => $db->generateId(),
                        'title' => $taskTitle,
                        'description' => 'Restock low inventory items',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'dueDate' => date('Y-m-d', strtotime('+3 days')),
                        'estimatedMinutes' => 30,
                        'actualMinutes' => 0,
                        'subtasks' => [],
                        'linkedHabitId' => null,
                        'linkedInventoryIds' => $lowStockItems,
                        'createdAt' => date('c'),
                        'updatedAt' => date('c')
                    ];

                    $projects[$pKey]['tasks'][] = $newTask;
                    $taskCreated = true;

                    foreach ($lowStockItems as $inventoryId) {
                        foreach ($inventory as $iKey => $item) {
                            if ($item['id'] === $inventoryId) {
                                $inventory[$iKey]['linkedTaskId'] = $newTask['id'];
                                break;
                            }
                        }
                    }
                    break;
                }
            }

            if (!$taskCreated) {
                errorResponse('Project not found', 404, ERROR_NOT_FOUND);
            }

            if (!$db->save('projects', $projects)) {
                errorResponse('Failed to create task', 500, ERROR_SERVER);
            }

            if (!$db->save('inventory', $inventory)) {
                errorResponse('Failed to link inventory items', 500, ERROR_SERVER);
            }

            successResponse([
                'task' => $newTask,
                'linkedInventoryIds' => $lowStockItems,
                'linkedCount' => count($lowStockItems)
            ], 'Shopping task created');

        } catch (Exception $e) {
            errorResponse('Failed to create shopping task: ' . $e->getMessage(), 500, ERROR_SERVER);
        }
        break;

    default:
        errorResponse('Invalid action', 400, ERROR_VALIDATION);
}

