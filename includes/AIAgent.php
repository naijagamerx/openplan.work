<?php
/**
 * AI Agent Orchestrator
 *
 * Main agent class that coordinates function calling, conversation memory,
 * and AI provider integration to enable intelligent task automation.
 */

class AIAgent {
    private Database $db;
    private array $config;
    private string $provider;
    private ?ConversationMemory $memory;
    private ?FunctionExecutor $executor;
    private RateLimiter $rateLimiter;

    const AGENT_SYSTEM_PROMPT = <<<'PROMPT'
You are an intelligent assistant for LazyMan Tools, a comprehensive task and business management system.

## Your Capabilities
You can perform these actions by calling functions:
- **Task Management**: Create tasks with subtasks, set priorities and due dates, add time estimates
  and update/complete/delete existing tasks
- **Project Management**: Create projects, view project details and tasks
- **Client Management**: Create and manage client contacts
- **Invoice Creation**: Generate invoices with line items for clients
- **Notes**: Create notes with tags and organization
- **Knowledge Base**: Search and retrieve information
- **Finance**: Record expenses and revenue transactions
- **Habits**: Create and track daily/weekly habits (e.g., exercise, reading, meditation)
- **Inventory**: Add and manage inventory items with stock levels
- **Timer**: Start Pomodoro timers for focused work sessions

## CRITICAL BEHAVIOR RULES
1. **NEVER ASK FOR CONFIRMATION** - Execute ALL requested actions immediately without asking "would you like me to..." or "should I..."
2. **NEVER STOP TO WAIT** - When multiple actions are requested, execute them ALL in sequence without pausing
3. **MAKE SMART ASSUMPTIONS**:
   - Client email not provided? Use "client@example.com"
   - Client already exists? Use the existing one, don't ask
   - Not sure about exact amounts? Make a reasonable split
4. **EXECUTE FIRST, SUMMARIZE AFTER** - Complete all actions, then give a brief summary
5. **CONTINUE ON ERRORS** - If one action fails, continue with the others

## How To Handle Multi-Part Requests
When user asks for multiple things like "create client, project, tasks, invoice, habit, note":
1. Call list_clients to find existing client OR create new one
2. **CRITICAL**: Use the Client ID from step 1 for the project and invoice.
3. Call create_project using the Client ID.
4. **CRITICAL**: Use the Project ID from step 3 for the tasks.
5. Call create_task with the Project ID.
6. Call create_invoice with the Client ID.

PAY ATTENTION TO IDs returned in the "Result" summaries.

DO NOT STOP BETWEEN STEPS. Execute ALL functions in one response.

## Task vs Project Rules
- Use `list_tasks` for daily planning and "what should I do today?" requests.
- Use `get_project_tasks` only when user asks about one specific project.
- Standalone tasks are stored in the Inbox project; treat them as normal standalone tasks.
- If user asks to "complete", "mark done", "reopen", or "delete" a task, use `complete_task`, `update_task_status`, or `delete_task` immediately.

## Data Accuracy Rules (MANDATORY)
- NEVER invent task names, subtask names, project names, time estimates, priorities, or statuses.
- When listing tasks/subtasks, use exact values returned by tool results only.
- If a title is empty, say "(untitled task)" instead of inventing a replacement.
- If results are partial (due to filters/limits), state that clearly and suggest fetching more.
- For task-list responses, mirror task page structure: task title, project, status, priority, estimate, then subtasks.

## Response Format (CLEAN OUTPUT ONLY)
After executing all actions, respond with a simple summary like:

"Done! Here's what I created:
• Client: Space Man
• Project: Design Space Man Website
• Task: Design Homepage (3 subtasks)
• Invoice: INV-001 for R10,000
• Habit: Take a walk (daily)
• Note: Project summary saved"

DO NOT show technical details, IDs, JSON, or function names to users.
PROMPT;

    /**
     * Constructor
     *
     * @param Database $db Database instance
     * @param array $config Application configuration
     * @param string $provider AI provider ('groq' or 'openrouter')
     */
    public function __construct(Database $db, array $config = [], string $provider = 'groq') {
        $this->db = $db;
        $this->config = $config;
        $this->provider = $provider;
        $this->memory = null;
        $this->executor = null;
        $this->rateLimiter = new RateLimiter($db);
    }

