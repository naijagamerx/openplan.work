<?php
/**
 * Groq API Integration
 */

class GroqAPI {
    private string $apiKey;
    private string $baseUrl = 'https://api.groq.com/openai/v1';
    
    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Chat completion
     */
    public function chatCompletion(array $messages, string $model = null): array {
        $model = $model ?? DEFAULT_GROQ_MODEL;
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 4096
        ];
        
        return $this->makeRequest('/chat/completions', $payload);
    }
    
    /**
     * Simple completion for AIHelper
     */
    public function complete(string $prompt, string $model = null): string {
        $response = $this->chatCompletion([
            ['role' => 'user', 'content' => $prompt]
        ], $model);
        
        if (isset($response['error'])) {
            throw new Exception($response['error']);
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
            return $response;
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
        
        return ['success' => false, 'error' => 'Failed to parse AI response'];
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
            return $response;
        }
        
        $content = $response['choices'][0]['message']['content'] ?? '';
        return ['success' => true, 'prd' => $content];
    }
    
    /**
     * Make API request
     */
    private function makeRequest(string $endpoint, array $payload): array {
        $ch = curl_init($this->baseUrl . $endpoint);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => 'cURL error: ' . $error];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode !== 200) {
            return ['error' => $data['error']['message'] ?? 'API request failed'];
        }
        
        return $data;
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
