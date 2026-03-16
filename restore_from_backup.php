<?php
/**
 * File Restoration Script
 * Copies missing files from backup to current project
 * Run: php restore_from_backup.php
 */

// Configuration
$backupDir = __DIR__ . '/release-artifacts/openplan.work-hosted-clean-20260312-070308';
$currentDir = __DIR__;
$dryRun = false; // Set to true to preview changes without copying

// Color codes for CLI output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$blue = "\033[34m";
$reset = "\033[0m";

// Directories to restore
$directories = [
    '',                    // Root
    'api',                 // API endpoints
    'includes',            // Core classes
    'views',               // Views
    'views/layouts',       // Layouts
    'views/partials',      // Partials
    'mobile/views',        // Mobile views
    'mobile/views/layouts',
    'mobile/views/partials',
];

$stats = [
    'checked' => 0,
    'missing' => 0,
    'restored' => 0,
    'errors' => 0,
    'existing' => 0
];

echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "{$blue}  OpenPlan Work - File Restoration from Backup{$reset}\n";
echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n\n";

// Verify backup exists
if (!is_dir($backupDir)) {
    echo "{$red}ERROR: Backup directory not found:{$reset}\n";
    echo "  {$backupDir}\n\n";
    echo "Please ensure the backup folder exists and try again.\n";
    exit(1);
}

echo "Backup source: {$backupDir}\n";
echo "Current project: {$currentDir}\n";
echo "Mode: " . ($dryRun ? "{$yellow}DRY RUN (preview only){$reset}" : "{$green}LIVE (will copy files){$reset}") . "\n\n";

foreach ($directories as $dir) {
    $backupPath = $backupDir . ($dir ? '/' . $dir : '');
    $currentPath = $currentDir . ($dir ? '/' . $dir : '');

    if (!is_dir($backupPath)) {
        echo "{$yellow}Skipping{$reset}: Backup directory not found: {$dir}\n";
        continue;
    }

    // Ensure target directory exists
    if (!$dryRun && !is_dir($currentPath)) {
        if (!mkdir($currentPath, 0755, true)) {
            echo "{$red}ERROR{$reset}: Failed to create directory: {$currentPath}\n";
            $stats['errors']++;
            continue;
        }
        echo "{$green}Created directory{$reset}: {$dir}\n";
    }

    // Scan PHP files in backup
    $files = glob($backupPath . '/*.php');

    foreach ($files as $backupFile) {
        $stats['checked']++;
        $filename = basename($backupFile);
        $relativePath = ($dir ? $dir . '/' : '') . $filename;
        $currentFile = $currentPath . '/' . $filename;

        if (file_exists($currentFile)) {
            // Compare file sizes
            $backupSize = filesize($backupFile);
            $currentSize = filesize($currentFile);

            if ($backupSize !== $currentSize) {
                echo "{$yellow}DIFFERS{$reset}: {$relativePath} (backup: {$backupSize}b, current: {$currentSize}b)\n";
                // Optionally restore different files
                // copy($backupFile, $currentFile);
            } else {
                $stats['existing']++;
            }
            continue;
        }

        $stats['missing']++;
        echo "{$red}MISSING{$reset}: {$relativePath}\n";

        if (!$dryRun) {
            if (copy($backupFile, $currentFile)) {
                $stats['restored']++;
                echo "  {$green}→ Restored{$reset}\n";
            } else {
                $stats['errors']++;
                echo "  {$red}→ FAILED{$reset}\n";
            }
        }
    }
}

// Also check for critical non-PHP files
$criticalFiles = [
    'assets/js/app.js',
    'assets/css/app.css',
    'assets/js/mobile.js',
];

echo "\n{$blue}Checking critical non-PHP files...{$reset}\n";

foreach ($criticalFiles as $file) {
    $backupFile = $backupDir . '/' . $file;
    $currentFile = $currentDir . '/' . $file;

    if (file_exists($backupFile) && !file_exists($currentFile)) {
        $stats['missing']++;
        echo "{$red}MISSING{$reset}: {$file}\n";

        if (!$dryRun) {
            $targetDir = dirname($currentFile);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            if (copy($backupFile, $currentFile)) {
                $stats['restored']++;
                echo "  {$green}→ Restored{$reset}\n";
            } else {
                $stats['errors']++;
                echo "  {$red}→ FAILED{$reset}\n";
            }
        }
    }
}

// Summary
echo "\n{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "{$blue}  Restoration Summary{$reset}\n";
echo "{$blue}═══════════════════════════════════════════════════════════════{$reset}\n";
echo "Files checked:     {$stats['checked']}\n";
echo "Already exist:     {$stats['existing']}\n";
echo "Missing found:     {$stats['missing']}\n";

if (!$dryRun) {
    echo "Files restored:    {$stats['restored']}\n";
    echo "Errors:            {$stats['errors']}\n";
} else {
    echo "\n{$yellow}This was a dry run. No files were copied.{$reset}\n";
    echo "To restore files, edit the script and set \$dryRun = false;\n";
}

echo "\n{$blue}Next steps after restoration:${reset}\n";
echo "1. Review restored files to ensure they're correct\n";
echo "2. Run: git status  (to see all new files)\n";
echo "3. Run: git add .   (to stage all restored files)\n";
echo "4. Run: git commit -m \"Restore missing files from backup\"\n";
echo "5. Run: git push    (to push complete project to GitHub)\n";