    /**
     * Main chat method - processes user message and returns AI response
     *
     * @param string $message User's message
     * @param string|null $conversationId Optional existing conversation ID
     * @param bool $autoConfirm Whether to skip confirmation prompts
     * @param string|null $forceProvider Override AI provider for this request
     * @param string|null $forceModel Override AI model for this request
     * @param string|null $kbFolderId Optional Knowledge Base Folder ID to attach as context
     * @return array Response with status, message, and optional confirmation data
     */
    public function chat(string $message, ?string $conversationId = null, bool $autoConfirm = false, ?string $forceProvider = null, ?string $forceModel = null, ?string $kbFolderId = null): array {
        // Rate limit check
        $userId = Auth::userId() ?? '';
        $limitResult = $this->rateLimiter->checkApiLimit($userId, 'ai-agent');
        if (!$limitResult['allowed']) {
            throw new APIException('Rate limit exceeded. Please wait before making more requests.', 'RATE_LIMIT_EXCEEDED', 429);
        }

        // Count this request in rate limiting once accepted.
        if ($userId !== '') {
            $this->rateLimiter->incrementApiUsage($userId, 'ai-agent');
        }

        // Load or create conversation
        if ($conversationId) {
            $this->memory = new ConversationMemory($this->db, $conversationId);
            // Verify conversation exists and belongs to user
            if (!$this->memory->loadConversation($conversationId)) {
                throw new ValidationException('Conversation not found');
            }
        } else {
            $this->memory = new ConversationMemory($this->db);
            $conversationId = $this->memory->createConversation($message);
        }

        // Initialize function executor
        $this->executor = new FunctionExecutor($this->db, $this->config);
        $this->memory->clearCancelFlag($conversationId);

        // Save user message
        $this->memory->saveMessage('user', $message);

        // Build messages array with context
        $messages = $this->buildMessagesArray();

        // Inject Knowledge Base Context if provided
        if ($kbFolderId) {
            $kbData = $this->db->load('knowledge-base');
            $folderName = 'Unknown Folder';
            $files = [];
            
            // Find folder name
            if (!empty($kbData['folders'])) {
                foreach ($kbData['folders'] as $f) {
                    if ($f['id'] === $kbFolderId) {
                        $folderName = $f['name'];
                        break;
                    }
                }
            }

            // Find files in folder and calculate total size
            $totalSize = 0;
            $maxAutoContextSize = 20000; // ~5k tokens
            $fileContents = [];

            if (!empty($kbData['files'])) {
                foreach ($kbData['files'] as $f) {
                    if (isset($f['folderId']) && $f['folderId'] === $kbFolderId) {
                        $files[] = $f['name'];
                        
                        // Check if we can include content
                        $content = $f['content'] ?? '';
                        // Decode if base64 (simple check)
                        if (base64_encode(base64_decode($content, true)) === $content) {
                            $decoded = base64_decode($content);
                            if ($decoded !== false) $content = $decoded;
                        }
                        
                        $len = strlen($content);
                        if ($totalSize + $len < $maxAutoContextSize) {
                            $fileContents[] = "--- FILE: {$f['name']} ---\n{$content}\n--- END FILE ---";
                            $totalSize += $len;
                        }
                    }
                }
            }

            // Create context message
            if (!empty($fileContents)) {
                // Auto-Context Injection (RAG lite)
                $kbContext = "CONTEXT: User has attached Knowledge Base Folder: '{$folderName}'.\n";
                $kbContext .= "SYSTEM has automatically retrieved the following file contents for you:\n\n";
                $kbContext .= implode("\n\n", $fileContents);
                $kbContext .= "\n\nUse this information to answer user questions directly.";
            } else {
                // Fallback to file list if too big
                $fileList = empty($files) ? "No files." : implode(', ', array_slice($files, 0, 20));
                if (count($files) > 20) $fileList .= " (and " . (count($files) - 20) . " more)";
                $kbContext = "CONTEXT: User has attached Knowledge Base Folder: '{$folderName}'.\nFiles available: {$fileList}.\nUse 'search_knowledge_base' to find specific content.";
            }
            
            // Insert after system prompt (index 1, since index 0 is system)
            array_splice($messages, 1, 0, [['role' => 'system', 'content' => $kbContext]]);
        }

        // Get AI provider
        $provider = $forceProvider ?? $this->provider;
        $ai = $this->getAIProvider($provider);

        // Get available functions
        $functions = AIFunctions::getAllFunctions();

        // Get model - use forced model if provided, otherwise get from database
        $model = $forceModel ?? $this->getModelForProvider($provider);

        // Loop variables
        $maxLoops = 5;
        $loopCount = 0;
        $allCompletedActions = [];
        $finalAssistantMessage = '';

        do {
            $loopCount++;

            if ($this->memory->isCancelled($conversationId)) {
                $cancelMessage = 'Run stopped. I paused the remaining actions.';
                $this->memory->saveMessage('assistant', $cancelMessage);
                return [
                    'conversationId' => $conversationId,
                    'status' => 'cancelled',
                    'message' => $cancelMessage,
                    'functionCalls' => $allCompletedActions
                ];
            }

            // Make request with functions
            try {
                $response = $ai->chatWithFunctions(
                    $messages,
                    $functions,
                    $model
                );
            } catch (Exception $e) {
                $errorMsg = "I encountered an error: " . $e->getMessage();
                $this->memory->saveMessage('assistant', $errorMsg);
                throw $e;
            }

            // Parse response
            $assistantMessage = $response['choices'][0]['message']['content'] ?? '';
            $finalAssistantMessage = $assistantMessage;

            // Handle tool_calls / function_calls
            $toolCalls = $response['choices'][0]['message']['tool_calls'] ?? [];
            $functionCalls = $response['choices'][0]['message']['function_calls'] ?? [];
            $functionCalls = !empty($toolCalls) ? $toolCalls : $functionCalls;

            // Normalization
            $functionCalls = array_map(function($call) {
                if (isset($call['function'])) {
                    return [
                        'name' => $call['function']['name'] ?? '',
                        'arguments' => $call['function']['arguments'] ?? '{}',
                        'id' => $call['id'] ?? uniqid('call_')
                    ];
                } else {
                    return [
                        'name' => $call['name'] ?? '',
                        'arguments' => $call['arguments'] ?? '{}',
                        'id' => $call['id'] ?? uniqid('call_')
                    ];
                }
            }, $functionCalls);

            // IF NO TOOL CALLS -> WE ARE DONE
            if (empty($functionCalls)) {
                if (!empty($allCompletedActions)) {
                    $assistantMessage = $this->finalizeAssistantMessage($assistantMessage, $allCompletedActions, $message);
                }

                // If this was a subsequent loop, we save the final summary
                // If it's the first loop, it's just a normal text response
                if ($assistantMessage) {
                    $this->memory->saveMessage('assistant', $assistantMessage);
                }
                
                return [
                    'conversationId' => $conversationId,
                    'status' => 'complete',
                    'message' => $assistantMessage,
                    'functionCalls' => $allCompletedActions // Return all actions done in this session
                ];
            }

            // EXECUTE TOOLS
            $currentTurnActions = [];
            foreach ($functionCalls as $call) {
                if ($this->memory->isCancelled($conversationId)) {
                    break;
                }

                $name = $call['name'];
                $arguments = json_decode($call['arguments'], true);
                if (!is_array($arguments)) $arguments = [];

                try {
                    $result = $this->executor->execute($name, $arguments);
                    $currentTurnActions[] = [
                        'name' => $name,
                        'result' => $result,
                        'summary' => $this->formatResultSummary($name, $result)
                    ];
                } catch (Exception $e) {
                    $currentTurnActions[] = [
                        'name' => $name,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Accumulate actions
            $allCompletedActions = array_merge($allCompletedActions, $currentTurnActions);

            // Save this turn to memory so it's persisted
            $this->memory->saveMessage(
                'assistant', 
                $assistantMessage ?: "Executing " . count($currentTurnActions) . " actions...", 
                [], 
                $currentTurnActions
            );

            // PREPARE MESSAGES FOR NEXT LOOP
            // 1. Add the assistant's thought process (optional, but good for context)
            if ($assistantMessage) {
                $messages[] = ['role' => 'assistant', 'content' => $assistantMessage];
            }
            
            // 2. Add the results as a System/User message to prompt the next step
            // We use 'user' role to simulate "Here are the results, continue"
            $resultsSummary = "System Update: The following actions were executed successfully:\n";
            foreach ($currentTurnActions as $action) {
                $status = isset($action['error']) ? "FAILED: {$action['error']}" : "SUCCESS: {$action['summary']}";
                $resultsSummary .= "- {$action['name']}: {$status}\n";

                if (!isset($action['error']) && isset($action['result']) && is_array($action['result'])) {
                    $compact = $this->compactResultForModel((string)$action['name'], $action['result']);
                    if (!empty($compact)) {
                        $resultsSummary .= "  RAW_RESULT_JSON: " . json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
                    }
                }
            }
            $resultsSummary .= "\nUse RAW_RESULT_JSON as the source of truth for names/details. Never invent missing values.";
            $resultsSummary .= "\nIf there are remaining parts of the original request (e.g. creating tasks, invoices, habits), EXECUTE THEM NOW. Do not stop. If everything is done, provide a final summary.";

            $messages[] = ['role' => 'user', 'content' => $resultsSummary];

            // Loop continues...
            
        } while ($loopCount < $maxLoops);

        // If we hit max loops, return what we have
        if (!empty($allCompletedActions)) {
            $finalAssistantMessage = $this->finalizeAssistantMessage($finalAssistantMessage, $allCompletedActions, $message);
        }

        return [
            'conversationId' => $conversationId,
            'status' => 'complete',
            'message' => $finalAssistantMessage ?: "Completed multiple actions.",
            'functionCalls' => $allCompletedActions
        ];
    }

    /**
     * Handle user's confirmation decision
     *
     * @param string $conversationId
     * @param string $actionId
     * @param bool $approved
     * @return array Result of the action
     */
    public function handleConfirmation(string $conversationId, string $actionId, bool $approved): array {
        // Load conversation
        $this->memory = new ConversationMemory($this->db, $conversationId);
        $conversation = $this->memory->loadConversation($conversationId);

        if (!$conversation) {
            throw new ValidationException('Conversation not found');
        }

        // Find the pending action in the last assistant message
        $lastMessage = end($conversation['messages']);
        if ($lastMessage['role'] !== 'assistant' || empty($lastMessage['functionCalls'])) {
            throw new ValidationException('No pending action found');
        }

        $pendingAction = null;
        foreach ($lastMessage['functionCalls'] as $call) {
            if (isset($call['id']) && $call['id'] === $actionId) {
                $pendingAction = $call;
                break;
            }
        }

        if (!$pendingAction) {
            throw new ValidationException('Action not found');
        }

        if (!$approved) {
            // User declined - return message
            return [
                'status' => 'declined',
                'message' => 'Action cancelled by user.',
                'actionId' => $actionId
            ];
        }

        // Execute the action
        $this->executor = new FunctionExecutor($this->db, $this->config);

        try {
            $result = $this->executor->execute($pendingAction['name'], $pendingAction['arguments']);
            $summary = $this->formatResultSummary($pendingAction['name'], $result);

            return [
                'status' => 'complete',
                'actionName' => $pendingAction['name'],
                'result' => $result,
                'summary' => $summary,
                'message' => "Action completed: {$summary}"
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'actionName' => $pendingAction['name'],
                'error' => $e->getMessage(),
                'message' => "Action failed: " . $e->getMessage()
            ];
        }
    }

    /**
     * List all conversations for the current user
     *
     * @param int $limit Maximum number to return
     * @return array
     */
    public function listConversations(int $limit = 50): array {
        $memory = new ConversationMemory($this->db);
        return $memory->listConversations($limit);
    }

    /**
     * Delete a conversation
     *
     * @param string $conversationId
     * @return bool
     */
    public function deleteConversation(string $conversationId): bool {
        $memory = new ConversationMemory($this->db);
        return $memory->deleteConversation($conversationId);
    }

    /**
     * Export conversation as markdown
     *
     * @param string $conversationId
     * @return string
     */
    public function exportConversation(string $conversationId): string {
        $memory = new ConversationMemory($this->db);
        return $memory->exportAsMarkdown($conversationId);
    }

    /**
     * Ensure post-action replies include a useful summary and follow-up guidance.
     */
    private function finalizeAssistantMessage(string $assistantMessage, array $completedActions, string $userMessage): string {
        $taskPageMessage = $this->buildTaskPageAlignedResponse($completedActions, $userMessage);
        if ($taskPageMessage !== null) {
            return $taskPageMessage;
        }

        $trimmed = trim($assistantMessage);
        $needsFallback = $trimmed === '' || preg_match('/^Completed\\s+\\d+\\s+actions?\\s+successfully\\.?$/i', $trimmed) || preg_match('/^Completed\\s+multiple\\s+actions\\.?$/i', $trimmed);

        if ($needsFallback) {
            return $this->buildActionFollowUp($completedActions, $userMessage);
        }

        if (!$this->hasClearFollowUp($trimmed)) {
            return rtrim($trimmed) . "\n\n" . $this->buildSuggestedNextSteps($completedActions, $userMessage);
        }

        return $trimmed;
    }

    /**
     * For explicit task listing requests, build deterministic output from tool results.
     */
    private function buildTaskPageAlignedResponse(array $completedActions, string $userMessage): ?string {
        if (!$this->isTaskListingIntent($userMessage)) {
            return null;
        }

        $latest = $this->getLatestTaskResult($completedActions);
        if ($latest === null) {
            return null;
        }

        $actionName = $latest['name'] ?? '';
        $result = is_array($latest['result'] ?? null) ? $latest['result'] : [];
        $tasks = is_array($result['tasks'] ?? null) ? $result['tasks'] : [];

        $count = (int)($result['count'] ?? count($tasks));
        $returned = (int)($result['returned'] ?? count($tasks));
        $openCount = (int)($result['openCount'] ?? 0);
        $doneCount = (int)($result['doneCount'] ?? 0);

        if ($actionName === 'get_project_tasks' && ($openCount === 0 && $doneCount === 0)) {
            foreach ($tasks as $task) {
                $status = normalizeTaskStatus((string)($task['status'] ?? ''), '');
                if (isTaskDone($status)) {
                    $doneCount++;
                } else {
                    $openCount++;
                }
            }
        }

        $lines = [];
        $lines[] = "Task View (matching your Tasks page order)";
        $lines[] = "Total: {$count} | Showing: {$returned} | Open: {$openCount} | Done: {$doneCount}";
        $lines[] = "";

        if (empty($tasks)) {
            $lines[] = "No tasks found.";
            return implode("\n", $lines);
        }

        $projectFallback = (string)($result['project']['name'] ?? 'Unknown Project');
        foreach ($tasks as $index => $task) {
            $title = trim((string)($task['title'] ?? ''));
            $title = $title !== '' ? $title : '(untitled task)';

            $projectName = trim((string)($task['projectName'] ?? ''));
            if ($projectName === '') {
                $projectName = $projectFallback;
            }

            $status = normalizeTaskStatus((string)($task['status'] ?? 'todo'), 'todo');
            $priority = strtolower((string)($task['priority'] ?? 'medium'));
            if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
                $priority = 'medium';
            }

            $estimated = (int)($task['estimatedMinutes'] ?? 0);
            $actual = (int)($task['actualMinutes'] ?? 0);
            $timeLabel = ($estimated > 0 ? "{$estimated}m est" : "0m est") . " | " . ($actual > 0 ? "{$actual}m spent" : "0m spent");

            $lines[] = ($index + 1) . ". Task Name: {$title}";
            $lines[] = "   Project: {$projectName}";
            $lines[] = "   Status: {$status}";
            $lines[] = "   Priority: {$priority}";
            $lines[] = "   Time Tracking: {$timeLabel}";

            $subtasks = is_array($task['subtasks'] ?? null) ? $task['subtasks'] : [];
            if (!empty($subtasks)) {
                $lines[] = "   Subtasks:";
                foreach ($subtasks as $subtask) {
                    $subtaskTitle = trim((string)($subtask['title'] ?? ''));
                    $subtaskTitle = $subtaskTitle !== '' ? $subtaskTitle : '(untitled subtask)';
                    $subtaskDone = !empty($subtask['completed']) ? 'done' : 'open';
                    $subtaskEst = (int)($subtask['estimatedMinutes'] ?? 0);
                    $lines[] = "   - {$subtaskTitle} ({$subtaskDone}, {$subtaskEst}m)";
                }
            }

            $lines[] = "";
        }

        $lines[] = "I can help next by: 1) sorting by priority, 2) building a today plan, or 3) picking top 3 tasks.";
        return implode("\n", $lines);
    }

    /**
     * Check if the user asked to list/show tasks and subtasks.
     */
    private function isTaskListingIntent(string $userMessage): bool {
        $lower = strtolower($userMessage);
        $taskWords = str_contains($lower, 'task') || str_contains($lower, 'tasks');
        $listWords = str_contains($lower, 'list') || str_contains($lower, 'show') || str_contains($lower, 'what');
        $subtaskWords = str_contains($lower, 'subtask') || str_contains($lower, 'subtasks');
        return ($taskWords && $listWords) || ($taskWords && $subtaskWords);
    }

    /**
     * Get latest successful task-listing action result.
     */
    private function getLatestTaskResult(array $completedActions): ?array {
        for ($i = count($completedActions) - 1; $i >= 0; $i--) {
            $action = $completedActions[$i];
            if (isset($action['error'])) {
                continue;
            }

            $name = (string)($action['name'] ?? '');
            if ($name === 'list_tasks' || $name === 'get_project_tasks') {
                return $action;
            }
        }

        return null;
    }

    /**
     * Build a concise action recap with practical next steps.
     */
    private function buildActionFollowUp(array $completedActions, string $userMessage): string {
        $successes = [];
        $failures = [];

        foreach ($completedActions as $action) {
            if (isset($action['error'])) {
                $failures[] = $action['name'] . ': ' . $action['error'];
            } else {
                $successes[] = $action['summary'] ?? ('Completed: ' . ($action['name'] ?? 'action'));
            }
        }

        $lines = [];
        $lines[] = "Done. I finished the requested actions.";
        $lines[] = "";
        $lines[] = "What I found:";

        if (!empty($successes)) {
            foreach ($successes as $summary) {
                $lines[] = "- " . $summary;
            }
        } else {
            $lines[] = "- No successful actions were completed.";
        }

        if (!empty($failures)) {
            $lines[] = "";
            $lines[] = "Needs attention:";
            foreach ($failures as $failure) {
                $lines[] = "- " . $failure;
            }
        }

        $lines[] = "";
        $lines[] = $this->buildSuggestedNextSteps($completedActions, $userMessage);

        return implode("\n", $lines);
    }

    /**
     * Generate practical follow-up options based on executed actions.
     */
    private function buildSuggestedNextSteps(array $completedActions, string $userMessage): string {
        $actionNames = array_map(fn($a) => $a['name'] ?? '', $completedActions);
        $lowerUserMessage = strtolower($userMessage);
        $suggestions = [];

        if (in_array('list_projects', $actionNames, true) || str_contains($lowerUserMessage, 'today')) {
            $suggestions[] = 'Review today\'s tasks across your active projects and mark top 3 priorities.';
            $suggestions[] = 'Build a time-blocked plan (morning, afternoon, evening) from those priorities.';
        }

        if (in_array('list_tasks', $actionNames, true) || in_array('get_project_tasks', $actionNames, true) || in_array('create_task', $actionNames, true)) {
            $suggestions[] = 'Break selected tasks into next concrete actions you can finish in 30-60 minutes.';
        }

        if (in_array('create_invoice', $actionNames, true) || in_array('create_client', $actionNames, true)) {
            $suggestions[] = 'Prepare follow-up reminders so client and invoice steps do not stall.';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Convert the results into a simple priority plan for today.';
            $suggestions[] = 'Identify one quick win and one high-impact task to execute next.';
        }

        $suggestions = array_values(array_unique($suggestions));
        $top = array_slice($suggestions, 0, 3);

        $lines = [];
        $lines[] = "I can help next by doing one of these now:";
        foreach ($top as $index => $suggestion) {
            $lines[] = ($index + 1) . ". " . $suggestion;
        }

        return implode("\n", $lines);
    }

    /**
     * Heuristic to avoid duplicating follow-up if assistant already includes one.
     */
    private function hasClearFollowUp(string $content): bool {
        $lower = strtolower($content);
        return str_contains($lower, 'next step')
            || str_contains($lower, 'i can help')
            || str_contains($lower, 'i can do')
            || str_contains($lower, 'follow up')
            || str_contains($lower, 'recommend');
    }

    /**
     * Build messages array with system prompt and conversation history
     *
     * @return array
     */
    private function buildMessagesArray(): array {
        $messages = $this->memory->getRecentMessages(20);

        // Build system prompt with user context
        $systemPrompt = $this->buildSystemPrompt();

        // Prepend system message
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt
        ]);

        return $messages;
    }

