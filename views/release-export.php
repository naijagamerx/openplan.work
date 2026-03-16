<?php
if (!Auth::isAdmin()) {
    http_response_code(403);
    ?>
    <div class="max-w-2xl mx-auto py-16">
        <div class="bg-white border border-red-100 rounded-3xl shadow-sm p-8 text-center">
            <h2 class="text-2xl font-semibold text-gray-900 mb-3">Administrator access required</h2>
            <p class="text-gray-600">Only administrators can generate release exports.</p>
        </div>
    </div>
    <?php
    return;
}

$message = '';
$error = '';
$downloadUrl = '';
$fileCount = 0;
$exportType = $_POST['export_type'] ?? 'hosted';
if (!in_array($exportType, ['hosted', 'local'], true)) {
    $exportType = 'hosted';
}
$outputDir = ROOT_PATH . '/release-artifacts';

if (isset($_POST['delete_export'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $targetFile = basename((string) ($_POST['export_file'] ?? ''));
    $targetPath = $outputDir . '/' . $targetFile;

    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+\.zip$/', $targetFile)) {
        $error = 'Invalid export filename.';
    } elseif (!is_file($targetPath)) {
        $error = 'Selected export does not exist anymore.';
    } elseif (!unlink($targetPath)) {
        $error = 'Unable to delete export file.';
    } else {
        $message = 'Export deleted successfully.';
    }
}

if (isset($_POST['generate'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        $projectName = 'openplan.work';
        $timestamp = date('Ymd-His');
        $artifactLabel = $exportType === 'local' ? 'local' : 'hosted';
        $stagingPath = $outputDir . '/' . $projectName . '-' . $artifactLabel . '-clean-' . $timestamp . '-staging';
        $zipPath = $outputDir . '/' . $projectName . '-' . $artifactLabel . '-clean-' . $timestamp . '.zip';

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        if (is_dir($stagingPath)) {
            releaseExportRecursiveDelete($stagingPath);
        }

        try {
            mkdir($stagingPath, 0755, true);

            $includedDirectories = ['api', 'assets', 'cron', 'includes', 'mobile', 'templates', 'views'];
            $includedRootFiles = [
                '.env.example', '.gitignore', '.user.ini', 'cacert.pem',
                'composer.json', 'composer.lock', 'config.php', 'index.php',
                'manifest.php', 'migrate.php', 'mobile-logout.php',
                'setup_models.php',
                'robots.txt',
                'sitemap.xml'
            ];
            if ($exportType === 'hosted' && file_exists(ROOT_PATH . '/.env')) {
                $includedRootFiles[] = '.env';
            }
            if ($exportType === 'local') {
                $includedDirectories[] = 'php';
                $includedRootFiles[] = 'start_server.bat';
                $includedRootFiles[] = 'start_server.php';
                $includedRootFiles[] = 'stop_server.bat';
            }
            $includedDocs = ['README.md', 'docs/EXPORT_RELEASE.md', 'docs/HOSTED_SETUP.md'];
            $requiredArtifacts = [
                'index.php',
                'config.php',
                'manifest.php',
                'api/export.php',
                'views/release-export.php',
                'mobile/index.php',
                'mobile/views/release-export.php',
                'robots.txt',
                'sitemap.xml'
            ];

            $excludePatterns = [
                '/^\.(auto-claude|claude|kiro|qoder|serena|trae)$/',
                '/^doc\//', '/^sample\//', '/^test\//', '/^tests\//', '/^test_delete\//',
                '/AGENTS\.md$/', '/CLAUDE\.md$/',
                '/includes\/master_password\.php/', '/includes\/master_password\.php\.backup/',
                '/api\/ai-test\.php$/',
                '/views\/notes-backup/', '/views\/view-notes-backup/',
                '/views\/notes-three-pane-sample\.php$/', '/views\/mcp\.php$/', '/views\/speckitty\.php$/',
                '/\.bak$/', '/\.backup$/', '/\.log$/', '/\.pid$/', '/\.tmp$/',
                '/^mobile\/views\/tasks\.php\.backup$/',
                '/cron\/scheduler_status\.json$/'
            ];
            if ($exportType !== 'local') {
                $excludePatterns[] = '/^php\//';
            }

            foreach ($includedDirectories as $dir) {
                $sourcePath = ROOT_PATH . '/' . $dir;
                if (is_dir($sourcePath)) {
                    releaseExportCopyDirectory($sourcePath, $stagingPath . '/' . $dir, $excludePatterns);
                }
            }

            foreach ($includedRootFiles as $file) {
                $sourcePath = ROOT_PATH . '/' . $file;
                if (file_exists($sourcePath)) {
                    copy($sourcePath, $stagingPath . '/' . $file);
                }
            }

            foreach ($includedDocs as $doc) {
                $sourcePath = ROOT_PATH . '/' . $doc;
                if (file_exists($sourcePath)) {
                    copy($sourcePath, $stagingPath . '/' . $doc);
                }
            }

            $dataDir = $stagingPath . '/data';
            mkdir($dataDir, 0755, true);
            mkdir($dataDir . '/backups', 0755, true);
            mkdir($dataDir . '/sessions', 0755, true);
            mkdir($dataDir . '/uploads', 0755, true);
            mkdir($dataDir . '/users', 0755, true);

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

            file_put_contents($dataDir . '/index.php', implode("\n", [
                '<?php',
                'http_response_code(403);',
                "exit('Forbidden');"
            ]));

            file_put_contents($dataDir . '/PROTECTED.md', implode("\n", [
                '# PROTECTED DIRECTORY',
                '',
                'This folder is intentionally shipped empty in release exports.',
                'It is created so fresh installations can store runtime data safely.',
                '',
                'Do not commit real application data from this directory.'
            ]));

            $publicConfig = [
                'siteName' => 'OpenPlan Work',
                'tagline' => 'OpenPlan Work',
                'seriesName' => 'OpenPlan Work',
                'establishYear' => '2026'
            ];
            file_put_contents($dataDir . '/public_config.json', json_encode($publicConfig, JSON_PRETTY_PRINT));

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
                'requiredArtifacts' => $requiredArtifacts
            ];
            file_put_contents($stagingPath . '/release-manifest.json', json_encode($releaseManifest, JSON_PRETTY_PRINT));

            $missingArtifacts = releaseExportValidateRequiredArtifacts($stagingPath, $requiredArtifacts);
            if (!empty($missingArtifacts)) {
                throw new Exception(
                    'Critical release artifacts are missing: ' . implode(', ', $missingArtifacts) .
                    '. Export aborted so you do not download an incomplete package.'
                );
            }

            releaseExportCreateZip($stagingPath, $zipPath);
            $fileCount = releaseExportCountFiles($stagingPath);
            releaseExportRecursiveDelete($stagingPath);

            $message = 'Export generated successfully!';
            $downloadUrl = APP_URL . '/release-artifacts/' . basename($zipPath);
        } catch (Exception $e) {
            $error = 'Export failed: ' . $e->getMessage();
            if (is_dir($stagingPath)) {
                releaseExportRecursiveDelete($stagingPath);
            }
        }
    }
}

$existingExports = [];
if (is_dir($outputDir)) {
    $files = scandir($outputDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'zip') {
            continue;
        }
        $filepath = $outputDir . '/' . $file;
        $existingExports[] = [
            'name' => $file,
            'size' => filesize($filepath),
            'date' => filemtime($filepath),
            'url' => APP_URL . '/release-artifacts/' . $file
        ];
    }
}
usort($existingExports, static function (array $a, array $b): int {
    return $b['date'] <=> $a['date'];
});

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
            if (!releaseExportDeleteFileWithRetry($path)) {
                error_log('Release export cleanup: failed to delete file ' . $path);
            }
        }
    }
    if (!releaseExportDeleteDirWithRetry($dir)) {
        error_log('Release export cleanup: failed to delete directory ' . $dir);
    }
}

