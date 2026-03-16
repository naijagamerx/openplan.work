<?php
/**
 * Mobile View Task Page - LazyMan Tools
 *
 * Displays task details with subtasks, time tracking, and meta information.
 * Design replicated from Google Stitch with Heroicons integration.
 *
 * Route: ?page=view-task&id={taskId}&projectId={projectId}
 */

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

// Get master password from session
$masterPassword = getMasterPassword();

// Check if master password is available
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

// Load data
try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=dashboard">Return to dashboard</a></p>
    </body></html>');
}

// Get task ID and project ID from URL
$taskId = $_GET['id'] ?? null;
$projectId = $_GET['projectId'] ?? null;

// Validate IDs
if (!$taskId || !$projectId) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Invalid Request</h2>
        <p>Task ID and Project ID are required.</p>
        <p><a href="?page=tasks">Return to tasks</a></p>
    </body></html>');
}

// Load projects and find the task
$projects = $db->load('projects');
$task = null;
$project = null;

foreach ($projects as $p) {
    if ($p['id'] === $projectId) {
        $project = $p;
        if (isset($p['tasks']) && is_array($p['tasks'])) {
            foreach ($p['tasks'] as $t) {
                if ($t['id'] === $taskId) {
                    $task = $t;
                    break;
                }
            }
        }
        break;
    }
}

// Handle 404 - task not found
if (!$task || !$project) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Task Not Found</h2>
        <p>The requested task could not be found.</p>
        <p><a href="?page=tasks">Return to tasks</a></p>
    </body></html>');
}

// Extract task data
$taskTitle = $task['title'] ?? 'Untitled Task';
$description = $task['description'] ?? '';
$status = $task['status'] ?? 'backlog';
$priority = $task['priority'] ?? 'medium';
$dueDate = $task['dueDate'] ?? null;
$estimatedMinutes = $task['estimatedMinutes'] ?? 0;
$actualMinutes = $task['actualMinutes'] ?? 0;
$subtasks = $task['subtasks'] ?? [];
$timeEntries = $task['timeEntries'] ?? [];
$createdAt = $task['createdAt'] ?? '';
$updatedAt = $task['updatedAt'] ?? '';

// Calculate subtask completion
$completedSubtasks = 0;
$totalSubtasks = count($subtasks);
foreach ($subtasks as $st) {
    if ($st['completed'] ?? false) {
        $completedSubtasks++;
    }
}

// Calculate actual minutes from time entries
$totalActualMinutes = 0;
foreach ($timeEntries as $entry) {
    $totalActualMinutes += $entry['minutes'] ?? 0;
}

// Calculate time percentage
$timePercentage = 0;
if ($estimatedMinutes > 0) {
    $timePercentage = round(($totalActualMinutes / $estimatedMinutes) * 100);
}

// Get site name
$siteName = getSiteName() ?? 'LazyMan';

// Helper function to format minutes
function formatViewTaskMinutes($minutes)
{
    if ($minutes < 60) {
        return $minutes . 'm';
    }
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours . 'h ' . $mins . 'm';
}

