<?php
/**
 * AI Models API Endpoint
 */

require_once __DIR__ . '/../config.php';

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
    $db->load('models', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? 'list';

function modelLooksLikeApiKey(string $modelId, string $provider): bool {
    $trimmed = trim($modelId);
    if ($trimmed === '') {
        return false;
    }
    if ($provider === 'groq' && str_starts_with($trimmed, 'gsk_')) {
        return true;
    }
    if ($provider === 'openrouter' && str_starts_with($trimmed, 'sk-or-')) {
        return true;
    }
    return false;
}

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
        $models = $db->load('models', true);
        if (empty($models)) {
            $models = seedModels($db);
        }
        successResponse($models);
        break;

    case 'add':
        if (requestMethod() !== 'POST') errorResponse('Method not allowed', 405, ERROR_NOT_IMPLEMENTED);
        $body = getJsonBody();
        $provider = $body['provider'] ?? '';
        if (!in_array($provider, ['groq', 'openrouter'])) errorResponse('Invalid provider', 400, ERROR_VALIDATION);
        $modelId = trim((string)($body['modelId'] ?? ''));
        if ($modelId === '') errorResponse('Model ID is required', 400, ERROR_VALIDATION);
        if (modelLooksLikeApiKey($modelId, $provider)) {
            errorResponse('Model ID looks like an API key. Add API keys in Settings and use a real model ID here.', 400, ERROR_VALIDATION);
        }
        
        $models = $db->load('models', true);
        if (empty($models)) $models = seedModels($db);
        
        $newModel = [
            'id' => $db->generateId(),
            'modelId' => $modelId,
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
        if (requestMethod() !== 'POST') errorResponse('Method not allowed', 405, ERROR_NOT_IMPLEMENTED);
        $id = $_GET['id'] ?? '';
        $body = getJsonBody();
        $models = $db->load('models', true);
        $found = false;
        
        foreach (['groq', 'openrouter'] as $provider) {
            foreach ($models[$provider] as &$model) {
                if ($model['id'] === $id) {
                    $modelId = trim((string)($body['modelId'] ?? $model['modelId']));
                    if ($modelId === '') {
                        errorResponse('Model ID is required', 400, ERROR_VALIDATION);
                    }
                    if (modelLooksLikeApiKey($modelId, $provider)) {
                        errorResponse('Model ID looks like an API key. Add API keys in Settings and use a real model ID here.', 400, ERROR_VALIDATION);
                    }
                    $model['modelId'] = $modelId;
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
            errorResponse('Model not found', 404, ERROR_NOT_FOUND);
        }
        break;

    case 'set-default':
        if (requestMethod() !== 'POST') errorResponse('Method not allowed', 405, ERROR_NOT_IMPLEMENTED);
        $id = $_GET['id'] ?? '';
        $provider = $_GET['provider'] ?? '';
        $models = $db->load('models', true);

        if (!isset($models[$provider])) errorResponse('Invalid provider', 400, ERROR_VALIDATION);
        
        foreach ($models[$provider] as &$model) {
            $model['isDefault'] = ($model['id'] === $id);
        }
        
        $db->save('models', $models);
        successResponse(null, 'Default model set');
        break;

    case 'delete':
        if (requestMethod() !== 'DELETE') errorResponse('Method not allowed', 405, ERROR_NOT_IMPLEMENTED);
        $id = $_GET['id'] ?? '';
        $models = $db->load('models', true);
        $found = false;
        
        foreach (['groq', 'openrouter'] as $provider) {
            foreach ($models[$provider] as $key => $model) {
                if ($model['id'] === $id) {
                    if ($model['isDefault']) errorResponse('Cannot delete default model', 400, ERROR_VALIDATION);
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
            errorResponse('Model not found', 404, ERROR_NOT_FOUND);
        }
        break;

    default:
        errorResponse('Invalid action', 400, ERROR_VALIDATION);
}

