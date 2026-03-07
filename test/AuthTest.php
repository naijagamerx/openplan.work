<?php
/**
 * Comprehensive Unit Tests for Auth Class
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../config.php';

$runner = new TestRunner();

// Mock session for testing
if (!function_exists('session_start')) {
    function session_start() {
        $_SESSION = [];
        return true;
    }
}

if (!function_exists('session_regenerate_id')) {
    function session_regenerate_id($delete_old_session = false) {
        return true;
    }
}

if (!function_exists('session_get_cookie_params')) {
    function session_get_cookie_params() {
        return [
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true
        ];
    }
}

if (!function_exists('session_name')) {
    function session_name() {
        return 'test_session';
    }
}

if (!function_exists('session_destroy')) {
    function session_destroy() {
        $_SESSION = [];
        return true;
    }
}

if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 3600); // 1 hour
}

// Test user registration
$runner->test('Auth - User registration', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    $result = $auth->register('test@example.com', 'password123', 'Test User');

    assertTrue($result['success']);
    assertNotNull($result['user']);
    assertEquals('test@example.com', $result['user']['email']);
    assertEquals('Test User', $result['user']['name']);
    assertTrue(isset($result['user']['id']));
    assertTrue(isset($result['user']['passwordHash']));
    assertFalse(str_contains($result['user']['passwordHash'], 'password123')); // Should be hashed

    cleanupAuthTestDatabase($db);
    return true;
});

// Test duplicate email registration
$runner->test('Auth - Duplicate email registration fails', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register first user
    $auth->register('test@example.com', 'password123', 'User 1');

    // Try to register with same email
    $result = $auth->register('test@example.com', 'password456', 'User 2');

    assertFalse($result['success']);
    assertTrue(str_contains($result['error'], 'already registered'));

    cleanupAuthTestDatabase($db);
    return true;
});

// Test successful login
$runner->test('Auth - Successful login', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register user first
    $auth->register('test@example.com', 'password123', 'Test User');

    // Attempt login
    $result = $auth->login('test@example.com', 'password123');

    assertTrue($result['success']);
    assertNotNull($result['user']);
    assertEquals('test@example.com', $result['user']['email']);
    assertTrue(isset($_SESSION['user_id']));
    assertEquals($result['user']['id'], $_SESSION['user_id']);
    assertEquals('test@example.com', $_SESSION['user_email']);
    assertEquals('Test User', $_SESSION['user_name']);

    cleanupAuthTestDatabase($db);
    return true;
});

// Test login with wrong password
$runner->test('Auth - Login with wrong password fails', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register user first
    $auth->register('test@example.com', 'password123', 'Test User');

    // Attempt login with wrong password
    $result = $auth->login('test@example.com', 'wrongpassword');

    assertFalse($result['success']);
    assertTrue(str_contains($result['error'], 'Invalid password'));

    cleanupAuthTestDatabase($db);
    return true;
});

// Test login with non-existent user
$runner->test('Auth - Login with non-existent user fails', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    $result = $auth->login('nonexistent@example.com', 'password123');

    assertFalse($result['success']);
    assertTrue(str_contains($result['error'], 'not found'));

    cleanupAuthTestDatabase($db);
    return true;
});

// Test logout
$runner->test('Auth - Logout functionality', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register and login first
    $auth->register('test@example.com', 'password123', 'Test User');
    $auth->login('test@example.com', 'password123');

    // Verify session is set
    assertTrue(isset($_SESSION['user_id']));

    // Logout
    $auth->logout();

    // Verify session is cleared
    assertFalse(isset($_SESSION['user_id']));
    assertFalse(isset($_SESSION['user_email']));
    assertFalse(isset($_SESSION['user_name']));

    cleanupAuthTestDatabase($db);
    return true;
});

// Test Auth::check() with valid session
$runner->test('Auth - Check valid session', function() {
    $_SESSION = [
        'user_id' => 'test-user-id',
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'login_time' => time() - 1000 // Within session lifetime
    ];

    assertTrue(Auth::check());

    return true;
});

// Test Auth::check() with expired session
$runner->test('Auth - Check expired session', function() {
    $_SESSION = [
        'user_id' => 'test-user-id',
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'login_time' => time() - (SESSION_LIFETIME + 100) // Expired
    ];

    assertFalse(Auth::check());

    return true;
});

// Test Auth::check() with no session
$runner->test('Auth - Check no session', function() {
    $_SESSION = [];

    assertFalse(Auth::check());

    return true;
});

// Test Auth::userId()
$runner->test('Auth - Get current user ID', function() {
    $_SESSION = [
        'user_id' => 'test-user-id',
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'login_time' => time()
    ];

    assertEquals('test-user-id', Auth::userId());

    return true;
});

// Test Auth::userId() with no session
$runner->test('Auth - Get user ID with no session', function() {
    $_SESSION = [];

    assertNull(Auth::userId());

    return true;
});

// Test Auth::user()
$runner->test('Auth - Get current user info', function() {
    $_SESSION = [
        'user_id' => 'test-user-id',
        'user_email' => 'test@example.com',
        'user_name' => 'Test User',
        'login_time' => time()
    ];

    $user = Auth::user();
    assertNotNull($user);
    assertEquals('test-user-id', $user['id']);
    assertEquals('test@example.com', $user['email']);
    assertEquals('Test User', $user['name']);

    return true;
});

// Test Auth::user() with no session
$runner->test('Auth - Get user info with no session', function() {
    $_SESSION = [];

    assertNull(Auth::user());

    return true;
});

// Test CSRF token generation and validation
$runner->test('Auth - CSRF token generation and validation', function() {
    $_SESSION = [];
    $_SESSION['csrf_token'] = 'test-csrf-token-12345';

    // Test getting token
    $token = Auth::csrfToken();
    assertEquals('test-csrf-token-12345', $token);

    // Test validation with correct token
    assertTrue(Auth::validateCsrf('test-csrf-token-12345'));

    // Test validation with wrong token
    assertFalse(Auth::validateCsrf('wrong-token'));

    // Test validation with no token set
    unset($_SESSION['csrf_token']);
    assertFalse(Auth::validateCsrf('any-token'));

    return true;
});

// Test login updates last login timestamp
$runner->test('Auth - Login updates last login timestamp', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register user first
    $registerResult = $auth->register('test@example.com', 'password123', 'Test User');
    assertNull($registerResult['user']['lastLogin']);

    // Login
    $loginResult = $auth->login('test@example.com', 'password123');

    // Check user was updated
    $users = $db->load('users');
    $user = $users[0];
    assertNotNull($user['lastLogin']);

    cleanupAuthTestDatabase($db);
    return true;
});

// Test session security features
$runner->test('Auth - Session security features', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register and login
    $auth->register('test@example.com', 'password123', 'Test User');
    $auth->login('test@example.com', 'password123');

    // Verify session has required fields
    assertTrue(isset($_SESSION['user_id']));
    assertTrue(isset($_SESSION['user_email']));
    assertTrue(isset($_SESSION['user_name']));
    assertTrue(isset($_SESSION['login_time']));

    // Verify user ID is UUID format
    $userId = $_SESSION['user_id'];
    assertTrue(strlen($userId) === 36);
    assertTrue(preg_match('/^[0-9a-f-]+$/', $userId));

    cleanupAuthTestDatabase($db);
    return true;
});

// Test password strength (through bcrypt)
$runner->test('Auth - Password hashing strength', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    $result = $auth->register('test@example.com', 'simplepassword', 'Test User');
    $hash = $result['user']['passwordHash'];

    // Should be bcrypt hash (starts with $2y$)
    assertTrue(str_starts_with($hash, '$2y$'));

    // Should be 60 characters long
    assertEquals(60, strlen($hash));

    // Verify it's actually a valid bcrypt hash
    assertTrue(password_verify('simplepassword', $hash));
    assertFalse(password_verify('wrongpassword', $hash));

    cleanupAuthTestDatabase($db);
    return true;
});

// Test multiple user management
$runner->test('Auth - Multiple user management', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register multiple users
    $users = [
        ['email' => 'user1@test.com', 'password' => 'pass1', 'name' => 'User One'],
        ['email' => 'user2@test.com', 'password' => 'pass2', 'name' => 'User Two'],
        ['email' => 'user3@test.com', 'password' => 'pass3', 'name' => 'User Three']
    ];

    foreach ($users as $userData) {
        $result = $auth->register($userData['email'], $userData['password'], $userData['name']);
        assertTrue($result['success']);
    }

    // Test each user can login independently
    foreach ($users as $userData) {
        // Clear session
        $_SESSION = [];

        $result = $auth->login($userData['email'], $userData['password']);
        assertTrue($result['success']);
        assertEquals($userData['email'], $_SESSION['user_email']);
        assertEquals($userData['name'], $_SESSION['user_name']);
    }

    cleanupAuthTestDatabase($db);
    return true;
});

// Test email case sensitivity
$runner->test('Auth - Email case sensitivity', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register with lowercase email
    $auth->register('test@example.com', 'password123', 'Test User');

    // Try to login with different case - should work or fail consistently
    $result1 = $auth->login('test@example.com', 'password123');
    $result2 = $auth->login('TEST@example.com', 'password123');

    // Both should have the same result (emails should be case-insensitive in practice)
    assertEquals($result1['success'], $result2['success']);

    cleanupAuthTestDatabase($db);
    return true;
});

// Test concurrent login sessions (simulation)
$runner->test('Auth - Concurrent login simulation', function() {
    $db = createAuthTestDatabase();
    $auth = new Auth($db);

    // Register user
    $auth->register('test@example.com', 'password123', 'Test User');

    // Simulate multiple login attempts
    for ($i = 0; $i < 5; $i++) {
        $_SESSION = []; // Clear session
        $result = $auth->login('test@example.com', 'password123');
        assertTrue($result['success']);
        assertEquals('test@example.com', $_SESSION['user_email']);
    }

    cleanupAuthTestDatabase($db);
    return true;
});

// Helper functions for Auth testing
function createAuthTestDatabase() {
    return new class('test_password') extends Database {
        public function __construct($password) {
            $this->testPath = sys_get_temp_dir() . '/lazyman_auth_test_' . uniqid();
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

function cleanupAuthTestDatabase($db) {
    if (method_exists($db, '__destruct')) {
        $db->__destruct();
    }
}

// Run the tests
$runner->run();