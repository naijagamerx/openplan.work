<?php
/**
 * Export/Import API Endpoint
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    if (isAjax()) {
        errorResponse('Unauthorized', 401);
    }
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$db = new Database(getMasterPassword());
$action = $_GET['action'] ?? null;
$format = $_GET['format'] ?? 'json';

if ($action === 'import') {
    // Handle file import
    if (requestMethod() !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    if (!isset($_FILES['backup']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
        errorResponse('No file uploaded');
    }

    $file = $_FILES['backup'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext === 'json') {
        $content = file_get_contents($file['tmp_name']);
        $data = json_decode($content, true);

        if (!$data) {
            errorResponse('Invalid JSON file');
        }

        if ($db->importAll($data)) {
            successResponse(null, 'Data imported successfully');
        } else {
            errorResponse('Import failed');
        }
    } elseif ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) === true) {
            $zip->extractTo(DATA_PATH);
            $zip->close();
            successResponse(null, 'Backup restored successfully');
        } else {
            errorResponse('Failed to extract ZIP file');
        }
    } else {
        errorResponse('Unsupported file format. Use .json or .zip');
    }
} elseif ($action === 'export_habits') {
    // Export habit statistics
    $habits = $db->load('habits');
    $completions = $db->load('habit_completions');
    $timerSessions = $db->load('habit_timer_sessions');

    $exportData = [];

    foreach ($habits as $habit) {
        $habitCompletions = array_filter($completions, fn($c) => $c['habitId'] === $habit['id']);
        $habitTimerSessions = array_filter($timerSessions, fn($s) => $s['habitId'] === $habit['id']);

        $totalDuration = array_sum(array_map(fn($s) => $s['duration'] ?? 0, $habitTimerSessions));
        $avgDuration = count($habitTimerSessions) > 0 ? $totalDuration / count($habitTimerSessions) : 0;

        $exportData[] = [
            'id' => $habit['id'],
            'name' => $habit['name'],
            'category' => $habit['category'] ?? 'general',
            'frequency' => $habit['frequency'] ?? 'daily',
            'target_duration_minutes' => $habit['targetDuration'] ?? 0,
            'total_completions' => count($habitCompletions),
            'total_timer_sessions' => count($habitTimerSessions),
            'total_duration_minutes' => round($totalDuration / 60, 2),
            'average_duration_minutes' => round($avgDuration / 60, 2),
            'created_at' => $habit['createdAt'] ?? '',
            'updated_at' => $habit['updatedAt'] ?? ''
        ];
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="habit_stats_' . date('Ymd') . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['Habit Name', 'Category', 'Frequency', 'Target Duration (min)', 'Total Completions', 'Total Sessions', 'Total Time (min)', 'Avg Time (min)', 'Created At']);

        foreach ($exportData as $row) {
            fputcsv($output, [
                $row['name'],
                $row['category'],
                $row['frequency'],
                $row['target_duration_minutes'],
                $row['total_completions'],
                $row['total_timer_sessions'],
                $row['total_duration_minutes'],
                $row['average_duration_minutes'],
                $row['created_at']
            ]);
        }

        fclose($output);
        exit;
    } else {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="habit_stats_' . date('Ymd') . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT);
        exit;
    }
} else {
    // Export data
    $data = $db->exportAll();
    
    if ($format === 'zip') {
        // Create ZIP backup
        $zipPath = DATA_PATH . '/backups/backup_' . date('Ymd_His') . '.zip';
        
        // Ensure backup directory exists
        if (!is_dir(DATA_PATH . '/backups')) {
            mkdir(DATA_PATH . '/backups', 0755, true);
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $files = glob(DATA_PATH . '/*.json.enc');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
            
            // Download
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="lazyman_backup_' . date('Ymd') . '.zip"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            
            // Clean up
            unlink($zipPath);
            exit;
        } else {
            errorResponse('Failed to create backup');
        }
    } else {
        // JSON export
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="lazyman_export_' . date('Ymd') . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
}
