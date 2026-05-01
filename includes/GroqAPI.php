<?php
/**
 * Groq API Integration
 */

class GroqAPI {
    private string $apiKey;
    private string $baseUrl = 'https://api.groq.com/openai/v1';

    /**
     * Per-model free-tier TPM (tokens per minute) caps as published by Groq.
     * Source: https://console.groq.com/docs/rate-limits
     *
     * CRITICAL: Groq counts the *reserved* max_tokens against TPM, not just
     * the actual output. So if max_tokens=8192 and the model TPM is 8000,
     * the request is rejected before any tokens are emitted — even for a
     * one-word prompt like "hi". We must always reserve LESS than the cap.
     */
    private const MODEL_TPM_FREE_TIER = [
        'openai/gpt-oss-120b'        => 8000,
        'openai/gpt-oss-20b'         => 8000,
        'llama-3.3-70b-versatile'    => 12000,
        'llama-3.1-8b-instant'       => 6000,
        'gemma2-9b-it'               => 15000,
        'mixtral-8x7b-32768'         => 5000,
        'meta-llama/llama-4-scout-17b-16e-instruct'  => 30000,
        'meta-llama/llama-4-maverick-17b-128e-instruct' => 6000,
        'qwen/qwen3-32b'             => 6000,
        'deepseek-r1-distill-llama-70b' => 6000,
    ];

    /** Conservative fallback when the model is unknown to the map above. */
    private const DEFAULT_TPM = 6000;

    /** Safety margin (tokens) we always leave between our reservation and the cap. */
    private const TPM_SAFETY_MARGIN = 384;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Compute a max_tokens reservation that fits within the model's TPM cap
     * after subtracting estimated input tokens and a safety margin.
     *
     * The estimate uses the chars/4 heuristic (~standard English token rate).
     * We never go below 256 — there's always room for at least a short reply.
     */
    private function safeMaxTokens(string $model, array $messages, int $requested): int {
        $tpm = self::MODEL_TPM_FREE_TIER[$model] ?? self::DEFAULT_TPM;
        $chars = 0;
        foreach ($messages as $m) {
            $chars += strlen((string)($m['content'] ?? ''));
            // Tool payloads (when chatWithFunctions is used) are accounted for
            // by the caller passing extra options['toolPayloadChars'] — small
            // omission acceptable here, the safety margin absorbs jitter.
        }
        $estInput = (int)ceil($chars / 4);
        $available = $tpm - $estInput - self::TPM_SAFETY_MARGIN;
        return max(256, min($requested, $available));
    }