    /**
     * Build system prompt with user's context
     *
     * @return string
     */
    private function buildSystemPrompt(): string {
        $prompt = self::AGENT_SYSTEM_PROMPT . "\n\n";
        $prompt .= "You are an advanced AI assistant for a Project Management System.\n";
        $prompt .= "Today is: " . date('l, F j, Y H:i') . "\n\n"; // Temporal Awareness
        $prompt .= "Your goal is to help users manage projects, tasks, clients, and finance.\n";
        $prompt .= "Data integrity rule: use only exact values from tool responses. Never fabricate task or subtask details.\n";

        // Inject user's context
        $projects = $this->db->load('projects') ?? [];
        $clients = $this->db->load('clients') ?? [];

        if (!empty($projects)) {
            $prompt .= "\n\n## User's Projects\n";
            foreach (array_slice($projects, 0, 10) as $p) {
                $taskCount = count($p['tasks'] ?? []);
                $prompt .= "- {$p['name']} (ID: {$p['id']}, Status: {$p['status']}, Tasks: {$taskCount})\n";
            }
            if (count($projects) > 10) {
                $prompt .= "... and " . (count($projects) - 10) . " more projects\n";
            }
        }

        if (!empty($clients)) {
            $prompt .= "\n\n## User's Clients\n";
            foreach (array_slice($clients, 0, 10) as $c) {
                $company = $c['company'] ?? 'Individual';
                $prompt .= "- {$c['name']} ({$company}) - ID: {$c['id']}\n";
            }
            if (count($clients) > 10) {
                $prompt .= "... and " . (count($clients) - 10) . " more clients\n";
            }
        }

        // Add business info
        if (!empty($this->config['businessName'])) {
            $prompt .= "\n\n## Business Info\n";
            $prompt .= "- Name: {$this->config['businessName']}\n";
            if (!empty($this->config['currency'])) {
                $prompt .= "- Currency: {$this->config['currency']}\n";
            }
        }

        return $prompt;
    }

