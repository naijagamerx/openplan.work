<?php

class AIVerifier {
    private Database $db;
    private array $config;
    private float $latencyThreshold = 30.0;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->config = $db->load('config');
    }

    public function getBaseUrl(): string {
        return APP_URL;
    }

    public function testGroqConnection(?string $model = null): array {
        $apiKey = $this->config['groqApiKey'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'Groq API key not configured'];
        }

        $api = new GroqAPI($apiKey);
        $startTime = microtime(true);

        try {
            $response = $api->chatCompletion([
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Say "test successful" in exactly those words.']
            ], $model);

            $latency = microtime(true) - $startTime;

            return [
                'success' => true,
                'latency' => round($latency, 3),
                'within_threshold' => $latency <= $this->latencyThreshold,
                'response' => $response['choices'][0]['message']['content'] ?? '',
                'model' => $model ?? DEFAULT_GROQ_MODEL,
                'timestamp' => date('c'),
                'status_code' => 200
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'latency' => round(microtime(true) - $startTime, 3),
                'timestamp' => date('c'),
                'status_code' => $e->getCode()
            ];
        }
    }

    public function testOpenRouterConnection(?string $model = null): array {
        $apiKey = $this->config['openrouterApiKey'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'OpenRouter API key not configured'];
        }

        $api = new OpenRouterAPI($apiKey);
        $startTime = microtime(true);

        try {
            $response = $api->chatCompletion([
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Say "test successful" in exactly those words.']
            ], $model);

            $latency = microtime(true) - $startTime;

            return [
                'success' => true,
                'latency' => round($latency, 3),
                'within_threshold' => $latency <= $this->latencyThreshold,
                'response' => $response['choices'][0]['message']['content'] ?? '',
                'model' => $model ?? DEFAULT_OPENROUTER_MODEL,
                'timestamp' => date('c'),
                'status_code' => 200
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'latency' => round(microtime(true) - $startTime, 3),
                'timestamp' => date('c'),
                'status_code' => $e->getCode()
            ];
        }
    }

    public function validateResponse(array $result): array {
        $validation = [];

        if ($result['success']) {
            $validation['http_status'] = [
                'valid' => $result['status_code'] === 200,
                'code' => $result['status_code']
            ];

            $validation['latency'] = [
                'valid' => $result['within_threshold'],
                'value' => $result['latency'],
                'threshold' => $this->latencyThreshold
            ];

            $validation['response_content'] = [
                'valid' => !empty($result['response']),
                'contains_expected' => stripos($result['response'], 'test successful') !== false
            ];

            $validation['response_format'] = [
                'valid' => is_string($result['response']),
                'type' => gettype($result['response'])
            ];

            $validation['metadata_present'] = [
                'valid' => isset($result['timestamp']) && isset($result['model']) && isset($result['latency']),
                'timestamp' => $result['timestamp'] ?? 'missing',
                'model' => $result['model'] ?? 'missing'
            ];
        } else {
            $validation['error_handling'] = [
                'error_message' => $result['error'] ?? 'Unknown error',
                'status_code' => $result['status_code'] ?? 'missing'
            ];
        }

        return $validation;
    }

    public function runComprehensiveTest(): array {
        $results = [
            'test_timestamp' => date('c'),
            'base_url' => $this->getBaseUrl(),
            'tests' => []
        ];

        if (!empty($this->config['groqApiKey'])) {
            $results['tests']['groq_default'] = $this->testGroqConnection(DEFAULT_GROQ_MODEL);
            $results['tests']['groq_validation'] = $this->validateResponse($results['tests']['groq_default']);
        }

        if (!empty($this->config['openrouterApiKey'])) {
            $results['tests']['openrouter_default'] = $this->testOpenRouterConnection(DEFAULT_OPENROUTER_MODEL);
            $results['tests']['openrouter_validation'] = $this->validateResponse($results['tests']['openrouter_default']);
        }

        $results['overall_status'] = $this->calculateOverallStatus($results['tests']);

        return $results;
    }

    private function calculateOverallStatus(array $tests): string {
        $allPassed = true;

        foreach ($tests as $test) {
            if (isset($test['success']) && !$test['success']) {
                $allPassed = false;
                break;
            }
        }

        return $allPassed ? 'all_passed' : 'partial_failure';
    }
}
