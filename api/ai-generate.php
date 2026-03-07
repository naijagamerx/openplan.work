<?php
/**
 * Unified AI Generation Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$db = new Database(getMasterPassword());
$ai = new AIHelper($db);
$action = $_GET['action'] ?? '';

if (requestMethod() !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$body = getJsonBody();
$provider = $body['provider'] ?? 'groq';
$model = $body['model'] ?? '';

// Fallback to default model if none provided
if (empty($model)) {
    $models = $db->load('models');
    if (!empty($models[$provider])) {
        foreach ($models[$provider] as $m) {
            if ($m['isDefault']) {
                $model = $m['modelId'];
                break;
            }
        }
    }
}

try {
    switch ($action) {
        case 'project':
            $idea = $body['idea'] ?? '';
            if (empty($idea)) errorResponse('Idea is required');
            $result = $ai->generateProject($idea, $provider, $model);
            successResponse($result);
            break;

        case 'tasks':
            $projectData = $body['project'] ?? [];
            if (empty($projectData)) errorResponse('Project data required');
            $result = $ai->generateTasks($projectData, $provider, $model);
            successResponse($result);
            break;

        case 'subtasks':
            $title = $body['title'] ?? '';
            $description = $body['description'] ?? '';
            if (empty($title)) errorResponse('Title is required');
            $result = $ai->generateSubtasks($title, $description, $provider, $model);
            successResponse($result);
            break;

        case 'invoice_items':
        case 'invoice-items':
            $projectId = $body['projectId'] ?? $_GET['projectId'] ?? '';
            $projectName = $body['projectName'] ?? '';
            $tasks = $body['tasks'] ?? [];

            if ($projectId && empty($projectName)) {
                $projects = $db->load('projects');
                foreach ($projects as $p) {
                    if ($p['id'] === $projectId) {
                        $projectName = $p['name'];
                        break;
                    }
                }
                $allTasks = $db->load('tasks');
                $tasks = array_filter($allTasks, fn($t) => ($t['projectId'] ?? '') === $projectId && ($t['status'] === 'done' || $t['status'] === 'completed'));
            }

            if (empty($projectName)) errorResponse('Project name or ID required');
            $result = $ai->generateInvoiceItems($projectName, array_values($tasks), $provider, $model);
            
            // Normalize response for JS: ensure it has an 'items' key
            if (!isset($result['items']) && is_array($result)) {
                // If AI returned a direct array of items, wrap it
                if (isset($result[0]['description'])) {
                    $result = ['items' => $result];
                }
            }
            
            // Map suggested_rate_usd to unitPrice if needed
            if (isset($result['items'])) {
                foreach ($result['items'] as &$item) {
                    if (isset($item['suggested_rate_usd']) && !isset($item['unitPrice'])) {
                        $item['unitPrice'] = $item['suggested_rate_usd'];
                    }
                }
            }

            successResponse($result);
            break;

        case 'brief':
            $name = $body['name'] ?? '';
            $company = $body['company'] ?? '';
            if (empty($name)) errorResponse('Name is required');
            $result = $ai->generateBrief($name, $company, $provider, $model);
            successResponse($result);
            break;
            
        default:
            errorResponse('Invalid action');
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
