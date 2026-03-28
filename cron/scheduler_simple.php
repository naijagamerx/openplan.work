<?php
/**
 * Simple Scheduler Loop
 * Runs continuously and executes scheduled jobs based on configuration.
 */

// Bootstrap
require_once __DIR__ . '/../config.php';

// Configuration
define('SCHEDULER_PID_FILE', __DIR__ . '/scheduler.pid');
define('SCHEDULER_STATUS_FILE', __DIR__ . '/scheduler_status.json');
define('SCHEDULER_CONFIG_FILE', DATA_PATH . '/scheduler_config.json');
define('CHECK_INTERVAL', 60); // Check every 60 seconds
define('JOB_DEFINITIONS_FILE', __DIR__ . '/job_definitions.php');

// Write PID file
file_put_contents(SCHEDULER_PID_FILE, getmypid());

// Register shutdown handler
register_shutdown_function(function() {
    if (file_exists(SCHEDULER_PID_FILE)) {
        unlink(SCHEDULER_PID_FILE);
    }
});

// Signal handlers for graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        echo "Received SIGTERM, shutting down...\n";
        if (file_exists(SCHEDULER_PID_FILE)) {
            unlink(SCHEDULER_PID_FILE);
        }
        exit(0);
    });
    pcntl_signal(SIGINT, function() {
        echo "Received SIGINT, shutting down...\n";
        if (file_exists(SCHEDULER_PID_FILE)) {
            unlink(SCHEDULER_PID_FILE);
        }
        exit(0);
    });
}

/**
 * Load job definitions
 */
function loadJobDefinitions(): array {
    if (!file_exists(JOB_DEFINITIONS_FILE)) {
        return [];
    }
    return require JOB_DEFINITIONS_FILE;
}

/**
 * Load scheduler configuration
 */
function loadSchedulerConfig(): array {
    if (!file_exists(SCHEDULER_CONFIG_FILE)) {
        return [];
    }
    $config = json_decode(file_get_contents(SCHEDULER_CONFIG_FILE), true);
    return is_array($config) ? $config : [];
}

/**
 * Check if a job should run based on schedule
 */
