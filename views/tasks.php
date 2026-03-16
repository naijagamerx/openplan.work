<?php
// Tasks View
$db = new Database(getMasterPassword(), Auth::userId());
$projects = $db->load('projects');
$action = $_GET['action'] ?? null;

// Pagination settings
$itemsPerPage = 20;
$currentPage = isset($_GET['page_num']) ? max(1, intval($_GET['page_num'])) : 1;

// Get all tasks
$allTasks = [];
foreach ($projects as $project) {
    foreach ($project['tasks'] ?? [] as $task) {
        $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
        $task['projectName'] = $project['name'];
        $task['projectId'] = $project['id'];
        $allTasks[] = $task;
    }
}

// Group by status for Kanban (before pagination - shows all tasks)
$tasksByStatus = [
    'backlog' => [],
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];

foreach ($allTasks as $task) {
    $status = normalizeTaskStatus($task['status'] ?? 'todo');
    if (isset($tasksByStatus[$status])) {
        $tasksByStatus[$status][] = $task;
    }
}

$todayStart = strtotime(date('Y-m-d'));
usort($allTasks, function($a, $b) use ($todayStart) {
    $doneA = isTaskDone($a['status'] ?? '');
    $doneB = isTaskDone($b['status'] ?? '');
    if ($doneA !== $doneB) {
        return $doneA ? 1 : -1;
    }
    $dueA = $a['dueDate'] ?? '9999-12-31';
    $dueB = $b['dueDate'] ?? '9999-12-31';
    $cmp = strcmp($dueA, $dueB);
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcmp($a['title'] ?? '', $b['title'] ?? '');
});

// Add sequential numbering to all tasks
foreach ($allTasks as $index => $task) {
    $allTasks[$index]['taskNumber'] = $index + 1;
}

// Calculate pagination
$totalTasks = count($allTasks);
$totalPages = ceil($totalTasks / $itemsPerPage);
$currentPage = min($currentPage, max(1, $totalPages));
$offset = ($currentPage - 1) * $itemsPerPage;

// Get paginated tasks for list view
$paginatedTasks = array_slice($allTasks, $offset, $itemsPerPage);

// Build pagination URLs
$baseUrl = '?page=tasks';
$queryParams = $_GET;
unset($queryParams['page'], $queryParams['page_num']);

$statusLabels = [
    'backlog' => 'Backlog',
    'todo' => 'To Do',
    'in_progress' => 'In Progress',
    'review' => 'Review',
    'done' => 'Done'
];

$completedTaskCount = count(array_filter($allTasks, fn($task) => isTaskDone($task['status'] ?? '')));
$overdueTaskCount = count(array_filter($allTasks, function($task) use ($todayStart) {
    if (isTaskDone($task['status'] ?? '')) {
        return false;
    }
    if (empty($task['dueDate'])) {
        return false;
    }
    $dueTs = strtotime($task['dueDate']);
    return $dueTs !== false && $dueTs < $todayStart;
}));
$completionRate = $totalTasks > 0 ? round(($completedTaskCount / $totalTasks) * 100) : 0;
$totalEstimatedMinutes = array_sum(array_map(fn($task) => (int)($task['estimatedMinutes'] ?? 0), $allTasks));
$totalActualMinutes = array_sum(array_map(fn($task) => (int)($task['actualMinutes'] ?? 0), $allTasks));
$timeAccuracy = $totalEstimatedMinutes > 0 ? round(($totalActualMinutes / $totalEstimatedMinutes) * 100) : 0;

