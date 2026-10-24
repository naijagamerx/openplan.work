<?php
/**
 * MCPServer — MCP protocol handler and full tool registry.
 *
 * Translates JSON-RPC requests from api/mcp-server.php into calls against the
 * application's own REST API endpoints (api/*.php) via an internal HTTPS
 * self-call that forwards the MCP auth headers. This reuses every existing
 * endpoint's logic verbatim — guaranteeing behavior parity with the web UI
 * and the legacy Node stdio MCP server, with zero duplicated business logic.
 *
 * Why self-HTTP instead of direct class calls: most endpoints (habits, water,
 * finance, inventory, invoices, knowledge-base) keep their logic inline with
 * action routing and terminate via successResponse()/exit. They cannot be
 * include()d without killing the request, and re-implementing them per tool
 * would duplicate logic and drift. A loopback HTTPS call is the clean reuse.
 *
 * Full parity with mcp-server/index.js (86 tools).
 */

class MCPServer {
    private Database $db;
    private string $apiBase;
    private string $mcpToken;
    private string $masterPassword;
    private array $tools;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->apiBase = rtrim(APP_URL, '/') . '/api';
        $this->mcpToken = (string)($_SERVER['HTTP_X_MCP_API_TOKEN'] ?? '');
        $this->masterPassword = getMasterPassword();
        $this->tools = $this->buildToolRegistry();
    }

    // ------------------------------------------------------------------
    // JSON-RPC method handlers
    // ------------------------------------------------------------------

    public function initialize(array $params): array {
        return [
            'result' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['tools' => new stdClass()],
                'serverInfo' => ['name' => 'openplan-work', 'version' => '1.0.0'],
            ],
        ];
    }

    public function listTools(): array {
        $tools = [];
        foreach ($this->tools as $name => $def) {
            $tools[] = [
                'name'        => $name,
                'description' => $def['description'],
                'inputSchema' => $def['inputSchema'],
            ];
        }
        return ['result' => ['tools' => $tools]];
    }

    public function callTool(array $params): array {
        $name = (string)($params['name'] ?? '');
        $args = (array)($params['arguments'] ?? []);

        if (!isset($this->tools[$name])) {
            return ['error' => ['code' => -32602, 'message' => "Unknown tool: {$name}"]];
        }

        try {
            $output = ($this->tools[$name]['handler'])($args);
        } catch (Throwable $e) {
            error_log("[mcp-server] tool '{$name}' threw: " . $e->getMessage());
            return $this->textResult([
                'success' => false,
                'error'   => $e->getMessage(),
                'tool'    => $name,
            ], true);
        }

        $isError = is_array($output) && (($output['success'] ?? null) === false);
        return $this->textResult($output, $isError);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Wrap a value as an MCP tools/call result. */
    private function textResult($data, bool $isError = false): array {
        $text = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $result = ['content' => [['type' => 'text', 'text' => $text]]];
        if ($isError) {
            $result['isError'] = true;
        }
        return ['result' => $result];
    }

    /**
     * Internal self-call to one of the app's own API endpoints.
     * Forwards MCP auth headers so the sub-request authenticates as the same user.
     *
     * @param string $endpoint e.g. "todos.php" or "habits.php?action=complete"
     * @param string $method   GET|POST|PUT|DELETE|PATCH
     * @param array|null $body  JSON body for write methods
     * @param array $query      Extra query params merged into the URL
     * @return array Decoded JSON response (or a synthetic error array)
     */
    private function apiCall(string $endpoint, string $method = 'GET', ?array $body = null, array $query = []): array {
        $url = $this->apiBase . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $url .= $sep . http_build_query($query);
        }

        $headers = [
            'X-MCP-Tool: true',
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($this->mcpToken !== '') {
            $headers[] = 'X-MCP-Api-Token: ' . $this->mcpToken;
        }
        if ($this->masterPassword !== '') {
            $headers[] = 'X-Master-Password: ' . $this->masterPassword;
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['success' => false, 'error' => "Internal API call failed: {$err}", 'endpoint' => $endpoint];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'error' => 'Non-JSON response from endpoint', 'http_code' => $code, 'body' => substr($raw, 0, 300), 'endpoint' => $endpoint];
        }
        return $decoded;
    }

    /** Convert a date-ish string to ISO 8601; returns null for empty. */
    private function toIso(?string $date): ?string {
        if ($date === null || $date === '') {
            return null;
        }
        $ts = strtotime($date);
        return $ts === false ? $date : date('c', $ts);
    }

    private function xmlEscape(?string $s): string {
        if ($s === null || $s === '') return '';
        return str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
            (string)$s
        );
    }

    private function safeFilename(string $title, string $ext): string {
        $safe = strtolower(trim($title));
        $safe = preg_replace('/[^a-z0-9]+/', '-', $safe);
        $safe = trim($safe, '-');
        if ($safe === '') $safe = 'file';
        return $safe . '.' . $ext;
    }

    private function validKbExtension(string $filename): bool {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['md', 'xml'], true);
    }

    // ------------------------------------------------------------------
    // Tool registry — full parity (86 tools)
    // ------------------------------------------------------------------

    private function buildToolRegistry(): array {
        $S = fn(string $desc) => $desc; // readability noop

        return array_merge(
            $this->todoTools(),
            $this->projectTools(),
            $this->taskTools(),
            $this->clientTools(),
            $this->waterTools(),
            $this->habitTools(),
            $this->meetingTools(),
            $this->noteTools(),
            $this->financeTools(),
            $this->inventoryTools(),
            $this->invoiceTools(),
            $this->advancedInvoiceTools(),
            $this->knowledgeBaseTools(),
            $this->kbTextTools(),
            $this->systemTools(),
        );
    }

    private function obj(array $properties, array $required = []): array {
        $schema = ['type' => 'object', 'properties' => $properties];
        if (!empty($required)) {
            $schema['required'] = $required;
        }
        return $schema;
    }

    private function str(string $desc, ?array $enum = null): array {
        $s = ['type' => 'string', 'description' => $desc];
        if ($enum) $s['enum'] = $enum;
        return $s;
    }
    private function num(string $desc): array { return ['type' => 'number', 'description' => $desc]; }
    private function bool(string $desc): array { return ['type' => 'boolean', 'description' => $desc]; }
    private function arr(string $desc, array $items = ['type' => 'string']): array {
        return ['type' => 'array', 'description' => $desc, 'items' => $items];
    }

    // ---------------- TODOS ----------------
    private function todoTools(): array {
        return [
            'list_todos' => [
                'description' => 'Get a list of all todos. Returns all todo items sorted by creation date (newest first).',
                'inputSchema' => $this->obj(['status' => $this->str('Filter by status (default: all)', ['pending', 'completed', 'all'])]),
                'handler' => function(array $a) {
                    $r = $this->apiCall('todos.php');
                    if (!empty($a['status']) && $a['status'] !== 'all' && isset($r['data']) && is_array($r['data'])) {
                        $r['data'] = array_values(array_filter($r['data'], fn($t) => ($t['status'] ?? '') === $a['status']));
                    }
                    return $r;
                },
            ],
            'add_todo' => [
                'description' => 'Create a new todo item with title, optional description, and priority.',
                'inputSchema' => $this->obj([
                    'title' => $this->str('The todo title (required)'),
                    'description' => $this->str('Optional detailed description'),
                    'priority' => $this->str('Priority level (default: medium)', ['low', 'medium', 'high', 'urgent']),
                    'category' => $this->str('Optional category/tag'),
                    'dueDate' => $this->str('Due date in ISO format (YYYY-MM-DD)'),
                ], ['title']),
                'handler' => fn(array $a) => $this->apiCall('todos.php', 'POST', [
                    'title' => $a['title'] ?? '',
                    'description' => $a['description'] ?? '',
                    'priority' => $a['priority'] ?? 'medium',
                    'category' => $a['category'] ?? '',
                    'dueDate' => $this->toIso($a['dueDate'] ?? null),
                ]),
            ],
            'update_todo' => [
                'description' => 'Update an existing todo. Can update title, description, priority, status, or dueDate.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('The todo ID (UUID)'),
                    'title' => $this->str('New title'),
                    'description' => $this->str('New description'),
                    'priority' => $this->str('New priority level', ['low', 'medium', 'high', 'urgent']),
                    'status' => $this->str('New status', ['pending', 'completed']),
                    'dueDate' => $this->str('New due date (YYYY-MM-DD)'),
                ], ['id']),
                'handler' => function(array $a) {
                    $body = [];
                    foreach (['title', 'description', 'priority', 'status'] as $f) {
                        if (isset($a[$f])) $body[$f] = $a[$f];
                    }
                    if (isset($a['dueDate'])) $body['dueDate'] = $this->toIso($a['dueDate']);
                    return $this->apiCall('todos.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', $body);
                },
            ],
            'delete_todo' => [
                'description' => 'Delete a todo by its ID.',
                'inputSchema' => $this->obj(['id' => $this->str('The todo ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('todos.php?id=' . rawurlencode($a['id'] ?? ''), 'DELETE'),
            ],
            'complete_todo' => [
                'description' => 'Mark a todo as completed.',
                'inputSchema' => $this->obj(['id' => $this->str('The todo ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('todos.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', ['status' => 'completed']),
            ],
        ];
    }

    // ---------------- PROJECTS ----------------
    private function projectTools(): array {
        return [
            'list_projects' => [
                'description' => 'Get all projects with their embedded tasks. Returns project details including all tasks.',
                'inputSchema' => $this->obj([
                    'status' => $this->str('Filter by status (default: all)', ['active', 'completed', 'on-hold', 'cancelled', 'all']),
                    'clientId' => $this->str('Filter by client ID'),
                ]),
                'handler' => function(array $a) {
                    $r = $this->apiCall('projects.php');
                    if (isset($r['data']) && is_array($r['data'])) {
                        if (!empty($a['status']) && $a['status'] !== 'all') {
                            $r['data'] = array_values(array_filter($r['data'], fn($p) => ($p['status'] ?? '') === $a['status']));
                        }
                        if (!empty($a['clientId'])) {
                            $r['data'] = array_values(array_filter($r['data'], fn($p) => ($p['clientId'] ?? '') === $a['clientId']));
                        }
                    }
                    return $r;
                },
            ],
            'get_project' => [
                'description' => 'Get detailed information about a specific project including all its tasks.',
                'inputSchema' => $this->obj(['id' => $this->str('The project ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('projects.php?id=' . rawurlencode($a['id'] ?? '')),
            ],
            'add_project' => [
                'description' => 'Create a new project. Optionally link to an existing client.',
                'inputSchema' => $this->obj([
                    'name' => $this->str('Project name (required)'),
                    'description' => $this->str('Project description'),
                    'clientId' => $this->str('Linked client ID'),
                    'status' => $this->str('Project status (default: active)', ['active', 'completed', 'on-hold', 'cancelled']),
                    'color' => $this->str('Hex color code (e.g., #3B82F6)'),
                ], ['name']),
                'handler' => fn(array $a) => $this->apiCall('projects.php', 'POST', [
                    'name' => $a['name'] ?? '',
                    'description' => $a['description'] ?? '',
                    'clientId' => $a['clientId'] ?? null,
                    'status' => $a['status'] ?? 'active',
                    'color' => $a['color'] ?? '#3B82F6',
                ]),
            ],
            'update_project' => [
                'description' => 'Update project details including name, description, status, or color.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('The project ID (UUID)'),
                    'name' => $this->str('New project name'),
                    'description' => $this->str('New description'),
                    'clientId' => $this->str('New client ID'),
                    'status' => $this->str('New status', ['active', 'completed', 'on-hold', 'cancelled']),
                    'color' => $this->str('New hex color'),
                ], ['id']),
                'handler' => function(array $a) {
                    $body = [];
                    foreach (['name', 'description', 'clientId', 'status', 'color'] as $f) {
                        if (isset($a[$f])) $body[$f] = $a[$f];
                    }
                    return $this->apiCall('projects.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', $body);
                },
            ],
            'delete_project' => [
                'description' => 'Delete a project and all its embedded tasks.',
                'inputSchema' => $this->obj(['id' => $this->str('The project ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('projects.php?id=' . rawurlencode($a['id'] ?? ''), 'DELETE'),
            ],
        ];
    }

    // ---------------- TASKS ----------------
    private function taskTools(): array {
        return [
            'list_tasks' => [
                'description' => 'Get all tasks across all projects, or filter by projectId, status, or priority.',
                'inputSchema' => $this->obj([
                    'projectId' => $this->str('Filter by project ID'),
                    'status' => $this->str('Filter by status', ['backlog', 'todo', 'in_progress', 'review', 'done']),
                    'priority' => $this->str('Filter by priority', ['low', 'medium', 'high', 'urgent']),
                ]),
                'handler' => function(array $a) {
                    $pr = $this->apiCall('projects.php');
                    $tasks = [];
                    foreach (($pr['data'] ?? []) as $project) {
                        foreach (($project['tasks'] ?? []) as $t) {
                            $t['projectId'] = $project['id'] ?? null;
                            $t['projectName'] = $project['name'] ?? null;
                            $tasks[] = $t;
                        }
                    }
                    if (!empty($a['projectId'])) $tasks = array_filter($tasks, fn($t) => ($t['projectId'] ?? '') === $a['projectId']);
                    if (!empty($a['status']))   $tasks = array_filter($tasks, fn($t) => ($t['status'] ?? '') === $a['status']);
                    if (!empty($a['priority'])) $tasks = array_filter($tasks, fn($t) => ($t['priority'] ?? '') === $a['priority']);
                    return ['success' => true, 'data' => array_values($tasks)];
                },
            ],
            'add_task' => [
                'description' => 'Add a new task to a project. Requires projectId and title.',
                'inputSchema' => $this->obj([
                    'projectId' => $this->str('The project ID to add task to (required)'),
                    'title' => $this->str('Task title (required)'),
                    'description' => $this->str('Task description'),
                    'status' => $this->str('Initial status (default: todo)', ['backlog', 'todo', 'in_progress', 'review', 'done']),
                    'priority' => $this->str('Priority level (default: medium)', ['low', 'medium', 'high', 'urgent']),
                    'dueDate' => $this->str('Due date (YYYY-MM-DD)'),
                    'estimatedMinutes' => $this->num('Estimated time in minutes'),
                ], ['projectId', 'title']),
                'handler' => fn(array $a) => $this->apiCall('tasks.php', 'POST', [
                    'projectId' => $a['projectId'] ?? '',
                    'title' => $a['title'] ?? '',
                    'description' => $a['description'] ?? '',
                    'status' => $a['status'] ?? 'todo',
                    'priority' => $a['priority'] ?? 'medium',
                    'dueDate' => $this->toIso($a['dueDate'] ?? null),
                    'estimatedMinutes' => (int)($a['estimatedMinutes'] ?? 0),
                ]),
            ],
            'update_task' => [
                'description' => 'Update a task within a project. Requires projectId and taskId.',
                'inputSchema' => $this->obj([
                    'projectId' => $this->str('The project ID (required)'),
                    'taskId' => $this->str('The task ID (UUID, required)'),
                    'title' => $this->str('New title'),
                    'description' => $this->str('New description'),
                    'status' => $this->str('New status', ['backlog', 'todo', 'in_progress', 'review', 'done']),
                    'priority' => $this->str('New priority', ['low', 'medium', 'high', 'urgent']),
                    'dueDate' => $this->str('New due date'),
                    'estimatedMinutes' => $this->num('New estimated time'),
                ], ['projectId', 'taskId']),
                'handler' => function(array $a) {
                    $body = [];
                    foreach (['title', 'description', 'status', 'priority'] as $f) {
                        if (isset($a[$f])) $body[$f] = $a[$f];
                    }
                    if (isset($a['dueDate'])) $body['dueDate'] = $this->toIso($a['dueDate']);
                    if (isset($a['estimatedMinutes'])) $body['estimatedMinutes'] = (int)$a['estimatedMinutes'];
                    return $this->apiCall('tasks.php?projectId=' . rawurlencode($a['projectId'] ?? '') . '&id=' . rawurlencode($a['taskId'] ?? ''), 'PUT', $body);
                },
            ],
            'delete_task' => [
                'description' => 'Delete a task from a project.',
                'inputSchema' => $this->obj([
                    'projectId' => $this->str('The project ID (required)'),
                    'taskId' => $this->str('The task ID (UUID, required)'),
                ], ['projectId', 'taskId']),
                'handler' => fn(array $a) => $this->apiCall('tasks.php?projectId=' . rawurlencode($a['projectId'] ?? '') . '&id=' . rawurlencode($a['taskId'] ?? ''), 'DELETE'),
            ],
            'complete_task' => [
                'description' => 'Mark a task as completed.',
                'inputSchema' => $this->obj([
                    'projectId' => $this->str('The project ID (required)'),
                    'taskId' => $this->str('The task ID (UUID, required)'),
                ], ['projectId', 'taskId']),
                'handler' => fn(array $a) => $this->apiCall('tasks.php?projectId=' . rawurlencode($a['projectId'] ?? '') . '&id=' . rawurlencode($a['taskId'] ?? ''), 'PUT', ['status' => 'done']),
            ],
            'add_subtask' => [
                'description' => 'Add a subtask to an existing task.',
                'inputSchema' => $this->obj([
                    'projectId' => $this->str('The project ID (required)'),
                    'taskId' => $this->str('The task ID (UUID, required)'),
                    'title' => $this->str('Subtask title (required)'),
                    'estimatedMinutes' => $this->num('Estimated time'),
                ], ['projectId', 'taskId', 'title']),
                'handler' => fn(array $a) => $this->apiCall(
                    'tasks.php?projectId=' . rawurlencode($a['projectId'] ?? '') . '&id=' . rawurlencode($a['taskId'] ?? '') . '&action=subtask',
                    'POST',
                    ['title' => $a['title'] ?? '', 'estimatedMinutes' => (int)($a['estimatedMinutes'] ?? 0)]
                ),
            ],
        ];
    }

    // ---------------- CLIENTS ----------------
    private function clientTools(): array {
        $addrFields = [
            'street' => 'Street address', 'city' => 'City', 'state' => 'State/Province',
            'zip' => 'ZIP/Postal code', 'country' => 'Country',
        ];
        $props = [
            'name' => $this->str('Client name'), 'email' => $this->str('Email address'),
            'phone' => $this->str('Phone number'), 'company' => $this->str('Company name'),
            'notes' => $this->str('Additional notes'),
        ];
        foreach ($addrFields as $k => $d) $props[$k] = $this->str($d);

        $buildBody = function(array $a, bool $forUpdate): array {
            $body = [];
            foreach (['name', 'email', 'phone', 'company', 'notes'] as $f) {
                if (isset($a[$f])) $body[$f] = $a[$f];
            }
            $hasAddr = isset($a['street']) || isset($a['city']) || isset($a['state']) || isset($a['zip']) || isset($a['country']);
            if ($hasAddr || !$forUpdate) {
                $body['address'] = [
                    'street' => $a['street'] ?? '', 'city' => $a['city'] ?? '', 'state' => $a['state'] ?? '',
                    'zip' => $a['zip'] ?? '', 'country' => $a['country'] ?? '',
                ];
            }
            return $body;
        };

        return [
            'list_clients' => [
                'description' => 'Get all clients/contacts from the CRM.',
                'inputSchema' => $this->obj([]),
                'handler' => fn(array $a) => $this->apiCall('clients.php'),
            ],
            'get_client' => [
                'description' => 'Get detailed information about a specific client.',
                'inputSchema' => $this->obj(['id' => $this->str('The client ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('clients.php?id=' . rawurlencode($a['id'] ?? '')),
            ],
            'add_client' => [
                'description' => 'Add a new client/contact to the CRM. Both name and email are required.',
                'inputSchema' => $this->obj($props + ['name' => $this->str('Client name (required)'), 'email' => $this->str('Email address (required)')], ['name', 'email']),
                'handler' => fn(array $a) => $this->apiCall('clients.php', 'POST', $buildBody($a, false)),
            ],
            'update_client' => [
                'description' => 'Update client information.',
                'inputSchema' => $this->obj($props + ['id' => $this->str('The client ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('clients.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', $buildBody($a, true)),
            ],
            'delete_client' => [
                'description' => 'Delete a client from the CRM.',
                'inputSchema' => $this->obj(['id' => $this->str('The client ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('clients.php?id=' . rawurlencode($a['id'] ?? ''), 'DELETE'),
            ],
        ];
    }

    // ---------------- WATER ----------------
    private function waterTools(): array {
        $today = fn() => date('Y-m-d');
        return [
            'get_water_status' => [
                'description' => "Get current water tracking status including today's intake and goal.",
                'inputSchema' => $this->obj(['date' => $this->str('Specific date (YYYY-MM-DD, default: today)')]),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=get_water_tracker&date=' . rawurlencode($a['date'] ?? $today())),
            ],
            'log_water' => [
                'description' => 'Log water intake by adding glasses. Default is 1 glass.',
                'inputSchema' => $this->obj([
                    'glasses' => $this->num('Number of glasses to add (default: 1)'),
                    'date' => $this->str('Date for this entry (YYYY-MM-DD, default: today)'),
                ]),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=add_water_glass', 'POST', [
                    'count' => (int)($a['glasses'] ?? 1),
                    'goal' => 8,
                    'reminderInterval' => 60,
                    'date' => $a['date'] ?? $today(),
                ]),
            ],
            'set_water_goal' => [
                'description' => 'Set or update the daily water goal (number of glasses).',
                'inputSchema' => $this->obj(['goal' => $this->num('Daily goal in glasses (required)')], ['goal']),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=set_water_goal', 'POST', ['goal' => (int)($a['goal'] ?? 8)]),
            ],
            'get_water_history' => [
                'description' => 'Get water tracking history for a date range.',
                'inputSchema' => $this->obj([
                    'startDate' => $this->str('Start date (YYYY-MM-DD)'),
                    'endDate' => $this->str('End date (YYYY-MM-DD)'),
                ]),
                'handler' => function(array $a) use ($today) {
                    $start = $a['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
                    $end = $a['endDate'] ?? $today();
                    $history = [];
                    $cur = strtotime($start);
                    $endTs = strtotime($end);
                    $guard = 0;
                    while ($cur <= $endTs && $guard < 400) {
                        $d = date('Y-m-d', $cur);
                        $r = $this->apiCall('habits.php?action=get_water_tracker&date=' . $d);
                        if (isset($r['data'])) $history[] = ['date' => $d] + (array)$r['data'];
                        $cur = strtotime('+1 day', $cur);
                        $guard++;
                    }
                    return ['success' => true, 'data' => $history];
                },
            ],
            'generate_water_plan' => [
                'description' => 'Generate an AI-powered personalized hydration plan based on weight, activity level, climate, and schedule.',
                'inputSchema' => $this->obj([
                    'weight' => $this->num('Weight in kg (default: 70)'),
                    'activity' => $this->str('Activity level (default: moderate)', ['sedentary', 'moderate', 'active', 'athlete']),
                    'climate' => $this->str('Climate (default: temperate)', ['temperate', 'hot', 'humid', 'cold']),
                    'goal' => $this->num('Daily water goal in ml (default: 2500)'),
                    'wakeTime' => $this->str('Wake time HH:MM (default: 07:00)'),
                    'sleepTime' => $this->str('Sleep time HH:MM (default: 23:00)'),
                    'glassSize' => $this->num('Glass size in ml (default: 250)'),
                ]),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=generate_water_plan', 'POST', [
                    'weight' => (float)($a['weight'] ?? 70),
                    'activity' => $a['activity'] ?? 'moderate',
                    'climate' => $a['climate'] ?? 'temperate',
                    'goal' => (int)($a['goal'] ?? 2500),
                    'wakeTime' => $a['wakeTime'] ?? '07:00',
                    'sleepTime' => $a['sleepTime'] ?? '23:00',
                    'glassSize' => (int)($a['glassSize'] ?? 250),
                ]),
            ],
            'list_water_plan_history' => [
                'description' => 'List all saved water plan history.',
                'inputSchema' => $this->obj([]),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=list_water_plan_history'),
            ],
            'save_water_plan_history' => [
                'description' => 'Save the current water plan to history for later use.',
                'inputSchema' => $this->obj(['planId' => $this->str('The plan ID to save (required)')], ['planId']),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=save_water_plan_history', 'POST', ['planId' => $a['planId'] ?? '']),
            ],
        ];
    }

    // ---------------- HABITS ----------------
    private function habitTools(): array {
        return [
            'list_habits' => [
                'description' => 'Get all habits with their current status.',
                'inputSchema' => $this->obj([]),
                'handler' => fn(array $a) => $this->apiCall('habits.php'),
            ],
            'add_habit' => [
                'description' => 'Create a new habit to track.',
                'inputSchema' => $this->obj([
                    'name' => $this->str('Habit name (required)'),
                    'category' => $this->str('Category (default: general)'),
                    'frequency' => $this->str('Frequency (default: daily)', ['daily', 'weekly', 'monthly']),
                    'reminderTime' => $this->str('Daily reminder time (HH:MM)'),
                    'targetDuration' => $this->num('Target duration in minutes'),
                ], ['name']),
                'handler' => fn(array $a) => $this->apiCall('habits.php', 'POST', [
                    'name' => $a['name'] ?? '',
                    'category' => $a['category'] ?? 'general',
                    'frequency' => $a['frequency'] ?? 'daily',
                    'reminderTime' => $a['reminderTime'] ?? null,
                    'targetDuration' => (int)($a['targetDuration'] ?? 0),
                ]),
            ],
            'complete_habit' => [
                'description' => 'Mark a habit as complete for today or a specific date.',
                'inputSchema' => $this->obj([
                    'habitId' => $this->str('The habit ID (UUID, required)'),
                    'date' => $this->str('Date to complete (YYYY-MM-DD, default: today)'),
                ], ['habitId']),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=complete', 'POST', [
                    'habitId' => $a['habitId'] ?? '',
                    'date' => $a['date'] ?? date('Y-m-d'),
                ]),
            ],
            'get_habit_stats' => [
                'description' => 'Get habit tracking statistics and completion history.',
                'inputSchema' => $this->obj([
                    'habitId' => $this->str('Specific habit ID'),
                    'days' => $this->num('Number of days to look back (default: 30)'),
                ]),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=stats&habitId=' . rawurlencode($a['habitId'] ?? '') . '&days=' . (int)($a['days'] ?? 30)),
            ],
            'start_habit_timer' => [
                'description' => 'Start a timer session for a habit.',
                'inputSchema' => $this->obj(['habitId' => $this->str('The habit ID (UUID, required)')], ['habitId']),
                'handler' => fn(array $a) => $this->apiCall('habits.php?action=start_timer', 'POST', ['habitId' => $a['habitId'] ?? '']),
            ],
            'stop_habit_timer' => [
                'description' => 'Stop a running habit timer session. Requires the sessionId returned by start_habit_timer.',
                'inputSchema' => $this->obj([
                    'sessionId' => $this->str('The timer session ID returned by start_habit_timer (required)'),
                    'habitId' => $this->str('The habit ID (UUID, optional)'),
                ], ['sessionId']),
                'handler' => function(array $a) {
                    $body = ['sessionId' => $a['sessionId'] ?? ''];
                    if (!empty($a['habitId'])) $body['habitId'] = $a['habitId'];
                    return $this->apiCall('habits.php?action=stop_timer', 'POST', $body);
                },
            ],
            'delete_habit' => [
                'description' => 'Delete a habit and all its completion records.',
                'inputSchema' => $this->obj(['habitId' => $this->str('The habit ID (UUID, required)')], ['habitId']),
                'handler' => fn(array $a) => $this->apiCall('habits.php?id=' . rawurlencode($a['habitId'] ?? ''), 'DELETE'),
            ],
        ];
    }

    // ---------------- MEETINGS ----------------
    private function meetingTools(): array {
        $fields = ['title', 'date', 'startTime', 'endTime', 'location', 'attendees', 'description', 'color', 'projectId', 'clientId'];
        return [
            'list_meetings' => [
                'description' => 'List calendar meetings. Optional filters: month + year (1-12, 4-digit), date (YYYY-MM-DD), projectId, clientId.',
                'inputSchema' => $this->obj([
                    'month' => $this->num('Month 1-12 (requires year)'),
                    'year' => $this->num('4-digit year (requires month)'),
                    'date' => $this->str('Exact date YYYY-MM-DD'),
                    'projectId' => $this->str('Filter by project'),
                    'clientId' => $this->str('Filter by client'),
                ]),
                'handler' => function(array $a) {
                    $q = [];
                    foreach (['month', 'year', 'date', 'projectId', 'clientId'] as $f) {
                        if (isset($a[$f]) && $a[$f] !== '') $q[$f] = $a[$f];
                    }
                    return $this->apiCall('meetings.php', 'GET', null, $q);
                },
            ],
            'get_meeting' => [
                'description' => 'Get a single meeting by ID.',
                'inputSchema' => $this->obj(['meetingId' => $this->str('Meeting ID (required)')], ['meetingId']),
                'handler' => fn(array $a) => $this->apiCall('meetings.php?id=' . rawurlencode($a['meetingId'] ?? '')),
            ],
            'add_meeting' => [
                'description' => 'Create a new calendar meeting. Title and date required; everything else optional.',
                'inputSchema' => $this->obj([
                    'title' => $this->str('Meeting title (required)'),
                    'date' => $this->str('Date YYYY-MM-DD (required)'),
                    'startTime' => $this->str('Start time HH:MM (24h)'),
                    'endTime' => $this->str('End time HH:MM (24h)'),
                    'location' => $this->str('Room / Zoom link / address'),
                    'attendees' => $this->arr('Attendee names or emails'),
                    'description' => $this->str('Agenda or notes'),
                    'color' => $this->str('Hex color (default: #000000)'),
                    'projectId' => $this->str('Linked project ID'),
                    'clientId' => $this->str('Linked client ID'),
                ], ['title', 'date']),
                'handler' => fn(array $a) => $this->apiCall('meetings.php', 'POST', [
                    'title' => $a['title'] ?? '',
                    'date' => $a['date'] ?? '',
                    'startTime' => $a['startTime'] ?? '',
                    'endTime' => $a['endTime'] ?? '',
                    'location' => $a['location'] ?? '',
                    'attendees' => is_array($a['attendees'] ?? null) ? $a['attendees'] : [],
                    'description' => $a['description'] ?? '',
                    'color' => $a['color'] ?? '#000000',
                    'projectId' => $a['projectId'] ?? '',
                    'clientId' => $a['clientId'] ?? '',
                ]),
            ],
            'update_meeting' => [
                'description' => 'Update fields of an existing meeting. Pass only fields to change.',
                'inputSchema' => $this->obj([
                    'meetingId' => $this->str('Meeting ID (required)'),
                    'title' => $this->str('Title'), 'date' => $this->str('YYYY-MM-DD'),
                    'startTime' => $this->str('HH:MM'), 'endTime' => $this->str('HH:MM'),
                    'location' => $this->str('Location'), 'attendees' => $this->arr('Attendees'),
                    'description' => $this->str('Description'), 'color' => $this->str('Hex color'),
                    'projectId' => $this->str('Project ID'), 'clientId' => $this->str('Client ID'),
                ], ['meetingId']),
                'handler' => function(array $a) use ($fields) {
                    $body = [];
                    foreach ($fields as $f) {
                        if (array_key_exists($f, $a)) $body[$f] = $a[$f];
                    }
                    return $this->apiCall('meetings.php?id=' . rawurlencode($a['meetingId'] ?? ''), 'PUT', $body);
                },
            ],
            'delete_meeting' => [
                'description' => 'Permanently delete a meeting.',
                'inputSchema' => $this->obj(['meetingId' => $this->str('Meeting ID (required)')], ['meetingId']),
                'handler' => fn(array $a) => $this->apiCall('meetings.php?id=' . rawurlencode($a['meetingId'] ?? ''), 'DELETE'),
            ],
        ];
    }

    // ---------------- NOTES ----------------
    private function noteTools(): array {
        return [
            'list_notes' => [
                'description' => 'Get all notes with optional filters.',
                'inputSchema' => $this->obj([
                    'tag' => $this->str('Filter by tag'),
                    'search' => $this->str('Search query'),
                    'isPinned' => $this->bool('Filter to pinned notes only'),
                    'isFavorite' => $this->bool('Filter to favorite notes only'),
                ]),
                'handler' => function(array $a) {
                    $q = [];
                    foreach (['tag', 'search', 'isPinned', 'isFavorite'] as $f) {
                        if (isset($a[$f])) $q[$f] = is_bool($a[$f]) ? ($a[$f] ? 'true' : 'false') : $a[$f];
                    }
                    return $this->apiCall('notes.php', 'GET', null, $q);
                },
            ],
            'get_note' => [
                'description' => 'Get a specific note by ID.',
                'inputSchema' => $this->obj(['id' => $this->str('The note ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('notes.php?id=' . rawurlencode($a['id'] ?? '')),
            ],
            'create_note' => [
                'description' => 'Create a new note.',
                'inputSchema' => $this->obj([
                    'title' => $this->str('Note title (required)'),
                    'content' => $this->str('Note content'),
                    'tags' => $this->arr('Array of tags'),
                    'color' => $this->str('Background color (hex)'),
                ], ['title']),
                'handler' => fn(array $a) => $this->apiCall('notes.php', 'POST', [
                    'title' => $a['title'] ?? '',
                    'content' => $a['content'] ?? '',
                    'tags' => is_array($a['tags'] ?? null) ? $a['tags'] : [],
                    'color' => $a['color'] ?? '',
                ]),
            ],
            'update_note' => [
                'description' => 'Update an existing note.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('The note ID (UUID, required)'),
                    'title' => $this->str('New title'), 'content' => $this->str('New content'),
                    'tags' => $this->arr('New tags array'), 'color' => $this->str('New background color'),
                    'isPinned' => $this->bool('Pin status'), 'isFavorite' => $this->bool('Favorite status'),
                ], ['id']),
                'handler' => function(array $a) {
                    $body = [];
                    foreach (['title', 'content', 'tags', 'color', 'isPinned', 'isFavorite'] as $f) {
                        if (array_key_exists($f, $a)) $body[$f] = $a[$f];
                    }
                    return $this->apiCall('notes.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', $body);
                },
            ],
            'delete_note' => [
                'description' => 'Delete a note.',
                'inputSchema' => $this->obj(['id' => $this->str('The note ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('notes.php?id=' . rawurlencode($a['id'] ?? ''), 'DELETE'),
            ],
            'search_notes' => [
                'description' => 'Search notes by query (title or content).',
                'inputSchema' => $this->obj(['query' => $this->str('Search query (required)')], ['query']),
                'handler' => fn(array $a) => $this->apiCall('notes.php?action=search&query=' . rawurlencode($a['query'] ?? '')),
            ],
            'list_note_tags' => [
                'description' => 'Get all unique tags from all notes.',
                'inputSchema' => $this->obj([]),
                'handler' => fn(array $a) => $this->apiCall('notes.php?action=tags'),
            ],
            'toggle_note_pin' => [
                'description' => 'Pin or unpin a note.',
                'inputSchema' => $this->obj(['id' => $this->str('The note ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('notes.php?action=pin&id=' . rawurlencode($a['id'] ?? ''), 'POST', []),
            ],
            'toggle_note_favorite' => [
                'description' => 'Add or remove a note from favorites.',
                'inputSchema' => $this->obj(['id' => $this->str('The note ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('notes.php?action=favorite&id=' . rawurlencode($a['id'] ?? ''), 'POST', []),
            ],
            'convert_note_to_kb' => [
                'description' => 'Convert a note to a file in the knowledge base. Exports as Markdown or XML with auto-renaming for duplicates.',
                'inputSchema' => $this->obj([
                    'noteId' => $this->str('The note ID to convert (UUID, required)'),
                    'folderId' => $this->str('Target folder ID in knowledge base (UUID, required)'),
                    'filename' => $this->str('Filename (without extension, auto-generated from note title if not provided)'),
                    'format' => $this->str('Export format: markdown (.md) or xml (.xml). Default: markdown', ['markdown', 'xml']),
                ], ['noteId', 'folderId']),
                'handler' => function(array $a) {
                    $noteRes = $this->apiCall('notes.php?id=' . rawurlencode($a['noteId'] ?? ''));
                    if (!($noteRes['success'] ?? false)) return $noteRes;
                    $note = $noteRes['data'] ?? [];
                    $format = $a['format'] ?? 'markdown';
                    $ext = $format === 'xml' ? 'xml' : 'md';
                    $baseName = $a['filename'] ?? ($note['title'] ?? 'note');
                    $filename = $this->safeFilename($baseName, $ext);

                    if ($format === 'xml') {
                        $tags = '';
                        foreach (($note['tags'] ?? []) as $t) {
                            $tags .= '    <tag>' . $this->xmlEscape($t) . "</tag>\n";
                        }
                        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                            . "<note>\n"
                            . '  <title>' . $this->xmlEscape($note['title'] ?? 'Untitled') . "</title>\n"
                            . '  <content>' . $this->xmlEscape($note['content'] ?? '') . "</content>\n"
                            . "  <tags>\n" . ($tags !== '' ? $tags : "    ") . "  </tags>\n"
                            . "  <metadata>\n"
                            . '    <createdAt>' . ($note['createdAt'] ?? date('c')) . "</createdAt>\n"
                            . '    <updatedAt>' . ($note['updatedAt'] ?? date('c')) . "</updatedAt>\n"
                            . "  </metadata>\n</note>";
                    } else {
                        $content = '# ' . ($note['title'] ?? 'Note') . "\n\n" . ($note['content'] ?? '');
                    }

                    return $this->apiCall('knowledge-base.php', 'POST', [
                        'folderId' => $a['folderId'] ?? '',
                        'name' => $filename,
                        'type' => $format === 'xml' ? 'xml' : 'markdown',
                        'content' => base64_encode($content),
                        'size' => strlen($content),
                    ], ['action' => 'upload_file']);
                },
            ],
            'ai_edit_note' => [
                'description' => 'Use AI to edit a note with operations: rewrite, improve, expand, or summarize.',
                'inputSchema' => $this->obj([
                    'noteId' => $this->str('The note ID to edit (UUID, required)'),
                    'operation' => $this->str('AI operation', ['rewrite', 'improve', 'expand', 'summarize']),
                    'provider' => $this->str('AI provider: groq or openrouter (optional)', ['groq', 'openrouter']),
                    'model' => $this->str('AI model ID (optional)'),
                ], ['noteId', 'operation']),
                'handler' => function(array $a) {
                    $noteRes = $this->apiCall('notes.php?id=' . rawurlencode($a['noteId'] ?? ''));
                    if (!($noteRes['success'] ?? false)) return $noteRes;
                    $aiBody = ['noteId' => $a['noteId'] ?? '', 'operation' => $a['operation'] ?? ''];
                    if (!empty($a['provider'])) $aiBody['provider'] = $a['provider'];
                    if (!empty($a['model'])) $aiBody['model'] = $a['model'];
                    $aiRes = $this->apiCall('ai.php?action=edit_note', 'POST', $aiBody);
                    if (!($aiRes['success'] ?? false)) return $aiRes;
                    $updateRes = $this->apiCall('notes.php?id=' . rawurlencode($a['noteId'] ?? ''), 'PUT', [
                        'content' => $aiRes['data']['content'] ?? '',
                    ]);
                    return [
                        'success' => true,
                        'message' => 'Note ' . ($a['operation'] ?? '') . ' completed',
                        'edited' => $aiRes['data'] ?? null,
                        'update' => $updateRes,
                    ];
                },
            ],
        ];
    }

    // ---------------- FINANCE ----------------
    private function financeTools(): array {
        return [
            'list_transactions' => [
                'description' => 'Get all financial transactions (income and expenses).',
                'inputSchema' => $this->obj([
                    'type' => $this->str('Filter by type', ['expense', 'revenue']),
                    'startDate' => $this->str('Start date (YYYY-MM-DD)'),
                    'endDate' => $this->str('End date (YYYY-MM-DD)'),
                ]),
                'handler' => function(array $a) {
                    $q = [];
                    foreach (['type', 'startDate', 'endDate'] as $f) {
                        if (!empty($a[$f])) $q[$f] = $a[$f];
                    }
                    return $this->apiCall('finance.php', 'GET', null, $q);
                },
            ],
            'add_transaction' => [
                'description' => 'Add a new financial transaction (income or expense).',
                'inputSchema' => $this->obj([
                    'type' => $this->str('Transaction type (required)', ['expense', 'revenue']),
                    'amount' => $this->num('Amount (required)'),
                    'description' => $this->str('Description (required)'),
                    'category' => $this->str('Category'),
                    'date' => $this->str('Date (YYYY-MM-DD, default: today)'),
                    'projectId' => $this->str('Related project ID'),
                    'clientId' => $this->str('Related client ID'),
                    'notes' => $this->str('Additional notes'),
                ], ['type', 'amount', 'description']),
                'handler' => fn(array $a) => $this->apiCall('finance.php', 'POST', [
                    'type' => $a['type'] ?? 'expense',
                    'amount' => (float)($a['amount'] ?? 0),
                    'description' => $a['description'] ?? '',
                    'category' => $a['category'] ?? '',
                    'date' => $this->toIso($a['date'] ?? null) ?? date('c'),
                    'projectId' => $a['projectId'] ?? null,
                    'clientId' => $a['clientId'] ?? null,
                    'notes' => $a['notes'] ?? '',
                ]),
            ],
            'get_finance_summary' => [
                'description' => 'Get a financial summary including total income, expenses, and balance.',
                'inputSchema' => $this->obj([
                    'startDate' => $this->str('Start date (YYYY-MM-DD)'),
                    'endDate' => $this->str('End date (YYYY-MM-DD)'),
                ]),
                'handler' => function(array $a) {
                    $q = [];
                    foreach (['startDate', 'endDate'] as $f) {
                        if (!empty($a[$f])) $q[$f] = $a[$f];
                    }
                    return $this->apiCall('finance.php?action=summary', 'GET', null, $q);
                },
            ],
        ];
    }

    // ---------------- INVENTORY ----------------
    private function inventoryTools(): array {
        return [
            'list_inventory' => [
                'description' => 'Get all inventory items with stock levels.',
                'inputSchema' => $this->obj([
                    'category' => $this->str('Filter by category'),
                    'lowStock' => $this->bool('Show only low stock items'),
                ]),
                'handler' => function(array $a) {
                    $q = [];
                    if (!empty($a['category'])) $q['category'] = $a['category'];
                    if (!empty($a['lowStock'])) $q['lowStock'] = 'true';
                    return $this->apiCall('inventory.php', 'GET', null, $q);
                },
            ],
            'add_inventory_item' => [
                'description' => 'Add a new item to inventory.',
                'inputSchema' => $this->obj([
                    'name' => $this->str('Item name (required)'),
                    'sku' => $this->str('Stock keeping unit'),
                    'description' => $this->str('Description'),
                    'category' => $this->str('Category'),
                    'quantity' => $this->num('Initial quantity (default: 0)'),
                    'unitPrice' => $this->num('Selling price'),
                    'costPrice' => $this->num('Cost price'),
                    'reorderPoint' => $this->num('Reorder alert level'),
                    'supplier' => $this->str('Supplier name'),
                    'notes' => $this->str('Additional notes'),
                ], ['name']),
                'handler' => fn(array $a) => $this->apiCall('inventory.php', 'POST', [
                    'name' => $a['name'] ?? '',
                    'sku' => $a['sku'] ?? '',
                    'description' => $a['description'] ?? '',
                    'category' => $a['category'] ?? '',
                    'quantity' => (int)($a['quantity'] ?? 0),
                    'unitPrice' => (float)($a['unitPrice'] ?? 0),
                    'costPrice' => (float)($a['costPrice'] ?? 0),
                    'reorderPoint' => (int)($a['reorderPoint'] ?? 5),
                    'supplier' => $a['supplier'] ?? '',
                    'notes' => $a['notes'] ?? '',
                ]),
            ],
            'update_inventory_stock' => [
                'description' => 'Update inventory item quantity.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('Item ID (UUID, required)'),
                    'quantity' => $this->num('New quantity (required)'),
                ], ['id', 'quantity']),
                'handler' => fn(array $a) => $this->apiCall('inventory.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', ['quantity' => (int)($a['quantity'] ?? 0)]),
            ],
            'adjust_inventory_stock' => [
                'description' => 'Adjust inventory quantity by adding or subtracting.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('Item ID (UUID, required)'),
                    'adjustment' => $this->num('Change amount (+/-)'),
                    'notes' => $this->str('Reason for adjustment'),
                ], ['id', 'adjustment']),
                'handler' => fn(array $a) => $this->apiCall('inventory.php?action=adjust', 'POST', [
                    'id' => $a['id'] ?? '',
                    'adjustment' => (int)($a['adjustment'] ?? 0),
                    'note' => $a['notes'] ?? '',
                ]),
            ],
        ];
    }

    // ---------------- INVOICES ----------------
    private function invoiceTools(): array {
        return [
            'list_invoices' => [
                'description' => 'Get all invoices.',
                'inputSchema' => $this->obj([
                    'status' => $this->str('Filter by status', ['draft', 'sent', 'paid', 'overdue', 'cancelled']),
                    'clientId' => $this->str('Filter by client'),
                ]),
                'handler' => function(array $a) {
                    $q = [];
                    foreach (['status', 'clientId'] as $f) {
                        if (!empty($a[$f])) $q[$f] = $a[$f];
                    }
                    return $this->apiCall('invoices.php', 'GET', null, $q);
                },
            ],
            'create_invoice' => [
                'description' => 'Create a new invoice for a client. Pass lineItems with description, quantity, and unitPrice for each item. The API calculates totals automatically.',
                'inputSchema' => $this->obj([
                    'clientId' => $this->str('Client ID (UUID, required)'),
                    'projectId' => $this->str('Related project ID'),
                    'lineItems' => $this->arr('Invoice line items', $this->obj([
                        'description' => $this->str('Item description'),
                        'quantity' => $this->num('Quantity'),
                        'unitPrice' => $this->num('Unit price'),
                    ], ['description', 'quantity', 'unitPrice'])),
                    'dueDate' => $this->str('Due date (YYYY-MM-DD, default: 30 days from now)'),
                    'notes' => $this->str('Invoice notes'),
                    'currency' => $this->str('Currency code (USD, EUR, etc.)'),
                ], ['clientId', 'lineItems']),
                'handler' => function(array $a) {
                    $dueDate = $a['dueDate'] ?? '';
                    if ($dueDate === '') {
                        $dueDate = date('Y-m-d', strtotime('+30 days'));
                    } elseif (str_contains($dueDate, 'T')) {
                        $dueDate = explode('T', $dueDate)[0];
                    }
                    return $this->apiCall('invoices.php', 'POST', [
                        'clientId' => $a['clientId'] ?? '',
                        'projectId' => $a['projectId'] ?? null,
                        'dueDate' => $dueDate,
                        'notes' => $a['notes'] ?? '',
                        'items' => $a['lineItems'] ?? [],
                    ]);
                },
            ],
            'update_invoice_status' => [
                'description' => 'Update invoice status (e.g., mark as paid).',
                'inputSchema' => $this->obj([
                    'id' => $this->str('Invoice ID (UUID, required)'),
                    'status' => $this->str('New status (required)', ['draft', 'sent', 'paid', 'overdue', 'cancelled']),
                ], ['id', 'status']),
                'handler' => fn(array $a) => $this->apiCall('invoices.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', ['status' => $a['status'] ?? '']),
            ],
        ];
    }

    // ---------------- ADVANCED INVOICES ----------------
    private function advancedInvoiceTools(): array {
        $companyHeader = $this->obj([
            'logoUrl' => $this->str('Company logo URL'), 'companyName' => $this->str('Company name'),
            'companyEmail' => $this->str('Company email'), 'companyPhone' => $this->str('Company phone'),
            'companyAddress' => $this->str('Company address'),
        ]);
        $customer = $this->obj([
            'name' => $this->str('Customer name'), 'email' => $this->str('Customer email'),
            'phone' => $this->str('Customer phone'), 'propertyAddress' => $this->str('Property/billing address'),
            'customerId' => $this->str('Customer ID/reference'),
            'contractFrom' => $this->str('Contract start (YYYY-MM-DD)'), 'contractTo' => $this->str('Contract end (YYYY-MM-DD)'),
        ]);
        $lineItems = $this->arr('Line items', $this->obj([
            'date' => $this->str('Item date (YYYY-MM-DD)'), 'reference' => $this->str('Reference code'),
            'description' => $this->str('Item description'), 'amount' => $this->num('Item amount'),
        ]));
        $paymentDetails = $this->obj([
            'bankName' => $this->str('Bank name'), 'accountNumber' => $this->str('Account number'),
            'branchCode' => $this->str('Branch code'), 'paymentReference' => $this->str('Payment reference'),
            'dueDate' => $this->str('Payment due date (YYYY-MM-DD)'),
        ]);
        $customFields = $this->arr('Custom fields', $this->obj([
            'label' => $this->str('Field label'), 'value' => $this->str('Field value'),
            'section' => $this->str('Section'), 'type' => $this->str('Field type', ['text', 'number', 'date', 'textarea']),
            'showOnPrint' => $this->bool('Show on print'),
        ]));

        return [
            'list_advanced_invoices' => [
                'description' => 'Get all advanced invoices with company header, customer, line items, and payment details.',
                'inputSchema' => $this->obj([]),
                'handler' => fn(array $a) => $this->apiCall('advanced-invoices.php'),
            ],
            'get_advanced_invoice' => [
                'description' => 'Get a specific advanced invoice by ID with all details.',
                'inputSchema' => $this->obj(['id' => $this->str('The advanced invoice ID (UUID)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('advanced-invoices.php?id=' . rawurlencode($a['id'] ?? '')),
            ],
            'create_advanced_invoice' => [
                'description' => 'Create a new advanced invoice with custom company header, customer details, line items, and payment information.',
                'inputSchema' => $this->obj([
                    'invoiceDate' => $this->str('Invoice date (YYYY-MM-DD, default: today)'),
                    'companyHeader' => $companyHeader,
                    'customer' => $customer,
                    'lineItems' => $lineItems,
                    'paymentDetails' => $paymentDetails,
                    'currency' => $this->str('Currency code (ZAR, USD, EUR, GBP - default: ZAR)'),
                    'notes' => $this->str('Invoice notes'),
                    'footerText' => $this->str('Footer text'),
                    'customFields' => $customFields,
                ], ['companyHeader', 'customer', 'lineItems', 'paymentDetails']),
                'handler' => fn(array $a) => $this->apiCall('advanced-invoices.php', 'POST', [
                    'invoiceDate' => $a['invoiceDate'] ?? date('Y-m-d'),
                    'companyHeader' => $a['companyHeader'] ?? [],
                    'customer' => $a['customer'] ?? [],
                    'lineItems' => $a['lineItems'] ?? [],
                    'paymentDetails' => $a['paymentDetails'] ?? [],
                    'currency' => $a['currency'] ?? 'ZAR',
                    'notes' => $a['notes'] ?? '',
                    'footerText' => $a['footerText'] ?? '',
                    'customFields' => $a['customFields'] ?? [],
                ]),
            ],
            'update_advanced_invoice' => [
                'description' => 'Update an existing advanced invoice.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('The invoice ID (UUID, required)'),
                    'invoiceDate' => $this->str('New invoice date'),
                    'companyHeader' => $companyHeader, 'customer' => $customer, 'lineItems' => $lineItems,
                    'paymentDetails' => $paymentDetails, 'currency' => $this->str('Currency'),
                    'notes' => $this->str('Notes'), 'footerText' => $this->str('Footer text'),
                    'customFields' => $customFields,
                ], ['id']),
                'handler' => function(array $a) {
                    $body = [];
                    foreach (['invoiceDate', 'companyHeader', 'customer', 'lineItems', 'paymentDetails', 'currency', 'notes', 'footerText', 'customFields'] as $f) {
                        if (array_key_exists($f, $a)) $body[$f] = $a[$f];
                    }
                    return $this->apiCall('advanced-invoices.php?id=' . rawurlencode($a['id'] ?? ''), 'PUT', $body);
                },
            ],
            'delete_advanced_invoice' => [
                'description' => 'Delete an advanced invoice.',
                'inputSchema' => $this->obj(['id' => $this->str('The invoice ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('advanced-invoices.php?id=' . rawurlencode($a['id'] ?? ''), 'DELETE'),
            ],
        ];
    }

    // ---------------- KNOWLEDGE BASE ----------------
    private function knowledgeBaseTools(): array {
        return [
            'list_kb_folders' => [
                'description' => 'Get all folders in the knowledge base.',
                'inputSchema' => $this->obj(['parentId' => $this->str('Filter by parent folder ID')]),
                'handler' => function(array $a) {
                    $q = ['action' => 'list_folders'];
                    if (!empty($a['parentId'])) $q['parentId'] = $a['parentId'];
                    return $this->apiCall('knowledge-base.php', 'GET', null, $q);
                },
            ],
            'create_kb_folder' => [
                'description' => 'Create a new folder in the knowledge base.',
                'inputSchema' => $this->obj([
                    'name' => $this->str('Folder name (required, URL-safe characters only)'),
                    'parentId' => $this->str('Parent folder ID (optional)'),
                ], ['name']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'POST', [
                    'name' => $a['name'] ?? '',
                    'parentId' => $a['parentId'] ?? null,
                ], ['action' => 'create_folder']),
            ],
            'update_kb_folder' => [
                'description' => 'Rename a knowledge base folder.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('Folder ID (UUID, required)'),
                    'name' => $this->str('New folder name'),
                ], ['id', 'name']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'PUT', ['id' => $a['id'] ?? '', 'name' => $a['name'] ?? ''], ['action' => 'update_folder']),
            ],
            'delete_kb_folder' => [
                'description' => 'Delete a knowledge base folder (must be empty).',
                'inputSchema' => $this->obj(['id' => $this->str('Folder ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'DELETE', ['id' => $a['id'] ?? ''], ['action' => 'delete_folder']),
            ],
            'list_kb_files' => [
                'description' => 'Get all files in a knowledge base folder.',
                'inputSchema' => $this->obj(['folderId' => $this->str('Folder ID (required)')], ['folderId']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'GET', null, ['action' => 'list_files', 'folderId' => $a['folderId'] ?? '']),
            ],
            'upload_kb_file' => [
                'description' => 'Upload a new file to the knowledge base. Requires file content as base64 string.',
                'inputSchema' => $this->obj([
                    'folderId' => $this->str('Target folder ID (required)'),
                    'filename' => $this->str('File name with extension (required)'),
                    'content' => $this->str('File content as base64 string (required)'),
                ], ['folderId', 'filename', 'content']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'POST', [
                    'content' => $a['content'] ?? '',
                    'folderId' => $a['folderId'] ?? '',
                    'name' => $a['filename'] ?? '',
                ], ['action' => 'upload_file']),
            ],
            'get_kb_file' => [
                'description' => 'Get a knowledge base file with content.',
                'inputSchema' => $this->obj(['id' => $this->str('File ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'GET', null, ['action' => 'get_file', 'id' => $a['id'] ?? '']),
            ],
            'update_kb_file' => [
                'description' => 'Update a knowledge base file (rename or update content).',
                'inputSchema' => $this->obj([
                    'id' => $this->str('File ID (UUID, required)'),
                    'name' => $this->str('New file name'),
                    'content' => $this->str('New file content as base64'),
                ], ['id']),
                'handler' => function(array $a) {
                    $body = [];
                    if (!empty($a['name'])) $body['name'] = $a['name'];
                    if (!empty($a['content'])) $body['content'] = $a['content'];
                    return $this->apiCall('knowledge-base.php', 'PUT', $body, ['action' => 'update_file', 'id' => $a['id'] ?? '']);
                },
            ],
            'delete_kb_file' => [
                'description' => 'Delete a knowledge base file.',
                'inputSchema' => $this->obj(['id' => $this->str('File ID (UUID, required)')], ['id']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'DELETE', ['id' => $a['id'] ?? ''], ['action' => 'delete_file']),
            ],
            'move_kb_file' => [
                'description' => 'Move a knowledge base file to another folder.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('File ID (UUID, required)'),
                    'targetFolderId' => $this->str('Target folder ID (required)'),
                ], ['id', 'targetFolderId']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'POST', ['targetFolderId' => $a['targetFolderId'] ?? ''], ['action' => 'move_file', 'id' => $a['id'] ?? '']),
            ],
            'search_knowledge_base' => [
                'description' => 'Search for files and folders in the knowledge base.',
                'inputSchema' => $this->obj(['query' => $this->str('Search query (required)')], ['query']),
                'handler' => fn(array $a) => $this->apiCall('knowledge-base.php', 'GET', null, ['action' => 'search', 'q' => $a['query'] ?? '']),
            ],
        ];
    }

    // ---------------- KB TEXT (base64-free helpers) ----------------
    private function kbTextTools(): array {
        $uploadText = function(string $folderId, string $filename, string $content) {
            if (!$this->validKbExtension($filename)) {
                return ['success' => false, 'error' => 'Invalid file extension. Only .md and .xml files are allowed.'];
            }
            return $this->apiCall('knowledge-base.php', 'POST', [
                'folderId' => $folderId,
                'name' => $filename,
                'content' => base64_encode($content),
            ], ['action' => 'upload_file']);
        };

        return [
            'upload_kb_file_text' => [
                'description' => 'Upload a text file to knowledge base without base64 encoding. Automatically handles encoding. Supports .md and .xml files.',
                'inputSchema' => $this->obj([
                    'folderId' => $this->str('Target folder ID (UUID, required)'),
                    'filename' => $this->str('File name with extension .md or .xml (required)'),
                    'content' => $this->str('File content as plain text (markdown or XML)'),
                ], ['folderId', 'filename', 'content']),
                'handler' => fn(array $a) => $uploadText($a['folderId'] ?? '', $a['filename'] ?? '', $a['content'] ?? ''),
            ],
            'get_kb_file_text' => [
                'description' => 'Get a knowledge base file with automatically decoded text content. Returns plain text instead of base64.',
                'inputSchema' => $this->obj(['id' => $this->str('File ID (UUID, required)')], ['id']),
                'handler' => function(array $a) {
                    $r = $this->apiCall('knowledge-base.php', 'GET', null, ['action' => 'get_file', 'id' => $a['id'] ?? '']);
                    if (($r['success'] ?? false) && !empty($r['data']['content'])) {
                        $decoded = base64_decode($r['data']['content'], true);
                        if ($decoded !== false) {
                            $r['data']['textContent'] = $decoded;
                        } else {
                            $r['warning'] = 'Failed to decode content as base64';
                        }
                    }
                    return $r;
                },
            ],
            'update_kb_file_text' => [
                'description' => 'Update a knowledge base file with plain text content. Automatically base64 encodes the content.',
                'inputSchema' => $this->obj([
                    'id' => $this->str('File ID (UUID, required)'),
                    'name' => $this->str('New file name (optional, keep .md or .xml extension)'),
                    'content' => $this->str('New file content as plain text'),
                ], ['id']),
                'handler' => function(array $a) {
                    $body = [];
                    if (!empty($a['name'])) {
                        if (!$this->validKbExtension($a['name'])) {
                            return ['success' => false, 'error' => 'Invalid file extension. Only .md and .xml files are allowed.'];
                        }
                        $body['name'] = $a['name'];
                    }
                    if (isset($a['content'])) $body['content'] = base64_encode($a['content']);
                    return $this->apiCall('knowledge-base.php', 'PUT', $body, ['action' => 'update_file', 'id' => $a['id'] ?? '']);
                },
            ],
            'create_kb_md_file' => [
                'description' => 'Create a new Markdown file with proper formatting and optional YAML frontmatter. Perfect for documentation.',
                'inputSchema' => $this->obj([
                    'folderId' => $this->str('Target folder ID (UUID, required)'),
                    'title' => $this->str('Document title (required, used for H1 and filename)'),
                    'body' => $this->str('Markdown content/body (required)'),
                    'tags' => $this->arr('Optional tags array for frontmatter'),
                    'author' => $this->str('Optional author name for frontmatter'),
                    'customFilename' => $this->str('Optional custom filename (without .md extension)'),
                ], ['folderId', 'title', 'body']),
                'handler' => function(array $a) use ($uploadText) {
                    $filename = !empty($a['customFilename']) ? $a['customFilename'] . '.md' : $this->safeFilename($a['title'] ?? 'file', 'md');
                    $ts = date('c');
                    $tags = $a['tags'] ?? [];
                    $fm = "---\ntitle: \"" . str_replace('"', '\\"', $a['title'] ?? '') . "\"\ndate: {$ts}\n";
                    if (!empty($a['author'])) $fm .= 'author: "' . str_replace('"', '\\"', $a['author']) . "\"\n";
                    if (!empty($tags)) {
                        $safeTags = implode(', ', array_map(fn($t) => '"' . str_replace('"', '\\"', $t) . '"', $tags));
                        $fm .= "tags: [{$safeTags}]\n";
                    }
                    $fm .= "---\n\n";
                    $content = $fm . '# ' . ($a['title'] ?? '') . "\n\n" . ($a['body'] ?? '');
                    $r = $uploadText($a['folderId'] ?? '', $filename, $content);
                    if ($r['success'] ?? false) {
                        $r['formattedContent'] = $content;
                        $r['generatedFilename'] = $filename;
                    }
                    return $r;
                },
            ],
            'create_kb_xml_file' => [
                'description' => 'Create a new XML file with proper formatting and optional metadata. Useful for structured data.',
                'inputSchema' => $this->obj([
                    'folderId' => $this->str('Target folder ID (UUID, required)'),
                    'title' => $this->str('Document title (required, used for filename)'),
                    'content' => $this->str('XML content/body (required)'),
                    'tags' => $this->arr('Optional tags array for metadata'),
                    'author' => $this->str('Optional author name for metadata'),
                    'customFilename' => $this->str('Optional custom filename (without .xml extension)'),
                ], ['folderId', 'title', 'content']),
                'handler' => function(array $a) use ($uploadText) {
                    $filename = !empty($a['customFilename']) ? $a['customFilename'] . '.xml' : $this->safeFilename($a['title'] ?? 'file', 'xml');
                    $ts = date('c');
                    $tagXml = '';
                    foreach (($a['tags'] ?? []) as $t) {
                        $tagXml .= '    <tag>' . $this->xmlEscape($t) . "</tag>\n";
                    }
                    $authorXml = !empty($a['author']) ? '<author>' . $this->xmlEscape($a['author']) . '</author>' : '';
                    $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                        . "<document>\n  <metadata>\n"
                        . '    <title>' . $this->xmlEscape($a['title'] ?? '') . "</title>\n"
                        . "    <createdAt>{$ts}</createdAt>\n"
                        . '    ' . $authorXml . "\n"
                        . "    <tags>\n" . ($tagXml !== '' ? $tagXml : '    ') . "    </tags>\n"
                        . "  </metadata>\n  <content>\n"
                        . $this->xmlEscape($a['content'] ?? '') . "\n"
                        . "  </content>\n</document>";
                    $r = $uploadText($a['folderId'] ?? '', $filename, $content);
                    if ($r['success'] ?? false) {
                        $r['formattedContent'] = $content;
                        $r['generatedFilename'] = $filename;
                    }
                    return $r;
                },
            ],
            'batch_upload_kb_text' => [
                'description' => 'Upload multiple text files to knowledge base in one call. Automatically handles base64 encoding. Retries failed uploads up to 2 times for name conflicts.',
                'inputSchema' => $this->obj([
                    'folderId' => $this->str('Target folder ID for all files (UUID, required)'),
                    'files' => $this->arr('Array of files to upload (required)', $this->obj([
                        'filename' => $this->str('File name with .md or .xml extension'),
                        'content' => $this->str('File content as plain text'),
                    ], ['filename', 'content'])),
                ], ['folderId', 'files']),
                'handler' => function(array $a) use ($uploadText) {
                    $results = [];
                    $errors = [];
                    $successCount = 0;
                    $failCount = 0;
                    foreach (($a['files'] ?? []) as $file) {
                        $retries = 0;
                        $maxRetries = 2;
                        $success = false;
                        $lastError = null;
                        while ($retries <= $maxRetries && !$success) {
                            $r = $uploadText($a['folderId'] ?? '', $file['filename'] ?? '', $file['content'] ?? '');
                            if ($r['success'] ?? false) {
                                $success = true;
                                $successCount++;
                                $results[] = ['filename' => $file['filename'] ?? '', 'success' => true, 'file' => $r['data'] ?? ($r['file'] ?? null), 'retries' => $retries];
                            } else {
                                $lastError = $r['error'] ?? ($r['message'] ?? 'Upload failed');
                                $retries++;
                            }
                        }
                        if (!$success) {
                            $failCount++;
                            $errors[] = ['filename' => $file['filename'] ?? '', 'error' => $lastError, 'retries' => $retries];
                        }
                    }
                    $out = [
                        'success' => $failCount === 0,
                        'summary' => ['total' => count($a['files'] ?? []), 'successful' => $successCount, 'failed' => $failCount],
                        'results' => $results,
                    ];
                    if (!empty($errors)) $out['errors'] = $errors;
                    return $out;
                },
            ],
        ];
    }

    // ---------------- SYSTEM ----------------
    private function systemTools(): array {
        return [
            'test_connection' => [
                'description' => 'Test the connection to the OpenPlan.work API server.',
                'inputSchema' => $this->obj([]),
                'handler' => function(array $a) {
                    $r = $this->apiCall('auth.php?action=status');
                    if ($r['success'] ?? false) {
                        return ['success' => true, 'message' => 'Connection to OpenPlan.work API successful', 'server' => 'openplan-work', 'apiUrl' => $this->apiBase];
                    }
                    return ['success' => false, 'error' => 'Failed to connect to OpenPlan.work API', 'detail' => $r];
                },
            ],
            'get_system_status' => [
                'description' => 'Get system status including storage usage and configuration.',
                'inputSchema' => $this->obj([]),
                'handler' => function(array $a) {
                    $auth = $this->apiCall('auth.php?action=status');
                    $cfg = $this->apiCall('settings.php');
                    return ['success' => true, 'authenticated' => (bool)($auth['success'] ?? false), 'config' => $cfg['data'] ?? null, 'apiUrl' => $this->apiBase];
                },
            ],
            'search_all' => [
                'description' => 'Search across all data (todos, tasks, projects, clients, notes).',
                'inputSchema' => $this->obj(['query' => $this->str('Search query (required)')], ['query']),
                'handler' => function(array $a) {
                    $q = strtolower(trim((string)($a['query'] ?? '')));
                    if ($q === '') return ['success' => false, 'error' => 'query is required'];
                    $hit = fn($h) => $h !== null && $h !== '' && stripos((string)$h, $q) !== false;

                    $todos = $this->apiCall('todos.php');
                    $projects = $this->apiCall('projects.php');
                    $clients = $this->apiCall('clients.php');

                    $results = [
                        'todos' => array_values(array_filter($todos['data'] ?? [], fn($t) => $hit($t['title'] ?? '') || $hit($t['description'] ?? ''))),
                        'projects' => array_values(array_filter($projects['data'] ?? [], fn($p) => $hit($p['name'] ?? '') || $hit($p['description'] ?? ''))),
                        'clients' => array_values(array_filter($clients['data'] ?? [], fn($c) => $hit($c['name'] ?? '') || $hit($c['company'] ?? '') || $hit($c['email'] ?? ''))),
                    ];
                    return ['success' => true, 'query' => $a['query'] ?? '', 'results' => $results];
                },
            ],
        ];
    }
}
