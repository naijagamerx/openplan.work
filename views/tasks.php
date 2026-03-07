<?php
// Tasks View
$db = new Database(getMasterPassword());
$projects = $db->load('projects');
$action = $_GET['action'] ?? null;

// Get all tasks
$allTasks = [];
foreach ($projects as $project) {
    foreach ($project['tasks'] ?? [] as $task) {
        $task['projectName'] = $project['name'];
        $task['projectId'] = $project['id'];
        $allTasks[] = $task;
    }
}

// Group by status for Kanban
$tasksByStatus = [
    'backlog' => [],
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];

foreach ($allTasks as $task) {
    $status = $task['status'] ?? 'todo';
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

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4 bg-white p-1 rounded-xl border border-gray-200 shadow-sm">
            <button onclick="setView('kanban')" id="view-kanban" class="px-6 py-2 rounded-lg font-bold text-sm transition-all bg-black text-white">Kanban</button>
            <button onclick="setView('list')" id="view-list" class="px-6 py-2 rounded-lg font-bold text-sm transition-all text-gray-500 hover:bg-gray-50">List</button>
        </div>
        <a href="?page=task-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Task
        </a>
    </div>
    
    <!-- Kanban Board -->
    <div id="kanban-view" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
        <?php foreach ($tasksByStatus as $status => $tasks): ?>
            <div class="flex flex-col h-full bg-gray-50/50 rounded-2xl p-4 border border-gray-100">
                <div class="flex items-center justify-between mb-6 px-1">
                    <h3 class="font-black text-xs uppercase tracking-widest text-gray-400"><?php echo $statusLabels[$status]; ?></h3>
                    <span class="w-6 h-6 flex items-center justify-center bg-gray-200 text-gray-600 rounded-full text-[10px] font-bold"><?php echo count($tasks); ?></span>
                </div>
                <div id="kanban-<?php echo $status; ?>" class="kanban-column space-y-4 flex-1 min-h-[500px]" data-status="<?php echo $status; ?>">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card bg-white rounded-xl p-5 shadow-sm border border-gray-200 cursor-grab hover:shadow-md transition-all active:scale-95 group" 
                             data-id="<?php echo e($task['id']); ?>"
                             data-project="<?php echo e($task['projectId']); ?>">
                            <div class="flex items-start justify-between mb-3">
                                <span class="px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter rounded shadow-sm <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                                    <?php echo $task['priority'] ?? 'medium'; ?>
                                </span>
                                <a href="?page=task-form&id=<?php echo e($task['id']); ?>" class="p-1 text-gray-300 hover:text-black opacity-0 group-hover:opacity-100 transition-opacity">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </a>
                            </div>
                            <h4 class="font-bold text-gray-900 text-sm leading-tight mb-2"><?php echo e($task['title']); ?></h4>
                            <?php if (!empty($task['description'])): ?>
                                <p class="text-[11px] text-gray-500 line-clamp-2 leading-relaxed mb-4"><?php echo e($task['description']); ?></p>
                            <?php endif; ?>
                            <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                                <span class="truncate pr-2"><?php echo e($task['projectName']); ?></span>
                                <?php if (!empty($task['dueDate'])): ?>
                                    <span class="whitespace-nowrap flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <?php echo formatDate($task['dueDate'], 'M j'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- List View -->
    <div id="list-view" class="hidden bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="p-5 bg-gray-50/50 border-b border-gray-200">
            <div class="flex flex-wrap items-center gap-4">
                <select id="filter-project" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-bold uppercase tracking-widest outline-none focus:border-black transition-colors">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo e($project['id']); ?>"><?php echo e($project['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter-status" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-bold uppercase tracking-widest outline-none focus:border-black transition-colors">
                    <option value="">All Status</option>
                    <option value="backlog">Backlog</option>
                    <option value="todo">To Do</option>
                    <option value="in_progress">In Progress</option>
                    <option value="review">Review</option>
                    <option value="done">Done</option>
                </select>
                <select id="filter-priority" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-bold uppercase tracking-widest outline-none focus:border-black transition-colors">
                    <option value="">All Priority</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (empty($allTasks)): ?>
                <div class="p-12 text-center">
                    <p class="text-gray-400 font-medium">No tasks found. Time to create some!</p>
                </div>
            <?php else: ?>
                <?php foreach ($allTasks as $task): ?>
                    <div class="task-row flex items-center gap-6 p-5 hover:bg-gray-50 transition-colors group"
                         data-project="<?php echo e($task['projectId']); ?>"
                         data-status="<?php echo e($task['status'] ?? 'todo'); ?>"
                         data-priority="<?php echo e($task['priority'] ?? 'medium'); ?>">
                        <div class="flex-shrink-0">
                            <input type="checkbox" 
                                   class="w-6 h-6 rounded-lg border-2 border-gray-200 text-black focus:ring-black cursor-pointer transition-all"
                                   <?php echo ($task['status'] ?? '') === 'done' ? 'checked' : ''; ?>
                                   onchange="toggleTask('<?php echo e($task['id']); ?>', this.checked)">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-900 truncate <?php echo ($task['status'] ?? '') === 'done' ? 'line-through text-gray-300' : ''; ?>">
                                <?php echo e($task['title']); ?>
                            </p>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400"><?php echo e($task['projectName']); ?></p>
                        </div>
                        <div class="hidden md:flex items-center gap-3">
                            <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                                <?php echo $task['priority'] ?? 'medium'; ?>
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo statusClass($task['status'] ?? 'todo'); ?>">
                                <?php echo str_replace('_', ' ', $task['status'] ?? 'todo'); ?>
                            </span>
                        </div>
                        <?php if (!empty($task['dueDate'])): ?>
                            <div class="hidden sm:block text-xs font-bold text-gray-400 tabular-nums">
                                <?php echo formatDate($task['dueDate']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="?page=task-form&id=<?php echo e($task['id']); ?>" class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-black hover:border-black transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                            <button onclick="deleteTask('<?php echo e($task['id']); ?>')" class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-100 transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// View toggle
function setView(view) {
    document.getElementById('kanban-view').classList.toggle('hidden', view !== 'kanban');
    document.getElementById('list-view').classList.toggle('hidden', view !== 'list');
    document.getElementById('view-kanban').classList.toggle('bg-black', view === 'kanban');
    document.getElementById('view-kanban').classList.toggle('text-white', view === 'kanban');
    document.getElementById('view-list').classList.toggle('bg-black', view === 'list');
    document.getElementById('view-list').classList.toggle('text-white', view === 'list');
    
    // Style adjustments
    const kBtn = document.getElementById('view-kanban');
    const lBtn = document.getElementById('view-list');
    if (view === 'kanban') {
        kBtn.className = 'px-6 py-2 rounded-lg font-bold text-sm transition-all bg-black text-white';
        lBtn.className = 'px-6 py-2 rounded-lg font-bold text-sm transition-all text-gray-500 hover:bg-gray-50';
    } else {
        lBtn.className = 'px-6 py-2 rounded-lg font-bold text-sm transition-all bg-black text-white';
        kBtn.className = 'px-6 py-2 rounded-lg font-bold text-sm transition-all text-gray-500 hover:bg-gray-50';
    }
}

// Toggle task complete
async function toggleTask(taskId, completed) {
    const response = await api.put(`api/tasks.php?id=${taskId}`, {
        status: completed ? 'done' : 'todo',
        csrf_token: CSRF_TOKEN
    });
    
    if (response.success) {
        showToast(completed ? 'Task completed!' : 'Task reopened', 'success');
    }
}

// Initialize Kanban drag and drop
document.addEventListener('DOMContentLoaded', () => {
    const columns = document.querySelectorAll('.kanban-column');
    columns.forEach(column => {
        new Sortable(column, {
            group: 'tasks',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: async function(evt) {
                const taskId = evt.item.dataset.id;
                const newStatus = evt.to.dataset.status;
                
                const response = await api.put(`api/tasks.php?id=${taskId}`, {
                    status: newStatus,
                    csrf_token: CSRF_TOKEN
                });
                
                if (response.success) {
                    showToast('Task moved', 'success');
                }
            }
        });
    });
});

// Filter list view
['filter-project', 'filter-status', 'filter-priority'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', filterTasks);
});

function filterTasks() {
    const project = document.getElementById('filter-project').value;
    const status = document.getElementById('filter-status').value;
    const priority = document.getElementById('filter-priority').value;
    
    document.querySelectorAll('.task-row').forEach(row => {
        const matchProject = !project || row.dataset.project === project;
        const matchStatus = !status || row.dataset.status === status;
        const matchPriority = !priority || row.dataset.priority === priority;
        
        row.style.display = matchProject && matchStatus && matchPriority ? '' : 'none';
    });
}
</script>
