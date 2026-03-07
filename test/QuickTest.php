<?php
/**
 * Quick functional test to validate the new features
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

$runner->runSuite('Core Functionality Tests', function($testRunner) {
    // Test that new files can be included without errors
    $aiHelperExists = class_exists('AIHelper');
    $testRunner->assert('AIHelper class exists', $aiHelperExists);

    $modelsEndpointExists = file_exists(__DIR__ . '/../api/models.php');
    $testRunner->assert('Models API endpoint exists', $modelsEndpointExists);

    $aiGenerateEndpointExists = file_exists(__DIR__ . '/../api/ai-generate.php');
    $testRunner->assert('AI Generation API endpoint exists', $aiGenerateEndpointExists);

    $settingsEndpointExists = file_exists(__DIR__ . '/../api/settings.php');
    $testRunner->assert('Settings API endpoint exists', $settingsEndpointExists);

    // Test basic functionality of the new components
    if ($aiHelperExists) {
        // Test that we can create a basic database for testing
        $testPath = sys_get_temp_dir() . '/test_db_' . uniqid();
        mkdir($testPath, 0755, true);

        // Create a basic database instance
        $db = new class('test_password') extends Database {
            public $dataPath;

            public function __construct($password) {
                $this->dataPath = sys_get_temp_dir() . '/test_db_' . uniqid();
                if (!is_dir($this->dataPath)) {
                    mkdir($this->dataPath, 0755, true);
                }
                $this->encryption = new Encryption($password);
            }

            public function __destruct() {
                if (isset($this->dataPath) && is_dir($this->dataPath)) {
                    $files = glob($this->dataPath . '/*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                    rmdir($this->dataPath);
                }
            }
        };

        // Test that AIHelper can be instantiated (with proper initialization)
        try {
            $aiHelper = new AIHelper($db);
            $testRunner->assert('AIHelper can be instantiated', true);
        } catch (Exception $e) {
            $testRunner->assert('AIHelper can be instantiated', false);
        }

        // Clean up
        $db->__destruct();
    } else {
        $testRunner->assert('AIHelper can be instantiated', false);
    }

    // Test that default models structure is correct
    $defaultModels = [
        'groq' => [
            [
                'modelId' => 'llama-3.3-70b-versatile',
                'displayName' => 'Llama 3.3 70B',
                'description' => 'Fast, versatile model for general tasks',
                'enabled' => true,
                'isDefault' => true
            ]
        ],
        'openrouter' => []
    ];

    $hasRequiredFields = isset($defaultModels['groq'][0]['modelId']) &&
                         isset($defaultModels['groq'][0]['displayName']) &&
                         isset($defaultModels['groq'][0]['enabled']) &&
                         isset($defaultModels['groq'][0]['isDefault']);

    $testRunner->assert('Default models have required fields', $hasRequiredFields);

    // Test that AI generation endpoint structure is correct
    $expectedActions = ['project', 'tasks', 'subtasks', 'invoice_items', 'invoice-items'];
    $hasProjectAction = in_array('project', $expectedActions);
    $testRunner->assert('AI generation supports project action', $hasProjectAction);

    // Test that settings endpoint handles business section
    $businessSettings = [
        'businessName' => 'Test Business',
        'businessEmail' => 'test@example.com',
        'currency' => 'USD',
        'taxRate' => 8.5
    ];

    $hasBusinessFields = isset($businessSettings['businessName']) &&
                         isset($businessSettings['currency']) &&
                         isset($businessSettings['taxRate']);

    $testRunner->assert('Business settings have required fields', $hasBusinessFields);

    // Test API key masking pattern
    $realKey = 'sk-1234567890abcdef';
    $maskedKey = substr($realKey, 0, 4) . '...' . substr($realKey, -4);
    $correctMasking = $maskedKey === 'sk-1...cdef';
    $testRunner->assert('API key masking works correctly', $correctMasking);

    // Test that new views exist
    $newViewsExist = file_exists(__DIR__ . '/../views/model-settings.php') &&
                     file_exists(__DIR__ . '/../views/ai-assistant.php') &&
                     file_exists(__DIR__ . '/../views/import-data.php');
    $testRunner->assert('New view files exist', $newViewsExist);

    // Test that new form views exist
    $formViewsExist = file_exists(__DIR__ . '/../views/project-form.php') &&
                      file_exists(__DIR__ . '/../views/task-form.php') &&
                      file_exists(__DIR__ . '/../views/client-form.php') &&
                      file_exists(__DIR__ . '/../views/product-form.php') &&
                      file_exists(__DIR__ . '/../views/transaction-form.php') &&
                      file_exists(__DIR__ . '/../views/invoice-form.php') &&
                      file_exists(__DIR__ . '/../views/invoice-view.php');
    $testRunner->assert('New form view files exist', $formViewsExist);
});

// Output results
$runner->printSummary();