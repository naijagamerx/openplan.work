<?php
/**
 * Ollama AI Integration (Local LLM)
 *
 * Connects to a locally running Ollama instance via its OpenAI-compatible API.
 * No API key required — just a base URL (default: http://localhost:11434).
 *
 * Docs: https://github.com/ollama/ollama/blob/main/docs/openai.md
 */

class OllamaAPI {
    private string $baseUrl;

    public function __construct(string $baseUrl = 'http://localhost:11434') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Chat completion via Ollama's OpenAI-compatible endpoint.
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     * Models MUST come from database (Model Settings page: /?page=model-settings)
     */
    public function chatCompletion(array $messages, ?string $model = null, array $options = []): array {
        if (empty($model)) {
            throw new APIException(
                'Ollama model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'ollama']
            );
        }

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => (float)($options['temperature'] ?? 0.7),
            'max_tokens'  => (int)($options['maxTokens'] ?? 8192),
            'stream'      => false
        ];

        return $this->makeRequest('/v1/chat/completions', $payload);
    }

    /**
     * Chat completion with function calling.
     *
     * Enhanced for small model compatibility:
     * - Injects simplified JSON tool-call instructions into the system prompt
     * - Falls back to parsing tool calls from text if native tool_calls is absent
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     */
    public function chatWithFunctions(array $messages, array $functions, ?string $model = null, array $options = []): array {
        if (empty($model)) {
            throw new APIException(
                'Ollama model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'ollama']
            );
        }

        // Inject simplified tool-calling instructions for small-model compatibility
        $messages = $this->injectToolInstruction($messages, $functions);

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'tools'       => $functions,
            'tool_choice' => 'auto',
            'temperature' => (float)($options['temperature'] ?? 0.7),
            'max_tokens'  => (int)($options['maxTokens'] ?? 8192),
            'stream'      => false
        ];

        $response = $this->makeRequest('/v1/chat/completions', $payload);

        // Fallback: if the model returned no native tool_calls, try to parse from text content
        $toolCalls = $response['choices'][0]['message']['tool_calls'] ?? [];
        if (empty($toolCalls)) {
            $content = $response['choices'][0]['message']['content'] ?? '';
            $parsed  = $this->parseToolCallsFromText($content);
            if (!empty($parsed)) {
                $response['choices'][0]['message']['tool_calls'] = $parsed;
                $response['choices'][0]['message']['content']    = null;
            }
        }

        return $response;
    }

    /**
     * Prepend a simplified tool-calling instruction to the system message.
     * This helps small models (4B-9B) understand the expected JSON output format
     * even when they don't fully support the native OpenAI tools schema.
     */
    private function injectToolInstruction(array $messages, array $functions): array {
        if (empty($functions)) return $messages;

        $toolList = implode(', ', array_filter(array_map(
            fn($f) => $f['function']['name'] ?? $f['name'] ?? '',
            $functions
        )));

        $instruction = "\n\nTOOL CALLING INSTRUCTIONS: You have access to the following tools: {$toolList}.\n" .
            "When you need to use a tool, output ONLY a valid JSON object in this exact format — nothing else before or after it:\n" .
            "{\"name\": \"tool_name\", \"arguments\": {\"param\": \"value\"}}\n" .
            "When responding normally without using a tool, write plain text. " .
            "NEVER mix a JSON tool call with plain text in the same response.";

        // Append to first system message, or insert one at the beginning
        foreach ($messages as &$msg) {
            if (($msg['role'] ?? '') === 'system') {
                $msg['content'] .= $instruction;
                return $messages;
            }
        }
        unset($msg);
        array_unshift($messages, ['role' => 'system', 'content' => ltrim($instruction)]);
        return $messages;
    }

    /**
     * Try to extract a tool call from plain text when native tool_calls is absent.
     * Handles JSON in fenced code blocks and bare JSON objects.
     *
     * @return array OpenAI-format tool_calls array, or empty array if nothing found
     */
    private function parseToolCallsFromText(string $content): array {
        if (empty($content)) return [];

        $patterns = [
            '/```(?:json)?\s*(\{[\s\S]+?\})\s*```/i',                               // fenced JSON block
            '/(\{"name"\s*:\s*"[^"]+"\s*,\s*"arguments"\s*:\s*\{[\s\S]*?\}\s*\})/s', // bare JSON object
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $m)) {
                $data = json_decode($m[1], true);
                if (is_array($data) && isset($data['name'], $data['arguments']) && is_array($data['arguments'])) {
                    return [[
                        'id'       => 'call_' . bin2hex(random_bytes(6)),
                        'type'     => 'function',
                        'function' => [
                            'name'      => $data['name'],
                            'arguments' => json_encode($data['arguments']),
                        ],
                    ]];
                }
            }
        }

        return [];
    }

    /**
     * Simple completion for AIHelper.
     *
     * CRITICAL: Model must be explicitly provided. NO fallback to hardcoded defaults.
     */
    public function complete(string $prompt, ?string $model = null): string {
        if (empty($model)) {
            throw new APIException(
                'Ollama model must be explicitly specified. Configure a model in Model Settings (/?page=model-settings).',
                'MISSING_MODEL',
                400,
                ['provider' => 'ollama']
            );
        }

        $response = $this->chatCompletion([
            ['role' => 'user', 'content' => $prompt]
        ], $model);

        if (isset($response['error'])) {
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'ollama']);
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
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'ollama']);
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        preg_match('/\{[\s\S]*\}/', $content, $matches);
        if (!empty($matches[0])) {
            $parsed = json_decode($matches[0], true);
            if ($parsed) {
                return ['success' => true, 'data' => $parsed];
            }
        }

        throw new APIException('Failed to parse AI response', 'AI_PARSE_ERROR', 500, ['provider' => 'ollama']);
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
            throw new APIException($response['error'], 'AI_ERROR', 500, ['provider' => 'ollama']);
        }

        $content = $response['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'prd' => $content];
    }

    /**
     * List locally installed Ollama models via GET /api/tags.
     *
     * @return array List of models with modelId, displayName, description
     * @throws APIException if Ollama is not reachable
     */
    public function listModels(): array {
        if (!function_exists('curl_init')) {
            throw new APIException('PHP cURL extension is not enabled.', 'CURL_MISSING', 500, ['provider' => 'ollama']);
        }

        $ch = curl_init($this->baseUrl . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new APIException(
                'Cannot reach Ollama at ' . $this->baseUrl . '. Is Ollama running? Error: ' . $curlError,
                'OLLAMA_UNREACHABLE',
                503,
                ['provider' => 'ollama']
            );
        }

        if ($httpCode !== 200) {
            throw new APIException(
                'Ollama returned HTTP ' . $httpCode . '. Check that Ollama is running and the URL is correct.',
                'OLLAMA_ERROR',
                $httpCode,
                ['provider' => 'ollama']
            );
        }

        $data   = json_decode($response, true);
        $models = [];

        foreach ($data['models'] ?? [] as $m) {
            $name   = $m['name'] ?? '';
            $size   = $m['details']['parameter_size'] ?? '';
            $family = $m['details']['family'] ?? '';
            $models[] = [
                'modelId'     => $name,
                'displayName' => $name,
                'description' => trim(($family ? ucfirst($family) . ' — ' : '') . ($size ? $size . ' parameters' : 'Local model'))
            ];
        }

        return $models;
    }

    /**
     * Make API request to Ollama.
     * @throws APIException
     */
    private function makeRequest(string $endpoint, array $payload): array {
        if (!function_exists('curl_init')) {
            throw new APIException(
                'PHP cURL extension is not enabled. Please enable php_curl to use AI features.',
                'CURL_MISSING',
                500,
                ['provider' => 'ollama']
            );
        }

        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new APIException(
                'Cannot reach Ollama at ' . $this->baseUrl . '. Is Ollama running? cURL error: ' . $curlError,
                'OLLAMA_UNREACHABLE',
                503,
                ['provider' => 'ollama']
            );
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMessage = $data['error'] ?? 'Ollama API request failed';
            throw new APIException($errorMessage, 'API_ERROR', $httpCode, ['provider' => 'ollama', 'response' => $data]);
        }

        return $data;
    }

    /**
     * Get common Ollama model names as a static reference list.
     */
    public static function getModels(): array {
        return [
            'llama3.2'   => 'Llama 3.2',
            'llama3.1'   => 'Llama 3.1',
            'mistral'    => 'Mistral',
            'phi4'       => 'Phi 4',
            'gemma3'     => 'Gemma 3',
            'qwen2.5'    => 'Qwen 2.5',
            'deepseek-r1' => 'DeepSeek R1'
        ];
    }
}
