<?php
/**
 * AI API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AIHelper.php';
require_once __DIR__ . '/../includes/OllamaAPI.php';
require_once __DIR__ . '/../includes/GeminiAPI.php';
require_once __DIR__ . '/../includes/SubscriptionHelper.php';

// Keep errors out of the JSON response, but keep logging on (config.php sets
// E_ALL + log_errors). error_reporting(0) here silently swallowed errors.
ini_set('display_errors', 0);

/**
 * Charge one request against the platform's shared quota when (and only when)
 * the user is consuming admin-supplied keys. Called immediately after a real
 * AI API call returns successfully — before successResponse() exits.
 */
function aiCountSharedUsage(?array $config, ?string $userId, ?string $provider): void {
    if (empty($config['useAdminSharedAiKeys'])) {
        return;
    }
    if (!$userId || $userId === 'anonymous' || empty($provider)) {
        return;
    }
    if (function_exists('incrementUsage')) {
        incrementUsage($userId, $provider);
    }
}

/**
 * Generate a SKU code from product name
 */
function generateSKU(string $name): string {
    // Get first 3 letters of product name (uppercase)
    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3));
    if (strlen($prefix) < 3) {
        $prefix = 'GEN';
    }
    // Generate random number
    $number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $number;
}

function modelLooksLikeApiKey(?string $model, string $provider): bool {
    if (empty($model)) {
        return false;
    }
    $trimmed = trim($model);
    if ($provider === 'groq' && str_starts_with($trimmed, 'gsk_')) {
        return true;
    }
    if ($provider === 'openrouter' && str_starts_with($trimmed, 'sk-or-')) {
        return true;
    }
    if ($provider === 'gemini' && str_starts_with($trimmed, 'AIza')) {
        return true;
    }
    return false;
}

function loadKnowledgeBaseData(Database $db): array {
    $data = $db->load('knowledge-base', true);
    if (!is_array($data) || empty($data)) {
        return ['folders' => [], 'files' => []];
    }
    if (!isset($data['folders']) || !is_array($data['folders'])) {
        $data['folders'] = [];
    }
    if (!isset($data['files']) || !is_array($data['files'])) {
        $data['files'] = [];
    }
    return $data;
}

function buildKnowledgeBaseContext(Database $db, string $folderId, string $userId, int $maxChars = 12000): array {
    if (empty($folderId) || empty($userId)) {
        return ['content' => '', 'folderName' => '', 'fileCount' => 0, 'truncated' => false];
    }

    $kbData = loadKnowledgeBaseData($db);
    $folder = null;
    foreach ($kbData['folders'] as $f) {
        if (($f['id'] ?? '') === $folderId && ($f['userId'] ?? '') === $userId) {
            $folder = $f;
            break;
        }
    }

    if (!$folder) {
        return ['content' => '', 'folderName' => '', 'fileCount' => 0, 'truncated' => false];
    }

    $files = array_filter($kbData['files'], function($file) use ($folderId, $userId) {
        $isMarkdown = ($file['type'] ?? '') === 'markdown' || str_ends_with(($file['name'] ?? ''), '.md');
        return ($file['folderId'] ?? '') === $folderId && ($file['userId'] ?? '') === $userId && $isMarkdown;
    });

    usort($files, function($a, $b) {
        return strcmp($a['name'] ?? '', $b['name'] ?? '');
    });

    $parts = [];
    $used = 0;
    $truncated = false;

    foreach ($files as $file) {
        $decoded = base64_decode($file['content'] ?? '', true);
        if ($decoded === false) {
            continue;
        }
        $text = trim($decoded);
        if ($text === '') {
            continue;
        }

        $section = "File: " . ($file['name'] ?? 'unknown') . "\n" . $text;
        $remaining = $maxChars - $used;
        if ($remaining <= 0) {
            $truncated = true;
            break;
        }

        if (strlen($section) > $remaining) {
            $section = substr($section, 0, $remaining);
            $truncated = true;
        }

        $parts[] = $section;
        $used += strlen($section);
    }

    if (empty($parts)) {
        return ['content' => '', 'folderName' => $folder['name'] ?? '', 'fileCount' => count($files), 'truncated' => false];
    }

    $content = "Knowledge Base Folder: " . ($folder['name'] ?? 'Unknown') . "\n\n";
    $content .= implode("\n\n---\n\n", $parts);
    if ($truncated) {
        $content .= "\n\n[Context truncated]";
    }

    return [
        'content' => $content,
        'folderName' => $folder['name'] ?? '',
        'fileCount' => count($files),
        'truncated' => $truncated
    ];
}

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

