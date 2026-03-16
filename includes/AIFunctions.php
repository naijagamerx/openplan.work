<?php
/**
 * AI Functions Registry
 *
 * Defines all available functions/tools for the AI agent.
 * Uses OpenAI-compatible JSON schema format.
 */

class AIFunctions {

    /**
     * Get all available functions for the AI agent
     *
     * @return array OpenAI-compatible function definitions
     */
    public static function getAllFunctions(): array {
        return [
            self::createTask(),
            self::updateTask(),
            self::updateTaskStatus(),
            self::completeTask(),
            self::deleteTask(),
            self::createProject(),
            self::createClient(),
            self::listClients(),
            self::createInvoice(),
            self::createAdvancedInvoice(),
            self::createNote(),
            self::searchKnowledgeBase(),
            self::createTransaction(),
            self::listProjects(),
            self::listTasks(),
            self::getProjectTasks(),
            self::createHabit(),
            self::listHabits(),
            self::createInventoryItem(),
            self::listInventory(),
            self::setPomodoroTimer(),
        ];
    }

    /**
     * Function: Create Task
     *
     * Creates a new task with optional subtasks.
     * Tasks must be associated with a project.
     */
    private static function createTask(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_task',
                'description' => 'Create a new task with optional subtasks. Tasks must be associated with a project.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'The task title (brief, actionable)'
                        ],
                        'projectId' => [
                            'type' => 'string',
                            'description' => 'The ID of the project to add this task to. Use list_projects to find IDs.'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Detailed task description'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high', 'urgent'],
                            'description' => 'Task priority level'
                        ],
                        'estimatedMinutes' => [
                            'type' => 'number',
                            'description' => 'Estimated time in minutes'
                        ],
                        'dueDate' => [
                            'type' => 'string',
                            'description' => 'Due date in ISO 8601 format (e.g., 2025-12-31)'
                        ],
                        'subtasks' => [
                            'type' => 'array',
                            'description' => 'Optional list of subtasks',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'estimatedMinutes' => ['type' => 'number']
                                ]
                            ]
                        ]
                    ],
                    'required' => ['title']
                ]
            ]
        ];
    }

    /**
     * Function: Create Project
     */
    private static function createProject(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_project',
                'description' => 'Create a new project. Projects contain tasks and can be associated with clients.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Project name (clear, descriptive)'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Project description and goals'
                        ],
                        'clientId' => [
                            'type' => 'string',
                            'description' => 'Optional client ID to associate with this project'
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Optional color hex code (e.g., #3B82F6)'
                        ]
                    ],
                    'required' => ['name']
                ]
            ]
        ];
    }

    /**
     * Function: Create Client
     */
    private static function createClient(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_client',
                'description' => 'Create a new client/contact. Clients are used for projects and invoicing.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Contact person name'
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Email address (must be unique)'
                        ],
                        'phone' => [
                            'type' => 'string',
                            'description' => 'Phone number (optional)'
                        ],
                        'company' => [
                            'type' => 'string',
                            'description' => 'Company name (optional)'
                        ],
                        'address' => [
                            'type' => 'object',
                            'description' => 'Address object',
                            'properties' => [
                                'street' => ['type' => 'string'],
                                'city' => ['type' => 'string'],
                                'state' => ['type' => 'string'],
                                'zip' => ['type' => 'string'],
                                'country' => ['type' => 'string']
                            ]
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Additional notes about this client'
                        ]
                    ],
                    'required' => ['name', 'email']
                ]
            ]
        ];
    }

    /**
     * Function: List Clients
     */
    private static function listClients(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_clients',
                'description' => 'List all clients. Use this to find client IDs for invoicing.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object)[]
                ]
            ]
        ];
    }

    /**
     * Function: Create Invoice
     */
    private static function createInvoice(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_invoice',
                'description' => 'Create a new invoice for a client with line items. Invoices generate automatic invoice numbers.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'clientId' => [
                            'type' => 'string',
                            'description' => 'The client ID to invoice'
                        ],
                        'projectId' => [
                            'type' => 'string',
                            'description' => 'Optional associated project ID'
                        ],
                        'dueDate' => [
                            'type' => 'string',
                            'description' => 'Due date in ISO 8601 format'
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'Line items for the invoice',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => [
                                        'type' => 'string',
                                        'description' => 'Item description'
                                    ],
                                    'quantity' => [
                                        'type' => 'number',
                                        'description' => 'Quantity (default: 1)'
                                    ],
                                    'unitPrice' => [
                                        'type' => 'number',
                                        'description' => 'Price per unit'
                                    ]
                                ],
                                'required' => ['description', 'unitPrice']
                            ]
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Optional invoice notes'
                        ]
                    ],
                    'required' => ['clientId', 'dueDate', 'lineItems']
                ]
            ]
        ];
    }

    /**
     * Function: Create Advanced Invoice
     *
     * Creates an advanced invoice with custom fields, templates, and detailed formatting.
     */
    private static function createAdvancedInvoice(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_advanced_invoice',
                'description' => 'Create an advanced invoice with custom fields, templates, and detailed formatting options',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'clientId' => [
                            'type' => 'string',
                            'description' => 'The client ID to invoice'
                        ],
                        'projectId' => [
                            'type' => 'string',
                            'description' => 'Optional associated project ID'
                        ],
                        'invoiceDate' => [
                            'type' => 'string',
                            'description' => 'Invoice date in ISO 8601 format'
                        ],
                        'dueDate' => [
                            'type' => 'string',
                            'description' => 'Due date in ISO 8601 format'
                        ],
                        'lineItems' => [
                            'type' => 'array',
                            'description' => 'Line items with detailed information',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'description' => ['type' => 'string'],
                                    'quantity' => ['type' => 'number'],
                                    'unitPrice' => ['type' => 'number'],
                                    'taxRate' => ['type' => 'number']
                                ],
                                'required' => ['description', 'unitPrice']
                            ]
                        ],
                        'customFields' => [
                            'type' => 'object',
                            'description' => 'Custom fields for invoice (key-value pairs)'
                        ],
                        'template' => [
                            'type' => 'string',
                            'description' => 'Invoice template name (e.g., "standard", "detailed", "minimal")'
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Optional invoice notes'
                        ]
                    ],
                    'required' => ['clientId', 'dueDate', 'lineItems']
                ]
            ]
        ];
    }

    /**
     * Function: Create Note
     */
    private static function createNote(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_note',
                'description' => 'Create a new note. Notes can be organized with tags and colors.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Note title'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Note content (max 10,000 characters)'
                        ],
                        'tags' => [
                            'type' => 'array',
                            'description' => 'Optional tags for organization',
                            'items' => ['type' => 'string']
                        ],
                        'color' => [
                            'type' => 'string',
                            'description' => 'Optional background color hex code (e.g., #FEF3C7)'
                        ],
                        'isPinned' => [
                            'type' => 'boolean',
                            'description' => 'Whether to pin this note'
                        ]
                    ],
                    'required' => ['title', 'content']
                ]
            ]
        ];
    }

    /**
     * Function: Search Knowledge Base
     */
    private static function searchKnowledgeBase(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'search_knowledge_base',
                'description' => 'Search the knowledge base for information. Returns matching files and folders.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query'
                        ],
                        'folderId' => [
                            'type' => 'string',
                            'description' => 'Optional folder ID to scope search'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ];
    }

    /**
     * Function: Create Transaction
     */
    private static function createTransaction(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_transaction',
                'description' => 'Create a finance transaction (expense or revenue).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => [
                            'type' => 'string',
                            'enum' => ['expense', 'revenue'],
                            'description' => 'Transaction type'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Transaction description'
                        ],
                        'amount' => [
                            'type' => 'number',
                            'description' => 'Amount in currency (e.g., 100.50)'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Category (e.g., Software, Hosting, Services, Sales)'
                        ],
                        'date' => [
                            'type' => 'string',
                            'description' => 'Transaction date in ISO 8601 format'
                        ],
                        'projectId' => [
                            'type' => 'string',
                            'description' => 'Optional associated project ID'
                        ],
                        'clientId' => [
                            'type' => 'string',
                            'description' => 'Optional associated client ID'
                        ]
                    ],
                    'required' => ['type', 'description', 'amount']
                ]
            ]
        ];
    }

    /**
     * Function: List Projects
     */
    private static function listProjects(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_projects',
                'description' => 'List all projects. Use this to find project IDs for task creation.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['active', 'completed', 'on-hold', 'cancelled'],
                            'description' => 'Optional filter by status'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Function: Get Project Tasks
     */
    private static function getProjectTasks(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_project_tasks',
                'description' => 'Get all tasks for a specific project.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => [
                            'type' => 'string',
                            'description' => 'The project ID'
                        ]
                    ],
                    'required' => ['projectId']
                ]
            ]
        ];
    }

    /**
     * Function: Update Task (general)
     */
    private static function updateTask(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'update_task',
                'description' => 'Update an existing task by ID or exact title (optionally scoped by project name).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'taskId' => [
                            'type' => 'string',
                            'description' => 'Task ID (preferred when available)'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Exact task title when taskId is not provided'
                        ],
                        'projectName' => [
                            'type' => 'string',
                            'description' => 'Optional project name to disambiguate duplicate titles'
                        ],
                        'newTitle' => [
                            'type' => 'string',
                            'description' => 'New task title'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'New task description'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['todo', 'in_progress', 'done', 'backlog'],
                            'description' => 'Task status'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high', 'urgent'],
                            'description' => 'Task priority'
                        ],
                        'dueDate' => [
                            'type' => 'string',
                            'description' => 'Due date in ISO 8601 format'
                        ],
                        'estimatedMinutes' => [
                            'type' => 'number',
                            'description' => 'Estimated minutes'
                        ],
                        'actualMinutes' => [
                            'type' => 'number',
                            'description' => 'Actual minutes spent'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Function: Update Task Status
     */
    private static function updateTaskStatus(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'update_task_status',
                'description' => 'Update status of an existing task by ID or exact title.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'taskId' => [
                            'type' => 'string',
                            'description' => 'Task ID (preferred)'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Exact task title when taskId is not provided'
                        ],
                        'projectName' => [
                            'type' => 'string',
                            'description' => 'Optional project name to disambiguate duplicate titles'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['todo', 'in_progress', 'done', 'backlog'],
                            'description' => 'New task status'
                        ]
                    ],
                    'required' => ['status']
                ]
            ]
        ];
    }

    /**
     * Function: Complete Task
     */
    private static function completeTask(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'complete_task',
                'description' => 'Mark an existing task as done by ID or exact title.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'taskId' => [
                            'type' => 'string',
                            'description' => 'Task ID (preferred)'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Exact task title when taskId is not provided'
                        ],
                        'projectName' => [
                            'type' => 'string',
                            'description' => 'Optional project name to disambiguate duplicate titles'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Function: Delete Task
     */
    private static function deleteTask(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'delete_task',
                'description' => 'Delete an existing task by ID or exact title.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'taskId' => [
                            'type' => 'string',
                            'description' => 'Task ID (preferred)'
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Exact task title when taskId is not provided'
                        ],
                        'projectName' => [
                            'type' => 'string',
                            'description' => 'Optional project name to disambiguate duplicate titles'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Function: List Tasks (global)
     */
    private static function listTasks(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_tasks',
                'description' => 'List tasks across all projects, including standalone tasks in Inbox. Best tool for daily planning.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['todo', 'in_progress', 'done', 'backlog'],
                            'description' => 'Optional status filter'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high', 'urgent'],
                            'description' => 'Optional priority filter'
                        ],
                        'dueFilter' => [
                            'type' => 'string',
                            'enum' => ['today', 'overdue', 'upcoming', 'all'],
                            'description' => 'Optional due-date filter for planning'
                        ],
                        'includeCompleted' => [
                            'type' => 'boolean',
                            'description' => 'Include completed tasks (default true)'
                        ],
                        'query' => [
                            'type' => 'string',
                            'description' => 'Optional text search in title/description'
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum tasks to return (default 50, max 200)'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Function: Create Habit
     */
    private static function createHabit(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_habit',
                'description' => 'Create a new habit to track. Habits can be daily, weekly, or custom frequency.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Habit name (e.g., "Morning Exercise", "Read 30 minutes")'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Detailed description of the habit'
                        ],
                        'frequency' => [
                            'type' => 'string',
                            'enum' => ['daily', 'weekly', 'custom'],
                            'description' => 'How often to track this habit'
                        ],
                        'goal' => [
                            'type' => 'integer',
                            'description' => 'Target count per period (e.g., 5 for 5 times per week)'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Category (e.g., "Health", "Learning", "Productivity")'
                        ]
                    ],
                    'required' => ['name', 'frequency']
                ]
            ]
        ];
    }

    /**
     * Function: List Habits
     */
    private static function listHabits(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_habits',
                'description' => 'List all habits being tracked.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object)[]
                ]
            ]
        ];
    }

    /**
     * Function: Create Inventory Item
     */
    private static function createInventoryItem(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'create_inventory_item',
                'description' => 'Add a new item to inventory with pricing and stock information.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Product name'
                        ],
                        'sku' => [
                            'type' => 'string',
                            'description' => 'Stock keeping unit (unique identifier)'
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Product description'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Product category'
                        ],
                        'cost' => [
                            'type' => 'number',
                            'description' => 'Cost price'
                        ],
                        'price' => [
                            'type' => 'number',
                            'description' => 'Selling price'
                        ],
                        'quantity' => [
                            'type' => 'integer',
                            'description' => 'Current stock quantity'
                        ],
                        'minQuantity' => [
                            'type' => 'integer',
                            'description' => 'Minimum quantity before reorder alert'
                        ]
                    ],
                    'required' => ['name', 'price', 'quantity']
                ]
            ]
        ];
    }

    /**
     * Function: List Inventory
     */
    private static function listInventory(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_inventory',
                'description' => 'List all inventory items with stock levels.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optional filter by category'
                        ],
                        'lowStockOnly' => [
                            'type' => 'boolean',
                            'description' => 'Show only items below minimum quantity'
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Function: Set Pomodoro Timer
     */
    private static function setPomodoroTimer(): array {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'set_pomodoro_timer',
                'description' => 'Start a Pomodoro timer session with work and break intervals.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'workMinutes' => [
                            'type' => 'integer',
                            'description' => 'Work interval in minutes (default: 25)'
                        ],
                        'breakMinutes' => [
                            'type' => 'integer',
                            'description' => 'Break interval in minutes (default: 5)'
                        ],
                        'sessions' => [
                            'type' => 'integer',
                            'description' => 'Number of Pomodoro sessions (default: 1)'
                        ],
                        'taskId' => [
                            'type' => 'string',
                            'description' => 'Optional task ID to associate with timer'
                        ]
                    ]
                ]
            ]
        ];
    }
}
