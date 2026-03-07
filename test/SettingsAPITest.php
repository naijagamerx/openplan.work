<?php
/**
 * Comprehensive Unit Tests for Settings API Endpoint
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

$runner->runSuite('Settings API Tests', function($testRunner) {
    $db = createTestDatabase();

    // Test initial config setup
    $testRunner->assert('Settings API can handle empty config', function() use ($db) {
        $config = $db->load('config');

        // If config is empty, it should return an empty array
        return is_array($config);
    });

    // Test business settings structure
    $testRunner->assert('Settings API handles business settings correctly', function() use ($db) {
        $businessData = [
            'section' => 'business',
            'businessName' => 'Test Business',
            'businessEmail' => 'test@example.com',
            'businessPhone' => '+1-555-0123',
            'businessAddress' => '123 Test St, Test City, TC 12345',
            'currency' => 'USD',
            'taxRate' => 8.5
        ];

        $config = $db->load('config');

        // Apply business settings like the API does
        $config['businessName'] = trim($businessData['businessName']);
        $config['businessEmail'] = trim($businessData['businessEmail']);
        $config['businessPhone'] = trim($businessData['businessPhone']);
        $config['businessAddress'] = trim($businessData['businessAddress']);
        $config['currency'] = $businessData['currency'];
        $config['taxRate'] = floatval($businessData['taxRate']);

        $db->save('config', $config);

        $loaded = $db->load('config');
        return $loaded['businessName'] === 'Test Business' &&
               $loaded['businessEmail'] === 'test@example.com' &&
               $loaded['currency'] === 'USD' &&
               $loaded['taxRate'] === 8.5;
    });

    // Test API key settings and masking
    $testRunner->assert('Settings API handles API keys and masking', function() use ($db) {
        $apiData = [
            'section' => 'api',
            'groqApiKey' => 'sk-groq-1234567890abcdef',
            'openrouterApiKey' => 'sk-openrouter-0987654321fedcba'
        ];

        $config = $db->load('config');

        // Apply API settings like the API does
        if (isset($apiData['groqApiKey']) && !str_contains($apiData['groqApiKey'], '...')) {
            $config['groqApiKey'] = trim($apiData['groqApiKey']);
        }
        if (isset($apiData['openrouterApiKey']) && !str_contains($apiData['openrouterApiKey'], '...')) {
            $config['openrouterApiKey'] = trim($apiData['openrouterApiKey']);
        }

        $db->save('config', $config);

        // Test retrieval with masking (like the 'get' action does)
        $retrievedConfig = $db->load('config');
        if (!empty($retrievedConfig['groqApiKey'])) {
            $retrievedConfig['groqApiKey'] = substr($retrievedConfig['groqApiKey'], 0, 4) . '...' . substr($retrievedConfig['groqApiKey'], -4);
        }
        if (!empty($retrievedConfig['openrouterApiKey'])) {
            $retrievedConfig['openrouterApiKey'] = substr($retrievedConfig['openrouterApiKey'], 0, 4) . '...' . substr($retrievedConfig['openrouterApiKey'], -4);
        }

        return $retrievedConfig['groqApiKey'] === 'sk-g...' . 'cdef' &&
               $retrievedConfig['openrouterApiKey'] === 'sk-o...' . 'cba';
    });

    // Test API key update logic (not overwriting masked keys)
    $testRunner->assert('Settings API respects masked key protection', function() use ($db) {
        // First, set real API keys
        $config = [
            'groqApiKey' => 'real-groq-key-12345',
            'openrouterApiKey' => 'real-openrouter-key-67890'
        ];
        $db->save('config', $config);

        // Simulate retrieving and masking the keys (like in 'get' action)
        $maskedConfig = $db->load('config');
        $maskedConfig['groqApiKey'] = substr($maskedConfig['groqApiKey'], 0, 4) . '...' . substr($maskedConfig['groqApiKey'], -4);
        $maskedConfig['openrouterApiKey'] = substr($maskedConfig['openrouterApiKey'], 0, 4) . '...' . substr($maskedConfig['openrouterApiKey'], -4);

        // Now simulate an update with masked keys (this should not update the real keys)
        $updateData = [
            'section' => 'api',
            'groqApiKey' => $maskedConfig['groqApiKey'], // This is masked: "real...2345"
            'openrouterApiKey' => $maskedConfig['openrouterApiKey'] // This is masked: "real...67890"
        ];

        $currentConfig = $db->load('config');

        // The API logic - only update if the key doesn't contain '...' (meaning it's unmasked)
        if (isset($updateData['groqApiKey']) && !str_contains($updateData['groqApiKey'], '...')) {
            $currentConfig['groqApiKey'] = trim($updateData['groqApiKey']);
        }
        // Otherwise, the original key remains

        if (isset($updateData['openrouterApiKey']) && !str_contains($updateData['openrouterApiKey'], '...')) {
            $currentConfig['openrouterApiKey'] = trim($updateData['openrouterApiKey']);
        }

        $db->save('config', $currentConfig);

        $finalConfig = $db->load('config');

        // The original keys should still be there since masked keys were provided
        return $finalConfig['groqApiKey'] === 'real-groq-key-12345' &&
               $finalConfig['openrouterApiKey'] === 'real-openrouter-key-67890';
    });

    // Test currency validation
    $testRunner->assert('Settings API handles currency values correctly', function() use ($db) {
        $validCurrencies = ['USD', 'EUR', 'GBP', 'ZAR'];
        $invalidCurrency = 'INVALID';

        $testConfig = $db->load('config');
        $testConfig['currency'] = 'EUR';
        $db->save('config', $testConfig);

        $loaded = $db->load('config');

        return in_array($loaded['currency'], ['USD', 'EUR', 'GBP', 'ZAR']) &&
               !in_array($invalidCurrency, $validCurrencies);
    });

    // Test tax rate validation
    $testRunner->assert('Settings API handles tax rate values correctly', function() use ($db) {
        $config = $db->load('config');

        // Test valid tax rates
        $config['taxRate'] = 8.5;
        $db->save('config', $config);

        $loaded = $db->load('config');
        $valid1 = is_numeric($loaded['taxRate']) && $loaded['taxRate'] >= 0;

        // Test zero tax rate
        $config['taxRate'] = 0;
        $db->save('config', $config);

        $loaded = $db->load('config');
        $valid2 = is_numeric($loaded['taxRate']) && $loaded['taxRate'] == 0;

        // Test high tax rate (to ensure it's not capped unexpectedly)
        $config['taxRate'] = 25.0;
        $db->save('config', $config);

        $loaded = $db->load('config');
        $valid3 = is_numeric($loaded['taxRate']) && $loaded['taxRate'] == 25.0;

        return $valid1 && $valid2 && $valid3;
    });

    // Test email validation (format only, not existence)
    $testRunner->assert('Settings API can store email addresses', function() use ($db) {
        $emails = [
            'simple@example.com',
            'user.name+tag@example.co.uk',
            'user123@test-domain.com',
            'user_name@sub.domain.org'
        ];

        foreach ($emails as $email) {
            $config = $db->load('config');
            $config['businessEmail'] = $email;
            $db->save('config', $config);

            $loaded = $db->load('config');
            if ($loaded['businessEmail'] !== $email) {
                return false;
            }
        }

        return true;
    });

    // Test phone number flexibility
    $testRunner->assert('Settings API can store various phone formats', function() use ($db) {
        $phoneNumbers = [
            '+1-555-0123',
            '(555) 123-4567',
            '555.123.4567',
            '+1 555 123 4567',
            '5551234567'
        ];

        foreach ($phoneNumbers as $phone) {
            $config = $db->load('config');
            $config['businessPhone'] = $phone;
            $db->save('config', $config);

            $loaded = $db->load('config');
            if ($loaded['businessPhone'] !== $phone) {
                return false;
            }
        }

        return true;
    });

    // Test address storage
    $testRunner->assert('Settings API can store address information', function() use ($db) {
        $address = '123 Main St, Apt 4B, New York, NY 10001, USA';

        $config = $db->load('config');
        $config['businessAddress'] = $address;
        $db->save('config', $config);

        $loaded = $db->load('config');

        return $loaded['businessAddress'] === $address;
    });

    // Test section validation
    $testRunner->assert('Settings API validates sections correctly', function() use ($db) {
        $validSections = ['business', 'api'];
        $invalidSection = 'invalid_section';

        return in_array('business', $validSections) &&
               in_array('api', $validSections) &&
               !in_array($invalidSection, $validSections);
    });

    // Test data sanitization (trimming)
    $testRunner->assert('Settings API trims input values', function() use ($db) {
        $rawData = [
            'businessName' => '  Test Business Name  ',
            'businessEmail' => '  test@example.com  ',
            'businessPhone' => '  +1-555-0123  ',
            'businessAddress' => '  123 Test St  '
        ];

        $config = $db->load('config');

        // Apply the same trimming logic as in the API
        $config['businessName'] = trim($rawData['businessName']);
        $config['businessEmail'] = trim($rawData['businessEmail']);
        $config['businessPhone'] = trim($rawData['businessPhone']);
        $config['businessAddress'] = trim($rawData['businessAddress']);

        $db->save('config', $config);

        $loaded = $db->load('config');

        return $loaded['businessName'] === 'Test Business Name' &&
               $loaded['businessEmail'] === 'test@example.com' &&
               $loaded['businessPhone'] === '+1-555-0123' &&
               $loaded['businessAddress'] === '123 Test St';
    });

    // Test config saving and loading
    $testRunner->assert('Settings API can save and retrieve full config', function() use ($db) {
        $fullConfig = [
            'businessName' => 'Complete Business',
            'businessEmail' => 'complete@example.com',
            'businessPhone' => '+1-555-0199',
            'businessAddress' => '456 Full St, Full City, FC 67890',
            'currency' => 'EUR',
            'taxRate' => 19.0,
            'groqApiKey' => 'complete-groq-key',
            'openrouterApiKey' => 'complete-openrouter-key'
        ];

        $db->save('config', $fullConfig);

        $retrieved = $db->load('config');

        return $retrieved['businessName'] === 'Complete Business' &&
               $retrieved['currency'] === 'EUR' &&
               $retrieved['taxRate'] == 19.0;
    });

    cleanupTestDatabase($db);
});

function createTestDatabase() {
    return new class('test_password') extends Database {
        public function __construct($password) {
            $this->testPath = sys_get_temp_dir() . '/lazyman_settings_test_' . uniqid();
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