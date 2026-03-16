<?php
/**
 * Scheduler Status API Endpoint
 * Returns the current scheduler status from cron/scheduler_status.json
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$statusFile = __DIR__ . '/../cron/scheduler_status.json';
$schedulerPidFile = __DIR__ . '/../cron/scheduler.pid';
$serverPidFile = __DIR__ . '/../server.pid';
$schedulerLog = __DIR__ . '/../cron/scheduler.log';
$serverLog = __DIR__ . '/../php_server.log';

function isProcessRunning(?int $pid): bool
{
    if (!$pid) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $output = [];
        exec('tasklist /FI "PID eq ' . (int)$pid . '" 2>NUL', $output);
        return count($output) > 1;
    }

    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    return false;
}

function readLogTail(string $path, int $lines = 50): array
{
    if (!file_exists($path)) {
        return [];
    }
    $content = file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($content)) {
        return [];
    }
    return array_slice($content, -1 * $lines);
}

if (!file_exists($statusFile)) {
    http_response_code(404);
    echo json_encode([
        'running' => false,
        'error' => 'Scheduler not running',
        'message' => 'The scheduler process is not currently active. Start it by running start_server.bat',
            ]);
    exit;
}

$status = json_decode(file_get_contents($statusFile), true);
if (!is_array($status)) {
    http_response_code(500);
    echo json_encode(['running' => false, 'error' => 'Invalid status file']);
    exit;
}

$schedulerPid = file_exists($schedulerPidFile) ? (int)trim(file_get_contents($schedulerPidFile)) : null;
$serverPid = file_exists($serverPidFile) ? (int)trim(file_get_contents($serverPidFile)) : null;

$status['scheduler_pid'] = $schedulerPid;
$status['scheduler_running'] = isProcessRunning($schedulerPid);
$status['server_pid'] = $serverPid;
$status['server_running'] = isProcessRunning($serverPid);
$status['status_file_age_seconds'] = isset($status['last_check']) ? max(0, time() - strtotime($status['last_check'])) : null;

// Consider stale status as not running
$checkInterval = (int)($status['check_interval'] ?? 60);
if ($status['status_file_age_seconds'] !== null && $status['status_file_age_seconds'] > ($checkInterval * 3)) {
    $status['running'] = false;
    $status['stale'] = true;
}

echo json_encode($status);
