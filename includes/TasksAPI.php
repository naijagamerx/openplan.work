<?php

require_once __DIR__ . '/BaseAPI.php';

class TasksAPI extends BaseAPI
{
    protected function getAllowedFields(): array
    {
        return ['title', 'description', 'status', 'priority', 'startDate', 'dueDate', 'estimatedMinutes', 'actualMinutes', 'subtasks', 'timeEntries', 'timerState', 'recurrence', 'parentTaskId', 'linkedHabitId', 'linkedInventoryIds', 'createdAt', 'updatedAt', 'completedAt', 'projectId'];
    }

    public function findAll(array $filters = []): array
    {
        $projects = $this->db->load('projects');
        $allTasks = [];

        foreach ($projects as $project) {
            if (!empty($filters['projectId']) && $project['id'] !== $filters['projectId']) {
                continue;
            }

            foreach ($project['tasks'] ?? [] as $task) {
                $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
                $task['subtasks'] = $this->normalizeSubtasks($task['subtasks'] ?? []);
                $task['projectName'] = $project['name'];
                $task['projectId'] = $project['id'];
                $allTasks[] = $task;
            }
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $filterStatus = normalizeTaskStatus($filters['status'], '');
            $allTasks = array_filter($allTasks, fn ($t) => normalizeTaskStatus($t['status'] ?? '') === $filterStatus);
        }
        if (!empty($filters['priority'])) {
            $allTasks = array_filter($allTasks, fn ($t) => ($t['priority'] ?? '') === $filters['priority']);
        }

        // Sort by due date
        usort($allTasks, function ($a, $b) {
            $dateA = $a['dueDate'] ?? '9999-12-31';
            $dateB = $b['dueDate'] ?? '9999-12-31';
            return strcmp($dateA, $dateB);
        });

        return array_values($allTasks);
    }

    public function find(string $id): ?array
    {
        $projects = $this->db->load('projects');
        foreach ($projects as $project) {
            foreach ($project['tasks'] ?? [] as $task) {
                if ($task['id'] === $id) {
                    $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
                    $task['subtasks'] = $this->normalizeSubtasks($task['subtasks'] ?? []);
                    $task['projectName'] = $project['name'];
                    $task['projectId'] = $project['id'];
                    return $task;
                }
            }
        }
        return null;
    }

    public function create(array $data): ?array
    {
        $projects = $this->db->load('projects');
        $projects = $this->resolveProjectsForCreate($projects, $data);
        $newTask = null;

        foreach ($projects as $key => $project) {
            if ($project['id'] !== ($data['projectId'] ?? null)) {
                continue;
            }

            $newTask = $this->buildTaskForCreate($data);
            $projects[$key]['tasks'][] = $newTask;
            break;
        }

        if ($newTask === null) {
            return null;
        }

        if ($this->db->save('projects', $projects)) {
            return $newTask;
        }

        return null;
    }

    public function update(string $id, array $data): ?array
    {
        $projects = $this->db->load('projects');
        $updatedTask = null;
        $createdRecurringTask = null;

        foreach ($projects as $pKey => $project) {
            foreach ($project['tasks'] ?? [] as $tKey => $task) {
                if ($task['id'] === $id) {
                    $isAddingTimeEntry = !empty($data['addTimeEntry']) && !empty($data['timeEntries']);
                    $normalizedData = $this->normalizeTaskUpdateData($data);
                    $projects[$pKey]['tasks'][$tKey] = $this->applyTaskUpdate(
                        $projects[$pKey]['tasks'][$tKey],
                        $normalizedData,
                        $task,
                        $isAddingTimeEntry
                    );

                    $currentTask = $projects[$pKey]['tasks'][$tKey];
                    $newStatus = $currentTask['status'] ?? 'todo';
                    $wasDone = isTaskDone($task['status'] ?? '');
                    if (!$wasDone && isTaskDone($newStatus) && empty($currentTask['recurrenceGeneratedAt'] ?? null)) {
                        $createdRecurringTask = $this->createNextRecurringTask($projects, $pKey, $tKey);
                    }

                    $updatedTask = $projects[$pKey]['tasks'][$tKey];
                    break 2;
                }
            }
        }

        if ($updatedTask !== null) {
            if (!$this->db->save('projects', $projects)) {
                return null;
            }

            if ($createdRecurringTask) {
                $updatedTask['nextRecurringTaskId'] = $createdRecurringTask['id'];
            }

            return $updatedTask;
        }

        return null;
    }

