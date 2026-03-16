<?php
/**
 * Base API Class - Provides consistent CRUD operations for API endpoints
 */

abstract class BaseAPI {
    protected Database $db;
    protected string $collection;

    public function __construct(Database $db, string $collection) {
        $this->db = $db;
        $this->collection = $collection;
    }

    /**
     * Find a record by ID
     */
    public function find(string $id): ?array {
        return $this->db->findById($this->collection, $id);
    }

    /**
     * Find all records
     */
    public function findAll(): array {
        return $this->db->load($this->collection);
    }

    /**
     * Find records by field value
     */
    public function findBy(string $field, mixed $value): array {
        return $this->db->findBy($this->collection, $field, $value);
    }

    /**
     * Create a new record
     */
    public function create(array $data): ?array {
        // Filter to only allowed fields
        $allowedFields = $this->getAllowedFields();
        $record = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $record[$field] = $data[$field];
            }
        }

        // Generate ID if not provided, ensuring we know it for retrieval
        if (!isset($record['id'])) {
            $record['id'] = $this->db->generateId();
        }

        if ($this->db->insert($this->collection, $record)) {
            return $this->db->findById($this->collection, $record['id']);
        }

        return null;
    }

    /**
     * Update an existing record
     */
    public function update(string $id, array $data): ?array {
        // Filter to only allowed fields
        $allowedFields = $this->getAllowedFields();
        $updates = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[$field] = $data[$field];
            }
        }

        if ($this->db->update($this->collection, $id, $updates)) {
            return $this->db->findById($this->collection, $id);
        }

        return null;
    }

    /**
     * Delete a record
     */
    public function delete(string $id): bool {
        return $this->db->delete($this->collection, $id);
    }

    /**
     * Check if record exists
     */
    public function exists(string $id): bool {
        return $this->find($id) !== null;
    }

    /**
     * Get fields that can be set/updated via API
     */
    abstract protected function getAllowedFields(): array;

    /**
     * Validate required fields for creation
     */
    public function validateRequired(array $data, array $requiredFields): ?string {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return 'Missing required fields: ' . implode(', ', $missing);
        }

        return null;
    }

    /**
     * Send not found error
     */
    protected function notFound(string $resource = 'Resource'): void {
        errorResponse($resource . ' not found', 404, ERROR_NOT_FOUND);
    }

    /**
     * Send validation error
     */
    protected function validationError(string $message): void {
        errorResponse($message, 400, ERROR_VALIDATION);
    }
}
