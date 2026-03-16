<?php
/**
 * Mobile Scheduler Status Page
 * Monochrome design for monitoring automated background jobs
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Scheduler Status - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#000000",
                    secondary: "#333333",
                    accent: "#666666",
                    "background-light": "#F9FAFB",
                    "background-dark": "#0A0A0A",
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"],
                },
                borderRadius: {
                    DEFAULT: "12px",
                },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    body {
        font-family: 'Inter', sans-serif;
        -webkit-tap-highlight-color: transparent;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .safe-bottom {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Scheduler Status';
$leftAction = 'back';
$backUrl = '?page=settings';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Status Indicator -->
    <div class="bg-black dark:bg-white rounded-2xl p-5 mb-6 text-white dark:text-black">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold">Scheduler</h2>
                <p class="text-sm opacity-75">Automated jobs monitor</p>
            </div>
            <div id="status-indicator" class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-gray-400 animate-pulse" id="status-dot"></span>
                <span class="text-sm font-bold" id="status-text">Loading...</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white/10 dark:bg-black/10 rounded-xl p-3">
                <p class="text-xs opacity-75 mb-1">Uptime</p>
                <p id="uptime" class="text-lg font-bold">-</p>
            </div>
            <div class="bg-white/10 dark:bg-black/10 rounded-xl p-3">
                <p class="text-xs opacity-75 mb-1">Process ID</p>
                <p id="pid" class="text-lg font-bold">-</p>
            </div>
        </div>
    </div>

    <!-- PHP Info -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">PHP Status</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Server environment</p>
            </div>
        </div>
        <div class="space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-zinc-800">
                <span class="text-sm text-gray-600 dark:text-zinc-400">PHP Path</span>
                <span class="text-sm font-bold text-gray-900 dark:text-zinc-100"><?= htmlspecialchars($phpPath) ?></span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-gray-600 dark:text-zinc-400">Version</span>
                <span class="text-sm font-bold text-gray-900 dark:text-zinc-100"><?= phpversion() ?></span>
            </div>
        </div>
    </div>

    <!-- Jobs List -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Scheduled Jobs</h3>
            <button onclick="refreshJobs()" class="px-3 py-1.5 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 rounded-lg text-xs font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition">
                Refresh
            </button>
        </div>
        <div id="jobs-list" class="space-y-3">
            <div class="text-center py-8">
                <svg class="w-8 h-8 animate-spin text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Loading jobs...</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-3">
        <button onclick="startScheduler()" class="py-3.5 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition text-sm">
            Start Scheduler
        </button>
        <button onclick="stopScheduler()" class="py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 rounded-xl font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition text-sm">
            Stop Scheduler
        </button>
    </div>

</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white opacity-0 transition-opacity duration-300 pointer-events-none z-50 safe-bottom">
    <span id="toast-message"></span>
</div>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>

<script>
const CSRF_TOKEN = '<?= Auth::csrfToken() ?>';
const APP_URL = '<?= APP_URL ?>';

const api = {
    async get(url) {
        const response = await fetch(`${APP_URL}/${url}`, { headers: { 'Accept': 'application/json' } });
        return response.json();
    },
    async post(url, data) {
        const response = await fetch(`${APP_URL}/${url}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    }
};

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const colors = { success: 'bg-black', error: 'bg-red-600', info: 'bg-gray-600' };
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

async function loadStatus() {
    try {
        const response = await api.get('api/scheduler_status.php');
        if (response.success) {
            const status = response.data;
            const isRunning = status.scheduler?.status === 'running' || status.process?.pid;

            document.getElementById('status-dot').className = `w-3 h-3 rounded-full ${isRunning ? 'bg-white dark:bg-black' : 'bg-gray-400'}`;
            document.getElementById('status-text').textContent = isRunning ? 'Running' : 'Stopped';
            document.getElementById('uptime').textContent = status.uptime || '-';
            document.getElementById('pid').textContent = status.process?.pid || '-';
        }
    } catch (error) {
        console.error('Failed to load status:', error);
    }
}

async function refreshJobs() {
    showToast('Refreshing...', 'info');
    loadStatus();
}

async function startScheduler() {
    try {
        const response = await api.post('api/cron.php', { action: 'start', csrf_token: CSRF_TOKEN });
        if (response.success) {
            showToast('Scheduler started', 'success');
            loadStatus();
        } else {
            showToast(response.error || 'Failed to start', 'error');
        }
    } catch (error) {
        showToast('Failed to start scheduler', 'error');
    }
}

async function stopScheduler() {
    try {
        const response = await api.post('api/cron.php', { action: 'stop', csrf_token: CSRF_TOKEN });
        if (response.success) {
            showToast('Scheduler stopped', 'success');
            loadStatus();
        } else {
            showToast(response.error || 'Failed to stop', 'error');
        }
    } catch (error) {
        showToast('Failed to stop scheduler', 'error');
    }
}

// Load status on page load
loadStatus();
// Refresh every 10 seconds
setInterval(loadStatus, 10000);
</script>

</body>
</html>