    public function delete(string $id): bool
    {
        $projects = $this->db->load('projects');
        $taskFound = false;

        foreach ($projects as $pKey => $project) {
            foreach ($project['tasks'] ?? [] as $tKey => $task) {
                if ($task['id'] === $id) {
                    array_splice($projects[$pKey]['tasks'], $tKey, 1);
                    $taskFound = true;
                    break 2;
                }
            }
        }

        if ($taskFound) {
            return $this->db->save('projects', $projects);
        }

        return false;
    }

    public function addSubtask(string $taskId, array $data): ?array
    {
        $projects = $this->db->load('projects');
        $taskFound = false;
        $updatedTask = null;

        foreach ($projects as $pKey => $project) {
            foreach ($project['tasks'] ?? [] as $tKey => $task) {
                if ($task['id'] === $taskId) {
                    // If updating existing subtask
                    if (!empty($data['subtaskId'])) {
                        $subtaskFound = false;
                        foreach ($projects[$pKey]['tasks'][$tKey]['subtasks'] ?? [] as $sKey => $subtask) {
                            if ($subtask['id'] === $data['subtaskId'] || (isset($subtask['id']) && $subtask['id'] === $data['subtaskId'])) {
                                $projects[$pKey]['tasks'][$tKey]['subtasks'][$sKey]['completed'] = $data['completed'] ?? false;
                                if ($data['completed'] ?? false) {
                                    $projects[$pKey]['tasks'][$tKey]['subtasks'][$sKey]['completedAt'] = date('c');
                                }
                                $subtaskFound = true;
                                break;
                            }
                        }

                        // If subtask not found by ID, try by index for backward compatibility
                        if (!$subtaskFound && is_numeric($data['subtaskId'])) {
                            $index = intval($data['subtaskId']);
                            if (isset($projects[$pKey]['tasks'][$tKey]['subtasks'][$index])) {
                                $projects[$pKey]['tasks'][$tKey]['subtasks'][$index]['completed'] = $data['completed'] ?? false;
                                if ($data['completed'] ?? false) {
                                    $projects[$pKey]['tasks'][$tKey]['subtasks'][$index]['completedAt'] = date('c');
                                }
                            }
                        }
                    } else {
                        // Add new subtask
                        $newSubtask = [
                            'id' => $this->db->generateId(),
                            'title' => $data['title'] ?? '',
                            'completed' => false,
                            'estimatedMinutes' => $data['estimatedMinutes'] ?? 0
                        ];

                        $projects[$pKey]['tasks'][$tKey]['subtasks'][] = $newSubtask;
                    }
                    $projects[$pKey]['tasks'][$tKey]['subtasks'] = $this->normalizeSubtasks($projects[$pKey]['tasks'][$tKey]['subtasks'] ?? []);
                    $projects[$pKey]['tasks'][$tKey]['updatedAt'] = date('c');
                    $updatedTask = $projects[$pKey]['tasks'][$tKey];
                    $taskFound = true;
                    break 2;
                }
            }
        }

        if ($taskFound) {
            if ($this->db->save('projects', $projects)) {
                return $updatedTask;
            }
        }

        return null;
    }

    public function getTemplates(): array
    {
        $templates = $this->db->load('task_templates');
        usort($templates, function ($a, $b) {
            return strcmp($b['updatedAt'] ?? '', $a['updatedAt'] ?? '');
        });
        return $templates;
    }

