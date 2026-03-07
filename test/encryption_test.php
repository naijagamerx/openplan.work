<?php
/**
 * Encryption Verification Test
 */

require_once __DIR__ . '/../config.php';

echo "<h2>LazyMan Tools - Encryption Test</h2>";

$masterPassword = "test_master_password_123!";
$encryption = new Encryption($masterPassword);

$sampleData = [
    'name' => 'John Doe',
    'balance' => 1234.56,
    'sensitive_info' => 'secret_token_abc'
];

try {
    echo "1. Original Data: <pre>" . json_encode($sampleData, JSON_PRETTY_PRINT) . "</pre>";

    // Test encryption
    $encrypted = $encryption->encrypt($sampleData);
    echo "2. Encrypted (Base64): <pre>{$encrypted}</pre>";

    // Test decryption
    $decrypted = $encryption->decrypt($encrypted);
    echo "3. Decrypted Data: <pre>" . json_encode($decrypted, JSON_PRETTY_PRINT) . "</pre>";

    // Verification
    if (json_encode($sampleData) === json_encode($decrypted)) {
        echo "<h3 style='color: green;'>✅ VERIFICATION SUCCESSFUL: Decrypted data matches original.</h3>";
    } else {
        echo "<h3 style='color: red;'>❌ VERIFICATION FAILED: Data mismatch.</h3>";
    }

    // Test with wrong password
    echo "4. Testing with wrong password...<br>";
    $wrongEncryption = new Encryption("wrong_password");
    try {
        $wrongEncryption->decrypt($encrypted);
        echo "<span style='color: red;'>❌ FAILED: Decryption should have failed with wrong password.</span>";
    } catch (Exception $e) {
        echo "<span style='color: green;'>✅ SUCCESS: Correctly failed to decrypt with wrong password: " . $e->getMessage() . "</span>";
    }

} catch (Exception $e) {
    echo "<h3 style='color: red;'>ERROR during test: " . $e->getMessage() . "</h3>";
}
