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

    /**
     * Maximum reasoning loops per chat() invocation. Each loop = one round-trip
     * to the AI provider plus zero-or-more tool executions. Higher = more agency
     * but more tokens spent and more chance to hit shared-host wall clock.
     */
    public const AGENT_MAX_LOOPS = 4;

    /**
     * Wall-clock budget (seconds) before we break out of the loop early to avoid
     * 504 gateway timeouts on shared hosting. PHP max_execution_time is set higher
     * (300s in api/ai-agent.php), but reverse proxies typically cut at 60s.
     */
    public const AGENT_WALLCLOCK_BUDGET_SEC = 45;

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
- **Habits**: Create, delete, list, mark complete/incomplete/missed, archive/reactivate, and run timers for daily/weekly habits (e.g., exercise, reading, meditation)
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
6. **NEVER ASK "WHICH ONES?" FOR LISTING REQUESTS** - When user says "all tasks", "all projects", "show me everything", "write them in a table", or any phrase using "all" or "every" for data we have already fetched or can fetch:
   - Call `list_tasks` for all tasks — immediately, no questions
   - Call `list_projects` for all projects — immediately, no questions
   - Call `list_clients` for all clients — immediately, no questions
   - NEVER respond with "could you specify which tasks?" or "which project?" when the user said "all"
7. **ONE TOOL CALL PER DATA TYPE** - When answering questions about tasks/projects/clients, call the appropriate list function ONCE and use the results. Do NOT call the same function multiple times in one response. Fetch all data at once.
8. **ALWAYS USE TABLES FOR LISTS** - When showing task lists, project lists, or client lists, ALWAYS format as a markdown table. Do NOT use bullet points or plain text lists. Example:
   | Task | Project | Status | Priority | Est |
   |------|---------|--------|----------|-----|
   | Fix login | Task Manager | In Progress | High | 60m |
   Use real data only — never invent values.

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
- For habits, if a delete is requested and no habitId is provided, call list_habits first to get the ID, then call delete_habit.