function releaseExportDeleteFileWithRetry(string $path, int $retries = 5, int $delayMicros = 120000): bool {
    if (!file_exists($path)) {
        return true;
    }

    for ($i = 0; $i < $retries; $i++) {
        clearstatcache(true, $path);
        if (@unlink($path) || !file_exists($path)) {
            return true;
        }
        usleep($delayMicros);
    }

    return !file_exists($path);
}

function releaseExportDeleteDirWithRetry(string $path, int $retries = 5, int $delayMicros = 120000): bool {
    if (!is_dir($path)) {
        return true;
    }

    for ($i = 0; $i < $retries; $i++) {
        clearstatcache(true, $path);
        if (@rmdir($path) || !is_dir($path)) {
            return true;
        }
        usleep($delayMicros);
    }

    return !is_dir($path);
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

function releaseExportValidateRequiredArtifacts(string $stagingPath, array $requiredArtifacts): array {
    $missing = [];
    foreach ($requiredArtifacts as $relativePath) {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        $fullPath = $stagingPath . '/' . $normalized;
        if (!file_exists($fullPath)) {
            $missing[] = $normalized;
        }
    }

    return $missing;
}
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h2 class="text-2xl font-bold text-gray-900">Release Export</h2>
        <p class="text-sm text-gray-500 mt-1">Generate clean hosted and local ZIP builds from the admin area.</p>
    </div>

    <?php if ($message): ?>
    <div class="bg-green-50 border border-green-200 rounded-2xl p-5">
        <p class="text-sm font-semibold text-green-800"><?php echo e($message); ?></p>
        <?php if ($downloadUrl): ?>
            <p class="text-xs text-green-700 mt-1">Ready to download. Files included: <?php echo (int) $fileCount; ?></p>
            <a href="<?php echo e($downloadUrl); ?>" class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold hover:bg-green-800 transition">
                Download Latest Artifact
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl p-5">
        <p class="text-sm font-semibold text-red-700"><?php echo e($error); ?></p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="text-lg font-semibold text-gray-900">Generate New Export</h3>
        <p class="text-sm text-gray-500 mt-1">Hosted export includes your current `.env`. Local export bundles the PHP runtime scripts.</p>
        <form method="POST" class="mt-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
            <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-3">
                <input type="radio" name="export_type" value="hosted" <?php echo $exportType === 'hosted' ? 'checked' : ''; ?>>
                <span class="text-sm text-gray-800">Hosted export (server-ready package)</span>
            </label>
            <label class="flex items-center gap-3 rounded-lg border border-gray-200 p-3">
                <input type="radio" name="export_type" value="local" <?php echo $exportType === 'local' ? 'checked' : ''; ?>>
                <span class="text-sm text-gray-800">Local export (includes local PHP runtime scripts)</span>
            </label>
            <button type="submit" name="generate" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-black text-white text-sm font-semibold hover:bg-gray-800 transition">
                Generate Export
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Existing Exports</h3>
            <p class="text-sm text-gray-500 mt-1">Newest files are listed first.</p>
        </div>
        <?php if (empty($existingExports)): ?>
            <div class="p-8 text-center text-sm text-gray-500">No exports yet. Generate your first export above.</div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($existingExports as $export): ?>
                    <div class="p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900"><?php echo e($export['name']); ?></p>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo e(releaseExportFormatSize((int) $export['size'])); ?> • <?php echo e(date('M j, Y g:i A', (int) $export['date'])); ?>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="<?php echo e($export['url']); ?>" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                                Download
                            </a>
                            <form method="POST" onsubmit="return confirm('Delete this export file? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="export_file" value="<?php echo e($export['name']); ?>">
                                <button type="submit" name="delete_export" class="inline-flex items-center px-4 py-2 rounded-lg border border-red-200 text-sm font-medium text-red-700 hover:bg-red-50 transition">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