// Helper function to format date
function formatViewTaskDate($dateString, $format = 'M j, Y')
{
    if (empty($dateString)) return '';
    $timestamp = is_numeric($dateString) ? $dateString : strtotime($dateString);
    return date($format, $timestamp);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= htmlspecialchars($taskTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#000000",
                    "background-light": "#ffffff",
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                },
            },
        },
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-white text-black font-display antialiased;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .badge {
        @apply px-2 py-0.5 text-[10px] font-black uppercase tracking-widest border border-black;
    }
    .meta-card {
        @apply border border-black/10 p-4 flex flex-col gap-1;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .subtask-row {
        @apply cursor-pointer;
    }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'View Task';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto no-scrollbar pb-10 px-6">
    <!-- Badges Section -->
    <div class="flex gap-2 mb-6">
        <span class="badge bg-white text-black">
            <?= htmlspecialchars(ucfirst($priority)) ?>
        </span>
        <span class="badge bg-black text-white border-black">
            <?= htmlspecialchars(str_replace('_', ' ', ucfirst($status))) ?>
        </span>
    </div>

    <!-- Title Section -->
    <div class="mb-8">
        <h2 class="text-3xl font-black leading-none tracking-tighter uppercase mb-4">
            <?= htmlspecialchars($taskTitle) ?>
        </h2>
        <div class="flex gap-4">
            <a href="?page=task-form&id=<?= htmlspecialchars($taskId) ?>&projectId=<?= htmlspecialchars($projectId) ?>"
               class="text-[10px] font-black uppercase tracking-widest underline underline-offset-4 touch-target">
                Edit
            </a>
            <button onclick="Mobile.viewTask.confirmDelete('<?= htmlspecialchars($taskId) ?>', '<?= htmlspecialchars($projectId) ?>')"
                    class="text-[10px] font-black uppercase tracking-widest text-black/40 hover:text-black touch-target">
                Delete
            </button>
        </div>
    </div>

    <?php if (!empty($description)): ?>
    <!-- Description Section -->
    <section class="mb-10">
        <h3 class="text-[10px] font-black uppercase tracking-widest text-black/40 mb-3">Description</h3>
        <p class="text-sm font-medium leading-relaxed">
            <?= nl2br(htmlspecialchars($description)) ?>
        </p>
    </section>
    <?php endif; ?>

    <?php if (!empty($subtasks)): ?>
    <!-- Subtasks Section -->
    <section class="mb-10">
        <div class="flex justify-between items-end mb-4">
            <h3 class="text-[10px] font-black uppercase tracking-widest">Subtasks</h3>
            <span id="subtask-completion-counter" class="text-[10px] font-black">
                <?= $completedSubtasks ?>/<?= $totalSubtasks ?> Completed
            </span>
        </div>
        <div class="space-y-4">
            <?php foreach ($subtasks as $subtask): ?>
                <?php
                $isCompleted = $subtask['completed'] ?? false;
                $subtaskId = htmlspecialchars($subtask['id'] ?? '');
                $subtaskTitle = htmlspecialchars($subtask['title'] ?? '');
                $subtaskMinutes = formatViewTaskMinutes($subtask['estimatedMinutes'] ?? 0);
                ?>
                <div class="flex items-center gap-3 subtask-row"
                     data-subtask-id="<?= $subtaskId ?>"
                     data-completed="<?= $isCompleted ? '1' : '0' ?>"
                     onclick="Mobile.viewTask.toggleSubtask('<?= $subtaskId ?>')">
                    <?php if ($isCompleted): ?>
                        <!-- Heroicon: Check Circle (completed) -->
                        <svg class="w-5 h-5 text-black" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    <?php else: ?>
                        <!-- Heroicon: Circle (uncompleted) -->
                        <svg class="w-5 h-5 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    <?php endif; ?>
                    <div class="flex flex-1 justify-between items-center border-b border-black/5 pb-2">
                        <span class="text-xs font-bold uppercase tracking-tight <?= $isCompleted ? 'line-through text-gray-400' : '' ?>">
                            <?= $subtaskTitle ?>
                        </span>
                        <span class="text-[10px] font-medium text-black/40">
                            <?= $subtaskMinutes ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Time Tracking Section -->
    <section class="mb-10">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-[10px] font-black uppercase tracking-widest">Time Tracking</h3>
            <button onclick="Mobile.viewTask.openLogTimeModal()"
                    class="bg-black text-white text-[10px] font-black uppercase tracking-widest px-4 py-2 flex items-center gap-1 touch-target">
                <!-- Heroicon: Plus -->
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Log Time
            </button>
        </div>
        <div class="mb-2 flex justify-between items-end">
            <span class="text-xs font-black uppercase">
                Total Time: <?= formatViewTaskMinutes($totalActualMinutes) ?>
            </span>
            <?php if ($estimatedMinutes > 0): ?>
                <span class="text-2xl font-black tracking-tighter">
                    <?= $timePercentage ?>%
                </span>
            <?php endif; ?>
        </div>
        <div class="w-full h-4 bg-gray-100 overflow-hidden">
            <?php if ($estimatedMinutes > 0): ?>
                <div class="h-full bg-black transition-all duration-500"
                     style="width: <?= min(100, $timePercentage) ?>%"></div>
            <?php else: ?>
                <div class="h-full bg-black"></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Meta Cards Grid -->
    <section class="grid grid-cols-2 gap-4 mb-10">
        <!-- Project Card -->
        <div class="meta-card col-span-2">
            <span class="text-[9px] font-black uppercase tracking-widest text-black/40">Project</span>
            <a href="?page=view-project&id=<?= htmlspecialchars($projectId) ?>"
               class="text-xs font-black uppercase underline underline-offset-2">
                <?= htmlspecialchars($project['name'] ?? 'Unknown Project') ?>
            </a>
        </div>
        <!-- Due Date Card -->
        <div class="meta-card">
            <span class="text-[9px] font-black uppercase tracking-widest text-black/40">Due Date</span>
            <?php if ($dueDate): ?>
                <span class="text-xs font-black uppercase">
                    <?= formatViewTaskDate($dueDate, 'M j') ?>
                </span>
            <?php else: ?>
                <span class="text-xs font-black uppercase">No due date</span>
            <?php endif; ?>
        </div>
        <!-- Time Estimate Card -->
        <div class="meta-card">
            <span class="text-[9px] font-black uppercase tracking-widest text-black/40">Time Estimate</span>
            <div class="flex flex-col">
                <span class="text-[10px] font-bold">EST: <?= formatViewTaskMinutes($estimatedMinutes) ?></span>
                <span class="text-[10px] font-bold">ACT: <?= formatViewTaskMinutes($totalActualMinutes) ?></span>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="pt-6 border-t border-black/5 pb-10">
        <div class="flex flex-col gap-1">
            <span class="text-[9px] font-bold uppercase tracking-widest text-black/40">
                Created: <?= formatViewTaskDate($createdAt, 'M j, Y') ?>
            </span>
            <span class="text-[9px] font-bold uppercase tracking-widest text-black/40">
                Updated: <?= formatViewTaskDate($updatedAt, 'M j, Y') ?>
            </span>
        </div>
    </footer>
</main>

<!-- Universal Bottom Navigation -->
<?php
$activePage = 'tasks';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>

<!-- Universal Off-Canvas Menu -->
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<!-- iPhone Home Indicator -->
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<!-- App Config -->
<script>
    // Dynamic base path detection for mobile views
    (function() {
        const path = window.location.pathname;
        // Extract base path (e.g., /taskmanager or /)
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
</script>

<!-- Mobile JS -->
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

<!-- View Task Specific JS -->
<script>
Mobile.viewTask = (function() {
    'use strict';

    let _taskId = '<?= htmlspecialchars($taskId) ?>';
    let _projectId = '<?= htmlspecialchars($projectId) ?>';
    const _pendingSubtasks = new Set();

    function toast(message, type = 'info') {
        if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
            Mobile.ui.showToast(message, type);
            return;
        }
        alert(message);
    }

    function getErrorMessage(error, fallback) {
        if (error && error.response && error.response.error) {
            if (typeof error.response.error === 'string') {
                return error.response.error;
            }
            if (error.response.error && typeof error.response.error.message === 'string') {
                return error.response.error.message;
            }
        }
        if (error && typeof error.message === 'string' && error.message) {
            return error.message;
        }
        return fallback;
    }

    // Toggle subtask completion
    async function toggleSubtask(subtaskId) {
        if (!subtaskId || _pendingSubtasks.has(subtaskId)) {
            return;
        }

        const row = document.querySelector(`[data-subtask-id="${subtaskId}"]`);
        if (!row) {
            toast('Subtask not found', 'error');
            return;
        }

        const currentlyCompleted = row.getAttribute('data-completed') === '1';
        const nextState = !currentlyCompleted;
        _pendingSubtasks.add(subtaskId);
        row.style.opacity = '0.5';

        try {
            const response = await App.api.post(
                `api/tasks.php?action=subtask&projectId=${encodeURIComponent(_projectId)}&id=${encodeURIComponent(_taskId)}`,
                {
                subtaskId: subtaskId,
                completed: nextState,
                csrf_token: CSRF_TOKEN
            });

            if (!response.success) {
                throw new Error('Failed to update subtask');
            }

            row.setAttribute('data-completed', nextState ? '1' : '0');
            updateSubtaskUI(subtaskId, nextState);
            updateCompletionCounter();
            toast('Subtask updated', 'success');
        } catch (error) {
            console.error('Error toggling subtask:', error);
            toast(getErrorMessage(error, 'Failed to update subtask'), 'error');
        } finally {
            row.style.opacity = '';
            _pendingSubtasks.delete(subtaskId);
        }
    }

    // Update subtask UI after toggle
    function updateSubtaskUI(subtaskId, completed) {
        const row = document.querySelector(`[data-subtask-id="${subtaskId}"]`);
        if (!row) return;

        const icon = row.querySelector('svg');
        const title = row.querySelector('.text-xs');

        if (completed) {
            // Change to completed icon
            icon.setAttribute('fill', 'currentColor');
            icon.removeAttribute('stroke');
            icon.classList.remove('text-gray-200');
            icon.classList.add('text-black');
            icon.querySelector('path').setAttribute('d', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z');
            title.classList.add('line-through', 'text-gray-400');
        } else {
            // Change to uncompleted icon
            icon.setAttribute('fill', 'none');
            icon.classList.remove('text-black');
            icon.classList.add('text-gray-200');
            icon.setAttribute('stroke', 'currentColor');
            icon.querySelector('path').setAttribute('stroke-linecap', 'round');
            icon.querySelector('path').setAttribute('stroke-linejoin', 'round');
            icon.querySelector('path').setAttribute('stroke-width', '2');
            icon.querySelector('path').setAttribute('d', 'M21 12a9 9 0 11-18 0 9 9 0 0118 0z');
            title.classList.remove('line-through', 'text-gray-400');
        }
    }

    // Update completion counter
    function updateCompletionCounter() {
        const rows = document.querySelectorAll('.subtask-row');
        const completed = document.querySelectorAll('.subtask-row .line-through').length;
        const counter = document.getElementById('subtask-completion-counter');
        if (counter) {
            counter.textContent = `${completed}/${rows.length} Completed`;
        }
    }

    // Confirm delete
    function confirmDelete(taskId, projectId) {
        if (Mobile.ui && Mobile.ui.confirmAction) {
            Mobile.ui.confirmAction(
                'Delete this task?',
                async () => {
                    try {
                        const response = await App.api.delete(`api/tasks.php?id=${taskId}&projectId=${projectId}`);
                        if (response.success) {
                            if (Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
                                Mobile.ui.queueToast('Task deleted', 'success');
                            }
                            window.location.href = '?page=tasks';
                        } else {
                            toast('Failed to delete task', 'error');
                        }
                    } catch (error) {
                        console.error('Error deleting task:', error);
                        toast(getErrorMessage(error, 'Failed to delete task'), 'error');
                    }
                }
            );
        } else if (confirm('Delete this task?')) {
            // Fallback if confirmAction not available
            App.api.delete(`api/tasks.php?id=${taskId}&projectId=${projectId}`)
                .then(response => {
                    if (response.success) {
                        if (Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
                            Mobile.ui.queueToast('Task deleted', 'success');
                        }
                        window.location.href = '?page=tasks';
                    } else {
                        toast('Failed to delete task', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting task:', error);
                    toast(getErrorMessage(error, 'Failed to delete task'), 'error');
                });
        }
    }

    async function submitLogTime(event) {
        event.preventDefault();
        const minutesInput = document.getElementById('mobile-log-minutes');
        const descriptionInput = document.getElementById('mobile-log-description');
        const minutes = parseInt(minutesInput?.value || '0', 10);
        const description = (descriptionInput?.value || '').trim() || 'Manual time entry';

        if (!Number.isFinite(minutes) || minutes <= 0) {
            toast('Enter a valid number of minutes.', 'error');
            return;
        }

        try {
            if (Mobile.ui && typeof Mobile.ui.showLoading === 'function') {
                Mobile.ui.showLoading();
            }

            const response = await App.api.put(`api/tasks.php?id=${encodeURIComponent(_taskId)}`, {
                timeEntries: {
                    date: new Date().toISOString(),
                    minutes: minutes,
                    description: description
                },
                addTimeEntry: true,
                csrf_token: CSRF_TOKEN
            });

            if (!response.success) {
                throw new Error('Failed to log time');
            }

            if (Mobile.ui && typeof Mobile.ui.hideLoading === 'function') {
                Mobile.ui.hideLoading();
            }
            if (Mobile.ui && typeof Mobile.ui.closeModal === 'function') {
                Mobile.ui.closeModal();
            }
            if (Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
                Mobile.ui.queueToast('Time logged successfully.', 'success');
            }
            window.location.reload();
        } catch (error) {
            if (Mobile.ui && typeof Mobile.ui.hideLoading === 'function') {
                Mobile.ui.hideLoading();
            }
            toast(getErrorMessage(error, 'Failed to log time.'), 'error');
        }
    }

    // Open log time modal
    function openLogTimeModal() {
        if (!window.Mobile || !Mobile.ui || typeof Mobile.ui.openModal !== 'function') {
            toast('Modal unavailable. Please refresh and try again.', 'error');
            return;
        }

        const modalHtml = `
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-black uppercase tracking-widest">Log Time</h3>
                    <button type="button" onclick="Mobile.ui.closeModal()" class="p-2 -mr-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <form id="mobile-log-time-form" class="space-y-4">
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2" for="mobile-log-minutes">Minutes</label>
                        <input id="mobile-log-minutes" type="number" min="1" step="1" value="25"
                               class="w-full border border-gray-200 dark:border-zinc-700 px-3 py-3 bg-white dark:bg-zinc-950"
                               placeholder="25" required />
                    </div>
                    <div>
                        <label class="block text-xs font-black uppercase tracking-widest text-gray-400 mb-2" for="mobile-log-description">Description</label>
                        <input id="mobile-log-description" type="text"
                               class="w-full border border-gray-200 dark:border-zinc-700 px-3 py-3 bg-white dark:bg-zinc-950"
                               placeholder="What did you work on?" />
                    </div>
                    <button type="submit"
                            class="w-full bg-black dark:bg-white text-white dark:text-black py-3 text-[11px] font-black uppercase tracking-[0.2em]">
                        Save Time Entry
                    </button>
                </form>
            </div>
        `;

        Mobile.ui.openModal(modalHtml);
        const form = document.getElementById('mobile-log-time-form');
        if (form) {
            form.addEventListener('submit', submitLogTime);
        }
    }

    // Public API
    return {
        toggleSubtask,
        confirmDelete,
        openLogTimeModal
    };

})();
</script>

<!-- Initialize Mobile -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});
</script>

</body>
</html>
