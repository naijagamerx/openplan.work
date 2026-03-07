<?php
/**
 * AI Models API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

$db = new Database(getMasterPassword());
$action = $_GET['action'] ?? 'list';

// Helper to seed default models if collection is empty
function seedModels(Database $db) {
    $defaults = [
        'groq' => [
            [
                'id' => $db->generateId(),
                'modelId' => 'llama-3.3-70b-versatile',
                'displayName' => 'Llama 3.3 70B',
                'description' => 'Fast, versatile model for general tasks',
                'enabled' => true,
                'isDefault' => true,
                'createdAt' => date('c')
            ],
            [
                'id' => $db->generateId(),
                'modelId' => 'llama-3.1-8b-instant',
                'displayName' => 'Llama 3.1 8B',
                'description' => 'Lightweight and extremely fast',
                'enabled' => true,
                'isDefault' => false,
                'createdAt' => date('c')
            ]
        ],
        'openrouter' => [
            [
                'id' => $db->generateId(),
                'modelId' => 'anthropic/claude-3.5-sonnet',
                'displayName' => 'Claude 3.5 Sonnet',
                'description' => 'Advanced reasoning and coding',
                'enabled' => true,
                'isDefault' => true,
                'createdAt' => date('c')
            ],
            [
                'id' => $db->generateId(),
                'modelId' => 'google/gemini-pro-1.5',
                'displayName' => 'Gemini Pro 1.5',
                'description' => 'Google\'s flagship large context model',
                'enabled' => true,
                'isDefault' => false,
                'createdAt' => date('c')
            ]
        ]
    ];
    $db->save('models', $defaults);
    return $defaults;
}

switch ($action) {
    case 'list':
        $models = $db->load('models');
        if (empty($models)) {
            $models = seedModels($db);
        }
        successResponse($models);
        break;

    case 'add':
        if (requestMethod() !== 'POST') errorResponse('Method not allowed', 405);
        $body = getJsonBody();
        $provider = $body['provider'] ?? '';
        if (!in_array($provider, ['groq', 'openrouter'])) errorResponse('Invalid provider');
        
        $models = $db->load('models');
        if (empty($models)) $models = seedModels($db);
        
        $newModel = [
            'id' => $db->generateId(),
            'modelId' => $body['modelId'] ?? '',
            'displayName' => $body['displayName'] ?? '',
            'description' => $body['description'] ?? '',
            'enabled' => (bool)($body['enabled'] ?? true),
            'isDefault' => false,
            'createdAt' => date('c')
        ];
        
        $models[$provider][] = $newModel;
        $db->save('models', $models);
        successResponse($newModel, 'Model added');
        break;

    case 'update':
        if (requestMethod() !== 'POST') errorResponse('Method not allowed', 405);
        $id = $_GET['id'] ?? '';
        $body = getJsonBody();
        $models = $db->load('models');
        $found = false;
        
        foreach (['groq', 'openrouter'] as $provider) {
            foreach ($models[$provider] as &$model) {
                if ($model['id'] === $id) {
                    $model['modelId'] = $body['modelId'] ?? $model['modelId'];
                    $model['displayName'] = $body['displayName'] ?? $model['displayName'];
                    $model['description'] = $body['description'] ?? $model['description'];
                    $model['enabled'] = isset($body['enabled']) ? (bool)$body['enabled'] : $model['enabled'];
                    $found = true;
                    break 2;
                }
            }
        }
        
        if ($found) {
            $db->save('models', $models);
            successResponse(null, 'Model updated');
        } else {
            errorResponse('Model not found');
        }
        break;

    case 'set-default':
        if (requestMethod() !== 'POST') errorResponse('Method not allowed', 405);
        $id = $_GET['id'] ?? '';
        $provider = $_GET['provider'] ?? '';
        $models = $db->load('models');
        
        if (!isset($models[$provider])) errorResponse('Invalid provider');
        
        foreach ($models[$provider] as &$model) {
            $model['isDefault'] = ($model['id'] === $id);
        }
        
        $db->save('models', $models);
        successResponse(null, 'Default model set');
        break;

    case 'delete':
        if (requestMethod() !== 'DELETE') errorResponse('Method not allowed', 405);
        $id = $_GET['id'] ?? '';
        $models = $db->load('models');
        $found = false;
        
        foreach (['groq', 'openrouter'] as $provider) {
            foreach ($models[$provider] as $key => $model) {
                if ($model['id'] === $id) {
                    if ($model['isDefault']) errorResponse('Cannot delete default model');
                    array_splice($models[$provider], $key, 1);
                    $found = true;
                    break 2;
                }
            }
        }
        
        if ($found) {
            $db->save('models', $models);
            successResponse(null, 'Model deleted');
        } else {
            errorResponse('Model not found');
        }
        break;

    default:
        errorResponse('Invalid action');
}