    /**
     * Get AI provider instance
     *
     * @param string $provider
     * @return GroqAPI|OpenRouterAPI
     * @throws APIException
     */
    private function getAIProvider(string $provider) {
        $apiKey = null;

        switch ($provider) {
            case 'groq':
                $apiKey = $this->config['groqApiKey'] ?? null;
                if (empty($apiKey)) {
                    throw new APIException('Groq API key not configured', 'API_KEY_MISSING', 500);
                }
                return new GroqAPI($apiKey);

            case 'openrouter':
                $apiKey = $this->config['openrouterApiKey'] ?? null;
                if (empty($apiKey)) {
                    throw new APIException('OpenRouter API key not configured', 'API_KEY_MISSING', 500);
                }
                return new OpenRouterAPI($apiKey, getSiteName());

            default:
                throw new APIException("Unknown AI provider: {$provider}", 'INVALID_PROVIDER', 400);
        }
    }

    /**
     * Get model ID for provider
     *
     * CRITICAL: NO FALLBACK TO HARDCODED MODELS
     * Models MUST come from database (Model Settings page: /?page=model-settings)
     * If no model configured, throw error
     *
     * @param string $provider
     * @return string
     * @throws APIException
     */
    private function getModelForProvider(string $provider): string {
        // Check if user has configured a preferred model
        $models = $this->db->load('models');
        if ($models && is_array($models)) {
            // First, look for a model marked as default
            foreach ($models as $model) {
                if (isset($model['provider']) && isset($model['isDefault']) && isset($model['modelId'])) {
                    if ($model['provider'] === $provider && $model['isDefault']) {
                        return $model['modelId'];
                    }
                }
            }

            // If no default found, use the first enabled model for this provider
            foreach ($models as $model) {
                if (isset($model['provider']) && isset($model['modelId'])) {
                    if ($model['provider'] === $provider && ($model['enabled'] ?? true)) {
                        return $model['modelId'];
                    }
                }
            }
        }

        // NO FALLBACK - throw error if no model configured
        throw new APIException(
            "No {$provider} model configured. Please configure a model in Model Settings (/?page=model-settings).",
            'NO_MODEL_CONFIGURED',
            400,
            ['provider' => $provider]
        );
    }

