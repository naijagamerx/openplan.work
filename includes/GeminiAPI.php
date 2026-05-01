<?php
/**
 * Google Gemini AI Integration
 *
 * Uses the Google Generative AI REST API (v1beta).
 * Requires a Google AI Studio API key.
 *
 * Docs: https://ai.google.dev/gemini-api/docs
 * Get API Key: https://aistudio.google.com/app/apikey
 *
 * Note: Gemini uses a different API format from OpenAI-compatible providers.
 * This class converts to/from the OpenAI message format so the rest of the
 * application works without modification.
 */

class GeminiAPI {
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    /**
     * Chat completion.
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     * Models MUST come from database (Model Settings page: /?page=model-settings)
     */
    public function chatCompletion(array $messages, ?string $model = null, array $options = []): array {
        if (empty($model)) {
            throw new APIException(
                'Gemini model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'gemini']
            );
        }

        [$contents, $systemInstruction] = $this->convertMessages($messages);

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'     => (float)($options['temperature'] ?? 0.7),
                'maxOutputTokens' => (int)($options['maxTokens'] ?? 8192)
            ]
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        $raw = $this->makeRequest("/models/{$model}:generateContent", $payload);
        return $this->normalizeResponse($raw);
    }

    /**
     * Chat completion with native Gemini function calling.
     *
     * Converts OpenAI-style function definitions to Gemini's functionDeclarations format,
     * sends the request, then normalizes the response back to OpenAI tool_calls format
     * so the AIAgent works without any changes.
     *
     * Gemini request format:
     *   tools: [{ functionDeclarations: [{ name, description, parameters }] }]
     *
     * Gemini response format (when tool is called):
     *   candidates[0].content.parts[0].functionCall = { name, args }
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     */
    public function chatWithFunctions(array $messages, array $functions, ?string $model = null, array $options = []): array {
        if (empty($model)) {
            throw new APIException(
                'Gemini model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'gemini']
            );
        }

        [$contents, $systemInstruction] = $this->convertMessages($messages);

        // Convert OpenAI function definitions → Gemini functionDeclarations
        $functionDeclarations = array_map(function ($fn) {
            $def = $fn['function'] ?? $fn;
            $decl = [
                'name'        => $def['name'] ?? '',
                'description' => $def['description'] ?? '',
            ];
            if (!empty($def['parameters'])) {
                $decl['parameters'] = $def['parameters'];
            }
            return $decl;
        }, $functions);

        $payload = [
            'contents'         => $contents,
            'tools'            => [['functionDeclarations' => $functionDeclarations]],
            'generationConfig' => [
                'temperature'     => (float)($options['temperature'] ?? 0.7),
                'maxOutputTokens' => (int)($options['maxTokens'] ?? 8192),
            ],
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
        }

        $raw = $this->makeRequest("/models/{$model}:generateContent", $payload);

        // Normalize response: check for functionCall in parts
        $parts       = $raw['candidates'][0]['content']['parts'] ?? [];
        $finishReason = $raw['candidates'][0]['finishReason'] ?? 'stop';

        $toolCalls = [];
        $textContent = null;

        foreach ($parts as $part) {
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $toolCalls[] = [
                    'id'       => 'call_' . bin2hex(random_bytes(6)),
                    'type'     => 'function',
                    'function' => [
                        'name'      => $fc['name'] ?? '',
                        'arguments' => json_encode($fc['args'] ?? []),
                    ],
                ];
            } elseif (isset($part['text'])) {
                $textContent = ($textContent ?? '') . $part['text'];
            }
        }

        // Build normalized OpenAI-format response
        $message = ['role' => 'assistant', 'content' => $textContent];
        if (!empty($toolCalls)) {
            $message['tool_calls'] = $toolCalls;
        }

        return [
            'choices' => [['message' => $message, 'finish_reason' => $finishReason]],
            'usage'   => $raw['usageMetadata'] ?? [],
        ];
    }

    /**
     * Simple completion for AIHelper.
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     */
    public function complete(string $prompt, ?string $model = null): string {
        if (empty($model)) {
            throw new APIException(
                'Gemini model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'gemini']
            );
        }

        $response = $this->chatCompletion([
            ['role' => 'user', 'content' => $prompt]
        ], $model);

        if (isset($response['error'])) {
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'gemini']);
        }

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Generate task breakdown.
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
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'gemini']);
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        preg_match('/\{[\s\S]*\}/', $content, $matches);
        if (!empty($matches[0])) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed];
            }
        }

        throw new APIException('Failed to parse AI response', 'AI_PARSE_ERROR', 500, ['provider' => 'gemini']);
    }

    /**
     * Generate PRD.
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
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'gemini']);
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'prd' => $content];
    }

    /**
     * Convert OpenAI-style messages array to Gemini format.
     *
     * Rules:
     *  - system → extracted into systemInstruction (returned separately)
     *  - user    → role: "user"
     *  - assistant → role: "model"
     *
     * @return array [contents, systemInstruction|null]
     */
    private function convertMessages(array $messages): array {
        $contents          = [];
        $systemInstruction = null;

        foreach ($messages as $msg) {
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            if ($role === 'system') {
                // Collect all system messages into one instruction block
                $systemInstruction = ($systemInstruction !== null)
                    ? $systemInstruction . "\n\n" . $content
                    : $content;
                continue;
            }

            // OpenAI 'assistant' → Gemini 'model'
            $geminiRole = ($role === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role'  => $geminiRole,
                'parts' => [['text' => $content]]
            ];
        }

        // Gemini requires at least one user turn
        if (empty($contents)) {
            $contents[] = ['role' => 'user', 'parts' => [['text' => 'Hello']]];
        }

        return [$contents, $systemInstruction];
    }

    /**
     * Convert Gemini response to OpenAI-compatible format.
     *
     * Gemini: candidates[0].content.parts[0].text
     * OpenAI: choices[0].message.content
     */
    private function normalizeResponse(array $response): array {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return [
            'choices' => [
                [
                    'message'       => ['role' => 'assistant', 'content' => $text],
                    'finish_reason' => $response['candidates'][0]['finishReason'] ?? 'stop'
                ]
            ],
            'usage' => $response['usageMetadata'] ?? []
        ];
    }

    /**
     * Make API request to Gemini.
     *
     * Endpoint format: POST /v1beta/models/{model}:generateContent?key={apiKey}
     * @throws APIException
     */
    private function makeRequest(string $endpoint, array $payload): array {
        if (!function_exists('curl_init')) {
            throw new APIException(
                'PHP cURL extension is not enabled. Please enable php_curl to use AI features.',
                'CURL_MISSING',
                500,
                ['provider' => 'gemini']
            );
        }

        $allowInsecureSsl = getenv('LAZYMAN_ALLOW_INSECURE_SSL') === '1';
        $caBundlePath = $allowInsecureSsl ? null : $this->resolveCaBundlePath();
        $url = $this->baseUrl . $endpoint . '?key=' . urlencode($this->apiKey);
        $ch  = curl_init($url);

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30, // Shared hosting: fail fast rather than 504
            CURLOPT_SSL_VERIFYPEER => !$allowInsecureSsl,
            CURLOPT_SSL_VERIFYHOST => $allowInsecureSsl ? 0 : 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ];

        if (!$allowInsecureSsl && !empty($caBundlePath)) {
            $curlOptions[CURLOPT_CAINFO] = $caBundlePath;
        }

        curl_setopt_array($ch, $curlOptions);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            if (stripos($curlError, 'SSL certificate') !== false || stripos($curlError, 'unable to get local issuer') !== false) {
                throw new APIException(
                    "SSL certificate error connecting to Gemini API. Set LAZYMAN_ALLOW_INSECURE_SSL=1 in your environment for local development.",
                    'CURL_SSL_ERROR', 500, ['provider' => 'gemini', 'curl_error' => $curlError]
                );
            }
            throw new APIException('cURL error: ' . $curlError, 'CURL_ERROR', 500, ['provider' => 'gemini']);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $data['error']['message'] ?? 'Gemini API request failed';
            throw new APIException($errorMessage, 'API_ERROR', $httpCode, ['provider' => 'gemini', 'response' => $data]);
        }

        return $data;
    }

    /**
     * Resolve a CA bundle path for local SSL verification (Windows/MAMP/Hostinger friendly).
     * Same logic as GroqAPI::resolveCaBundlePath().
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
     * Get available Gemini models.
     */
    public static function getModels(): array {
        return [
            'gemini-2.0-flash'     => 'Gemini 2.0 Flash',
            'gemini-1.5-pro'       => 'Gemini 1.5 Pro',
            'gemini-1.5-flash'     => 'Gemini 1.5 Flash',
            'gemini-2.0-pro-exp'   => 'Gemini 2.0 Pro (Experimental)'
        ];
    }
}
