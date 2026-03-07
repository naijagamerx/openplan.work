<?php
/**
 * AI API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

if (requestMethod() !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$body = getJsonBody();
$token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!Auth::validateCsrf($token)) {
    errorResponse('Invalid CSRF token', 403);
}

$action = $_GET['action'] ?? $body['action'] ?? null;
$provider = $body['provider'] ?? 'groq';
$model = $body['model'] ?? null;

// Get API keys from config
$db = new Database(getMasterPassword());
$config = $db->load('config');

$groqKey = $config['groqApiKey'] ?? '';
$openrouterKey = $config['openrouterApiKey'] ?? '';

// Check API key
if ($provider === 'groq' && empty($groqKey)) {
    errorResponse('Groq API key not configured. Please add it in Settings.');
}
if ($provider === 'openrouter' && empty($openrouterKey)) {
    errorResponse('OpenRouter API key not configured. Please add it in Settings.');
}

// Initialize API client
$api = $provider === 'groq' 
    ? new GroqAPI($groqKey)
    : new OpenRouterAPI($openrouterKey);

switch ($action) {
    case 'generate_tasks':
        $description = $body['description'] ?? '';
        if (empty($description)) {
            errorResponse('Project description is required');
        }
        
        $result = $api->generateTasks($description);
        
        if (isset($result['error'])) {
            errorResponse($result['error']);
        }
        
        successResponse($result['data'] ?? $result);
        break;
        
    case 'generate_prd':
        $idea = $body['idea'] ?? '';
        if (empty($idea)) {
            errorResponse('Project idea is required');
        }
        
        $result = $api->generatePRD($idea);
        
        if (isset($result['error'])) {
            errorResponse($result['error']);
        }
        
        successResponse(['prd' => $result['prd'] ?? '']);
        break;
        
    case 'chat':
        $messages = $body['messages'] ?? [];
        if (empty($messages)) {
            errorResponse('Messages are required');
        }
        
        $result = $api->chatCompletion($messages, $model);
        
        if (isset($result['error'])) {
            errorResponse($result['error']);
        }
        
        $content = $result['choices'][0]['message']['content'] ?? '';
        successResponse(['response' => $content]);
        break;
        
    case 'models':
        $models = $provider === 'groq'
            ? GroqAPI::getModels()
            : OpenRouterAPI::getModels();
        successResponse($models);
        break;

    case 'suggest_habits':
        $goals = $body['goals'] ?? '';
        if (empty($goals)) {
            errorResponse('Goals are required for habit suggestions');
        }

        $aiHelper = new AIHelper($db);
        $result = $aiHelper->suggestHabits($goals, $provider, $model);

        if (isset($result['error'])) {
            errorResponse($result['error']);
        }

        successResponse($result);
        break;

    default:
        errorResponse('Invalid action');
}
