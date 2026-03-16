<?php
/**
 * Mobile Projects Page
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
        <p><a href="?page=login">Return to login</a></p>
    </body></html>');
}

$projects = $db->load('projects') ?? [];
$clients = $db->load('clients') ?? [];
$siteName = getSiteName() ?? 'LazyMan';

$clientMap = [];
foreach ($clients as $client) {
    $clientId = (string)($client['id'] ?? '');
    if ($clientId !== '') {
        $clientMap[$clientId] = (string)($client['name'] ?? '');
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

$statusClasses = [
    'planning' => 'bg-gray-100 text-gray-700 dark:bg-zinc-800 dark:text-zinc-300',
    'active' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
    'in_progress' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'review' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
    'completed' => 'bg-black text-white dark:bg-white dark:text-black',
    'on_hold' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
    'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
];

$projectStats = [];
$activeCount = 0;
$completedCount = 0;

foreach ($projects as $project) {
    $projectId = (string)($project['id'] ?? '');
    $status = strtolower(trim((string)($project['status'] ?? 'active')));
    if ($status === 'in progress') {
        $status = 'in_progress';
    }
    if ($status === 'on hold') {
        $status = 'on_hold';
    }
    if ($status === 'done') {
        $status = 'completed';
    }
    $tasks = is_array($project['tasks'] ?? null) ? $project['tasks'] : [];
    $totalTasks = count($tasks);
    $completedTasks = count(array_filter($tasks, fn($t) => isTaskDone((string)($t['status'] ?? 'todo'))));
    $progress = $totalTasks > 0 ? (int)round(($completedTasks / $totalTasks) * 100) : 0;
    $clientId = (string)($project['clientId'] ?? '');
    $clientName = $clientMap[$clientId] ?? '';

    $projectStats[$projectId] = [
        'totalTasks' => $totalTasks,
        'completedTasks' => $completedTasks,
        'progress' => $progress,
        'clientName' => $clientName
    ];

    if (!in_array($status, ['completed', 'cancelled'], true)) {
        $activeCount++;
    }
    if ($status === 'completed') {
        $completedCount++;
    }
}

usort($projects, static function (array $a, array $b): int {
    $aUpdated = (string)($a['updatedAt'] ?? $a['createdAt'] ?? '');
    $bUpdated = (string)($b['updatedAt'] ?? $b['createdAt'] ?? '');
    return strcmp($bUpdated, $aUpdated);
});
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Projects - <?= htmlspecialchars($siteName) ?></title>

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
            @apply bg-white text-black dark:text-white font-display antialiased;
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
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-gray-100 dark:border-zinc-800 overflow-hidden">

<?php
$title = 'Projects';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=project-form';
$rightIsLink = true;
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<div class="px-4 py-3 bg-white dark:bg-zinc-950 border-b border-gray-100 dark:border-zinc-800">
    <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 dark:text-zinc-500 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
            class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-none py-2.5 pl-10 pr-4 text-sm font-medium focus:ring-1 focus:ring-black dark:focus:ring-white placeholder:text-gray-400 dark:placeholder:text-zinc-500 transition-all"
            placeholder="Search projects..."
            type="text"
            id="project-search"
            oninput="filterProjects(this.value)"
        />
    </div>
</div>

<section class="grid grid-cols-3 gap-px bg-gray-200 dark:bg-zinc-800 border-b border-gray-100 dark:border-zinc-800">
    <div class="bg-white dark:bg-zinc-950 p-4 text-center">
        <p class="text-2xl font-black"><?= count($projects) ?></p>
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-zinc-500 mt-1">Total</p>
    </div>
    <div class="bg-white dark:bg-zinc-950 p-4 text-center">
        <p class="text-2xl font-black"><?= $activeCount ?></p>
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-zinc-500 mt-1">Active</p>
    </div>
    <div class="bg-white dark:bg-zinc-950 p-4 text-center">
        <p class="text-2xl font-black"><?= $completedCount ?></p>
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 dark:text-zinc-500 mt-1">Done</p>
    </div>
</section>

<main class="flex-1 overflow-y-auto no-scrollbar bg-gray-50/70 dark:bg-zinc-950 p-3 pb-32" id="projects-list">
    <?php if (empty($projects)): ?>
        <div class="flex flex-col items-center justify-center h-72 px-6 text-center border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900">
            <div class="w-16 h-16 bg-gray-100 dark:bg-zinc-800 rounded-full flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold">No projects yet</h3>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-1">Create your first project to get started</p>
            <a href="?page=project-form" class="inline-flex mt-5 bg-black dark:bg-white text-white dark:text-black px-4 py-2 text-[10px] font-black uppercase tracking-widest touch-target">
                Create Project
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($projects as $project): ?>
                <?php
                $projectId = (string)($project['id'] ?? '');
                $name = (string)($project['name'] ?? 'Unnamed Project');
                $description = trim((string)($project['description'] ?? ''));
                $status = (string)($project['status'] ?? 'active');
                $status = strtolower(trim($status));
                if ($status === 'in progress') {
                    $status = 'in_progress';
                }
                if ($status === 'on hold') {
                    $status = 'on_hold';
                }
                if ($status === 'done') {
                    $status = 'completed';
                }
                $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
                $statusClass = $statusClasses[$status] ?? $statusClasses['active'];
                $color = (string)($project['color'] ?? '#000000');
                $stats = $projectStats[$projectId] ?? ['totalTasks' => 0, 'completedTasks' => 0, 'progress' => 0, 'clientName' => ''];
                $searchParts = [
                    strtolower($name),
                    strtolower((string)$statusLabel),
                    strtolower((string)$stats['clientName'])
                ];
                ?>
                <article
                    class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 cursor-pointer"
                    data-project-card
                    data-project-search="<?= htmlspecialchars(implode(' ', $searchParts)) ?>"
                    onclick="openProject('<?= htmlspecialchars($projectId) ?>')"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center font-black text-white text-sm flex-shrink-0" style="background-color: <?= htmlspecialchars($color) ?>">
                            <?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-black uppercase tracking-tight truncate"><?= htmlspecialchars($name) ?></h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest <?= $statusClass ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </div>
                            <?php if ($stats['clientName'] !== ''): ?>
                                <p class="text-xs text-gray-500 dark:text-zinc-400 truncate mt-0.5">
                                    <?= htmlspecialchars((string)$stats['clientName']) ?>
                                </p>
                            <?php endif; ?>
                            <div class="mt-2 flex items-center gap-2 text-[10px] uppercase tracking-wider">
                                <span class="text-gray-400 dark:text-zinc-500">Tasks</span>
                                <span class="font-bold"><?= $stats['completedTasks'] ?>/<?= $stats['totalTasks'] ?></span>
                            </div>
                            <?php if ($stats['totalTasks'] > 0): ?>
                                <div class="mt-2">
                                    <div class="h-1.5 bg-gray-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full bg-black dark:bg-white transition-all duration-300" style="width: <?= (int)$stats['progress'] ?>%"></div>
                                    </div>
                                    <p class="text-[10px] text-gray-400 dark:text-zinc-500 mt-1 uppercase tracking-wider">
                                        <?= (int)$stats['progress'] ?>% complete
                                    </p>
                                </div>
                            <?php endif; ?>
                            <?php if ($description !== ''): ?>
                                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-2 line-clamp-2">
                                    <?= htmlspecialchars($description) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-100 dark:border-zinc-800 grid grid-cols-2 gap-2">
                        <button type="button" onclick="event.stopPropagation(); editProject('<?= htmlspecialchars($projectId) ?>')" class="h-10 border border-black dark:border-white text-[10px] font-black uppercase tracking-widest touch-target">
                            Edit
                        </button>
                        <button type="button" onclick="event.stopPropagation(); deleteProject('<?= htmlspecialchars($projectId) ?>')" class="h-10 border border-red-500 text-red-600 dark:text-red-400 text-[10px] font-black uppercase tracking-widest touch-target">
                            Delete
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

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
        let basePath = path.replace(/\/index\.php$/i, '');
        basePath = basePath.replace(/\/+$/, '');
        basePath = basePath.replace(/\/mobile$/i, '');
        window.BASE_PATH = basePath;
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
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

function filterProjects(query) {
    const value = (query || '').toLowerCase().trim();
    const cards = document.querySelectorAll('[data-project-card]');
    cards.forEach((card) => {
        const haystack = (card.getAttribute('data-project-search') || '').toLowerCase();
        card.style.display = haystack.includes(value) ? '' : 'none';
    });
}

function openProject(projectId) {
    window.location.href = '?page=view-project&id=' + encodeURIComponent(projectId);
}

function editProject(projectId) {
    window.location.href = '?page=project-form&id=' + encodeURIComponent(projectId);
}

async function deleteProject(projectId) {
    if (!confirm('Delete this project and all its tasks?')) {
        return;
    }
    try {
        const response = await App.api.delete('api/projects.php?id=' + encodeURIComponent(projectId));
        if (!response.success) {
            throw new Error('Delete failed');
        }
        Mobile.ui.showToast('Project deleted.', 'success');
        setTimeout(function() {
            window.location.reload();
        }, 180);
    } catch (error) {
        Mobile.ui.showToast(getErrorMessage(error, 'Failed to delete project.'), 'error');
    }
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