$weekStartTs = strtotime('monday this week', $todayStart);
$weekEndTs = strtotime('+7 days', $weekStartTs);
$weeklyLoggedMinutes = 0;
$projectMinutes = [];
$taskMinutes = [];
foreach ($allTasks as $task) {
    $taskTotalForWeek = 0;
    foreach (($task['timeEntries'] ?? []) as $entry) {
        $entryTs = strtotime($entry['date'] ?? '');
        if ($entryTs === false) {
            continue;
        }
        if ($entryTs >= $weekStartTs && $entryTs < $weekEndTs) {
            $minutes = (int)($entry['minutes'] ?? 0);
            $taskTotalForWeek += $minutes;
            $weeklyLoggedMinutes += $minutes;
        }
    }

    if ($taskTotalForWeek > 0) {
        $projectName = $task['projectName'] ?? 'Unknown Project';
        $projectMinutes[$projectName] = ($projectMinutes[$projectName] ?? 0) + $taskTotalForWeek;
        $taskTitle = $task['title'] ?? 'Untitled Task';
        $taskMinutes[$taskTitle] = ($taskMinutes[$taskTitle] ?? 0) + $taskTotalForWeek;
    }
}
arsort($projectMinutes);
arsort($taskMinutes);
$topProjects = array_slice($projectMinutes, 0, 3, true);
$topTasks = array_slice($taskMinutes, 0, 5, true);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4 bg-white p-1 rounded-xl border border-gray-200 shadow-sm">
            <button onclick="setView('kanban')" id="view-kanban" class="px-6 py-2 rounded-lg font-bold text-sm transition-all text-gray-500 hover:bg-gray-50">Kanban</button>
            <button onclick="setView('list')" id="view-list" class="px-6 py-2 rounded-lg font-bold text-sm transition-all bg-black text-white">List</button>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openAIGenerateKanban()" class="flex items-center gap-2 px-4 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                AI Generate Kanban
            </button>
            <a href="?page=task-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Task
            </a>
        </div>
    </div>

    <!-- AI Generate Kanban Modal -->
    <div id="ai-kanban-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">AI Generate Kanban</h3>
                        <p class="text-sm text-gray-500">Describe your project to generate a Kanban board</p>
                    </div>
                </div>
                <button onclick="closeAIGenerateKanban()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Project Description</label>
                    <textarea id="ai-kanban-description" rows="4" placeholder="e.g., Build a customer relationship management (CRM) system with contact management, deal tracking, and email integration..." class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Select Project</label>
                    <select id="ai-kanban-project" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors">
                        <option value="">Create new project</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo e($project['id']); ?>"><?php echo e($project['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button onclick="generateKanban()" id="ai-kanban-generate-btn" class="w-full py-4 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Generate Kanban Board
                </button>
            </div>

            <!-- Generated Kanban Preview -->
            <div id="ai-kanban-preview" class="mt-6 hidden">
                <h4 class="font-bold text-gray-900 mb-4">Preview</h4>
                <div id="ai-kanban-columns" class="grid grid-cols-3 gap-3 max-h-80 overflow-y-auto"></div>
                <div class="flex gap-3 mt-4">
                    <button onclick="importKanbanToProject()" class="flex-1 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Import to Project
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kanban Board -->
    <div id="kanban-view" class="hidden grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-6">
        <?php foreach ($tasksByStatus as $status => $tasks): ?>
            <div class="flex flex-col h-full bg-gray-50/50 rounded-2xl p-4 border border-gray-100">
                <div class="flex items-center justify-between mb-6 px-1">
                    <h3 class="font-black text-xs uppercase tracking-widest text-gray-400"><?php echo $statusLabels[$status]; ?></h3>
                    <span class="w-6 h-6 flex items-center justify-center bg-gray-200 text-gray-600 rounded-full text-[10px] font-bold"><?php echo count($tasks); ?></span>
                </div>
                <div id="kanban-<?php echo $status; ?>" class="kanban-column space-y-4 flex-1 min-h-[500px]" data-status="<?php echo $status; ?>">
                <?php foreach ($tasks as $index => $task): ?>
                        <div class="task-card bg-white rounded-xl p-5 shadow-sm border border-gray-200 cursor-grab hover:shadow-md transition-all active:scale-95 group"
                             data-id="<?php echo e($task['id']); ?>"
                             data-project="<?php echo e($task['projectId']); ?>"
                             data-status="<?php echo e($task['status'] ?? 'todo'); ?>"
                             data-estimated-minutes="<?php echo (int)($task['estimatedMinutes'] ?? 0); ?>"
                             data-task-number="<?php echo e($task['taskNumber'] ?? ($index + 1)); ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 text-[9px] font-black uppercase tracking-tighter rounded bg-gray-100 text-gray-600">
                                        #<?php echo e($task['taskNumber'] ?? ($index + 1)); ?>
                                    </span>
                                    <span class="px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter rounded shadow-sm <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                                        <?php echo $task['priority'] ?? 'medium'; ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button onclick="toggleTaskTimer('<?php echo e($task['id']); ?>')" class="task-timer-btn p-1 text-gray-400 hover:text-blue-600 transition-colors" title="Start timer" data-task-timer-btn="<?php echo e($task['id']); ?>">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.586-2.293A1 1 0 009 9.764v4.472a1 1 0 001.166.986l4.586-2.293a1 1 0 000-1.758z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                    <a href="?page=view-task&id=<?php echo e($task['id']); ?>&projectId=<?php echo e($task['projectId']); ?>" class="p-1 text-gray-400 hover:text-blue-600 transition-colors" title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="?page=task-form&id=<?php echo e($task['id']); ?>" class="p-1 text-gray-400 hover:text-black transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                </div>
                            </div>
                            <h4 class="font-bold text-gray-900 text-sm leading-tight mb-2"><?php echo e($task['title']); ?></h4>
                            <?php if (!empty($task['description'])): ?>
                                <p class="text-[11px] text-gray-500 line-clamp-2 leading-relaxed mb-3"><?php echo e($task['description']); ?></p>
                            <?php endif; ?>
                            <?php $subtasks = $task['subtasks'] ?? []; ?>
                            <?php if (!empty($subtasks)): ?>
                                <?php $completedSubtasks = count(array_filter($subtasks, fn($s) => $s['completed'] ?? false)); ?>
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-bold">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                        <?php echo $completedSubtasks; ?>/<?php echo count($subtasks); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center justify-between mt-auto pt-4 border-t border-gray-50 text-[10px] font-bold text-gray-400 uppercase tracking-tighter">
                                <span class="truncate pr-2"><?php echo e($task['projectName']); ?></span>
                                <span class="hidden font-mono text-blue-600" data-task-timer-display="<?php echo e($task['id']); ?>">
                                    <span data-task-timer-remaining="<?php echo e($task['id']); ?>">00:00</span>
                                </span>
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
    <div id="list-view" class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="p-5 bg-gray-50/50 border-b border-gray-200">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="relative">
                        <input id="filter-search"
                               type="text"
                               placeholder="Search task, project, subtask..."
                               class="w-64 pl-10 pr-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-semibold tracking-wide outline-none focus:border-black transition-colors">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"></path>
                        </svg>
                    </div>
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
                    <select id="filter-due" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-bold uppercase tracking-widest outline-none focus:border-black transition-colors">
                        <option value="">All Due Dates</option>
                        <option value="overdue">Overdue</option>
                        <option value="today">Due Today</option>
                        <option value="week">Due This Week</option>
                        <option value="none">No Due Date</option>
                    </select>
                    <select id="sort-by" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-bold uppercase tracking-widest outline-none focus:border-black transition-colors">
                        <option value="default">Sort: Default</option>
                        <option value="title">Sort: Title</option>
                        <option value="dueDate">Sort: Due Date</option>
                        <option value="priority">Sort: Priority</option>
                        <option value="estimated">Sort: Estimate</option>
                        <option value="actual">Sort: Actual</option>
                    </select>
                    <button id="sort-direction"
                            type="button"
                            data-direction="asc"
                            class="px-3 py-2 border-2 border-gray-100 rounded-xl text-xs font-black uppercase tracking-widest text-gray-600 hover:border-black hover:text-black transition-colors">
                        Asc
                    </button>
                </div>
                <div class="flex items-center gap-3 text-[11px] font-black uppercase tracking-widest text-gray-400">
                    <span class="px-3 py-1 rounded-full border border-gray-200 bg-white">Total: <span id="task-count-total"><?php echo count($allTasks); ?></span></span>
                    <span class="px-3 py-1 rounded-full border border-gray-200 bg-white">Showing: <span id="task-count-visible"><?php echo count($allTasks); ?></span></span>
                    <a href="api/export.php?action=export_csv&collection=tasks" class="px-3 py-1 rounded-full border border-gray-200 bg-white text-gray-600 hover:text-black hover:border-black transition">Export CSV</a>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" class="today-focus-chip px-3 py-1.5 rounded-lg text-[11px] font-black uppercase tracking-widest border border-gray-200 bg-white text-gray-700 hover:border-black transition" data-focus="all">All</button>
                <button type="button" class="today-focus-chip px-3 py-1.5 rounded-lg text-[11px] font-black uppercase tracking-widest border border-red-200 bg-red-50 text-red-700 hover:border-red-400 transition" data-focus="overdue">Overdue</button>
                <button type="button" class="today-focus-chip px-3 py-1.5 rounded-lg text-[11px] font-black uppercase tracking-widest border border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-400 transition" data-focus="today">Due Today</button>
                <button type="button" class="today-focus-chip px-3 py-1.5 rounded-lg text-[11px] font-black uppercase tracking-widest border border-blue-200 bg-blue-50 text-blue-700 hover:border-blue-400 transition" data-focus="in_progress">In Progress</button>
            </div>
        </div>
        <div id="bulk-action-bar" class="hidden px-5 py-3 border-b border-gray-200 bg-blue-50/60">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs font-black uppercase tracking-widest text-blue-700">
                    <span id="bulk-selected-count">0</span> selected
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="bulkUpdateStatus('in_progress')" class="px-3 py-1.5 rounded-lg bg-white border border-blue-200 text-[11px] font-bold uppercase tracking-widest text-blue-700 hover:bg-blue-100 transition">Move: In Progress</button>
                    <button type="button" onclick="bulkUpdateStatus('done')" class="px-3 py-1.5 rounded-lg bg-white border border-green-200 text-[11px] font-bold uppercase tracking-widest text-green-700 hover:bg-green-100 transition">Mark Done</button>
                    <button type="button" onclick="bulkDeleteTasks()" class="px-3 py-1.5 rounded-lg bg-white border border-red-200 text-[11px] font-bold uppercase tracking-widest text-red-700 hover:bg-red-100 transition">Delete Selected</button>
                    <button type="button" onclick="clearBulkSelection()" class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-[11px] font-bold uppercase tracking-widest text-gray-600 hover:bg-gray-100 transition">Clear</button>
                </div>
            </div>
        </div>
        <div class="hidden md:grid grid-cols-12 gap-4 px-5 py-3 bg-white text-[11px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100">
            <div class="col-span-1">
                <input id="select-all-visible" type="checkbox" class="w-4 h-4 rounded border-2 border-gray-300 text-black focus:ring-black cursor-pointer" title="Select all visible tasks">
            </div>
            <div class="col-span-3">Task Name</div>
            <div class="col-span-2">Project</div>
            <div class="col-span-1">Status</div>
            <div class="col-span-1">Priority</div>
            <div class="col-span-3">Time Tracking</div>
            <div class="col-span-1 text-right">Actions</div>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (empty($paginatedTasks)): ?>
                <div class="p-12 text-center">
                    <p class="text-gray-400 font-medium">No tasks found. Time to create some!</p>
                </div>
            <?php else: ?>
                <?php foreach ($paginatedTasks as $task): ?>
                    <div class="task-row grid grid-cols-1 md:grid-cols-12 gap-4 p-5 hover:bg-gray-50 transition-colors group items-center"
                         data-project="<?php echo e($task['projectId']); ?>"
                         data-status="<?php echo e($task['status'] ?? 'todo'); ?>"
                         data-priority="<?php echo e($task['priority'] ?? 'medium'); ?>"
                         data-task-id="<?php echo e($task['id']); ?>"
                         data-estimated-minutes="<?php echo (int)($task['estimatedMinutes'] ?? 0); ?>"
                         data-actual-minutes="<?php echo (int)($task['actualMinutes'] ?? 0); ?>"
                         data-due-date="<?php echo e($task['dueDate'] ?? ''); ?>"
                         data-title="<?php echo e($task['title'] ?? ''); ?>"
                         data-description="<?php echo e($task['description'] ?? ''); ?>"
                         data-project-name="<?php echo e($task['projectName'] ?? ''); ?>"
                         data-subtasks="<?php echo e(implode(' ', array_map(fn($subtask) => $subtask['title'] ?? '', $task['subtasks'] ?? []))); ?>"
                         data-timer-state="<?php echo e(json_encode($task['timerState'] ?? null)); ?>"
                         data-task-number="<?php echo e($task['taskNumber'] ?? ''); ?>">
                        <div class="md:col-span-1 hidden md:flex items-start justify-center">
                            <input type="checkbox"
                                   class="bulk-select-checkbox mt-1 w-4 h-4 rounded border-2 border-gray-300 text-black focus:ring-black cursor-pointer"
                                   value="<?php echo e($task['id']); ?>"
                                   onchange="toggleBulkSelection('<?php echo e($task['id']); ?>', this.checked)">
                        </div>
                        <div class="md:col-span-3 flex items-start gap-3 min-w-0">
                            <input type="checkbox"
                                   class="mt-1 w-6 h-6 rounded-lg border-2 border-gray-200 text-black focus:ring-black cursor-pointer transition-all"
                                   <?php echo isTaskDone($task['status'] ?? '') ? 'checked' : ''; ?>
                                   onchange="toggleTask('<?php echo e($task['id']); ?>', this.checked)">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="px-1.5 py-0.5 text-[10px] font-black uppercase tracking-tighter rounded bg-gray-100 text-gray-500 flex-shrink-0">
                                        #<?php echo e($task['taskNumber'] ?? ''); ?>
                                    </span>
                                    <p class="font-bold text-gray-900 truncate <?php echo isTaskDone($task['status'] ?? '') ? 'line-through text-red-500 decoration-red-500' : ''; ?>">
                                        <?php echo e($task['title']); ?>
                                    </p>
                                </div>
                                <?php $listSubtasks = $task['subtasks'] ?? []; ?>
                                <?php if (!empty($listSubtasks)): ?>
                                    <div class="mt-2 space-y-1">
                                        <?php foreach ($listSubtasks as $subIndex => $subtask): ?>
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox"
                                                       class="w-4 h-4 rounded border-2 border-gray-300 text-black focus:ring-black cursor-pointer"
                                                       <?php echo ($subtask['completed'] ?? false) ? 'checked' : ''; ?>
                                                       onchange="toggleSubtaskQuick('<?php echo e($task['id']); ?>', '<?php echo e($task['projectId']); ?>', '<?php echo e($subtask['id'] ?? $subIndex); ?>', this.checked, this)">
                                                <span class="text-xs text-gray-600 <?php echo ($subtask['completed'] ?? false) ? 'line-through text-gray-400' : ''; ?>">
                                                    <?php echo e($subtask['title'] ?? 'Untitled'); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <p class="text-[11px] font-black uppercase tracking-widest text-gray-500 truncate"><?php echo e($task['projectName']); ?></p>
                        </div>
                        <div class="md:col-span-1">
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo statusClass($task['status'] ?? 'todo'); ?>">
                                <?php echo ucwords(str_replace('_', ' ', $task['status'] ?? 'todo')); ?>
                            </span>
                        </div>
                        <div class="md:col-span-1">
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                                <?php echo $task['priority'] ?? 'medium'; ?>
                            </span>
                        </div>
                        <?php
                            $createdAt = $task['createdAt'] ?? null;
                            $dueDate = $task['dueDate'] ?? null;
                            $dueStateLabel = 'No Due';
                            $dueStateClass = 'bg-gray-100 text-gray-500';
                            if (!empty($dueDate)) {
                                $dueTs = strtotime($dueDate);
                                if ($dueTs < $todayStart) {
                                    $dueStateLabel = 'Overdue';
                                    $dueStateClass = 'bg-red-100 text-red-700';
                                } elseif ($dueTs === $todayStart) {
                                    $dueStateLabel = 'Due Today';
                                    $dueStateClass = 'bg-amber-100 text-amber-700';
                                } elseif ($dueTs <= $todayStart + 2 * 86400) {
                                    $dueStateLabel = 'Due Soon';
                                    $dueStateClass = 'bg-yellow-100 text-yellow-700';
                                } else {
                                    $dueStateLabel = 'Upcoming';
                                    $dueStateClass = 'bg-green-100 text-green-700';
                                }
                            }
                        ?>
                        <div class="md:col-span-3 flex items-center gap-2 flex-wrap">
                            <!-- Status Badges (Overdue/Due Soon/Not Started) -->
                            <div class="deadline-badge-container" data-task-id="<?php echo e($task['id']); ?>">
                                <!-- Badges will be inserted here by JavaScript -->
                            </div>

                            <!-- Timer Display (Hidden by default, shown when timer running) -->
                            <div class="timer-display-container hidden" data-task-timer-display="<?php echo e($task['id']); ?>">
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-mono font-bold rounded-full" data-task-timer-remaining="<?php echo e($task['id']); ?>">00:00</span>
                            </div>

                            <!-- Estimated Time (always visible) -->
                            <div class="flex items-center gap-1.5 text-xs text-gray-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?php echo (int)($task['estimatedMinutes'] ?? 0); ?>m</span>
                            </div>

                            <!-- Time Spent Badge -->
                            <?php if (!empty($task['actualMinutes'])): ?>
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-medium rounded-full">
                                <?php echo (int)$task['actualMinutes']; ?>m spent
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="md:col-span-1 flex items-center justify-end">
                            <div class="grid grid-cols-2 gap-1 opacity-100 md:opacity-20 md:group-hover:opacity-100 md:group-focus-within:opacity-100 transition-opacity">
                                <button onclick="toggleTaskTimer('<?php echo e($task['id']); ?>')" class="task-timer-btn p-1.5 bg-white border border-gray-200 rounded-lg text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-all" title="Start timer" data-task-timer-btn="<?php echo e($task['id']); ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.586-2.293A1 1 0 009 9.764v4.472a1 1 0 001.166.986l4.586-2.293a1 1 0 000-1.758z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                <a href="?page=view-task&id=<?php echo e($task['id']); ?>&projectId=<?php echo e($task['projectId']); ?>" class="p-1.5 bg-white border border-gray-200 rounded-lg text-gray-500 hover:text-green-600 hover:border-green-600 transition-all flex items-center justify-center" title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <a href="?page=task-form&id=<?php echo e($task['id']); ?>" class="p-1.5 bg-white border border-gray-200 rounded-lg text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-all flex items-center justify-center" title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </a>
                                <button onclick="deleteTask('<?php echo e($task['id']); ?>')" class="p-1.5 bg-white border border-gray-200 rounded-lg text-gray-500 hover:text-red-600 hover:border-red-600 transition-all" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
        <div class="p-4 bg-gray-50 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing <?php echo (($currentPage - 1) * $itemsPerPage) + 1; ?> - <?php echo min($currentPage * $itemsPerPage, $totalTasks); ?> of <?php echo $totalTasks; ?> tasks
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?php echo $baseUrl . '&page_num=' . ($currentPage - 1) . '&' . http_build_query($queryParams); ?>" 
                           class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed">
                            ← Previous
                        </span>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-1">
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1) {
                            echo '<a href="' . $baseUrl . '&page_num=1&' . http_build_query($queryParams) . '" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 transition">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="w-8 h-8 flex items-center justify-center text-gray-400">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="w-8 h-8 flex items-center justify-center bg-black text-white rounded-lg text-sm font-medium">
                                    <?php echo $i; ?>
                                </span>
                            <?php else: ?>
                                <a href="<?php echo $baseUrl . '&page_num=' . $i . '&' . http_build_query($queryParams); ?>" 
                                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor;
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="w-8 h-8 flex items-center justify-center text-gray-400">...</span>';
                            }
                            echo '<a href="' . $baseUrl . '&page_num=' . $totalPages . '&' . http_build_query($queryParams) . '" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100 transition">' . $totalPages . '</a>';
                        }
                        ?>
                    </div>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?php echo $baseUrl . '&page_num=' . ($currentPage + 1) . '&' . http_build_query($queryParams); ?>" 
                           class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 cursor-not-allowed">
                            Next →
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Analytics Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Completion Rate</p>
            <p class="text-2xl font-black text-gray-900"><?php echo $completionRate; ?>%</p>
            <p class="text-xs text-gray-500 mt-1"><?php echo $completedTaskCount; ?> of <?php echo $totalTasks; ?> tasks completed</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Overdue Open</p>
            <p class="text-2xl font-black text-red-600"><?php echo $overdueTaskCount; ?></p>
            <p class="text-xs text-gray-500 mt-1">Need priority attention</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Estimated Time</p>
            <p class="text-2xl font-black text-gray-900"><?php echo (int)round($totalEstimatedMinutes / 60); ?>h</p>
            <p class="text-xs text-gray-500 mt-1"><?php echo $totalEstimatedMinutes; ?> total minutes planned</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Time Accuracy</p>
            <p class="text-2xl font-black <?php echo $timeAccuracy > 120 ? 'text-red-600' : 'text-green-700'; ?>"><?php echo $timeAccuracy; ?>%</p>
            <p class="text-xs text-gray-500 mt-1"><?php echo $totalActualMinutes; ?>m logged vs <?php echo $totalEstimatedMinutes; ?>m estimated</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-black uppercase tracking-widest text-gray-500">Weekly Time Summary</h3>
            <span class="text-xs font-bold text-gray-400"><?php echo date('M j', $weekStartTs); ?> - <?php echo date('M j', $weekEndTs - 86400); ?></span>
        </div>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Logged This Week</p>
                <p class="text-2xl font-black text-gray-900"><?php echo (int)round($weeklyLoggedMinutes / 60); ?>h</p>
                <p class="text-xs text-gray-500 mt-1"><?php echo $weeklyLoggedMinutes; ?> minutes tracked</p>
            </div>
            <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Top Projects</p>
                <?php if (empty($topProjects)): ?>
                    <p class="text-xs text-gray-400">No tracked time yet this week.</p>
                <?php else: ?>
                    <div class="space-y-1">
                        <?php foreach ($topProjects as $projectName => $minutes): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-700 truncate pr-3"><?php echo e($projectName); ?></span>
                                <span class="font-black text-gray-900"><?php echo (int)$minutes; ?>m</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                <p class="text-[11px] font-black uppercase tracking-widest text-gray-400 mb-2">Top Tasks</p>
                <?php if (empty($topTasks)): ?>
                    <p class="text-xs text-gray-400">No tracked time yet this week.</p>
                <?php else: ?>
                    <div class="space-y-1">
                        <?php foreach ($topTasks as $taskTitle => $minutes): ?>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-700 truncate pr-3"><?php echo e($taskTitle); ?></span>
                                <span class="font-black text-gray-900"><?php echo (int)$minutes; ?>m</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
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

