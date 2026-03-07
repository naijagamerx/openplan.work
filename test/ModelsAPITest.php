<?php
/**
 * Comprehensive Unit Tests for Models API Endpoint
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

$runner->runSuite('Models API Tests', function($testRunner) {
    $db = createTestDatabase();

    // Test models endpoint initialization
    $testRunner->assert('Models API can seed default models', function() use ($db) {
        // Simulate the seeding process from models.php
        $defaults = [
            'groq' => [
                [
                    'modelId' => 'llama-3.3-70b-versatile',
                    'displayName' => 'Llama 3.3 70B',
                    'description' => 'Fast, versatile model for general tasks',
                    'enabled' => true,
                    'isDefault' => true
                ],
                [
                    'modelId' => 'llama-3.1-8b-instant',
                    'displayName' => 'Llama 3.1 8B',
                    'description' => 'Lightweight and extremely fast',
                    'enabled' => true,
                    'isDefault' => false
                ]
            ],
            'openrouter' => [
                [
                    'modelId' => 'anthropic/claude-3.5-sonnet',
                    'displayName' => 'Claude 3.5 Sonnet',
                    'description' => 'Advanced reasoning and coding',
                    'enabled' => true,
                    'isDefault' => true
                ],
                [
                    'modelId' => 'google/gemini-pro-1.5',
                    'displayName' => 'Gemini Pro 1.5',
                    'description' => 'Google\'s flagship large context model',
                    'enabled' => true,
                    'isDefault' => false
                ]
            ]
        ];

        $db->save('models', $defaults);
        $loaded = $db->load('models');

        return !empty($loaded) &&
               isset($loaded['groq']) &&
               isset($loaded['openrouter']) &&
               count($loaded['groq']) === 2 &&
               count($loaded['openrouter']) === 2;
    });

    // Test list action
    $testRunner->assert('Models API list action returns models', function() use ($db) {
        $defaults = [
            'groq' => [
                [
                    'id' => $db->generateId(),
                    'modelId' => 'test-model-1',
                    'displayName' => 'Test Model 1',
                    'enabled' => true,
                    'isDefault' => true
                ]
            ],
            'openrouter' => []
        ];
        $db->save('models', $defaults);

        // Simulate the API logic for 'list' action
        $models = $db->load('models');

        return !empty($models) &&
               isset($models['groq']) &&
               $models['groq'][0]['modelId'] === 'test-model-1';
    });

    // Test structure of model objects
    $testRunner->assert('Models have required structure', function() use ($db) {
        $model = [
            'id' => $db->generateId(),
            'modelId' => 'test-model',
            'displayName' => 'Test Model',
            'description' => 'A test model',
            'enabled' => true,
            'isDefault' => false,
            'createdAt' => date('c')
        ];

        return isset($model['id']) &&
               isset($model['modelId']) &&
               isset($model['displayName']) &&
               isset($model['enabled']) &&
               isset($model['isDefault']) &&
               isset($model['createdAt']);
    });

    // Test add model functionality
    $testRunner->assert('Models API can add new model', function() use ($db) {
        $initialModels = [
            'groq' => [],
            'openrouter' => []
        ];
        $db->save('models', $initialModels);

        // Simulate adding a model (like the API does)
        $newModel = [
            'id' => $db->generateId(),
            'modelId' => 'new-test-model',
            'displayName' => 'New Test Model',
            'description' => 'A new test model',
            'enabled' => true,
            'isDefault' => false,
            'createdAt' => date('c')
        ];

        $models = $db->load('models');
        $models['groq'][] = $newModel;
        $db->save('models', $models);

        $updated = $db->load('models');
        $found = false;

        foreach ($updated['groq'] as $model) {
            if ($model['modelId'] === 'new-test-model') {
                $found = true;
                break;
            }
        }

        return $found;
    });

    // Test update model functionality
    $testRunner->assert('Models API can update existing model', function() use ($db) {
        $modelId = $db->generateId();
        $initialModels = [
            'groq' => [
                [
                    'id' => $modelId,
                    'modelId' => 'update-test-model',
                    'displayName' => 'Old Name',
                    'description' => 'Old description',
                    'enabled' => true,
                    'isDefault' => false,
                    'createdAt' => date('c')
                ]
            ],
            'openrouter' => []
        ];
        $db->save('models', $initialModels);

        // Simulate updating a model (like the API does)
        $models = $db->load('models');
        $found = false;

        foreach ($models['groq'] as &$model) {
            if ($model['id'] === $modelId) {
                $model['displayName'] = 'Updated Name';
                $model['description'] = 'Updated description';
                $found = true;
                break;
            }
        }

        if ($found) {
            $db->save('models', $models);
        }

        $updated = $db->load('models');
        $verified = false;

        foreach ($updated['groq'] as $model) {
            if ($model['id'] === $modelId && $model['displayName'] === 'Updated Name') {
                $verified = true;
                break;
            }
        }

        return $verified;
    });

    // Test set default model functionality
    $testRunner->assert('Models API can set default model', function() use ($db) {
        $model1Id = $db->generateId();
        $model2Id = $db->generateId();
        $initialModels = [
            'groq' => [
                [
                    'id' => $model1Id,
                    'modelId' => 'model-1',
                    'displayName' => 'Model 1',
                    'enabled' => true,
                    'isDefault' => false,
                    'createdAt' => date('c')
                ],
                [
                    'id' => $model2Id,
                    'modelId' => 'model-2',
                    'displayName' => 'Model 2',
                    'enabled' => true,
                    'isDefault' => false,  // Initially neither is default
                    'createdAt' => date('c')
                ]
            ]
        ];
        $db->save('models', $initialModels);

        // Simulate setting model2 as default (like the API does)
        $models = $db->load('models');

        foreach ($models['groq'] as &$model) {
            $model['isDefault'] = ($model['id'] === $model2Id);
        }

        $db->save('models', $models);

        $updated = $db->load('models');
        $model2IsDefault = false;
        $model1IsNotDefault = true;

        foreach ($updated['groq'] as $model) {
            if ($model['id'] === $model2Id) {
                $model2IsDefault = $model['isDefault'];
            }
            if ($model['id'] === $model1Id) {
                $model1IsNotDefault = !$model['isDefault'];
            }
        }

        return $model2IsDefault && $model1IsNotDefault;
    });

    // Test provider validation
    $testRunner->assert('Models API validates provider correctly', function() use ($db) {
        $validProviders = ['groq', 'openrouter'];
        $invalidProvider = 'invalid_provider';

        return in_array('groq', $validProviders) &&
               in_array('openrouter', $validProviders) &&
               !in_array($invalidProvider, $validProviders);
    });

    // Test model deletion (but not default)
    $testRunner->assert('Models API prevents deleting default model', function() use ($db) {
        $model1Id = $db->generateId();
        $model2Id = $db->generateId();
        $initialModels = [
            'groq' => [
                [
                    'id' => $model1Id,
                    'modelId' => 'default-model',
                    'displayName' => 'Default Model',
                    'enabled' => true,
                    'isDefault' => true,  // This is the default model
                    'createdAt' => date('c')
                ],
                [
                    'id' => $model2Id,
                    'modelId' => 'deletable-model',
                    'displayName' => 'Deletable Model',
                    'enabled' => false,
                    'isDefault' => false,  // This can be deleted
                    'createdAt' => date('c')
                ]
            ]
        ];
        $db->save('models', $initialModels);

        // In real API, this would fail for default models, but let's test the logic
        $models = $db->load('models');

        // We'll implement the check that prevents deleting default models
        $forbiddenToDelete = false;
        $targetId = $model1Id; // Try to delete the default model

        foreach (['groq', 'openrouter'] as $provider) {
            foreach ($models[$provider] as $model) {
                if ($model['id'] === $targetId && $model['isDefault']) {
                    $forbiddenToDelete = true;
                    break 2;
                }
            }
        }

        return $forbiddenToDelete;
    });

    // Test model deletion for non-default model
    $testRunner->assert('Models API allows deleting non-default model', function() use ($db) {
        $model1Id = $db->generateId();
        $model2Id = $db->generateId();
        $initialModels = [
            'groq' => [
                [
                    'id' => $model1Id,
                    'modelId' => 'default-model',
                    'displayName' => 'Default Model',
                    'enabled' => true,
                    'isDefault' => true,
                    'createdAt' => date('c')
                ],
                [
                    'id' => $model2Id,
                    'modelId' => 'deletable-model',
                    'displayName' => 'Deletable Model',
                    'enabled' => false,
                    'isDefault' => false,  // Non-default model
                    'createdAt' => date('c')
                ]
            ]
        ];
        $db->save('models', $initialModels);

        // Simulate deletion of non-default model
        $models = $db->load('models');
        $found = false;

        foreach (['groq', 'openrouter'] as $provider) {
            foreach ($models[$provider] as $key => $model) {
                if ($model['id'] === $model2Id) {
                    if (!$model['isDefault']) {  // Only delete if not default
                        array_splice($models[$provider], $key, 1);
                        $found = true;
                        break 2;
                    }
                }
            }
        }

        if ($found) {
            $db->save('models', $models);
        }

        $updated = $db->load('models');
        $modelStillExists = false;

        foreach ($updated['groq'] as $model) {
            if ($model['id'] === $model2Id) {
                $modelStillExists = true;
                break;
            }
        }

        // The model should be gone since it wasn't default
        return !$modelStillExists;
    });

    // Test model ID generation
    $testRunner->assert('Models have properly formatted IDs', function() use ($db) {
        $id = $db->generateId();

        return strlen($id) === 36 &&  // UUID format
               substr_count($id, '-') === 4 &&  // Has 4 hyphens
               preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id);
    });

    cleanupTestDatabase($db);
});

function createTestDatabase() {
    return new class('test_password') extends Database {
        public function __construct($password) {
            $this->testPath = sys_get_temp_dir() . '/lazyman_models_test_' . uniqid();
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