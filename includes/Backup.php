<?php
/**
 * Backup Class - Automated backup management with retention policies
 *
 * Handles creating, restoring, and cleaning up backups with support for:
 * - Daily, weekly, and monthly retention policies
 * - Encrypted backup storage
 * - Backup history tracking
 */

class Backup {
    // Retention constants
    const RETENTION_DAILY = 7;     // Keep 7 daily backups
    const RETENTION_WEEKLY = 4;    // Keep 4 weekly backups
    const RETENTION_MONTHLY = 12;  // Keep 12 monthly backups

    // Backup types
    const TYPE_FULL = 'full';
    const TYPE_INCREMENTAL = 'incremental';

    // Storage paths
    protected string $backupPath;
    protected string $dataPath;
    protected Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
        $this->dataPath = $db->getDataPath();
        $this->backupPath = $this->dataPath . '/backups';

        // Ensure backup directory exists
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Create a new backup
     */
    public function createBackup(string $type = self::TYPE_FULL, string $description = ''): array {
        $timestamp = date('Y-m-d_His');
        $filename = sprintf('backup_%s_%s_%s.zip', $type, $timestamp, uniqid());
        $filepath = $this->backupPath . '/' . $filename;

        try {
            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to create ZIP archive');
            }

            // Add encrypted data files
            $dataFiles = glob($this->dataPath . '/*.json.enc');
            $fileChecksums = [];
            foreach ($dataFiles as $file) {
                $basename = basename($file);
                $zip->addFile($file, $basename);
                $fileChecksums[$basename] = hash_file('sha256', $file);
            }

            // Add manifest with metadata
            $manifest = [
                'version' => APP_VERSION,
                'created_at' => date('c'),
                'type' => $type,
                'description' => $description,
                'php_version' => PHP_VERSION,
                'files' => array_map('basename', $dataFiles),
                'file_checksums' => $fileChecksums
            ];
            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            $zip->close();

            // Calculate checksum after ZIP is closed and file exists
            $checksum = $this->calculateChecksum($filepath);

            // Update backup history
            $this->addToHistory([
                'filename' => $filename,
                'type' => $type,
                'size' => filesize($filepath),
                'created_at' => date('c'),
                'checksum' => $checksum
            ]);

            return [
                'success' => true,
                'filename' => $filename,
                'size' => $this->formatBytes(filesize($filepath)),
                'created_at' => date('c')
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Restore a backup from filename
     */
    public function restoreBackup(string $filename): array {
        $filepath = $this->backupPath . '/' . basename($filename);

        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }

            // Verify archive metadata first
            if (!$this->verifyChecksum($filepath)) {
                return ['success' => false, 'error' => 'Backup manifest verification failed'];
            }

        try {
            $zip = new ZipArchive();
            if ($zip->open($filepath) !== true) {
                return ['success' => false, 'error' => 'Failed to open ZIP archive'];
            }

            // Extract to temporary location first
            $tempPath = $this->backupPath . '/temp_restore_' . time();
            mkdir($tempPath, 0755, true);
            $zip->extractTo($tempPath);
            $zip->close();

            // Verify manifest
            $manifestPath = $tempPath . '/manifest.json';
            if (!file_exists($manifestPath)) {
                $this->cleanupTemp($tempPath);
                return ['success' => false, 'error' => 'Invalid backup: manifest missing'];
            }

            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                $this->cleanupTemp($tempPath);
                return ['success' => false, 'error' => 'Invalid backup: manifest unreadable'];
            }

            $expectedChecksums = is_array($manifest['file_checksums'] ?? null) ? $manifest['file_checksums'] : [];
            foreach ($expectedChecksums as $backupFileName => $expectedChecksum) {
                $safeName = basename((string)$backupFileName);
                $restoredFile = $tempPath . '/' . $safeName;
                if (!is_file($restoredFile)) {
                    $this->cleanupTemp($tempPath);
                    return ['success' => false, 'error' => "Backup integrity check failed: missing {$safeName}"];
                }
                if (!hash_equals((string)$expectedChecksum, hash_file('sha256', $restoredFile))) {
                    $this->cleanupTemp($tempPath);
                    return ['success' => false, 'error' => "Backup integrity check failed: checksum mismatch for {$safeName}"];
                }
            }

            // Move files to data directory
            $files = glob($tempPath . '/*.json.enc');
            foreach ($files as $file) {
                $destFile = $this->dataPath . '/' . basename($file);
                if (file_exists($destFile)) {
                    unlink($destFile); // Remove old file
                }
                rename($file, $destFile);
            }

            // Cleanup
            $this->cleanupTemp($tempPath);

            // Log the restore action
            $this->logRestoreAction($filename);

            return [
                'success' => true,
                'message' => 'Backup restored successfully',
                'restored_at' => date('c')
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get list of available backups
     */
    public function getBackupList(): array {
        $backups = [];
        $files = glob($this->backupPath . '/backup_*.zip');

        foreach ($files as $file) {
            $filename = basename($file);
            $matches = [];
            // Current format: backup_<type>_YYYY-MM-DD_HHMMSS_<id>.zip
            if (preg_match('/^backup_([^_]+)_(\d{4}-\d{2}-\d{2})_(\d{6})_(.+)\.zip$/', $filename, $matches)) {
                $dateStr = $matches[2];
                $sortDate = str_replace('-', '', $dateStr);
                $type = $matches[1];
                $time = $matches[3];
            // Legacy format fallback: backup_YYYY-MM-DD_HHMMSS_<type>.zip
            } elseif (preg_match('/^backup_(\d{4}-\d{2}-\d{2}|\d{8})_(\d{6})_(.+)\.zip$/', $filename, $matches)) {
                $dateStr = $matches[1];
                $sortDate = str_replace('-', '', $dateStr);
                $type = $matches[3];
                $time = $matches[2];
            } else {
                continue;
            }

            $backups[] = [
                'filename' => $filename,
                'date' => $dateStr,
                'sort_date' => $sortDate,
                'time' => $time,
                'type' => $type,
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'created_at' => date('c', filemtime($file))
            ];
        }

        // Sort by date/time descending
        usort($backups, function($a, $b) {
            return strcmp($b['sort_date'] . $b['time'], $a['sort_date'] . $a['time']);
        });

        return $backups;
    }

    /**
     * Get backup history from manifest
     */
    public function getBackupHistory(): array {
        $historyFile = $this->backupPath . '/backup_history.json';
        if (!file_exists($historyFile)) {
            return [];
        }

        $content = file_get_contents($historyFile);
        return json_decode($content, true) ?? [];
    }

    /**
     * Add entry to backup history
     */
    protected function addToHistory(array $entry): void {
        $history = $this->getBackupHistory();
        array_unshift($history, $entry);

        // Keep only last 100 entries
        $history = array_slice($history, 0, 100);

        $historyFile = $this->backupPath . '/backup_history.json';
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
    }

    /**
     * Delete a specific backup
     */
    public function deleteBackup(string $filename): array {
        $filepath = $this->backupPath . '/' . basename($filename);

        if (!file_exists($filepath)) {
            return ['success' => false, 'error' => 'Backup file not found'];
        }

        if (unlink($filepath)) {
            return ['success' => true, 'filename' => basename($filename)];
        }

        return ['success' => false, 'error' => 'Failed to delete backup'];
    }

    /**
     * Remove stale backups while preserving the newest N backups per type.
     */
    public function cleanupBackupsByType(int $keep = 7, ?array $types = null): array {
        $keep = max(1, $keep);
        $backups = $this->getBackupList();
        if ($types !== null) {
            $allowedTypes = array_fill_keys($types, true);
            $backups = array_values(array_filter($backups, static function(array $backup) use ($allowedTypes): bool {
                return isset($allowedTypes[$backup['type'] ?? '']);
            }));
        }

        $grouped = [];
        foreach ($backups as $backup) {
            $type = (string)($backup['type'] ?? 'unknown');
            $grouped[$type][] = $backup;
        }

        $deletedFiles = [];
        foreach ($grouped as $entries) {
            if (count($entries) <= $keep) {
                continue;
            }

            usort($entries, static function(array $a, array $b): int {
                return strcmp((string)$b['created_at'], (string)$a['created_at']);
            });

            foreach (array_slice($entries, $keep) as $backup) {
                $filepath = $this->backupPath . '/' . basename((string)$backup['filename']);
                if (is_file($filepath) && unlink($filepath)) {
                    $deletedFiles[] = basename((string)$backup['filename']);
                }
            }
        }

        return [
            'success' => true,
            'deleted_count' => count($deletedFiles),
            'deleted_files' => $deletedFiles
        ];
    }

    /**
     * Calculate checksum for backup file
     */
    protected function calculateChecksum(string $filepath): string {
        return hash_file('sha256', $filepath);
    }

    /**
     * Verify checksum for backup file
     */
    protected function verifyChecksum(string $filepath): bool {
        // Open ZIP and read manifest
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            return false;
        }

        $manifestContent = $zip->getFromName('manifest.json');
        $zip->close();

        if (!$manifestContent) {
            return false;
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest) {
            return false;
        }

        // Backward compatible: legacy manifests did not store a usable archive checksum.
        if (!empty($manifest['checksum'])) {
            return hash_equals((string)$manifest['checksum'], $this->calculateChecksum($filepath));
        }

        return isset($manifest['files']) && is_array($manifest['files']);
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Cleanup temporary directory
     */
    protected function cleanupTemp(string $path): void {
        if (!is_dir($path)) {
            return;
        }

        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($path);
    }

    /**
     * Log restore action to audit
     */
    protected function logRestoreAction(string $filename): void {
        // This will be called from API context where Auth is available
        if (class_exists('Audit')) {
            $masterPassword = getMasterPassword();
            if ($masterPassword === '') {
                return;
            }
            $db = new Database($masterPassword);
            $audit = new Audit($db);
            $user = Auth::user();
            $audit->log(Audit::EVENT_RESTORE, [
                'resource_type' => 'backup',
                'resource_id' => basename($filename),
                'details' => [
                    'filename' => basename($filename),
                    'user_id' => $user['id'] ?? 'system',
                    'user_email' => $user['email'] ?? 'system'
                ]
            ]);
        }
    }

    /**
     * Get backup statistics
     */
    public function getStats(): array {
        $backups = $this->getBackupList();
        $totalSize = array_sum(array_column($backups, 'size'));
        $latestBackup = $backups[0] ?? null;

        return [
            'total_backups' => count($backups),
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatBytes($totalSize),
            'latest_backup' => $latestBackup,
            'auto_backups' => array_values(array_filter($backups, fn($b) => str_starts_with($b['filename'], 'backup_auto_'))),
            'retention_daily' => self::RETENTION_DAILY,
            'retention_weekly' => self::RETENTION_WEEKLY,
            'retention_monthly' => self::RETENTION_MONTHLY
        ];
    }

    /**
     * Cleanup old backups by type
     */
    public function cleanupOldBackups(string $type = 'auto', int $keep = 7): int {
        $pattern = $this->backupPath . '/backup_' . $type . '_*.zip';
        $files = glob($pattern);

        if (empty($files)) {
            return 0;
        }

        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Delete all but the last $keep files
        $deleted = 0;
        $toDelete = max(0, count($files) - $keep);

        for ($i = 0; $i < $toDelete; $i++) {
            if (unlink($files[$i])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get all persisted backup settings.
     */
    public function getSettings(): array {
        $configFile = $this->dataPath . '/backup_settings.json';
        if (!file_exists($configFile)) {
            return [];
        }

        $settings = json_decode((string)file_get_contents($configFile), true);
        return is_array($settings) ? $settings : [];
    }

    /**
     * Get a backup setting value
     */
    public function getSetting(string $key, $default = null) {
        $configFile = $this->dataPath . '/backup_settings.json';
        if (!file_exists($configFile)) {
            return $default;
        }
        $settings = json_decode(file_get_contents($configFile), true) ?: [];
        return $settings[$key] ?? $default;
    }

    /**
     * Set a backup setting value
     */
    public function setSetting(string $key, $value): void {
        $configFile = $this->dataPath . '/backup_settings.json';
        $settings = [];
        if (file_exists($configFile)) {
            $settings = json_decode(file_get_contents($configFile), true) ?: [];
        }
        $settings[$key] = $value;
        file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT));
    }

    /**
     * Restore a backup from an uploaded file
     * Handles uploaded ZIP files from external sources (for migration)
     *
     * @param string $uploadedFile Path to the uploaded temporary file
     * @param string $originalFilename Original filename of the uploaded file
     * @return array Result with success status and message
     */
    public function restoreFromUpload(string $uploadedFile, string $originalFilename): array {
        // Validate file exists
        if (!file_exists($uploadedFile)) {
            return ['success' => false, 'error' => 'Uploaded file not found'];
        }

        // Validate file extension
        if (strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION)) !== 'zip') {
            return ['success' => false, 'error' => 'Invalid file format. Only .zip files are supported.'];
        }

        // Validate file size (max 100MB)
        $maxSize = 100 * 1024 * 1024; // 100MB
        if (filesize($uploadedFile) > $maxSize) {
            return ['success' => false, 'error' => 'File too large. Maximum size is 100MB.'];
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($uploadedFile) !== true) {
                return ['success' => false, 'error' => 'Failed to open ZIP archive. The file may be corrupted.'];
            }

            // Check for manifest.json (valid backup indicator)
            $manifestContent = $zip->getFromName('manifest.json');
            if ($manifestContent === false) {
                $zip->close();
                return [
                    'success' => false,
                    'error' => 'Invalid backup file: manifest.json not found. Please ensure this is a valid LazyMan backup.'
                ];
            }

            $manifest = json_decode($manifestContent, true);
            if (!is_array($manifest)) {
                $zip->close();
                return ['success' => false, 'error' => 'Invalid backup file: manifest.json is corrupted.'];
            }

            // Extract to temporary location first
            $tempPath = $this->backupPath . '/temp_upload_' . time();
            mkdir($tempPath, 0755, true);
            $zip->extractTo($tempPath);
            $zip->close();

            // Verify file checksums if present in manifest
            $expectedChecksums = is_array($manifest['file_checksums'] ?? null) ? $manifest['file_checksums'] : [];
            $restoredFiles = [];

            foreach ($expectedChecksums as $backupFileName => $expectedChecksum) {
                $safeName = basename((string)$backupFileName);
                $restoredFile = $tempPath . '/' . $safeName;

                if (!is_file($restoredFile)) {
                    $this->cleanupTemp($tempPath);
                    return ['success' => false, 'error' => "Backup integrity check failed: missing {$safeName}"];
                }

                // Verify checksum
                $actualChecksum = hash_file('sha256', $restoredFile);
                if (!hash_equals((string)$expectedChecksum, $actualChecksum)) {
                    $this->cleanupTemp($tempPath);
                    return ['success' => false, 'error' => "Backup integrity check failed: checksum mismatch for {$safeName}. File may be corrupted."];
                }

                $restoredFiles[] = $safeName;
            }

            // If no checksums in manifest (legacy format), just check for .json.enc files
            if (empty($expectedChecksums)) {
                $files = glob($tempPath . '/*.json.enc');
                if (empty($files)) {
                    $this->cleanupTemp($tempPath);
                    return ['success' => false, 'error' => 'No data files found in backup.'];
                }
                foreach ($files as $file) {
                    $restoredFiles[] = basename($file);
                }
            }

            // Move files to data directory (replacing existing files)
            foreach ($restoredFiles as $filename) {
                $sourceFile = $tempPath . '/' . $filename;
                $destFile = $this->dataPath . '/' . $filename;

                if (file_exists($destFile)) {
                    // Backup existing file before replacing
                    $backupExisting = $this->dataPath . '/' . $filename . '.before_restore.' . time();
                    rename($destFile, $backupExisting);
                }

                rename($sourceFile, $destFile);
            }

            // Cleanup temp directory
            $this->cleanupTemp($tempPath);

            // Save the uploaded file to backups directory for future reference
            $savedFilename = 'uploaded_' . basename($originalFilename, '.zip') . '_' . time() . '.zip';
            $savedPath = $this->backupPath . '/' . $savedFilename;
            rename($uploadedFile, $savedPath);

            // Log the restore action
            $this->logRestoreAction($savedFilename);

            return [
                'success' => true,
                'message' => 'Backup uploaded and restored successfully',
                'restored_at' => date('c'),
                'files_restored' => count($restoredFiles),
                'saved_filename' => $savedFilename
            ];

        } catch (Exception $e) {
            // Cleanup temp directory if it exists
            if (isset($tempPath) && is_dir($tempPath)) {
                $this->cleanupTemp($tempPath);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
