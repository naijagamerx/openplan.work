<?php
/**
 * Database Class - JSON file handling with encryption
 */

class Database {
    private Encryption $encryption;
    private string $dataPath;
    
    public function __construct(string $masterPassword) {
        $this->encryption = new Encryption($masterPassword);
        $this->dataPath = DATA_PATH;
        
        // Ensure data directory exists
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
    }
    
    /**
     * Get the file path for a collection
     */
    private function getFilePath(string $collection): string {
        return $this->dataPath . '/' . $collection . '.json.enc';
    }
    
    /**
     * Load a collection
     */
    public function load(string $collection): array {
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
            error_log("Failed to load collection {$collection}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Save a collection
     */
    public function save(string $collection, array $data): bool {
        $filePath = $this->getFilePath($collection);
        
        try {
            $encryptedData = $this->encryption->encrypt($data);
            return file_put_contents($filePath, $encryptedData) !== false;
        } catch (Exception $e) {
            error_log("Failed to save collection {$collection}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find a record by ID
     */
    public function findById(string $collection, string $id): ?array {
        $data = $this->load($collection);
        
        foreach ($data as $item) {
            if (isset($item['id']) && $item['id'] === $id) {
                return $item;
            }
        }
        
        return null;
    }
    
    /**
     * Find records by field value
     */
    public function findBy(string $collection, string $field, mixed $value): array {
        $data = $this->load($collection);
        
        return array_filter($data, function($item) use ($field, $value) {
            return isset($item[$field]) && $item[$field] === $value;
        });
    }
    
    /**
     * Insert a new record
     */
    public function insert(string $collection, array $record): bool {
        $data = $this->load($collection);
        
        // Generate ID if not provided
        if (!isset($record['id'])) {
            $record['id'] = $this->generateId();
        }
        
        // Add timestamps
        $record['createdAt'] = date('c');
        $record['updatedAt'] = date('c');
        
        $data[] = $record;
        
        return $this->save($collection, $data);
    }
    
    /**
     * Update a record
     */
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
    
    /**
     * Delete a record
     */
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
    
    /**
     * Generate a unique ID
     */
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
    
    /**
     * Export all collections as a backup
     */
    public function exportAll(): array {
        $collections = ['config', 'users', 'projects', 'clients', 'invoices', 'finance', 'inventory', 'templates'];
        $export = [];
        
        foreach ($collections as $collection) {
            $export[$collection] = $this->load($collection);
        }
        
        return $export;
    }
    
    /**
     * Import data from backup
     */
    public function importAll(array $data): bool {
        foreach ($data as $collection => $records) {
            if (!$this->save($collection, $records)) {
                return false;
            }
        }
        return true;
    }
}
