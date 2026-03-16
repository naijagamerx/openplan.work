<?php
/**
 * Mobile Logout Page
 * Simple page that handles logout and redirects to login
 */

// Clear mobile device preference if set
if (isset($_SESSION['device_preference'])) {
    unset($_SESSION['device_preference']);
}

// Use the Auth class to logout if available
if (class_exists('Auth')) {
    $auth = new Auth(new Database(getMasterPassword()));
    $auth->logout();
} else {
    // Fallback logout
    session_destroy();
    $_SESSION = [];
}

// Redirect to login
header('Location: ?page=login');
exit;
