<?php
/**
 * Mobile Task Form (Create/Edit)
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
        <p><a href="?page=tasks">Back to tasks</a></p>
    </body></html>');
}

$taskId = trim((string)($_GET['id'] ?? ''));
$queryProjectId = trim((string)($_GET['projectId'] ?? $_GET['project_id'] ?? ''));
$projects = $db->load('projects') ?? [];

$task = null;
$projectId = $queryProjectId;

if ($taskId !== '') {
    foreach ($projects as $project) {
        foreach (($project['tasks'] ?? []) as $candidate) {
            if ((string)($candidate['id'] ?? '') === $taskId) {
                $task = $candidate;
                $projectId = (string)($project['id'] ?? '');
                break 2;
            }
        }
    }
}

$isEdit = is_array($task);
$pageTitle = $isEdit ? 'Edit Task' : 'New Task';
$siteName = getSiteName() ?? 'LazyMan';

$field = static function (string $key, string $default = '') use ($task): string {
    if (!$task) {
        return $default;
    }
    $value = $task[$key] ?? $default;
    if (!is_scalar($value)) {
        return $default;
    }
    return (string)$value;
};

$priority = strtolower($field('priority', 'medium'));
if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
    $priority = 'medium';
}

$status = normalizeTaskStatus($field('status', 'todo'));
$subtasks = is_array($task['subtasks'] ?? null) ? $task['subtasks'] : [];
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

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
                    primary: "#000000",
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a"
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"]
                }
            }
        }
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-zinc-50 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
        }
        input, select, textarea {
            @apply w-full bg-white dark:bg-zinc-900 border border-black dark:border-white rounded-none px-4 py-3 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white outline-none transition-all placeholder:text-zinc-400 dark:placeholder:text-zinc-500 font-medium;
        }
        label {
            @apply block text-[10px] font-black uppercase tracking-[0.2em] mb-2 text-black dark:text-white;
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
    .toggle-chip input:checked + div {
        @apply bg-black dark:bg-white text-white dark:text-black;
    }
    @supports (-webkit-touch-callout: none) {
        input, textarea, select {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-zinc-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = $pageTitle;
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-[280px] text-zinc-900 dark:text-zinc-100">
    <form id="task-form" class="space-y-6">
        <input type="hidden" name="id" value="<?= htmlspecialchars($taskId) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label for="task-title">Task Title</label>
            <input id="task-title" name="title" type="text" required placeholder="What needs to be done?" value="<?= htmlspecialchars($field('title')) ?>" />
        </div>

        <div>
            <label for="task-project">Project</label>
            <div class="relative">
                <select id="task-project" name="projectId" class="appearance-none">
                    <option value="">Inbox</option>
                    <?php foreach ($projects as $project): ?>
                        <?php $pid = (string)($project['id'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($pid) ?>" <?= $pid === $projectId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($project['name'] ?? 'Untitled')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="task-status">Status</label>
                <div class="relative">
                    <select id="task-status" name="status" class="appearance-none">
                        <option value="backlog" <?= $status === 'backlog' ? 'selected' : '' ?>>Backlog</option>
                        <option value="todo" <?= $status === 'todo' ? 'selected' : '' ?>>To Do</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="review" <?= $status === 'review' ? 'selected' : '' ?>>Review</option>
                        <option value="done" <?= $status === 'done' ? 'selected' : '' ?>>Done</option>
                    </select>
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <div>
                <label>Priority</label>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach (['low', 'medium', 'high', 'urgent'] as $value): ?>
                        <label class="toggle-chip cursor-pointer m-0">
                            <input class="hidden" name="priority" type="radio" value="<?= $value ?>" <?= $priority === $value ? 'checked' : '' ?>/>
                            <div class="border border-black dark:border-white py-2 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">
                                <?= htmlspecialchars($value) ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="task-start-date">Start Date</label>
                <input id="task-start-date" name="startDate" type="date" value="<?= htmlspecialchars($field('startDate')) ?>" />
            </div>
            <div>
                <label for="task-due-date">Due Date</label>
                <input id="task-due-date" name="dueDate" type="date" value="<?= htmlspecialchars($field('dueDate')) ?>" />
            </div>
        </div>

        <div>
            <label for="task-estimate">Estimated Minutes</label>
            <input id="task-estimate" name="estimatedMinutes" type="number" min="0" step="1" value="<?= htmlspecialchars($field('estimatedMinutes', '60')) ?>" placeholder="60" />
        </div>

        <div>
            <label for="task-description">Description</label>
            <textarea id="task-description" name="description" rows="5" placeholder="Add details about this task..."><?= htmlspecialchars($field('description')) ?></textarea>
        </div>

        <section class="border border-black dark:border-white p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Subtasks</h3>
                <button type="button" onclick="addSubtaskRow()" class="text-[10px] font-black uppercase tracking-widest underline underline-offset-2 touch-target">
                    Add
                </button>
            </div>
            <div id="subtasks-list" class="space-y-2">
                <?php if (empty($subtasks)): ?>
                    <p id="subtasks-empty" class="text-[10px] text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">No subtasks yet</p>
                <?php else: ?>
                    <?php foreach ($subtasks as $subtask): ?>
                        <?php
                        $subtaskTitle = is_scalar($subtask['title'] ?? null) ? (string)$subtask['title'] : '';
                        $subtaskMinutes = (int)($subtask['estimatedMinutes'] ?? 0);
                        ?>
                        <div class="subtask-row grid grid-cols-[1fr_auto_auto] gap-2">
                            <input type="text" class="subtask-title" placeholder="Subtask title" value="<?= htmlspecialchars($subtaskTitle) ?>" />
                            <input type="number" class="subtask-minutes w-20" min="0" step="1" placeholder="Min" value="<?= $subtaskMinutes ?>" />
                            <button type="button" onclick="removeSubtaskRow(this)" class="border border-black dark:border-white px-3 text-[10px] font-black uppercase tracking-widest touch-target">Del</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </form>
</main>

<div class="fixed left-1/2 -translate-x-1/2 bottom-[84px] w-full max-w-[420px] bg-white dark:bg-zinc-950 border-t border-zinc-100 dark:border-zinc-800 p-6 z-30">
    <div class="flex flex-col gap-3">
        <button id="save-task-btn" type="button" onclick="saveTask()" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.3em] hover:opacity-90 transition-opacity touch-target">
            <?= $isEdit ? 'Save Task' : 'Create Task' ?>
        </button>
        <a href="?page=tasks" class="w-full py-3 text-center text-zinc-400 dark:text-zinc-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-black dark:hover:text-white transition-colors touch-target">
            Cancel
        </a>
    </div>
    <div class="mt-4 mx-auto w-32 h-1 bg-zinc-100 dark:bg-zinc-800 rounded-full"></div>
</div>

<?php
$activePage = 'tasks';
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
    const EDIT_TASK_ID = <?= json_encode($taskId) ?>;
    const CURRENT_PROJECT_ID = <?= json_encode($projectId) ?>;
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
function getErrorMessage(error, fallback) {
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

function removeSubtaskRow(button) {
    const row = button.closest('.subtask-row');
    if (row) {
        row.remove();
    }
    ensureSubtaskEmptyState();
}

function ensureSubtaskEmptyState() {
    const list = document.getElementById('subtasks-list');
    if (!list) return;
    const rowCount = list.querySelectorAll('.subtask-row').length;
    let empty = document.getElementById('subtasks-empty');
    if (rowCount === 0 && !empty) {
        empty = document.createElement('p');
        empty.id = 'subtasks-empty';
        empty.className = 'text-[10px] text-zinc-500 dark:text-zinc-400 uppercase tracking-widest';
        empty.textContent = 'No subtasks yet';
        list.appendChild(empty);
    }
    if (rowCount > 0 && empty) {
        empty.remove();
    }
}

function addSubtaskRow(title = '', minutes = '') {
    const list = document.getElementById('subtasks-list');
    if (!list) return;

    const row = document.createElement('div');
    row.className = 'subtask-row grid grid-cols-[1fr_auto_auto] gap-2';
    row.innerHTML = `
        <input type="text" class="subtask-title" placeholder="Subtask title" value="${String(title).replace(/"/g, '&quot;')}" />
        <input type="number" class="subtask-minutes w-20" min="0" step="1" placeholder="Min" value="${String(minutes).replace(/"/g, '&quot;')}" />
        <button type="button" onclick="removeSubtaskRow(this)" class="border border-black dark:border-white px-3 text-[10px] font-black uppercase tracking-widest touch-target">Del</button>
    `;
    list.appendChild(row);
    ensureSubtaskEmptyState();
}

function collectSubtasks() {
    const rows = document.querySelectorAll('#subtasks-list .subtask-row');
    const subtasks = [];
    rows.forEach((row) => {
        const title = (row.querySelector('.subtask-title')?.value || '').trim();
        const minutesRaw = row.querySelector('.subtask-minutes')?.value || '0';
        const estimatedMinutes = Number.parseInt(minutesRaw, 10);
        if (title !== '') {
            subtasks.push({
                title: title,
                estimatedMinutes: Number.isFinite(estimatedMinutes) ? Math.max(0, estimatedMinutes) : 0
            });
        }
    });
    return subtasks;
}

async function saveTask() {
    const form = document.getElementById('task-form');
    const saveBtn = document.getElementById('save-task-btn');
    if (!form || !saveBtn) {
        return;
    }

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    payload.title = (payload.title || '').trim();
    payload.description = (payload.description || '').trim();
    payload.projectId = (payload.projectId || '').trim();
    payload.priority = (payload.priority || 'medium').trim();
    payload.status = (payload.status || 'todo').trim();
    payload.startDate = (payload.startDate || '').trim();
    payload.dueDate = (payload.dueDate || '').trim();
    payload.estimatedMinutes = Number.parseInt(payload.estimatedMinutes || '0', 10);
    payload.estimatedMinutes = Number.isFinite(payload.estimatedMinutes) ? Math.max(0, payload.estimatedMinutes) : 0;
    payload.subtasks = collectSubtasks();
    payload.csrf_token = CSRF_TOKEN;

    if (!payload.title) {
        Mobile.ui.showToast('Task title is required.', 'error');
        return;
    }

    saveBtn.disabled = true;
    const initialLabel = saveBtn.textContent;
    saveBtn.textContent = IS_EDIT ? 'SAVING...' : 'CREATING...';

    try {
        let response;
        if (IS_EDIT && EDIT_TASK_ID) {
            response = await App.api.put('api/tasks.php?id=' + encodeURIComponent(EDIT_TASK_ID), payload);
        } else {
            response = await App.api.post('api/tasks.php', payload);
        }

        if (!response.success || !response.data) {
            throw new Error('Failed to save task');
        }

        const nextTaskId = String(response.data.id || EDIT_TASK_ID || '');
        const nextProjectId = String(payload.projectId || response.data.projectId || CURRENT_PROJECT_ID || '');

        Mobile.ui.showToast(IS_EDIT ? 'Task updated.' : 'Task created.', 'success');

        setTimeout(function() {
            if (nextTaskId && nextProjectId) {
                window.location.href = '?page=view-task&id=' + encodeURIComponent(nextTaskId) + '&projectId=' + encodeURIComponent(nextProjectId);
                return;
            }
            window.location.href = '?page=tasks';
        }, 180);
    } catch (error) {
        Mobile.ui.showToast(getErrorMessage(error, 'Failed to save task.'), 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = initialLabel;
    }
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