    /**
     * Determine if an action requires confirmation
     * 
     * DISABLED: AI now executes all actions autonomously without confirmation
     * Users requested fully autonomous execution
     *
     * @param string $actionName
     * @return bool
     */
    private function shouldConfirmAction(string $actionName): bool {
        // All actions execute immediately without confirmation
        return false;
    }

    /**
     * Format confirmation question for user
     *
     * @param string $actionName
     * @param array $arguments
     * @return string
     */
    private function formatConfirmationQuestion(string $actionName, array $arguments): string {
        $title = $arguments['title'] ?? $arguments['name'] ?? '';
        $description = $arguments['description'] ?? '';

        switch ($actionName) {
            case 'create_task':
                $project = $arguments['projectId'] ?? 'Inbox';
                return "Create task \"{$title}\"" . ($description ? " - {$description}" : '');

            case 'create_project':
                return "Create project \"{$title}\"" . ($description ? " - {$description}" : '');

            case 'create_client':
                $company = $arguments['company'] ?? '';
                return "Create client \"{$title}\"" . ($company ? " ({$company})" : '');

            case 'create_invoice':
                return "Create invoice for client";

            case 'create_note':
                return "Create note \"{$title}\"";

            case 'create_transaction':
                $type = $arguments['type'] === 'expense' ? 'Expense' : 'Revenue';
                $amount = $arguments['amount'] ?? 0;
                return "Record {$type}: {$amount} - {$description}";

            default:
                return "Execute {$actionName}";
        }
    }

