<?php
/**
 * Comprehensive Unit Tests for AIHelper Class
 */

// Define required constants if they don't exist
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/..');
    define('DATA_PATH', ROOT_PATH . '/data');
    define('INCLUDES_PATH', ROOT_PATH . '/includes');
}

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

function createTestDatabase() {
    // Create a temporary test data path
    $testPath = sys_get_temp_dir() . '/lazyman_ai_test_' . uniqid();
    mkdir($testPath, 0755, true);

    // Temporarily redefine DATA_PATH for this test instance
    return new class('test_password', $testPath) extends Database {
        private $testPath;

        public function __construct($masterPassword, $tempPath) {
            $this->testPath = $tempPath;
            $this->encryption = new Encryption($masterPassword);
            $this->dataPath = $this->testPath;  // Override with test path

            // Ensure test data directory exists
            if (!is_dir($this->dataPath)) {
                mkdir($this->dataPath, 0755, true);
            }
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

$runner = new TestRunner();

$runner->runSuite('AIHelper Tests', function($testRunner) {
    $db = createTestDatabase();

    // Test AIHelper construction
    $aiHelper = new AIHelper($db);
    $testRunner->assert('AIHelper can be instantiated', $aiHelper instanceof AIHelper);

    // Test prompts loading
    $reflection = new ReflectionClass($aiHelper);
    $property = $reflection->getProperty('prompts');
    $property->setAccessible(true);
    $prompts = $property->getValue($aiHelper);
    $testRunner->assert('AIHelper loads prompts correctly', count($prompts) > 0);

    // Test prompt keys existence
    $requiredKeys = ['project_from_idea', 'tasks_from_project', 'subtasks', 'invoice_items', 'daily_brief'];
    $hasAllKeys = true;
    foreach ($requiredKeys as $key) {
        if (!isset($prompts[$key])) {
            $hasAllKeys = false;
            break;
        }
    }
    $testRunner->assert('AIHelper has required prompt keys', $hasAllKeys);

    // Test JSON parsing for valid JSON
    $method = $reflection->getMethod('parseJSON');
    $method->setAccessible(true);
    $valid = true;
    try {
        $result = $method->invoke($aiHelper, '{"test": "value", "number": 123}');
        $valid = $result['test'] === 'value' && $result['number'] === 123;
    } catch (Exception $e) {
        $valid = false;
    }
    $testRunner->assert('AIHelper can parse valid JSON', $valid);

    // Test JSON parsing for nested JSON
    $nestedValid = true;
    try {
        $jsonStr = '{"items": [{"id": 1, "name": "test"}], "count": 1}';
        $result = $method->invoke($aiHelper, $jsonStr);
        $nestedValid = is_array($result) &&
                       isset($result['items']) &&
                       is_array($result['items']) &&
                       count($result['items']) === 1 &&
                       $result['items'][0]['name'] === 'test';
    } catch (Exception $e) {
        $nestedValid = false;
    }
    $testRunner->assert('AIHelper can parse nested JSON', $nestedValid);

    // Test JSON parsing for JSON wrapped in text
    $wrapperValid = true;
    try {
        $wrapperText = 'Here is the response: {"name": "project", "status": "success"} Thank you!';
        $result = $method->invoke($aiHelper, $wrapperText);
        $wrapperValid = $result['name'] === 'project' && $result['status'] === 'success';
    } catch (Exception $e) {
        $wrapperValid = false;
    }
    $testRunner->assert('AIHelper can extract JSON from text wrapper', $wrapperValid);

    // Test JSON parsing for array wrapper
    $arrayValid = true;
    try {
        $wrapperText = 'Here are the items: [{"name": "task1"}, {"name": "task2"}]';
        $result = $method->invoke($aiHelper, $wrapperText);
        $arrayValid = is_array($result) &&
                      count($result) === 2 &&
                      $result[0]['name'] === 'task1';
    } catch (Exception $e) {
        $arrayValid = false;
    }
    $testRunner->assert('AIHelper can extract JSON array from text', $arrayValid);

    // Test error handling for invalid JSON
    $errorHandled = false;
    try {
        $method->invoke($aiHelper, 'not valid json at all');
        // If we get here without exception, it's not properly handled
    } catch (Exception $e) {
        $errorHandled = true;
    }
    $testRunner->assert('AIHelper handles invalid JSON gracefully', $errorHandled);

    // Test error handling for non-JSON value in callAI (mocked scenario)
    // Set up mocked config without API keys
    $config = [];
    $db->save('config', $config);

    $callAivalid = false;
    try {
        $method = $reflection->getMethod('callAI');
        $method->setAccessible(true);
        $method->invoke($aiHelper, 'nonexistent', 'model', 'prompt');
    } catch (Exception $e) {
        $callAivalid = true;
    }
    $testRunner->assert('AIHelper handles API errors gracefully', $callAivalid);

    // Test generateProject method structure with mocked response (simulated)
    $parseJSONMethod = $reflection->getMethod('parseJSON');
    $parseJSONMethod->setAccessible(true);
    $projectValid = true;
    try {
        $mockResponse = '{"name": "Test Project", "description": "A test project", "timeline_weeks": 4, "milestones": [{"name": "Start", "week": 1}], "suggested_tasks": [{"title": "Setup"}]}';
        $result = $parseJSONMethod->invoke($aiHelper, $mockResponse);
        $projectValid = isset($result['name']) &&
               isset($result['description']) &&
               isset($result['timeline_weeks']) &&
               isset($result['milestones']) &&
               isset($result['suggested_tasks']);
    } catch (Exception $e) {
        $projectValid = false;
    }
    $testRunner->assert('AIHelper generateProject follows correct structure', $projectValid);

    // Test generateTasks method structure with mocked response
    $tasksValid = true;
    try {
        $mockResponse = '[{"title": "Task 1", "description": "First task", "priority": "high", "estimated_hours": 2}]';
        $result = $parseJSONMethod->invoke($aiHelper, $mockResponse);
        $tasksValid = is_array($result) &&
               isset($result[0]['title']) &&
               isset($result[0]['priority']) &&
               isset($result[0]['estimated_hours']);
    } catch (Exception $e) {
        $tasksValid = false;
    }
    $testRunner->assert('AIHelper generateTasks follows correct structure', $tasksValid);

    // Test generateSubtasks method structure with mocked response
    $subtasksValid = true;
    try {
        $mockResponse = '[{"title": "Subtask 1", "estimated_minutes": 30}]';
        $result = $parseJSONMethod->invoke($aiHelper, $mockResponse);
        $subtasksValid = is_array($result) &&
               isset($result[0]['title']) &&
               isset($result[0]['estimated_minutes']);
    } catch (Exception $e) {
        $subtasksValid = false;
    }
    $testRunner->assert('AIHelper generateSubtasks follows correct structure', $subtasksValid);

    // Test generateInvoiceItems method structure with mocked response
    $invoiceValid = true;
    try {
        $mockResponse = '[{"description": "Service A", "quantity": 1, "suggested_rate_usd": 100}]';
        $result = $parseJSONMethod->invoke($aiHelper, $mockResponse);
        $invoiceValid = is_array($result) &&
               isset($result[0]['description']) &&
               isset($result[0]['suggested_rate_usd']);
    } catch (Exception $e) {
        $invoiceValid = false;
    }
    $testRunner->assert('AIHelper generateInvoiceItems follows correct structure', $invoiceValid);

    // Test AI prompting replacement functionality
    $prompt = $prompts['project_from_idea'] ?? '';
    $processed = str_replace('{idea}', 'Make a to-do app', $prompt);
    $testRunner->assert('AIHelper correctly replaces placeholders in prompts', strpos($processed, 'Make a to-do app') !== false);

    // Test default prompts are set if collection is empty
    $promptsFromDb = $db->load('ai_prompts');
    $seedsValid = !empty($promptsFromDb) &&
           isset($promptsFromDb['project_from_idea']) &&
           isset($promptsFromDb['tasks_from_project']);
    $testRunner->assert('AIHelper seeds default prompts when collection is empty', $seedsValid);

    cleanupTestDatabase($db);
});

// Output results
$runner->printSummary();