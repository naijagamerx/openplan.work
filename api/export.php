<?php
/**
 * Export/Import API Endpoint
 *
 * Provides data export/import with support for:
 * - JSON format (full data export)
 * - ZIP format (encrypted backup)
 * - CSV format (individual collections)
 * - Import from JSON/ZIP
 */

require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    if (isAjax()) {
        errorResponse('Unauthorized', 401);
    }
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$db = new Database(getMasterPassword(), Auth::userId());
$userDataPath = $db->getDataPath();

// Verify database connection works
try {
    $db->load('config', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$format = $_GET['format'] ?? 'json';
$collection = $_GET['collection'] ?? null;
$includeMusicRaw = $_GET['include_music'] ?? '1';
$includeMusic = !in_array(strtolower((string)$includeMusicRaw), ['0', 'false', 'no'], true);

function findFirst(array $items, callable $predicate): ?array {
    foreach ($items as $item) {
        if ($predicate($item)) {
            return $item;
        }
    }
    return null;
}

/**
 * Get CSV headers for different collection types
 */
function getCsvHeaders(string $collection): array {
    return match ($collection) {
        'tasks' => ['ID', 'Title', 'Description', 'Status', 'Priority', 'Project', 'Due Date', 'Created At', 'Updated At'],
        'projects' => ['ID', 'Name', 'Description', 'Client', 'Status', 'Color', 'Created At', 'Updated At'],
        'clients' => ['ID', 'Name', 'Email', 'Phone', 'Company', 'City', 'Country', 'Created At', 'Updated At'],
        'invoices' => ['ID', 'Invoice #', 'Client', 'Status', 'Total', 'Currency', 'Issue Date', 'Due Date', 'Created At'],
        'finance' => ['ID', 'Type', 'Category', 'Amount', 'Currency', 'Date', 'Description', 'Created At'],
        'inventory' => ['ID', 'Name', 'SKU', 'Category', 'Cost', 'Price', 'Quantity', 'Min Qty', 'Supplier', 'Created At'],
        'habits' => ['ID', 'Name', 'Category', 'Frequency', 'Target Duration', 'Reminder Time', 'Created At'],
        default => []
    };
}

/**
 * Flatten task for CSV export
 */
function flattenTask(array $task, array $projects): array {
    $projectName = '';
    if (!empty($task['projectId'])) {
        $project = findFirst($projects, fn($p) => ($p['id'] ?? '') === $task['projectId']);
        $projectName = $project['name'] ?? '';
    }

    return [
        'id' => $task['id'],
        'title' => $task['title'] ?? '',
        'description' => substr($task['description'] ?? '', 0, 200),
        'status' => normalizeTaskStatus($task['status'] ?? '', ''),
        'priority' => $task['priority'] ?? '',
        'project' => $projectName,
        'dueDate' => $task['dueDate'] ?? '',
        'createdAt' => $task['createdAt'] ?? '',
        'updatedAt' => $task['updatedAt'] ?? ''
    ];
}

/**
 * Flatten project for CSV export
 */
function flattenProject(array $project, array $clients): array {
    $clientName = '';
    if (!empty($project['clientId'])) {
        $client = findFirst($clients, fn($c) => ($c['id'] ?? '') === $project['clientId']);
        $clientName = $client['name'] ?? '';
    }

    return [
        'id' => $project['id'],
        'name' => $project['name'] ?? '',
        'description' => substr($project['description'] ?? '', 0, 200),
        'client' => $clientName,
        'status' => $project['status'] ?? '',
        'color' => $project['color'] ?? '',
        'createdAt' => $project['createdAt'] ?? '',
        'updatedAt' => $project['updatedAt'] ?? ''
    ];
}

/**
 * Flatten invoice for CSV export
 */
function flattenInvoice(array $invoice, array $clients): array {
    $clientName = '';
    if (!empty($invoice['clientId'])) {
        $client = findFirst($clients, fn($c) => ($c['id'] ?? '') === $invoice['clientId']);
        $clientName = $client['name'] ?? '';
    }

    return [
        'id' => $invoice['id'],
        'invoiceNumber' => $invoice['invoiceNumber'] ?? '',
        'client' => $clientName,
        'status' => $invoice['status'] ?? '',
        'total' => $invoice['total'] ?? 0,
        'currency' => $invoice['currency'] ?? 'USD',
        'issueDate' => $invoice['issueDate'] ?? '',
        'dueDate' => $invoice['dueDate'] ?? '',
        'createdAt' => $invoice['createdAt'] ?? ''
    ];
}

/**
 * Flatten inventory item for CSV export
 */
function flattenInventory(array $item): array {
    return [
        'id' => $item['id'],
        'name' => $item['name'] ?? '',
        'sku' => $item['sku'] ?? '',
        'category' => $item['category'] ?? '',
        'cost' => $item['cost'] ?? 0,
        'price' => $item['price'] ?? 0,
        'quantity' => $item['quantity'] ?? 0,
        'minQuantity' => $item['minQuantity'] ?? 0,
        'supplier' => $item['supplier'] ?? '',
        'createdAt' => $item['createdAt'] ?? ''
    ];
}

/**
 * Flatten finance record for CSV export
 */
function flattenFinance(array $record): array {
    return [
        'id' => $record['id'],
        'type' => $record['type'] ?? '',
        'category' => $record['category'] ?? '',
        'amount' => $record['amount'] ?? 0,
        'currency' => $record['currency'] ?? 'USD',
        'date' => $record['date'] ?? '',
        'description' => substr($record['description'] ?? '', 0, 200),
        'createdAt' => $record['createdAt'] ?? ''
    ];
}

/**
 * Flatten habit for CSV export
 */
function flattenHabit(array $habit): array {
    return [
        'id' => $habit['id'],
        'name' => $habit['name'] ?? '',
        'category' => $habit['category'] ?? '',
        'frequency' => $habit['frequency'] ?? 'daily',
        'targetDuration' => $habit['targetDuration'] ?? 0,
        'reminderTime' => $habit['reminderTime'] ?? '',
        'createdAt' => $habit['createdAt'] ?? ''
    ];
}

if ($action === 'export_csv') {
    // Export specific collection to CSV
    $validCollections = ['tasks', 'projects', 'clients', 'invoices', 'finance', 'inventory', 'habits'];

    if (!in_array($collection, $validCollections)) {
        errorResponse('Invalid collection. Valid: ' . implode(', ', $validCollections), 400);
    }

    // Load data
    $data = match ($collection) {
        'tasks' => [
            'projects' => $db->load('projects', true)
        ],
        'projects' => [
            'projects' => $db->load('projects', true),
            'clients' => $db->load('clients', true)
        ],
        'invoices' => [
            'invoices' => $db->load('invoices', true),
            'clients' => $db->load('clients', true)
        ],
        'finance' => ['finance' => $db->load('finance', true)],
        'inventory' => ['inventory' => $db->load('inventory', true)],
        'clients' => ['clients' => $db->load('clients', true)],
        'habits' => ['habits' => $db->load('habits', true)],
        default => []
    };

    // Flatten data based on collection type
    $flatData = [];
    switch ($collection) {
        case 'tasks':
            foreach ($data['projects'] as $project) {
                foreach ($project['tasks'] ?? [] as $task) {
                    $task['projectId'] = $project['id'] ?? null;
                    $flatData[] = flattenTask($task, $data['projects']);
                }
            }
            break;
        case 'projects':
            foreach ($data['projects'] as $project) {
                $flatData[] = flattenProject($project, $data['clients']);
            }
            break;
        case 'invoices':
            foreach ($data['invoices'] as $invoice) {
                $flatData[] = flattenInvoice($invoice, $data['clients']);
            }
            break;
        case 'finance':
            foreach ($data['finance'] as $record) {
                $flatData[] = flattenFinance($record);
            }
            break;
        case 'inventory':
            foreach ($data['inventory'] as $item) {
                $flatData[] = flattenInventory($item);
            }
            break;
        case 'clients':
            foreach ($data['clients'] as $client) {
                $flatData[] = [
                    'id' => $client['id'],
                    'name' => $client['name'] ?? '',
                    'email' => $client['email'] ?? '',
                    'phone' => $client['phone'] ?? '',
                    'company' => $client['company'] ?? '',
                    'city' => $client['address']['city'] ?? '',
                    'country' => $client['address']['country'] ?? '',
                    'createdAt' => $client['createdAt'] ?? '',
                    'updatedAt' => $client['updatedAt'] ?? ''
                ];
            }
            break;
        case 'habits':
            foreach ($data['habits'] as $habit) {
                $flatData[] = flattenHabit($habit);
            }
            break;
    }

    // Generate CSV
    $headers = getCsvHeaders($collection);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $collection . '_' . date('Ymd') . '.csv"');
    header('X-Content-Type-Options: nosniff');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Write headers
    fputcsv($output, $headers);

    // Write data rows
    foreach ($flatData as $row) {
        fputcsv($output, array_values($row));
    }

    fclose($output);
    exit;
}

if ($action === 'import') {
    // Handle file import
    if (requestMethod() !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    // CSRF validation for import
    $body = getJsonBody();
    $csrfToken = $body['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    if (!Auth::isMcp() && !Auth::validateCsrf($csrfToken)) {
        errorResponse('Invalid CSRF token', 403);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        errorResponse('No file uploaded');
    }

    $file = $_FILES['file'];
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
            $allowedDataPattern = '/^[a-zA-Z0-9_-]+\.json\.enc$/';
            $allowedSharedMusicPattern = '/^assets\/media\/pomodoro\/[a-zA-Z0-9._-]+\.(mp3|wav|m4a)$/i';
            $allowedSharedManifest = 'assets/media/pomodoro/library.json';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (!is_string($entryName)) {
                    continue;
                }

                $normalizedEntry = str_replace('\\', '/', $entryName);
                if ($normalizedEntry === '' || str_ends_with($normalizedEntry, '/')) {
                    continue;
                }

                if (str_contains($normalizedEntry, '../') || str_starts_with($normalizedEntry, '/') || preg_match('/^[A-Za-z]:\//', $normalizedEntry)) {
                    $zip->close();
                    errorResponse('Invalid ZIP entry path', 400, ERROR_VALIDATION);
                }

                $cleanName = basename($normalizedEntry);
                $isDataFile = ($normalizedEntry === $cleanName) && preg_match($allowedDataPattern, $cleanName);
                $isMusicFile = preg_match($allowedSharedMusicPattern, $normalizedEntry) || $normalizedEntry === $allowedSharedManifest;

                if (!$isDataFile && !$isMusicFile) {
                    $zip->close();
                    errorResponse('ZIP contains unsupported files. Only encrypted workspace data files and shared Pomodoro assets are allowed.', 400, ERROR_VALIDATION);
                }

                $contents = $zip->getFromIndex($i);
                if ($contents === false) {
                    $zip->close();
                    errorResponse('Failed to read ZIP entry', 400, ERROR_VALIDATION);
                }

                if ($isMusicFile) {
                    $target = ROOT_PATH . '/' . $normalizedEntry;
                } else {
                    $target = $userDataPath . '/' . $cleanName;
                }
                $targetDir = dirname($target);
                if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
                    $zip->close();
                    errorResponse('Failed to prepare restore directory', 500, ERROR_SERVER);
                }
                if (file_put_contents($target, $contents, LOCK_EX) === false) {
                    $zip->close();
                    errorResponse('Failed to restore ZIP entry', 500, ERROR_SERVER);
                }
            }
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
    $habits = $db->load('habits', true);
    $completions = $db->load('habit_completions', true);
    $timerSessions = $db->load('habit_timer_sessions', true);

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
            'targetDuration' => $habit['targetDuration'] ?? 0,
            'totalCompletions' => count($habitCompletions),
            'totalTimerSessions' => count($habitTimerSessions),
            'totalDurationMinutes' => round($totalDuration / 60, 2),
            'averageDurationMinutes' => round($avgDuration / 60, 2),
            'createdAt' => $habit['createdAt'] ?? '',
            'updatedAt' => $habit['updatedAt'] ?? ''
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
                $row['targetDuration'],
                $row['totalCompletions'],
                $row['totalTimerSessions'],
                $row['totalDurationMinutes'],
                $row['averageDurationMinutes'],
                $row['createdAt']
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
        $backupDir = $userDataPath . '/backups';
        $zipPath = $backupDir . '/backup_' . date('Ymd_His') . '.zip';
        
        // Ensure backup directory exists
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $files = glob($userDataPath . '/*.json.enc');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }

            if ($includeMusic) {
                $musicFiles = glob(ROOT_PATH . '/assets/media/pomodoro/*.{mp3,wav,m4a,MP3,WAV,M4A}', GLOB_BRACE);
                if (is_array($musicFiles)) {
                    foreach ($musicFiles as $musicFile) {
                        if (is_file($musicFile)) {
                            $zip->addFile($musicFile, 'assets/media/pomodoro/' . basename($musicFile));
                        }
                    }
                }

                $musicManifest = ROOT_PATH . '/assets/media/pomodoro/library.json';
                if (is_file($musicManifest)) {
                    $zip->addFile($musicManifest, 'assets/media/pomodoro/library.json');
                }
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