// Task timer (list view)
const taskTimers = new Map();
const TASK_TIMER_STORAGE_KEY = 'taskTimers';
const stoppingTaskTimers = new Set();

function formatTimerDisplay(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function formatClockTime(timestamp) {
    if (!timestamp) return '--';
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function requestTaskNotificationPermission() {
    if (!('Notification' in window)) {
        return;
    }
    if (Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {});
    }
}

function sendTaskTimerNotification(title, body) {
    if ('Notification' in window && Notification.permission === 'granted') {
        try {
            new Notification(title, {
                body: body,
                tag: 'task-timer',
                silent: false
            });
        } catch (error) {
            console.warn('Notification failed:', error);
        }
    }
    showToast(body, 'info');
}

const selectedTaskIds = new Set();
const priorityRank = { urgent: 4, high: 3, medium: 2, low: 1 };

function getTaskRows() {
    return Array.from(document.querySelectorAll('.task-row'));
}

function getTaskRow(taskId) {
    return document.querySelector(`.task-row[data-task-id="${taskId}"]`);
}

function getTaskCards(taskId) {
    return Array.from(document.querySelectorAll(`.task-card[data-id="${taskId}"]`));
}

function getTaskElements(taskId) {
    return {
        row: getTaskRow(taskId),
        cards: getTaskCards(taskId),
        buttons: Array.from(document.querySelectorAll(`[data-task-timer-btn="${taskId}"]`)),
        timerContainers: Array.from(document.querySelectorAll(`[data-task-timer-display="${taskId}"]`)),
        remainingEls: Array.from(document.querySelectorAll(`[data-task-timer-remaining="${taskId}"]`))
    };
}

function getEstimatedMinutesForTask(taskId) {
    const row = getTaskRow(taskId);
    if (row) {
        return parseInt(row.dataset.estimatedMinutes || '0', 10);
    }
    const card = getTaskCards(taskId)[0];
    if (card) {
        return parseInt(card.dataset.estimatedMinutes || '0', 10);
    }
    return 0;
}

function getTaskStatus(taskId) {
    const row = getTaskRow(taskId);
    if (row) {
        return row.dataset.status || 'todo';
    }
    const card = getTaskCards(taskId)[0];
    if (card) {
        return card.dataset.status || 'todo';
    }
    return 'todo';
}

function setTaskStatus(taskId, status) {
    const row = getTaskRow(taskId);
    if (row) {
        row.dataset.status = status;
    }
    getTaskCards(taskId).forEach(card => {
        card.dataset.status = status;
    });
}

async function persistTaskTimerState(taskId, timer) {
    try {
        await api.put(`api/tasks.php?id=${taskId}`, {
            timerState: timer ? {
                startTime: timer.startTime,
                estimatedMinutes: timer.estimatedMinutes || 0,
                running: !!timer.running,
                paused: !!timer.paused,
                pausedTime: timer.pausedTime || null
            } : null,
            csrf_token: CSRF_TOKEN
        });
    } catch (error) {
        console.warn('Failed to persist timer state:', error);
    }
}

function updateTaskTimerUI(taskId) {
    const { row, buttons, timerContainers, remainingEls } = getTaskElements(taskId);
    const timer = taskTimers.get(taskId);
    const estimatedMinutes = getEstimatedMinutesForTask(taskId);

    timerContainers.forEach(container => {
        container.classList.toggle('hidden', !(timer && timer.running));
    });

    if (timer && timer.running) {
        const elapsedSeconds = Math.floor((Date.now() - timer.startTime) / 1000);
        const remainingSeconds = estimatedMinutes > 0 ? Math.max(0, estimatedMinutes * 60 - elapsedSeconds) : null;
        remainingEls.forEach(remainingEl => {
            remainingEl.textContent = remainingSeconds === null ? '--' : formatTimerDisplay(remainingSeconds);
        });
    }

    if (row) {
        updateTaskBadges(taskId, row, row.dataset.dueDate || '', timer);
    }

    buttons.forEach(btn => {
        if (timer && timer.running) {
            btn.title = 'Pause timer';
            btn.classList.add('text-black', 'border-black');
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        } else if (timer && timer.paused) {
            btn.title = 'Resume timer';
            btn.classList.add('text-black', 'border-black');
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.586-2.293A1 1 0 009 9.764v4.472a1 1 0 001.166.986l4.586-2.293a1 1 0 000-1.758z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        } else {
            btn.title = 'Start timer';
            btn.classList.remove('text-black', 'border-black');
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.586-2.293A1 1 0 009 9.764v4.472a1 1 0 001.166.986l4.586-2.293a1 1 0 000-1.758z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        }
    });
}

/**
 * Phase 2: Update task status badges (Overdue, Due Soon, Not Started)
 */
function updateTaskBadges(taskId, row, dueDate, timer) {
    const badgeContainer = row.querySelector(`.deadline-badge-container[data-task-id="${taskId}"]`);
    if (!badgeContainer) return;

    // Clear existing badges
    badgeContainer.innerHTML = '';

    if (!dueDate) {
        return; // No due date, no badges
    }

    const now = new Date();
    const due = new Date(dueDate);
    const timeDiff = due.getTime() - now.getTime();
    const minutesDiff = Math.floor(timeDiff / (1000 * 60));
    const isDone = row.dataset.status === 'done';

    // Don't show badges for completed tasks
    if (isDone) {
        return;
    }

    // Badge 1: OVERDUE (Red) - Past due date
    if (timeDiff < 0) {
        const badge = document.createElement('span');
        badge.className = 'px-2 py-0.5 bg-red-100 text-red-800 text-[10px] font-bold rounded-full inline-flex items-center gap-1';
        badge.innerHTML = `
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z"/>
            </svg>
            OVERDUE
        `;
        badgeContainer.appendChild(badge);
    }
    // Badge 2: DUE SOON (Orange) - Within 15 minutes
    else if (minutesDiff >= 0 && minutesDiff <= 15) {
        const badge = document.createElement('span');
        badge.className = 'px-2 py-0.5 bg-orange-100 text-orange-800 text-[10px] font-bold rounded-full inline-flex items-center gap-1';
        badge.innerHTML = `
            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
            </svg>
            DUE SOON
        `;
        badgeContainer.appendChild(badge);
    }
    // Badge 3: NOT STARTED (Yellow) - Timer not running and approaching deadline
    else if (!timer || !timer.running) {
        // Only show if due within 1 hour
        if (minutesDiff >= 0 && minutesDiff <= 60) {
            const badge = document.createElement('span');
            badge.className = 'px-2 py-0.5 bg-yellow-100 text-yellow-800 text-[10px] font-bold rounded-full inline-flex items-center gap-1';
            badge.innerHTML = `
                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
                </svg>
                NOT STARTED
            `;
            badgeContainer.appendChild(badge);
        }
    }
}

function saveTaskTimers() {
    const data = {};
    taskTimers.forEach((timer, taskId) => {
        if (timer?.running || timer?.paused) {
            data[taskId] = {
                startTime: timer.startTime,
                estimatedMinutes: timer.estimatedMinutes,
                running: timer.running,
                paused: timer.paused,
                pausedTime: timer.pausedTime
            };
        }
    });
    localStorage.setItem(TASK_TIMER_STORAGE_KEY, JSON.stringify(data));
}

function loadTaskTimers() {
    const serverTimers = {};
    getTaskRows().forEach(row => {
        const rawTimerState = row.dataset.timerState;
        if (!rawTimerState || rawTimerState === 'null') {
            return;
        }

        try {
            const parsed = JSON.parse(rawTimerState);
            if (parsed && parsed.startTime) {
                serverTimers[row.dataset.taskId] = parsed;
            }
        } catch (error) {
            console.warn('Invalid task timer state:', error);
        }
    });

    try {
        const saved = JSON.parse(localStorage.getItem(TASK_TIMER_STORAGE_KEY) || '{}');
        const mergedTimers = { ...saved, ...serverTimers };

        Object.entries(mergedTimers).forEach(([taskId, timer]) => {
            if (timer?.startTime) {
                taskTimers.set(taskId, {
                    startTime: Number(timer.startTime),
                    estimatedMinutes: timer.estimatedMinutes || 0,
                    running: timer.running || false,
                    paused: timer.paused || false,
                    pausedTime: timer.pausedTime || null,
                    interval: null
                });
                if (timer.running) {
                    startTaskTimerInterval(taskId);
                }
            }
        });
    } catch (error) {
        console.warn('Failed to load task timers:', error);
    }
}

function startTaskTimerInterval(taskId) {
    const timer = taskTimers.get(taskId);
    if (!timer) return;

    updateTaskTimerUI(taskId);
    if (timer.interval) clearInterval(timer.interval);
    timer.interval = setInterval(() => {
        const estimatedMinutes = getEstimatedMinutesForTask(taskId);
        const elapsedSeconds = Math.floor((Date.now() - timer.startTime) / 1000);

        updateTaskTimerUI(taskId);

        if (estimatedMinutes > 0 && elapsedSeconds >= estimatedMinutes * 60) {
            stopTaskTimer(taskId, true);
        }
    }, 1000);
}

async function logTaskTimeEntry(taskId, minutes, description) {
    try {
        await api.put(`api/tasks.php?id=${taskId}`, {
            addTimeEntry: true,
            timeEntries: {
                minutes: minutes,
                description: description || 'Task timer',
                date: new Date().toISOString()
            },
            csrf_token: CSRF_TOKEN
        });
    } catch (error) {
        console.error('Failed to log time entry:', error);
    }
}

function startTaskTimer(taskId) {
    if (getTaskStatus(taskId) === 'done') {
        showToast('Task is already completed', 'info');
        return;
    }

    requestTaskNotificationPermission();

    const estimatedMinutes = getEstimatedMinutesForTask(taskId);
    taskTimers.set(taskId, {
        startTime: Date.now(),
        estimatedMinutes: estimatedMinutes,
        running: true,
        paused: false,
        pausedTime: null,
        interval: null
    });
    startTaskTimerInterval(taskId);
    saveTaskTimers();
    persistTaskTimerState(taskId, taskTimers.get(taskId));
    showToast('Timer started', 'success');
}

async function stopTaskTimer(taskId, autoComplete = false, silent = false) {
    const timer = taskTimers.get(taskId);
    if (!timer) return;
    if (stoppingTaskTimers.has(taskId)) return;
    stoppingTaskTimers.add(taskId);

    try {
        if (timer.interval) {
            clearInterval(timer.interval);
        }

        const elapsedSeconds = Math.floor((Date.now() - timer.startTime) / 1000);
        const elapsedMinutes = Math.max(1, Math.round(elapsedSeconds / 60));
        const estimatedMinutes = timer.estimatedMinutes || 0;
        const loggedMinutes = autoComplete && estimatedMinutes > 0 ? estimatedMinutes : elapsedMinutes;

        taskTimers.delete(taskId);
        saveTaskTimers();
        updateTaskTimerUI(taskId);
        await persistTaskTimerState(taskId, null);

        await logTaskTimeEntry(taskId, loggedMinutes, autoComplete ? 'Task timer (estimate)' : 'Task timer');
        if (autoComplete) {
            const row = getTaskRow(taskId);
            const title = row?.querySelector('p.font-bold')?.textContent?.trim() || 'Task timer';
            sendTaskTimerNotification('Task time complete', `${title} reached its estimated time.`);
        } else if (!silent) {
            showToast('Timer stopped and logged', 'success');
        }
    } finally {
        stoppingTaskTimers.delete(taskId);
    }
}

function toggleTaskTimer(taskId) {
    const timer = taskTimers.get(taskId);
    if (timer?.running) {
        pauseTaskTimer(taskId);
    } else if (timer?.paused) {
        resumeTaskTimer(taskId);
    } else {
        startTaskTimer(taskId);
    }
}

function pauseTaskTimer(taskId) {
    const timer = taskTimers.get(taskId);
    if (!timer || !timer.running) return;

    if (timer.interval) {
        clearInterval(timer.interval);
        timer.interval = null;
    }

    timer.running = false;
    timer.paused = true;
    timer.pausedTime = Date.now();

    saveTaskTimers();
    persistTaskTimerState(taskId, timer);
    updateTaskTimerUI(taskId);
    showToast('Timer paused', 'info');
}

function resumeTaskTimer(taskId) {
    const timer = taskTimers.get(taskId);
    if (!timer || !timer.paused) return;

    // Adjust start time to account for pause duration
    const pauseDuration = Date.now() - timer.pausedTime;
    timer.startTime += pauseDuration;
    timer.paused = false;
    timer.running = true;
    timer.pausedTime = null;

    startTaskTimerInterval(taskId);
    saveTaskTimers();
    persistTaskTimerState(taskId, timer);
    showToast('Timer resumed', 'success');
}

function reorderTaskRows() {
    const container = document.querySelector('#list-view .divide-y');
    if (!container) return;

    const sortBy = document.getElementById('sort-by')?.value || 'default';
    const sortDirection = document.getElementById('sort-direction')?.dataset.direction || 'asc';
    const rows = Array.from(container.querySelectorAll('.task-row'));

    const getValue = (row) => {
        if (sortBy === 'title') return (row.dataset.title || '').toLowerCase();
        if (sortBy === 'dueDate') return row.dataset.dueDate || '9999-12-31';
        if (sortBy === 'priority') return priorityRank[row.dataset.priority || 'low'] || 0;
        if (sortBy === 'estimated') return parseInt(row.dataset.estimatedMinutes || '0', 10);
        if (sortBy === 'actual') return parseInt(row.dataset.actualMinutes || '0', 10);
        return parseInt(row.dataset.taskNumber || '999999', 10);
    };

    rows.sort((a, b) => {
        if (sortBy === 'default') {
            const doneA = a.dataset.status === 'done';
            const doneB = b.dataset.status === 'done';
            if (doneA !== doneB) {
                return doneA ? 1 : -1;
            }

            const dueA = a.dataset.dueDate || '9999-12-31';
            const dueB = b.dataset.dueDate || '9999-12-31';
            const dueComparison = dueA.localeCompare(dueB);
            if (dueComparison !== 0) {
                return dueComparison;
            }

            const priorityA = priorityRank[a.dataset.priority || 'low'] || 0;
            const priorityB = priorityRank[b.dataset.priority || 'low'] || 0;
            if (priorityA !== priorityB) {
                return priorityB - priorityA;
            }

            return (a.dataset.title || '').toLowerCase().localeCompare((b.dataset.title || '').toLowerCase());
        }

        const valueA = getValue(a);
        const valueB = getValue(b);
        let comparison = 0;

        if (typeof valueA === 'number' && typeof valueB === 'number') {
            comparison = valueA - valueB;
        } else {
            comparison = String(valueA).localeCompare(String(valueB));
        }

        if (comparison === 0) {
            comparison = (a.dataset.title || '').toLowerCase().localeCompare((b.dataset.title || '').toLowerCase());
        }

        return sortDirection === 'desc' ? -comparison : comparison;
    });

    rows.forEach(row => container.appendChild(row));
}

// Toggle task complete
async function toggleTask(taskId, completed) {
    const response = await api.put(`api/tasks.php?id=${taskId}`, {
        status: completed ? 'done' : 'todo',
        csrf_token: CSRF_TOKEN
    });
    
    if (response.success) {
        if (completed && taskTimers.has(taskId)) {
            await stopTaskTimer(taskId, false, true);
        }

        setTaskStatus(taskId, completed ? 'done' : 'todo');
        const row = getTaskRow(taskId);
        if (row) {
            const title = row.querySelector('p.font-bold');
            if (title) {
                title.classList.toggle('line-through', completed);
                title.classList.toggle('text-red-500', completed);
                title.classList.toggle('decoration-red-500', completed);
                if (!completed) {
                    title.classList.remove('text-red-500', 'decoration-red-500');
                }
            }
        }
        reorderTaskRows();
        filterTasks();
        showToast(completed ? 'Task completed!' : 'Task reopened', 'success');
    }
}

// Quick subtask toggle from list view
async function toggleSubtaskQuick(taskId, projectId, subtaskId, completed, checkboxEl) {
    try {
        const response = await api.post(`api/tasks.php?action=subtask&projectId=${projectId}&id=${taskId}`, {
            subtaskId: subtaskId,
            completed: completed,
            csrf_token: CSRF_TOKEN
        });
        
        if (response.success) {
            const subtaskSpan = checkboxEl?.nextElementSibling;
            if (subtaskSpan) {
                subtaskSpan.classList.toggle('line-through', completed);
                subtaskSpan.classList.toggle('text-gray-400', completed);
            }
            showToast(completed ? 'Subtask completed!' : 'Subtask reopened', 'success');
        } else {
            showToast(response.error || 'Failed to update subtask', 'error');
        }
    } catch (error) {
        console.error('Failed to toggle subtask:', error);
        showToast('Failed to update subtask', 'error');
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
                    setTaskStatus(taskId, newStatus);
                    const row = getTaskRow(taskId);
                    if (row) {
                        const title = row.querySelector('p.font-bold');
                        const completed = newStatus === 'done';
                        if (title) {
                            title.classList.toggle('line-through', completed);
                            title.classList.toggle('text-red-500', completed);
                            title.classList.toggle('decoration-red-500', completed);
                        }
                    }
                    filterTasks();
                    showToast('Task moved', 'success');
                }
            }
        });
    });
});

