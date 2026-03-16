<?php
/**
 * Mobile Data Management View
 *
 * Mobile-optimized data management hub for:
 * - Export data (JSON/ZIP)
 * - Import data (file upload)
 * - Backup management links
 * - Data recovery links
 * - Backup settings links
 *
 * ADMIN ONLY: Data export/import affects all user data
 * Uses Heroicons inline SVG, touch-friendly controls, and existing mobile patterns.
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
                    <p class="text-gray-600 mb-6">Only administrators can manage data export/import.</p>
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
        <p>Your session has expired.</p>
        <p><a href="?page=login">Log in again</a>.</p>
    </body></html>');
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Data Management - <?= htmlspecialchars($siteName) ?></title>

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
$title = 'Data Management';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'document.getElementById(\'import-file\')?.click()';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Banner -->
    <div class="bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-800 p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-gray-600 dark:text-zinc-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-gray-900 dark:text-zinc-100">
                <p class="font-semibold mb-1">Data Management Hub</p>
                <p class="text-xs text-gray-600 dark:text-zinc-400">Export, import, and manage your workspace data. All operations are encrypted and secure.</p>
            </div>
        </div>
    </div>

    <!-- Export Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Export Data</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Download your workspace backup</p>
            </div>
        </div>

        <div class="space-y-3">
            <button onclick="exportData('zip')" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                </svg>
                Download ZIP Backup
            </button>
            <button onclick="exportData('json')" class="w-full py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Raw JSON Export
            </button>
            <label class="flex items-center gap-3 p-3 border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 cursor-pointer">
                <input type="checkbox" id="include-music-export" checked class="w-4 h-4 text-black rounded">
                <div>
                    <span class="text-sm font-bold text-gray-900 dark:text-zinc-100">Include Pomodoro music</span>
                    <p class="text-xs text-gray-500 dark:text-zinc-400">Add shared audio files to ZIP</p>
                </div>
            </label>
        </div>
    </div>

    <!-- Import Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Import Data</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Restore from backup file</p>
            </div>
        </div>

        <form id="import-form" class="space-y-4">
            <div class="relative">
                <input type="file" id="import-file" name="file" accept=".json,.zip" required
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div class="border-2 border-dashed border-gray-300 dark:border-zinc-700 p-6 text-center hover:border-black dark:hover:border-white transition-colors">
                    <svg class="w-8 h-8 text-gray-400 dark:text-zinc-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    <p id="file-name" class="text-sm font-semibold text-gray-500 dark:text-zinc-400">Tap to select file</p>
                    <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">.json or .zip</p>
                </div>
            </div>
            <button type="submit" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Import Data
            </button>
        </form>
    </div>

    <!-- Backup Management Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Backups</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Manage backup history</p>
            </div>
        </div>

        <div class="space-y-3">
            <a href="?page=backup-list" class="block w-full py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition text-center touch-target">
                View All Backups
            </a>
            <button onclick="createBackupNow()" id="create-backup-btn" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create Backup Now
            </button>
        </div>
    </div>

    <!-- Data Recovery Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Data Recovery</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Recover locked collections</p>
            </div>
        </div>

        <a href="?page=data-recovery" class="block w-full py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition text-center touch-target">
            Run Diagnostic
        </a>
    </div>

    <!-- Backup Settings Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Auto Backup</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Schedule automatic backups</p>
            </div>
        </div>

        <a href="?page=backup-settings" class="block w-full py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition text-center touch-target">
            Configure Settings
        </a>
    </div>

    <!-- Danger Zone -->
    <div class="bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 p-5 mb-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-red-600 text-white flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-red-900 dark:text-red-100">Wipe All Data</h3>
                <p class="text-xs text-red-700 dark:text-red-300">Permanent deletion</p>
            </div>
        </div>

        <button onclick="confirmWipe()" class="w-full py-3.5 bg-red-600 text-white font-bold hover:bg-red-700 transition shadow-lg touch-target">
            Delete Everything
        </button>
    </div>

</div>

<!-- Toast Notification -->
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
// Global config from PHP
const CSRF_TOKEN = '<?= Auth::csrfToken() ?>';
const APP_URL = '<?= APP_URL ?>';

// API helper
const api = {
    async get(url) {
        const response = await fetch(`${APP_URL}/${url}`, {
            headers: { 'Accept': 'application/json' }
        });
        return response.json();
    },
    async post(url, data) {
        const response = await fetch(`${APP_URL}/${url}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    },
    async request(url, options) {
        const response = await fetch(`${APP_URL}/${url}`, options);
        return response.json();
    }
};

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');

    const colors = {
        success: 'bg-black',
        error: 'bg-red-600',
        info: 'bg-gray-600',
        warning: 'bg-gray-800'
    };

    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.opacity = '0';
    }, 3000);
}

// Confirmation dialog
function confirmAction(message, onConfirm) {
    if (window.confirm(message)) {
        onConfirm();
    }
}

// Export data
async function exportData(format) {
    showToast('Preparing export...', 'info');
    const includeMusic = document.getElementById('include-music-export')?.checked ? '1' : '0';
    window.location.href = `${APP_URL}/api/export.php?format=${encodeURIComponent(format)}&include_music=${includeMusic}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`;
}

// Import file handling
document.getElementById('import-file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-name').classList.add('text-black', 'dark:text-white');
    }
});

document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = document.getElementById('import-file').files[0];
    if (!file) return;

    confirmAction('⚠️ This will OVERWRITE all current data. This cannot be undone. Proceed?', async () => {
        showToast('Importing data...', 'info');

        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', CSRF_TOKEN);

        try {
            const response = await api.request('api/export.php?action=import', {
                method: 'POST',
                body: formData
            });

            if (response.success) {
                showToast('Data imported successfully!', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(response.error || 'Import failed', 'error');
            }
        } catch (error) {
            showToast('Import failed: ' + error.message, 'error');
        }
    });
});

// Create backup now
async function createBackupNow() {
    const btn = document.getElementById('create-backup-btn');
    const originalContent = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Creating...';

    try {
        const response = await api.post('api/backup.php?action=create', {
            type: 'full',
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Backup created: ${response.data.filename}`, 'success');
        } else {
            throw new Error(response.message || 'Backup failed');
        }
    } catch (error) {
        showToast('Backup failed: ' + error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

// Confirm wipe data
function confirmWipe() {
    confirmAction('⚠️ DANGER: This will PERMANENTLY DELETE ALL DATA including tasks, projects, clients, invoices, and settings. This CANNOT be undone.\n\nAre you absolutely sure?', () => {
        window.location.href = '?page=import-data';
    });
}
</script>

</body>
</html>
