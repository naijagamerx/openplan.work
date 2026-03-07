<?php
/**
 * Comprehensive Unit Tests for Helper Functions
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../includes/Helpers.php';

$runner = new TestRunner();

// Test HTML escaping function
$runner->test('Helpers - HTML escaping function', function() {
    $input = '<script>alert("xss")</script>';
    $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';
    $result = e($input);

    assertEquals($expected, $result);

    // Test with quotes
    $input2 = "John's \"special\" string";
    $expected2 = 'John&#039;s &quot;special&quot; string';
    assertEquals($expected2, e($input2));

    // Test with empty string
    assertEquals('', e(''));

    return true;
});

// Test JSON response function
$runner->test('Helpers - JSON response function', function() {
    ob_start();
    $data = ['status' => 'ok', 'count' => 5];
    jsonResponse($data, 200);
    $output = ob_get_clean();

    $expected = json_encode($data);
    assertEquals($expected, $output);

    return true;
});

// Test success response function
$runner->test('Helpers - Success response function', function() {
    ob_start();
    successResponse(['id' => 123], 'Operation completed');
    $output = ob_get_clean();

    $response = json_decode($output, true);
    assertTrue($response['success']);
    assertEquals('Operation completed', $response['message']);
    assertEquals(['id' => 123], $response['data']);
    assertTrue(isset($response['timestamp']));

    return true;
});

// Test error response function
$runner->test('Helpers - Error response function', function() {
    ob_start();
    errorResponse('Something went wrong', 400);
    $output = ob_get_clean();

    $response = json_decode($output, true);
    assertFalse($response['success']);
    assertEquals('Something went wrong', $response['error']);
    assertTrue(isset($response['timestamp']));

    return true;
});

// Test get JSON body function
$runner->test('Helpers - Get JSON body function', function() {
    // Mock php://input
    $originalInput = 'php://input';
    $testJson = '{"name": "Test", "value": 123}';

    // Create a temporary file with test JSON
    $tempFile = tempnam(sys_get_temp_dir(), 'test_json');
    file_put_contents($tempFile, $testJson);

    // Since we can't easily mock php://input, we'll test the fallback case
    // where json_decode returns null
    $result = getJsonBody();
    assertEquals([], $result);

    unlink($tempFile);
    return true;
});

// Test format date function
$runner->test('Helpers - Format date function', function() {
    $date = '2024-01-15 14:30:00';

    // Test default format
    $result = formatDate($date);
    assertEquals('Jan 15, 2024', $result);

    // Test custom format
    $result2 = formatDate($date, 'Y-m-d');
    assertEquals('2024-01-15', $result2);

    // Test another format
    $result3 = formatDate($date, 'd/m/Y H:i');
    assertEquals('15/01/2024 14:30', $result3);

    return true;
});

// Test format currency function
$runner->test('Helpers - Format currency function', function() {
    // Test USD
    $result = formatCurrency(1234.56, 'USD');
    assertEquals('$1,234.56', $result);

    // Test EUR
    $result2 = formatCurrency(99.99, 'EUR');
    assertEquals('€99.99', $result2);

    // Test GBP
    $result3 = formatCurrency(1000, 'GBP');
    assertEquals('£1,000.00', $result3);

    // Test ZAR
    $result4 = formatCurrency(500.25, 'ZAR');
    assertEquals('R500.25', $result4);

    // Test unknown currency
    $result5 = formatCurrency(100, 'XYZ');
    assertEquals('XYZ 100.00', $result5);

    // Test zero amount
    $result6 = formatCurrency(0, 'USD');
    assertEquals('$0.00', $result6);

    return true;
});

// Test time ago function
$runner->test('Helpers - Time ago function', function() {
    $now = time();

    // Test just now (within 60 seconds)
    $datetime = date('Y-m-d H:i:s', $now - 30);
    $result = timeAgo($datetime);
    assertEquals('just now', $result);

    // Test minutes ago
    $datetime2 = date('Y-m-d H:i:s', $now - 1800); // 30 minutes ago
    $result2 = timeAgo($datetime2);
    assertEquals('30 min ago', $result2);

    // Test hours ago
    $datetime3 = date('Y-m-d H:i:s', $now - 7200); // 2 hours ago
    $result3 = timeAgo($datetime3);
    assertEquals('2 hours ago', $result3);

    // Test days ago
    $datetime4 = date('Y-m-d H:i:s', $now - 172800); // 2 days ago
    $result4 = timeAgo($datetime4);
    assertEquals('2 days ago', $result4);

    // Test old date (beyond 7 days)
    $datetime5 = '2024-01-01 12:00:00';
    $result5 = timeAgo($datetime5);
    assertEquals(formatDate($datetime5), $result5);

    return true;
});

// Test slugify function
$runner->test('Helpers - Slugify function', function() {
    // Test basic slugification
    $result = slugify('Hello World');
    assertEquals('hello-world', $result);

    // Test with special characters
    $result2 = slugify('Hello, World! This is a test.');
    assertEquals('hello-world-this-is-a-test', $result2);

    // Test with multiple spaces and dashes
    $result3 = slugify('  Multiple   spaces---and---dashes  ');
    assertEquals('multiple-spaces-and-dashes', $result3);

    // Test with numbers
    $result4 = slugify('Product 123 Version 2.0');
    assertEquals('product-123-version-2-0', $result4);

    // Test empty string
    $result5 = slugify('');
    assertEquals('', $result5);

    // Test string with only special characters
    $result6 = slugify('!!!@@@###');
    assertEquals('', $result6);

    return true;
});

// Test priority class function
$runner->test('Helpers - Priority class function', function() {
    assertEquals('bg-red-100 text-red-800', priorityClass('urgent'));
    assertEquals('bg-orange-100 text-orange-800', priorityClass('high'));
    assertEquals('bg-yellow-100 text-yellow-800', priorityClass('medium'));
    assertEquals('bg-green-100 text-green-800', priorityClass('low'));
    assertEquals('bg-gray-100 text-gray-800', priorityClass('unknown'));
    assertEquals('bg-gray-100 text-gray-800', priorityClass(''));

    return true;
});

// Test status class function
$runner->test('Helpers - Status class function', function() {
    assertEquals('bg-green-100 text-green-800', statusClass('done'));
    assertEquals('bg-green-100 text-green-800', statusClass('completed'));
    assertEquals('bg-green-100 text-green-800', statusClass('paid'));
    assertEquals('bg-blue-100 text-blue-800', statusClass('in_progress'));
    assertEquals('bg-blue-100 text-blue-800', statusClass('sent'));
    assertEquals('bg-purple-100 text-purple-800', statusClass('review'));
    assertEquals('bg-gray-100 text-gray-800', statusClass('todo'));
    assertEquals('bg-gray-100 text-gray-800', statusClass('draft'));
    assertEquals('bg-red-100 text-red-800', statusClass('overdue'));
    assertEquals('bg-red-100 text-red-800', statusClass('cancelled'));
    assertEquals('bg-gray-100 text-gray-800', statusClass('unknown'));

    return true;
});

// Test AJAX detection function
$runner->test('Helpers - AJAX detection function', function() {
    // Mock $_SERVER
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    assertTrue(isAjax());

    // Test with lowercase
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
    assertTrue(isAjax());

    // Test without header
    unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    assertFalse(isAjax());

    // Test with different header
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'SomeOtherValue';
    assertFalse(isAjax());

    return true;
});

// Test request method function
$runner->test('Helpers - Request method function', function() {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    assertEquals('POST', requestMethod());

    $_SERVER['REQUEST_METHOD'] = 'GET';
    assertEquals('GET', requestMethod());

    $_SERVER['REQUEST_METHOD'] = 'put';
    assertEquals('PUT', requestMethod());

    // Test with no REQUEST_METHOD
    unset($_SERVER['REQUEST_METHOD']);
    assertEquals('GET', requestMethod()); // Should default to GET

    return true;
});

// Test email validation function
$runner->test('Helpers - Email validation function', function() {
    // Valid emails
    assertTrue(isValidEmail('test@example.com'));
    assertTrue(isValidEmail('user.name+tag@domain.co.uk'));
    assertTrue(isValidEmail('user123@test-domain.com'));

    // Invalid emails
    assertFalse(isValidEmail('invalid-email'));
    assertFalse(isValidEmail('test@'));
    assertFalse(isValidEmail('@example.com'));
    assertFalse(isValidEmail('test.example.com'));
    assertFalse(isValidEmail(''));
    assertFalse(isValidEmail('test@.com'));

    return true;
});

// Test master password function
$runner->test('Helpers - Master password function', function() {
    // Test with session
    $_SESSION['master_password'] = 'session_password';
    assertEquals('session_password', getMasterPassword());

    // Test with environment variable (mocked)
    unset($_SESSION['master_password']);
    if (!function_exists('getenv')) {
        function getenv($var) {
            return $var === 'LAZYMAN_MASTER_PASSWORD' ? 'env_password' : false;
        }
    }
    // Note: This test may not work as expected if getenv is already defined

    // Test default fallback
    unset($_SESSION['master_password']);
    assertEquals('default_insecure_key', getMasterPassword());

    return true;
});

// Test edge cases for currency formatting
$runner->test('Helpers - Currency formatting edge cases', function() {
    // Test very large numbers
    $result = formatCurrency(999999999.99, 'USD');
    assertEquals('$999,999,999.99', $result);

    // Test very small numbers
    $result2 = formatCurrency(0.001, 'USD');
    assertEquals('$0.00', $result2);

    // Test negative numbers
    $result3 = formatCurrency(-100.50, 'USD');
    assertEquals('-$100.50', $result3);

    return true;
});

// Test edge cases for time ago function
$runner->test('Helpers - Time ago edge cases', function() {
    $now = time();

    // Test exactly 59 seconds ago
    $datetime = date('Y-m-d H:i:s', $now - 59);
    $result = timeAgo($datetime);
    assertEquals('just now', $result);

    // Test exactly 60 seconds ago
    $datetime2 = date('Y-m-d H:i:s', $now - 60);
    $result2 = timeAgo($datetime2);
    assertEquals('1 min ago', $result2);

    // Test exactly 3599 seconds ago (59 minutes, 59 seconds)
    $datetime3 = date('Y-m-d H:i:s', $now - 3599);
    $result3 = timeAgo($datetime3);
    assertEquals('59 min ago', $result3);

    // Test exactly 3600 seconds ago (1 hour)
    $datetime4 = date('Y-m-d H:i:s', $now - 3600);
    $result4 = timeAgo($datetime4);
    assertEquals('1 hours ago', $result4);

    return true;
});

// Test slugify edge cases
$runner->test('Helpers - Slugify edge cases', function() {
    // Test with underscores
    $result = slugify('hello_world_test');
    assertEquals('hello-world-test', $result);

    // Test with mixed case
    $result2 = slugify('MiXeD CaSe StRiNg');
    assertEquals('mixed-case-string', $result2);

    // Test with international characters (should remove them)
    $result3 = slugify('café résumé naïve');
    assertEquals('caf-rsum-nav', $result3);

    // Test with leading/trailing special chars
    $result4 = slugify('---Hello World---');
    assertEquals('hello-world', $result4);

    return true;
});

// Run the tests
$runner->run();