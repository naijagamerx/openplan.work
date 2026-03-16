<?php
/**
 * Setup script to add default models and test API keys
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Encryption.php';

echo "=== TaskManager Setup Script ===\n\n";

// Get master password from argument or environment
$masterPassword = $argv[1] ?? getenv('LAZYMAN_MASTER_PASSWORD') ?? '';

if (empty($masterPassword)) {
    echo "ERROR: Master password required.\n";
    echo "Usage: php setup_models.php <master_password>\n";
    echo "   Or set LAZYMAN_MASTER_PASSWORD environment variable\n";
    exit(1);
}

$db = new Database($masterPassword);

// Default Groq models
$groqModels = [
    [
        'id' => 'groq-llama-3.3-70b',
        'provider' => 'groq',
        'displayName' => 'Llama 3.3 70B',
        'modelId' => 'llama-3.3-70b-versatile',
        'description' => 'Meta Llama 3.3 70B - Fast and capable',
        'enabled' => true,
        'isDefault' => true,
        'contextLength' => 32768,
        'createdAt' => date('c')
    ],
    [
        'id' => 'groq-llama-3.1-8b',
        'provider' => 'groq',
        'displayName' => 'Llama 3.1 8B (Fast)',
        'modelId' => 'llama-3.1-8b-instant',
        'description' => 'Fast Llama model for quick tasks',
        'enabled' => true,
        'isDefault' => false,
        'contextLength' => 32768,
        'createdAt' => date('c')
    ],
    [
        'id' => 'groq-mixtral-8x7b',
        'provider' => 'groq',
        'displayName' => 'Mixtral 8x7B',
        'modelId' => 'mixtral-8x7b-32768',
        'description' => 'Mixtral mixture of experts model',
        'enabled' => true,
        'isDefault' => false,
        'contextLength' => 32768,
        'createdAt' => date('c')
    ],
    [
        'id' => 'groq-gemma2-9b',
        'provider' => 'groq',
        'displayName' => 'Gemma 2 9B',
        'modelId' => 'gemma2-9b-it',
        'description' => 'Google Gemma 2 9B instruction-tuned',
        'enabled' => true,
        'isDefault' => false,
        'contextLength' => 8192,
        'createdAt' => date('c')
    ]
];

// Default OpenRouter models
$openRouterModels = [
    [
        'id' => 'or-claude-3.5-sonnet',
        'provider' => 'openrouter',
        'displayName' => 'Claude 3.5 Sonnet',
        'modelId' => 'anthropic/claude-3.5-sonnet',
        'description' => 'Anthropic Claude 3.5 Sonnet',
        'enabled' => false,
        'isDefault' => false,
        'contextLength' => 200000,
        'createdAt' => date('c')
    ],
    [
        'id' => 'or-gpt-4-turbo',
        'provider' => 'openrouter',
        'displayName' => 'GPT-4 Turbo',
        'modelId' => 'openai/gpt-4-turbo',
        'description' => 'OpenAI GPT-4 Turbo 128K',
        'enabled' => false,
        'isDefault' => false,
        'contextLength' => 128000,
        'createdAt' => date('c')
    ],
    [
        'id' => 'or-gpt-oss-120b',
        'provider' => 'openrouter',
        'displayName' => 'GPT-OSS 120B',
        'modelId' => 'openai/gpt-oss-120b',
        'description' => 'OpenAI OSS 120B parameter model via OpenRouter',
        'enabled' => true,
        'isDefault' => false,
        'contextLength' => 16384,
        'createdAt' => date('c')
    ],
    [
        'id' => 'or-llama-3.3-70b',
        'provider' => 'openrouter',
        'displayName' => 'Llama 3.3 70B (OR)',
        'modelId' => 'meta-llama/llama-3.3-70b-instruct',
        'description' => 'Meta Llama 3.3 70B via OpenRouter',
        'enabled' => false,
        'isDefault' => false,
        'contextLength' => 32768,
        'createdAt' => date('c')
    ]
];

// Load existing models or use defaults
echo "Loading models...\n";
$existingModels = $db->safeLoad('models');

if (!$existingModels['success'] || empty($existingModels['data'])) {
    echo "No existing models found. Creating defaults...\n";
    $models = [
        'groq' => $groqModels,
        'openrouter' => $openRouterModels
    ];
} else {
    echo "Existing models found.\n";
    $models = $existingModels['data'];

    // Add gpt-oss-120b if not exists
    $hasGptOss = false;
    foreach ($models['openrouter'] ?? [] as $m) {
        if ($m['modelId'] === 'openai/gpt-oss-120b') {
            $hasGptOss = true;
            break;
        }
    }

    if (!$hasGptOss) {
        echo "Adding GPT-OSS 120B model...\n";
        $models['openrouter'] = array_merge($models['openrouter'] ?? [], $openRouterModels);
    }
}

// Save models
echo "Saving models to database...\n";
if ($db->save('models', $models)) {
    echo "✅ Models saved successfully!\n";
} else {
    echo "❌ Failed to save models\n";
    exit(1);
}

// Test Groq API key
echo "\n=== Testing Groq API ===\n";
$config = $db->load('config');
$groqKey = $config['groqApiKey'] ?? '';

if (empty($groqKey)) {
    echo "No Groq API key in config. Checking environment variable...\n";
    $groqKey = getenv('GROQ_API_KEY') ?? '';
    
    if (!empty($groqKey)) {
        echo "Found Groq API key in environment.\n";
        // Optionally save to config if desired, but for security maybe not?
        // Let's just use it for the test.
    } else {
        echo "⚠️ No Groq API key found. Skipping API test.\n";
    }
}

if (!empty($groqKey)) {
    // Test the API
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $groqKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'llama-3.3-70b-versatile',
        'messages' => [['role' => 'user', 'content' => 'Say "API working!"']],
        'max_tokens' => 50
    ]),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['choices'][0]['message']['content'])) {
    echo "✅ Groq API Test PASSED!\n";
    echo "Response: " . trim($data['choices'][0]['message']['content']) . "\n";
} else {
    echo "❌ Groq API Test FAILED\n";
    echo "HTTP: $httpCode\n";
    if (isset($data['error']['message'])) {
        echo "Error: " . $data['error']['message'] . "\n";
    }
}
}

echo "\n=== Setup Complete ===\n";
echo "\nModels configured:\n";
echo "  Groq: " . count($models['groq'] ?? []) . " models\n";
echo "  OpenRouter: " . count($models['openrouter'] ?? []) . " models\n";
echo "\nGPT-OSS 120B has been added to OpenRouter models.\n";
