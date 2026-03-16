<?php
/**
 * Scheduler Config API
 * Read/write job schedules for the scheduler.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$configFile = DATA_PATH . '/scheduler_config.json';
$jobDefinitions = require __DIR__ . '/../cron/job_definitions.php';

function buildDefaults(array $jobDefinitions): array
{
    $defaults = [];
    foreach ($jobDefinitions as $jobName => $definition) {
        $schedule = $definition['schedule'] ?? [];
        $defaults[$jobName] = [
            'enabled' => (bool)($definition['enabled'] ?? true),
            'frequency' => $schedule['frequency'] ?? 'daily',
            'hour' => (int)($schedule['hour'] ?? 0),
            'day' => $schedule['day'] ?? 0
        ];
    }
    return $defaults;
}

function readConfig(string $configFile, array $defaults): array
{
    if (!file_exists($configFile)) {
        file_put_contents($configFile, json_encode($defaults, JSON_PRETTY_PRINT));
        return $defaults;
    }

    $config = json_decode(file_get_contents($configFile), true);
    if (!is_array($config)) {
        return $defaults;
    }

    return $config;
}

function sanitizeConfig(array $input, array $defaults): array
{
    $output = [];
    foreach ($defaults as $jobName => $schedule) {
        $override = $input[$jobName] ?? [];
        $frequency = in_array(($override['frequency'] ?? $schedule['frequency']), ['daily', 'weekly'], true)
            ? $override['frequency']
            : $schedule['frequency'];
        $hour = isset($override['hour']) && is_numeric($override['hour'])
            ? max(0, min(23, (int)$override['hour']))
            : $schedule['hour'];
        $day = isset($override['day']) && is_numeric($override['day'])
            ? max(0, min(6, (int)$override['day']))
            : $schedule['day'];
        $enabled = isset($override['enabled']) ? (bool)$override['enabled'] : (bool)$schedule['enabled'];

        if (defined('CRON_ENABLED') && !CRON_ENABLED) {
            $enabled = false;
        }
        if ($jobName === 'inventory_alerts' && defined('INVENTORY_LOW_STOCK_ENABLED') && !INVENTORY_LOW_STOCK_ENABLED) {
            $enabled = false;
        }

        $output[$jobName] = [
            'enabled' => $enabled,
            'frequency' => $frequency,
            'hour' => $hour,
            'day' => $day
        ];
    }
    return $output;
}

$defaults = buildDefaults($jobDefinitions);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $config = sanitizeConfig(readConfig($configFile, $defaults), $defaults);
    $jobs = [];
    foreach ($jobDefinitions as $jobName => $definition) {
        $jobs[$jobName] = [
            'id' => $jobName,
            'label' => $definition['label'] ?? $jobName,
            'description' => $definition['description'] ?? '',
            'schedule' => $config[$jobName]
        ];
    }
    echo json_encode(['success' => true, 'data' => ['jobs' => $jobs]]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    $jobInput = $payload['jobs'] ?? [];
    if (!is_array($jobInput)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid jobs payload']);
        exit;
    }

    $updated = sanitizeConfig($jobInput, $defaults);
    file_put_contents($configFile, json_encode($updated, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'data' => ['jobs' => $updated]]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
