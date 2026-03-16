<?php
/**
 * Scheduler Bootstrap API
 * Ensures the in-house scheduler is running (manual start remains supported).
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$payload = json_decode(file_get_contents('php://input'), true);
$csrfBody = is_array($payload) ? ($payload['csrf_token'] ?? '') : '';

if (!Auth::validateCsrf($csrfHeader ?: $csrfBody)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

ignore_user_abort(true);
set_time_limit(10);

$root = dirname(__DIR__);
$pidFile = $root . '/cron/scheduler.pid';
$statusFile = $root . '/cron/scheduler_status.json';
$logFile = $root . '/cron/scheduler.log';
$schedulerScript = $root . '/cron/scheduler_simple.php';
$lockFile = DATA_PATH . '/scheduler_autostart_lock.json';
$cooldownSeconds = 180;

function isProcessRunningBootstrap(?int $pid): bool
{
    if (!$pid || $pid <= 0) {
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

function readBootstrapLock(string $lockFile): array
{
    if (!file_exists($lockFile)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($lockFile), true);
    return is_array($data) ? $data : [];
}

function writeBootstrapLock(string $lockFile): void
{
    @file_put_contents($lockFile, json_encode([
        'last_attempt_at' => date('c'),
        'last_attempt_ts' => time(),
    ], JSON_PRETTY_PRINT));
}

function resolvePhpBinary(string $root): ?string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $bundled = $root . '/php/php.exe';
        if (file_exists($bundled)) {
            return $bundled;
        }
    }

    if (defined('PHP_BINARY') && PHP_BINARY) {
        return PHP_BINARY;
    }

    return null;
}

function startSchedulerProcess(string $phpBinary, string $schedulerScript, string $logFile): bool
{
    if (!file_exists($schedulerScript)) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'start /B "" ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($schedulerScript)
            . ' >> ' . escapeshellarg($logFile) . ' 2>&1';

        @pclose(@popen('cmd /c ' . $command, 'r'));
        return true;
    }

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($schedulerScript)
        . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
    @exec($command, $output, $code);
    return $code === 0;
}

$existingPid = file_exists($pidFile) ? (int)trim((string)file_get_contents($pidFile)) : 0;
$alreadyRunning = isProcessRunningBootstrap($existingPid);

if ($existingPid > 0 && !$alreadyRunning) {
    @unlink($pidFile);
}

if ($alreadyRunning) {
    echo json_encode([
        'success' => true,
        'data' => [
            'running' => true,
            'started' => false,
            'pid' => $existingPid,
            'message' => 'Scheduler already running',
        ],
    ]);
    exit;
}

$lock = readBootstrapLock($lockFile);
$lastAttemptTs = (int)($lock['last_attempt_ts'] ?? 0);
$secondsSinceAttempt = $lastAttemptTs > 0 ? (time() - $lastAttemptTs) : PHP_INT_MAX;

if ($secondsSinceAttempt < $cooldownSeconds) {
    echo json_encode([
        'success' => true,
        'data' => [
            'running' => false,
            'started' => false,
            'throttled' => true,
            'retry_in_seconds' => $cooldownSeconds - $secondsSinceAttempt,
            'message' => 'Scheduler auto-start throttled',
        ],
    ]);
    exit;
}

$phpBinary = resolvePhpBinary($root);
if (!$phpBinary || !file_exists($phpBinary)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP binary not found for scheduler bootstrap',
    ]);
    exit;
}

writeBootstrapLock($lockFile);
$spawned = startSchedulerProcess($phpBinary, $schedulerScript, $logFile);

usleep(800000);

$newPid = file_exists($pidFile) ? (int)trim((string)file_get_contents($pidFile)) : 0;
$runningNow = isProcessRunningBootstrap($newPid);
$statusAge = file_exists($statusFile) ? max(0, time() - filemtime($statusFile)) : null;

echo json_encode([
    'success' => $spawned,
    'data' => [
        'running' => $runningNow,
        'started' => $runningNow && !$alreadyRunning,
        'spawned' => $spawned,
        'pid' => $newPid ?: null,
        'status_file_age_seconds' => $statusAge,
        'message' => $runningNow
            ? 'Scheduler running'
            : 'Scheduler start attempted; process not yet confirmed',
    ],
]);
