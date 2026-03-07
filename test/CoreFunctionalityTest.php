<?php
/**
 * Core functionality validation tests
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Helpers.php';

$runner = new TestRunner();

$runner->runSuite('Core Functionality Tests', function($testRunner) {
    // Test basic encryption functionality
    $encryption = new Encryption('test_password_123');
    $testData = ['name' => 'John Doe', 'balance' => 1234.56];

    $encrypted = $encryption->encrypt($testData);
    $decrypted = $encryption->decrypt($encrypted);

    $encryptionWorks = $decrypted === $testData;
    $testRunner->assert('Basic encryption/decryption works', $encryptionWorks);

    // Test password hashing
    $password = 'test_password_123';
    $hash = Encryption::hashPassword($password);
    $verification = Encryption::verifyPassword($password, $hash);
    $wrongVerification = Encryption::verifyPassword('wrong_password', $hash);

    $passwordHashingWorks = $verification && !$wrongVerification;
    $testRunner->assert('Password hashing/verification works', $passwordHashingWorks);

    // Test Database basic functionality (with default DATA_PATH)
    $db = new Database('temp_test_password');

    // Test ID generation
    $id1 = $db->generateId();
    $id2 = $db->generateId();
    $idsAreDifferent = $id1 !== $id2 && strlen($id1) === 36 && strlen($id2) === 36;
    $testRunner->assert('Database ID generation works', $idsAreDifferent);

    // Test simple save/load
    $testRecord = ['name' => 'Test Item', 'value' => 100, 'id' => $db->generateId()];
    $saveResult = $db->insert('test_collection', $testRecord);
    $testRunner->assert('Database insert works', $saveResult);

    // Test that we can find the record back
    $foundRecord = $db->findById('test_collection', $testRecord['id']);
    $recordFound = $foundRecord !== null && $foundRecord['name'] === 'Test Item';
    $testRunner->assert('Database find by ID works', $recordFound);

    // Test update functionality
    $updateResult = $db->update('test_collection', $testRecord['id'], ['value' => 200]);
    $updatedRecord = $db->findById('test_collection', $testRecord['id']);
    $recordUpdated = $updateResult && isset($updatedRecord['value']) && $updatedRecord['value'] == 200;
    $testRunner->assert('Database update works', $recordUpdated);

    // Test delete functionality
    $deleteResult = $db->delete('test_collection', $testRecord['id']);
    $deletedRecord = $db->findById('test_collection', $testRecord['id']);
    $recordDeleted = $deleteResult && $deletedRecord === null;
    $testRunner->assert('Database delete works', $recordDeleted);

    // Test helper functions
    $escaped = e('<script>alert("xss")</script>');
    $htmlEscaped = $escaped === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
    $testRunner->assert('HTML escaping helper works', $htmlEscaped);

    // Test date formatting
    $formatted = formatDate('2024-01-15 14:30:00', 'Y-m-d');
    $dateFormatCorrect = $formatted === '2024-01-15';
    $testRunner->assert('Date formatting helper works', $dateFormatCorrect);

    // Test currency formatting
    $currencyFormatted = formatCurrency(1234.56, 'USD');
    $currencyCorrect = $currencyFormatted === '$1,234.56';
    $testRunner->assert('Currency formatting helper works', $currencyCorrect);

    // Test email validation
    $validEmail = isValidEmail('test@example.com');
    $invalidEmail = isValidEmail('invalid-email');
    $emailValidationWorks = $validEmail && !$invalidEmail;
    $testRunner->assert('Email validation helper works', $emailValidationWorks);

    // Test slugify
    $slug = slugify('Hello World Test');
    $slugCorrect = $slug === 'hello-world-test';
    $testRunner->assert('Slugify helper works', $slugCorrect);

    // Test priority class function
    $priorityClass = priorityClass('high');
    $priorityClassCorrect = $priorityClass === 'bg-orange-100 text-orange-800';
    $testRunner->assert('Priority class helper works', $priorityClassCorrect);

    // Test status class function
    $statusClass = statusClass('done');
    $statusClassCorrect = $statusClass === 'bg-green-100 text-green-800';
    $testRunner->assert('Status class helper works', $statusClassCorrect);

    // Test that new AI-related collections can be created
    $aiPrompts = [
        'test_prompt' => 'This is a test prompt with {variable}'
    ];
    $savePrompts = $db->save('ai_prompts', $aiPrompts);
    $loadedPrompts = $db->load('ai_prompts');
    $aiPromptsWork = $savePrompts && isset($loadedPrompts['test_prompt']);
    $testRunner->assert('AI prompts collection works', $aiPromptsWork);

    // Test models collection structure
    $models = [
        'groq' => [
            [
                'id' => $db->generateId(),
                'modelId' => 'test-model',
                'displayName' => 'Test Model',
                'enabled' => true,
                'isDefault' => false
            ]
        ]
    ];
    $saveModels = $db->save('models', $models);
    $loadedModels = $db->load('models');
    $modelsWork = $saveModels && isset($loadedModels['groq']) && count($loadedModels['groq']) > 0;
    $testRunner->assert('Models collection works', $modelsWork);

    // Clean up test data
    $db->save('test_collection', []);
    $db->save('ai_prompts', []);
    $db->save('models', []);
});

// Output results
$runner->printSummary();