    /**
     * Format result summary for display
     *
     * @param string $actionName
     * @param array $result
     * @return string
     */
    private function formatResultSummary(string $actionName, array $result): string {
        switch ($actionName) {
            case 'create_task':
                $task = $result['task'] ?? [];
                $title = $task['title'] ?? 'Task';
                $id = $task['id'] ?? '';
                $hours = round(($task['estimatedMinutes'] ?? 60) / 60, 1);
                $subtaskCount = count($task['subtasks'] ?? []);
                $summary = "Created task: {$title} (ID: {$id})";
                if ($subtaskCount > 0) {
                    $summary .= " with {$subtaskCount} subtasks";
                }
                $summary .= " (~{$hours}h)";
                return $summary;

            case 'create_project':
                $project = $result['project'] ?? [];
                $id = $project['id'] ?? '';
                return "Created project: {$project['name']} (ID: {$id})";

            case 'create_client':
                $client = $result['client'] ?? [];
                $id = $client['id'] ?? '';
                $company = $client['company'] ?? '';
                return "Created client: {$client['name']} (ID: {$id})" . ($company ? " ({$company})" : '');

            case 'create_invoice':
                $invoice = $result['invoice'] ?? [];
                $id = $invoice['id'] ?? '';
                $total = $invoice['total'] ?? 0;
                return "Created invoice {$invoice['invoiceNumber']} (ID: {$id}) for {$total}";

            case 'create_note':
                $note = $result['note'] ?? [];
                $id = $note['id'] ?? '';
                return "Created note: {$note['title']} (ID: {$id})";

            case 'create_transaction':
                $transaction = $result['transaction'] ?? [];
                $id = $transaction['id'] ?? '';
                $type = $transaction['type'] === 'expense' ? 'Expense' : 'Revenue';
                return "Recorded {$type}: {$transaction['amount']} (ID: {$id})";

            case 'list_projects':
                $count = $result['count'] ?? 0;
                $projects = $result['projects'] ?? [];
                $projectPreview = $this->summarizeProjectsForPlanning($projects, 6);
                if ($projectPreview === '') {
                    return "Found {$count} projects";
                }
                return "Found {$count} projects: {$projectPreview}";

            case 'list_tasks':
                $count = $result['count'] ?? 0;
                $returned = $result['returned'] ?? 0;
                $openCount = $result['openCount'] ?? 0;
                $doneCount = $result['doneCount'] ?? 0;
                $tasks = $result['tasks'] ?? [];
                $taskPreview = $this->summarizeTasksForPlanning($tasks, 10);
                if ($taskPreview === '') {
                    return "Found {$count} tasks (returned {$returned}, open {$openCount}, done {$doneCount})";
                }
                return "Found {$count} tasks (returned {$returned}, open {$openCount}, done {$doneCount}): {$taskPreview}";

            case 'list_clients':
                $count = $result['count'] ?? 0;
                return "Found {$count} clients";

            case 'get_project_tasks':
                $count = $result['count'] ?? 0;
                $project = $result['project']['name'] ?? 'Project';
                $tasks = $result['tasks'] ?? [];
                $taskPreview = $this->summarizeTasksForPlanning($tasks, 8);
                if ($taskPreview === '') {
                    return "Found {$count} tasks in {$project}";
                }
                return "Found {$count} tasks in {$project}: {$taskPreview}";

            case 'search_knowledge_base':
                $count = $result['count'] ?? 0;
                $items = [];
                if (!empty($result['results']['folders'])) {
                    foreach (array_slice($result['results']['folders'], 0, 5) as $f) $items[] = "[Folder] " . $f['name'];
                }
                if (!empty($result['results']['files'])) {
                    foreach (array_slice($result['results']['files'], 0, 10) as $f) $items[] = "[File] " . $f['name'];
                }
                $list = implode(', ', $items);
                if ($count > count($items)) $list .= ", ... (and " . ($count - count($items)) . " more)";
                
                return "Found {$count} results: {$list}";

            default:
                if (isset($result['message'])) {
                    return (string)$result['message'];
                }
                return "Completed: {$actionName}";
        }
    }