## Error Handling
- If you encounter rate limit errors (429), model errors, or API errors, do NOT show raw error messages to the user.
- Instead, say something like: "That model is busy. Let me try a different approach..." and retry with different parameters or model.
- Never expose internal error codes or technical details in your response.


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
     * @param string $provider AI provider ('groq', 'openrouter', 'gemini', or 'ollama')
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
     * Providers that are restricted to chat / summarization only.
     * Tool calls MUST NOT be sent to these providers regardless of stored model
     * capability. Keeps fast/cheap chat models out of the tool-execution path.
     */
    public static function isChatOnlyProvider(string $provider): bool {
        return strtolower(trim($provider)) === 'groq';
    }

    /**
     * Heuristic: does the user message read like a request for an action
     * (create/update/delete/send/log/etc.) rather than a question or summary?
     * Used to (a) prefer function-capable models in auto mode and
     * (b) inject a deflection system prompt when the resolved provider is chat-only.
     */
    private function looksLikeActionRequest(string $message): bool {
        $text = strtolower(trim($message));
        if ($text === '') return false;
        $needles = [
            'create', 'add ', 'make ', 'new ', 'build ',
            'update', 'edit ', 'change ', 'modify', 'rename',
            'delete', 'remove', 'archive', 'cancel',
            'complete', 'mark done', 'mark as done', 'finish ', 'close ',
            'send ', 'email ', 'invoice', 'bill ',
            'schedule', 'book ', 'plan ',
            'log ', 'record ', 'track ',
            'start timer', 'stop timer', 'pomodoro',
            'export', 'import',
            'set goal', 'log water'
        ];
        foreach ($needles as $n) {
            if (strpos($text, $n) !== false) return true;
        }
        return false;
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
    public function chat(
        string $message,
        ?string $conversationId = null,
        bool $autoConfirm = false,
        ?string $forceProvider = null,
        ?string $forceModel = null,
        ?string $kbFolderId = null,
        string $contextLevel = 'medium',
        string $outputLength = 'normal',
        bool $smartMode = false
    ): array {
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

        // Resolve generation profile for context and output budget.
        $profile = $this->getGenerationProfile($contextLevel, $outputLength);
        $maxRecentMessages = (int)$profile['maxRecentMessages'];
        $maxAutoContextSize = (int)$profile['maxKbChars'];
        $currentMaxTokens = (int)$profile['maxTokens'];

        // Build messages array with context
        $messages = $this->buildMessagesArray($maxRecentMessages);

        // Bias one-off reminder intents toward tasks (not habits).
        if ($this->isOneTimeReminderIntent($message)) {
            array_splice($messages, 1, 0, [[
                'role' => 'system',
                'content' => 'The user request is a ONE-TIME reminder. Use create_task with dueDate and do not create or delete habits unless explicitly requested.'
            ]]);
        }

        // Hard-block KB injection for chat-only providers regardless of what the
        // frontend sent. Groq has tight TPM limits and the KB context can blow
        // it on a single message; also chat-only providers cannot tool-call into
        // the KB so the inline content is the only signal anyway, and we are
        // intentionally keeping that channel closed for them.
        $resolvedProviderForKb = $forceProvider ?? $this->provider;
        if (self::isChatOnlyProvider($resolvedProviderForKb)) {
            $kbFolderId = null;
        }

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

        // Chat-only provider deflection: if Groq is the resolved provider AND the
        // user's message looks like an action request, prepend a system instruction
        // telling the model to politely decline and suggest switching to OpenRouter
        // or Gemini. The deflection text is generated by the LLM (natural phrasing)
        // but the *rule* is enforced by code, not by hoping the LLM behaves.
        if (self::isChatOnlyProvider($provider) && $this->looksLikeActionRequest($message)) {
            array_splice($messages, 1, 0, [[
                'role' => 'system',
                'content' => 'You are running in chat-only mode. The user appears to be requesting an action (create/update/delete/send/log/etc.). You CANNOT execute actions in this mode. In one or two short sentences, tell the user to switch the provider selector to OpenRouter or Google Gemini to perform actions, then briefly answer any informational part of their question. Do not apologize repeatedly. Do not pretend to perform the action.'
            ]]);
        }

        // Get available functions
        $functions = AIFunctions::getAllFunctions();

        // Determine model(s) to use
        // Auto-failover mode: build list across ALL providers (cross-provider failover)
        // This allows switching from Groq → Gemini → OpenRouter when needed.
        // When the user message looks action-y we prefer function-capable models so
        // chat-only providers (Groq) don't silently drop the request.
        if ($forceModel === 'auto') {
            $needsTools = $this->looksLikeActionRequest($message);
            $capability = $needsTools ? 'function_calling' : null;
            $modelCandidates = $this->getAllCapableModels($capability, $provider);
            // Fallback: if user wanted an action but no function-capable model exists,
            // fall back to any enabled model. The chat-only deflection prompt above
            // will make Groq tell the user to switch providers.
            if ($needsTools && empty($modelCandidates)) {
                $modelCandidates = $this->getAllCapableModels(null, $provider);
            }
            if (empty($modelCandidates)) {
                throw new APIException(
                    "No enabled AI models found. Please configure at least one model in Model Settings.",
                    'NO_MODELS',
                    400
                );
            }
        } else {
            // User explicitly selected a model - use it with its provider.
            // Capability defaults to 'both' but is overridden to 'chat' for chat-only
            // providers so downstream tool dispatch knows to skip the functions array.
            $targetModel = $forceModel ?? $this->getModelForProvider($provider);
            $candidateCapability = self::isChatOnlyProvider($provider) ? 'chat' : 'both';
            $modelCandidates = [['provider' => $provider, 'modelId' => $targetModel, 'capability' => $candidateCapability]];
        }

        // Pre-flight token-budget check (chat-only providers only).
        // If the conversation + KB context is approaching the model's context window,
        // emit a switch_provider_recommended event so the UI can suggest moving to
        // OpenRouter or Gemini. This is computed in PHP — never asks the LLM.
        $primaryCandidate = $modelCandidates[0] ?? null;
        if ($primaryCandidate && self::isChatOnlyProvider($primaryCandidate['provider'])) {
            $window = $this->getModelContextWindow($primaryCandidate['provider'], $primaryCandidate['modelId']);
            $estimated = $this->estimateTokens($messages, $message);
            if ($window > 0 && $estimated > (int)floor(0.8 * $window)) {
                $this->emitEvent('switch_provider_recommended', [
                    'reason' => 'context_near_limit',
                    'currentProvider' => $primaryCandidate['provider'],
                    'estimatedTokens' => $estimated,
                    'modelContextWindow' => $window,
                    'suggestedProviders' => $this->getConfiguredProvidersForSwitch(),
                ]);
            }
        }

        // Loop variables
        $maxLoops = self::AGENT_MAX_LOOPS;
        $loopCount = 0;
        $allCompletedActions = [];
        $finalAssistantMessage = '';
        $toolEvents = [];
        $loopStartTime = microtime(true); // Wall-clock guard against shared-hosting timeouts
        // Track *why* the loop ends. Inside the loop we can return early with
        // `status=complete` (no more tool calls). If we fall out via break/while-exit
        // we use this to mark the response as truncated so the UI can surface it
        // instead of pretending the run finished.
        $loopExitReason = 'max_loops';

        do {
            $loopCount++;

            // Shared hosting wall-clock guard: if we've been running > 45s, wrap up now
            // rather than risking a 504 gateway timeout mid-response.
            if ((microtime(true) - $loopStartTime) > self::AGENT_WALLCLOCK_BUDGET_SEC) {
                $this->emitEvent('timeout_warning', ['elapsed' => round(microtime(true) - $loopStartTime)]);
                $loopExitReason = 'timeout';
                break;
            }
            $this->emitEvent('thinking_started', [
                'loop' => $loopCount,
                'providers' => array_unique(array_column($modelCandidates, 'provider'))
            ]);

            if ($this->memory->isCancelled($conversationId)) {
                $cancelMessage = 'Run stopped. I paused the remaining actions.';
                $this->memory->saveMessage('assistant', $cancelMessage);
                $this->emitEvent('run_cancelled', ['message' => $cancelMessage]);
                return [
                    'conversationId' => $conversationId,
                    'status' => 'cancelled',
                    'message' => $cancelMessage,
                    'functionCalls' => $allCompletedActions,
                    'toolEvents' => $toolEvents
                ];
            }

            // Make request with functions - cross-provider failover
            $response = null;
            $lastError = null;
            $success = false;
            $lastProvider = null;
            $lastModelId = null;

            foreach ($modelCandidates as $candidate) {
                $currentProvider = $candidate['provider'];
                $currentModel = $candidate['modelId'];
                
                // Get AI instance for this provider (may be different from initial $ai)
                $currentAi = $this->getAIProvider($currentProvider);
                
                $maxProviderAttempts = 2;
                // Chat-only providers (Groq) MUST receive an empty tools array — no
                // function calls, no tool dispatch. Combined with the deflection
                // system prompt above, the model will simply chat or politely
                // suggest switching to a tool-capable provider.
                $candidateFunctions = self::isChatOnlyProvider($currentProvider) ? [] : $functions;
                for ($providerAttempt = 1; $providerAttempt <= $maxProviderAttempts; $providerAttempt++) {
                    try {
                        $response = $currentAi->chatWithFunctions(
                            $messages,
                            $candidateFunctions,
                            $currentModel,
                            ['maxTokens' => $currentMaxTokens]
                        );
                        $success = true;
                        $lastProvider = $currentProvider;
                        $lastModelId = $currentModel;
                        break 2; // Exit provider-retry loops, continue do-while to process response
                    } catch (Exception $e) {
                        $lastError = $e;
                        $lastProvider = $currentProvider;
                        $lastModelId = $currentModel;
                        
                        if ($this->isTokenLimitError($e) && $providerAttempt < $maxProviderAttempts) {
                            $messages = $this->trimMessagesForRetry($messages);
                            $currentMaxTokens = max(256, (int)floor($currentMaxTokens * 0.6));
                            $this->emitEvent('token_retry', [
                                'provider' => $currentProvider,
                                'model' => $currentModel,
                                'attempt' => $providerAttempt + 1,
                                'maxTokens' => $currentMaxTokens
                            ]);
                            // Reactive: when a chat-only provider hits a token limit,
                            // surface the switch suggestion to the UI immediately so
                            // the user can move to a longer-context tool provider.
                            if (self::isChatOnlyProvider($currentProvider)) {
                                $this->emitEvent('switch_provider_recommended', [
                                    'reason' => 'context_overflow',
                                    'currentProvider' => $currentProvider,
                                    'suggestedProviders' => $this->getConfiguredProvidersForSwitch(),
                                ]);
                            }
                            continue;
                        }
                        
                        // For other errors, log and try next model
                        error_log("AI Agent: {$currentProvider}/{$currentModel} failed: " . $e->getMessage());
                        break; // Try next model
                    }
                }
            }

            if (!$success) {
                $e = $lastError;
                $errorMsg = "I encountered an error: " . $e->getMessage();
                
                // Add troubleshooting hint for provider errors
                if ($e instanceof APIException && strpos($e->getMessage(), 'Provider returned error') !== false) {
                    $errorMsg .= "\n\n(Tip: This usually means the AI model is temporarily unavailable. Try selecting 'Auto' mode or a different model.)";
                }
                
                $this->memory->saveMessage('assistant', $errorMsg);
                $this->emitEvent('run_error', ['error' => $this->shortenToolError($e->getMessage(), 260)]);
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
                    $assistantMessage = $this->finalizeAssistantMessage($assistantMessage, $allCompletedActions, $message, $toolEvents);
                }

                // Smart Auto pipeline: when user picked Smart and the answering
                // provider was NOT Groq (e.g. OpenRouter did the heavy tool work),
                // ask Groq to rewrite the final message as a clean user-facing
                // summary. Groq is fast + cheap and the user explicitly wants
                // it for the final read. Falls back silently if Groq fails
                // (TPM-locked, no key, etc.) so the original reply still ships.
                if ($smartMode && $assistantMessage && $lastProvider && $lastProvider !== 'groq') {
                    $polished = $this->polishWithGroq($assistantMessage, $allCompletedActions);
                    if ($polished !== '') {
                        $assistantMessage = $polished;
                    }
                }

                // If this was a subsequent loop, we save the final summary
                // If it's the first loop, it's just a normal text response
                if ($assistantMessage) {
                    $this->memory->saveMessage('assistant', $assistantMessage);
                }
                $this->emitEvent('summary_ready', ['message' => $assistantMessage]);

                return [
                    'conversationId' => $conversationId,
                    'status' => 'complete',
                    'message' => $assistantMessage,
                    'functionCalls' => $allCompletedActions, // Return all actions done in this session
                    'toolEvents' => $toolEvents
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

                // Tool status is surfaced via timeline events only. We DO NOT
                // persist "Executing tool: X..." as an assistant message because
                // that pollutes the chat transcript when the conversation is
                // reloaded later (the user sees rows of "Executing tool:" lines
                // mixed in with real replies). The frontend already renders
                // tool_started/tool_succeeded events in the typing indicator.
                $this->emitEvent('tool_started', [
                    'loop' => $loopCount,
                    'step' => count($allCompletedActions) + count($currentTurnActions) + 1,
                    'name' => $name
                ]);

                $eventIndex = count($toolEvents);
                $toolEvents[] = [
                    'name' => $name,
                    'status' => 'started',
                    'startedAt' => date('c'),
                    'attempts' => 0
                ];

                $startTime = microtime(true);
                $attempts = 0;
                $maxAttempts = $this->isRetryableAction($name) ? 2 : 1;
                $result = null;
                $error = null;

                while ($attempts < $maxAttempts) {
                    $attempts++;
                    try {
                        $result = $this->executor->execute($name, $arguments);
                        $error = null;
                        break;
                    } catch (Exception $e) {
                        $error = $e;
                        if ($attempts < $maxAttempts) {
                            usleep(200000);
                        }
                    }
                }

                $durationMs = (int)round((microtime(true) - $startTime) * 1000);
                $toolEvents[$eventIndex]['attempts'] = $attempts;
                $toolEvents[$eventIndex]['endedAt'] = date('c');
                $toolEvents[$eventIndex]['durationMs'] = $durationMs;

                if ($error === null) {
                    $toolEvents[$eventIndex]['status'] = 'success';
                    $summary = $this->formatResultSummary($name, $result);
                    $currentTurnActions[] = [
                        'name' => $name,
                        'result' => $result,
                        'summary' => $summary
                    ];
                    $this->emitEvent('tool_succeeded', [
                        'name' => $name,
                        'summary' => $summary,
                        'durationMs' => $durationMs,
                        'attempts' => $attempts
                    ]);
                } else {
                    $toolEvents[$eventIndex]['status'] = 'failed';
                    $toolEvents[$eventIndex]['error'] = $this->shortenToolError($error->getMessage());
                    $shortError = $this->shortenToolError($error->getMessage());
                    $currentTurnActions[] = [
                        'name' => $name,
                        'error' => $error->getMessage()
                    ];
                    $this->emitEvent('tool_failed', [
                        'name' => $name,
                        'error' => $shortError,
                        'durationMs' => $durationMs,
                        'attempts' => $attempts
                    ]);
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

        // We exited the do-while without an early return, which means either we hit
        // the loop cap or the wall-clock guard. Either way, work is partial — surface
        // that to the UI so the user knows to either continue or accept incomplete results.
        $truncationNote = '';
        if ($loopExitReason === 'timeout') {
            $truncationNote = "\n\n_(I ran out of time partway through. The actions above completed; ask me to continue if more is needed.)_";
        } elseif ($loopExitReason === 'max_loops') {
            $truncationNote = "\n\n_(I reached the maximum number of reasoning steps. The actions above completed; ask me to continue if more is needed.)_";
        }

        if (!empty($allCompletedActions)) {
            $finalAssistantMessage = $this->finalizeAssistantMessage($finalAssistantMessage, $allCompletedActions, $message, $toolEvents);
        }
        if ($truncationNote !== '') {
            $finalAssistantMessage = ($finalAssistantMessage ?: 'Completed multiple actions.') . $truncationNote;
        }

        // Smart Auto polish — same rule as the early-return path: if Smart was
        // picked and the answer came from a non-Groq provider, let Groq write
        // the user-facing summary.
        if ($smartMode && $finalAssistantMessage && $lastProvider && $lastProvider !== 'groq') {
            $polished = $this->polishWithGroq($finalAssistantMessage, $allCompletedActions);
            if ($polished !== '') {
                $finalAssistantMessage = $polished;
            }
        }

        // Save the final message to conversation memory so the frontend can display it
        if ($finalAssistantMessage) {
            $this->memory->saveMessage('assistant', $finalAssistantMessage);
        }

        $this->emitEvent('summary_ready', [
            'message' => $finalAssistantMessage ?: "Completed multiple actions.",
            'truncated' => true,
            'reason' => $loopExitReason,
        ]);

        return [
            'conversationId' => $conversationId,
            'status' => 'truncated',
            'truncated' => true,
            'reason' => $loopExitReason,
            'loops' => $loopCount,
            'message' => $finalAssistantMessage ?: "Completed multiple actions.",
            'functionCalls' => $allCompletedActions,
            'toolEvents' => $toolEvents
        ];
    }

    /**
     * Create an empty conversation and return its id for immediate UI polling.
     */
    public function startConversation(string $titleSeed = 'New Conversation'): string {
        $memory = new ConversationMemory($this->db);
        $conversationId = $memory->createConversation($titleSeed);
        $memory->saveEvent('run_ready', ['message' => 'Conversation started']);
        return $conversationId;
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
    private function finalizeAssistantMessage(string $assistantMessage, array $completedActions, string $userMessage, array $toolEvents = []): string {
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
     * Generate practical follow-up suggestions based on what was just executed.
     * These are phrased as things the user can say next — short and specific.
     */
    private function buildSuggestedNextSteps(array $completedActions, string $userMessage): string {
        $actionNames = array_map(fn($a) => $a['name'] ?? '', $completedActions);
        $lowerUserMessage = strtolower($userMessage);
        $suggestions = [];

        if (in_array('list_projects', $actionNames, true)) {
            $suggestions[] = '"Show all tasks in a table"';
            $suggestions[] = '"Which project has the most tasks?"';
        }

        if (in_array('list_tasks', $actionNames, true) || in_array('get_project_tasks', $actionNames, true)) {
            $suggestions[] = '"Mark all done tasks as complete"';
            $suggestions[] = '"Show only high priority tasks"';
            $suggestions[] = '"Add a new task to [project name]"';
        }

        if (in_array('create_task', $actionNames, true)) {
            $suggestions[] = '"Add subtasks to that task"';
            $suggestions[] = '"Set it as high priority"';
        }

        if (in_array('create_invoice', $actionNames, true) || in_array('create_client', $actionNames, true)) {
            $suggestions[] = '"Show all unpaid invoices"';
            $suggestions[] = '"Create another invoice for this client"';
        }

        if (in_array('list_habits', $actionNames, true) || in_array('create_habit', $actionNames, true)) {
            $suggestions[] = '"Mark my morning habit as done"';
            $suggestions[] = '"Show my habit streak"';
        }

        if (empty($suggestions)) {
            $suggestions[] = '"Show my tasks for today"';
            $suggestions[] = '"What projects am I working on?"';
        }

        $suggestions = array_values(array_unique($suggestions));
        $top = array_slice($suggestions, 0, 2);

        return "You can ask: " . implode(' or ', $top);
    }

    /**
     * Heuristic to avoid duplicating follow-up if assistant already includes one.
     *
     * Also suppresses generic follow-up when the response is already rich data
     * (a markdown table, a bulleted list of results, etc.) — the user can simply
     * ask the next question naturally.
     */
    private function hasClearFollowUp(string $content): bool {
        $lower = strtolower($content);

        // Explicit follow-up phrases
        if (str_contains($lower, 'next step')
            || str_contains($lower, 'i can help')
            || str_contains($lower, 'i can do')
            || str_contains($lower, 'follow up')
            || str_contains($lower, 'recommend')) {
            return true;
        }

        // Response already contains a markdown table — don't pile on suggestions
        if (substr_count($content, '|') >= 4) {
            return true;
        }

        // Response contains a numbered or bulleted list with 4+ items — rich enough
        $bulletCount = preg_match_all('/^[\*\-\d]\./m', $content);
        $numberedCount = preg_match_all('/^\d+\./m', $content);
        if (($bulletCount + $numberedCount) >= 4) {
            return true;
        }

        return false;
    }

    /**
     * Build messages array with system prompt and conversation history
     *
     * @return array
     */
    private function buildMessagesArray(int $limit = 20): array {
        $messages = $this->memory->getRecentMessages($limit);

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
     * Emit a structured event for live UI timelines.
     */
    private function emitEvent(string $type, array $data = []): void {
        if ($this->memory === null) {
            return;
        }
        $this->memory->saveEvent($type, $data);
    }

    /**
     * Basic output/context profile used for token and history budgeting.
     */
    private function getGenerationProfile(string $contextLevel, string $outputLength): array {
        $contextLevel = strtolower(trim($contextLevel));
        $outputLength = strtolower(trim($outputLength));

        $maxRecentMessages = 20;
        $maxKbChars = 20000;
        $maxTokens = 2048;

        if ($contextLevel === 'low') {
            $maxRecentMessages = 10;
            $maxKbChars = 8000;
        } elseif ($contextLevel === 'high') {
            $maxRecentMessages = 35;
            $maxKbChars = 40000;
        }

        if ($outputLength === 'short') {
            $maxTokens = 768;
        } elseif ($outputLength === 'long') {
            $maxTokens = 4096;
        }

        return [
            'maxRecentMessages' => $maxRecentMessages,
            'maxKbChars' => $maxKbChars,
            'maxTokens' => $maxTokens
        ];
    }

    /**
     * Detect token/context overflow errors from providers.
     */
    private function isTokenLimitError(Exception $e): bool {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'token')
            || str_contains($msg, 'context length')
            || str_contains($msg, 'maximum context')
            || str_contains($msg, 'too many tokens');
    }

    /**
     * Rough token estimate using the chars/4 heuristic. Cheap, no external call.
     * Used pre-flight to decide whether to warn the user about context limits.
     */
    private function estimateTokens(array $messages, string $extraText = ''): int {
        $chars = strlen($extraText);
        foreach ($messages as $msg) {
            $chars += strlen((string)($msg['content'] ?? ''));
        }
        return (int)ceil($chars / 4);
    }

    /**
     * Look up the model's context window. Prefers a `contextWindow` value stored
     * on the model row in the encrypted models collection; otherwise falls back
     * to a conservative provider default chosen so the 80% pre-flight threshold
     * fires *before* the user hits a hard error.
     */
    private function getModelContextWindow(string $provider, string $modelId): int {
        $models = $this->db->load('models') ?? [];
        foreach (($models[$provider] ?? []) as $m) {
            if (($m['modelId'] ?? '') === $modelId) {
                $window = (int)($m['contextWindow'] ?? 0);
                if ($window > 0) return $window;
                break;
            }
        }
        switch (strtolower($provider)) {
            case 'groq':       return 8192;   // Conservative; many Groq models do 32k+
            case 'gemini':     return 32768;
            case 'openrouter': return 16384;
            case 'ollama':     return 4096;
            default:           return 4096;
        }
    }

    /**
     * Smart Auto pipeline: pass the final assistant message through Groq so
     * Groq writes the user-facing summary. The agent loop (typically OpenRouter
     * or Gemini) does the heavy lifting — tool calls, chain-of-thought — and
     * Groq just polishes the result for fast, friendly delivery.
     *
     * Returns the polished string on success or '' on any failure (caller
     * keeps the original message). Never throws.
     */
    private function polishWithGroq(string $original, array $completedActions): string {
        $groqKey = $this->config['groqApiKey'] ?? '';
        if (empty($groqKey) || empty($original)) return '';

        // Pick the best Groq chat model available to the user. Prefer
        // llama-3.3-70b-versatile (12K TPM, strong writer), fall back to
        // gemma2-9b-it (15K TPM, fast), then gpt-oss-120b.
        $models = $this->db->load('models') ?? [];
        $groqModels = $models['groq'] ?? [];
        $preferred = ['llama-3.3-70b-versatile', 'gemma2-9b-it', 'openai/gpt-oss-120b'];
        $modelId = '';
        foreach ($preferred as $candidate) {
            foreach ($groqModels as $m) {
                if (($m['enabled'] ?? true) && ($m['modelId'] ?? '') === $candidate) {
                    $modelId = $candidate;
                    break 2;
                }
            }
        }
        if ($modelId === '') {
            // No preferred model — use whichever Groq model is enabled first.
            foreach ($groqModels as $m) {
                if ($m['enabled'] ?? true) { $modelId = $m['modelId']; break; }
            }
        }
        if ($modelId === '') return '';

        $actionsList = '';
        foreach ($completedActions as $action) {
            $name = (string)($action['name'] ?? '');
            $summary = (string)($action['summary'] ?? '');
            if ($name !== '') $actionsList .= "- {$name}" . ($summary !== '' ? ": {$summary}" : '') . "\n";
        }

        $systemPrompt = "You are a friendly summary writer. The user asked something and another AI did the work — your job is to rewrite the result as a short, clear, conversational message for the user. Keep all factual content (numbers, names, IDs, statuses, table contents). Do NOT invent details. If the original used a markdown table, KEEP the table verbatim. Do not apologize, do not preamble, do not say 'I rewrote this' — just deliver the polished reply.";
        $userPrompt = "Original assistant reply to polish:\n\n" . $original;
        if ($actionsList !== '') {
            $userPrompt .= "\n\nActions completed:\n" . $actionsList;
        }

        try {
            $groq = new GroqAPI($groqKey);
            $resp = $groq->chatCompletion([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt]
            ], $modelId, ['maxTokens' => 1024, 'temperature' => 0.6]);
            if (isset($resp['error'])) return '';
            $text = $resp['choices'][0]['message']['content'] ?? '';
            return trim($text);
        } catch (Throwable $e) {
            error_log('Smart Auto polish via Groq failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Which non-chat-only providers are currently configured? Used by the
     * switch_provider_recommended event so the UI knows which buttons to render.
     */
    private function getConfiguredProvidersForSwitch(): array {
        $out = [];
        if (!empty($this->config['openrouterApiKey'])) $out[] = 'openrouter';
        if (!empty($this->config['geminiApiKey']))     $out[] = 'gemini';
        return $out;
    }

    /**
     * Aggressively trim oldest non-system context for a same-provider retry.
     */
    private function trimMessagesForRetry(array $messages): array {
        if (count($messages) <= 8) {
            return $messages;
        }

        $system = [];
        $rest = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system[] = $msg;
            } else {
                $rest[] = $msg;
            }
        }

        $rest = array_slice($rest, -6);
        return array_merge($system, $rest);
    }

    /**
     * Heuristic: one-off reminder language should route to create_task.
     */
    private function isOneTimeReminderIntent(string $message): bool {
        $lower = strtolower($message);
        $hasReminderTiming = preg_match('/\b(in|after)\s+\d+\s*(minute|minutes|hour|hours)\b/', $lower) === 1
            || preg_match('/\b(today|tomorrow|at\s+\d{1,2}(:\d{2})?\s*(am|pm)?)\b/', $lower) === 1;
        $hasRecurring = preg_match('/\b(daily|weekly|every day|every week|recurring|habit)\b/', $lower) === 1;
        return $hasReminderTiming && !$hasRecurring;
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
        
        $prompt .= "\n## Habits vs Tasks Rules\n";
        $prompt .= "- Use Habits for RECURRING actions only (daily, weekly).\n";
        $prompt .= "- For one-time reminders (e.g., \"remind me to call John in 2 hours\"), use `create_task` with a due date/time. DO NOT create a habit for a one-off event.\n";

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
     * @return GroqAPI|OpenRouterAPI|OllamaAPI|GeminiAPI
     * @throws APIException
     */
    private function getAIProvider(string $provider) {
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

            case 'gemini':
                $apiKey = $this->config['geminiApiKey'] ?? null;
                if (empty($apiKey)) {
                    throw new APIException('Google Gemini API key not configured. Add it in Settings → AI API Keys.', 'API_KEY_MISSING', 500);
                }
                return new GeminiAPI($apiKey);

            case 'ollama':
                if (!canUseOllama()) {
                    throw new APIException(
                        'Ollama is not available in online mode (shared hosting cannot reach local Ollama). Use Groq, OpenRouter, or Gemini instead.',
                        'OLLAMA_UNAVAILABLE_ONLINE',
                        503
                    );
                }
                $url = $this->config['ollamaUrl'] ?? 'http://localhost:11434';
                return new OllamaAPI($url);

            default:
                throw new APIException("Unknown AI provider: {$provider}", 'INVALID_PROVIDER', 400);
        }
    }

    /**
     * Get all enabled model IDs for a provider to support auto-failover.
     */
    private function getEnabledModels(string $provider): array {
        $models = $this->db->load('models') ?? [];
        $enabled = [];
        
        // Models are stored as: ['groq' => [...], 'openrouter' => [...]]
        $providerModels = $models[$provider] ?? [];
        
        foreach ($providerModels as $m) {
            if ($m['enabled'] ?? true) {
                $enabled[] = $m['modelId'];
            }
        }
        return $enabled;
    }

    /**
     * Get ALL enabled models across ALL providers with their capabilities.
     * Used for cross-provider failover when a model fails.
     * 
     * @param string|null $requiredCapability Filter by capability: 'chat', 'function_calling', or 'both'
     * @param string|null $preferredProvider Prefer this provider first
     * @return array Array of ['provider' => x, 'modelId' => y, 'capability' => z]
     */
    private function getAllCapableModels(?string $requiredCapability = null, ?string $preferredProvider = null): array {
        $models = $this->db->load('models') ?? [];
        $allModels = [];
        $providers = ['groq', 'openrouter', 'gemini', 'ollama'];
        
        // If Ollama is not available (online mode), skip it
        if (!canUseOllama()) {
            $providers = array_filter($providers, fn($p) => $p !== 'ollama');
        }
        
        // First add preferred provider models (if specified)
        if ($preferredProvider && in_array($preferredProvider, $providers)) {
            foreach (($models[$preferredProvider] ?? []) as $m) {
                if ($m['enabled'] ?? true) {
                    $capability = $m['capability'] ?? 'both';
                    // Force chat-only providers (Groq) to 'chat' regardless of DB
                    // value so they never get matched when 'function_calling' is required.
                    if (self::isChatOnlyProvider($preferredProvider)) {
                        $capability = 'chat';
                    }
                    if ($requiredCapability === null || $capability === $requiredCapability || $capability === 'both') {
                        $allModels[] = [
                            'provider' => $preferredProvider,
                            'modelId' => $m['modelId'],
                            'capability' => $capability
                        ];
                    }
                }
            }
        }
        
        // Then add all other providers
        foreach ($providers as $provider) {
            if ($provider === $preferredProvider) continue;
            foreach (($models[$provider] ?? []) as $m) {
                if ($m['enabled'] ?? true) {
                    $capability = $m['capability'] ?? 'both';
                    // Same chat-only override applied to non-preferred providers.
                    if (self::isChatOnlyProvider($provider)) {
                        $capability = 'chat';
                    }
                    if ($requiredCapability === null || $capability === $requiredCapability || $capability === 'both') {
                        $allModels[] = [
                            'provider' => $provider,
                            'modelId' => $m['modelId'],
                            'capability' => $capability
                        ];
                    }
                }
            }
        }

        return $allModels;
    }

    /**
     * Get models suitable for function calling across all providers.
     * Prefers the user's selected provider but falls back to any capable model.
     */
    private function getFunctionCallingModels(string $preferredProvider = 'gemini'): array {
        return $this->getAllCapableModels('function_calling', $preferredProvider);
    }

    /**
     * Get models suitable for chat only.
     */
    private function getChatModels(string $preferredProvider = 'groq'): array {
        return $this->getAllCapableModels('chat', $preferredProvider);
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
            // Models are stored as: ['groq' => [...], 'openrouter' => [...]]
            $providerModels = $models[$provider] ?? [];
            
            // First, look for a model marked as default
            foreach ($providerModels as $model) {
                if (isset($model['isDefault']) && isset($model['modelId'])) {
                    if ($model['isDefault']) {
                        return $model['modelId'];
                    }
                }
            }

            // If no default found, use the first enabled model for this provider
            foreach ($providerModels as $model) {
                if (isset($model['modelId'])) {
                    if ($model['enabled'] ?? true) {
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

            case 'create_habit':
            case 'complete_habit':
            case 'start_habit_timer':
            case 'stop_habit_timer':
            case 'delete_habit':
            case 'list_habits':
                if (isset($result['message'])) {
                    return (string)$result['message'];
                }
                return "Action {$actionName} completed successfully.";

            default:
                if (isset($result['message'])) {
                    return (string)$result['message'];
                }
                return "Completed: {$actionName}";
        }
    }

    private function isRetryableAction(string $actionName): bool {
        return in_array($actionName, [
            'list_tasks',
            'list_projects',
            'get_project_tasks',
            'list_clients',
            'search_knowledge_base',
            'list_habits',
            'list_inventory',
            'list_transactions',
            'list_invoices',
            'list_notes',
            'get_water_status',
        ], true);
    }

    private function shortenToolError(string $message, int $maxLen = 160): string {
        $trimmed = trim($message);
        if (strlen($trimmed) <= $maxLen) {
            return $trimmed;
        }
        return substr($trimmed, 0, $maxLen - 3) . '...';
    }

    private function buildToolStatusSummary(array $toolEvents): string {
        if (empty($toolEvents)) {
            return '';
        }

        $lines = ["Tool status:"];
        foreach ($toolEvents as $event) {
            $name = (string)($event['name'] ?? 'tool');
            $status = (string)($event['status'] ?? 'unknown');
            $duration = isset($event['durationMs']) ? $event['durationMs'] . "ms" : "n/a";
            $attempts = (int)($event['attempts'] ?? 1);
            $attemptLabel = $attempts > 1 ? " ({$attempts} attempts)" : "";

            if ($status === 'failed') {
                $error = (string)($event['error'] ?? 'Unknown error');
                $lines[] = "- {$name}: FAILED in {$duration}{$attemptLabel}. {$error}";
            } else {
                $statusLabel = strtoupper($status);
                $lines[] = "- {$name}: {$statusLabel} in {$duration}{$attemptLabel}.";
            }
        }

        return implode("\n", $lines);
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
            case 'list_habits':
                $habits = is_array($result['habits'] ?? null) ? $result['habits'] : [];
                return [
                    'count' => (int)($result['count'] ?? count($habits)),
                    'habits' => array_map(function($habit) {
                        return [
                            'id' => (string)($habit['id'] ?? ''),
                            'name' => trim((string)($habit['name'] ?? '')) ?: '(unnamed habit)',
                            'frequency' => (string)($habit['frequency'] ?? ''),
                            'category' => (string)($habit['category'] ?? ''),
                            'isActive' => (bool)($habit['isActive'] ?? true)
                        ];
                    }, array_slice($habits, 0, 30))
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

    /**
     * Generate proactive "Smart Start" suggestions based on current system state.
     * This runs entirely in PHP to avoid LLM latency/costs.
     */
    public function getProactiveSuggestions(): array {
        $suggestions = [];
        $projects = $this->db->load('projects') ?? [];
        
        // Metrics
        $overdueTasks = [];
        $todayTasks = [];
        $quickWins = [];
        $today = date('Y-m-d');
        
        foreach ($projects as $project) {
            foreach ($project['tasks'] ?? [] as $task) {
                $status = normalizeTaskStatus($task['status'] ?? 'todo');
                if (isTaskDone($status)) {
                    continue;
                }
                
                $dueDate = $task['dueDate'] ?? '';
                $est = (int)($task['estimatedMinutes'] ?? 0);
                
                // Overdue
                if ($dueDate && $dueDate < $today) {
                    $overdueTasks[] = $task;
                }
                
                // Today
                if ($dueDate === $today) {
                    $todayTasks[] = $task;
                }
                
                // Quick Wins (under 15 mins)
                if ($est > 0 && $est <= 15) {
                    $quickWins[] = $task;
                }
            }
        }
        
        // 1. Overdue Logic
        $overdueCount = count($overdueTasks);
        if ($overdueCount > 0) {
            $suggestions[] = [
                'label' => "Reschedule {$overdueCount} overdue tasks",
                'prompt' => "I have {$overdueCount} overdue tasks. Please help me reschedule them to today or tomorrow."
            ];
        }
        
        // 2. Daily Planning Logic
        $todayCount = count($todayTasks);
        if ($todayCount > 0) {
            $suggestions[] = [
                'label' => "Plan my day ({$todayCount} tasks)",
                'prompt' => "Review my {$todayCount} tasks for today and help me prioritize them into a schedule."
            ];
        } else {
            // No tasks for today?
            $suggestions[] = [
                'label' => "Plan my day",
                'prompt' => "I have no tasks scheduled for today. Please check my backlog and suggest 3 high-priority tasks to work on."
            ];
        }
        
        // 3. Quick Wins
        $quickCount = count($quickWins);
        if ($quickCount > 0) {
            $suggestions[] = [
                'label' => "Knock out quick wins ({$quickCount})",
                'prompt' => "List the {$quickCount} quick tasks (under 15 mins) so I can clear them out rapidly."
            ];
        }
        
        // 4. Project Review (Find most recently active project)
        usort($projects, fn($a, $b) => strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? ''));
        $activeProject = $projects[0] ?? null;
        if ($activeProject) {
            $name = $activeProject['name'];
            $suggestions[] = [
                'label' => "Review '{$name}'",
                'prompt' => "Summarize the status of project '{$name}' and tell me what is left to do."
            ];
        }
        
        // Limit to 4 suggestions
        return array_slice($suggestions, 0, 4);
    }
}
