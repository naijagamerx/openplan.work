<?php
// View Project Page
$db = new Database(getMasterPassword(), Auth::userId());

// Get project ID from URL
$projectId = $_GET['id'] ?? null;

if (!$projectId) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Project not found</div></div>';
    return;
}

// Find the project
$projects = $db->load('projects');
$project = null;

foreach ($projects as $p) {
    if ($p['id'] === $projectId) {
        $project = $p;
        break;
    }
}

if (!$project) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Project not found</div></div>';
    return;
}

// Get project tasks
$tasks = $project['tasks'] ?? [];
foreach ($tasks as $key => $task) {
    $tasks[$key]['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
}

// Calculate progress
$totalTasks = count($tasks);
$completedTasks = count(array_filter($tasks, fn($t) => isTaskDone($t['status'] ?? '')));
$progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

// Group tasks by status
$tasksByStatus = [
    'backlog' => [],
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];

foreach ($tasks as $task) {
    $status = normalizeTaskStatus($task['status'] ?? 'todo');
    if (isset($tasksByStatus[$status])) {
        $tasksByStatus[$status][] = $task;
    }
}

$statusLabels = [
    'backlog' => 'Backlog',
    'todo' => 'To Do',
    'in_progress' => 'In Progress',
    'review' => 'Review',
    'done' => 'Done'
];
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            <a href="?page=projects" class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-xl flex items-center justify-center text-white text-2xl font-black shadow-lg"
                     style="background-color: <?php echo e($project['color'] ?? '#000'); ?>">
                    <?php echo strtoupper(substr($project['name'], 0, 1)); ?>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo e($project['name']); ?></h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo statusClass($project['status'] ?? 'active'); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $project['status'] ?? 'active')); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=project-form&id=<?php echo e($projectId); ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl font-bold text-sm hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                </svg>
                Edit Project
            </a>
            <button onclick="confirmAction('Delete this project and all its tasks?', () => deleteProject('<?php echo e($projectId); ?>'))" class="flex items-center gap-2 px-4 py-2.5 bg-red-50 border border-red-200 text-red-600 rounded-xl font-bold text-sm hover:bg-red-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete
            </button>
        </div>
    </div>

    <!-- Description -->
    <?php if (!empty($project['description'])): ?>
        <div class="mb-8">
            <p class="text-gray-600"><?php echo e($project['description']); ?></p>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalTasks; ?></p>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Total Tasks</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $completedTasks; ?></p>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Completed</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $totalTasks - $completedTasks; ?></p>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Pending</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-black rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?php echo $progress; ?>%</p>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Progress</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-8">
        <div class="flex items-center justify-between mb-2">
            <span class="font-bold text-gray-900">Overall Progress</span>
            <span class="font-bold text-gray-700"><?php echo $progress; ?>%</span>
        </div>
        <div class="h-4 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full bg-black rounded-full transition-all duration-1000" style="width: <?php echo $progress; ?>%"></div>
        </div>
    </div>

    <!-- Add Task Button -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900">Tasks</h2>
        <a href="?page=task-form&projectId=<?php echo e($projectId); ?>" class="flex items-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-bold text-sm hover:bg-gray-800 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Task
        </a>
    </div>

    <!-- Tasks Kanban Board -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <?php foreach ($tasksByStatus as $status => $statusTasks): ?>
            <div class="kanban-column flex flex-col bg-gray-50/50 rounded-xl p-3 border border-gray-100" data-status="<?php echo $status; ?>">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-black text-[10px] uppercase tracking-widest text-gray-400"><?php echo $statusLabels[$status]; ?></h3>
                    <span class="w-5 h-5 flex items-center justify-center bg-gray-200 text-gray-600 rounded-full text-[9px] font-bold"><?php echo count($statusTasks); ?></span>
                </div>
                <div class="space-y-2 flex-1 min-h-[200px]">
                    <?php foreach ($statusTasks as $task): ?>
                        <div class="task-card bg-white rounded-lg p-3 shadow-sm border border-gray-200 cursor-grab hover:shadow-md transition-all active:scale-95 group"
                             data-id="<?php echo e($task['id']); ?>">
                            <div class="flex items-start justify-between mb-2">
                                <span class="px-1.5 py-0.5 text-[8px] font-black uppercase tracking-tighter rounded <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                                    <?php echo $task['priority'] ?? 'medium'; ?>
                                </span>
                                <div class="flex items-center gap-1">
                                    <a href="?page=view-task&id=<?php echo e($task['id']); ?>&projectId=<?php echo e($projectId); ?>" class="p-0.5 text-gray-300 hover:text-blue-600 transition-colors" title="View">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <h4 class="font-bold text-gray-900 text-xs leading-tight mb-1"><?php echo e($task['title']); ?></h4>
                            <?php if (!empty($task['description'])): ?>
                                <p class="text-[10px] text-gray-500 line-clamp-2"><?php echo e($task['description']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($task['dueDate'])): ?>
                                <div class="flex items-center gap-1 mt-2 text-[10px] text-gray-400">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <?php echo formatDate($task['dueDate'], 'M j'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($statusTasks)): ?>
                        <div class="text-center py-6 text-gray-400 text-[10px]">
                            Drop tasks here
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Initialize Kanban drag and drop
document.addEventListener('DOMContentLoaded', () => {
    const columns = document.querySelectorAll('.kanban-column');
    columns.forEach(column => {
        new Sortable(column.querySelector('.space-y-2'), {
            group: 'tasks',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: async function(evt) {
                const taskId = evt.item.dataset.id;
                const newStatus = evt.to.closest('.kanban-column').dataset.status;

                const response = await api.put(`api/tasks.php?id=${taskId}`, {
                    status: newStatus,
                    csrf_token: CSRF_TOKEN
                });

                if (response.success) {
                    showToast('Task moved to ' + newStatus.replace('_', ' '), 'success');
                }
            }
        });
    });
});

async function deleteProject(id) {
    try {
        const response = await api.delete(`api/projects.php?id=${id}`);
        if (response.success) {
            showToast('Project deleted', 'success');
            setTimeout(() => {
                window.location.href = '?page=projects';
            }, 500);
        } else {
            showToast(response.error || 'Failed to delete project', 'error');
        }
    } catch (error) {
        showToast('Failed to delete project', 'error');
    }
}
</script>

