<?php
/**
 * AI Helper Class - Centralized AI operations
 */

class AIHelper {
    private Database $db;
    private array $prompts;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->loadPrompts();
    }

    /**
     * Load reusable prompts
     */
    private function loadPrompts(): void {
        $data = $this->db->load('ai_prompts');
        if (empty($data)) {
            // Default prompts
            $this->prompts = [
                'project_from_idea' => "You are a project manager. Create a detailed project plan from this idea: {idea}. Return JSON with: name, description, timeline_weeks, milestones[], suggested_tasks[].",
                'tasks_from_project' => "Break down this project into actionable tasks: Project: {name}, Description: {description}, Timeline: {timeline}. Return JSON array of tasks with: title, description, priority (urgent/high/medium/low), estimated_hours.",
                'subtasks' => "Break this task into smaller subtasks: Task: {title}, Description: {description}. Return JSON array of subtasks with: title, estimated_minutes.",
                'invoice_items' => "Generate invoice line items for this completed work: Project: {project_name}, Completed Tasks: {tasks}. Return JSON array with: description, quantity, suggested_rate_usd.",
                'daily_brief' => "You are a productivity assistant. Summarize this user's day: Today's Tasks: {tasks}, Overdue: {overdue}, Upcoming: {upcoming}. Be concise, actionable, and encouraging.",
                'client_brief' => "Create a professional client brief/profile for: Name: {name}, Company: {company}. Focus on potential needs, project styles, and communication preferences. Return a concise markdown summary.",
                'suggest_habits' => "You are a habit and productivity coach. Suggest 5-7 daily habits based on these user goals: {goals}. Return JSON array with objects containing: name (string), category (one of: health, productivity, mindfulness, learning, social), frequency (always 'daily'), reminderTime (suggested time in HH:MM format, e.g., 07:00 or 18:30). Make habits specific and actionable."
            ];
            $this->db->save('ai_prompts', $this->prompts);
        } else {
            $this->prompts = $data;
        }
    }

    /**
     * Call AI API based on provider
     */
    private function callAI(string $provider, string $model, string $prompt): string {
        $config = $this->db->load('config');
        
        try {
            if ($provider === 'groq') {
                $apiKey = $config['groqApiKey'] ?? '';
                if (empty($apiKey)) throw new Exception('Groq API Key missing');
                $ai = new GroqAPI($apiKey);
                return $ai->complete($prompt, $model);
            } else {
                $apiKey = $config['openrouterApiKey'] ?? '';
                if (empty($apiKey)) throw new Exception('OpenRouter API Key missing');
                $ai = new OpenRouterAPI($apiKey);
                return $ai->complete($prompt, $model);
            }
        } catch (Exception $e) {
            error_log("AI Call failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse JSON response safely
     */
    private function parseJSON(string $response): array {
        // Try to find JSON in the response if it's wrapped in text
        if (preg_match('/\{(?:[^{}]|(?R))*\}/x', $response, $matches)) {
            $response = $matches[0];
        } elseif (preg_match('/\[(?:[^[\]]|(?R))*\]/x', $response, $matches)) {
            $response = $matches[0];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse AI JSON response: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Generate Project from Idea
     */
    public function generateProject(string $idea, string $provider, string $model): array {
        $prompt = str_replace('{idea}', $idea, $this->prompts['project_from_idea']);
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }

    /**
     * Generate Tasks
     */
    public function generateTasks(array $projectData, string $provider, string $model): array {
        $prompt = str_replace(
            ['{name}', '{description}', '{timeline}'],
            [$projectData['name'], $projectData['description'], $projectData['timeline_weeks'] ?? 'unknown'],
            $this->prompts['tasks_from_project']
        );
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }

    /**
     * Generate Subtasks
     */
    public function generateSubtasks(string $title, string $description, string $provider, string $model): array {
        $prompt = str_replace(
            ['{title}', '{description}'],
            [$title, $description],
            $this->prompts['subtasks']
        );
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }

    /**
     * Generate Invoice Items
     */
    public function generateInvoiceItems(string $projectName, array $tasks, string $provider, string $model): array {
        $taskTitles = array_column($tasks, 'title');
        $prompt = str_replace(
            ['{project_name}', '{tasks}'],
            [$projectName, implode(', ', $taskTitles)],
            $this->prompts['invoice_items']
        );
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }

    /**
     * Generate Client Brief
     */
    public function generateBrief(string $name, string $company, string $provider, string $model): string {
        $prompt = str_replace(
            ['{name}', '{company}'],
            [$name, $company],
            $this->prompts['client_brief']
        );
        return $this->callAI($provider, $model, $prompt);
    }

    /**
     * Suggest Habits
     */
    public function suggestHabits(string $goals, string $provider, string $model): array {
        $prompt = str_replace(
            '{goals}',
            $goals,
            $this->prompts['suggest_habits']
        );
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }
}