// Default to list view and load timers
document.addEventListener('DOMContentLoaded', () => {
    setView('list');
    loadTaskTimers();
    getTaskRows().forEach(row => {
        updateTaskTimerUI(row.dataset.taskId);
    });
    reorderTaskRows();
    filterTasks();

    const searchInput = document.getElementById('filter-search');
    const sortDirectionBtn = document.getElementById('sort-direction');
    const selectAllVisible = document.getElementById('select-all-visible');
    const newTaskLink = document.querySelector('a[href="?page=task-form"]');

    let searchDebounce = null;
    searchInput?.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => {
            filterTasks();
        }, 120);
    });

    sortDirectionBtn?.addEventListener('click', () => {
        const currentDirection = sortDirectionBtn.dataset.direction || 'asc';
        const nextDirection = currentDirection === 'asc' ? 'desc' : 'asc';
        sortDirectionBtn.dataset.direction = nextDirection;
        sortDirectionBtn.textContent = nextDirection === 'asc' ? 'Asc' : 'Desc';
        reorderTaskRows();
        filterTasks();
    });

    selectAllVisible?.addEventListener('change', (event) => {
        const checked = event.target.checked;
        getVisibleTaskRows().forEach(row => {
            const taskId = row.dataset.taskId;
            const checkbox = row.querySelector('.bulk-select-checkbox');
            if (!checkbox) {
                return;
            }
            checkbox.checked = checked;
            if (checked) {
                selectedTaskIds.add(taskId);
            } else {
                selectedTaskIds.delete(taskId);
            }
        });
        syncBulkSelectionUI();
    });

    syncBulkSelectionUI();
    setActiveFocusChip('all');
    document.querySelectorAll('.today-focus-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            applyTodayFocusPreset(chip.dataset.focus || 'all');
        });
    });

    document.addEventListener('keydown', (event) => {
        const tagName = (event.target?.tagName || '').toLowerCase();
        const isTypingContext = tagName === 'input' || tagName === 'textarea' || tagName === 'select' || event.target?.isContentEditable;
        if (isTypingContext) {
            return;
        }

        if (event.key === 'n' || event.key === 'N') {
            event.preventDefault();
            if (newTaskLink) {
                window.location.href = newTaskLink.getAttribute('href');
            }
        } else if (event.key === '/' || event.key === 'f' || event.key === 'F') {
            event.preventDefault();
            searchInput?.focus();
        } else if (event.key === 'x' || event.key === 'X') {
            event.preventDefault();
            completeSelectedTasksShortcut();
        }
    });

    // Phase 2: Update badges every 10 seconds for all tasks
    setInterval(() => {
        getTaskRows().forEach(row => {
            const taskId = row.dataset.taskId;
            const dueDate = row.dataset.dueDate || '';
            const timer = taskTimers.get(taskId);
            updateTaskBadges(taskId, row, dueDate, timer);
        });
    }, 10000); // Update every 10 seconds
});

