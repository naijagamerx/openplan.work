<?php
/**
 * Release Export API Endpoint
 * Handles export generation requests from mobile interface
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Auth.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON content type
header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Check admin status
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access required']);
    exit;
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Handle POST requests for generating exports
if ($method === 'POST') {
    // Validate CSRF token
    $input = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';

    if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $exportType = $input['export_type'] ?? $_POST['export_type'] ?? 'hosted';
    if (!in_array($exportType, ['hosted', 'local'], true)) {
        $exportType = 'hosted';
    }

    $outputDir = ROOT_PATH . '/release-artifacts';
    $projectName = 'openplan.work';
    $timestamp = date('Ymd-His');
    $artifactLabel = $exportType === 'local' ? 'local' : 'hosted';
    $stagingPath = $outputDir . '/' . $projectName . '-' . $artifactLabel . '-clean-' . $timestamp . '-staging';
    $zipPath = $outputDir . '/' . $projectName . '-' . $artifactLabel . '-clean-' . $timestamp . '.zip';

    // Create output directory if needed
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to create output directory']);
            exit;
        }
    }

    // Clean up existing staging if present
    if (is_dir($stagingPath)) {
        releaseExportRecursiveDelete($stagingPath);
    }

    try {
        mkdir($stagingPath, 0755, true);

        // Define included directories and files
        $includedDirectories = ['api', 'android', 'assets', 'cron', 'includes', 'mobile', 'templates', 'views'];
        // .user.ini is never shipped: on the host it can carry the master password.
        $includedRootFiles = [
            '.env.example', '.gitignore', 'cacert.pem',
            'composer.json', 'composer.lock', 'config.php', 'index.php',
            'manifest.php', 'migrate.php', 'mobile-logout.php',
            'setup_models.php', 'robots.txt', 'sitemap.xml'
        ];

        if ($exportType === 'hosted' && file_exists(ROOT_PATH . '/.env')) {
            // Blank the value of every key whose name looks secret, instead of a
            // fixed list of six names that missed any newer secret.
            $envContent = (string) file_get_contents(ROOT_PATH . '/.env');
            $cleanEnv = preg_replace_callback(
                '/^([ \t]*(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=).*$/m',
                static fn (array $m): string => (releaseExportIsSecretName($m[2]) || preg_match('/_USER(NAME)?$/i', $m[2]))
                    ? $m[1]
                    : $m[0],
                $envContent
            );

            // Write cleaned .env to staging
            file_put_contents($stagingPath . '/.env', $cleanEnv);
        }

        if ($exportType === 'local') {
            $includedDirectories[] = 'php';
            $includedRootFiles[] = 'start_server.bat';
            $includedRootFiles[] = 'start_server.php';
            $includedRootFiles[] = 'stop_server.bat';
        }

        $includedDocs = ['README.md', 'docs/EXPORT_RELEASE.md', 'docs/HOSTED_SETUP.md'];

        $excludePatterns = [
            '/^\.(auto-claude|claude|kiro|qoder|serena|trae)$/',
            '/^doc\//', '/^sample\//', '/^test\//', '/^tests\//', '/^test_delete\//',
            '/AGENTS\.md$/', '/CLAUDE(\.local)?\.md$/',
            '/includes\/master_password\.php/', '/includes\/master_password\.php\.backup/',
            '/api\/ai-test\.php$/',
            '/views\/notes-backup/', '/views\/view-notes-backup/',
            '/views\/notes-three-pane-sample\.php$/', '/views\/mcp\.php$/', '/views\/speckitty\.php$/',
            '/\.bak$/', '/\.backup$/', '/\.log$/', '/\.pid$/', '/\.tmp$/',
            '/^mobile\/views\/tasks\.php\.backup$/',
            '/cron\/scheduler_status\.json$/',
            // Exclude android build artifacts and cache
            '/^android\/\.gradle\//',
            '/^android\/\.idea\//',
            '/^android\/build\//',
            '/^android\/app\/build\//',
            '/^android\/local\.properties$/',
            '/^android\/\.gradle-v2\.json$/',
            // Secrets and tool folders: every dot-file/dot-dir except .htaccess
            // (android/.claude, .remember, .idea, .user.ini), plus signing files.
            '/(^|\/)\.(?!htaccess$)[^\/]+$/',
            '/(^|\/)(keystore|local)\.properties$/',
            '/\.(jks|keystore|p12|pfx)$/i'
        ];

        if ($exportType !== 'local') {
            $excludePatterns[] = '/^php\//';
        }

        // Copy directories
        foreach ($includedDirectories as $dir) {
            $sourcePath = ROOT_PATH . '/' . $dir;
            if (is_dir($sourcePath)) {
                releaseExportCopyDirectory($sourcePath, $stagingPath . '/' . $dir, $excludePatterns);
            }
        }

        // Copy root files
        foreach ($includedRootFiles as $file) {
            $sourcePath = ROOT_PATH . '/' . $file;
            if (file_exists($sourcePath)) {
                // If file is .env and we already processed it, skip
                if ($file === '.env' && file_exists($stagingPath . '/.env')) {
                    continue;
                }
                copy($sourcePath, $stagingPath . '/' . $file);
            }
        }

        // Copy docs
        foreach ($includedDocs as $doc) {
            $sourcePath = ROOT_PATH . '/' . $doc;
            if (file_exists($sourcePath)) {
                copy($sourcePath, $stagingPath . '/' . $doc);
            }
        }

        // Create empty data directory structure (NO actual data)
        $dataDir = $stagingPath . '/data';
        mkdir($dataDir, 0755, true);
        mkdir($dataDir . '/backups', 0755, true);
        mkdir($dataDir . '/sessions', 0755, true);
        mkdir($dataDir . '/uploads', 0755, true);
        mkdir($dataDir . '/users', 0755, true);

        // Create .htaccess to deny access
        file_put_contents($dataDir . '/.htaccess', implode("\n", [
            '# Deny access to data directory',
            '<IfModule mod_authz_core.c>',
            '    Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            '    Order deny,allow',
            '    Deny from all',
            '</IfModule>'
        ]));

        // Create index.php that returns 403
        file_put_contents($dataDir . '/index.php', implode("\n", [
            '<?php',
            'http_response_code(403);',
            "exit('Forbidden');"
        ]));

        // Create PROTECTED.md documentation
        file_put_contents($dataDir . '/PROTECTED.md', implode("\n", [
            '# PROTECTED DIRECTORY',
            '',
            'This folder is intentionally shipped empty in release exports.',
            'It is created so fresh installations can store runtime data safely.',
            '',
            'Do not commit real application data from this directory.'
        ]));

        // Create public config
        $publicConfig = [
            'siteName' => 'OpenPlan Work',
            'tagline' => 'OpenPlan Work',
            'seriesName' => 'OpenPlan Work',
            'establishYear' => '2026'
        ];
        file_put_contents($dataDir . '/public_config.json', json_encode($publicConfig, JSON_PRETTY_PRINT));

        // Create release-artifacts/android directory structure (empty, for APK downloads)
        $releaseArtifactsDir = $stagingPath . '/release-artifacts';
        mkdir($releaseArtifactsDir, 0755, true);
        mkdir($releaseArtifactsDir . '/android', 0755, true);

        file_put_contents($releaseArtifactsDir . '/android/README.md', implode("\n", [
            '# Android Release Artifacts',
            '',
            'Place compiled APK files in this directory to enable',
            'homepage download functionality.',
            '',
            'Example: openplan-1.0.apk',
            '',
            'The homepage will automatically detect and offer the',
            'latest APK file for download.'
        ]));

        // Create release manifest
        $releaseManifest = [
            'project' => $projectName,
            'exportType' => $artifactLabel,
            'generatedAt' => date('c'),
            'sourceRepo' => ROOT_PATH,
            'artifact' => basename($zipPath),
            'includedDirectories' => $includedDirectories,
            'includedRootFiles' => $includedRootFiles,
            'includedDocs' => $includedDocs,
            'generatedDataDirectories' => ['backups', 'sessions', 'uploads', 'users'],
            'generatedReleaseArtifacts' => ['android']
        ];
        file_put_contents($stagingPath . '/release-manifest.json', json_encode($releaseManifest, JSON_PRETTY_PRINT));

        // Last gate before anything is zipped: refuse the export if any staged
        // file still holds a secret. Report paths only, never the values.
        $secretHits = releaseExportFindSecrets($stagingPath, releaseExportKnownSecretValues());
        if ($secretHits !== []) {
            releaseExportRecursiveDelete($stagingPath);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Export blocked: ' . count($secretHits) . ' file(s) look like they contain secrets. Remove them and try again.',
                'files' => $secretHits
            ]);
            exit;
        }

        // Create ZIP
        releaseExportCreateZip($stagingPath, $zipPath);
        $fileCount = releaseExportCountFiles($stagingPath);

        // Clean up staging
        releaseExportRecursiveDelete($stagingPath);

        // Return success
        echo json_encode([
            'success' => true,
            'message' => 'Export generated successfully',
            'download_url' => APP_URL . '/api/release-download.php?file=' . urlencode(basename($zipPath)),
            'file_count' => $fileCount,
            'filename' => basename($zipPath)
        ]);

    } catch (Exception $e) {
        // Clean up on error
        if (is_dir($stagingPath)) {
            releaseExportRecursiveDelete($stagingPath);
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}

// Handle DELETE requests for deleting exports
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $csrfToken = $input['csrf_token'] ?? '';

    if (!Auth::isTokenAuth() && !Auth::validateCsrf($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    $filename = basename($input['filename'] ?? '');
    $outputDir = ROOT_PATH . '/release-artifacts';
    $filepath = $outputDir . '/' . $filename;

    if (!preg_match('/^[a-zA-Z0-9._-]+\.zip$/', $filename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid filename']);
        exit;
    }

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Export not found']);
        exit;
    }

    if (!unlink($filepath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete export']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Export deleted successfully']);
    exit;
}

// Handle GET requests for listing exports
if ($method === 'GET' && $action === 'list') {
    $outputDir = ROOT_PATH . '/release-artifacts';
    $exports = [];

    if (is_dir($outputDir)) {
        $files = scandir($outputDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'zip') {
                continue;
            }
            $filepath = $outputDir . '/' . $file;
            $exports[] = [
                'name' => $file,
                'size' => filesize($filepath),
                'size_formatted' => releaseExportFormatSize(filesize($filepath)),
                'date' => filemtime($filepath),
                'date_formatted' => date('M j, Y g:i A', filemtime($filepath)),
                'url' => APP_URL . '/api/release-download.php?file=' . urlencode($file)
            ];
        }
    }

    // Sort by date descending
    usort($exports, function ($a, $b) {
        return $b['date'] <=> $a['date'];
    });

    echo json_encode(['success' => true, 'exports' => $exports]);
    exit;
}

// Invalid request
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid request']);

// Helper functions
function releaseExportRecursiveDelete(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            releaseExportRecursiveDelete($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function releaseExportIsSecretName(string $name): bool {
    return preg_match('/PASS|SECRET|KEY|TOKEN|PEPPER|SALT|PRIVATE|CREDENTIAL/i', $name) === 1;
}

/**
 * Secret values this server actually knows (session master password plus any
 * secret-named variable from .env or the process environment), so the export
 * can be checked for them literally.
 */
