<?php
/**
 * Function Executor
 *
 * Validates and executes AI function calls.
 * Routes to internal API endpoints for actual data operations.
 */
require_once __DIR__ . '/TasksAPI.php';

class FunctionExecutor {
    private Database $db;
    private array $config;
    private string $userId;
    private bool $debugMode;

    public function __construct(Database $db, array $config = []) {
        $this->db = $db;
        $this->config = $config;
        $this->userId = Auth::userId() ?? '';
        $this->debugMode = (
            (defined('DEBUG') && DEBUG) ||
            (getenv('LAZYMAN_DEBUG') === '1')
        );
    }

    /**
     * Validate function call before execution
     *
     * @param string $functionName Function to validate
     * @param array $parameters Parameters to validate
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateCall(string $functionName, array $parameters): array {
        $errors = [];

        switch ($functionName) {
            case 'create_task':
                // Validate required title
                if (empty($parameters['title'])) {
                    $errors[] = "Task title is required";
                }
                // Validate projectId if provided
                if (!empty($parameters['projectId'])) {
                    $project = $this->db->findById('projects', $parameters['projectId']);
                    if (!$project) {
                        $errors[] = "Project not found: {$parameters['projectId']}";
                    }
                }
                // Validate priority if provided
                if (!empty($parameters['priority']) && !in_array($parameters['priority'], ['low', 'medium', 'high', 'urgent'])) {
                    $errors[] = "Invalid priority value";
                }
                break;

            case 'update_task':
                if (empty($parameters['taskId']) && empty($parameters['title'])) {
                    $errors[] = "Either taskId or title is required";
                }
                if (!empty($parameters['status']) && !in_array($parameters['status'], ['todo', 'in_progress', 'done', 'backlog'])) {
                    $errors[] = "Invalid task status value";
                }
                if (!empty($parameters['priority']) && !in_array($parameters['priority'], ['low', 'medium', 'high', 'urgent'])) {
                    $errors[] = "Invalid task priority value";
                }
                break;

            case 'update_task_status':
                if (empty($parameters['taskId']) && empty($parameters['title'])) {
                    $errors[] = "Either taskId or title is required";
                }
                if (empty($parameters['status'])) {
                    $errors[] = "Task status is required";
                } elseif (!in_array($parameters['status'], ['todo', 'in_progress', 'done', 'backlog'])) {
                    $errors[] = "Invalid task status value";
                }
                break;

            case 'complete_task':
            case 'delete_task':
                if (empty($parameters['taskId']) && empty($parameters['title'])) {
                    $errors[] = "Either taskId or title is required";
                }
                break;

            case 'create_project':
                if (empty($parameters['name'])) {
                    $errors[] = "Project name is required";
                }
                // Validate clientId if provided
                if (!empty($parameters['clientId'])) {
                    $client = $this->db->findById('clients', $parameters['clientId']);
                    if (!$client) {
                        $errors[] = "Client not found: {$parameters['clientId']}";
                    }
                }
                break;

            case 'create_client':
                if (empty($parameters['name'])) {
                    $errors[] = "Client name is required";
                }
                if (empty($parameters['email'])) {
                    $errors[] = "Client email is required";
                }
                // Validate email format
                if (!empty($parameters['email']) && !filter_var($parameters['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email format";
                }
                break;

            case 'create_invoice':
            case 'create_advanced_invoice':
                if (empty($parameters['clientId'])) {
                    $errors[] = "Client ID is required for invoice";
                } else {
                    $client = $this->db->findById('clients', $parameters['clientId']);
                    if (!$client) {
                        $errors[] = "Client not found: {$parameters['clientId']}";
                    }
                }
                if (empty($parameters['dueDate'])) {
                    $errors[] = "Due date is required";
                }
                if (empty($parameters['lineItems']) || !is_array($parameters['lineItems'])) {
                    $errors[] = "Line items are required and must be an array";
                } else {
                    foreach ($parameters['lineItems'] as $index => $item) {
                        if (empty($item['description'])) {
                            $errors[] = "Line item " . ($index + 1) . " missing description";
                        }
                        if (!isset($item['unitPrice']) || !is_numeric($item['unitPrice'])) {
                            $errors[] = "Line item " . ($index + 1) . " missing or invalid unit price";
                        }
                    }
                }
                break;

            case 'create_note':
                if (empty($parameters['title'])) {
                    $errors[] = "Note title is required";
                }
                if (empty($parameters['content'])) {
                    $errors[] = "Note content is required";
                }
                if (!empty($parameters['content']) && strlen($parameters['content']) > 10000) {
                    $errors[] = "Note content exceeds maximum length of 10,000 characters";
                }
                break;

            case 'create_transaction':
                if (empty($parameters['type'])) {
                    $errors[] = "Transaction type is required";
                } elseif (!in_array($parameters['type'], ['expense', 'revenue'])) {
                    $errors[] = "Transaction type must be 'expense' or 'revenue'";
                }
                if (empty($parameters['description'])) {
                    $errors[] = "Transaction description is required";
                }
                if (!isset($parameters['amount']) || !is_numeric($parameters['amount'])) {
                    $errors[] = "Transaction amount is required and must be numeric";
                }
                break;

            case 'search_knowledge_base':
                // Query can be empty (meaning 'list all files')
                // if (empty($parameters['query'])) {
                //    $errors[] = "Search query is required";
                // }
                break;

            case 'list_projects':
                // No parameters required, optional status filter
                if (!empty($parameters['status']) && !in_array($parameters['status'], ['active', 'completed', 'on-hold', 'cancelled'])) {
                    $errors[] = "Invalid status filter";
                }
                break;

            case 'list_tasks':
                if (!empty($parameters['status']) && !in_array($parameters['status'], ['todo', 'in_progress', 'done', 'backlog'])) {
                    $errors[] = "Invalid task status filter";
                }
                if (!empty($parameters['priority']) && !in_array($parameters['priority'], ['low', 'medium', 'high', 'urgent'])) {
                    $errors[] = "Invalid task priority filter";
                }
                if (!empty($parameters['dueFilter']) && !in_array($parameters['dueFilter'], ['today', 'overdue', 'upcoming', 'all'])) {
                    $errors[] = "Invalid dueFilter value";
                }
                if (isset($parameters['limit']) && (!is_numeric($parameters['limit']) || (int)$parameters['limit'] < 1 || (int)$parameters['limit'] > 200)) {
                    $errors[] = "Limit must be between 1 and 200";
                }
                break;

            case 'get_project_tasks':
                if (empty($parameters['projectId'])) {
                    $errors[] = "Project ID is required";
                } else {
                    $project = $this->db->findById('projects', $parameters['projectId']);
                    if (!$project) {
                        $errors[] = "Project not found: {$parameters['projectId']}";
                    }
                }
                break;

            case 'list_clients':
                // No parameters required
                break;

            case 'create_habit':
                if (empty($parameters['name'])) {
                    $errors[] = "Habit name is required";
                }
                if (!empty($parameters['frequency']) && !in_array($parameters['frequency'], ['daily', 'weekly', 'custom'])) {
                    $errors[] = "Invalid frequency value";
                }
                break;

            case 'list_habits':
                // No parameters required
                break;

            case 'create_inventory_item':
                if (empty($parameters['name'])) {
                    $errors[] = "Item name is required";
                }
                if (empty($parameters['price'])) {
                    $errors[] = "Item price is required";
                }
                break;

            case 'list_inventory':
                // Optional parameters only
                break;

            case 'set_pomodoro_timer':
                // All parameters optional, will use defaults
                break;

            case 'create_advanced_invoice':
                if (empty($parameters['clientId'])) {
                    $errors[] = "Client ID is required";
                }
                if (empty($parameters['lineItems']) || !is_array($parameters['lineItems'])) {
                    $errors[] = "Line items array is required";
                }
                break;

            default:
                $errors[] = "Unknown function: {$functionName}";
                break;
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Execute validated function call
     *
     * @param string $functionName Function to execute
     * @param array $parameters Function parameters
     * @return array Execution result
     * @throws ValidationException
     */
    public function execute(string $functionName, array $parameters): array {
        // Validate first
        $validation = $this->validateCall($functionName, $parameters);
        if (!$validation['valid']) {
            throw new ValidationException(implode(', ', $validation['errors']));
        }

        // Route to appropriate handler
        return $this->routeToHandler($functionName, $parameters);
    }

