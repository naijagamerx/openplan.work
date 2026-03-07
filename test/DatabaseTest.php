<?php
/**
 * Comprehensive Unit Tests for Database Class
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

// Test database initialization
$runner->test('Database - Initialization and data directory creation', function() {
    $testDataPath = sys_get_temp_dir() . '/lazyman_test_' . uniqid();
    if (!is_dir($testDataPath)) {
        mkdir($testDataPath, 0755, true);
    }

    // Temporarily override DATA_PATH for testing
    $originalDataPath = defined('DATA_PATH') ? DATA_PATH : null;
    define('TEST_DATA_PATH', $testDataPath);

    // Create a modified Database class for testing
    $db = new class($testDataPath) {
        private $testDataPath;
        public function __construct($testPath) {
            $this->testDataPath = $testPath;
            // Create encryption instance
            $this->encryption = new Encryption('test_password');
        }
        public function getTestDataPath() { return $this->testDataPath; }
        public function testEncryption() { return $this->encryption; }
    };

    assertTrue(is_dir($testDataPath));
    assertTrue(is_writable($testDataPath));

    // Cleanup
    if (is_dir($testDataPath)) {
        rmdir($testDataPath);
    }

    return true;
});

// Test ID generation
$runner->test('Database - ID generation uniqueness', function() {
    $db = new Database('test_password');
    $ids = [];

    // Generate multiple IDs and check uniqueness
    for ($i = 0; $i < 100; $i++) {
        $id = $db->generateId();
        assertFalse(isset($ids[$id]));
        $ids[$id] = true;
        assertTrue(strlen($id) === 36); // UUID format
        assertTrue(preg_match('/^[0-9a-f-]+$/', $id)); // Hex characters and dashes
    }

    assertEquals(100, count($ids));
});

// Test collection save and load
$runner->test('Database - Collection save and load', function() {
    $testDataPath = sys_get_temp_dir() . '/lazyman_test_' . uniqid();
    mkdir($testDataPath, 0755, true);

    // Create a test database class with custom data path
    $db = new class('test_password') extends Database {
        private $customPath;
        public function __construct($password) {
            $this->customPath = sys_get_temp_dir() . '/lazyman_test_' . uniqid();
            mkdir($this->customPath, 0755, true);
            parent::__construct($password);
        }
        protected function getFilePath($collection) {
            return $this->customPath . '/' . $collection . '.json.enc';
        }
        public function __destruct() {
            // Cleanup test files
            $files = glob($this->customPath . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->customPath);
        }
    };

    $testData = [
        ['id' => '1', 'name' => 'Test Item 1', 'value' => 100],
        ['id' => '2', 'name' => 'Test Item 2', 'value' => 200]
    ];

    assertTrue($db->save('test_collection', $testData));
    $loadedData = $db->load('test_collection');
    assertEquals($testData, $loadedData);

    return true;
});

// Test insert operation
$runner->test('Database - Insert operation', function() {
    $db = createDatabaseTestDatabase();

    $record = ['name' => 'New Item', 'value' => 42];
    assertTrue($db->insert('test_collection', $record));

    $data = $db->load('test_collection');
    assertEquals(1, count($data));
    assertEquals('New Item', $data[0]['name']);
    assertEquals(42, $data[0]['value']);
    assertTrue(isset($data[0]['id']));
    assertTrue(isset($data[0]['createdAt']));
    assertTrue(isset($data[0]['updatedAt']));

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test findById operation
$runner->test('Database - Find by ID operation', function() {
    $db = createDatabaseTestDatabase();

    $record = ['name' => 'Find Me', 'value' => 123];
    $db->insert('test_collection', $record);
    $data = $db->load('test_collection');
    $id = $data[0]['id'];

    $found = $db->findById('test_collection', $id);
    assertNotNull($found);
    assertEquals('Find Me', $found['name']);
    assertEquals(123, $found['value']);

    $notFound = $db->findById('test_collection', 'non-existent-id');
    assertNull($notFound);

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test findBy operation
$runner->test('Database - Find by field operation', function() {
    $db = createDatabaseTestDatabase();

    $records = [
        ['name' => 'Item A', 'category' => 'alpha'],
        ['name' => 'Item B', 'category' => 'beta'],
        ['name' => 'Item C', 'category' => 'alpha']
    ];

    foreach ($records as $record) {
        $db->insert('test_collection', $record);
    }

    $alphaItems = $db->findBy('test_collection', 'category', 'alpha');
    assertEquals(2, count($alphaItems));

    $betaItems = $db->findBy('test_collection', 'category', 'beta');
    assertEquals(1, count($betaItems));

    $noneItems = $db->findBy('test_collection', 'category', 'gamma');
    assertEquals(0, count($noneItems));

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test update operation
$runner->test('Database - Update operation', function() {
    $db = createDatabaseTestDatabase();

    $record = ['name' => 'Original', 'value' => 100];
    $db->insert('test_collection', $record);
    $data = $db->load('test_collection');
    $id = $data[0]['id'];

    $updates = ['name' => 'Updated', 'value' => 200];
    assertTrue($db->update('test_collection', $id, $updates));

    $updated = $db->findById('test_collection', $id);
    assertEquals('Updated', $updated['name']);
    assertEquals(200, $updated['value']);
    assertTrue(strtotime($updated['updatedAt']) > strtotime($updated['createdAt']));

    // Test update non-existent record
    assertFalse($db->update('test_collection', 'non-existent-id', $updates));

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test delete operation
$runner->test('Database - Delete operation', function() {
    $db = createDatabaseTestDatabase();

    $record = ['name' => 'To Delete', 'value' => 999];
    $db->insert('test_collection', $record);
    $data = $db->load('test_collection');
    $id = $data[0]['id'];

    assertEquals(1, count($db->load('test_collection')));

    assertTrue($db->delete('test_collection', $id));
    assertEquals(0, count($db->load('test_collection')));

    // Test delete non-existent record
    assertFalse($db->delete('test_collection', 'non-existent-id'));

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test empty collection handling
$runner->test('Database - Empty collection handling', function() {
    $db = createDatabaseTestDatabase();

    // Load non-existent collection
    $data = $db->load('non_existent_collection');
    assertEquals([], $data);

    // Find operations on empty collection
    assertNull($db->findById('non_existent_collection', 'some-id'));
    assertEquals([], $db->findBy('non_existent_collection', 'field', 'value'));

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test export all collections
$runner->test('Database - Export all collections', function() {
    $db = createDatabaseTestDatabase();

    // Add data to multiple collections
    $db->insert('users', ['name' => 'User 1', 'email' => 'user1@test.com']);
    $db->insert('projects', ['name' => 'Project 1', 'status' => 'active']);

    $export = $db->exportAll();

    assertTrue(is_array($export));
    assertTrue(isset($export['users']));
    assertTrue(isset($export['projects']));
    assertEquals(1, count($export['users']));
    assertEquals(1, count($export['projects']));

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test import all collections
$runner->test('Database - Import all collections', function() {
    $db = createDatabaseTestDatabase();

    $importData = [
        'users' => [
            ['id' => 'import-1', 'name' => 'Imported User', 'email' => 'import@test.com']
        ],
        'projects' => [
            ['id' => 'import-2', 'name' => 'Imported Project', 'status' => 'completed']
        ]
    ];

    assertTrue($db->importAll($importData));

    $users = $db->load('users');
    $projects = $db->load('projects');

    assertEquals(1, count($users));
    assertEquals('Imported User', $users[0]['name']);
    assertEquals(1, count($projects));
    assertEquals('Imported Project', $projects[0]['name']);

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test encryption integration
$runner->test('Database - Encryption integration', function() {
    $db = createDatabaseTestDatabase();

    $sensitiveData = ['secret' => 'confidential information', 'token' => 'secret_token_123'];
    $db->insert('secrets', $sensitiveData);

    // Verify data is encrypted in files
    $testDb = new class('test_password') extends Database {
        public function getTestFilePath($collection) {
            return $this->getFilePath($collection);
        }
    };

    $filePath = $testDb->getTestFilePath('secrets');
    assertTrue(file_exists($filePath));

    $fileContent = file_get_contents($filePath);
    assertFalse(str_contains($fileContent, 'confidential information'));
    assertFalse(str_contains($fileContent, 'secret_token_123'));

    // But data decrypts correctly
    $loadedData = $db->load('secrets');
    assertEquals($sensitiveData, $loadedData);

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test data integrity
$runner->test('Database - Data integrity with many operations', function() {
    $db = createDatabaseTestDatabase();

    // Insert many records
    $records = [];
    for ($i = 0; $i < 50; $i++) {
        $record = ['name' => "Item {$i}", 'value' => $i * 10];
        $db->insert('bulk_test', $record);
        $records[] = $record;
    }

    $data = $db->load('bulk_test');
    assertEquals(50, count($data));

    // Update every 5th record
    for ($i = 0; $i < 50; $i += 5) {
        $id = $data[$i]['id'];
        $db->update('bulk_test', $id, ['value' => $data[$i]['value'] * 2]);
    }

    // Delete every 10th record
    for ($i = 0; $i < 50; $i += 10) {
        $id = $data[$i]['id'];
        $db->delete('bulk_test', $id);
    }

    $finalData = $db->load('bulk_test');
    assertEquals(45, count($finalData)); // 5 deleted

    // Verify updated records
    foreach ($finalData as $record) {
        $originalIndex = (int)str_replace('Item ', '', $record['name']);
        if ($originalIndex % 5 === 0) {
            assertEquals($originalIndex * 20, $record['value']); // Doubled
        } else {
            assertEquals($originalIndex * 10, $record['value']); // Original
        }
    }

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Test concurrent access simulation
$runner->test('Database - Concurrent access simulation', function() {
    $db = createDatabaseTestDatabase();

    // Simulate multiple operations
    $db->insert('concurrent_test', ['name' => 'Initial', 'counter' => 0]);
    $data = $db->load('concurrent_test');
    $id = $data[0]['id'];

    // Multiple reads and writes
    for ($i = 1; $i <= 10; $i++) {
        $record = $db->findById('concurrent_test', $id);
        assertNotNull($record);

        $db->update('concurrent_test', $id, ['counter' => $i]);

        $updated = $db->findById('concurrent_test', $id);
        assertEquals($i, $updated['counter']);
    }

    cleanupDatabaseTestDatabase($db);
    return true;
});

// Helper functions for database testing
function createDatabaseTestDatabase() {
    return new class('test_password') extends Database {
        public function __construct($password) {
            $this->testPath = sys_get_temp_dir() . '/lazyman_db_test_' . uniqid();
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

function cleanupDatabaseTestDatabase($db) {
    // Force cleanup by calling destructor
    if (method_exists($db, '__destruct')) {
        $db->__destruct();
    }
}

// Run the tests
$runner->run();