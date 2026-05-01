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
            switch ($provider) {
                case 'groq':
                    $apiKey = $config['groqApiKey'] ?? '';
                    if (empty($apiKey)) throw new Exception('Groq API Key missing');
                    $ai = new GroqAPI($apiKey);
                    break;
                case 'openrouter':
                    $apiKey = $config['openrouterApiKey'] ?? '';
                    if (empty($apiKey)) throw new Exception('OpenRouter API Key missing');
                    $ai = new OpenRouterAPI($apiKey);
                    break;
                case 'gemini':
                    $apiKey = $config['geminiApiKey'] ?? '';
                    if (empty($apiKey)) throw new Exception('Gemini API Key missing');
                    $ai = new GeminiAPI($apiKey);
                    break;
                case 'ollama':
                    if (!canUseOllama()) {
                        throw new Exception('Ollama not available in online mode');
                    }
                    $url = $config['ollamaUrl'] ?? 'http://localhost:11434';
                    $ai = new OllamaAPI($url);
                    break;
                default:
                    throw new Exception("Unknown provider: {$provider}");
            }
            return $ai->complete($prompt, $model);
        } catch (Exception $e) {
            error_log("AI Call failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Call AI with provider fallback chain.
     * Tries each provider in AI_CRON_PROVIDER_ORDER, stops at first success.
     */
    public function callAIWithFallback(string $prompt, string $systemPrompt = ''): string {
        $providerOrder = defined('AI_CRON_PROVIDER_ORDER') ? AI_CRON_PROVIDER_ORDER : ['groq', 'openrouter', 'gemini', 'ollama'];
        $config = $this->db->load('config') ?? [];
        $models = $this->db->load('models') ?? [];
        $lastError = null;

        foreach ($providerOrder as $provider) {
            // Skip Ollama if not in local mode (shared hosting cannot reach local Ollama)
            if ($provider === 'ollama' && !canUseOllama()) {
                continue;
            }
            // Skip providers missing an API key (Ollama is keyless)
            if ($provider !== 'ollama' && empty($config[$provider . 'ApiKey'] ?? '')) {
                continue;
            }
            // Resolve default model for provider
            $providerModels = $models[$provider] ?? [];
            $model = '';
            foreach ($providerModels as $m) {
                if ($m['isDefault'] ?? false) { $model = $m['modelId']; break; }
            }
            if (!$model && !empty($providerModels)) {
                $model = $providerModels[0]['modelId'] ?? '';
            }
            if (!$model) continue;

            try {
                $fullPrompt = $systemPrompt ? $systemPrompt . "\n\n" . $prompt : $prompt;
                return $this->callAI($provider, $model, $fullPrompt);
            } catch (Exception $e) {
                $lastError = $e;
                error_log("AI Cron: provider '{$provider}' failed: " . $e->getMessage());
            }
        }

        throw new Exception('All AI providers failed. Last error: ' . ($lastError ? $lastError->getMessage() : 'unknown'));
    }

    /**
     * Generate a concise daily brief for a user.
     * Loads overdue tasks, today's tasks, pending habits, invoice count, low-stock count.
     */
    public function generateDailyBrief(): string {
        $today    = date('Y-m-d');
        $todayTs  = strtotime($today);

        // Tasks
        $projects = $this->db->load('projects') ?? [];
        $allTasks = [];
        foreach ($projects as $project) {
            foreach ($project['tasks'] ?? [] as $task) {
                $task['projectName'] = $project['name'] ?? '';
                $allTasks[] = $task;
            }
        }
        $overdueTasks = array_filter($allTasks, function ($t) use ($todayTs) {
            if (in_array($t['status'] ?? '', ['done', 'review'], true)) return false;
            $due = strtotime($t['dueDate'] ?? '');
            return $due && $due < $todayTs;
        });
        $todayTasks = array_filter($allTasks, function ($t) use ($today) {
            if (($t['status'] ?? '') === 'done') return false;
            return !empty($t['dueDate']) && substr($t['dueDate'], 0, 10) === $today;
        });

        // Habits
        $habits      = $this->db->load('habits') ?? [];
        $completions = $this->db->load('habit_completions') ?? [];
        $todayDone   = array_filter($completions, fn($c) => ($c['date'] ?? '') === $today && ($c['status'] ?? '') === 'complete');
        $doneIds     = array_column(array_values($todayDone), 'habitId');
        $pendingHabits = array_filter($habits, fn($h) => !in_array($h['id'] ?? '', $doneIds));

        // Invoices
        $invoices       = $this->db->load('invoices') ?? [];
        $pendingInvoices = array_filter($invoices, fn($i) => in_array($i['status'] ?? '', ['sent', 'overdue']));

        // Inventory
        $inventory = $this->db->load('inventory') ?? [];
        $lowStock  = array_filter($inventory, fn($item) => ($item['quantity'] ?? 0) < ($item['minQuantity'] ?? 5));

        // Condense to avoid large prompts
        $overdueList = implode(', ', array_map(fn($t) => $t['title'] ?? 'untitled', array_slice(array_values($overdueTasks), 0, 5)));
        $todayList   = implode(', ', array_map(fn($t) => $t['title'] ?? 'untitled', array_slice(array_values($todayTasks),   0, 5)));
        $habitList   = implode(', ', array_map(fn($h) => $h['name']  ?? 'habit',    array_slice(array_values($pendingHabits), 0, 5)));

        $systemPrompt = 'You are a focused productivity assistant. Be concise, warm, and actionable. Use bullet points. Maximum 150 words.';
        $prompt = "Today is {$today}. Generate a daily brief for this user:\n"
            . '- Overdue tasks (' . count($overdueTasks) . '): ' . ($overdueList ?: 'none') . "\n"
            . '- Due today (' . count($todayTasks) . '): ' . ($todayList ?: 'none') . "\n"
            . '- Habits not yet done (' . count($pendingHabits) . '): ' . ($habitList ?: 'none') . "\n"
            . '- Pending invoices: ' . count($pendingInvoices) . "\n"
            . '- Low-stock inventory items: ' . count($lowStock) . "\n\n"
            . 'Summarise what to focus on, flag urgent items, and give one clear actionable suggestion.';

        return $this->callAIWithFallback($prompt, $systemPrompt);
    }

    /**
     * Parse JSON response safely.
     *
     * Uses a bounded bracket-counting scanner instead of recursive regex to avoid
     * catastrophic backtracking (ReDoS) on adversarial AI output.
     */
    private function parseJSON(string $response): array {
        $jsonStr = $this->extractFirstJsonBlock($response);

        $data = json_decode($jsonStr, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse AI JSON response: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Extract the first balanced JSON object or array from a string.
     * Skips over string contents (so braces inside strings don't unbalance the count).
     * Returns the original input if no balanced block is found.
     */
    private function extractFirstJsonBlock(string $response): string {
        $len = strlen($response);
        $start = -1;
        $open = '';
        $close = '';

        for ($i = 0; $i < $len; $i++) {
            $c = $response[$i];
            if ($c === '{') { $start = $i; $open = '{'; $close = '}'; break; }
            if ($c === '[') { $start = $i; $open = '['; $close = ']'; break; }
        }

        if ($start === -1) {
            return $response;
        }

        $depth = 0;
        $inString = false;
        $escape = false;

        for ($i = $start; $i < $len; $i++) {
            $c = $response[$i];

            if ($inString) {
                if ($escape) { $escape = false; continue; }
                if ($c === '\\') { $escape = true; continue; }
                if ($c === '"')  { $inString = false; }
                continue;
            }

            if ($c === '"') { $inString = true; continue; }
            if ($c === $open)  { $depth++; continue; }
            if ($c === $close) {
                $depth--;
                if ($depth === 0) {
                    return substr($response, $start, $i - $start + 1);
                }
            }
        }

        return $response;
    }

    /**
     * Sanitize user-supplied text before interpolating it into a prompt template.
     * Defends against prompt injection by:
     *   - Capping length so adversarial input can't dwarf the system prompt
     *   - Stripping ASCII control chars (except \t, \n, \r) to remove stealth payloads
     *   - Replacing curly braces in user input so it can't smuggle new {placeholders}
     *     into the template the way str_replace expands them
     *   - Collapsing 3+ consecutive newlines (used by injection tricks like
     *     "\n\n\nSYSTEM: ignore previous instructions")
     */
    public static function sanitizeUserInputForPrompt(?string $input, int $maxLen = 2000): string {
        $s = (string)($input ?? '');
        if ($s === '') {
            return '';
        }
        // Strip ASCII control characters except common whitespace
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        // Neutralize curly braces in user input so they can't masquerade as template tokens
        $s = strtr($s, ['{' => '(', '}' => ')']);
        // Collapse runs of blank lines that prompt-injection often abuses
        $s = preg_replace("/(\r?\n){3,}/", "\n\n", $s) ?? $s;
        // Length cap (mb-aware): cap by *characters*, not bytes, so multibyte input
        // isn't truncated mid-character. Fall back to bytewise only if mbstring missing.
        if (function_exists('mb_strlen')) {
            if (mb_strlen($s, 'UTF-8') > $maxLen) {
                $s = mb_substr($s, 0, $maxLen, 'UTF-8') . '… [truncated]';
            }
        } elseif (strlen($s) > $maxLen) {
            $s = substr($s, 0, $maxLen) . '… [truncated]';
        }
        return $s;
    }

    /**
     * Generate Project from Idea
     */
    public function generateProject(string $idea, string $provider, string $model): array {
        $prompt = str_replace('{idea}', self::sanitizeUserInputForPrompt($idea, 4000), $this->prompts['project_from_idea']);
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }

    /**
     * Generate Tasks
     */
    public function generateTasks(array $projectData, string $provider, string $model): array {
        $prompt = str_replace(
            ['{name}', '{description}', '{timeline}'],
            [
                self::sanitizeUserInputForPrompt($projectData['name'] ?? '', 200),
                self::sanitizeUserInputForPrompt($projectData['description'] ?? '', 4000),
                self::sanitizeUserInputForPrompt((string)($projectData['timeline_weeks'] ?? 'unknown'), 50),
            ],
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
            [
                self::sanitizeUserInputForPrompt($title, 200),
                self::sanitizeUserInputForPrompt($description, 4000),
            ],
            $this->prompts['subtasks']
        );
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }

    /**
     * Generate Invoice Items
     */
    public function generateInvoiceItems(string $projectName, array $tasks, string $provider, string $model): array {
        $taskTitles = array_map(
            fn($t) => self::sanitizeUserInputForPrompt((string)$t, 200),
            array_column($tasks, 'title')
        );
        $prompt = str_replace(
            ['{project_name}', '{tasks}'],
            [
                self::sanitizeUserInputForPrompt($projectName, 200),
                implode(', ', $taskTitles),
            ],
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
            [
                self::sanitizeUserInputForPrompt($name, 200),
                self::sanitizeUserInputForPrompt($company, 200),
            ],
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
            self::sanitizeUserInputForPrompt($goals, 4000),
            $this->prompts['suggest_habits']
        );
        $response = $this->callAI($provider, $model, $prompt);
        return $this->parseJSON($response);
    }
}