// Delete task
async function deleteTask(taskId) {
    const confirmed = await confirmAction('Are you sure you want to delete this task? This action cannot be undone.');
    if (!confirmed) return;

    const response = await api.delete(`api/tasks.php?id=${taskId}`);
    if (response.success) {
        showToast('Task deleted', 'success');
        setTimeout(() => location.reload(), 500);
    } else {
        showToast(response.error || 'Failed to delete task', 'error');
    }
}

// Filter list view
['filter-project', 'filter-status', 'filter-priority', 'filter-due', 'sort-by'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => {
        reorderTaskRows();
        filterTasks();
    });
});

function updateTaskCount() {
    const rows = getTaskRows();
    const visible = rows.filter(row => row.style.display !== 'none').length;
    const totalEl = document.getElementById('task-count-total');
    const visibleEl = document.getElementById('task-count-visible');
    if (totalEl) totalEl.textContent = rows.length;
    if (visibleEl) visibleEl.textContent = visible;
}

function matchesDueFilter(row, dueFilter) {
    const dueDate = row.dataset.dueDate || '';
    if (!dueFilter) return true;
    if (dueFilter === 'none') return !dueDate;
    if (!dueDate) return false;

    const due = new Date(`${dueDate}T00:00:00`);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const weekEnd = new Date(today);
    weekEnd.setDate(today.getDate() + 7);

    if (dueFilter === 'overdue') return due < today;
    if (dueFilter === 'today') return due.getTime() === today.getTime();
    if (dueFilter === 'week') return due >= today && due <= weekEnd;
    return true;
}

