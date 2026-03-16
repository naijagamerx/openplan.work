<?php
/**
 * Mobile Project Detail View
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=projects">Back to projects</a></p>
    </body></html>');
}

$siteName = getSiteName() ?? 'LazyMan';
$projectId = trim((string)($_GET['id'] ?? ''));
$projects = $db->load('projects') ?? [];
$clients = $db->load('clients') ?? [];

$project = null;
foreach ($projects as $candidate) {
    if ((string)($candidate['id'] ?? '') === $projectId) {
        $project = $candidate;
        break;
    }
}

$statusLabels = [
    'planning' => 'Planning',
    'active' => 'Active',
    'in_progress' => 'In Progress',
    'review' => 'Review',
    'completed' => 'Completed',
    'on_hold' => 'On Hold',
    'cancelled' => 'Cancelled'
];

$statusClassMap = [
    'planning' => 'border border-black dark:border-white bg-white dark:bg-zinc-950 text-black dark:text-white',
    'active' => 'border border-black dark:border-white bg-black text-white dark:bg-white dark:text-black',
    'in_progress' => 'border border-black dark:border-white bg-zinc-200 text-black dark:bg-zinc-700 dark:text-white',
    'review' => 'border border-black dark:border-white bg-zinc-100 text-black dark:bg-zinc-800 dark:text-white',
    'completed' => 'border border-black dark:border-white bg-black text-white dark:bg-white dark:text-black',
    'on_hold' => 'border border-black dark:border-white bg-zinc-300 text-black dark:bg-zinc-600 dark:text-white',
    'cancelled' => 'border border-black dark:border-white bg-zinc-950 text-white dark:bg-zinc-300 dark:text-black'
];

$taskStatusLabels = [
    'backlog' => 'Backlog',
    'todo' => 'To Do',
    'in_progress' => 'In Progress',
    'review' => 'Review',
    'done' => 'Done'
];

$taskPriorityClass = static function (string $priority): string {
    return match ($priority) {
        'urgent' => 'border border-black dark:border-white bg-black text-white dark:bg-white dark:text-black',
        'high' => 'border border-black dark:border-white bg-zinc-800 text-white dark:bg-zinc-200 dark:text-black',
        'medium' => 'border border-black dark:border-white bg-zinc-200 text-black dark:bg-zinc-700 dark:text-white',
        'low' => 'border border-black dark:border-white bg-white text-black dark:bg-zinc-950 dark:text-white',
        default => 'border border-black dark:border-white bg-white text-black dark:bg-zinc-950 dark:text-white'
    };
};

$clientName = '';
$tasks = [];
$tasksByStatus = [
    'backlog' => [],
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];
$totalTasks = 0;
$completedTasks = 0;
$pendingTasks = 0;
$progress = 0;
$statusValue = 'active';
$statusLabel = 'Active';
$statusClass = $statusClassMap['active'];

if ($project) {
    $statusValue = strtolower(trim((string)($project['status'] ?? 'active')));
    if ($statusValue === 'in progress') {
        $statusValue = 'in_progress';
    }
    if ($statusValue === 'on hold') {
        $statusValue = 'on_hold';
    }
    if ($statusValue === 'done') {
        $statusValue = 'completed';
    }
    $statusLabel = $statusLabels[$statusValue] ?? ucfirst(str_replace('_', ' ', $statusValue));
    $statusClass = $statusClassMap[$statusValue] ?? $statusClassMap['active'];

    $rawClientId = (string)($project['clientId'] ?? '');
    if ($rawClientId !== '') {
        foreach ($clients as $client) {
            if ((string)($client['id'] ?? '') === $rawClientId) {
                $clientName = (string)($client['name'] ?? '');
                break;
            }
        }
    }

    foreach (($project['tasks'] ?? []) as $task) {
        if (!is_array($task)) {
            continue;
        }
        $task['status'] = normalizeTaskStatus((string)($task['status'] ?? 'todo'));
        if (!isset($tasksByStatus[$task['status']])) {
            $task['status'] = 'todo';
        }
        $task['priority'] = (string)($task['priority'] ?? 'medium');
        $tasks[] = $task;
        $tasksByStatus[$task['status']][] = $task;
    }

    $totalTasks = count($tasks);
    $completedTasks = count(array_filter($tasks, fn($t) => isTaskDone((string)($t['status'] ?? 'todo'))));
    $pendingTasks = max(0, $totalTasks - $completedTasks);
    $progress = $totalTasks > 0 ? (int)round(($completedTasks / $totalTasks) * 100) : 0;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Project Details - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#000000",
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a"
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                }
            }
        }
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-zinc-50 text-black dark:bg-zinc-900 dark:text-white font-display antialiased;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    @supports (-webkit-touch-callout: none) {
        input, textarea, select {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body class="bg-zinc-50 dark:bg-zinc-900 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-zinc-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = 'Project';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<?php if (!$project): ?>
    <main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-32 text-zinc-900 dark:text-zinc-100">
        <section class="border border-gray-200 dark:border-zinc-800 p-6 mt-2">
            <h2 class="text-xl font-black uppercase tracking-tight mb-2">Project not found</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">The project may have been deleted.</p>
            <a href="?page=projects" class="inline-block bg-black dark:bg-white text-white dark:text-black px-4 py-2 text-[10px] font-black uppercase tracking-widest touch-target">
                Back to Projects
            </a>
        </section>
    </main>
<?php else: ?>
    <main class="flex-1 overflow-y-auto no-scrollbar pb-32 text-zinc-900 dark:text-zinc-100">
        <section class="px-6 pt-6 pb-5 border-b border-zinc-200 dark:border-zinc-800">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 flex items-center justify-center font-black text-white text-base flex-shrink-0 border border-black dark:border-white" style="background-color: <?= htmlspecialchars((string)($project['color'] ?? '#000000')) ?>">
                    <?= htmlspecialchars(strtoupper(substr((string)($project['name'] ?? 'P'), 0, 1))) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-2xl font-black tracking-tight uppercase truncate"><?= htmlspecialchars((string)($project['name'] ?? 'Unnamed Project')) ?></h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="px-2 py-1 text-[10px] font-black uppercase tracking-widest <?= $statusClass ?>">
                            <?= htmlspecialchars($statusLabel) ?>
                        </span>
                        <?php if ($clientName !== ''): ?>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-zinc-400">
                                <?= htmlspecialchars($clientName) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($project['description'])): ?>
                <p class="text-sm text-gray-600 dark:text-zinc-300 mt-4 leading-relaxed">
                    <?= htmlspecialchars((string)$project['description']) ?>
                </p>
            <?php endif; ?>
            <div class="mt-5 grid grid-cols-3 gap-3">
                <a href="?page=project-form&id=<?= urlencode($projectId) ?>" class="h-11 border border-black dark:border-white text-[10px] font-black uppercase tracking-widest flex items-center justify-center touch-target">
                    Edit
                </a>
                <a href="?page=task-form&projectId=<?= urlencode($projectId) ?>" class="h-11 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest touch-target flex items-center justify-center">
                    Add Task
                </a>
                <button type="button" onclick="deleteProject()" class="h-11 border border-red-500 text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-widest touch-target">
                    Delete
                </button>
            </div>
        </section>

        <section class="px-6 py-5 grid grid-cols-2 gap-3 border-b border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950">
            <div class="border border-black dark:border-white p-3">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Total</p>
                <p id="project-total-tasks" class="text-xl font-black"><?= $totalTasks ?></p>
            </div>
            <div class="border border-black dark:border-white p-3">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Done</p>
                <p id="project-completed-tasks" class="text-xl font-black"><?= $completedTasks ?></p>
            </div>
            <div class="border border-black dark:border-white p-3">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Pending</p>
                <p id="project-pending-tasks" class="text-xl font-black"><?= $pendingTasks ?></p>
            </div>
            <div class="border border-black dark:border-white p-3">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">Progress</p>
                <p id="project-progress-value" class="text-xl font-black"><?= $progress ?>%</p>
            </div>
            <div class="col-span-2 border border-black dark:border-white p-3">
                <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400 mb-2">
                    <span>Overall Progress</span>
                    <span id="project-progress-label"><?= $progress ?>%</span>
                </div>
                <div class="h-2 bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
                    <div id="project-progress-bar" class="h-full bg-black dark:bg-white transition-all duration-300" style="width: <?= $progress ?>%"></div>
                </div>
            </div>
        </section>

        <section class="px-6 pt-5 pb-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-600 dark:text-zinc-300">Task Board</h3>
                <span class="text-[10px] font-bold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">Tap status to move</span>
            </div>
            <div class="space-y-3">
                <?php foreach ($tasksByStatus as $statusKey => $statusTasks): ?>
                    <article class="border border-black dark:border-white bg-white dark:bg-zinc-950 p-3" data-column="<?= htmlspecialchars($statusKey) ?>">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-zinc-600 dark:text-zinc-300">
                                <?= htmlspecialchars($taskStatusLabels[$statusKey] ?? ucfirst($statusKey)) ?>
                            </h4>
                            <span class="w-6 h-6 flex items-center justify-center border border-black dark:border-white bg-white dark:bg-zinc-950 text-black dark:text-white text-[9px] font-black" data-column-count="<?= htmlspecialchars($statusKey) ?>">
                                <?= count($statusTasks) ?>
                            </span>
                        </div>
                        <div class="space-y-2 min-h-[56px] kanban-list" data-status="<?= htmlspecialchars($statusKey) ?>">
                            <?php if (empty($statusTasks)): ?>
                                <p class="kanban-empty text-[10px] uppercase tracking-widest text-zinc-500 dark:text-zinc-400 py-2">
                                    No tasks
                                </p>
                            <?php endif; ?>
                            <?php foreach ($statusTasks as $task): ?>
                                <?php
                                $taskId = (string)($task['id'] ?? '');
                                $title = (string)($task['title'] ?? 'Untitled Task');
                                $description = trim((string)($task['description'] ?? ''));
                                $priority = (string)($task['priority'] ?? 'medium');
                                $dueDate = trim((string)($task['dueDate'] ?? ''));
                                $currentStatus = (string)($task['status'] ?? 'todo');
                                ?>
                                <article class="task-card border border-black dark:border-white bg-white dark:bg-zinc-950 p-3 touch-target" data-task-id="<?= htmlspecialchars($taskId) ?>">
                                    <div class="flex items-start justify-between gap-2 mb-2">
                                        <span class="px-1.5 py-0.5 text-[9px] font-black uppercase tracking-tighter <?= $taskPriorityClass($priority) ?>">
                                            <?= htmlspecialchars($priority) ?>
                                        </span>
                                        <a href="?page=view-task&id=<?= urlencode($taskId) ?>&projectId=<?= urlencode($projectId) ?>" class="text-zinc-500 dark:text-zinc-400 hover:text-black dark:hover:text-white transition-colors" onclick="event.stopPropagation()">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </a>
                                    </div>
                                    <h5 class="text-xs font-bold leading-tight mb-1"><?= htmlspecialchars($title) ?></h5>
                                    <?php if ($description !== ''): ?>
                                        <p class="text-[10px] text-zinc-600 dark:text-zinc-300 line-clamp-2 leading-relaxed mb-2"><?= htmlspecialchars($description) ?></p>
                                    <?php endif; ?>
                                    <?php if ($dueDate !== ''): ?>
                                        <p class="text-[10px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-2">
                                            Due <?= htmlspecialchars(formatDate($dueDate, 'M j')) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-2 mt-1">
                                        <label class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400" for="task-status-<?= htmlspecialchars($taskId) ?>">
                                            Status
                                        </label>
                                        <select
                                            id="task-status-<?= htmlspecialchars($taskId) ?>"
                                            class="task-status-select flex-1 text-[10px] font-bold uppercase tracking-wider border border-black dark:border-white bg-white dark:bg-zinc-950 text-black dark:text-white px-3 py-2 rounded-none focus:ring-1 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white outline-none"
                                            data-task-id="<?= htmlspecialchars($taskId) ?>"
                                            data-prev-status="<?= htmlspecialchars($currentStatus) ?>"
                                            onchange="updateTaskStatus(this)"
                                        >
                                            <?php foreach ($taskStatusLabels as $optionStatus => $optionLabel): ?>
                                                <option value="<?= htmlspecialchars($optionStatus) ?>" <?= $optionStatus === $currentStatus ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($optionLabel) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
<?php endif; ?>

<?php
$activePage = '';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 dark:bg-zinc-700 rounded-full z-40"></div>
</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const PROJECT_ID = <?= json_encode($projectId) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<?php if ($project): ?>
<script>
function getApiError(error, fallback) {
    if (error && error.response && error.response.error) {
        if (typeof error.response.error === 'string') {
            return error.response.error;
        }
        if (typeof error.response.error.message === 'string') {
            return error.response.error.message;
        }
    }
    if (error && typeof error.message === 'string' && error.message) {
        return error.message;
    }
    return fallback;
}

function updateBoardCounters() {
    document.querySelectorAll('.kanban-list').forEach((list) => {
        const status = list.getAttribute('data-status') || '';
        const countEl = document.querySelector('[data-column-count="' + status + '"]');
        const taskCount = list.querySelectorAll('.task-card').length;
        if (countEl) {
            countEl.textContent = String(taskCount);
        }

        const emptyState = list.querySelector('.kanban-empty');
        if (taskCount === 0) {
            if (!emptyState) {
                const message = document.createElement('p');
                message.className = 'kanban-empty text-[10px] uppercase tracking-widest text-zinc-500 dark:text-zinc-400 py-2';
                message.textContent = 'No tasks';
                list.appendChild(message);
            }
        } else if (emptyState) {
            emptyState.remove();
        }
    });
}

function recalculateProjectStats() {
    const total = document.querySelectorAll('.task-card').length;
    const done = document.querySelectorAll('.kanban-list[data-status="done"] .task-card').length;
    const pending = Math.max(0, total - done);
    const progress = total > 0 ? Math.round((done / total) * 100) : 0;

    const totalEl = document.getElementById('project-total-tasks');
    const doneEl = document.getElementById('project-completed-tasks');
    const pendingEl = document.getElementById('project-pending-tasks');
    const progressEl = document.getElementById('project-progress-value');
    const progressLabelEl = document.getElementById('project-progress-label');
    const progressBarEl = document.getElementById('project-progress-bar');

    if (totalEl) totalEl.textContent = String(total);
    if (doneEl) doneEl.textContent = String(done);
    if (pendingEl) pendingEl.textContent = String(pending);
    if (progressEl) progressEl.textContent = progress + '%';
    if (progressLabelEl) progressLabelEl.textContent = progress + '%';
    if (progressBarEl) progressBarEl.style.width = progress + '%';
}

function moveTaskCardToStatus(taskId, nextStatus) {
    const taskCard = document.querySelector('.task-card[data-task-id="' + taskId + '"]');
    const targetList = document.querySelector('.kanban-list[data-status="' + nextStatus + '"]');
    if (!taskCard || !targetList) {
        return;
    }
    targetList.appendChild(taskCard);
}

async function updateTaskStatus(selectEl) {
    const taskId = selectEl.getAttribute('data-task-id');
    const previousStatus = selectEl.getAttribute('data-prev-status') || 'todo';
    const nextStatus = selectEl.value;

    if (!taskId || !nextStatus || previousStatus === nextStatus) {
        return;
    }

    selectEl.disabled = true;

    try {
        await App.api.put('api/tasks.php?id=' + encodeURIComponent(taskId), {
            status: nextStatus,
            csrf_token: CSRF_TOKEN
        });

        selectEl.setAttribute('data-prev-status', nextStatus);
        moveTaskCardToStatus(taskId, nextStatus);

        document.querySelectorAll('.task-status-select[data-task-id="' + taskId + '"]').forEach((select) => {
            select.value = nextStatus;
            select.setAttribute('data-prev-status', nextStatus);
        });

        updateBoardCounters();
        recalculateProjectStats();
        if (window.Mobile && Mobile.ui) {
            Mobile.ui.showToast('Task moved to ' + nextStatus.replace('_', ' ') + '.', 'success');
        }
    } catch (error) {
        selectEl.value = previousStatus;
        if (window.Mobile && Mobile.ui) {
            Mobile.ui.showToast(getApiError(error, 'Failed to move task.'), 'error');
        }
    } finally {
        selectEl.disabled = false;
    }
}

async function deleteProject() {
    if (!confirm('Delete this project and all its tasks?')) {
        return;
    }
    try {
        const response = await App.api.delete('api/projects.php?id=' + encodeURIComponent(PROJECT_ID));
        if (!response.success) {
            throw new Error('Delete failed');
        }
        Mobile.ui.showToast('Project deleted.', 'success');
        setTimeout(function() {
            window.location.href = '?page=projects';
        }, 180);
    } catch (error) {
        Mobile.ui.showToast(getApiError(error, 'Failed to delete project.'), 'error');
    }
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
updateBoardCounters();
recalculateProjectStats();
</script>
<?php else: ?>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
<?php endif; ?>
</body>
</html>