    public function saveTemplate(array $data): ?array
    {
        $templates = $this->db->load('task_templates');
        $templateId = trim((string)($data['templateId'] ?? ''));
        $name = trim((string)($data['templateName'] ?? ''));
        if ($name === '') {
            return null;
        }

        $templatePayload = [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'priority' => $data['priority'] ?? 'medium',
            'estimatedMinutes' => (int)($data['estimatedMinutes'] ?? 0),
            'subtasks' => $this->normalizeTemplateSubtasks($data['subtasks'] ?? []),
            'recurrence' => $this->normalizeRecurrence($data['recurrence'] ?? []),
            'startDate' => $data['startDate'] ?? null,
            'dueDate' => $data['dueDate'] ?? null
        ];

        if ($templateId !== '') {
            foreach ($templates as $index => $template) {
                if (($template['id'] ?? '') === $templateId) {
                    $templates[$index]['name'] = $name;
                    $templates[$index]['task'] = $templatePayload;
                    $templates[$index]['updatedAt'] = date('c');
                    if ($this->db->save('task_templates', $templates)) {
                        return $templates[$index];
                    }
                    return null;
                }
            }
        }

        $newTemplate = [
            'id' => $this->db->generateId(),
            'name' => $name,
            'task' => $templatePayload,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
        $templates[] = $newTemplate;
        if ($this->db->save('task_templates', $templates)) {
            return $newTemplate;
        }
        return null;
    }

    public function deleteTemplate(string $templateId): bool
    {
        $templates = $this->db->load('task_templates');
        $originalCount = count($templates);
        $templates = array_values(array_filter($templates, fn ($template) => ($template['id'] ?? '') !== $templateId));
        if ($originalCount === count($templates)) {
            return false;
        }
        return $this->db->save('task_templates', $templates);
    }

    public function createFromTemplate(string $templateId, array $overrides = []): ?array
    {
        $templates = $this->db->load('task_templates');
        $template = null;
        foreach ($templates as $candidate) {
            if (($candidate['id'] ?? '') === $templateId) {
                $template = $candidate;
                break;
            }
        }
        if (!$template) {
            return null;
        }

        $taskData = $template['task'] ?? [];
        $merged = array_merge($taskData, $overrides);
        $merged['subtasks'] = $this->normalizeSubtasks($this->normalizeTemplateSubtasks($merged['subtasks'] ?? []));
        $merged['recurrence'] = $this->normalizeRecurrence($merged['recurrence'] ?? []);
        return $this->create($merged);
    }

    private function normalizeRecurrence(array $recurrence): array
    {
        $enabled = (bool)($recurrence['enabled'] ?? false);
        $frequency = (string)($recurrence['frequency'] ?? 'weekly');
        $interval = max(1, (int)($recurrence['interval'] ?? 1));
        if (!in_array($frequency, ['daily', 'weekly', 'monthly'])) {
            $frequency = 'weekly';
        }

        return [
            'enabled' => $enabled,
            'frequency' => $frequency,
            'interval' => $interval
        ];
    }

    private function resolveProjectsForCreate(array $projects, array &$data): array
    {
        if (!empty($data['projectId'])) {
            return $projects;
        }

        $inboxProject = $this->findInboxProject($projects);
        if ($inboxProject === null) {
            $inboxProject = $this->buildInboxProject();
            $projects[] = $inboxProject;
            $this->db->save('projects', $projects);
            $projects = $this->db->load('projects');
        }

        $data['projectId'] = $inboxProject['id'];
        return $projects;
    }

    private function findInboxProject(array $projects): ?array
    {
        foreach ($projects as $project) {
            if (isset($project['isInbox']) && $project['isInbox'] === true) {
                return $project;
            }
        }

        return null;
    }

    private function buildInboxProject(): array
    {
        return [
            'id' => $this->db->generateId(),
            'name' => 'Inbox',
            'description' => 'Tasks without a project',
            'status' => 'active',
            'color' => '#6B7280',
            'isInbox' => true,
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
            'tasks' => [],
        ];
    }

    private function buildTaskForCreate(array $data): array
    {
        $status = normalizeTaskStatus($data['status'] ?? 'todo');
        $newTask = [
            'id' => $this->db->generateId(),
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'status' => $status,
            'priority' => $data['priority'] ?? 'medium',
            'startDate' => $data['startDate'] ?? null,
            'dueDate' => $data['dueDate'] ?? null,
            'estimatedMinutes' => $data['estimatedMinutes'] ?? 0,
            'actualMinutes' => 0,
            'subtasks' => $this->normalizeSubtasks($data['subtasks'] ?? []),
            'recurrence' => $this->normalizeRecurrence($data['recurrence'] ?? []),
            'linkedHabitId' => $data['linkedHabitId'] ?? null,
            'createdAt' => date('c'),
            'updatedAt' => date('c'),
        ];

        foreach ($this->getAllowedFields() as $field) {
            if (isset($data[$field]) && !array_key_exists($field, $newTask)) {
                $newTask[$field] = $data[$field];
            }
        }

        if ($status === 'done') {
            $newTask['completedAt'] = date('c');
        }

        return $newTask;
    }

    private function normalizeTaskUpdateData(array $data): array
    {
        if (isset($data['status'])) {
            $data['status'] = normalizeTaskStatus($data['status']);
        }

        if (isset($data['recurrence']) && is_array($data['recurrence'])) {
            $data['recurrence'] = $this->normalizeRecurrence($data['recurrence']);
        }

        return $data;
    }

    private function applyTaskUpdate(array $task, array $data, array $originalTask, bool $isAddingTimeEntry): array
    {
        $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');

        if (isset($data['subtasks'])) {
            $task['subtasks'] = $this->normalizeSubtasks($data['subtasks']);
        }

        foreach ($this->getAllowedFields() as $field) {
            if (!isset($data[$field])) {
                continue;
            }

            if (in_array($field, ['subtasks', 'timeEntries', 'actualMinutes'], true)) {
                continue;
            }

            $task[$field] = $data[$field];
        }

        $task['subtasks'] = $this->normalizeSubtasks($task['subtasks'] ?? []);
        $task['updatedAt'] = date('c');

        if ($isAddingTimeEntry && is_array($data['timeEntries'])) {
            $timeEntry = $data['timeEntries'];
            $task['timeEntries'] = $originalTask['timeEntries'] ?? [];
            $task['timeEntries'][] = [
                'date' => $timeEntry['date'] ?? date('c'),
                'minutes' => $timeEntry['minutes'] ?? 0,
                'description' => $timeEntry['description'] ?? 'Time entry',
            ];

            $actualMinutes = 0;
            foreach ($task['timeEntries'] as $entry) {
                $actualMinutes += $entry['minutes'] ?? 0;
            }
            $task['actualMinutes'] = $actualMinutes;
        }

        if (isTaskDone($data['status'] ?? '') && empty($originalTask['completedAt'])) {
            $task['completedAt'] = date('c');
        }

        return $task;
    }

    private function normalizeTemplateSubtasks(array $subtasks): array
    {
        $normalized = [];
        foreach ($subtasks as $subtask) {
            if (!is_array($subtask)) {
                continue;
            }
            $title = trim((string)($subtask['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $normalized[] = [
                'title' => $title,
                'estimatedMinutes' => (int)($subtask['estimatedMinutes'] ?? 0)
            ];
        }
        return $normalized;
    }

    private function normalizeSubtasks(array $subtasks): array
    {
        $normalized = [];
        foreach ($subtasks as $subtask) {
            if (!is_array($subtask)) {
                continue;
            }

            $title = trim((string)($subtask['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $normalized[] = [
                'id' => (string)($subtask['id'] ?? $this->db->generateId()),
                'title' => $title,
                'completed' => (bool)($subtask['completed'] ?? false),
                'completedAt' => !empty($subtask['completedAt']) ? $subtask['completedAt'] : null,
                'estimatedMinutes' => (int)($subtask['estimatedMinutes'] ?? 0),
                'dueDate' => $subtask['dueDate'] ?? null
            ];
        }

        return $normalized;
    }

    private function calculateNextRecurringDate(?string $currentDate, array $recurrence): string
    {
        $baseDate = $currentDate ?: date('Y-m-d');
        $date = DateTime::createFromFormat('Y-m-d', substr($baseDate, 0, 10));
        if (!$date) {
            $date = new DateTime();
        }

        $interval = max(1, (int)($recurrence['interval'] ?? 1));
        $frequency = $recurrence['frequency'] ?? 'weekly';
        if ($frequency === 'daily') {
            $date->modify("+{$interval} day");
        } elseif ($frequency === 'monthly') {
            $date->modify("+{$interval} month");
        } else {
            $date->modify("+{$interval} week");
        }

        return $date->format('Y-m-d');
    }

    private function createNextRecurringTask(array &$projects, int $projectIndex, int $taskIndex): ?array
    {
        $task = $projects[$projectIndex]['tasks'][$taskIndex];
        $recurrence = $this->normalizeRecurrence($task['recurrence'] ?? []);
        if (!($recurrence['enabled'] ?? false)) {
            return null;
        }

        $newTask = $task;
        $newTask['id'] = $this->db->generateId();
        $newTask['status'] = 'todo';
        $newTask['actualMinutes'] = 0;
        $newTask['timeEntries'] = [];
        $newTask['timerState'] = null;
        $newTask['completedAt'] = null;
        $newTask['recurrenceGeneratedAt'] = null;
        $newTask['parentTaskId'] = $task['parentTaskId'] ?? $task['id'];
        $newTask['createdAt'] = date('c');
        $newTask['updatedAt'] = date('c');
        $newTask['startDate'] = $this->calculateNextRecurringDate($task['startDate'] ?? null, $recurrence);
        $newTask['dueDate'] = $this->calculateNextRecurringDate($task['dueDate'] ?? null, $recurrence);

        foreach ($projects[$projectIndex]['tasks'] ?? [] as $existingTask) {
            $sameParent = ($existingTask['parentTaskId'] ?? null) === $newTask['parentTaskId'];
            $sameStart = ($existingTask['startDate'] ?? null) === $newTask['startDate'];
            $sameDue = ($existingTask['dueDate'] ?? null) === $newTask['dueDate'];
            if ($sameParent && $sameStart && $sameDue && !isTaskDone($existingTask['status'] ?? '')) {
                $projects[$projectIndex]['tasks'][$taskIndex]['recurrenceGeneratedAt'] = date('c');
                return $existingTask;
            }
        }

        $newTask['subtasks'] = array_map(function ($subtask) {
            $normalizedSubtask = $subtask;
            $normalizedSubtask['completed'] = false;
            $normalizedSubtask['completedAt'] = null;
            return $normalizedSubtask;
        }, $this->normalizeSubtasks($task['subtasks'] ?? []));

        $projects[$projectIndex]['tasks'][] = $newTask;
        $projects[$projectIndex]['tasks'][$taskIndex]['recurrenceGeneratedAt'] = date('c');
        return $newTask;
    }

    public function bulkUpdateStatus(array $taskIds, string $status): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $normalizedStatus = normalizeTaskStatus($status);
        $taskIdSet = array_fill_keys($taskIds, true);
        $projects = $this->db->load('projects');
        $updatedCount = 0;

        foreach ($projects as $pKey => $project) {
            foreach ($project['tasks'] ?? [] as $tKey => $task) {
                if (!isset($taskIdSet[$task['id'] ?? ''])) {
                    continue;
                }

                $projects[$pKey]['tasks'][$tKey]['status'] = $normalizedStatus;
                $projects[$pKey]['tasks'][$tKey]['updatedAt'] = date('c');

                if (isTaskDone($normalizedStatus)) {
                    if (empty($projects[$pKey]['tasks'][$tKey]['completedAt'])) {
                        $projects[$pKey]['tasks'][$tKey]['completedAt'] = date('c');
                    }
                } else {
                    unset($projects[$pKey]['tasks'][$tKey]['completedAt']);
                }

                $updatedCount++;
            }
        }

        if ($updatedCount > 0) {
            $this->db->save('projects', $projects);
        }

        return $updatedCount;
    }

    public function bulkDelete(array $taskIds): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $taskIdSet = array_fill_keys($taskIds, true);
        $projects = $this->db->load('projects');
        $deletedCount = 0;

        foreach ($projects as $pKey => $project) {
            $originalCount = count($project['tasks'] ?? []);
            if ($originalCount === 0) {
                continue;
            }

            $projects[$pKey]['tasks'] = array_values(array_filter(
                $project['tasks'],
                function ($task) use ($taskIdSet) {
                    return !isset($taskIdSet[$task['id'] ?? '']);
                }
            ));

            $deletedCount += $originalCount - count($projects[$pKey]['tasks']);
        }

        if ($deletedCount > 0) {
            $this->db->save('projects', $projects);
        }

        return $deletedCount;
    }
}
