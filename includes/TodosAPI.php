<?php
require_once __DIR__ . '/BaseAPI.php';

class TodosAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return ['title', 'description', 'status', 'priority', 'dueDate', 'category', 'createdAt', 'updatedAt', 'completedAt'];
    }

    public function create(array $data): ?array {
        $data['createdAt'] = date('c');
        $data['updatedAt'] = date('c');
        $data['status'] = $data['status'] ?? 'pending';
        return parent::create($data);
    }

    public function update(string $id, array $data): ?array {
        $data['updatedAt'] = date('c');
        
        // Auto-set completedAt if status changes to completed/done
        if (isset($data['status']) && in_array($data['status'], ['completed', 'done'])) {
            $existing = $this->find($id);
            if ($existing && empty($existing['completedAt'])) {
                $data['completedAt'] = date('c');
            }
        }
        
        return parent::update($id, $data);
    }
}