function shouldJobRun(array $jobConfig, array $lastRuns): bool {
    if (!($jobConfig['enabled'] ?? false)) {
        return false;
    }

    $frequency = $jobConfig['frequency'] ?? 'daily';
    $hour = (int)($jobConfig['hour'] ?? 0);
    $day = (int)($jobConfig['day'] ?? 0);
    $now = new DateTime();
    $currentHour = (int)$now->format('G');
    $currentDay = (int)$now->format('w'); // 0 = Sunday

    // Check if we're in the right hour
    if ($currentHour !== $hour) {
        return false;
    }

    // For weekly jobs, check if we're on the right day
    if ($frequency === 'weekly' && $currentDay !== $day) {
        return false;
    }

    // Check if job already ran today/this week
    $lastRunKey = $jobConfig['job_name'] ?? '';
    if (isset($lastRuns[$lastRunKey])) {
        $lastRun = new DateTime($lastRuns[$lastRunKey]);
        if ($frequency === 'daily' && $lastRun->format('Y-m-d') === $now->format('Y-m-d')) {
            return false;
        }
        if ($frequency === 'weekly') {
            $lastRunWeek = (int)$lastRun->format('W');
            $currentWeek = (int)$now->format('W');
            if ($lastRunWeek === $currentWeek && (int)$lastRun->format('Y') === (int)$now->format('Y')) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Execute a cron job via HTTP request to local API
 */
function executeJob(string $jobName): array {
    $url = APP_URL . '/api/cron.php?job=' . urlencode($jobName);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'header' => "User-Agent: LazyMan-Scheduler/1.0\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [
            'success' => false,
            'error' => 'Failed to execute job: HTTP request failed'
        ];
    }

    $result = json_decode($response, true);
    return is_array($result) ? $result : ['success' => false, 'error' => 'Invalid response'];
}

/**
 * Update scheduler status file
 */
function updateStatus(array $status): void {
    file_put_contents(SCHEDULER_STATUS_FILE, json_encode($status, JSON_PRETTY_PRINT));
}

/**
 * Format uptime string
 */
function formatUptime(int $startTime): string {
    $seconds = time() - $startTime;
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($days > 0) {
        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    } elseif ($hours > 0) {
        return sprintf('%dh %dm', $hours, $minutes);
    } else {
        return sprintf('%dm', $minutes);
    }
}

// Main scheduler loop
echo "Scheduler started (PID: " . getmypid() . ")\n";
$startTime = time();
$lastRuns = [];

while (true) {
    try {
        // Load configurations
        $jobDefinitions = loadJobDefinitions();
        $schedulerConfig = loadSchedulerConfig();
        
        // Merge job definitions with config
        $jobs = [];
        foreach ($jobDefinitions as $jobName => $definition) {
            $config = $schedulerConfig[$jobName] ?? [];
            $jobs[$jobName] = array_merge($definition, [
                'job_name' => $jobName,
                'enabled' => $config['enabled'] ?? $definition['enabled'] ?? true,
                'frequency' => $config['frequency'] ?? $definition['schedule']['frequency'] ?? 'daily',
                'hour' => $config['hour'] ?? $definition['schedule']['hour'] ?? 0,
                'day' => $config['day'] ?? $definition['schedule']['day'] ?? 0
            ]);
        }

        // Check and execute jobs
        $jobStatuses = [];
        foreach ($jobs as $jobName => $jobConfig) {
            $status = [
                'label' => $jobConfig['label'] ?? $jobName,
                'description' => $jobConfig['description'] ?? '',
                'enabled' => $jobConfig['enabled'],
                'schedule' => [
                    'frequency' => $jobConfig['frequency'],
                    'hour' => $jobConfig['hour'],
                    'day' => $jobConfig['day']
                ],
                'last_run' => $lastRuns[$jobName] ?? null,
                'status' => 'pending',
                'result' => null,
                'error' => null
            ];

            if (shouldJobRun($jobConfig, $lastRuns)) {
                echo "Executing job: $jobName\n";
                $result = executeJob($jobName);
                
                $status['last_run'] = date('c');
                $status['status'] = $result['success'] ? 'success' : 'error';
                $status['result'] = $result['data'] ?? null;
                $status['error'] = $result['error'] ?? null;
                
                $lastRuns[$jobName] = $status['last_run'];
                
                if ($result['success']) {
                    echo "  ✓ Job completed successfully\n";
                } else {
                    echo "  ✗ Job failed: " . ($result['error'] ?? 'Unknown error') . "\n";
                }
            } elseif (!($jobConfig['enabled'])) {
                $status['status'] = 'disabled';
            }

            $jobStatuses[$jobName] = $status;
        }

        // Update status file
        $status = [
            'running' => true,
            'pid' => getmypid(),
            'uptime' => formatUptime($startTime),
            'start_time' => date('c', $startTime),
            'last_check' => date('c'),
            'check_interval' => CHECK_INTERVAL,
            'jobs' => $jobStatuses
        ];
        updateStatus($status);

        // Handle signals
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        // Sleep until next check
        sleep(CHECK_INTERVAL);

    } catch (Exception $e) {
        echo "Scheduler error: " . $e->getMessage() . "\n";
        
        // Update status with error
        $status = [
            'running' => true,
            'pid' => getmypid(),
            'uptime' => formatUptime($startTime),
            'start_time' => date('c', $startTime),
            'last_check' => date('c'),
            'check_interval' => CHECK_INTERVAL,
            'error' => $e->getMessage(),
            'jobs' => []
        ];
        updateStatus($status);
        
        sleep(CHECK_INTERVAL);
    }
}
