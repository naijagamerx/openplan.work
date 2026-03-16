<?php
/**
 * Database Class - JSON file handling with encryption
 */

class Database {
    protected Encryption $encryption;
    protected string $masterPassword;
    protected string $dataPath;
    protected ?string $userId;

    public function __construct(string $masterPassword, ?string $userId = null) {
        $this->masterPassword = $masterPassword;
        $this->encryption = new Encryption($masterPassword);
        $this->userId = $userId;

        if ($this->userId) {
            if (!preg_match('/^[a-f0-9-]{36}$/i', $this->userId)) {
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $this->userId)) {
                    throw new Exception('Invalid user ID format');
                }
            }
            $this->dataPath = DATA_PATH . '/users/' . $this->userId;
        } else {
            $this->dataPath = DATA_PATH;
        }

        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
    }

    public function getDataPath(): string {
        return $this->dataPath;
    }

    public function createScoped(?string $userId = null): self {
        return new self($this->masterPassword, $userId);
    }

    protected function getFilePath(string $collection): string {
        if ($collection === 'users') {
            return DATA_PATH . '/users.json';
        }

        return $this->dataPath . '/' . $collection . '.json.enc';
    }

    protected function getLegacyFilePath(string $collection): ?string {
        if ($collection === 'users') {
            return DATA_PATH . '/users.json.enc';
        }

        return null;
    }

    protected function isPlainCollection(string $collection): bool {
        return $collection === 'users';
    }

    public function load(string $collection, bool $strict = true): array {
        if ($this->isPlainCollection($collection)) {
            return $this->loadPlainCollection($collection, $strict);
        }

        $filePath = $this->getFilePath($collection);

        if (!file_exists($filePath)) {
            return [];
        }

        $encryptedData = file_get_contents($filePath);
        if (empty($encryptedData)) {
            return [];
        }

        try {
            $data = $this->encryption->decrypt($encryptedData);
            return is_array($data) ? $data : [];
        } catch (Exception $e) {
            error_log("Decryption failed for {$collection}: " . $e->getMessage());
            if ($strict) {
                throw $e;
            }
            return [];
        }
    }

    public function safeLoad(string $collection): array {
        if ($this->isPlainCollection($collection)) {
            try {
                return [
                    'data' => $this->loadPlainCollection($collection, true),
                    'error' => null,
                    'success' => true
                ];
            } catch (Exception $e) {
                return [
                    'data' => [],
                    'error' => $e->getMessage(),
                    'success' => false,
                    'corrupted' => true
                ];
            }
        }

        $filePath = $this->getFilePath($collection);

        if (!file_exists($filePath)) {
            return ['data' => [], 'error' => null, 'success' => true];
        }

        $encryptedData = file_get_contents($filePath);
        if (empty($encryptedData)) {
            return ['data' => [], 'error' => null, 'success' => true];
        }

        try {
            $data = $this->encryption->decrypt($encryptedData);
            return [
                'data' => is_array($data) ? $data : [],
                'error' => null,
                'success' => true
            ];
        } catch (Exception $e) {
            error_log("Decryption failed for {$collection}: " . $e->getMessage());
            return [
                'data' => [],
                'error' => $e->getMessage(),
                'success' => false,
                'corrupted' => true
            ];
        }
    }

    public function save(string $collection, array $data): bool {
        if ($this->isPlainCollection($collection)) {
            return $this->savePlainCollection($collection, $data);
        }

        $filePath = $this->getFilePath($collection);

        try {
            $encryptedData = $this->encryption->encrypt($data);

            $fp = fopen($filePath, 'w');
            if (!$fp) {
                throw new Exception("Could not open file for writing: $filePath");
            }

            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $encryptedData);
                fflush($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                return true;
            }

            fclose($fp);
            throw new Exception("Could not lock file for writing: $filePath");
        } catch (Exception $e) {
            error_log("Failed to save collection {$collection}: " . $e->getMessage());
            return false;
        }
    }

    public function findById(string $collection, string $id): ?array {
        $data = $this->load($collection);

        foreach ($data as $item) {
            if (isset($item['id']) && $item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findBy(string $collection, string $field, mixed $value): array {
        $data = $this->load($collection);

        return array_filter($data, function($item) use ($field, $value) {
            return isset($item[$field]) && $item[$field] === $value;
        });
    }

    public function insert(string $collection, array $record): bool {
        $data = $this->load($collection);

        if (!isset($record['id'])) {
            $record['id'] = $this->generateId();
        }

        $record['createdAt'] = date('c');
        $record['updatedAt'] = date('c');

        $data[] = $record;

        return $this->save($collection, $data);
    }

    public function update(string $collection, string $id, array $updates): bool {
        $data = $this->load($collection);
        $found = false;

        foreach ($data as $key => $item) {
            if (isset($item['id']) && $item['id'] === $id) {
                $data[$key] = array_merge($item, $updates);
                $data[$key]['updatedAt'] = date('c');
                $found = true;
                break;
            }
        }

        if (!$found) {
            return false;
        }

        return $this->save($collection, $data);
    }

    public function delete(string $collection, string $id): bool {
        $data = $this->load($collection);
        $initialCount = count($data);

        $data = array_filter($data, function($item) use ($id) {
            return !isset($item['id']) || $item['id'] !== $id;
        });

        if (count($data) === $initialCount) {
            return false;
        }

        return $this->save($collection, array_values($data));
    }

    public function generateId(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function exportAll(): array {
        $collections = [];
        foreach (glob($this->dataPath . '/*.json.enc') as $file) {
            $collection = basename($file, '.json.enc');
            if ($collection !== 'sessions' && $collection !== 'rate_limits' && $collection !== 'scheduler_config') {
                $collections[] = $collection;
            }
        }

        $export = [];
        foreach ($collections as $collection) {
            $export[$collection] = $this->load($collection);
        }

        if ($this->userId === null) {
            $export['users'] = $this->load('users', false);
        }

        return $export;
    }

    public function importAll(array $data): bool {
        foreach ($data as $collection => $records) {
            if ($collection === 'users' && $this->userId) {
                continue;
            }

            if (!$this->save($collection, $records)) {
                return false;
            }
        }
        return true;
    }

    protected function loadPlainCollection(string $collection, bool $strict = true): array {
        $filePath = $this->getFilePath($collection);
        if (file_exists($filePath)) {
            $content = file_get_contents($filePath);
            if ($content === false || trim($content) === '') {
                return [];
            }

            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            if ($strict) {
                throw new Exception("Invalid JSON data in {$collection}");
            }

            return [];
        }

        $legacyFilePath = $this->getLegacyFilePath($collection);
        if ($legacyFilePath !== null && file_exists($legacyFilePath)) {
            return $this->migrateLegacyPlainCollection($collection, $legacyFilePath, $strict);
        }

        return [];
    }

    protected function savePlainCollection(string $collection, array $data): bool {
        $filePath = $this->getFilePath($collection);

        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new Exception("Could not encode {$collection} as JSON");
            }

            $fp = fopen($filePath, 'c+');
            if (!$fp) {
                throw new Exception("Could not open file for writing: $filePath");
            }

            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                throw new Exception("Could not lock file for writing: $filePath");
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            return true;
        } catch (Exception $e) {
            error_log("Failed to save collection {$collection}: " . $e->getMessage());
            return false;
        }
    }

    protected function migrateLegacyPlainCollection(string $collection, string $legacyFilePath, bool $strict): array {
        $encryptedData = file_get_contents($legacyFilePath);
        if ($encryptedData === false || trim($encryptedData) === '') {
            return [];
        }

        $candidatePasswords = [$this->masterPassword];
        if ($collection === 'users') {
            $fallbackMasterPassword = trim(getMasterPassword());
            if ($fallbackMasterPassword !== '' && !in_array($fallbackMasterPassword, $candidatePasswords, true)) {
                $candidatePasswords[] = $fallbackMasterPassword;
            }
        }

        try {
            $data = null;
            $migrationPassword = null;

            foreach ($candidatePasswords as $candidatePassword) {
                try {
                    $candidateEncryption = new Encryption($candidatePassword);
                    $data = $candidateEncryption->decrypt($encryptedData);
                    $migrationPassword = $candidatePassword;
                    break;
                } catch (Exception $ignored) {
                    continue;
                }
            }

            if ($migrationPassword === null) {
                throw new Exception('Unable to decrypt legacy registry with available master passwords');
            }

            $this->masterPassword = $migrationPassword;
            $this->encryption = new Encryption($migrationPassword);
            $decoded = is_array($data) ? $data : [];
            $this->backupLegacyCollection($legacyFilePath, $collection);

            if ($collection === 'users') {
                $this->seedLegacyUserKeyChecks($decoded);
            }

            if (!$this->savePlainCollection($collection, $decoded)) {
                throw new Exception("Failed to persist migrated {$collection} data");
            }

            return $decoded;
        } catch (Exception $e) {
            error_log("Migration failed for {$collection}: " . $e->getMessage());
            if ($strict) {
                throw $e;
            }

            return [];
        }
    }

    protected function backupLegacyCollection(string $legacyFilePath, string $collection): void {
        $backupDir = DATA_PATH . '/migration_backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupPath = $backupDir . '/' . $collection . '.legacy-' . date('Ymd-His') . '.bak';
        if (!file_exists($backupPath)) {
            copy($legacyFilePath, $backupPath);
        }
    }

    protected function seedLegacyUserKeyChecks(array $users): void {
        foreach ($users as $user) {
            $userId = trim((string)($user['id'] ?? ''));
            if ($userId === '') {
                continue;
            }

            $userDb = new self($this->masterPassword, $userId);
            $existing = $userDb->load('key_check', false);
            if (!empty($existing)) {
                continue;
            }

            $userDb->save('key_check', [
                'version' => 1,
                'userId' => $userId,
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ]);
        }
    }
}
