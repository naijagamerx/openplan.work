<?php
/**
 * Cron API Endpoint
 * Handles scheduled job execution requests from the scheduler or external cron services
 */

// Three-tier authentication: CLI -> Localhost -> External MCP
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

    // Tier 3: External requires MCP authentication via X-MASTER-PASSWORD header
    $headerPassword = $_SERVER['HTTP_X_MASTER_PASSWORD'] ?? '';
    if ($headerPassword === '') {
        return false;
    }

    // Validate against master password
    $expectedPassword = getenv('LAZYMAN_MASTER_PASSWORD') ?: '';
    if ($expectedPassword === '') {
        // Fall back to trying to get from environment/config
        require_once __DIR__ . '/../config.php';
        $expectedPassword = getMasterPassword();
    }

    return hash_equals($expectedPassword, $headerPassword);
}

// Check authentication
if (!isCronAllowed()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied. Requires CLI, localhost, or X-MASTER-PASSWORD header.']);
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
    require_once __DIR__ . '/../config.php';
    $rateLimiter = new RateLimiter();
    $maxRequests = defined('CRON_RATE_LIMIT_REQUESTS') ? CRON_RATE_LIMIT_REQUESTS : 60;
    $windowSeconds = defined('CRON_RATE_LIMIT_WINDOW') ? CRON_RATE_LIMIT_WINDOW : 3600;
    return $rateLimiter->check('cron_' . $remoteAddr, $maxRequests, $windowSeconds);
}

// Bootstrap
require_once __DIR__ . '/../config.php';

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

// Log execution
$audit = new Audit(new Database(getMasterPassword()));

$result = ['success' => false, 'data' => []];

switch ($job) {
    case 'overdue_invoices':
        $db = new Database(getMasterPassword());
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

        $db->save('invoices', $invoices);
        $audit->log('system.cron_job', [
            'resource_type' => 'cron',
            'resource_id' => 'overdue_invoices',
            'details' => ['updated_count' => $updatedCount]
        ]);

        $result = [
            'success' => true,
            'data' => ['updated' => $updatedCount]
        ];
        break;

    case 'rate_limit_cleanup':
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

        $result = [
            'success' => true,
            'data' => ['removed' => $removedCount]
        ];
        break;

    case 'audit_cleanup':
        $db = new Database(getMasterPassword());
        $audit = new Audit($db);
        $retentionDays = defined('AUDIT_RETENTION_DAYS') ? AUDIT_RETENTION_DAYS : 90;

        $cutoffDate = new DateTime();
        $cutoffDate->modify("-{$retentionDays} days");
        $cutoffTimestamp = $cutoffDate->getTimestamp();

        $auditLogs = $db->load('audit', true);

        if (!is_array($auditLogs)) {
            $result = ['success' => true, 'data' => ['removed' => 0]];
            break;
        }

        $originalCount = count($auditLogs);
        $filteredLogs = array_filter($auditLogs, function($entry) use ($cutoffTimestamp) {
            $entryTimestamp = isset($entry['timestamp']) ? strtotime($entry['timestamp']) : 0;
            return $entryTimestamp >= $cutoffTimestamp;
        });

        $cleanedLogs = array_values($filteredLogs);
        $removedCount = $originalCount - count($cleanedLogs);

        $db->save('audit', $cleanedLogs);
        $audit->log('system.cron_job', [
            'resource_type' => 'cron',
            'resource_id' => 'audit_cleanup',
            'details' => [
                'status' => 'success',
                'entries_removed' => $removedCount,
                'retention_days' => $retentionDays
            ]
        ]);

        $result = ['success' => true, 'data' => ['removed' => $removedCount]];
        break;

    case 'inventory_alerts':
        $db = new Database(getMasterPassword());
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

        // In production, this would send email notifications
        // For now, just log the low stock items
        $audit->log('system.cron_job', [
            'resource_type' => 'cron',
            'resource_id' => 'inventory_alerts',
            'details' => [
                'low_stock_count' => count($lowStockItems),
                'items' => $lowStockItems
            ]
        ]);

        $result = ['success' => true, 'data' => ['low_stock_count' => count($lowStockItems)]];
        break;

    case 'invoice_reminders':
        $db = new Database(getMasterPassword());
        $invoices = $db->load('invoices', true);
        $reminderDays = defined('INVOICE_REMINDER_DAYS') ? INVOICE_REMINDER_DAYS : 3;
        $reminderCount = 0;

        $dueDate = new DateTime();
        $dueDate->modify("+{$reminderDays} days");
        $targetDate = $dueDate->format('Y-m-d');

        foreach ($invoices as $invoice) {
            if ($invoice['status'] === 'sent' && $invoice['dueDate'] === $targetDate) {
                // In production, this would send email reminders
                // For now, just log that a reminder would be sent
                $reminderCount++;
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

        $result = ['success' => true, 'data' => ['reminders_sent' => $reminderCount]];
        break;

    case 'task_reminders':
        $db = new Database(getMasterPassword());
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
                        // In production, this would send email reminders
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

        $result = ['success' => true, 'data' => ['reminders_sent' => $reminderCount]];
        break;

    default:
        $result = [
            'success' => false,
            'error' => 'Unknown job: ' . $job
        ];
        break;
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

