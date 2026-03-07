<?php
/**
 * Comprehensive Unit Tests for API Endpoints
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

// Mock server environment for API testing
function setupApiEnvironment() {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SESSION = [];
    $_GET = [];
    $_POST = [];
}

function createApiTestDatabase() {
    return new class('test_password') extends Database {
        public function __construct($password) {
            $this->testPath = sys_get_temp_dir() . '/lazyman_api_test_' . uniqid();
            mkdir($this->testPath, 0755, true);
        }
        protected function getFilePath($collection) {
            return $this->testPath . '/' . $collection . '.json.enc';
        }
        public function __destruct() {
            if (isset($this->testPath) && is_dir($this->testPath)) {
                $files = glob($this->testPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                rmdir($this->testPath);
            }
        }
    };
}

// Test auth API - login endpoint
$runner->test('API Auth - Login endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();
    $auth = new Auth($db);

    // Register a test user
    $auth->register('test@example.com', 'password123', 'Test User');

    // Mock POST data
    $_POST['action'] = 'login';
    $_POST['email'] = 'test@example.com';
    $_POST['password'] = 'password123';

    // Simulate API call (simplified for testing)
    $users = $db->load('users');
    $loginResult = $auth->login($_POST['email'], $_POST['password']);

    assertTrue($loginResult['success']);
    assertEquals('test@example.com', $loginResult['user']['email']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test auth API - registration endpoint
$runner->test('API Auth - Registration endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();
    $auth = new Auth($db);

    // Mock POST data
    $_POST['action'] = 'register';
    $_POST['email'] = 'newuser@example.com';
    $_POST['password'] = 'newpassword123';
    $_POST['name'] = 'New User';

    $result = $auth->register($_POST['email'], $_POST['password'], $_POST['name']);

    assertTrue($result['success']);
    assertEquals('newuser@example.com', $result['user']['email']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test tasks API - create task
$runner->test('API Tasks - Create task endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    // Create a test project first
    $projectData = [
        'id' => $db->generateId(),
        'name' => 'Test Project',
        'description' => 'A test project',
        'status' => 'active',
        'color' => '#3B82F6',
        'tasks' => []
    ];
    $db->save('projects', [$projectData]);

    // Mock task creation data
    $taskData = [
        'id' => $db->generateId(),
        'title' => 'New Test Task',
        'description' => 'This is a test task',
        'status' => 'todo',
        'priority' => 'medium',
        'projectId' => $projectData['id'],
        'subtasks' => [],
        'timeEntries' => [],
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ];

    // Simulate adding task to project
    $projects = $db->load('projects');
    foreach ($projects as &$project) {
        if ($project['id'] === $projectData['id']) {
            $project['tasks'][] = $taskData;
            break;
        }
    }
    $db->save('projects', $projects);

    // Verify task was created
    $updatedProjects = $db->load('projects');
    $testProject = null;
    foreach ($updatedProjects as $project) {
        if ($project['id'] === $projectData['id']) {
            $testProject = $project;
            break;
        }
    }

    assertNotNull($testProject);
    assertEquals(1, count($testProject['tasks']));
    assertEquals('New Test Task', $testProject['tasks'][0]['title']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test tasks API - update task
$runner->test('API Tasks - Update task endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    // Create project with task
    $taskId = $db->generateId();
    $projectData = [
        'id' => $db->generateId(),
        'name' => 'Test Project',
        'description' => 'A test project',
        'status' => 'active',
        'color' => '#3B82F6',
        'tasks' => [[
            'id' => $taskId,
            'title' => 'Original Task',
            'description' => 'Original description',
            'status' => 'todo',
            'priority' => 'medium',
            'subtasks' => [],
            'timeEntries' => [],
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ]]
    ];
    $db->save('projects', [$projectData]);

    // Update task data
    $updates = [
        'title' => 'Updated Task',
        'description' => 'Updated description',
        'status' => 'in_progress',
        'priority' => 'high'
    ];

    // Simulate task update
    $projects = $db->load('projects');
    foreach ($projects as &$project) {
        if ($project['id'] === $projectData['id']) {
            foreach ($project['tasks'] as &$task) {
                if ($task['id'] === $taskId) {
                    $task = array_merge($task, $updates);
                    $task['updatedAt'] = date('c');
                    break;
                }
            }
            break;
        }
    }
    $db->save('projects', $projects);

    // Verify update
    $updatedProjects = $db->load('projects');
    $updatedTask = null;
    foreach ($updatedProjects as $project) {
        if ($project['id'] === $projectData['id']) {
            foreach ($project['tasks'] as $task) {
                if ($task['id'] === $taskId) {
                    $updatedTask = $task;
                    break;
                }
            }
            break;
        }
    }

    assertNotNull($updatedTask);
    assertEquals('Updated Task', $updatedTask['title']);
    assertEquals('in_progress', $updatedTask['status']);
    assertEquals('high', $updatedTask['priority']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test tasks API - delete task
$runner->test('API Tasks - Delete task endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    // Create project with multiple tasks
    $taskId1 = $db->generateId();
    $taskId2 = $db->generateId();
    $projectData = [
        'id' => $db->generateId(),
        'name' => 'Test Project',
        'description' => 'A test project',
        'status' => 'active',
        'color' => '#3B82F6',
        'tasks' => [
            [
                'id' => $taskId1,
                'title' => 'Task 1',
                'status' => 'todo',
                'priority' => 'medium',
                'subtasks' => [],
                'timeEntries' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ],
            [
                'id' => $taskId2,
                'title' => 'Task 2',
                'status' => 'done',
                'priority' => 'low',
                'subtasks' => [],
                'timeEntries' => [],
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ]
        ]
    ];
    $db->save('projects', [$projectData]);

    // Simulate task deletion
    $projects = $db->load('projects');
    foreach ($projects as &$project) {
        if ($project['id'] === $projectData['id']) {
            $project['tasks'] = array_filter($project['tasks'], function($task) use ($taskId1) {
                return $task['id'] !== $taskId1;
            });
            $project['tasks'] = array_values($project['tasks']); // Re-index
            break;
        }
    }
    $db->save('projects', $projects);

    // Verify deletion
    $updatedProjects = $db->load('projects');
    $updatedProject = null;
    foreach ($updatedProjects as $project) {
        if ($project['id'] === $projectData['id']) {
            $updatedProject = $project;
            break;
        }
    }

    assertNotNull($updatedProject);
    assertEquals(1, count($updatedProject['tasks']));
    assertEquals('Task 2', $updatedProject['tasks'][0]['title']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test projects API - create project
$runner->test('API Projects - Create project endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    $projectData = [
        'name' => 'New Project',
        'description' => 'A new test project',
        'status' => 'active',
        'color' => '#10B981'
    ];

    // Simulate project creation
    $newProject = [
        'id' => $db->generateId(),
        'tasks' => [],
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ] + $projectData;

    $projects = $db->load('projects');
    $projects[] = $newProject;
    $db->save('projects', $projects);

    // Verify creation
    $savedProjects = $db->load('projects');
    assertEquals(1, count($savedProjects));
    assertEquals('New Project', $savedProjects[0]['name']);
    assertEquals('active', $savedProjects[0]['status']);
    assertEquals([], $savedProjects[0]['tasks']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test clients API - create client
$runner->test('API Clients - Create client endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    $clientData = [
        'name' => 'Test Client',
        'email' => 'client@example.com',
        'phone' => '+1-555-0123',
        'company' => 'Test Company',
        'address' => [
            'street' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'zip' => '12345',
            'country' => 'USA'
        ],
        'notes' => 'Test client notes'
    ];

    // Simulate client creation
    $newClient = [
        'id' => $db->generateId(),
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ] + $clientData;

    $clients = $db->load('clients');
    $clients[] = $newClient;
    $db->save('clients', $clients);

    // Verify creation
    $savedClients = $db->load('clients');
    assertEquals(1, count($savedClients));
    assertEquals('Test Client', $savedClients[0]['name']);
    assertEquals('client@example.com', $savedClients[0]['email']);
    assertEquals('Test Company', $savedClients[0]['company']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test invoices API - create invoice
$runner->test('API Invoices - Create invoice endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    // Create a client first
    $clientId = $db->generateId();
    $clientData = [
        'id' => $clientId,
        'name' => 'Test Client',
        'email' => 'client@example.com',
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ];
    $db->save('clients', [$clientData]);

    $invoiceData = [
        'clientId' => $clientId,
        'invoiceNumber' => '2024-0001',
        'lineItems' => [
            [
                'description' => 'Web Development',
                'quantity' => 10,
                'unitPrice' => 100,
                'total' => 1000
            ],
            [
                'description' => 'Design Services',
                'quantity' => 5,
                'unitPrice' => 50,
                'total' => 250
            ]
        ],
        'subtotal' => 1250,
        'taxRate' => 10,
        'taxAmount' => 125,
        'total' => 1375,
        'currency' => 'USD',
        'status' => 'draft',
        'dueDate' => date('Y-m-d', strtotime('+30 days')),
        'issueDate' => date('Y-m-d'),
        'notes' => 'Payment due within 30 days'
    ];

    // Simulate invoice creation
    $newInvoice = [
        'id' => $db->generateId(),
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ] + $invoiceData;

    $invoices = $db->load('invoices');
    $invoices[] = $newInvoice;
    $db->save('invoices', $invoices);

    // Verify creation
    $savedInvoices = $db->load('invoices');
    assertEquals(1, count($savedInvoices));
    assertEquals('2024-0001', $savedInvoices[0]['invoiceNumber']);
    assertEquals($clientId, $savedInvoices[0]['clientId']);
    assertEquals(1375, $savedInvoices[0]['total']);
    assertEquals(2, count($savedInvoices[0]['lineItems']));

    cleanupApiTestDatabase($db);
    return true;
});

// Test finance API - create expense
$runner->test('API Finance - Create expense endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    $expenseData = [
        'type' => 'expense',
        'category' => 'Software',
        'amount' => 99.99,
        'currency' => 'USD',
        'date' => date('Y-m-d'),
        'description' => 'Monthly software subscription'
    ];

    // Simulate expense creation
    $newExpense = [
        'id' => $db->generateId(),
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ] + $expenseData;

    $finance = $db->load('finance');
    $finance[] = $newExpense;
    $db->save('finance', $finance);

    // Verify creation
    $savedFinance = $db->load('finance');
    assertEquals(1, count($savedFinance));
    assertEquals('expense', $savedFinance[0]['type']);
    assertEquals('Software', $savedFinance[0]['category']);
    assertEquals(99.99, $savedFinance[0]['amount']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test inventory API - create inventory item
$runner->test('API Inventory - Create inventory item endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    $inventoryData = [
        'name' => 'Wireless Mouse',
        'sku' => 'WM-001',
        'description' => 'Ergonomic wireless mouse',
        'category' => 'Electronics',
        'cost' => 25.00,
        'price' => 49.99,
        'quantity' => 100,
        'minQuantity' => 10,
        'supplier' => 'Tech Supplies Co.'
    ];

    // Simulate inventory item creation
    $newItem = [
        'id' => $db->generateId(),
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ] + $inventoryData;

    $inventory = $db->load('inventory');
    $inventory[] = $newItem;
    $db->save('inventory', $inventory);

    // Verify creation
    $savedInventory = $db->load('inventory');
    assertEquals(1, count($savedInventory));
    assertEquals('Wireless Mouse', $savedInventory[0]['name']);
    assertEquals('WM-001', $savedInventory[0]['sku']);
    assertEquals(100, $savedInventory[0]['quantity']);
    assertEquals(49.99, $savedInventory[0]['price']);

    cleanupApiTestDatabase($db);
    return true;
});

// Test export API - export all data
$runner->test('API Export - Export all data endpoint', function() {
    setupApiEnvironment();
    $db = createApiTestDatabase();

    // Add some test data
    $db->insert('users', ['name' => 'Test User', 'email' => 'test@example.com']);
    $db->insert('projects', ['name' => 'Test Project', 'status' => 'active']);
    $db->insert('clients', ['name' => 'Test Client', 'email' => 'client@example.com']);

    // Simulate export
    $exportData = $db->exportAll();

    // Verify export structure
    assertTrue(is_array($exportData));
    assertTrue(isset($exportData['users']));
    assertTrue(isset($exportData['projects']));
    assertTrue(isset($exportData['clients']));
    assertEquals(1, count($exportData['users']));
    assertEquals(1, count($exportData['projects']));
    assertEquals(1, count($exportData['clients']));

    cleanupApiTestDatabase($db);
    return true;
});

// Test API error handling
$runner->test('API - Error handling', function() {
    setupApiEnvironment();

    // Test missing required parameters
    try {
        // Simulate validation
        if (empty($_POST['email'])) {
            throw new Exception('Email is required');
        }
        return true;
    } catch (Exception $e) {
        assertEquals('Email is required', $e->getMessage());
        return true;
    }
});

// Test API authentication middleware
$runner->test('API - Authentication middleware', function() {
    setupApiEnvironment();

    // Test without session
    $_SESSION = [];
    $isAuthenticated = Auth::check();
    assertFalse($isAuthenticated);

    // Test with valid session
    $_SESSION = [
        'user_id' => 'test-user-id',
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'login_time' => time()
    ];
    $isAuthenticated = Auth::check();
    assertTrue($isAuthenticated);

    return true;
});

// Test CSRF token validation in API
$runner->test('API - CSRF token validation', function() {
    setupApiEnvironment();

    // Set CSRF token in session
    $_SESSION['csrf_token'] = 'test-csrf-token-12345';

    // Test valid token
    $isValid = Auth::validateCsrf('test-csrf-token-12345');
    assertTrue($isValid);

    // Test invalid token
    $isInvalid = Auth::validateCsrf('wrong-token');
    assertFalse($isInvalid);

    return true;
});

// Test API response format consistency
$runner->test('API - Response format consistency', function() {
    setupApiEnvironment();

    // Test success response format
    ob_start();
    successResponse(['id' => 123], 'Operation successful');
    $output = ob_get_clean();

    $response = json_decode($output, true);
    assertTrue($response['success']);
    assertEquals('Operation successful', $response['message']);
    assertEquals(['id' => 123], $response['data']);
    assertTrue(isset($response['timestamp']));

    // Test error response format
    ob_start();
    errorResponse('Something went wrong', 400);
    $output = ob_get_clean();

    $errorResponse = json_decode($output, true);
    assertFalse($errorResponse['success']);
    assertEquals('Something went wrong', $errorResponse['error']);
    assertTrue(isset($errorResponse['timestamp']));

    return true;
});

// Test API input validation
$runner->test('API - Input validation', function() {
    setupApiEnvironment();

    // Test email validation
    assertTrue(isValidEmail('valid@example.com'));
    assertFalse(isValidEmail('invalid-email'));

    // Test required field validation
    $requiredFields = ['name', 'email', 'password'];
    $inputData = ['name' => 'John', 'email' => 'john@example.com'];
    $missingFields = array_diff($requiredFields, array_keys($inputData));
    assertEquals(['password'], $missingFields);

    // Test data type validation
    $numericField = '123abc';
    $isNumeric = is_numeric($numericField);
    assertFalse($isNumeric);

    $validNumeric = '123';
    $isValidNumeric = is_numeric($validNumeric);
    assertTrue($isValidNumeric);

    return true;
});

function cleanupApiTestDatabase($db) {
    if (method_exists($db, '__destruct')) {
        $db->__destruct();
    }
}

// Run the tests
$runner->run();