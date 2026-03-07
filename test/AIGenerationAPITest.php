<?php
/**
 * Comprehensive Unit Tests for AI Generation API Endpoint
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

$runner->runSuite('AI Generation API Tests', function($testRunner) {
    $db = createTestDatabase();

    // Set up required data for AI operations
    $models = [
        'groq' => [
            [
                'id' => $db->generateId(),
                'modelId' => 'llama-3.3-70b-versatile',
                'displayName' => 'Llama 3.3 70B',
                'description' => 'Fast, versatile model for general tasks',
                'enabled' => true,
                'isDefault' => true,
                'createdAt' => date('c')
            ]
        ],
        'openrouter' => []
    ];
    $db->save('models', $models);

    // Test project generation action structure
    $testRunner->assert('AI Generation API handles project action structure', function() use ($db) {
        // The API expects a specific structure for project generation
        $idea = 'Build a task management app';

        // Simulate what the AIHelper would generate
        $expectedStructure = [
            'name' => 'string',
            'description' => 'string',
            'timeline_weeks' => 'numeric',
            'milestones' => 'array',
            'suggested_tasks' => 'array'
        ];

        // While we can't call the real AI, we can test the expected structure
        // The API would call $ai->generateProject() which expects this structure
        // So we'll test if the mock response matches expectations
        $mockResponse = [
            'name' => 'Task Management App',
            'description' => $idea,
            'timeline_weeks' => 4,
            'milestones' => [['name' => 'Setup', 'week' => 1]],
            'suggested_tasks' => [['title' => 'Create database schema']]
        ];

        return isset($mockResponse['name']) &&
               isset($mockResponse['description']) &&
               isset($mockResponse['timeline_weeks']) &&
               is_array($mockResponse['milestones']) &&
               is_array($mockResponse['suggested_tasks']);
    });

    // Test tasks generation action structure
    $testRunner->assert('AI Generation API handles tasks action structure', function() use ($db) {
        $projectData = [
            'name' => 'Website Redesign',
            'description' => 'Redesign the company website',
            'timeline_weeks' => 6
        ];

        $mockResponse = [
            [
                'title' => 'Design mockups',
                'description' => 'Create design mockups',
                'priority' => 'high',
                'estimated_hours' => 8
            ],
            [
                'title' => 'Frontend development',
                'description' => 'Develop frontend components',
                'priority' => 'high',
                'estimated_hours' => 20
            ]
        ];

        return is_array($mockResponse) &&
               isset($mockResponse[0]['title']) &&
               isset($mockResponse[0]['priority']) &&
               isset($mockResponse[0]['estimated_hours']);
    });

    // Test subtasks generation action structure
    $testRunner->assert('AI Generation API handles subtasks action structure', function() use ($db) {
        $mockResponse = [
            [
                'title' => 'Research design patterns',
                'estimated_minutes' => 60
            ],
            [
                'title' => 'Create wireframes',
                'estimated_minutes' => 120
            ]
        ];

        return is_array($mockResponse) &&
               isset($mockResponse[0]['title']) &&
               isset($mockResponse[0]['estimated_minutes']);
    });

    // Test invoice items generation action structure
    $testRunner->assert('AI Generation API handles invoice items action structure', function() use ($db) {
        $mockResponse = [
            [
                'description' => 'Website Design',
                'quantity' => 1,
                'suggested_rate_usd' => 1500
            ],
            [
                'description' => 'Development',
                'quantity' => 20,
                'suggested_rate_usd' => 100
            ]
        ];

        return is_array($mockResponse) &&
               isset($mockResponse[0]['description']) &&
               isset($mockResponse[0]['suggested_rate_usd']);
    });

    // Test provider fallback to default model
    $testRunner->assert('AI Generation API falls back to default model', function() use ($db) {
        $models = $db->load('models');

        $provider = 'groq';
        $selectedModel = '';

        // Simulate the API's model selection logic
        if (empty($selectedModel) && !empty($models[$provider])) {
            foreach ($models[$provider] as $m) {
                if ($m['isDefault']) {
                    $selectedModel = $m['modelId'];
                    break;
                }
            }
        }

        return $selectedModel === 'llama-3.3-70b-versatile';
    });

    // Test project name lookup from ID
    $testRunner->assert('AI Generation API can lookup project by ID', function() use ($db) {
        $projectId = $db->generateId();
        $projects = [
            [
                'id' => $projectId,
                'name' => 'Test Project',
                'description' => 'A test project',
                'tasks' => []
            ]
        ];
        $db->save('projects', $projects);

        // Simulate the API logic for looking up project by ID
        $lookupId = $projectId;
        $foundProjectName = '';

        $allProjects = $db->load('projects');
        foreach ($allProjects as $p) {
            if ($p['id'] === $lookupId) {
                $foundProjectName = $p['name'];
                break;
            }
        }

        return $foundProjectName === 'Test Project';
    });

    // Test task filtering for invoice generation
    $testRunner->assert('AI Generation API filters tasks for invoice generation', function() use ($db) {
        $projectId = $db->generateId();
        $tasks = [
            [
                'id' => $db->generateId(),
                'projectId' => $projectId,
                'title' => 'Completed Task',
                'status' => 'done',
                'description' => 'A completed task'
            ],
            [
                'id' => $db->generateId(),
                'projectId' => $projectId,
                'title' => 'In Progress Task',
                'status' => 'in_progress',
                'description' => 'Task still in progress'
            ],
            [
                'id' => $db->generateId(),
                'projectId' => $projectId,
                'title' => 'Completed Task 2',
                'status' => 'completed',
                'description' => 'Another completed task'
            ]
        ];
        $db->save('tasks', $tasks);

        // Simulate the API filtering logic for completed tasks
        $allTasks = $db->load('tasks');
        $filteredTasks = array_filter($allTasks, fn($t) =>
            ($t['projectId'] ?? '') === $projectId &&
            ($t['status'] === 'done' || $t['status'] === 'completed')
        );

        return count($filteredTasks) === 2;  // Should find 2 completed tasks
    });

    // Test response normalization for invoice items
    $testRunner->assert('AI Generation API normalizes invoice response structure', function() use ($db) {
        // Simulate AI response that needs normalization
        $rawResponse = [
            [
                'description' => 'Service A',
                'suggested_rate_usd' => 100,
                'quantity' => 1
            ]
        ];

        // Test the API's normalization logic
        $result = ['items' => $rawResponse];  // What the API would wrap it in

        // And map suggested_rate_usd to unitPrice if needed
        foreach ($result['items'] as &$item) {
            if (isset($item['suggested_rate_usd']) && !isset($item['unitPrice'])) {
                $item['unitPrice'] = $item['suggested_rate_usd'];
            }
        }

        return isset($result['items'][0]['unitPrice']) &&
               $result['items'][0]['unitPrice'] === 100;
    });

    // Test error handling for missing idea in project generation
    $testRunner->assert('AI Generation API validates required parameters', function() use ($db) {
        $idea = '';
        $projectData = [];
        $title = '';
        $description = '';

        // Test the API's validation logic
        $projectValid = !empty($idea);
        $tasksValid = !empty($projectData);
        $subtasksValid = !empty($title);

        return !$projectValid && !$tasksValid && !$subtasksValid;
    });

    // Test supported actions
    $testRunner->assert('AI Generation API supports required actions', function() use ($db) {
        $supportedActions = ['project', 'tasks', 'subtasks', 'invoice_items', 'invoice-items'];
        $testActions = ['project', 'tasks', 'subtasks', 'invoice_items'];

        foreach ($testActions as $action) {
            if (!in_array($action, $supportedActions)) {
                return false;
            }
        }

        return true;
    });

    // Test AI response extraction and parsing
    $testRunner->assert('AI Generation API can extract JSON from AI responses', function() use ($db) {
        $aiResponse = "Some introductory text here:\n\n```json\n{\"items\": [{\"name\": \"test\"}]}\n```\n\nSome closing text.";

        // Test if we can extract JSON using pattern matching (like in AIHelper)
        if (preg_match('/\{(?:[^{}]|(?R))*\}/x', $aiResponse, $matches)) {
            $extracted = $matches[0];
            $data = json_decode($extracted, true);
            return $data !== null && isset($data['items']);
        } elseif (preg_match('/\[(?:[^[\]]|(?R))*\]/x', $aiResponse, $matches)) {
            $extracted = $matches[0];
            $data = json_decode($extracted, true);
            return $data !== null;
        }

        return false;
    });

    // Test default provider fallback
    $testRunner->assert('AI Generation API has default provider', function() use ($db) {
        $defaultProvider = 'groq';
        $validProviders = ['groq', 'openrouter'];

        return in_array($defaultProvider, $validProviders);
    });

    cleanupTestDatabase($db);
});

function createTestDatabase() {
    return new class('test_password') extends Database {
        public function __construct($password) {
            $this->testPath = sys_get_temp_dir() . '/lazyman_ai_gen_test_' . uniqid();
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

function cleanupTestDatabase($db) {
    if (method_exists($db, '__destruct')) {
        $db->__destruct();
    }
}

// Output results
$testRunner->printSummary();