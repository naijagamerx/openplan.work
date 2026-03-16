<?php
/**
 * Mobile Entry Point
 *
 * Routes mobile requests to appropriate mobile view pages.
 * All mobile pages use universal components (header, bottom-nav, offcanvas-menu).
 * Device detection is automatic via DeviceDetector.
 *
 * @var string $page - Page name from URL (?page=dashboard)
 */

// Enable error display for debugging (can be disabled in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Custom error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        error_log(
            sprintf(
                'Mobile fatal error: %s in %s:%d',
                $error['message'] ?? 'Unknown error',
                $error['file'] ?? 'unknown',
                (int)($error['line'] ?? 0)
            )
        );
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body style="font-family: sans-serif; padding: 20px; text-align: center;">';
        echo '<h2 style="color: #e74c3c;">Application Error</h2>';
        echo '<p><strong>An unexpected error occurred.</strong></p>';
        echo '<p>Please try again or contact support.</p>';
        echo '<hr>';
        echo '<p><a href="?page=login">Return to Login</a></p>';
        echo '</body></html>';
    }
});

// Define mobile view path constant
if (!defined('MOBILE_VIEW_PATH')) {
    define('MOBILE_VIEW_PATH', __DIR__ . '/views');
}

// Include mobile config first (which loads main config and dependencies)
require_once __DIR__ . '/config.php';

// Get page parameter (default to dashboard)
$page = $_GET['page'] ?? 'dashboard';

// Ensure user is authenticated for protected pages
$publicPages = ['login', 'setup', 'register', 'logout', '404'];

if (!in_array($page, $publicPages)) {
    if (!Auth::check()) {
        // Render login directly for unauthenticated mobile requests.
        // Some mobile webviews can mis-handle chained redirects and show a blank page.
        $page = 'login';
    }
}

// Sanitize page name to prevent directory traversal
$page = preg_replace('/[^a-z0-9_-]/i', '', $page);
$viewFile = MOBILE_VIEW_PATH . '/' . $page . '.php';

// Check if view exists
if (!file_exists($viewFile)) {
    $desktopAuthPages = ['thank-you', 'forgot-password', 'reset-password', 'verify-email', 'verification-required'];
    $desktopViewFile = VIEWS_PATH . '/' . $page . '.php';

    if (in_array($page, $desktopAuthPages, true) && file_exists($desktopViewFile)) {
        $viewFile = $desktopViewFile;
        $pageTitle = getSiteName();
        include VIEWS_PATH . '/layouts/auth.php';
        exit;
    }

    http_response_code(404);
    $notFoundView = MOBILE_VIEW_PATH . '/404.php';

    if (file_exists($notFoundView)) {
        $viewFile = $notFoundView;
    } else {
        echo '<!DOCTYPE html><html><head><title>Not Found</title></head><body style="font-family: sans-serif; padding: 20px; text-align: center;">';
        echo '<h2>Page Not Found</h2>';
        echo '<p>The requested mobile page does not exist.</p>';
        echo '<p><a href="?page=dashboard">Back to Dashboard</a></p>';
        echo '</body></html>';
        exit;
    }
}

// Render the mobile view
// Mobile views include their own full HTML structure (not a layout wrapper)
require $viewFile;