    /**
     * Provide compact, high-signal tool result payloads to reduce hallucinations.
     */
    private function compactResultForModel(string $actionName, array $result): array {
        switch ($actionName) {
            case 'list_tasks':
                $tasks = is_array($result['tasks'] ?? null) ? $result['tasks'] : [];
                return [
                    'count' => (int)($result['count'] ?? count($tasks)),
                    'returned' => (int)($result['returned'] ?? count($tasks)),
                    'openCount' => (int)($result['openCount'] ?? 0),
                    'doneCount' => (int)($result['doneCount'] ?? 0),
                    'tasks' => array_map(function($task) {
                        $subtasks = is_array($task['subtasks'] ?? null) ? $task['subtasks'] : [];
                        return [
                            'id' => (string)($task['id'] ?? ''),
                            'title' => trim((string)($task['title'] ?? '')) ?: '(untitled task)',
                            'projectName' => (string)($task['projectName'] ?? ''),
                            'status' => (string)($task['status'] ?? ''),
                            'priority' => (string)($task['priority'] ?? ''),
                            'estimatedMinutes' => (int)($task['estimatedMinutes'] ?? 0),
                            'subtasks' => array_values(array_map(function($subtask) {
                                return [
                                    'title' => trim((string)($subtask['title'] ?? '')) ?: '(untitled subtask)',
                                    'completed' => (bool)($subtask['completed'] ?? false),
                                    'estimatedMinutes' => (int)($subtask['estimatedMinutes'] ?? 0)
                                ];
                            }, array_slice($subtasks, 0, 8)))
                        ];
                    }, array_slice($tasks, 0, 20))
                ];

            case 'get_project_tasks':
                $tasks = is_array($result['tasks'] ?? null) ? $result['tasks'] : [];
                return [
                    'project' => [
                        'id' => (string)($result['project']['id'] ?? ''),
                        'name' => (string)($result['project']['name'] ?? '')
                    ],
                    'count' => (int)($result['count'] ?? count($tasks)),
                    'tasks' => array_map(function($task) {
                        $subtasks = is_array($task['subtasks'] ?? null) ? $task['subtasks'] : [];
                        return [
                            'id' => (string)($task['id'] ?? ''),
                            'title' => trim((string)($task['title'] ?? '')) ?: '(untitled task)',
                            'status' => (string)($task['status'] ?? ''),
                            'priority' => (string)($task['priority'] ?? ''),
                            'estimatedMinutes' => (int)($task['estimatedMinutes'] ?? 0),
                            'subtasks' => array_values(array_map(function($subtask) {
                                return [
                                    'title' => trim((string)($subtask['title'] ?? '')) ?: '(untitled subtask)',
                                    'completed' => (bool)($subtask['completed'] ?? false),
                                    'estimatedMinutes' => (int)($subtask['estimatedMinutes'] ?? 0)
                                ];
                            }, array_slice($subtasks, 0, 8)))
                        ];
                    }, array_slice($tasks, 0, 20))
                ];

            case 'list_projects':
                $projects = is_array($result['projects'] ?? null) ? $result['projects'] : [];
                return [
                    'count' => (int)($result['count'] ?? count($projects)),
                    'projects' => array_map(function($project) {
                        return [
                            'id' => (string)($project['id'] ?? ''),
                            'name' => trim((string)($project['name'] ?? '')) ?: '(untitled project)',
                            'status' => (string)($project['status'] ?? ''),
                            'taskCount' => (int)($project['taskCount'] ?? 0)
                        ];
                    }, array_slice($projects, 0, 20))
                ];

            default:
                return [];
        }
    }

