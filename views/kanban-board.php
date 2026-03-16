<?php
$pageTitle = 'Kanban Board - ' . getSiteName();
$currentPage = 'kanban-board';

$db = new Database(getMasterPassword(), Auth::userId());
$projects = $db->load('projects') ?: [];
$currentProject = $projects[0] ?? null;
$tasks = $currentProject['tasks'] ?? [];

// Organize tasks by status
$columns = [
    'backlog' => [],
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];

foreach ($tasks as $task) {
    $status = normalizeTaskStatus($task['status'] ?? 'backlog', 'backlog');
    $task['status'] = $status;
    if (isset($columns[$status])) {
        $columns[$status][] = $task;
    }
}

// Get current user info
$user = Auth::user();
$userName = $user['name'] ?? 'Guest';
$userInitials = strtoupper(substr($userName, 0, 2));
?>

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside class="w-64 flex-shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col justify-between p-4">
        <div class="flex flex-col gap-6">
            <div class="flex items-center gap-3 px-2">
                <div class="bg-primary rounded-lg size-10 flex items-center justify-center text-white">
                    <span class="material-symbols-outlined">dashboard_customize</span>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-[#111318] dark:text-white text-base font-bold leading-tight"><?= e(getSiteName()) ?></h1>
                    <p class="text-[#616f89] text-xs font-normal">Task Management</p>
                </div>
            </div>
            <nav class="flex flex-col gap-1">
                <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2 text-[#616f89] hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">dashboard</span>
                    <p class="text-sm font-medium">Dashboard</p>
                </a>
                <a href="?page=projects" class="flex items-center gap-3 px-3 py-2 text-[#616f89] hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">folder</span>
                    <p class="text-sm font-medium">Projects</p>
                </a>
                <a href="?page=tasks" class="flex items-center gap-3 px-3 py-2 text-[#616f89] hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">check_circle</span>
                    <p class="text-sm font-medium">Tasks</p>
                </a>
                <a href="?page=kanban-board" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary/10 text-primary">
                    <span class="material-symbols-outlined">view_kanban</span>
                    <p class="text-sm font-semibold">Kanban Board</p>
                </a>
                <a href="?page=calendar" class="flex items-center gap-3 px-3 py-2 text-[#616f89] hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">calendar_month</span>
                    <p class="text-sm font-medium">Calendar</p>
                </a>
                <a href="?page=settings" class="flex items-center gap-3 px-3 py-2 text-[#616f89] hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">settings</span>
                    <p class="text-sm font-medium">Settings</p>
                </a>
            </nav>
        </div>
        <div class="flex flex-col gap-4">
            <button onclick="openNewTaskModal()" class="flex w-full cursor-pointer items-center justify-center rounded-lg h-10 px-4 bg-primary text-white text-sm font-bold tracking-tight">
                <span class="truncate">New Project</span>
            </button>
            <div class="flex flex-col gap-1">
                <a href="?page=knowledge-base" class="flex items-center gap-3 px-3 py-2 text-[#616f89] hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">help</span>
                    <p class="text-sm font-medium">Help Center</p>
                </a>
                <a href="<?php echo APP_URL; ?>/api/auth.php?action=logout" class="flex items-center gap-3 px-3 py-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/10 rounded-lg cursor-pointer">
                    <span class="material-symbols-outlined">logout</span>
                    <p class="text-sm font-medium">Logout</p>
                </a>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
        <!-- TopNavBar -->
        <header class="flex items-center justify-between bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-8 py-3">
            <div class="flex items-center gap-8 flex-1">
                <div class="flex items-center gap-3 text-primary">
                    <span class="material-symbols-outlined text-3xl">view_kanban</span>
                    <h2 class="text-[#111318] dark:text-white text-lg font-bold tracking-tight">Kanban Planner</h2>
                </div>
                <label class="flex flex-col min-w-[320px] max-w-md">
                    <div class="flex w-full items-stretch rounded-lg h-10 bg-gray-100 dark:bg-gray-800">
                        <div class="text-gray-500 flex items-center justify-center pl-4">
                            <span class="material-symbols-outlined text-xl">search</span>
                        </div>
                        <input id="kanbanSearch" class="w-full border-none bg-transparent focus:ring-0 text-sm placeholder:text-gray-500" placeholder="Search tasks, members, or tags..." type="text"/>
                    </div>
                </label>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex gap-2">
                    <button onclick="toggleKanbanFilter()" class="flex items-center justify-center rounded-lg size-10 bg-gray-100 dark:bg-gray-800 text-[#111318] dark:text-white hover:bg-gray-200">
                        <span class="material-symbols-outlined">filter_list</span>
                    </button>
                    <button class="flex items-center justify-center rounded-lg size-10 bg-gray-100 dark:bg-gray-800 text-[#111318] dark:text-white hover:bg-gray-200">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>
                </div>
                <div class="h-8 w-px bg-gray-200 dark:bg-gray-700 mx-2"></div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-bold text-[#111318] dark:text-white"><?= htmlspecialchars($userName) ?></p>
                        <p class="text-xs text-[#616f89]">User</p>
                    </div>
                    <div class="bg-primary/10 text-primary rounded-full size-10 flex items-center justify-center text-sm font-bold">
                        <?= $userInitials ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Board Area -->
        <div class="flex-1 overflow-y-auto px-8 py-6">
            <!-- Page Heading -->
            <div class="flex flex-wrap justify-between items-end gap-4 mb-6">
                <div class="flex flex-col gap-1">
                    <p class="text-[#111318] dark:text-white text-3xl font-black tracking-tight"><?= htmlspecialchars($currentProject['name'] ?? 'All Projects') ?></p>
                    <p class="text-[#616f89] text-base font-normal">Manage your tasks with Kanban</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- View Tabs -->
                    <div class="flex bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                        <button class="flex items-center gap-2 px-4 py-1.5 rounded-md text-gray-500 text-sm font-bold hover:text-gray-700">
                            <span class="material-symbols-outlined text-sm">format_list_bulleted</span> List
                        </button>
                        <button class="flex items-center gap-2 px-4 py-1.5 rounded-md bg-white dark:bg-gray-700 shadow-sm text-sm font-bold">
                            <span class="material-symbols-outlined text-sm">view_kanban</span> Board
                        </button>
                        <button class="flex items-center gap-2 px-4 py-1.5 rounded-md text-gray-500 text-sm font-bold hover:text-gray-700">
                            <span class="material-symbols-outlined text-sm">calendar_month</span> Calendar
                        </button>
                    </div>
                    <button onclick="openNewTaskModal()" class="flex items-center justify-center gap-2 rounded-lg h-10 px-6 bg-primary text-white text-sm font-bold hover:bg-blue-700">
                        <span class="material-symbols-outlined text-sm">add</span> New Task
                    </button>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="flex gap-6 overflow-x-auto pb-6 h-full items-start" id="kanbanBoard">
                <?php
                $columnConfigs = [
                    'backlog' => ['title' => 'Backlog', 'color' => 'text-gray-500', 'bg' => 'bg-gray-200'],
                    'todo' => ['title' => 'To Do', 'color' => 'text-blue-500', 'bg' => 'bg-blue-100'],
                    'in_progress' => ['title' => 'In Progress', 'color' => 'text-yellow-500', 'bg' => 'bg-yellow-100'],
                    'review' => ['title' => 'Review', 'color' => 'text-purple-500', 'bg' => 'bg-purple-100'],
                    'done' => ['title' => 'Done', 'color' => 'text-green-500', 'bg' => 'bg-green-100'],
                ];

                foreach ($columns as $status => $columnTasks): ?>
                <!-- Column: <?= $columnConfigs[$status]['title'] ?> -->
                <div class="kanban-column flex flex-col gap-4 bg-gray-100/50 dark:bg-gray-900/30 p-4 rounded-xl min-h-full w-80 flex-shrink-0" data-status="<?= $status ?>">
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-[#616f89]"><?= $columnConfigs[$status]['title'] ?></h3>
                            <span class="bg-gray-200 dark:bg-gray-800 text-xs font-bold px-2 py-0.5 rounded-full text-gray-600 dark:text-gray-400"><?= count($columnTasks) ?></span>
                        </div>
                        <button onclick="addTaskToColumn('<?= $status ?>')" class="text-gray-400 hover:text-primary">
                            <span class="material-symbols-outlined">add</span>
                        </button>
                    </div>

                    <!-- Task Cards -->
                    <div class="flex flex-col gap-3">
                        <?php foreach ($columnTasks as $task): ?>
                        <?php
                        $priority = $task['priority'] ?? 'medium';
                        $priorityConfig = [
                            'low' => ['class' => 'bg-green-100 text-green-600 dark:bg-green-900/30', 'label' => 'Low'],
                            'medium' => ['class' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30', 'label' => 'Medium'],
                            'high' => ['class' => 'bg-orange-100 text-orange-600 dark:bg-orange-900/30', 'label' => 'High'],
                            'urgent' => ['class' => 'bg-red-100 text-red-600 dark:bg-red-900/30', 'label' => 'Urgent'],
                        ];
                        $priorityClass = $priorityConfig[$priority]['class'] ?? $priorityConfig['medium']['class'];
                        $priorityLabel = $priorityConfig[$priority]['label'] ?? 'Medium';

                        $dueDate = $task['dueDate'] ?? '';
                        $dueDisplay = $dueDate ? date('M d', strtotime($dueDate)) : 'No date';
                        $isDone = $status === 'done';
                        ?>

                        <div class="task-card flex flex-col gap-3 p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl transition-all duration-200 cursor-grab hover:shadow-lg <?= $isDone ? 'grayscale-[0.5] opacity-75' : '' ?>"
                             draggable="true"
                             data-task-id="<?= $task['id'] ?>"
                             data-project-id="<?= $currentProject['id'] ?? '' ?>">
                            <div class="flex justify-between items-start">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $priorityClass ?>"><?= $priorityLabel ?></span>
                                <?php if ($isDone): ?>
                                    <span class="material-symbols-outlined text-green-500 text-sm">check_circle</span>
                                <?php elseif ($priority === 'urgent'): ?>
                                    <span class="material-symbols-outlined text-primary text-sm">bolt</span>
                                <?php else: ?>
                                    <button class="text-gray-400 hover:text-gray-600">
                                        <span class="material-symbols-outlined text-sm">more_horiz</span>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <h4 class="text-sm font-bold leading-tight <?= $isDone ? 'line-through text-gray-400' : 'text-[#111318] dark:text-white' ?>">
                                <?= htmlspecialchars($task['title'] ?? 'Untitled Task') ?>
                            </h4>

                            <?php if (!empty($task['description'])): ?>
                            <p class="text-xs text-[#616f89] line-clamp-2"><?= htmlspecialchars($task['description']) ?></p>
                            <?php endif; ?>

                            <?php if ($status === 'in_progress' && isset($task['progress'])): ?>
                            <div class="flex flex-col gap-2 mt-2">
                                <div class="flex justify-between items-center text-[10px] font-bold">
                                    <span>Progress</span>
                                    <span><?= $task['progress'] ?>%</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-primary h-full rounded-full" style="width: <?= $task['progress'] ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="flex justify-between items-center mt-2">
                                <div class="flex -space-x-2">
                                    <div class="size-6 rounded-full border-2 border-white dark:border-gray-800 bg-primary/20 text-primary flex items-center justify-center text-[10px] font-bold">
                                        <?= $userInitials ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 text-gray-400 text-[10px] font-medium">
                                    <span class="material-symbols-outlined text-xs">calendar_today</span>
                                    <?= $dueDisplay ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Add Task Placeholder -->
                        <button onclick="openNewTaskModal('<?= $status ?>')" class="flex items-center justify-center gap-2 p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-gray-400 hover:text-primary hover:border-primary transition-colors">
                            <span class="material-symbols-outlined">add</span>
                            <span class="text-sm font-medium">Add Task</span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- Add Column Button -->
                <div class="w-80 flex-shrink-0">
                    <button onclick="addNewColumn()" class="flex items-center justify-center gap-2 p-4 w-full border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-gray-400 hover:text-primary hover:border-primary transition-colors h-full min-h-[100px]">
                        <span class="material-symbols-outlined">add</span>
                        <span class="text-sm font-medium">Add Column</span>
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- New Task Modal -->
<div id="newTaskModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
    <div class="bg-white dark:bg-gray-800 rounded-xl w-full max-w-md p-6 shadow-2xl">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold">Create New Task</h3>
            <button onclick="closeNewTaskModal()" class="text-gray-400 hover:text-gray-600">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="newTaskForm" onsubmit="createNewTask(event)">
            <input type="hidden" name="projectId" value="<?= $currentProject['id'] ?? '' ?>">
            <input type="hidden" id="taskStatus" name="status" value="todo">

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Task Title</label>
                    <input type="text" id="taskTitle" name="title" required class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700" placeholder="Enter task title...">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                    <textarea id="taskDescription" name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700" placeholder="Enter description..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                        <select id="taskPriority" name="priority" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</label>
                        <input type="date" id="taskDueDate" name="dueDate" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeNewTaskModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-700 font-medium">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<script>
// Search functionality
document.getElementById('kanbanSearch').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    document.querySelectorAll('.task-card').forEach(card => {
        const title = card.querySelector('h4').textContent.toLowerCase();
        const description = card.querySelector('p')?.textContent.toLowerCase() || '';
        if (title.includes(query) || description.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
});

// Drag and drop functionality
let draggedCard = null;

document.querySelectorAll('.task-card').forEach(card => {
    card.addEventListener('dragstart', function(e) {
        draggedCard = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    card.addEventListener('dragend', function() {
        this.classList.remove('dragging');
        draggedCard = null;
    });
});

document.querySelectorAll('.kanban-column .flex.flex-col.gap-3').forEach(column => {
    column.addEventListener('dragover', function(e) {
        e.preventDefault();
        if (draggedCard) {
            const afterElement = getDragAfterElement(column, e.clientY);
            if (afterElement == null) {
                column.appendChild(draggedCard);
            } else {
                column.insertBefore(draggedCard, afterElement);
            }
        }
    });

    column.addEventListener('drop', function(e) {
        e.preventDefault();
        if (draggedCard) {
            const newStatus = this.closest('.kanban-column').dataset.status;
            const taskId = draggedCard.dataset.taskId;
            const projectId = draggedCard.dataset.projectId;

            // Update task status via API
            updateTaskStatus(projectId, taskId, newStatus);
        }
    });
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// Modal functions
function openNewTaskModal(status = 'todo') {
    document.getElementById('taskStatus').value = status;
    document.getElementById('newTaskModal').classList.remove('hidden');
    document.getElementById('taskTitle').focus();
}

function closeNewTaskModal() {
    document.getElementById('newTaskModal').classList.add('hidden');
    document.getElementById('newTaskForm').reset();
}

function addTaskToColumn(status) {
    openNewTaskModal(status);
}

function addNewColumn() {
    showToast('Column creation coming soon!', 'info');
}

// Create task
async function createNewTask(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const taskData = Object.fromEntries(formData);

    try {
        const response = await api.post('api/tasks.php?projectId=' + taskData.projectId, {
            ...taskData,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Task created successfully!', 'success');
            closeNewTaskModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(response.message || 'Failed to create task', 'error');
        }
    } catch (error) {
        showToast('Error creating task', 'error');
    }
}

// Update task status
async function updateTaskStatus(projectId, taskId, newStatus) {
    try {
        const response = await api.put('api/tasks.php?projectId=' + projectId + '&id=' + taskId, {
            status: newStatus,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Task moved to ' + newStatus.replace('_', ' '), 'success');
        } else {
            showToast('Failed to update task', 'error');
        }
    } catch (error) {
        console.error('Error updating task:', error);
    }
}

// Close modal on outside click
document.getElementById('newTaskModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeNewTaskModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeNewTaskModal();
    }
});
</script>

<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
.kanban-column {
    min-width: 300px;
}
.task-card.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}
.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>

