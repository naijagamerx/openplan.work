<?php
// Dynamic Web App Manifest - serves with correct URLs
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Build base URL correctly
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostWithPort = strpos($host, ':') !== false ? $host : $host . ':8000';
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$path = rtrim(str_replace('/manifest.php', '', $script), '/');
$baseUrl = $protocol . '://' . $hostWithPort;

// Get theme color from config
$themeColor = '#000000';
$backgroundColor = '#ffffff';

try {
    $db = new Database(getMasterPassword());
    $config = $db->load('config');
    if (!empty($config['faviconThemeColor'])) {
        $themeColor = $config['faviconThemeColor'];
    }
} catch (Exception $e) {
    // Use defaults
}

// Get file modification time for cache busting
$faviconFile = dirname(__FILE__) . '/assets/favicons/apple-touch-icon.png';
$iconVersion = file_exists($faviconFile) ? filemtime($faviconFile) : time();

$appUrl = $baseUrl . ($path ?: '');

$manifest = [
    'name' => 'Task Manager',
    'short_name' => 'TaskManager',
    'description' => 'PHP-based task management system',
    'start_url' => $appUrl . '/',
    'scope' => $appUrl . '/',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => $backgroundColor,
    'theme_color' => $themeColor,
    'icons' => [
        [
            'src' => $appUrl . '/assets/favicons/apple-touch-icon.png?v=' . $iconVersion,
            'sizes' => '180x180',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ]
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