// Unlock session file so concurrent tabs (e.g. opening another page while a
// chat is in flight) are not blocked by PHP's exclusive session file lock.
// $_SESSION values remain readable in memory; we just release the lock.
// Mirrors the pattern in api/ai-agent.php:28.
session_write_close();

$isPostRequest = requestMethod() === 'POST';

// Handle GET request for check_requirements (read-only, no auth needed beyond login)
if (!$isPostRequest) {
    $action = $_GET['action'] ?? null;
    if ($action === 'check_requirements') {
        $extensions = [
            'curl' => [
                'loaded' => extension_loaded('curl'),
                'required' => true,
                'feature' => 'AI API requests (Groq, OpenRouter)',
                'docs' => 'https://www.php.net/manual/en/curl.installation.php'
            ],
            'openssl' => [
                'loaded' => extension_loaded('openssl'),
                'required' => true,
                'feature' => 'Data encryption/decryption',
                'docs' => 'https://www.php.net/manual/en/openssl.installation.php'
            ],
            'mbstring' => [
                'loaded' => extension_loaded('mbstring'),
                'required' => true,
                'feature' => 'Multi-byte string handling',
                'docs' => 'https://www.php.net/manual/en/mbstring.installation.php'
            ],
            'json' => [
                'loaded' => extension_loaded('json'),
                'required' => true,
                'feature' => 'JSON data handling',
                'docs' => 'https://www.php.net/manual/en/json.installation.php'
            ],
            'zip' => [
                'loaded' => extension_loaded('zip'),
                'required' => false,
                'feature' => 'Backup export (ZIP format)',
                'docs' => 'https://www.php.net/manual/en/zip.installation.php'
            ]
        ];

        $allRequiredLoaded = true;
        $missingRequired = [];

        foreach ($extensions as $name => $info) {
            if (!$info['loaded'] && $info['required']) {
                $allRequiredLoaded = false;
                $missingRequired[] = $name;
            }
        }

        successResponse([
            'extensions' => $extensions,
            'all_required_loaded' => $allRequiredLoaded,
            'missing_required' => $missingRequired,
            'php_version' => PHP_VERSION,
            'php_version_ok' => version_compare(PHP_VERSION, '8.0.0', '>=')
        ]);
        exit;
    }
    errorResponse('Method not allowed', 405);
}

// POST requests below
$body = getJsonBody();
$action = $_GET['action'] ?? $body['action'] ?? null;

// Skip CSRF validation for authenticated MCP tools (validated via Auth::isMcp(), which
// requires loopback origin + valid X-MCP-Api-Token or static MCP secret).
// Previous behavior of trusting raw HTTP_X_MCP_TOOL header is unsafe — header alone
// is attacker-controlled and bypasses CSRF entirely.
if (!Auth::isTokenAuth()) {
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}
$provider = $body['provider'] ?? '';
$model = $body['model'] ?? null;

// Resolve 'auto' model to the default or first enabled model for the provider
// We must do this after $db is initialized, see below.

if (modelLooksLikeApiKey($model, $provider)) {
    errorResponse('Model ID looks like an API key. Choose a real model in Model Settings and keep API keys in Settings.', 400, ERROR_VALIDATION);
}

// Get API keys from config
$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    errorResponse('Session expired. Please log in again.', 401, ERROR_UNAUTHORIZED);
}

try {
    $db = new Database($masterPassword, Auth::userId());

    // Verify database connection works by loading config
    $configLoad = $db->safeLoad('config');
    if (!$configLoad['success']) {
        errorResponse('Database decryption failed. Please log in again.', 401, ERROR_UNAUTHORIZED);
    }
} catch (Exception $e) {
    errorResponse('Database connection failed: ' . $e->getMessage(), 401, ERROR_UNAUTHORIZED);
}

// API Rate Limiting
$userId = Auth::userId() ?? 'anonymous';
$rateLimiter = new RateLimiter($db);
$apiLimit = $rateLimiter->checkApiLimit($userId, 'ai');

// Check rate limit
if (!$apiLimit['allowed']) {
    $retryAfter = $apiLimit['reset'] - time();
    header('Retry-After: ' . $retryAfter);
    errorResponse(
        'API rate limit exceeded. Please try again in ' . ceil($retryAfter / 60) . ' minutes.',
        429,
        'RATE_LIMIT_EXCEEDED'
    );
}

