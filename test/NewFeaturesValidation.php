<?php
/**
 * Validate that new features work correctly without instantiation issues
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

$runner->runSuite('New Features Validation', function($testRunner) {
    // Test 1: Check that all new files exist and can be included
    $aiHelperExists = file_exists(__DIR__ . '/../includes/AIHelper.php');
    $testRunner->assert('AIHelper.php file exists', $aiHelperExists);

    if ($aiHelperExists) {
        // Check that the class is properly defined in the file
        $aiHelperContent = file_get_contents(__DIR__ . '/../includes/AIHelper.php');
        $hasClassDefinition = strpos($aiHelperContent, 'class AIHelper') !== false;
        $testRunner->assert('AIHelper has proper class definition', $hasClassDefinition);
    } else {
        $testRunner->assert('AIHelper has proper class definition', false);
    }

    // Test 2: Check new API endpoints exist
    $apiEndpoints = [
        'models.php' => __DIR__ . '/../api/models.php',
        'ai-generate.php' => __DIR__ . '/../api/ai-generate.php',
        'settings.php' => __DIR__ . '/../api/settings.php'
    ];

    foreach ($apiEndpoints as $name => $path) {
        $exists = file_exists($path);
        $testRunner->assert("API endpoint {$name} exists", $exists);
    }

    // Test 3: Check new view files exist
    $viewFiles = [
        'model-settings.php' => __DIR__ . '/../views/model-settings.php',
        'ai-assistant.php' => __DIR__ . '/../views/ai-assistant.php',
        'import-data.php' => __DIR__ . '/../views/import-data.php',
        'project-form.php' => __DIR__ . '/../views/project-form.php',
        'task-form.php' => __DIR__ . '/../views/task-form.php',
        'client-form.php' => __DIR__ . '/../views/client-form.php',
        'product-form.php' => __DIR__ . '/../views/product-form.php',
        'transaction-form.php' => __DIR__ . '/../views/transaction-form.php',
        'invoice-form.php' => __DIR__ . '/../views/invoice-form.php',
        'invoice-view.php' => __DIR__ . '/../views/invoice-view.php'
    ];

    foreach ($viewFiles as $name => $path) {
        $exists = file_exists($path);
        $testRunner->assert("View file {$name} exists", $exists);
    }

    // Test 4: Check that new API endpoints have correct structure
    $modelsContent = file_exists(__DIR__ . '/../api/models.php') ?
                     file_get_contents(__DIR__ . '/../api/models.php') : '';
    $hasModelsActions = strpos($modelsContent, 'case \'list\'') !== false &&
                        strpos($modelsContent, 'case \'add\'') !== false &&
                        strpos($modelsContent, 'case \'update\'') !== false;
    $testRunner->assert('Models API has proper action structure', $hasModelsActions);

    // Test 5: Check AI generation API structure
    $aiGenContent = file_exists(__DIR__ . '/../api/ai-generate.php') ?
                    file_get_contents(__DIR__ . '/../api/ai-generate.php') : '';
    $hasAiActions = strpos($aiGenContent, 'case \'project\'') !== false &&
                    strpos($aiGenContent, 'case \'tasks\'') !== false &&
                    strpos($aiGenContent, 'case \'subtasks\'') !== false;
    $testRunner->assert('AI Generation API has proper action structure', $hasAiActions);

    // Test 6: Check settings API structure
    $settingsContent = file_exists(__DIR__ . '/../api/settings.php') ?
                       file_get_contents(__DIR__ . '/../api/settings.php') : '';
    $hasSettingsSections = strpos($settingsContent, '\'business\'') !== false &&
                           strpos($settingsContent, '\'api\'') !== false;
    $testRunner->assert('Settings API has proper sections', $hasSettingsSections);

    // Test 7: Validate AIHelper methods exist
    if ($aiHelperExists) {
        $methodsExist = strpos($aiHelperContent, 'function generateProject') !== false &&
                        strpos($aiHelperContent, 'function generateTasks') !== false &&
                        strpos($aiHelperContent, 'function generateSubtasks') !== false &&
                        strpos($aiHelperContent, 'function generateInvoiceItems') !== false;
        $testRunner->assert('AIHelper has required generation methods', $methodsExist);
    } else {
        $testRunner->assert('AIHelper has required generation methods', false);
    }

    // Test 8: Check that new config collection is referenced
    $hasAiPrompts = strpos($aiHelperContent, 'ai_prompts') !== false;
    $testRunner->assert('AIHelper uses ai_prompts collection', $hasAiPrompts);

    // Test 9: Check model structure in models API
    if (file_exists(__DIR__ . '/../api/models.php')) {
        $modelsFile = file_get_contents(__DIR__ . '/../api/models.php');
        $hasModelStructure = strpos($modelsFile, '\'modelId\'') !== false &&
                             strpos($modelsFile, '\'displayName\'') !== false &&
                             strpos($modelsFile, '\'enabled\'') !== false &&
                             strpos($modelsFile, '\'isDefault\'') !== false;
        $testRunner->assert('Models API has proper model structure', $hasModelStructure);
    } else {
        $testRunner->assert('Models API has proper model structure', false);
    }

    // Test 10: Check for proper error handling in new APIs
    $hasErrorHandling = strpos($aiGenContent, 'errorResponse') !== false &&
                        strpos($settingsContent, 'errorResponse') !== false;
    $testRunner->assert('New APIs have error handling', $hasErrorHandling);

    // Test 11: Check for required includes in new files
    $hasRequiredIncludes = strpos($aiGenContent, 'require_once __DIR__') !== false &&
                           strpos($settingsContent, 'require_once __DIR__') !== false;
    $testRunner->assert('New APIs have required includes', $hasRequiredIncludes);

    // Test 12: Check that AI generation handles different providers
    $hasProviderSupport = strpos($aiGenContent, 'groq') !== false &&
                          strpos($aiGenContent, 'openrouter') !== false;
    $testRunner->assert('AI Generation supports multiple providers', $hasProviderSupport);
});

// Output results
$runner->printSummary();