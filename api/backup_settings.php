<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = new Database(getMasterPassword(), Auth::userId());
$backup = new Backup($db);

function backupSettingDefaults(): array
{
    return [
        'enabled' => false,
        'frequency' => 'daily',
        'retention' => 7,
        'last_auto_backup_at' => null
    ];
}

function sanitizeBackupSettings(array $input, array $current): array
{
    $frequency = strtolower(trim((string)($input['frequency'] ?? $current['frequency'] ?? 'daily')));
    if (!in_array($frequency, ['daily', 'weekly'], true)) {
        $frequency = 'daily';
    }

    $retention = isset($input['retention']) && is_numeric($input['retention']) ? (int)$input['retention'] : (int)($current['retention'] ?? 7);
    $retention = max(1, min(90, $retention));

    return [
        'enabled' => isset($input['enabled']) ? (bool)$input['enabled'] : (bool)($current['enabled'] ?? false),
        'frequency' => $frequency,
        'retention' => $retention,
        'last_auto_backup_at' => $current['last_auto_backup_at'] ?? null
    ];
}

$current = array_merge(backupSettingDefaults(), $backup->getSettings());

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'data' => $current]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $csrf = $payload['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($csrf)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $settings = sanitizeBackupSettings($payload, $current);
    $backup->setSetting('enabled', $settings['enabled']);
    $backup->setSetting('frequency', $settings['frequency']);
    $backup->setSetting('retention', $settings['retention']);

    echo json_encode(['success' => true, 'data' => $settings]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
