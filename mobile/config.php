<?php
/**
 * Mobile Configuration
 *
 * Mobile-specific settings and helper functions.
 * Extends main config with mobile-specific values.
 */

// Ensure main config is loaded
if (!function_exists('getMasterPassword')) {
    require_once __DIR__ . '/../config.php';
}

// Mobile-specific constants
define('MOBILE_VERSION', '1.0.0');
define('MOBILE_BUILD_DATE', '2025-01-04');

// Mobile URL paths
define('MOBILE_ASSETS_URL', APP_URL . '/mobile/assets');
define('MOBILE_CSS_URL', MOBILE_ASSETS_URL . '/css');
define('MOBILE_JS_URL', MOBILE_ASSETS_URL . '/js');

// Mobile UI settings
$mobileConfig = [
    'bottom_nav_items' => ['dashboard', 'tasks', 'habits', 'settings'],
    'offcanvas_menu_items' => ['app', 'clients', 'projects', 'notes', 'calendar', 'pomodoro', 'invoices', 'finance', 'inventory', 'ai-assistant'],
    'items_per_page' => 10,
    'swipe_threshold' => 80,
    'tap_target_size' => 44,
];

/**
 * Get mobile configuration value
 */
function getMobileConfig($key, $default = null) {
    global $mobileConfig;
    return $mobileConfig[$key] ?? $default;
}

/**
 * Check if current page is active
 */
function isMobilePageActive($pageName) {
    $currentPage = $_GET['page'] ?? 'dashboard';
    return $currentPage === $pageName;
}

/**
 * Generate mobile page URL
 */
function mobileUrl($page, $params = []) {
    $params['page'] = $page;
    $params['device'] = 'mobile';
    return '?' . http_build_query($params);
}
