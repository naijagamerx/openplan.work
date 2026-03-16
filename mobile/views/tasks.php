<?php
/**
 * Mobile Tasks Page - LazyMan Tools
 *
 * Modern mobile task list matching Stitch design exactly.
 * Uses Heroicons inline SVG (converted from Material Symbols).
 * Integrates with existing LazyMan backend data.
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
        <p><a href="?page=login">Return to login</a></p>
    </body></html>');
}

// Get all data collections
$projects = $db->load('projects');

// Get all tasks from projects and flatten them
$allTasks = [];
foreach ($projects as $project) {
    if (isset($project['tasks']) && is_array($project['tasks'])) {
        foreach ($project['tasks'] as $task) {
            $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
            $task['projectName'] = $project['name'];
            $task['projectColor'] = $project['color'] ?? '#000000';
            $task['projectId'] = $project['id'];
            $allTasks[] = $task;
        }
    }
}

// Sort tasks: incomplete first, then by priority, then by due date
usort($allTasks, function($a, $b) {
    $aCompleted = isTaskRecordDone($a);
    $bCompleted = isTaskRecordDone($b);
    if ($aCompleted && !$bCompleted) return 1;
    if (!$aCompleted && $bCompleted) return -1;

    if (!$aCompleted && !$bCompleted) {
        $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        $aPriority = $priorityOrder[$a['priority'] ?? 'medium'] ?? 2;
        $bPriority = $priorityOrder[$b['priority'] ?? 'medium'] ?? 2;
        if ($aPriority !== $bPriority) return $aPriority - $bPriority;
    }

    $aDue = $a['dueDate'] ?? '9999-12-31';
    $bDue = $b['dueDate'] ?? '9999-12-31';
    return strtotime($aDue) - strtotime($bDue);
});

// Get user name and site name
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Tasks - <?= htmlspecialchars($siteName) ?></title>

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
    .task-row {
        @apply border-b border-black/5 py-5 px-6 flex items-start gap-4 hover:bg-gray-50 transition-colors cursor-pointer;
    }
</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Tasks';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=task-form';
$rightIsLink = true;
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<!-- Search and Filters (below header) -->
<div class="px-4 py-3 bg-white border-b border-gray-100">
    <div class="relative mb-3">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
            class="w-full bg-gray-50 border-none rounded-xl py-2.5 pl-10 pr-4 text-xs font-medium focus:ring-1 focus:ring-black placeholder:text-gray-400 transition-all"
            placeholder="Find tasks..."
            type="text"
            id="task-search"
        />
    </div>

    <div class="flex gap-4">
        <button onclick="Mobile.ui.toggleFilterOptions()" class="text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 hover:underline underline-offset-4 touch-target">
            Project
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
        <button onclick="Mobile.ui.toggleFilterOptions()" class="text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 hover:underline underline-offset-4 touch-target">
            Status
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
        <button onclick="Mobile.ui.toggleFilterOptions()" class="text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 hover:underline underline-offset-4 touch-target">
            Priority
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.5 15.75l7.5-7.5 7.5 7.5"/>
            </svg>
        </button>
    </div>
</div>

<!-- Task List -->
<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <?php if (empty($allTasks)): ?>
        <!-- Empty State -->
        <div class="text-center py-16 px-6">
            <p class="text-gray-500 text-sm">No tasks yet. Tap + to create one!</p>
        </div>
    <?php else: ?>
        <?php foreach ($allTasks as $task): ?>
            <?php
            $isCompleted = isTaskRecordDone($task);
            $priority = $task['priority'] ?? 'medium';
            $dueDate = $task['dueDate'] ?? null;
            $taskTitle = htmlspecialchars($task['title']);
            $projectName = htmlspecialchars($task['projectName']);
            $taskId = htmlspecialchars($task['id']);
            $projectId = htmlspecialchars($task['projectId']);

            // Priority colors
            $priorityColors = [
                'urgent' => ['badge' => 'text-black', 'dot' => 'bg-black'],
                'high' => ['badge' => 'text-black', 'dot' => 'bg-black'],
                'medium' => ['badge' => 'text-gray-400', 'dot' => 'bg-gray-400'],
                'low' => ['badge' => 'text-gray-200', 'dot' => 'bg-gray-200'],
            ];
            $priorityColor = $priorityColors[$priority] ?? $priorityColors['medium'];

            // Calculate due date status
            $isOverdue = $dueDate && strtotime($dueDate) < time() && !$isCompleted;
            $isDueToday = $dueDate && date('Y-m-d', strtotime($dueDate)) === date('Y-m-d');
            $daysUntil = $dueDate ? floor((strtotime($dueDate) - time()) / 86400) : null;

            // Generate status badge HTML
            $statusBadge = '';
            if ($isOverdue) {
                $statusBadge = '<span class="text-[10px] font-black uppercase tracking-widest text-black bg-black text-white px-1">Overdue</span>';
            } elseif ($isDueToday) {
                $statusBadge = '<span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Due Today</span>';
            } elseif ($daysUntil !== null && $daysUntil > 0) {
                if ($daysUntil === 1) {
                    $statusBadge = '<span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Starts Tomorrow</span>';
                } elseif ($daysUntil < 7) {
                    $statusBadge = '<span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Starts ' . $daysUntil . 'd</span>';
                } else {
                    $statusBadge = '<span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Starts ' . date('M j', strtotime($dueDate)) . '</span>';
                }
            } else {
                $statusBadge = '<span class="text-[10px] font-black uppercase tracking-widest text-gray-400">No Deadline</span>';
            }

            // Checkbox HTML - matching Material Symbols look
            if ($isCompleted) {
                // check_circle with FILL 1 - filled black circle with white checkmark
                $checkbox = '<svg class="w-6 h-6 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            } else {
                // radio_button_unchecked - outline circle
                $checkbox = '<svg class="w-6 h-6 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            }

            $titleClass = $isCompleted ? 'line-through text-gray-400' : 'text-black';
            ?>
            <div class="task-row" onclick="Mobile.tasks.viewTask('<?= $taskId ?>', '<?= $projectId ?>')">
                <div class="mt-0.5">
                    <?= $checkbox ?>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-tight <?= $titleClass ?>"><?= $taskTitle ?></h2>
                        <div class="flex items-center gap-1">
                            <span class="text-[9px] font-bold uppercase tracking-tighter <?= $priorityColor['badge'] ?>"><?= htmlspecialchars(ucfirst($priority)) ?></span>
                            <div class="size-2 rounded-full <?= $priorityColor['dot'] ?>"></div>
                        </div>
                    </div>
                    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
                        <span class="text-[10px] font-black uppercase tracking-widest text-black underline underline-offset-2">
                            <?= $projectName ?>
                        </span>
                        <?= $statusBadge ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<!-- Floating Action Button -->
<a href="?page=task-form" class="absolute bottom-28 right-6 size-16 bg-black text-white rounded-full flex items-center justify-center shadow-2xl hover:scale-105 active:scale-95 transition-transform z-40 touch-target">
    <!-- Heroicon: Plus (add) -->
    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
</a>

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
        let basePath = path.replace(/\/index\.php$/i, '');
        basePath = basePath.replace(/\/+$/, '');
        basePath = basePath.replace(/\/mobile$/i, '');
        window.BASE_PATH = basePath;
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
</script>

<!-- Mobile JS moved to head -->

<!-- Tasks-Specific JS -->
<script>
if (!window.Mobile) {
    window.Mobile = {};
}
if (!Mobile.tasks) {
    Mobile.tasks = {};
}
Mobile.tasks.viewTask = function(taskId, projectId) {
    window.location.href = `?page=view-task&id=${taskId}&projectId=${projectId}`;
};

// Search functionality
document.getElementById('task-search').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase().trim();
    const taskRows = document.querySelectorAll('.task-row');

    taskRows.forEach(row => {
        if (!query) {
            row.style.display = '';
            return;
        }

        const title = row.querySelector('h2')?.textContent.toLowerCase() || '';
        const project = row.querySelector('.underline')?.textContent.toLowerCase() || '';

        const matches = title.includes(query) || project.includes(query);
        row.style.display = matches ? '' : 'none';
    });
});
</script>

<!-- Initialize Mobile -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});
</script>

</body>
</html>
