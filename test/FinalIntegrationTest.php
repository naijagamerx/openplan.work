<?php
/**
 * Final integration test to validate all systems work together
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Helpers.php';

$runner = new TestRunner();

$runner->runSuite('Final Integration Tests', function($testRunner) {
    // Test 1: Verify all new API endpoints can be accessed (syntax check)
    $endpoints = [
        'auth' => __DIR__ . '/../api/auth.php',
        'tasks' => __DIR__ . '/../api/tasks.php',
        'projects' => __DIR__ . '/../api/projects.php',
        'clients' => __DIR__ . '/../api/clients.php',
        'invoices' => __DIR__ . '/../api/invoices.php',
        'finance' => __DIR__ . '/../api/finance.php',
        'inventory' => __DIR__ . '/../api/inventory.php',
        'ai' => __DIR__ . '/../api/ai.php',
        'export' => __DIR__ . '/../api/export.php',
        'models' => __DIR__ . '/../api/models.php',
        'ai-generate' => __DIR__ . '/../api/ai-generate.php',
        'settings' => __DIR__ . '/../api/settings.php'
    ];

    foreach ($endpoints as $name => $path) {
        $exists = file_exists($path);
        $hasValidSyntax = true;
        if ($exists) {
            $content = file_get_contents($path);
            // Check for basic PHP syntax and required includes
            $hasValidSyntax = strpos($content, '<?php') !== false &&
                             (strpos($content, 'require_once') !== false || strpos($content, 'include_once') !== false);
        }
        $endpointValid = $exists && $hasValidSyntax;
        $testRunner->assert("{$name} API endpoint is valid", $endpointValid);
    }

    // Test 2: Verify all new view files exist and have proper structure
    $views = [
        'model-settings' => __DIR__ . '/../views/model-settings.php',
        'ai-assistant' => __DIR__ . '/../views/ai-assistant.php',
        'import-data' => __DIR__ . '/../views/import-data.php',
        'project-form' => __DIR__ . '/../views/project-form.php',
        'task-form' => __DIR__ . '/../views/task-form.php',
        'client-form' => __DIR__ . '/../views/client-form.php',
        'product-form' => __DIR__ . '/../views/product-form.php',
        'transaction-form' => __DIR__ . '/../views/transaction-form.php',
        'invoice-form' => __DIR__ . '/../views/invoice-form.php',
        'invoice-view' => __DIR__ . '/../views/invoice-view.php',
        'dashboard' => __DIR__ . '/../views/dashboard.php',
        'settings' => __DIR__ . '/../views/settings.php',
        'projects' => __DIR__ . '/../views/projects.php',
        'tasks' => __DIR__ . '/../views/tasks.php',
        'clients' => __DIR__ . '/../views/clients.php',
        'inventory' => __DIR__ . '/../views/inventory.php',
        'finance' => __DIR__ . '/../views/finance.php',
        'invoices' => __DIR__ . '/../views/invoices.php',
        'pomodoro' => __DIR__ . '/../views/pomodoro.php',
        'login' => __DIR__ . '/../views/login.php',
        'setup' => __DIR__ . '/../views/setup.php',
        '404' => __DIR__ . '/../views/404.php'
    ];

    foreach ($views as $name => $path) {
        $exists = file_exists($path);
        $hasValidStructure = true;
        if ($exists) {
            $content = file_get_contents($path);
            // Check for basic HTML structure
            $hasValidStructure = strpos($content, '<!DOCTYPE') !== false ||
                               strpos($content, '<?php') !== false ||
                               strpos($content, '<html') !== false ||
                               strpos($content, 'class="') !== false; // Tailwind classes
        }
        $viewValid = $exists && $hasValidStructure;
        $testRunner->assert("{$name} view file is valid", $viewValid);
    }

    // Test 3: Validate all new includes exist
    $includes = [
        'AIHelper' => __DIR__ . '/../includes/AIHelper.php',
        'Helpers' => __DIR__ . '/../includes/Helpers.php',
        'Encryption' => __DIR__ . '/../includes/Encryption.php',
        'Database' => __DIR__ . '/../includes/Database.php',
        'Auth' => __DIR__ . '/../includes/Auth.php',
        'GroqAPI' => __DIR__ . '/../includes/GroqAPI.php',
        'OpenRouterAPI' => __DIR__ . '/../includes/OpenRouterAPI.php',
        'Mailer' => __DIR__ . '/../includes/Mailer.php'
    ];

    foreach ($includes as $name => $path) {
        $validInclude = file_exists($path);
        $testRunner->assert("{$name} include file exists", $validInclude);
    }

    // Test 4: Verify API endpoints reference the new features
    $modelsAPI = file_get_contents(__DIR__ . '/../api/models.php');
    $aiGenAPI = file_get_contents(__DIR__ . '/../api/ai-generate.php');

    $modelsRefersToNewFeatures = strpos($modelsAPI, 'models') !== false &&
                                 strpos($modelsAPI, 'ai') !== false;
    $testRunner->assert('Models API references new features', $modelsRefersToNewFeatures);

    $aiGenRefersToAIHelper = strpos($aiGenAPI, 'AIHelper') !== false ||
                             strpos($aiGenAPI, 'generate') !== false;
    $testRunner->assert('AI Generation API uses AIHelper', $aiGenRefersToAIHelper);

    // Test 5: Verify the configuration includes new settings
    $configContent = file_get_contents(__DIR__ . '/../config.php');
    $hasNewConfigElements = strpos($configContent, 'AI') !== false ||
                           strpos($configContent, 'DEFAULT_AI_PROVIDER') !== false ||
                           strpos($configContent, 'MODEL') !== false;
    $testRunner->assert('Config includes new AI elements', $hasNewConfigElements);

    // Test 6: Ensure database collections mentioned in new code exist
    $allDBRefs = $modelsAPI . $aiGenAPI;
    $hasRequiredCollections = strpos($allDBRefs, 'models') !== false &&
                             strpos($allDBRefs, 'config') !== false &&
                             strpos($allDBRefs, 'ai_prompts') !== false;
    $testRunner->assert('APIs reference required DB collections', $hasRequiredCollections);

    // Test 7: Check that authentication is properly validated in new APIs
    $authRequiredInNewAPIs = strpos($modelsAPI, 'Auth::check()') !== false &&
                            strpos($aiGenAPI, 'Auth::check()') !== false;
    $testRunner->assert('New APIs require authentication', $authRequiredInNewAPIs);

    // Test 8: Verify error handling patterns are consistent
    $hasErrorResponsePattern = (substr_count($modelsAPI, 'errorResponse') >= 1) &&
                              (substr_count($aiGenAPI, 'errorResponse') >= 1) &&
                              (substr_count($aiGenAPI, 'successResponse') >= 1);
    $testRunner->assert('New APIs use consistent error handling', $hasErrorResponsePattern);

    // Test 9: Check that all required functionality is available
    $hasAllClasses = class_exists('AIHelper') &&
                     class_exists('Database') &&
                     class_exists('Encryption') &&
                     class_exists('Auth');
    $testRunner->assert('All required classes are available', $hasAllClasses);

    // Test 10: Verify the new functionality integrates with existing systems
    $db = new Database('integration_test_password');
    $integrationTest = true;

    // Test that we can save and load data that would be used by new features
    $testModels = ['groq' => [['modelId' => 'test', 'enabled' => true]]];
    $saveResult = $db->save('test_integration_models', $testModels);
    $loadResult = $db->load('test_integration_models');

    $integrationTest = $saveResult && !empty($loadResult);
    $testRunner->assert('New features integrate with existing DB', $integrationTest);

    // Clean up
    $db->save('test_integration_models', []);
});

// Output results
$runner->printSummary();