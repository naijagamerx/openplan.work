<?php
/**
 * Health Check API Endpoint
 *
 * Provides system health status for monitoring.
 * Accessible without authentication for load balancers/monitoring tools.
 */

// Get config without starting session (for pure health checks)
require_once dirname(__DIR__) . '/config.php';

// Perform health checks
$checks = [
    'data_directory' => [
        'name' => 'Data Directory',
        'status' => is_dir(DATA_PATH) && is_writable(DATA_PATH),
        'message' => is_dir(DATA_PATH) ? (is_writable(DATA_PATH) ? 'Writable' : 'Not writable') : 'Not found'
    ],
    'encryption' => [
        'name' => 'Encryption Module',
        'status' => class_exists('Encryption'),
        'message' => class_exists('Encryption') ? 'Available' : 'Missing'
    ],
    'encryption_test' => [
        'name' => 'Encryption Test',
        'status' => false,
        'message' => 'Not tested'
    ],
    'session' => [
        'name' => 'Session Status',
        'status' => session_status() !== PHP_SESSION_DISABLED,
        'message' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Available'
    ]
];

// Test encryption functionality
try {
    $testEncryption = new Encryption('health_check_test');
    $encrypted = $testEncryption->encrypt('test');
    $decrypted = $testEncryption->decrypt($encrypted);
    $checks['encryption_test']['status'] = ($decrypted === 'test');
    $checks['encryption_test']['message'] = $checks['encryption_test']['status'] ? 'Working' : 'Failed';
} catch (Exception $e) {
    $checks['encryption_test']['status'] = false;
    $checks['encryption_test']['message'] = 'Error: ' . $e->getMessage();
}

// Check local scheduler status (Windows)
$schedulerRunning = false;
$schedulerPid = null;
$pidFile = DATA_PATH . '/scheduler.pid';
if (file_exists($pidFile)) {
    $schedulerPid = file_get_contents($pidFile);
    if (PHP_OS_FAMILY === 'Windows') {
        $schedulerRunning = !empty($schedulerPid) && shell_exec("tasklist /FI \"PID eq {$schedulerPid}\" /NH 2>nul") !== null;
    } else {
        $schedulerRunning = !empty($schedulerPid) && posix_kill((int)$schedulerPid, 0);
    }
}

// Check last cron execution
$lastExecution = null;
$auditFile = DATA_PATH . '/audit_logs.json.enc';
if (file_exists($auditFile)) {
    try {
        require_once INCLUDES_PATH . '/Database.php';
        require_once INCLUDES_PATH . '/Audit.php';
        $db = new Database(getMasterPassword());
        $auditLogs = $db->load('audit', true);
        if (is_array($auditLogs)) {
            foreach (array_reverse($auditLogs) as $log) {
                if (($log['action'] ?? '') === 'system.cron_job') {
                    $lastExecution = $log['timestamp'] ?? null;
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Ignore errors reading audit logs
    }
}

// Add cron checks
$checks['cron'] = [
    'name' => 'Cron System',
    'status' => true,
    'message' => 'Configured',
    'details' => [
        'mode' => CRON_MODE,
        'local_scheduler_running' => $schedulerRunning,
        'local_scheduler_pid' => $schedulerPid,
        'web_cron_enabled' => in_array(CRON_MODE, ['web', 'both'], true),
        'rate_limiting_enabled' => CRON_RATE_LIMIT_ENABLED,
        'rate_limit_requests' => CRON_RATE_LIMIT_REQUESTS,
        'last_execution' => $lastExecution
    ]
];

// Determine overall health
$allHealthy = true;
foreach ($checks as $check) {
    if (!$check['status']) {
        $allHealthy = false;
        break;
    }
}

// Uptime is environment-specific and omitted by default in hardened mode.
$uptime = 'unavailable';

// Build response
$health = [
    'status' => $allHealthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'version' => APP_VERSION,
    'php_version' => PHP_VERSION,
    'uptime' => $uptime,
    'memory_usage' => [
        'used' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ],
    'checks' => $checks
];

// Set response code and headers
$statusCode = $allHealthy ? 200 : 503;
http_response_code($statusCode);
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Output response
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