// Resolve 'auto' model to the default or first enabled model for the provider
// Models are stored as ['groq' => [...], 'openrouter' => [...], 'ollama' => [...], 'gemini' => [...]]
if ($model === 'auto' && !empty($provider)) {
    $allModels = $db->load('models') ?? [];
    $providerModels = $allModels[$provider] ?? [];
    $resolvedModel = null;

    // First try to find a default model for this provider
    foreach ($providerModels as $m) {
        if ($m['isDefault'] ?? false) {
            $resolvedModel = $m['modelId'];
            break;
        }
    }

    // If no default, find the first enabled model
    if (!$resolvedModel) {
        foreach ($providerModels as $m) {
            if ($m['enabled'] ?? true) {
                $resolvedModel = $m['modelId'];
                break;
            }
        }
    }

    if ($resolvedModel) {
        $model = $resolvedModel;
    } else {
        errorResponse("No {$provider} model configured. Please configure a model in Model Settings.", 400, ERROR_VALIDATION);
    }
}

$config = $db->load('config', true);

$groqKey       = $config['groqApiKey'] ?? '';
$openrouterKey = $config['openrouterApiKey'] ?? '';
$geminiKey     = $config['geminiApiKey'] ?? '';
$ollamaUrl     = $config['ollamaUrl'] ?? 'http://localhost:11434';

// If provider not explicitly specified, use user's configured default
if (empty($provider)) {
    $provider = $config['aiProvider'] ?? 'groq';
    // Auto-resolve model for the config-default provider if not set
    if ($model === null) {
        $allModels = $allModels ?? ($db->load('models') ?? []);
        $providerModels = $allModels[$provider] ?? [];
        $resolvedDefault = null;
        foreach ($providerModels as $m) {
            if ($m['isDefault'] ?? false) { $resolvedDefault = $m['modelId']; break; }
        }
        if (!$resolvedDefault) {
            foreach ($providerModels as $m) {
                if ($m['enabled'] ?? true) { $resolvedDefault = $m['modelId']; break; }
            }
        }
        if ($resolvedDefault) $model = $resolvedDefault;
    }
}

// Plan-tier provider gate + shared-key swap. If the user has opted into platform
// keys AND has an active subscription that unlocks this provider, replace the
// user's (likely empty) key with the admin's shared key for the remainder of
// the request. This is the only place where the raw shared key is read on the
// chat path.
if (!empty($config['useAdminSharedAiKeys'])) {
    require_once INCLUDES_PATH . '/SubscriptionHelper.php';
    $tierSub = getActiveSubscription(Auth::userId() ?? '');
    if (!$tierSub) {
        errorResponse('No active subscription. Buy a plan to use platform AI keys.', 403, 'NO_SUBSCRIPTION');
    }
    if (!subscriptionUnlocksProvider($tierSub, $provider)) {
        errorResponse('Your plan does not include the ' . $provider . ' provider. Upgrade to access it.', 403, 'PROVIDER_NOT_IN_PLAN');
    }

    $sharedKey = getAdminSharedKey($provider);
    if (!empty($sharedKey)) {
        if ($provider === 'groq')       { $groqKey = $sharedKey; }
        if ($provider === 'openrouter') { $openrouterKey = $sharedKey; }
        if ($provider === 'gemini')     { $geminiKey = $sharedKey; }
    }
}

// Check API key / connectivity config
if ($provider === 'groq' && empty($groqKey)) {
    errorResponse('Groq API key not configured. Please add it in Settings.');
}
if ($provider === 'openrouter' && empty($openrouterKey)) {
    errorResponse('OpenRouter API key not configured. Please add it in Settings.');
}
if ($provider === 'gemini' && empty($geminiKey)) {
    errorResponse('Google Gemini API key not configured. Please add it in Settings.');
}
if ($provider === 'ollama') {
    if (!canUseOllama()) {
        errorResponse(
            'Ollama is not available in online mode (shared hosting cannot reach local Ollama). Please use Groq, OpenRouter, or Gemini instead. Configure your preferred provider in Model Settings.',
            503,
            'OLLAMA_UNAVAILABLE_ONLINE'
        );
    }
    // Also check if ollamaUrl is localhost - if so, warn but allow
    if (strpos($ollamaUrl, 'localhost') !== false || strpos($ollamaUrl, '127.0.0.1') !== false) {
        // This is expected for local, no issue
    }
}

// Initialize API client
switch ($provider) {
    case 'groq':
        $api = new GroqAPI($groqKey);
        break;
    case 'openrouter':
        $api = new OpenRouterAPI($openrouterKey);
        break;
    case 'gemini':
        $api = new GeminiAPI($geminiKey);
        break;
    case 'ollama':
        $api = new OllamaAPI($ollamaUrl);
        break;
    default:
        errorResponse("Unknown AI provider: {$provider}", 400, ERROR_VALIDATION);
}

