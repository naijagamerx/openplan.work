<?php
/**
 * LazyMan Tools - Configuration
 */

// Application Settings
define('APP_NAME', 'LazyMan Tools');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/TaskManager');

// Include global helpers
require_once __DIR__ . '/includes/Helpers.php';

// Paths
define('ROOT_PATH', __DIR__);
define('DATA_PATH', ROOT_PATH . '/data');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'lazyman_session');
define('SESSION_MASTER_KEY', 'master_password');

// Encryption
define('CIPHER_METHOD', 'aes-256-gcm');

// AI Defaults
define('DEFAULT_AI_PROVIDER', 'groq');
define('DEFAULT_GROQ_MODEL', 'llama-3.3-70b-versatile');
define('DEFAULT_OPENROUTER_MODEL', 'anthropic/claude-3.5-sonnet');

// Invoice Settings
define('DEFAULT_CURRENCY', 'USD');
define('DEFAULT_TAX_RATE', 0);

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_name(SESSION_NAME);
    session_start();
}

// Autoload classes
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