function filterTasks() {
    const project = document.getElementById('filter-project').value;
    const status = document.getElementById('filter-status').value;
    const priority = document.getElementById('filter-priority').value;
    const dueFilter = document.getElementById('filter-due').value;
    const searchTerm = (document.getElementById('filter-search')?.value || '').trim().toLowerCase();

    getTaskRows().forEach(row => {
        const matchProject = !project || row.dataset.project === project;
        const matchStatus = !status || row.dataset.status === status;
        const matchPriority = !priority || row.dataset.priority === priority;
        const matchDue = matchesDueFilter(row, dueFilter);

        const searchableContent = [
            row.dataset.taskNumber || '',
            row.dataset.title || '',
            row.dataset.description || '',
            row.dataset.projectName || '',
            row.dataset.subtasks || ''
        ].join(' ').toLowerCase();

        const matchSearch = !searchTerm || searchableContent.includes(searchTerm);

        row.style.display = matchProject && matchStatus && matchPriority && matchDue && matchSearch ? '' : 'none';
    });

    updateTaskCount();
    syncBulkSelectionUI();
}

function setActiveFocusChip(focus) {
    document.querySelectorAll('.today-focus-chip').forEach((chip) => {
        const isActive = chip.dataset.focus === focus;
        chip.classList.toggle('ring-2', isActive);
        chip.classList.toggle('ring-black', isActive);
    });
}