function releaseExportKnownSecretValues(): array {
    $values = [];
    $add = static function ($value) use (&$values): void {
        $value = trim((string) $value, " \t\r\n\"'");
        if (strlen($value) >= 8) {
            $values[$value] = true;
        }
    };

    $add(function_exists('getMasterPassword') ? getMasterPassword() : '');
    foreach ([(array) getenv(), $_ENV] as $source) {
        foreach ($source as $name => $value) {
            if (is_string($name) && releaseExportIsSecretName($name)) {
                $add($value);
            }
        }
    }
    $envFile = ROOT_PATH . '/.env';
    if (is_file($envFile) && preg_match_all('/^[ \t]*(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=(.*)$/m', (string) file_get_contents($envFile), $m, PREG_SET_ORDER)) {
        foreach ($m as $line) {
            if (releaseExportIsSecretName($line[1])) {
                $add($line[2]);
            }
        }
    }
    return array_keys($values);
}

/**
 * Returns [['file' => relative path, 'reason' => why], ...] for staged files that
 * must not ship: secret-bearing file names, known secret values, provider keys,
 * private keys, or a non-empty master-password assignment.
 */
function releaseExportFindSecrets(string $dir, array $knownValues): array {
    $hits = [];
    $forbiddenNames = ['.user.ini', 'keystore.properties', 'local.properties', 'master_password.php'];
    $binaryExt = '/\.(mp3|wav|m4a|ogg|png|jpe?g|gif|webp|ico|woff2?|ttf|otf|eot|zip|apk|aab|jar|gz|pdf|mp4|webm)$/i';
    $patterns = [
        'provider API key' => '/gsk_[A-Za-z0-9]{20,}|sk-or-v1-[0-9a-f]{32,}|sk-ant-[A-Za-z0-9_-]{20,}|AIza[0-9A-Za-z_-]{35}/',
        'private key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
        'master password assignment' => '/LAZYMAN_MASTER_PASSWORD["\'\]]?[ \t]*=[ \t]*["\']?[^\s"\']|SetEnv[ \t]+LAZYMAN_MASTER_PASSWORD[ \t]+\S/i',
    ];

    $base = rtrim(str_replace('\\', '/', $dir), '/') . '/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($base));
        $name = strtolower($file->getFilename());

        if (in_array($name, $forbiddenNames, true) || preg_match('/\.(jks|keystore|p12|pfx)$/', $name)) {
            $hits[] = ['file' => $relative, 'reason' => 'secret-bearing file'];
            continue;
        }
        if ($file->getSize() > 2 * 1024 * 1024 || preg_match($binaryExt, $name)) {
            continue;
        }

        $content = (string) file_get_contents($file->getPathname());
        foreach ($knownValues as $value) {
            if (str_contains($content, $value)) {
                $hits[] = ['file' => $relative, 'reason' => 'contains a configured secret value'];
                continue 2;
            }
        }
        foreach ($patterns as $reason => $pattern) {
            if (preg_match($pattern, $content)) {
                $hits[] = ['file' => $relative, 'reason' => $reason];
                continue 2;
            }
        }
    }
    return $hits;
}

