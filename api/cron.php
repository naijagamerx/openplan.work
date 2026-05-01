<?php
/**
 * Cron API Endpoint
 * Handles scheduled job execution requests from the scheduler or external cron services
 */

// Bootstrap first (needed for all functions)
require_once __DIR__ . '/../config.php';

// Three-tier authentication: CLI -> Localhost -> External Cron
function isCronAllowed(): bool {
    // Tier 1: CLI always allowed
    if (php_sapi_name() === 'cli') {
        return true;
    }

    // Tier 2: Localhost no auth required
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        return true;
    }

    // Tier 3: External cron jobs require valid cron_key (REQUIRED, not optional)
    // Reason: cron jobs trigger AI daily brief which costs money — anonymous trigger = DoS / cost vector
    $queryPassword = $_GET['cron_key'] ?? '';
    $expectedKey = getenv('LAZYMAN_CRON_KEY') ?: '';
    if ($expectedKey === '') {
        $expectedKey = defined('CRON_KEY') ? CRON_KEY : '';
    }

    // Reject if expected key is missing or still the default placeholder
    if ($expectedKey === '' || $expectedKey === 'change_this_to_a_secure_random_key') {
        return false;
    }

    if ($queryPassword === '') {
        return false;
    }

    return hash_equals($expectedKey, $queryPassword);
}

// Check authentication
if (!isCronAllowed()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

// Rate limiting for external requests
function checkCronRateLimit(): bool {
    // No rate limiting for CLI
    if (php_sapi_name() === 'cli') {
        return true;
    }

    // No rate limiting for localhost
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
        return true;
    }

    // Rate limit for external requests
    $rateLimiter = new RateLimiter();
    $maxRequests = defined('CRON_RATE_LIMIT_REQUESTS') ? CRON_RATE_LIMIT_REQUESTS : 60;
    $windowSeconds = defined('CRON_RATE_LIMIT_WINDOW') ? CRON_RATE_LIMIT_WINDOW : 3600;
    return $rateLimiter->check('cron_' . $remoteAddr, $maxRequests, $windowSeconds);
}

// Polyfill $_GET for CLI execution (Hostinger Cron)
if (php_sapi_name() === 'cli' && isset($argv)) {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

// Set JSON response
header('Content-Type: application/json');

// Get job name
$job = $_GET['job'] ?? '';

if (empty($job)) {
    echo json_encode(['success' => false, 'error' => 'No job specified']);
    exit;
}

// Check rate limiting for external requests
if (!checkCronRateLimit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded. Max 60 requests per hour.']);
    exit;
}

// Determine execution source for logging
$executionSource = 'unknown';
if (php_sapi_name() === 'cli') {
    $executionSource = 'cli';
} else {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $executionSource = in_array($remoteAddr, ['127.0.0.1', '::1'], true) ? 'localhost' : 'web';
}

$executionStartTime = microtime(true);

// Initialize DB and Audit - try to get master password
$masterPassword = getMasterPassword();

// For external cron jobs, check if master password is available via environment variable
if (empty($masterPassword) && php_sapi_name() !== 'cli') {
    $masterPassword = getenv('LAZYMAN_MASTER_PASSWORD') ?: '';
    if (empty($masterPassword)) {
        // Check for master_password.php file
        $configFile = __DIR__ . '/../includes/master_password.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $masterPassword = defined('MASTER_PASSWORD') ? MASTER_PASSWORD : '';
        }
    }
    
    if (empty($masterPassword)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Master password not available. Set LAZYMAN_MASTER_PASSWORD environment variable or create includes/master_password.php file.']);
        exit;
    }
}

$db = new Database($masterPassword);
$audit = new Audit($db);

$result = ['success' => false, 'data' => []];

if ($job === 'run_daily') {
    // Run all daily maintenance tasks sequentially
    $tasks = [
        'overdue_invoices',
        'rate_limit_cleanup', 
        'audit_cleanup',
        'inventory_alerts',
        'invoice_reminders',
        'task_reminders',
        'ai_daily_brief'
    ];
    
    $dailyResults = [];
    $overallSuccess = true;
    
    foreach ($tasks as $task) {
        $taskResult = executeCronJob($task, $db, $audit);
        $dailyResults[$task] = $taskResult;
        if (!$taskResult['success']) {
            $overallSuccess = false;
        }
    }
    
    $result = [
        'success' => $overallSuccess,
        'data' => $dailyResults
    ];
} else {
    // Run single job
    $result = executeCronJob($job, $db, $audit);
}

