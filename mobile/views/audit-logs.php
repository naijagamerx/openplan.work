<?php
/**
 * Mobile Audit Logs Page
 * Monochrome design for tracking user activities and system events
 * ADMIN ONLY: Contains sensitive system activity data
 */

require_once __DIR__ . '/../../config.php';

if (!Auth::isAdmin()) {
    http_response_code(403);
    $siteName = getSiteName() ?? 'LazyMan';
    ?>
    <!DOCTYPE html>
    <html class="light" lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
        <title>Access Denied - <?= htmlspecialchars($siteName) ?></title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-gray-100 flex justify-center">
        <div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden">
            <div class="flex-1 flex items-center justify-center p-6">
                <div class="text-center">
                    <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <h2 class="text-2xl font-bold text-gray-900 mb-3">Administrator Access Required</h2>
                    <p class="text-gray-600 mb-6">Only administrators can view audit logs.</p>
                    <a href="?page=dashboard" class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return;
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Audit Logs - <?= htmlspecialchars($siteName) ?></title>

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
    .rounded-2xl, .rounded-xl, .rounded-lg { border-radius: 0 !important; }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Audit Logs';
$leftAction = 'menu';
$rightAction = 'filter';
$rightTarget = 'document.getElementById(\'filter-search\')?.focus()';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-black dark:bg-white rounded-2xl p-4 text-white dark:text-black">
            <p class="text-xs opacity-75 mb-1">Total Events</p>
            <p id="stat-total" class="text-2xl font-bold">-</p>
        </div>
        <div class="bg-gray-100 dark:bg-zinc-900 rounded-2xl p-4 border border-gray-200 dark:border-zinc-800">
            <p class="text-xs text-gray-500 dark:text-zinc-400 mb-1">Today</p>
            <p id="stat-today" class="text-2xl font-bold text-gray-900 dark:text-zinc-100">-</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-4 mb-6">
        <div class="space-y-3">
            <input type="text" id="filter-search" placeholder="Search logs..."
                   class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100">
            <div class="grid grid-cols-2 gap-3">
                <select id="filter-event" class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100">
                    <option value="">All Events</option>
                    <option value="user.login">Login</option>
                    <option value="user.logout">Logout</option>
                    <option value="data.create">Create</option>
                    <option value="data.update">Update</option>
                    <option value="data.delete">Delete</option>
                    <option value="system.backup">Backup</option>
                    <option value="system.restore">Restore</option>
                    <option value="data.export">Export</option>
                    <option value="data.import">Import</option>
                </select>
                <input type="date" id="filter-from" class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100">
            </div>
            <button onclick="applyFilters()" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition">
                Apply Filters
            </button>
        </div>
    </div>

    <!-- Logs List -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Recent Activity</h3>
            <div class="flex gap-2">
                <button onclick="exportLogs('csv')" class="px-3 py-1.5 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 rounded-lg text-xs font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition">
                    CSV
                </button>
                <button onclick="exportLogs('json')" class="px-3 py-1.5 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 rounded-lg text-xs font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition">
                    JSON
                </button>
            </div>
        </div>
        <div id="logs-list" class="space-y-3">
            <div class="text-center py-8">
                <svg class="w-8 h-8 animate-spin text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Loading logs...</p>
            </div>
        </div>
    </div>

</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white opacity-0 transition-opacity duration-300 pointer-events-none z-50 safe-bottom">
    <span id="toast-message"></span>
</div>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>

<script src="<?= APP_URL ?>/mobile/assets/js/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>

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

function formatEventIcon(eventType) {
    const key = String(eventType || '').split('.').pop();
    const icons = {
        login: '🔑',
        logout: '🚪',
        create: '➕',
        update: '✏️',
        delete: '🗑️',
        backup: '💾',
        restore: '♻️',
        export: '📤',
        import: '📥'
    };
    return icons[key] || '📋';
}

async function loadLogs(filters = {}) {
    try {
        const params = new URLSearchParams(filters);
        params.set('action', 'list');
        const response = await api.get(`api/audit.php?${params}`);
        if (response.success) {
            const logs = response.data.logs || [];

            const listEl = document.getElementById('logs-list');
            if (logs.length === 0) {
                listEl.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 dark:text-zinc-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-zinc-400">No logs found</p>
                    </div>
                `;
                return;
            }

            listEl.innerHTML = logs.map(log => `
                <div class="bg-gray-50 dark:bg-zinc-800 rounded-xl p-4 border border-gray-200 dark:border-zinc-700">
                    <div class="flex items-start gap-3">
                        <div class="text-xl">${formatEventIcon(log.event)}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-bold text-gray-900 dark:text-zinc-100 capitalize">${(log.event || 'unknown').replace('.', ' ')}</span>
                                <span class="text-xs text-gray-500 dark:text-zinc-400">${new Date(log.timestamp).toLocaleDateString()}</span>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-zinc-300 truncate">${log.description || ''}</p>
                            <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">
                                ${log.user_name ? `By: ${log.user_name}` : ''} ${log.ip_address ? ` • IP: ${log.ip_address}` : ''}
                            </p>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Failed to load logs:', error);
        showToast('Failed to load logs', 'error');
    }
}

async function loadStats() {
    try {
        const response = await api.get('api/audit.php?action=stats');
        if (!response.success) {
            return;
        }
        const stats = response.data || {};
        const total = Number(stats.total_logs || 0);
        const byDay = stats.by_day || {};
        const todayKey = new Date().toISOString().slice(0, 10);
        const today = Number(byDay[todayKey] || 0);
        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-today').textContent = today;
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

function applyFilters() {
    const filters = {
        search: document.getElementById('filter-search').value,
        event: document.getElementById('filter-event').value,
        from: document.getElementById('filter-from').value
    };
    loadLogs(filters);
}

function exportLogs(format) {
    window.location.href = `${APP_URL}/api/audit.php?action=export&format=${format}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`;
    showToast(`Exporting ${format.toUpperCase()}...`, 'info');
}

// Load logs on page load
loadStats();
loadLogs();
</script>

</body>
</html>