function applyTodayFocusPreset(focus) {
    const dueFilter = document.getElementById('filter-due');
    const statusFilter = document.getElementById('filter-status');
    if (!dueFilter || !statusFilter) {
        return;
    }

    if (focus === 'overdue') {
        dueFilter.value = 'overdue';
        statusFilter.value = '';
    } else if (focus === 'today') {
        dueFilter.value = 'today';
        statusFilter.value = '';
    } else if (focus === 'in_progress') {
        dueFilter.value = '';
        statusFilter.value = 'in_progress';
    } else {
        dueFilter.value = '';
        statusFilter.value = '';
    }

    setActiveFocusChip(focus);
    filterTasks();
}

async function completeSelectedTasksShortcut() {
    if (selectedTaskIds.size === 0) {
        showToast('Select tasks first', 'info');
        return;
    }
    await bulkUpdateStatus('done');
}

function toggleBulkSelection(taskId, checked) {
    if (checked) {
        selectedTaskIds.add(taskId);
    } else {
        selectedTaskIds.delete(taskId);
    }
    syncBulkSelectionUI();
}

function clearBulkSelection() {
    selectedTaskIds.clear();
    document.querySelectorAll('.bulk-select-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    syncBulkSelectionUI();
}

function getVisibleTaskRows() {
    return getTaskRows().filter(row => row.style.display !== 'none');
}

function syncBulkSelectionUI() {
    const bulkBar = document.getElementById('bulk-action-bar');
    const countEl = document.getElementById('bulk-selected-count');
    const selectAll = document.getElementById('select-all-visible');
    const visibleRows = getVisibleTaskRows();
    const visibleIds = new Set(visibleRows.map(row => row.dataset.taskId));

    let visibleSelectedCount = 0;
    visibleRows.forEach(row => {
        if (selectedTaskIds.has(row.dataset.taskId)) {
            visibleSelectedCount++;
        }
    });

    if (countEl) {
        countEl.textContent = String(selectedTaskIds.size);
    }

    if (bulkBar) {
        bulkBar.classList.toggle('hidden', selectedTaskIds.size === 0);
    }

    if (selectAll) {
        selectAll.checked = visibleRows.length > 0 && visibleSelectedCount === visibleRows.length;
        selectAll.indeterminate = visibleSelectedCount > 0 && visibleSelectedCount < visibleRows.length;
    }

    document.querySelectorAll('.bulk-select-checkbox').forEach(checkbox => {
        const isVisible = visibleIds.has(checkbox.value);
        checkbox.checked = selectedTaskIds.has(checkbox.value);
        checkbox.disabled = !isVisible;
    });
}

async function bulkUpdateStatus(status) {
    if (selectedTaskIds.size === 0) {
        showToast('Select tasks first', 'info');
        return;
    }

    const response = await api.post('api/tasks.php?action=bulk', {
        operation: 'status',
        status: status,
        taskIds: Array.from(selectedTaskIds),
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        showToast(response.message || 'Tasks updated', 'success');
        setTimeout(() => location.reload(), 300);
    } else {
        showToast(response.error || 'Bulk update failed', 'error');
    }
}

async function bulkDeleteTasks() {
    if (selectedTaskIds.size === 0) {
        showToast('Select tasks first', 'info');
        return;
    }

    const confirmed = await confirmAction(`Delete ${selectedTaskIds.size} selected tasks? This action cannot be undone.`);
    if (!confirmed) {
        return;
    }

    const response = await api.post('api/tasks.php?action=bulk', {
        operation: 'delete',
        taskIds: Array.from(selectedTaskIds),
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        showToast(response.message || 'Tasks deleted', 'success');
        setTimeout(() => location.reload(), 300);
    } else {
        showToast(response.error || 'Bulk delete failed', 'error');
    }
}

// AI Kanban Generation
let generatedKanbanData = null;
let selectedKanbanProjectId = '';

function openAIGenerateKanban() {
    document.getElementById('ai-kanban-modal').classList.remove('hidden');
    document.getElementById('ai-kanban-modal').classList.add('flex');
    document.getElementById('ai-kanban-description').value = '';
    document.getElementById('ai-kanban-preview').classList.add('hidden');
    generatedKanbanData = null;
}

function closeAIGenerateKanban() {
    document.getElementById('ai-kanban-modal').classList.add('hidden');
    document.getElementById('ai-kanban-modal').classList.remove('flex');
}

async function generateKanban() {
    const description = document.getElementById('ai-kanban-description').value.trim();
    selectedKanbanProjectId = document.getElementById('ai-kanban-project').value;

    if (!description) {
        showToast('Please describe your project', 'info');
        return;
    }

    const btn = document.getElementById('ai-kanban-generate-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Generating...';

    try {
        // Get model info
        const modelsResponse = await api.get('api/models.php');
        const models = modelsResponse.data || {};
        const groqModels = models?.groq || [];
        const defaultModel = groqModels.find(m => m.isDefault) || groqModels[0];
        const model = defaultModel?.modelId;
        if (!model) {
            showToast('No AI model configured. Please set up a model in Model Settings.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        const response = await api.post('api/ai.php?action=generate_tasks', {
            description: description,
            provider: 'groq',
            model: model,
            csrf_token: CSRF_TOKEN
        });

        if (response.success && response.data?.tasks) {
            generatedKanbanData = response.data.tasks;
            renderKanbanPreview(response.data.tasks);
            document.getElementById('ai-kanban-preview').classList.remove('hidden');
            showToast('Kanban board generated!', 'success');
        } else {
            showToast('Failed to generate kanban board', 'error');
        }
    } catch (error) {
        console.error('Kanban generation error:', error);
        showToast('Failed to generate: ' + (error.message || 'Unknown error'), 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function renderKanbanPreview(tasks) {
    const container = document.getElementById('ai-kanban-columns');
    container.innerHTML = '';

    // Group tasks by priority for a simple 3-column view
    const columns = {
        'High Priority': tasks.filter(t => t.priority === 'high' || t.priority === 'urgent'),
        'Medium Priority': tasks.filter(t => t.priority === 'medium'),
        'Low Priority': tasks.filter(t => t.priority === 'low' || !t.priority)
    };

    const colors = {
        'High Priority': 'bg-red-50 border-red-200',
        'Medium Priority': 'bg-amber-50 border-amber-200',
        'Low Priority': 'bg-green-50 border-green-200'
    };

    Object.entries(columns).forEach(([columnName, columnTasks]) => {
        const col = document.createElement('div');
        col.className = `rounded-xl p-3 border-2 ${colors[columnName]}`;

        let tasksHtml = columnTasks.map(task => `
            <div class="bg-white rounded-lg p-3 shadow-sm mb-2 text-sm">
                <p class="font-bold text-gray-900">${task.title || 'Untitled'}</p>
                <p class="text-xs text-gray-500 mt-1">${(task.description || '').substring(0, 60)}${task.description?.length > 60 ? '...' : ''}</p>
                <div class="flex items-center gap-2 mt-2">
                    <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded ${getPriorityClass(task.priority || 'medium')}">${task.priority || 'medium'}</span>
                    <span class="text-xs text-gray-400">${task.estimatedMinutes || 60} min</span>
                </div>
            </div>
        `).join('');

        if (columnTasks.length === 0) {
            tasksHtml = '<p class="text-sm text-gray-400 text-center py-4">No tasks</p>';
        }

        col.innerHTML = `
            <h5 class="font-bold text-gray-700 mb-3 text-xs uppercase tracking-widest">${columnName} (${columnTasks.length})</h5>
            ${tasksHtml}
        `;

        container.appendChild(col);
    });
}

function getPriorityClass(priority) {
    const classes = {
        'urgent': 'bg-red-100 text-red-700',
        'high': 'bg-orange-100 text-orange-700',
        'medium': 'bg-amber-100 text-amber-700',
        'low': 'bg-green-100 text-green-700'
    };
    return classes[priority] || classes['medium'];
}

async function importKanbanToProject() {
    if (!generatedKanbanData || generatedKanbanData.length === 0) {
        showToast('No tasks to import', 'error');
        return;
    }

    let projectId = selectedKanbanProjectId;

    // If no project selected, create a new one
    if (!projectId) {
        const projectName = 'AI Generated Project';
        try {
            const createResponse = await api.post('api/projects.php', {
                name: projectName,
                description: document.getElementById('ai-kanban-description').value,
                status: 'active',
                color: '#3B82F6',
                csrf_token: CSRF_TOKEN
            });

            if (createResponse.success && createResponse.data?.id) {
                projectId = createResponse.data.id;
            } else {
                showToast('Failed to create project', 'error');
                return;
            }
        } catch (error) {
            showToast('Failed to create project', 'error');
            return;
        }
    }

    // Import tasks using the AI import action
    try {
        const response = await api.post('api/ai.php?action=import_tasks', {
            projectId: projectId,
            tasks: generatedKanbanData,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Kanban board imported!', 'success');
            closeAIGenerateKanban();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(response.error || 'Failed to import tasks', 'error');
        }
    } catch (error) {
        showToast('Failed to import tasks', 'error');
    }
}

</script>