// Enhanced audit logging
$executionDuration = round((microtime(true) - $executionStartTime) * 1000); // ms
$audit->log('system.cron_job', [
    'resource_type' => 'cron',
    'resource_id' => $job,
    'details' => [
        'source' => $executionSource,
        'success' => $result['success'],
        'duration_ms' => $executionDuration,
        'data' => $result['data'] ?? null,
        'error' => $result['error'] ?? null
    ]
]);

echo json_encode($result);

/**
 * Execute a specific cron job logic
 */
function executeCronJob($job, $db, $audit) {
    switch ($job) {
        case 'overdue_invoices':
            return runOverdueInvoices($db);
        case 'rate_limit_cleanup':
            return runRateLimitCleanup();
        case 'audit_cleanup':
            return runAuditCleanup($db, $audit);
        case 'inventory_alerts':
            return runInventoryAlerts($db, $audit);
        case 'invoice_reminders':
            return runInvoiceReminders($db, $audit);
        case 'task_reminders':
            return runTaskReminders($db, $audit);
        case 'ai_daily_brief':
            return runAIDailyBrief($db);
        default:
            return ['success' => false, 'error' => 'Unknown job: ' . $job];
    }
}

/**
 * Job: Mark overdue invoices
 */
function runOverdueInvoices($db) {
    $invoices = $db->load('invoices', true);
    $updatedCount = 0;
    $now = new DateTime();
    $today = $now->format('Y-m-d');

    foreach ($invoices as &$invoice) {
        if ($invoice['status'] === 'sent' && $invoice['dueDate'] < $today) {
            $invoice['status'] = 'overdue';
            $invoice['overdueSince'] = $today;
            $updatedCount++;
        }
    }

    if ($updatedCount > 0) {
        $db->save('invoices', $invoices);
    }

    return [
        'success' => true,
        'data' => ['updated' => $updatedCount]
    ];
}

/**
 * Job: Cleanup rate limits
 */
function runRateLimitCleanup() {
    $rateLimitsFile = __DIR__ . '/../data/rate_limits.json';
    $removedCount = 0;
    $now = time();

    if (file_exists($rateLimitsFile)) {
        $rateLimits = json_decode(file_get_contents($rateLimitsFile), true);
        if (is_array($rateLimits)) {
            $cleaned = [];
            foreach ($rateLimits as $key => $entry) {
                if (isset($entry['expiresAt'])) {
                    $expiresAt = is_numeric($entry['expiresAt']) ? $entry['expiresAt'] : strtotime($entry['expiresAt']);
                    if ($expiresAt > $now) {
                        $cleaned[$key] = $entry;
                    } else {
                        $removedCount++;
                    }
                } else {
                    $cleaned[$key] = $entry;
                }
            }
            file_put_contents($rateLimitsFile, json_encode($cleaned, JSON_PRETTY_PRINT));
        }
    }

    return [
        'success' => true,
        'data' => ['removed' => $removedCount]
    ];
}

/**
 * Job: Cleanup old audit logs
 */
function runAuditCleanup($db, $audit) {
    $retentionDays = defined('AUDIT_RETENTION_DAYS') ? AUDIT_RETENTION_DAYS : 90;

    $cutoffDate = new DateTime();
    $cutoffDate->modify("-{$retentionDays} days");
    $cutoffTimestamp = $cutoffDate->getTimestamp();

    $auditLogs = $db->load('audit', true);

    if (!is_array($auditLogs)) {
        return ['success' => true, 'data' => ['removed' => 0]];
    }

    $originalCount = count($auditLogs);
    $filteredLogs = array_filter($auditLogs, function($entry) use ($cutoffTimestamp) {
        $entryTimestamp = isset($entry['timestamp']) ? strtotime($entry['timestamp']) : 0;
        return $entryTimestamp >= $cutoffTimestamp;
    });

    $cleanedLogs = array_values($filteredLogs);
    $removedCount = $originalCount - count($cleanedLogs);

    if ($removedCount > 0) {
        $db->save('audit', $cleanedLogs);
        
        // Log the cleanup itself (recursively logs to audit, but that's fine as it's a new entry)
        $audit->log('system.cron_job', [
            'resource_type' => 'cron',
            'resource_id' => 'audit_cleanup',
            'details' => [
                'status' => 'success',
                'entries_removed' => $removedCount,
                'retention_days' => $retentionDays
            ]
        ]);
    }

    return ['success' => true, 'data' => ['removed' => $removedCount]];
}

/**
 * Job: Check inventory levels
 */