    /**
     * Chat completion
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     * Models MUST come from database (Model Settings page: /?page=model-settings)
     */
    public function chatCompletion(array $messages, ?string $model = null, array $options = []): array {
        if (empty($model)) {
            throw new APIException(
                'Groq model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'groq']
            );
        }

        // Default reservation for chat replies: 1024 tokens. Plenty for normal
        // chat (≈750 words). Caller can request larger via $options['maxTokens'];
        // safeMaxTokens() will clamp to whatever fits in the model's TPM cap.
        $requested = (int)($options['maxTokens'] ?? 1024);
        $maxTokens = $this->safeMaxTokens($model, $messages, $requested);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float)($options['temperature'] ?? 0.7),
            'max_tokens' => $maxTokens
        ];

        return $this->makeRequest('/chat/completions', $payload);
    }

    /**
     * Chat completion with function calling
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     * Models MUST come from database (Model Settings page: /?page=model-settings)
     */
    public function chatWithFunctions(array $messages, array $functions, ?string $model = null, array $options = []): array {
        if (empty($model)) {
            throw new APIException(
                'Groq model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'groq']
            );
        }

        // Tool definitions also count toward TPM. Add their JSON length to the
        // estimate so we don't underestimate input size.
        $toolChars = strlen(json_encode($functions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $messagesPlusTools = $messages;
        $messagesPlusTools[] = ['role' => 'system', 'content' => str_repeat(' ', $toolChars)];

        $requested = (int)($options['maxTokens'] ?? 1024);
        $maxTokens = $this->safeMaxTokens($model, $messagesPlusTools, $requested);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'tools' => $functions,
            'tool_choice' => 'auto',
            'temperature' => (float)($options['temperature'] ?? 0.7),
            'max_tokens' => $maxTokens
        ];

        return $this->makeRequest('/chat/completions', $payload);
    }

    /**
     * Simple completion for AIHelper
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     * Models MUST come from database (Model Settings page: /?page=model-settings)
     */
    public function complete(string $prompt, ?string $model = null): string {
        if (empty($model)) {
            throw new APIException(
                'Groq model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'groq']
            );
        }

        $response = $this->chatCompletion([
            ['role' => 'user', 'content' => $prompt]
        ], $model);

        if (isset($response['error'])) {
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'groq']);
        }

        return $response['choices'][0]['message']['content'] ?? '';
    }



    /**
     * Generate task breakdown
     */
    public function generateTasks(string $description): array {
        $systemPrompt = <<<PROMPT
You are a project management expert. Analyze the project description and generate a comprehensive task breakdown.

Return ONLY valid JSON in this exact format:
{
    "tasks": [
        {
            "title": "Task name",
            "description": "Detailed description",
            "priority": "high|medium|low",
            "estimatedMinutes": 60,
            "subtasks": [
                {
                    "title": "Subtask name",
                    "description": "Subtask description",
                    "estimatedMinutes": 30
                }
            ]
        }
    ]
}
PROMPT;

        $response = $this->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $description]
        ]);

        if (isset($response['error'])) {
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'groq']);
        }

        $content = $response['choices'][0]['message']['content'] ?? '';

        // Extract JSON from response
        preg_match('/\{[\s\S]*\}/', $content, $matches);
        if (!empty($matches[0])) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed];
            }
        }

        throw new APIException('Failed to parse AI response', 'AI_PARSE_ERROR', 500, ['provider' => 'groq']);
    }

    /**
     * Generate PRD
     */
    public function generatePRD(string $idea): array {
        $systemPrompt = <<<PROMPT
You are a senior product manager. Create a comprehensive Product Requirements Document (PRD) for the given project idea.

Include these sections:
1. Executive Summary
2. Problem Statement
3. Goals & Objectives
4. Target Users
5. Key Features
6. Technical Requirements
7. Success Metrics
8. Timeline Estimate

Format the response in Markdown.
PROMPT;

        $response = $this->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $idea]
        ]);

        if (isset($response['error'])) {
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'groq']);
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'prd' => $content];
    }

    /**
     * Make API request
     * @throws APIException
     */
    private function makeRequest(string $endpoint, array $payload): array {
        if (!function_exists('curl_init')) {
            throw new APIException(
                'PHP cURL extension is not enabled. Please enable php_curl to use AI features.',
                'CURL_MISSING',
                500,
                ['provider' => 'groq']
            );
        }

        $ch = curl_init($this->baseUrl . $endpoint);

        // SSL verification can only be disabled explicitly for local troubleshooting.
        $allowInsecureSsl = getenv('LAZYMAN_ALLOW_INSECURE_SSL') === '1';
        $caBundlePath = $allowInsecureSsl ? null : $this->resolveCaBundlePath();

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => !$allowInsecureSsl,
            CURLOPT_SSL_VERIFYHOST => $allowInsecureSsl ? 0 : 2,
            // Follow redirects
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ];

        if (!$allowInsecureSsl && !empty($caBundlePath)) {
            $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            if (stripos($error, 'SSL certificate problem') !== false) {
                $help = 'Local SSL trust store is missing. Set php.ini openssl.cafile/curl.cainfo to a valid cacert.pem';
                if (!empty($caBundlePath)) {
                    $help .= " (auto-detected: {$caBundlePath})";
                }
                throw new APIException("cURL error: {$error}. {$help}.", 'CURL_ERROR', 500, ['provider' => 'groq']);
            }
            throw new APIException('cURL error: ' . $error, 'CURL_ERROR', 500, ['provider' => 'groq']);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $data['error']['message'] ?? 'API request failed';
            throw new APIException($errorMessage, 'API_ERROR', $httpCode, ['provider' => 'groq', 'response' => $data]);
        }

        return $data;
    }

    /**
     * Resolve a CA bundle path for local SSL verification (Windows/MAMP/Hostinger friendly).
     */
    private function resolveCaBundlePath(): ?string {
        $phpDir = dirname(PHP_BINARY);
        $candidates = array_filter([
            getenv('CURL_CA_BUNDLE') ?: null,
            getenv('SSL_CERT_FILE') ?: null,
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            defined('ROOT_PATH') ? ROOT_PATH . '/cacert.pem' : null,
            defined('ROOT_PATH') ? ROOT_PATH . '/certs/cacert.pem' : null,
            $phpDir . '/extras/ssl/cacert.pem',
            $phpDir . '/cacert.pem',
            // Common Linux/Hostinger paths
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/ssl/certs/ca-bundle.crt',
            '/usr/local/share/certs/ca-root-nss.crt',
            '/etc/ssl/cert.pem',
            '/usr/local/etc/openssl/cert.pem',
        ]);

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Get available models
     */
    public static function getModels(): array {
        return [
            'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
            'llama-3.1-8b-instant' => 'Llama 3.1 8B (Fast)',
            'mixtral-8x7b-32768' => 'Mixtral 8x7B',
            'gemma2-9b-it' => 'Gemma 2 9B'
        ];
    }
}