try {
    switch ($action) {
        case 'generate_tasks':
            $description = $body['description'] ?? '';
            if (empty($description)) {
                errorResponse('Project description is required', 400, ERROR_VALIDATION);
            }

            $result = $api->generateTasks($description);
            aiCountSharedUsage($config, $userId, $provider);
            successResponse($result['data'] ?? $result);
            break;

        case 'import_tasks':
            $projectId = $body['projectId'] ?? '';
            $tasks = $body['tasks'] ?? [];

            if (empty($projectId)) {
                errorResponse('Project ID is required', 400, ERROR_VALIDATION);
            }
            if (empty($tasks)) {
                errorResponse('Tasks are required', 400, ERROR_VALIDATION);
            }

            $projects = $db->load('projects', true);
            $projectFound = false;

            foreach ($projects as &$project) {
                if ($project['id'] === $projectId) {
                    $projectFound = true;
                    // Convert AI task format to project task format
                    foreach ($tasks as $task) {
                        $project['tasks'][] = [
                            'id' => $db->generateId(),
                            'title' => $task['title'],
                            'description' => $task['description'] ?? '',
                            'status' => 'backlog',
                            'priority' => $task['priority'] ?? 'medium',
                            'estimatedMinutes' => $task['estimatedMinutes'] ?? 60,
                            'subtasks' => $task['subtasks'] ?? [],
                            'createdAt' => date('c'),
                            'updatedAt' => date('c')
                        ];
                    }
                    $project['updatedAt'] = date('c');
                    break;
                }
            }

            if (!$projectFound) {
                errorResponse('Project not found', 404, ERROR_NOT_FOUND);
            }

            $db->save('projects', $projects);
            successResponse(null, 'Tasks imported successfully');
            break;

        case 'generate_prd':
            $idea = $body['idea'] ?? '';
            if (empty($idea)) {
                errorResponse('Project idea is required', 400, ERROR_VALIDATION);
            }

            $result = $api->generatePRD($idea);
            aiCountSharedUsage($config, $userId, $provider);
            successResponse(['prd' => $result['prd'] ?? '', 'idea' => $idea]);
            break;

        case 'save_prd':
            $projectId = $body['projectId'] ?? '';
            $prd = $body['prd'] ?? '';
            $idea = $body['idea'] ?? '';

            if (empty($projectId)) {
                errorResponse('Project ID is required', 400, ERROR_VALIDATION);
            }
            if (empty($prd)) {
                errorResponse('PRD content is required', 400, ERROR_VALIDATION);
            }

            $projects = $db->load('projects', true);
            $projectFound = false;

            foreach ($projects as &$project) {
                if ($project['id'] === $projectId) {
                    $projectFound = true;
                    $project['prd'] = $prd;
                    $project['prdIdea'] = $idea;
                    $project['updatedAt'] = date('c');
                    break;
                }
            }

            if (!$projectFound) {
                errorResponse('Project not found', 404, ERROR_NOT_FOUND);
            }

            $db->save('projects', $projects);
            successResponse(null, 'PRD saved to project');
            break;

        case 'chat':
            $messages = $body['messages'] ?? [];
            if (empty($messages)) {
                errorResponse('Messages are required', 400, ERROR_VALIDATION);
            }

            // Defense-in-depth: trim chatHistory for chat-only providers (Groq).
            // Some Groq models (gpt-oss-120b) have only 8000 TPM on free tier.
            // Even if the frontend forgot to trim, we cap server-side here.
            $isChatOnlyProvider = (strtolower($provider) === 'groq');
            if ($isChatOnlyProvider) {
                $maxChars = 24000; // ~6000 tokens, well under 8000 TPM
                $totalChars = 0;
                foreach ($messages as $m) { $totalChars += strlen((string)($m['content'] ?? '')); }
                while (count($messages) > 1 && $totalChars > $maxChars) {
                    $removed = array_shift($messages);
                    $totalChars -= strlen((string)($removed['content'] ?? ''));
                }
                // Force-reject KB folder for chat-only providers — they cannot
                // afford the extra context.
                $body['kbFolderId'] = '';
            }

            try {
                $kbFolderId = trim((string)($body['kbFolderId'] ?? ''));
                $currentUserId = Auth::userId() ?? '';
                if (!empty($kbFolderId) && !empty($currentUserId)) {
                    $kbContext = buildKnowledgeBaseContext($db, $kbFolderId, $currentUserId);
                    if (!empty($kbContext['content'])) {
                        $kbSystemPrompt = "You are an AI assistant. Use the following Knowledge Base content as your primary reference. " .
                            "If the answer is not in the Knowledge Base, say so. Cite file names when relevant.\n\n" .
                            $kbContext['content'];
                        array_unshift($messages, ['role' => 'system', 'content' => $kbSystemPrompt]);
                    }
                }

                $result = $api->chatCompletion($messages, $model);
                if (isset($result['error'])) {
                    $errMsg = (string)($result['error']['message'] ?? 'Unknown error');
                    // Groq TPM / "Request too large" → return structured switch hint
                    // so the UI can show the banner instead of a raw 500.
                    if ($isChatOnlyProvider && preg_match('/request too large|tokens per minute|\bTPM\b/i', $errMsg)) {
                        $suggested = [];
                        if (!empty($openrouterKey)) $suggested[] = 'openrouter';
                        if (!empty($geminiKey))     $suggested[] = 'gemini';
                        successResponse([
                            'switchProviderRecommended' => true,
                            'reason' => 'context_overflow',
                            'suggestedProviders' => $suggested,
                            'message' => 'Groq hit its per-minute token limit on this request. Switch to OpenRouter or Gemini above to continue.'
                        ]);
                        break;
                    }
                    errorResponse('AI Error: ' . $errMsg, 500, 'AI_ERROR');
                }
                $content = $result['choices'][0]['message']['content'] ?? '';
                aiCountSharedUsage($config, $userId, $provider);
                successResponse([
                    'response' => $content,
                    'provider' => $provider,
                    'model'    => $model,
                ]);
            } catch (Exception $e) {
                $errMsg = $e->getMessage();
                if ($isChatOnlyProvider && preg_match('/request too large|tokens per minute|\bTPM\b/i', $errMsg)) {
                    $suggested = [];
                    if (!empty($openrouterKey)) $suggested[] = 'openrouter';
                    if (!empty($geminiKey))     $suggested[] = 'gemini';
                    successResponse([
                        'switchProviderRecommended' => true,
                        'reason' => 'context_overflow',
                        'suggestedProviders' => $suggested,
                        'message' => 'Groq hit its per-minute token limit on this request. Switch to OpenRouter or Gemini above to continue.'
                    ]);
                    break;
                }
                errorResponse('AI request failed: ' . $errMsg, 500, 'AI_ERROR');
            }
            break;

        case 'test_connection':
            // Test API connection
            try {
                $testPrompt = "Say 'API working!' if you receive this message.";
                $result = $api->chatCompletion([
                    ['role' => 'user', 'content' => $testPrompt]
                ], $model);
                if (isset($result['error'])) {
                    successResponse([
                        'connected' => false,
                        'error' => $result['error']['message'] ?? 'Unknown error'
                    ]);
                }
                successResponse(['connected' => true, 'response' => $result['choices'][0]['message']['content'] ?? 'OK']);
            } catch (Exception $e) {
                successResponse(['connected' => false, 'error' => $e->getMessage()]);
            }
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
                errorResponse('Goals are required for habit suggestions', 400, ERROR_VALIDATION);
            }

            if (empty($model)) {
                errorResponse('AI model not configured. Please set up a model in Model Settings (/?page=model-settings).', 400, ERROR_VALIDATION);
            }

            $aiHelper = new AIHelper($db);
            $result = $aiHelper->suggestHabits($goals, $provider, $model);
            aiCountSharedUsage($config, $userId, $provider);
            successResponse($result);
            break;

        case 'generate_inventory':
            $description = $body['description'] ?? '';
            $category = $body['category'] ?? 'General';

            if (empty($description)) {
                errorResponse('Description is required for inventory generation', 400, ERROR_VALIDATION);
            }

            // Sanitize user input before injecting into prompt template (prevents prompt injection)
            $safeDescription = AIHelper::sanitizeUserInputForPrompt($description, 2000);
            $safeCategory    = AIHelper::sanitizeUserInputForPrompt($category, 100);

            $categoryNote = '';
            if ($safeCategory === 'Groceries') {
                $categoryNote = ' For groceries, include typical household food items, produce, dairy, meat, pantry staples, beverages, etc.';
            }

            $prompt = <<<PROMPT
You are an inventory management expert. Generate a list of inventory items based on this description: "{$safeDescription}".
Category: {$safeCategory}{$categoryNote}

Return ONLY valid JSON in this exact format:
{
    "items": [
        {
            "name": "Product name",
            "description": "Brief description",
            "category": "{$safeCategory}",
            "sku": "Auto-generate SKU (e.g., CAT-001)",
            "cost": 0.00,
            "price": 0.00,
            "quantity": 0,
            "reorderPoint": 5,
            "supplier": ""
        }
    ]
}
Generate 5-10 relevant items based on description. Use realistic prices and costs.
PROMPT;

            try {
                $result = $api->complete($prompt, $model);

                // Extract JSON from response (regex below was missing closing delimiter '/'.
                // Use a properly delimited greedy match between the first '{' and last '}'.)
                $parsed = null;
                if (preg_match('/\{[\s\S]*\}/', $result, $matches)) {
                    $parsed = json_decode($matches[0], true);
                }

                if (!$parsed || !isset($parsed['items'])) {
                    // Fallback to simple parsing
                    $parsed = ['items' => []];
                }

                aiCountSharedUsage($config, $userId, $provider);
                successResponse(['items' => $parsed['items'] ?? []]);
            } catch (Exception $e) {
                error_log('Failed to generate inventory: ' . $e->getMessage());
                errorResponse('Failed to generate inventory. Please try again.', 500, 'AI_ERROR');
            }
            break;

        case 'create_inventory_items':
            $items = $body['items'] ?? [];

            if (empty($items)) {
                errorResponse('Items are required', 400, ERROR_VALIDATION);
            }

            $inventory = $db->load('inventory', true);
            $created = 0;
            $errors = [];

            foreach ($items as $item) {
                if (empty($item['name'])) {
                    continue;
                }

                $newItem = [
                    'id' => $db->generateId(),
                    'name' => $item['name'],
                    'sku' => $item['sku'] ?? generateSKU($item['name']),
                    'description' => $item['description'] ?? '',
                    'category' => $item['category'] ?? 'General',
                    'cost' => floatval($item['cost'] ?? 0),
                    'price' => floatval($item['price'] ?? 0),
                    'quantity' => intval($item['quantity'] ?? 0),
                    'reorderPoint' => intval($item['reorderPoint'] ?? 5),
                    'supplier' => $item['supplier'] ?? '',
                    'notes' => $item['notes'] ?? '',
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];

                $inventory[] = $newItem;
                $created++;
            }

            if ($created > 0) {
                $db->save('inventory', $inventory);
                successResponse(['created' => $created], "Created {$created} inventory item(s)");
            } else {
                errorResponse('No valid items to create', 400, ERROR_VALIDATION);
            }
            break;

        case 'generate_note_content':
            $prompt = $body['prompt'] ?? '';
            if (empty($prompt)) {
                errorResponse('Prompt is required', 400, ERROR_VALIDATION);
            }

            $aiPrompt = <<<PROMPT
You are a helpful note-writing assistant. Write content for a note based on this request: "{$prompt}"

Requirements:
- Write in clear, professional language
- Use proper formatting with headings, bullet points, and paragraphs where appropriate
- Be concise but comprehensive
- Include relevant details and examples if helpful
- Use markdown formatting for structure (## for headings, - for bullets, etc.)

Return only the note content, no explanations or meta-commentary.
PROMPT;

            try {
                $result = $api->complete($aiPrompt, $model);
                if (isset($result['error'])) {
                    errorResponse('AI Error: ' . ($result['error']['message'] ?? 'Unknown error'), 500, 'AI_ERROR');
                }
                $content = $result['choices'][0]['message']['content'] ?? $result;
                aiCountSharedUsage($config, $userId, $provider);
                successResponse(['content' => $content]);
            } catch (Exception $e) {
                errorResponse('AI request failed: ' . $e->getMessage(), 500, 'AI_ERROR');
            }
            break;

        case 'edit_note':
            $noteId = $body['noteId'] ?? '';
            $operation = $body['operation'] ?? '';

            if (empty($noteId)) {
                errorResponse('Note ID is required', 400, ERROR_VALIDATION);
            }
            if (empty($operation)) {
                errorResponse('Operation is required', 400, ERROR_VALIDATION);
            }

            // Validate operation
            $validOperations = ['rewrite', 'improve', 'expand', 'summarize'];
            if (!in_array($operation, $validOperations)) {
                errorResponse('Invalid operation. Must be one of: ' . implode(', ', $validOperations), 400, ERROR_VALIDATION);
            }

            // Load note from database
            $notes = $db->load('notes', true);
            $note = null;
            $noteIndex = null;
            $currentUserId = Auth::userId();
            foreach ($notes as $index => $n) {
                if (($n['id'] ?? '') !== $noteId) {
                    continue;
                }
                $noteUserId = $n['userId'] ?? null;
                if (!empty($noteUserId) && $noteUserId !== $currentUserId) {
                    continue;
                }
                $note = $n;
                $noteIndex = $index;
                break;
            }

            if (!$note) {
                errorResponse('Note not found', 404, ERROR_NOT_FOUND);
            }

            if (empty($note['userId']) && $noteIndex !== null && !empty($currentUserId)) {
                $notes[$noteIndex]['userId'] = $currentUserId;
                $note['userId'] = $currentUserId;
                $db->save('notes', $notes);
            }

            $noteContent = $note['content'] ?? '';
            $customPrompt = $body['prompt'] ?? '';

            // Build operation-specific prompt
            $operationBasePrompts = [
                'rewrite' => "Rewrite the following note for clarity, better structure, and professional tone. Preserve all key information and meaning while removing redundancy and improving flow.",
                'improve' => "Improve the following note by fixing grammar, spelling, punctuation, and sentence structure. Enhance vocabulary and readability while preserving the original meaning, tone, and technical accuracy.",
                'expand' => "Expand the following note by adding relevant examples, elaboration, and context to key points. Maintain the original structure and tone. Add 30-50% more content with valuable details.",
                'summarize' => "Summarize the following note into concise bullet points capturing all key information, main ideas, and conclusions. Reduce content to 20-30% of original length."
            ];

            $basePrompt = $operationBasePrompts[$operation] ?? "Please edit this note";

            // Append custom instructions if provided
            if (!empty($customPrompt)) {
                $aiPrompt = "{$basePrompt}\n\nAdditional instructions from user: \"{$customPrompt}\"\n\nNote content to edit:\n{$noteContent}";
            } else {
                $aiPrompt = "{$basePrompt}\n\n{$noteContent}";
            }

            try {
                $result = $api->complete($aiPrompt, $model);
                if (isset($result['error'])) {
                    errorResponse('AI Error: ' . ($result['error']['message'] ?? 'Unknown error'), 500, 'AI_ERROR');
                }

                $editedContent = $result['choices'][0]['message']['content'] ?? $result;

                // Clean up markdown code blocks if AI added them
                $editedContent = preg_replace('/^```markdown?\n/m', '', $editedContent);
                $editedContent = preg_replace('/^```\n/m', '', $editedContent);
                $editedContent = preg_replace('/\n```$/m', '', $editedContent);

                // Calculate word counts
                $originalWordCount = str_word_count(strip_tags($noteContent));
                $editedWordCount = str_word_count(strip_tags($editedContent));

                aiCountSharedUsage($config, $userId, $provider);
                successResponse([
                    'content' => $editedContent,
                    'originalWordCount' => $originalWordCount,
                    'editedWordCount' => $editedWordCount,
                    'operation' => $operation
                ]);
            } catch (Exception $e) {
                errorResponse('AI request failed: ' . $e->getMessage(), 500, 'AI_ERROR');
            }
            break;

        case 'notes_chat':
            // Docked "Notes AI" side-panel: a free-form chat that always sees the
            // current note, and optionally the titles of every other note, so the
            // user can brainstorm/edit alongside the editor. Client owns insertion.
            $noteId = $body['noteId'] ?? '';
            $incomingMessages = $body['messages'] ?? [];
            $includeAllNotes = !empty($body['includeAllNotes']);

            if (!is_array($incomingMessages) || empty($incomingMessages)) {
                errorResponse('At least one message is required', 400, ERROR_VALIDATION);
            }

            // Sanitize the conversation turns: known roles, string content, capped
            // length and turn-count so a runaway history can't blow the context.
            $chatMessages = [];
            foreach (array_slice($incomingMessages, -20) as $m) {
                if (!is_array($m)) { continue; }
                $role = $m['role'] ?? 'user';
                if (!in_array($role, ['user', 'assistant'], true)) { $role = 'user'; }
                $content = trim((string)($m['content'] ?? ''));
                if ($content === '') { continue; }
                $chatMessages[] = ['role' => $role, 'content' => mb_substr($content, 0, 8000)];
            }
            if (empty($chatMessages)) {
                errorResponse('At least one non-empty message is required', 400, ERROR_VALIDATION);
            }

            // Load the current note (ownership-checked, mirrors edit_note above).
            $allNotes = $db->load('notes', true);
            $currentUserId = Auth::userId();
            $currentNote = null;
            foreach ($allNotes as $n) {
                if (($n['id'] ?? '') !== $noteId) { continue; }
                $noteUserId = $n['userId'] ?? null;
                if (!empty($noteUserId) && $noteUserId !== $currentUserId) { continue; }
                $currentNote = $n;
                break;
            }

            // Build the grounding system prompt.
            $systemPrompt = "You are a focused writing assistant embedded in a note-taking app. "
                . "Help the user brainstorm, draft, rewrite, and refine their notes. "
                . "When asked to produce note content, use clean markdown (## headings, - bullets, **bold**). "
                . "Be concise and directly useful; do not add meta-commentary unless asked.";

            if ($currentNote) {
                $noteTitle = trim((string)($currentNote['title'] ?? 'Untitled'));
                $notePlain = trim(strip_tags((string)($currentNote['content'] ?? '')));
                $notePlain = mb_substr($notePlain, 0, 6000);
                $systemPrompt .= "\n\n--- CURRENT NOTE ---\nTitle: {$noteTitle}\n\n"
                    . ($notePlain !== '' ? $notePlain : '(empty note)') . "\n--- END CURRENT NOTE ---";
            }

            if ($includeAllNotes) {
                $titles = [];
                foreach ($allNotes as $n) {
                    $noteUserId = $n['userId'] ?? null;
                    if (!empty($noteUserId) && $noteUserId !== $currentUserId) { continue; }
                    if (($n['id'] ?? '') === $noteId) { continue; }
                    $t = trim((string)($n['title'] ?? ''));
                    if ($t !== '') { $titles[] = $t; }
                    if (count($titles) >= 100) { break; }
                }
                if (!empty($titles)) {
                    $systemPrompt .= "\n\n--- USER'S OTHER NOTE TITLES ---\n- " . implode("\n- ", $titles)
                        . "\n--- END TITLES ---";
                }
            }

            array_unshift($chatMessages, ['role' => 'system', 'content' => $systemPrompt]);

            try {
                $result = $api->chatCompletion($chatMessages, $model);
                if (isset($result['error'])) {
                    errorResponse('AI Error: ' . ($result['error']['message'] ?? 'Unknown error'), 500, 'AI_ERROR');
                }
                $content = $result['choices'][0]['message']['content'] ?? '';
                aiCountSharedUsage($config, $userId, $provider);
                successResponse(['content' => $content]);
            } catch (Exception $e) {
                errorResponse('AI request failed: ' . $e->getMessage(), 500, 'AI_ERROR');
            }
            break;

        case 'check_requirements':
            // Check PHP extension requirements for AI features
            $extensions = [
                'curl' => [
                    'loaded' => extension_loaded('curl'),
                    'required' => true,
                    'feature' => 'AI API requests (Groq, OpenRouter)',
                    'docs' => 'https://www.php.net/manual/en/curl.installation.php'
                ],
                'openssl' => [
                    'loaded' => extension_loaded('openssl'),
                    'required' => true,
                    'feature' => 'Data encryption/decryption',
                    'docs' => 'https://www.php.net/manual/en/openssl.installation.php'
                ],
                'mbstring' => [
                    'loaded' => extension_loaded('mbstring'),
                    'required' => true,
                    'feature' => 'Multi-byte string handling',
                    'docs' => 'https://www.php.net/manual/en/mbstring.installation.php'
                ],
                'json' => [
                    'loaded' => extension_loaded('json'),
                    'required' => true,
                    'feature' => 'JSON data handling',
                    'docs' => 'https://www.php.net/manual/en/json.installation.php'
                ],
                'zip' => [
                    'loaded' => extension_loaded('zip'),
                    'required' => false,
                    'feature' => 'Backup export (ZIP format)',
                    'docs' => 'https://www.php.net/manual/en/zip.installation.php'
                ]
            ];

            $allRequiredLoaded = true;
            $missingRequired = [];

            foreach ($extensions as $name => $info) {
                if (!$info['loaded'] && $info['required']) {
                    $allRequiredLoaded = false;
                    $missingRequired[] = $name;
                }
            }

            successResponse([
                'extensions' => $extensions,
                'all_required_loaded' => $allRequiredLoaded,
                'missing_required' => $missingRequired,
                'php_version' => PHP_VERSION,
                'php_version_ok' => version_compare(PHP_VERSION, '8.0.0', '>=')
            ]);
            break;

        default:
            errorResponse('Invalid action', 400, ERROR_VALIDATION);
    }

    // Increment API usage on successful operations
    $rateLimiter->incrementApiUsage($userId, 'ai');

} catch (Exception $e) {
    handleException($e);
}