    /**
     * Route function call to appropriate handler
     *
     * @param string $functionName
     * @param array $parameters
     * @return array
     */
    private function routeToHandler(string $functionName, array $parameters): array {
        switch ($functionName) {
            case 'create_task':
                return $this->createTask($parameters);
            case 'update_task':
                return $this->updateTask($parameters);
            case 'update_task_status':
                return $this->updateTaskStatus($parameters);
            case 'complete_task':
                return $this->completeTask($parameters);
            case 'delete_task':
                return $this->deleteTask($parameters);
            case 'create_project':
                return $this->createProject($parameters);
            case 'create_client':
                return $this->createClient($parameters);
            case 'list_clients':
                return $this->listClients($parameters);
            case 'create_invoice':
                return $this->createInvoice($parameters);
            case 'create_advanced_invoice':
                return $this->createAdvancedInvoice($parameters);
            case 'create_note':
                return $this->createNote($parameters);
            case 'search_knowledge_base':
                return $this->searchKnowledgeBase($parameters);
            case 'create_transaction':
                return $this->createTransaction($parameters);
            case 'list_projects':
                return $this->listProjects($parameters);
            case 'list_tasks':
                return $this->listTasks($parameters);
            case 'get_project_tasks':
                return $this->getProjectTasks($parameters);
            case 'create_habit':
                return $this->createHabit($parameters);
            case 'list_habits':
                return $this->listHabits($parameters);
            case 'create_inventory_item':
                return $this->createInventoryItem($parameters);
            case 'list_inventory':
                return $this->listInventory($parameters);
            case 'set_pomodoro_timer':
                return $this->setPomodoroTimer($parameters);
            default:
                throw new ValidationException("Unknown function: {$functionName}");
        }
    }

