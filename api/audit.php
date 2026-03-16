<?php
/**
 * Audit Log API Endpoint
 *
 * Provides REST API for audit log operations:
 *   GET  /api/audit.php?action=list           - List audit logs with filtering
 *   GET  /api/audit.php?action=get&id=        - Get single log entry
 *   GET  /api/audit.php?action=stats          - Get audit statistics
 *   GET  /api/audit.php?action=types          - Get event types
 *   POST /api/audit.php?action=clear          - Clear old logs
 *   GET  /api/audit.php?action=export         - Export logs to CSV
 */

require_once __DIR__ . '/../config.php';

// Authentication check
if (!Auth::check()) {
    if (isAjax()) {
        errorResponse('Unauthorized', 401);
    }
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$db = new Database(getMasterPassword(), Auth::userId());
$audit = new Audit($db);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// CSRF validation for write operations
$writeActions = ['clear'];
if (in_array($action, $writeActions)) {
    $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!Auth::validateCsrf($csrfToken)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

// Handle different actions
switch ($action) {
    case 'list':
        $filters = [
            'event' => $_GET['event'] ?? null,
            'user_id' => $_GET['user_id'] ?? null,
            'resource_type' => $_GET['resource_type'] ?? null,
            'resource_id' => $_GET['resource_id'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
            'search' => $_GET['search'] ?? null,
            'limit' => isset($_GET['limit']) ? min((int)$_GET['limit'], 500) : 100,
            'offset' => isset($_GET['offset']) ? (int)$_GET['offset'] : 0
        ];

        $result = $audit->getLogs($filters);
        successResponse($result, 'Audit logs retrieved successfully');
        break;

    case 'get':
        $id = $_GET['id'] ?? '';

        if (empty($id)) {
            errorResponse('Log ID is required', 400);
        }

        $log = $audit->getLogById($id);

        if ($log) {
            successResponse($log, 'Log entry retrieved successfully');
        } else {
            errorResponse('Log entry not found', 404);
        }
        break;

    case 'stats':
        $filters = [
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null
        ];

        $stats = $audit->getStats($filters);
        successResponse($stats, 'Statistics retrieved successfully');
        break;

    case 'types':
        $eventTypes = $audit->getEventTypes();
        $resourceTypes = $audit->getResourceTypes();

        successResponse([
            'events' => $eventTypes,
            'resources' => $resourceTypes
        ], 'Event types retrieved successfully');
        break;

    case 'clear':
        $before = $_POST['before'] ?? $_GET['before'] ?? null;

        $deleted = $audit->clearLogs($before);

        // Log the clear action
        $user = Auth::user();
        $audit->log('system.audit_clear', [
            'details' => [
                'before' => $before,
                'deleted_count' => $deleted,
                'user_id' => $user['id'] ?? 'unknown'
            ]
        ]);

        successResponse([
            'deleted_count' => $deleted
        ], "Cleared {$deleted} log entries");
        break;

    case 'export':
        $format = $_GET['format'] ?? 'json';
        $filters = [
            'event' => $_GET['event'] ?? null,
            'user_id' => $_GET['user_id'] ?? null,
            'resource_type' => $_GET['resource_type'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
            'limit' => 10000,
            'offset' => 0
        ];

        $result = $audit->getLogs($filters);
        $logs = $result['logs'];

        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd') . '.csv"');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');

            $output = fopen('php://output', 'w');

            // CSV Header
            fputcsv($output, [
                'ID', 'Timestamp', 'Event', 'User ID', 'User Email', 'User Name',
                'IP Address', 'Resource Type', 'Resource ID', 'Description', 'Success'
            ]);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['timestamp'],
                    $log['event'],
                    $log['user_id'],
                    $log['user_email'],
                    $log['user_name'],
                    $log['ip_address'],
                    $log['resource_type'] ?? '',
                    $log['resource_id'] ?? '',
                    $log['description'],
                    $log['success'] ? 'Yes' : 'No'
                ]);
            }

            fclose($output);
            exit;
        } else {
            // JSON export
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="audit_logs_' . date('Ymd') . '.json"');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');

            echo json_encode([
                'exported_at' => date('c'),
                'filters' => $filters,
                'total_logs' => count($logs),
                'logs' => $logs
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
        break;

    default:
        errorResponse('Invalid action. Supported actions: list, get, stats, types, clear, export', 400);
}