function runInventoryAlerts($db, $audit) {
    $inventory = $db->load('inventory', true);
    $lowStockItems = [];

    if (defined('INVENTORY_LOW_STOCK_ENABLED') && INVENTORY_LOW_STOCK_ENABLED) {
        foreach ($inventory as $item) {
            if (isset($item['quantity']) && isset($item['minQuantity']) &&
                $item['quantity'] <= $item['minQuantity']) {
                $lowStockItems[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'minQuantity' => $item['minQuantity']
                ];
            }
        }
    }

    return ['success' => true, 'data' => ['low_stock_count' => count($lowStockItems)]];
}

/**
 * Job: Send invoice reminders
 */
function runInvoiceReminders($db, $audit) {
    $invoices = $db->load('invoices', true);
    $reminderDays = defined('INVOICE_REMINDER_DAYS') ? INVOICE_REMINDER_DAYS : 3;
    $reminderCount = 0;

    $dueDate = new DateTime();
    $dueDate->modify("+{$reminderDays} days");
    $targetDate = $dueDate->format('Y-m-d');

    foreach ($invoices as $invoice) {
        if ($invoice['status'] === 'sent' && $invoice['dueDate'] === $targetDate) {
            $reminderCount++;
            // Logging handles the "sending" simulation
            $audit->log('system.cron_job', [
                'resource_type' => 'cron',
                'resource_id' => 'invoice_reminder',
                'details' => [
                    'invoice_id' => $invoice['id'],
                    'invoice_number' => $invoice['invoiceNumber'],
                    'client_id' => $invoice['clientId'],
                    'due_date' => $invoice['dueDate']
                ]
            ]);
        }
    }

    return ['success' => true, 'data' => ['reminders_sent' => $reminderCount]];
}

/**
 * Job: Send task reminders
 */
function runTaskReminders($db, $audit) {
    $projects = $db->load('projects', true);
    $reminderDays = defined('TASK_REMINDER_DAYS') ? TASK_REMINDER_DAYS : 1;
    $reminderCount = 0;

    $dueDate = new DateTime();
    $dueDate->modify("+{$reminderDays} days");
    $targetDate = $dueDate->format('Y-m-d');

    foreach ($projects as $project) {
        if (isset($project['tasks']) && is_array($project['tasks'])) {
            foreach ($project['tasks'] as $task) {
                if (!isTaskDone($task['status'] ?? '') && ($task['status'] ?? '') !== 'cancelled' &&
                    isset($task['dueDate']) && $task['dueDate'] === $targetDate) {
                    $reminderCount++;
                    $audit->log('system.cron_job', [
                        'resource_type' => 'cron',
                        'resource_id' => 'task_reminder',
                        'details' => [
                            'task_id' => $task['id'],
                            'task_title' => $task['title'],
                            'project_id' => $project['id'],
                            'due_date' => $task['dueDate']
                        ]
                    ]);
                }
            }
        }
    }

    return ['success' => true, 'data' => ['reminders_sent' => $reminderCount]];
}

/**
 * Job: Generate AI daily brief using provider fallback chain
 */
function runAIDailyBrief($db) {
    if (!defined('AI_CRON_ENABLED') || !AI_CRON_ENABLED) {
        return ['success' => true, 'data' => ['skipped' => true, 'reason' => 'AI_CRON_ENABLED is false']];
    }

    $today = date('Y-m-d');

    // Load all users from the global users list (load('users') always reads data/users.json)
    $users = $db->load('users');
    if (empty($users)) {
        return ['success' => true, 'data' => ['skipped' => true, 'reason' => 'No users found']];
    }

    $results = [];
    $overallSuccess = true;

    foreach ($users as $user) {
        // Skip banned users
        if (!empty($user['banned'])) {
            continue;
        }

        $userId = $user['id'] ?? '';
        if (empty($userId)) {
            continue;
        }

        // Create a user-scoped DB so API keys and data come from data/users/{userId}/
        $userDb = $db->createScoped($userId);

        try {
            $aiHelper = new AIHelper($userDb);
            $briefText = $aiHelper->generateDailyBrief();

            // Save brief into the user's own scoped directory
            $briefs = $userDb->load('ai_daily_brief') ?? [];
            $briefs[$today] = [
                'date'      => $today,
                'brief'     => $briefText,
                'createdAt' => date('c'),
                'dismissed' => false
            ];
            ksort($briefs);
            if (count($briefs) > 7) {
                $briefs = array_slice($briefs, -7, null, true);
            }
            $userDb->save('ai_daily_brief', $briefs);

            $results[$userId] = ['success' => true, 'length' => strlen($briefText)];
        } catch (Exception $e) {
            error_log("AI Daily Brief cron error for user {$userId}: " . $e->getMessage());
            $results[$userId] = ['success' => false, 'error' => $e->getMessage()];
            $overallSuccess = false;
        }
    }

    return ['success' => $overallSuccess, 'data' => ['date' => $today, 'users' => $results]];
}

