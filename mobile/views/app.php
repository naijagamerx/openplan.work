<?php
/**
 * Mobile App Hub Page - LazyMan Tools
 *
 * Central mobile launcher for key modules and quick actions.
 * Keeps shared header and footer consistency via partials.
 */

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

// Get master password from session
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
        <p><a href="?page=dashboard">Return to dashboard</a></p>
    </body></html>');
}

$projects = $db->load('projects') ?? [];
$notes = $db->load('notes') ?? [];
$habits = $db->load('habits') ?? [];

$allTasks = [];
foreach ($projects as $project) {
    if (!empty($project['tasks']) && is_array($project['tasks'])) {
        foreach ($project['tasks'] as $task) {
            $allTasks[] = $task;
        }
    }
}

$pendingTasks = count(array_filter($allTasks, fn($task) => empty($task['completedAt'])));
$completedTasks = count(array_filter($allTasks, fn($task) => !empty($task['completedAt'])));
$pinnedNotes = count(array_filter($notes, fn($note) => !empty($note['isPinned'])));
$activeHabits = count($habits);
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>App - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#000000",
                    "background-light": "#ffffff"
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                }
            }
        }
    };
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
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'App';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'openQuickCreate()';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-4 pt-5 pb-32 space-y-4">
    <section class="bg-black text-white rounded-2xl p-5">
        <p class="text-[10px] uppercase tracking-[0.2em] opacity-70 mb-2">Mobile App Hub</p>
        <h2 class="text-xl font-bold tracking-tight">Everything in one place</h2>
        <p class="text-xs mt-2 opacity-80">Launch modules fast and create items without leaving mobile flow.</p>
    </section>

    <section class="grid grid-cols-2 gap-3">
        <div class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Pending Tasks</p>
            <p class="text-2xl font-bold mt-1"><?= $pendingTasks ?></p>
        </div>
        <div class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Completed</p>
            <p class="text-2xl font-bold mt-1"><?= $completedTasks ?></p>
        </div>
        <div class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Pinned Notes</p>
            <p class="text-2xl font-bold mt-1"><?= $pinnedNotes ?></p>
        </div>
        <div class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-400">Habits</p>
            <p class="text-2xl font-bold mt-1"><?= $activeHabits ?></p>
        </div>
    </section>

    <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-xs font-bold uppercase tracking-[0.18em]">Quick Create</h3>
            <button onclick="openQuickCreate()" class="text-[10px] font-bold uppercase tracking-widest text-gray-500 touch-target">Open</button>
        </div>
        <div class="grid grid-cols-2 gap-2">
            <button onclick="Mobile.ui.openTaskModal()" class="border border-gray-200 dark:border-zinc-700 p-3 text-left hover:bg-gray-50 dark:hover:bg-zinc-800">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Task</p>
                <p class="text-sm font-bold mt-1">New Task</p>
            </button>
            <a href="?page=note-form" class="border border-gray-200 dark:border-zinc-700 p-3 hover:bg-gray-50 dark:hover:bg-zinc-800">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Notes</p>
                <p class="text-sm font-bold mt-1">New Note</p>
            </a>
            <a href="?page=habits" class="border border-gray-200 dark:border-zinc-700 p-3 hover:bg-gray-50 dark:hover:bg-zinc-800">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Habits</p>
                <p class="text-sm font-bold mt-1">Track Habit</p>
            </a>
            <a href="?page=ai-assistant" class="border border-gray-200 dark:border-zinc-700 p-3 hover:bg-gray-50 dark:hover:bg-zinc-800">
                <p class="text-[10px] uppercase tracking-widest text-gray-500">AI</p>
                <p class="text-sm font-bold mt-1">Open Assistant</p>
            </a>
        </div>
    </section>

    <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
        <h3 class="text-xs font-bold uppercase tracking-[0.18em] mb-3">Modules</h3>
        <div class="grid grid-cols-3 gap-2">
            <a href="?page=dashboard" class="bg-gray-50 dark:bg-zinc-800 p-3 text-center text-[11px] font-semibold">Dashboard</a>
            <a href="?page=tasks" class="bg-gray-50 dark:bg-zinc-800 p-3 text-center text-[11px] font-semibold">Tasks</a>
            <a href="?page=notes" class="bg-gray-50 dark:bg-zinc-800 p-3 text-center text-[11px] font-semibold">Notes</a>
            <a href="?page=projects" class="bg-gray-50 dark:bg-zinc-800 p-3 text-center text-[11px] font-semibold">Projects</a>
            <a href="?page=calendar" class="bg-gray-50 dark:bg-zinc-800 p-3 text-center text-[11px] font-semibold">Calendar</a>
            <a href="?page=settings" class="bg-gray-50 dark:bg-zinc-800 p-3 text-center text-[11px] font-semibold">Settings</a>
        </div>
    </section>
</main>

<?php
$activePage = 'app';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<!-- iPhone Home Indicator -->
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<div id="quickCreateModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-end justify-center p-4" onclick="closeQuickCreate()">
    <div class="bg-white rounded-t-3xl w-full max-w-[420px] p-5" onclick="event.stopPropagation()">
        <div class="w-10 h-1 bg-gray-300 rounded-full mx-auto mb-4"></div>
        <h3 class="text-base font-bold mb-4">Quick Create</h3>
        <div class="space-y-2">
            <button onclick="closeQuickCreate(); Mobile.ui.openTaskModal();" class="w-full text-left px-4 py-3 border border-gray-200 rounded-xl font-medium">New Task</button>
            <a href="?page=note-form" class="block w-full px-4 py-3 border border-gray-200 rounded-xl font-medium">New Note</a>
            <a href="?page=habits" class="block w-full px-4 py-3 border border-gray-200 rounded-xl font-medium">Open Habits</a>
            <a href="?page=ai-assistant" class="block w-full px-4 py-3 border border-gray-200 rounded-xl font-medium">AI Assistant</a>
        </div>
        <button onclick="closeQuickCreate()" class="w-full mt-4 py-3 border border-gray-300 rounded-xl text-sm font-medium">Close</button>
    </div>
</div>

<script>
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

    function openQuickCreate() {
        const modal = document.getElementById('quickCreateModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeQuickCreate() {
        const modal = document.getElementById('quickCreateModal');
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
</script>
<!-- Mobile JS moved to head -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});
</script>
</body>
</html>