    /**
     * Get final AI response after executing function calls
     *
     * @param array $messages Original messages
     * @param string $initialResponse Initial AI response
     * @param array $completedActions Actions that were executed
     * @param mixed $ai AI provider instance
     * @param array $functions Available functions
     * @param string $model Model to use for final response
     * @return string Final AI response
     */
    private function getFinalResponseAfterActions(array $messages, string $initialResponse, array $completedActions, $ai, array $functions, string $model): string {
        // Add a message with function results
        $functionResultsMessage = [
            'role' => 'user',
            'content' => "Function call results:\n" . json_encode($completedActions, JSON_PRETTY_PRINT)
        ];

        $messages[] = ['role' => 'assistant', 'content' => $initialResponse];
        $messages[] = $functionResultsMessage;

        try {
            $response = $ai->chatCompletion($messages, $model);
            return $response['choices'][0]['message']['content'] ?? $initialResponse;
        } catch (Exception $e) {
            // Fallback to initial response if final call fails
            return $initialResponse;
        }
    }

    /**
     * Compact project list for planning-oriented summaries.
     */
    private function summarizeProjectsForPlanning(array $projects, int $limit = 6): string {
        if (empty($projects)) {
            return '';
        }

        $parts = [];
        foreach (array_slice($projects, 0, $limit) as $project) {
            $name = trim((string)($project['name'] ?? 'Untitled project'));
            $taskCount = (int)($project['taskCount'] ?? 0);
            $parts[] = "{$name} ({$taskCount} tasks)";
        }

        if (count($projects) > $limit) {
            $parts[] = '+' . (count($projects) - $limit) . ' more';
        }

        return implode(', ', $parts);
    }

    /**
     * Compact task list (title, priority, estimate) for daily planning responses.
     */
    private function summarizeTasksForPlanning(array $tasks, int $limit = 8): string {
        if (empty($tasks)) {
            return '';
        }

        $parts = [];
        foreach (array_slice($tasks, 0, $limit) as $task) {
            $title = trim((string)($task['title'] ?? 'Untitled task'));
            $priority = strtolower((string)($task['priority'] ?? 'medium'));
            $priority = in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium';
            $minutes = (int)($task['estimatedMinutes'] ?? 0);
            $timeLabel = $minutes > 0 ? "{$minutes}m" : 'no estimate';
            $parts[] = "{$title} [{$priority}, {$timeLabel}]";
        }

        if (count($tasks) > $limit) {
            $parts[] = '+' . (count($tasks) - $limit) . ' more';
        }

        return implode('; ', $parts);
    }
}