    /**
     * Create a new task
     */
    private function createTask(array $params): array {
        $projects = $this->db->load('projects') ?? [];

        // If no projectId specified, try to find or create a default project
        if (empty($params['projectId'])) {
            // Look for an "Inbox" or "General" project
            foreach ($projects as $p) {
                if (in_array(strtolower($p['name']), ['inbox', 'general', 'default'])) {
                    $params['projectId'] = $p['id'];
                    break;
                }
            }
            // If still no project, create one
            if (empty($params['projectId'])) {
                $inboxProject = [
                    'id' => $this->db->generateId(),
                    'name' => 'Inbox',
                    'description' => 'Default project for unassigned tasks',
                    'status' => 'active',
                    'color' => '#6B7280',
                    'createdAt' => date('c'),
                    'updatedAt' => date('c'),
                    'tasks' => []
                ];
                $projects[] = $inboxProject;
                $this->db->save('projects', $projects);
                $params['projectId'] = $inboxProject['id'];
            }
        }

        // Create task
        $task = [
            'id' => $this->db->generateId(),
            'title' => $params['title'],
            'description' => $params['description'] ?? '',
            'status' => 'backlog',
            'priority' => $params['priority'] ?? 'medium',
            'dueDate' => $params['dueDate'] ?? null,
            'estimatedMinutes' => (int)($params['estimatedMinutes'] ?? 60),
            'actualMinutes' => 0,
            'subtasks' => [],
            'timeEntries' => [],
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        // Add subtasks if provided
        if (!empty($params['subtasks']) && is_array($params['subtasks'])) {
            foreach ($params['subtasks'] as $subtask) {
                $task['subtasks'][] = [
                    'title' => $subtask['title'] ?? '',
                    'description' => $subtask['description'] ?? '',
                    'completed' => false,
                    'estimatedMinutes' => (int)($subtask['estimatedMinutes'] ?? 30)
                ];
            }
        }

        // Add task to project
        $projectFound = false;
        foreach ($projects as &$p) {
            if ($p['id'] === $params['projectId']) {
                $p['tasks'][] = $task;
                $p['updatedAt'] = date('c');
                $projectFound = true;
                break;
            }
        }

        if (!$projectFound) {
            throw new ValidationException("Project not found with ID: " . $params['projectId']);
        }

        $this->db->save('projects', $projects);

        return [
            'success' => true,
            'task' => $task,
            'projectId' => $params['projectId'],
            'message' => "Created task: {$task['title']}"
        ];
    }

    /**
     * Create a new project
     */
    private function createProject(array $params): array {
        $projects = $this->db->load('projects') ?? [];

        $project = [
            'id' => $this->db->generateId(),
            'name' => $params['name'],
            'description' => $params['description'] ?? '',
            'clientId' => $params['clientId'] ?? null,
            'status' => 'active',
            'color' => $params['color'] ?? $this->randomColor(),
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
            'tasks' => []
        ];

        $projects[] = $project;
        $this->db->save('projects', $projects);

        return [
            'success' => true,
            'project' => $project,
            'message' => "Created project: {$project['name']}"
        ];
    }

    /**
     * Create a new client
     */
    private function createClient(array $params): array {
        $clients = $this->db->load('clients') ?? [];

        // Check for duplicate email - if exists, return existing client (Idempotent)
        foreach ($clients as $c) {
            if (strtolower($c['email']) === strtolower($params['email'])) {
                return [
                    'success' => true,
                    'client' => $c,
                    'message' => "Using existing client: {$c['name']} (ID: {$c['id']})"
                ];
            }
        }

        $client = [
            'id' => $this->db->generateId(),
            'name' => $params['name'],
            'email' => $params['email'],
            'phone' => $params['phone'] ?? '',
            'company' => $params['company'] ?? '',
            'address' => $params['address'] ?? [
                'street' => '',
                'city' => '',
                'state' => '',
                'zip' => '',
                'country' => ''
            ],
            'notes' => $params['notes'] ?? '',
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        $clients[] = $client;
        $this->db->save('clients', $clients);

        return [
            'success' => true,
            'client' => $client,
            'message' => "Created client: {$client['name']}"
        ];
    }

    /**
     * List all clients
     */
    private function listClients(array $params): array {
        $clients = $this->db->load('clients') ?? [];

        return [
            'success' => true,
            'clients' => array_map(function($c) {
                return [
                    'id' => $c['id'],
                    'name' => $c['name'],
                    'email' => $c['email'],
                    'company' => $c['company'] ?? ''
                ];
            }, $clients),
            'count' => count($clients)
        ];
    }

    /**
     * Create a new invoice
     */
    private function createInvoice(array $params): array {
        $invoices = $this->db->load('invoices') ?? [];
        $clients = $this->db->load('clients') ?? [];

        // Get client details
        $client = null;
        foreach ($clients as $c) {
            if ($c['id'] === $params['clientId']) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            throw new ValidationException("Client not found");
        }

        // Generate invoice number
        $year = date('Y');
        $invoiceNumber = $this->generateInvoiceNumber($invoices, $year);

        // Calculate line item totals
        $lineItems = [];
        $subtotal = 0;
        foreach ($params['lineItems'] as $item) {
            $quantity = (int)($item['quantity'] ?? 1);
            $unitPrice = (float)$item['unitPrice'];
            $total = $quantity * $unitPrice;
            $subtotal += $total;

            $lineItems[] = [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'total' => $total
            ];
        }

        // Calculate tax
        $taxRate = (float)($this->config['taxRate'] ?? 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $invoice = [
            'id' => $this->db->generateId(),
            'invoiceNumber' => $invoiceNumber,
            'clientId' => $params['clientId'],
            'projectId' => $params['projectId'] ?? null,
            'lineItems' => $lineItems,
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
            'total' => $total,
            'currency' => $this->config['currency'] ?? 'USD',
            'status' => 'draft',
            'dueDate' => $params['dueDate'],
            'issueDate' => date('c'),
            'notes' => $params['notes'] ?? '',
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        $invoices[] = $invoice;
        $this->db->save('invoices', $invoices);

        return [
            'success' => true,
            'invoice' => $invoice,
            'client' => [
                'name' => $client['name'],
                'company' => $client['company'] ?? ''
            ],
            'message' => "Created invoice {$invoiceNumber} for {$total}"
        ];
    }

    /**
     * Create an advanced invoice with custom fields and templates
     */
    private function createAdvancedInvoice(array $params): array {
        $invoices = $this->db->load('invoices') ?? [];
        $clients = $this->db->load('clients') ?? [];

        // Get client details
        $client = null;
        foreach ($clients as $c) {
            if ($c['id'] === $params['clientId']) {
                $client = $c;
                break;
            }
        }

        if (!$client) {
            throw new ValidationException("Client not found");
        }

        // Generate invoice number
        $year = date('Y');
        $invoiceNumber = $this->generateInvoiceNumber($invoices, $year);

        // Calculate line item totals with per-item tax support
        $lineItems = [];
        $subtotal = 0;
        foreach ($params['lineItems'] as $item) {
            $quantity = (int)($item['quantity'] ?? 1);
            $unitPrice = (float)$item['unitPrice'];
            $itemTaxRate = (float)($item['taxRate'] ?? 0);
            $total = $quantity * $unitPrice;
            $itemTax = $total * ($itemTaxRate / 100);
            $subtotal += $total;

            $lineItems[] = [
                'description' => $item['description'],
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'taxRate' => $itemTaxRate,
                'tax' => $itemTax,
                'total' => $total + $itemTax
            ];
        }

        // Calculate total tax
        $taxRate = (float)($this->config['taxRate'] ?? 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $invoice = [
            'id' => $this->db->generateId(),
            'invoiceNumber' => $invoiceNumber,
            'clientId' => $params['clientId'],
            'projectId' => $params['projectId'] ?? null,
            'lineItems' => $lineItems,
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
            'total' => $total,
            'currency' => $this->config['currency'] ?? 'USD',
            'status' => 'draft',
            'invoiceDate' => $params['invoiceDate'] ?? date('c'),
            'dueDate' => $params['dueDate'],
            'template' => $params['template'] ?? 'standard',
            'customFields' => $params['customFields'] ?? [],
            'notes' => $params['notes'] ?? '',
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        $invoices[] = $invoice;
        $this->db->save('invoices', $invoices);

        return [
            'success' => true,
            'invoice' => $invoice,
            'client' => [
                'name' => $client['name'],
                'company' => $client['company'] ?? ''
            ],
            'message' => "Created advanced invoice {$invoiceNumber} for {$total}"
        ];
    }

    /**
     * Create a new note
     */
    private function createNote(array $params): array {
        $notes = $this->db->load('notes') ?? [];

        $note = [
            'id' => $this->db->generateId(),
            'title' => $params['title'],
            'content' => $params['content'],
            'tags' => $params['tags'] ?? [],
            'color' => $params['color'] ?? '#fef3c7',
            'isPinned' => $params['isPinned'] ?? false,
            'isFavorite' => false,
            'linkedEntityType' => null,
            'linkedEntityId' => null,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        $notes[] = $note;
        $this->db->save('notes', $notes);

        return [
            'success' => true,
            'note' => $note,
            'message' => "Created note: {$note['title']}"
        ];
    }

    /**
     * Search knowledge base
     */
    private function searchKnowledgeBase(array $params): array {
        $query = strtolower(trim($params['query'] ?? ''));
        $folderId = $params['folderId'] ?? null;

        // if (empty($query)) {
        //    return [
        //        'success' => false,
        //        'results' => [],
        //        'message' => 'Search query cannot be empty'
        //    ];
        // }
        
        // Handle wildcard or empty query
        if ($query === '*') $query = '';

        // Load knowledge base data (uses 'knowledge-base' collection name)
        $kbData = $this->db->load('knowledge-base');

        // Debug logs only when explicitly enabled.
        if ($this->debugMode) {
            error_log("KB Search: Query='{$query}', KB Data exists=" . (!empty($kbData) ? 'yes' : 'no'));
            if ($kbData) {
                error_log("KB Search: Folders=" . count($kbData['folders'] ?? []) . ", Files=" . count($kbData['files'] ?? []));
            }
        }

        if (!$kbData || (empty($kbData['folders']) && empty($kbData['files']))) {
            return [
                'success' => true,
                'results' => [],
                'message' => 'Knowledge base is empty'
            ];
        }

        $results = ['folders' => [], 'files' => []];

        // Search folders
        if (!empty($kbData['folders'])) {
            foreach ($kbData['folders'] as $folder) {
                // Skip if folderId specified and doesn't match
                if ($folderId && $folder['id'] !== $folderId) {
                    continue;
                }

                // Search in folder name
                if (strpos(strtolower($folder['name']), $query) !== false) {
                    $results['folders'][] = [
                        'id' => $folder['id'],
                        'name' => $folder['name'],
                        'type' => 'folder'
                    ];
                }
            }
        }

        // Search files
        if (!empty($kbData['files'])) {
            foreach ($kbData['files'] as $file) {
                // Skip if folderId specified and doesn't match
                if ($folderId && $file['folderId'] !== $folderId) {
                    continue;
                }

                // Search in file name
                $nameMatch = strpos(strtolower($file['name']), $query) !== false;

                // Search in file content (decode base64 if needed)
                $contentMatch = false;
                if (!empty($file['content'])) {
                    $content = $file['content'];
                    // If content is base64 encoded, decode it
                    if (base64_encode(base64_decode($content, true)) === $content) {
                        $content = base64_decode($content);
                    }
                    $contentMatch = strpos(strtolower($content), $query) !== false;
                }

                if ($nameMatch || $contentMatch) {
                    $results['files'][] = [
                        'id' => $file['id'],
                        'name' => $file['name'],
                        'folderId' => $file['folderId'],
                        'type' => $file['type'] ?? 'file',
                        'size' => $file['size'] ?? 0,
                        'createdAt' => $file['createdAt'] ?? null
                    ];
                }
            }
        }

        // Limit results to prevent context overflow (max 50 total)
        $totalResults = count($results['folders']) + count($results['files']);
        if ($totalResults > 50) {
            $results['files'] = array_slice($results['files'], 0, 50 - count($results['folders']));
        }

        // Build detailed message
        $items = [];
        if (!empty($results['folders'])) {
            foreach (array_slice($results['folders'], 0, 5) as $f) $items[] = "[Folder] " . $f['name'];
        }
        if (!empty($results['files'])) {
            foreach (array_slice($results['files'], 0, 10) as $f) $items[] = "[File] " . $f['name'];
        }
        $list = implode(', ', $items);
        if ($totalResults > count($items)) $list .= ", ... (and " . ($totalResults - count($items)) . " more)";
        
        $message = "Found {$totalResults} items: {$list}";

        return [
            'success' => true,
            'results' => $results,
            'query' => $query,
            'count' => $totalResults,
            'message' => $message
        ];
    }

    /**
     * Create a finance transaction
     */
    private function createTransaction(array $params): array {
        $transactions = $this->db->load('finance') ?? [];

        $transaction = [
            'id' => $this->db->generateId(),
            'type' => $params['type'],
            'category' => $params['category'] ?? 'General',
            'amount' => (float)$params['amount'],
            'currency' => $this->config['currency'] ?? 'USD',
            'date' => $params['date'] ?? date('c'),
            'description' => $params['description'],
            'projectId' => $params['projectId'] ?? null,
            'clientId' => $params['clientId'] ?? null,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];

        $transactions[] = $transaction;
        $this->db->save('finance', $transactions);

        $typeLabel = $transaction['type'] === 'expense' ? 'Expense' : 'Revenue';
        return [
            'success' => true,
            'transaction' => $transaction,
            'message' => "Recorded {$typeLabel}: {$transaction['amount']}"
        ];
    }

    /**
     * List projects with optional status filter
     */
    private function listProjects(array $params): array {
        $projects = $this->db->load('projects') ?? [];

        // Filter by status if specified
        if (!empty($params['status'])) {
            $projects = array_filter($projects, function($p) use ($params) {
                return $p['status'] === $params['status'];
            });
            $projects = array_values($projects); // Re-index
        }

        return [
            'success' => true,
            'projects' => array_map(function($p) {
                return [
                    'id' => $p['id'],
                    'name' => $p['name'],
                    'description' => $p['description'] ?? '',
                    'status' => $p['status'],
                    'taskCount' => count($p['tasks'] ?? []),
                    'color' => $p['color'] ?? '#6B7280'
                ];
            }, $projects),
            'count' => count($projects)
        ];
    }

    /**
     * Update existing task fields.
     */
    private function updateTask(array $params): array {
        $match = $this->resolveTaskReference($params);
        $tasksAPI = new TasksAPI($this->db, 'projects');
        $taskId = $match['task']['id'];

        $updatePayload = [];
        if (isset($params['newTitle'])) {
            $updatePayload['title'] = (string)$params['newTitle'];
        }
        if (array_key_exists('description', $params)) {
            $updatePayload['description'] = (string)$params['description'];
        }
        if (isset($params['status'])) {
            $updatePayload['status'] = normalizeTaskStatus((string)$params['status']);
        }
        if (isset($params['priority'])) {
            $updatePayload['priority'] = strtolower((string)$params['priority']);
        }
        if (array_key_exists('dueDate', $params)) {
            $updatePayload['dueDate'] = $params['dueDate'] ?: null;
        }
        if (isset($params['estimatedMinutes'])) {
            $updatePayload['estimatedMinutes'] = (int)$params['estimatedMinutes'];
        }
        if (isset($params['actualMinutes'])) {
            $updatePayload['actualMinutes'] = (int)$params['actualMinutes'];
        }

        if (empty($updatePayload)) {
            throw new ValidationException('No update fields provided');
        }

        $updatedTask = $tasksAPI->update($taskId, $updatePayload);
        if (!$updatedTask) {
            throw new ValidationException('Failed to update task');
        }

        return [
            'success' => true,
            'task' => $updatedTask,
            'project' => [
                'id' => $match['project']['id'] ?? '',
                'name' => $match['project']['name'] ?? 'Unknown Project'
            ],
            'message' => "Updated task: " . ($updatedTask['title'] ?? $match['task']['title'] ?? 'Task')
        ];
    }

    /**
     * Update task status.
     */
    private function updateTaskStatus(array $params): array {
        $match = $this->resolveTaskReference($params);
        $tasksAPI = new TasksAPI($this->db, 'projects');
        $taskId = $match['task']['id'];
        $status = normalizeTaskStatus((string)($params['status'] ?? 'todo'));

        $updatedTask = $tasksAPI->update($taskId, ['status' => $status]);
        if (!$updatedTask) {
            throw new ValidationException('Failed to update task status');
        }

        return [
            'success' => true,
            'task' => $updatedTask,
            'project' => [
                'id' => $match['project']['id'] ?? '',
                'name' => $match['project']['name'] ?? 'Unknown Project'
            ],
            'message' => "Updated task status: " . ($updatedTask['title'] ?? $match['task']['title'] ?? 'Task') . " -> {$status}"
        ];
    }

    /**
     * Mark task as done.
     */
    private function completeTask(array $params): array {
        $params['status'] = 'done';
        $result = $this->updateTaskStatus($params);
        $taskTitle = $result['task']['title'] ?? 'Task';
        $result['message'] = "Completed task: {$taskTitle}";
        return $result;
    }

    /**
     * Delete task by ID or exact title.
     */
    private function deleteTask(array $params): array {
        $match = $this->resolveTaskReference($params);
        $tasksAPI = new TasksAPI($this->db, 'projects');
        $taskId = $match['task']['id'];
        $taskTitle = $match['task']['title'] ?? 'Task';
        $projectName = $match['project']['name'] ?? 'Unknown Project';

        if (!$tasksAPI->delete($taskId)) {
            throw new ValidationException('Failed to delete task');
        }

        return [
            'success' => true,
            'deletedTaskId' => $taskId,
            'deletedTaskTitle' => $taskTitle,
            'project' => [
                'id' => $match['project']['id'] ?? '',
                'name' => $projectName
            ],
            'message' => "Deleted task: {$taskTitle}"
        ];
    }

    /**
     * Resolve task by taskId or exact title, optional projectName filter.
     */
    private function resolveTaskReference(array $params): array {
        $projects = $this->db->load('projects') ?? [];
        $taskId = trim((string)($params['taskId'] ?? ''));
        $title = trim((string)($params['title'] ?? ''));
        $projectNameFilter = strtolower(trim((string)($params['projectName'] ?? '')));

        $matches = [];
        foreach ($projects as $project) {
            $projectName = (string)($project['name'] ?? '');
            if ($projectNameFilter !== '' && strtolower($projectName) !== $projectNameFilter) {
                continue;
            }

            foreach ($project['tasks'] ?? [] as $task) {
                $currentTaskId = (string)($task['id'] ?? '');
                $currentTitle = trim((string)($task['title'] ?? ''));
                if ($taskId !== '' && $currentTaskId === $taskId) {
                    return ['task' => $task, 'project' => $project];
                }
                if ($taskId === '' && $title !== '' && strcasecmp($currentTitle, $title) === 0) {
                    $matches[] = ['task' => $task, 'project' => $project];
                }
            }
        }

        if ($taskId !== '') {
            throw new ValidationException("Task not found: {$taskId}");
        }
        if ($title === '') {
            throw new ValidationException('Task title is required when taskId is missing');
        }
        if (count($matches) === 0) {
            throw new ValidationException("Task not found with title: {$title}");
        }
        if (count($matches) > 1) {
            $locations = array_map(function($m) {
                $name = $m['project']['name'] ?? 'Unknown Project';
                $id = $m['task']['id'] ?? '';
                return "{$name} [{$id}]";
            }, array_slice($matches, 0, 5));
            throw new ValidationException("Multiple tasks match '{$title}'. Please specify taskId or projectName. Matches: " . implode(', ', $locations));
        }

        return $matches[0];
    }

    /**
     * List tasks across all projects (including Inbox standalone tasks).
     */
    private function listTasks(array $params): array {
        $projects = $this->db->load('projects') ?? [];
        $tasks = [];
        $today = date('Y-m-d');
        $query = strtolower(trim((string)($params['query'] ?? '')));
        $statusFilter = isset($params['status']) ? normalizeTaskStatus((string)$params['status'], '') : '';
        $priorityFilter = strtolower((string)($params['priority'] ?? ''));
        $dueFilter = strtolower((string)($params['dueFilter'] ?? 'all'));
        // Default true so "list all tasks" returns completed + open unless explicitly filtered.
        $includeCompleted = array_key_exists('includeCompleted', $params) ? (bool)$params['includeCompleted'] : true;
        $limit = (int)($params['limit'] ?? 50);
        $limit = max(1, min($limit, 200));

        foreach ($projects as $project) {
            $projectName = (string)($project['name'] ?? 'Unknown Project');
            $isInboxProject = (bool)($project['isInbox'] ?? false) || strtolower($projectName) === 'inbox';

            foreach ($project['tasks'] ?? [] as $task) {
                $status = normalizeTaskStatus((string)($task['status'] ?? 'todo'));
                $priority = strtolower((string)($task['priority'] ?? 'medium'));
                $dueDate = (string)($task['dueDate'] ?? '');
                $dueDay = $dueDate !== '' ? substr($dueDate, 0, 10) : '';
                $title = (string)($task['title'] ?? '');
                $description = (string)($task['description'] ?? '');

                if (!$includeCompleted && isTaskDone($status)) {
                    continue;
                }

                if ($statusFilter !== '' && $status !== $statusFilter) {
                    continue;
                }

                if ($priorityFilter !== '' && $priority !== $priorityFilter) {
                    continue;
                }

                if ($dueFilter === 'today' && ($dueDay === '' || $dueDay !== $today)) {
                    continue;
                }
                if ($dueFilter === 'overdue' && ($dueDay === '' || $dueDay >= $today || isTaskDone($status))) {
                    continue;
                }
                if ($dueFilter === 'upcoming' && ($dueDay === '' || $dueDay <= $today)) {
                    continue;
                }

                if ($query !== '') {
                    $haystack = strtolower($title . ' ' . $description . ' ' . $projectName);
                    if (strpos($haystack, $query) === false) {
                        continue;
                    }
                }

                $tasks[] = [
                    'id' => $task['id'] ?? '',
                    'title' => $title,
                    'description' => $description,
                    'status' => $status,
                    'priority' => in_array($priority, ['low', 'medium', 'high', 'urgent'], true) ? $priority : 'medium',
                    'dueDate' => $task['dueDate'] ?? null,
                    'estimatedMinutes' => (int)($task['estimatedMinutes'] ?? 0),
                    'projectId' => $project['id'] ?? null,
                    'projectName' => $projectName,
                    'isStandalone' => $isInboxProject,
                    'subtasks' => array_values(array_map(function($subtask) {
                        return [
                            'id' => $subtask['id'] ?? '',
                            'title' => (string)($subtask['title'] ?? ''),
                            'completed' => (bool)($subtask['completed'] ?? false),
                            'estimatedMinutes' => (int)($subtask['estimatedMinutes'] ?? 0),
                        ];
                    }, is_array($task['subtasks'] ?? null) ? $task['subtasks'] : []))
                ];
            }
        }

        usort($tasks, function($a, $b) {
            $aDue = $a['dueDate'] ?? '';
            $bDue = $b['dueDate'] ?? '';
            if ($aDue !== $bDue) {
                $aKey = $aDue === '' ? '9999-12-31' : substr($aDue, 0, 10);
                $bKey = $bDue === '' ? '9999-12-31' : substr($bDue, 0, 10);
                return strcmp($aKey, $bKey);
            }
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        $totalCount = count($tasks);
        $tasks = array_slice($tasks, 0, $limit);
        $totalEstimatedMinutes = array_sum(array_map(fn($t) => (int)($t['estimatedMinutes'] ?? 0), $tasks));
        $doneCount = count(array_filter($tasks, fn($t) => isTaskDone($t['status'] ?? '')));
        $openCount = count($tasks) - $doneCount;

        return [
            'success' => true,
            'tasks' => $tasks,
            'count' => $totalCount,
            'returned' => count($tasks),
            'openCount' => $openCount,
            'doneCount' => $doneCount,
            'totalEstimatedMinutes' => $totalEstimatedMinutes,
            'message' => "Found {$totalCount} tasks across all projects"
        ];
    }

    /**
     * Get all tasks for a specific project
     */
    private function getProjectTasks(array $params): array {
        $project = $this->db->findById('projects', $params['projectId']);

        if (!$project) {
            throw new ValidationException("Project not found");
        }

        // Limit tasks to recent/relevant ones (max 50)
        $tasks = $project['tasks'] ?? [];
        if (count($tasks) > 50) {
            $tasks = array_slice($tasks, -50); // Get last 50
        }

        return [
            'success' => true,
            'project' => [
                'id' => $project['id'],
                'name' => $project['name']
            ],
            'tasks' => $tasks,
            'count' => count($project['tasks'] ?? []), // Total count
            'message' => "Found " . count($tasks) . " tasks in '{$project['name']}'"
        ];
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(array $invoices, string $year): string {
        // Count existing invoices for this year
        $count = 0;
        foreach ($invoices as $inv) {
            if (strpos($inv['invoiceNumber'], $year) === 0) {
                $count++;
            }
        }

        // Format: YYYY-NNNN
        return sprintf("%s-%04d", $year, $count + 1);
    }

    /**
     * Generate random hex color
     */
    private function randomColor(): string {
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#6B7280'];
        return $colors[array_rand($colors)];
    }

    /**
     * Create a new habit
     */
    private function createHabit(array $params): array {
        $habits = $this->db->load('habits') ?? [];

        $habit = [
            'id' => $this->db->generateId(),
            'name' => $params['name'],
            'description' => $params['description'] ?? '',
            'frequency' => $params['frequency'] ?? 'daily',
            'goal' => $params['goal'] ?? 1,
            'category' => $params['category'] ?? 'General',
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
            'completions' => []
        ];

        $habits[] = $habit;
        $this->db->save('habits', $habits);

        return [
            'success' => true,
            'habit' => $habit,
            'message' => "Habit '{$habit['name']}' created successfully"
        ];
    }

    /**
     * List all habits
     */
    private function listHabits(array $params): array {
        $habits = $this->db->load('habits') ?? [];

        return [
            'success' => true,
            'habits' => array_map(function($h) {
                return [
                    'id' => $h['id'],
                    'name' => $h['name'],
                    'frequency' => $h['frequency'],
                    'category' => $h['category'],
                    'goal' => $h['goal'],
                    'completions' => count($h['completions'] ?? [])
                ];
            }, $habits),
            'count' => count($habits)
        ];
    }

    /**
     * Create inventory item
     */
    private function createInventoryItem(array $params): array {
        $inventory = $this->db->load('inventory') ?? [];

        $item = [
            'id' => $this->db->generateId(),
            'name' => $params['name'],
            'sku' => $params['sku'] ?? strtoupper(substr($params['name'], 0, 3)) . '-' . rand(1000, 9999),
            'description' => $params['description'] ?? '',
            'category' => $params['category'] ?? 'General',
            'cost' => $params['cost'] ?? 0,
            'price' => $params['price'],
            'quantity' => $params['quantity'] ?? 0,
            'minQuantity' => $params['minQuantity'] ?? 5,
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c')
        ];

        $inventory[] = $item;
        $this->db->save('inventory', $inventory);

        return [
            'success' => true,
            'item' => $item,
            'message' => "Inventory item '{$item['name']}' created successfully"
        ];
    }

    /**
     * List inventory items
     */
    private function listInventory(array $params): array {
        $inventory = $this->db->load('inventory') ?? [];

        // Filter by category if specified
        if (!empty($params['category'])) {
            $inventory = array_filter($inventory, function($item) use ($params) {
                return $item['category'] === $params['category'];
            });
        }

        // Filter low stock if specified
        if (!empty($params['lowStockOnly'])) {
            $inventory = array_filter($inventory, function($item) {
                return $item['quantity'] < ($item['minQuantity'] ?? 5);
            });
        }

        return [
            'success' => true,
            'items' => array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'category' => $item['category'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'minQuantity' => $item['minQuantity'],
                    'lowStock' => $item['quantity'] < ($item['minQuantity'] ?? 5)
                ];
            }, $inventory),
            'count' => count($inventory)
        ];
    }

    /**
     * Set Pomodoro timer
     */
    private function setPomodoroTimer(array $params): array {
        $workMinutes = $params['workMinutes'] ?? 25;
        $breakMinutes = $params['breakMinutes'] ?? 5;
        $sessions = $params['sessions'] ?? 1;
        $taskId = $params['taskId'] ?? null;

        // Validate values
        if ($workMinutes < 1 || $workMinutes > 120) {
            $workMinutes = 25;
        }
        if ($breakMinutes < 1 || $breakMinutes > 60) {
            $breakMinutes = 5;
        }
        if ($sessions < 1 || $sessions > 10) {
            $sessions = 1;
        }

        return [
            'success' => true,
            'timer' => [
                'workMinutes' => $workMinutes,
                'breakMinutes' => $breakMinutes,
                'sessions' => $sessions,
                'taskId' => $taskId,
                'totalMinutes' => ($workMinutes + $breakMinutes) * $sessions - $breakMinutes,
                'startedAt' => gmdate('c')
            ],
            'message' => "Pomodoro timer started: {$sessions} session(s) of {$workMinutes}min work + {$breakMinutes}min break"
        ];
    }
}
