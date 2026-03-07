<?php
/**
 * Comprehensive Unit Tests for Encryption Class
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

// Test encryption/decryption with different data types
$runner->test('Encryption - Basic string encryption/decryption', function() {
    $encryption = new Encryption('test_password');
    $original = 'Hello, World!';
    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
    assertNotEmpty($encrypted);
    assertNotEquals($original, $encrypted);
});

// Test array encryption/decryption
$runner->test('Encryption - Array encryption/decryption', function() {
    $encryption = new Encryption('test_password');
    $original = ['name' => 'John', 'age' => 30, 'active' => true];
    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
    assertTrue(is_array($decrypted));
});

// Test complex nested data encryption
$runner->test('Encryption - Complex nested data encryption', function() {
    $encryption = new Encryption('test_password');
    $original = [
        'user' => [
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'profile' => [
                'name' => 'John Doe',
                'settings' => ['theme' => 'dark', 'notifications' => true]
            ]
        ],
        'metadata' => [
            'created_at' => '2024-01-01T00:00:00Z',
            'version' => 1.0
        ]
    ];

    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
});

// Test with different passwords
$runner->test('Encryption - Different passwords produce different ciphertext', function() {
    $encryption1 = new Encryption('password1');
    $encryption2 = new Encryption('password2');
    $data = 'Test data';

    $encrypted1 = $encryption1->encrypt($data);
    $encrypted2 = $encryption2->encrypt($data);

    assertNotEquals($encrypted1, $encrypted2);
});

// Test decryption with wrong password fails
$runner->test('Encryption - Decryption with wrong password fails', function() {
    $encryption = new Encryption('correct_password');
    $wrongEncryption = new Encryption('wrong_password');
    $data = 'Secret data';

    $encrypted = $encryption->encrypt($data);

    try {
        $wrongEncryption->decrypt($encrypted);
        return 'Should have failed to decrypt with wrong password';
    } catch (Exception $e) {
        assertTrue(str_contains($e->getMessage(), 'failed'));
        return true;
    }
});

// Test password hashing
$runner->test('Encryption - Password hashing', function() {
    $password = 'test_password_123';
    $hash = Encryption::hashPassword($password);

    assertNotEmpty($hash);
    assertTrue(password_verify($password, $hash));
    assertFalse(password_verify('wrong_password', $hash));
    assertEquals(60, strlen($hash)); // bcrypt hash length
});

// Test password verification
$runner->test('Encryption - Password verification', function() {
    $password = 'test_password';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    assertTrue(Encryption::verifyPassword($password, $hash));
    assertFalse(Encryption::verifyPassword('wrong_password', $hash));
});

// Test encryption with empty data
$runner->test('Encryption - Empty data encryption', function() {
    $encryption = new Encryption('test_password');

    // Empty string
    $encrypted = $encryption->encrypt('');
    $decrypted = $encryption->decrypt($encrypted);
    assertEquals('', $decrypted);

    // Empty array
    $encrypted = $encryption->encrypt([]);
    $decrypted = $encryption->decrypt($encrypted);
    assertEquals([], $decrypted);
});

// Test encryption with special characters
$runner->test('Encryption - Special characters handling', function() {
    $encryption = new Encryption('test_password');
    $original = 'Special chars: àáâãäåæçèéêë ñòóôõö ùúûüý ÿ 中文 العربية русский';

    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
});

// Test encryption with binary data (simulated)
$runner->test('Encryption - Binary-like data handling', function() {
    $encryption = new Encryption('test_password');
    $original = ["\x00\x01\x02\x03", "\xFF\xFE\xFD"];

    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
});

// Test invalid base64 decryption
$runner->test('Encryption - Invalid base64 decryption fails', function() {
    $encryption = new Encryption('test_password');

    try {
        $encryption->decrypt('invalid_base64_string');
        return 'Should have failed with invalid base64';
    } catch (Exception $e) {
        assertTrue(str_contains($e->getMessage(), 'Invalid encrypted data'));
        return true;
    }
});

// Test too short encrypted data
$runner->test('Encryption - Too short encrypted data fails', function() {
    $encryption = new Encryption('test_password');

    try {
        $encryption->decrypt(base64_encode('short'));
        return 'Should have failed with too short data';
    } catch (Exception $e) {
        assertTrue(str_contains($e->getMessage(), 'Invalid encrypted data'));
        return true;
    }
});

// Test large data encryption
$runner->test('Encryption - Large data encryption', function() {
    $encryption = new Encryption('test_password');

    // Create large string (100KB)
    $largeData = str_repeat('This is a test string for large data encryption. ', 2000);

    $encrypted = $encryption->encrypt($largeData);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($largeData, $decrypted);
    assertTrue(strlen($encrypted) > strlen($largeData)); // Encrypted should be larger
});

// Test encryption consistency
$runner->test('Encryption - Same input produces different output each time', function() {
    $encryption = new Encryption('test_password');
    $data = 'Consistent test data';

    $encrypted1 = $encryption->encrypt($data);
    $encrypted2 = $encryption->encrypt($data);

    // Should be different due to random IV
    assertNotEquals($encrypted1, $encrypted2);

    // But both should decrypt to the same original
    assertEquals($data, $encryption->decrypt($encrypted1));
    assertEquals($data, $encryption->decrypt($encrypted2));
});

// Test null data handling
$runner->test('Encryption - Null data encryption', function() {
    $encryption = new Encryption('test_password');

    $encrypted = $encryption->encrypt(null);
    $decrypted = $encryption->decrypt($encrypted);

    assertNull($decrypted);
});

// Test numeric data
$runner->test('Encryption - Numeric data encryption', function() {
    $encryption = new Encryption('test_password');

    $original = [123, 45.67, 0, -999];
    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
});

// Test boolean data
$runner->test('Encryption - Boolean data encryption', function() {
    $encryption = new Encryption('test_password');

    $original = [true, false, true];
    $encrypted = $encryption->encrypt($original);
    $decrypted = $encryption->decrypt($encrypted);

    assertEquals($original, $decrypted);
});

// Run the tests
$runner->run();