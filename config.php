<?php
/**
 * OpenPlan - Configuration
 */

if (!function_exists('loadEnvironmentFile')) {
    function loadEnvironmentFile(string $filePath): void {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $separatorPosition = strpos($trimmed, '=');
            if ($separatorPosition === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $separatorPosition));
            $value = trim(substr($trimmed, $separatorPosition + 1));

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

loadEnvironmentFile(__DIR__ . '/.env');
loadEnvironmentFile(__DIR__ . '/.env.local');

// Application Settings
// Default app name - can be overridden publicly via env and privately per user in settings
define('APP_NAME', 'OpenPlan');
define('APP_DISPLAY_NAME', trim((string)(getenv('APP_DISPLAY_NAME') ?: APP_NAME)));
define('APP_TAGLINE', trim((string)(getenv('APP_TAGLINE') ?: 'Focused task and productivity management for modern teams.')));
define('APP_VERSION', '1.0.0');

// Get dynamic site name from config if available
// This will be set after config is loaded
$GLOBALS['SITE_NAME'] = null;

// Auto-detect base URL
// Auto-detect from server headers (works for both MAMP and CLI server)
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'] ?? '';

// Extract the directory path (remove the script filename)
// SCRIPT_NAME is like /taskmanager/index.php or /taskmanager/api/auth.php
// We want to get /taskmanager for the base URL
$path = dirname($script);

// Only use the path if it's a real directory path (starts with /)
// This prevents issues when SCRIPT_NAME is just a filename
if (strpos($path, '/') === 0) {
    // Remove trailing slash unless it's the root
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
} else {
    // If no directory path, use empty string
    $path = '';
}

// Normalize app path for nested entry points (e.g. /api/*, /mobile/*)
$normalizedPath = $path;
if (preg_match('#/(api|mobile)$#', $normalizedPath) === 1) {
    $normalizedPath = dirname($normalizedPath);
}
if ($normalizedPath === '\\' || $normalizedPath === '.') {
    $normalizedPath = '';
}
if ($normalizedPath !== '/' && $normalizedPath !== '') {
    $normalizedPath = rtrim($normalizedPath, '/');
}
if ($normalizedPath === '/') {
    $normalizedPath = '';
}

define('APP_URL', $protocol . "://" . $host . $normalizedPath);

// Include global helpers
require_once __DIR__ . '/includes/Helpers.php';
require_once __DIR__ . '/includes/Exceptions.php';

// Paths
define('ROOT_PATH', __DIR__);
define('DATA_PATH', ROOT_PATH . '/data');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('VIEWS_PATH', ROOT_PATH . '/views');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_STORAGE_LIFETIME', 2592000); // 30 days (session storage/cookie lifetime)
define('REMEMBER_ME_LIFETIME', 2592000); // 30 days
define('SESSION_NAME', 'lazyman_session');
define('SESSION_COOKIE_SAMESITE', 'Lax');
define('SESSION_MASTER_KEY', 'master_password');
define('SESSION_CSRF_TOKEN', 'csrf_token');
define('SESSION_USER_ID', 'user_id');
define('SESSION_USER_EMAIL', 'user_email');
define('SESSION_USER_NAME', 'user_name');
define('SESSION_LOGIN_TIME', 'login_time');
define('SESSION_REMEMBER_ME', 'remember_me');

// Built-in server session handling (fixes white page issue)
if (PHP_SAPI === 'cli-server') {
    ini_set('session.save_path', DATA_PATH . '/sessions');
    if (!file_exists(DATA_PATH . '/sessions')) {
        mkdir(DATA_PATH . '/sessions', 0777, true);
    }
}

// API Error Codes
define('ERROR_VALIDATION', 'VALIDATION_ERROR');
define('ERROR_NOT_FOUND', 'NOT_FOUND');
define('ERROR_UNAUTHORIZED', 'UNAUTHORIZED');
define('ERROR_FORBIDDEN', 'FORBIDDEN');
define('ERROR_SERVER', 'INTERNAL_ERROR');
define('ERROR_NOT_IMPLEMENTED', 'NOT_IMPLEMENTED');

// Encryption
define('CIPHER_METHOD', 'aes-256-gcm');

// AI Defaults
define('DEFAULT_AI_PROVIDER', 'groq');
define('DEFAULT_GROQ_MODEL', 'openai/gpt-oss-120b');
define('DEFAULT_OPENROUTER_MODEL', 'stepfun/step-3.5-flash:free');

// Hosted auth features (open-source defaults stay off)
define('EMAIL_VERIFICATION_ENABLED', filter_var(getenv('EMAIL_VERIFICATION_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN));
define('PASSWORD_RESET_ENABLED', filter_var(getenv('PASSWORD_RESET_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN));
define('IMAGE_SERVICE_ENABLED', filter_var(getenv('IMAGE_SERVICE_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN));
define('IMAGE_SERVICE_PROVIDER', trim((string)(getenv('IMAGE_SERVICE_PROVIDER') ?: '')));

// Mail transport (hosted deployments should use SMTP)
define('MAIL_DRIVER', strtolower(trim((string)(getenv('MAIL_DRIVER') ?: 'mail'))));
define('MAIL_FROM_ADDRESS', trim((string)(getenv('MAIL_FROM_ADDRESS') ?: '')));
define('MAIL_FROM_NAME', trim((string)(getenv('MAIL_FROM_NAME') ?: '')));
define('SMTP_HOST', trim((string)(getenv('SMTP_HOST') ?: '')));
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', trim((string)(getenv('SMTP_USERNAME') ?: '')));
define('SMTP_PASSWORD', (string)(getenv('SMTP_PASSWORD') ?: ''));
define('SMTP_ENCRYPTION', strtolower(trim((string)(getenv('SMTP_ENCRYPTION') ?: 'tls'))));
define('SMTP_TIMEOUT', (int)(getenv('SMTP_TIMEOUT') ?: 15));

// Invoice Settings
define('DEFAULT_CURRENCY', 'USD');
define('DEFAULT_TAX_RATE', 0);

// Rate Limiting Settings
define('LOGIN_MAX_ATTEMPTS', 5);           // Max login attempts before lockout
define('LOGIN_WINDOW_SECONDS', 900);       // 15 minute window for login attempts
define('LOGIN_LOCKOUT_SECONDS', 1800);     // 30 minute lockout after max attempts
define('API_RATE_LIMIT', 100);             // Max API requests per window
define('API_WINDOW_SECONDS', 3600);        // 1 hour window for API limits

// Cron / Scheduler Settings
define('CRON_ENABLED', true);                      // Enable/disable scheduled jobs
define('CRON_MODE', $_ENV['CRON_MODE'] ?? 'both'); // local|web|both - supports Windows scheduler (local), external cron services (web), or both
define('INVOICE_REMINDER_DAYS', 3);                 // Remind X days before invoice due
define('TASK_REMINDER_DAYS', 1);                    // Remind X days before task due
define('AUDIT_RETENTION_DAYS', 90);                 // Keep audit logs for X days
define('INVENTORY_LOW_STOCK_ENABLED', true);        // Enable inventory low stock alerts
define('CRON_RATE_LIMIT_ENABLED', true);            // Enable rate limiting for external cron requests
define('CRON_RATE_LIMIT_REQUESTS', 60);             // Max external cron requests per hour
define('CRON_RATE_LIMIT_WINDOW', 3600);             // Rate limit window in seconds

// Timezone
date_default_timezone_set('UTC');

// Error Reporting - log errors but don't display them (prevents HTML in JSON responses)
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);
ini_set('error_log', DATA_PATH . '/php_error.log');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.gc_maxlifetime', SESSION_STORAGE_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_STORAGE_LIFETIME,
        'path' => '/',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
        'httponly' => true,
        'samesite' => SESSION_COOKIE_SAMESITE
    ]);
    session_name(SESSION_NAME);
    session_start();
}

// Security Headers - HTTP protection against common attacks
$requestedPage = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['page'] ?? ''));
$isEmbeddedInvoiceRequest = (($_GET['embedded'] ?? '') === '1') && in_array($requestedPage, ['invoice-view', 'advanced-invoice-view'], true);
$frameAncestorsPolicy = $isEmbeddedInvoiceRequest ? "'self'" : "'none'";

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ' . ($isEmbeddedInvoiceRequest ? 'SAMEORIGIN' : 'DENY'));
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header(
    "Content-Security-Policy: default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
    "font-src 'self' https://fonts.gstatic.com data:; " .
    "img-src 'self' data: blob:; " .
    "connect-src 'self' https://api.groq.com https://openrouter.ai; " .
    "media-src 'self' blob: https://assets.mixkit.co; frame-ancestors {$frameAncestorsPolicy}; base-uri 'self'; form-action 'self';"
);

// Autoload classes
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
