<?php
require_once __DIR__ . '/BaseAPI.php';

class ProjectsAPI extends BaseAPI {
    protected function getAllowedFields(): array {
        return ['name', 'description', 'clientId', 'status', 'color', 'tasks', 'createdAt', 'updatedAt'];
    }

    public function findAll(): array {
        $projects = parent::findAll();
        foreach ($projects as $key => $project) {
            $tasks = $project['tasks'] ?? [];
            $projects[$key]['taskCount'] = count($tasks);
            $projects[$key]['completedCount'] = count(array_filter($tasks, fn($t) => isTaskDone($t['status'] ?? '')));
        }
        return $projects;
    }

    public function create(array $data): ?array {
        $data['createdAt'] = date('c');
        $data['updatedAt'] = date('c');
        $data['tasks'] = [];
        return parent::create($data);
    }

    public function update(string $id, array $data): ?array {
        $data['updatedAt'] = date('c');
        return parent::update($id, $data);
    }
}
