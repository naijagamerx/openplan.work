<?php
/**
 * LazyMan Tools - Main Entry Point & Router
 */

require_once __DIR__ . '/config.php';
require_once INCLUDES_PATH . '/Helpers.php';

// Get the requested page
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? null;

// Check authentication for protected pages
$publicPages = ['login', 'setup'];
$isAuthenticated = isset($_SESSION['user_id']);

// Redirect to login if not authenticated
if (!in_array($page, $publicPages) && !$isAuthenticated) {
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

// Redirect to dashboard if authenticated and on login page
if ($page === 'login' && $isAuthenticated) {
    header('Location: ' . APP_URL . '?page=dashboard');
    exit;
}

// Check if first-time setup is needed
if (!file_exists(DATA_PATH . '/users.json.enc') && $page !== 'setup') {
    header('Location: ' . APP_URL . '?page=setup');
    exit;
}

// CSRF Token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Route to appropriate view
$viewFile = VIEWS_PATH . '/' . $page . '.php';

// Check if view exists
if (!file_exists($viewFile)) {
    $page = '404';
    $viewFile = VIEWS_PATH . '/404.php';
}

// Set page title
$pageTitle = ucfirst(str_replace('-', ' ', $page)) . ' | ' . APP_NAME;

// Include the appropriate layout
if (in_array($page, ['login', 'setup'])) {
    include VIEWS_PATH . '/layouts/auth.php';
} else {
    include VIEWS_PATH . '/layouts/main.php';
}