function releaseExportShouldExclude(string $path, array $patterns): bool {
    $normalized = str_replace('\\', '/', $path);
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $normalized)) {
            return true;
        }
    }
    return false;
}

function releaseExportCopyDirectory(string $src, string $dst, array $excludePatterns): void {
    if (!is_dir($src)) {
        return;
    }
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    $files = scandir($src);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        $relativePath = str_replace(ROOT_PATH . '/', '', str_replace('\\', '/', $srcPath));

        if (releaseExportShouldExclude($relativePath, $excludePatterns)) {
            continue;
        }

        if (is_dir($srcPath)) {
            releaseExportCopyDirectory($srcPath, $dstPath, $excludePatterns);
        } else {
            copy($srcPath, $dstPath);
        }
    }
}

function releaseExportCreateZip(string $source, string $destination): void {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('Failed to create zip file');
    }

    $source = str_replace('\\', '/', $source);
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($files as $file) {
        $filePath = str_replace('\\', '/', $file->getRealPath());
        $relativePath = str_replace($source . '/', '', $filePath);
        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($filePath, $relativePath);
        }
    }

    $zip->close();
}

function releaseExportCountFiles(string $dir): int {
    $count = 0;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        $count += is_dir($path) ? releaseExportCountFiles($path) : 1;
    }
    return $count;
}

function releaseExportFormatSize(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return round($value, 2) . ' ' . $units[$index];
}
