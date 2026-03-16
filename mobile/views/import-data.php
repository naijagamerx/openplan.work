<?php
/**
 * Mobile Import/Export Data Page
 * Monochrome design for data management
 *
 * ADMIN ONLY: Import/export affects all user data
 */

require_once '../../config.php';

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
                    <p class="text-gray-600 mb-6">Only administrators can import/export data.</p>
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
<title>Import/Export - <?= htmlspecialchars($siteName) ?></title>

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
$title = 'Import/Export';
$leftAction = 'back';
$backUrl = '?page=data-management';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Banner -->
    <div class="bg-black dark:bg-white p-5 mb-6 text-white dark:text-black">
        <h1 class="text-lg font-bold mb-1">Data Management</h1>
        <p class="text-sm opacity-75">Export, import, and backup your data</p>
    </div>

    <!-- Auto Backup Settings -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Automatic Backups</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Schedule automatic backups</p>
            </div>
        </div>

        <div class="space-y-4">
            <label class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-800 cursor-pointer">
                <div>
                    <span class="font-bold text-gray-900 dark:text-zinc-100 text-sm">Enable Auto Backup</span>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Run scheduled backups</p>
                </div>
                <input type="checkbox" id="auto-backup-enabled" class="w-5 h-5 text-black rounded focus:ring-black">
            </label>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-zinc-300 mb-2">Frequency</label>
                    <select id="backup-frequency" class="w-full px-3 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100 text-sm">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-zinc-300 mb-2">Keep For</label>
                    <select id="backup-retention" class="w-full px-3 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100 text-sm">
                        <option value="3">3 days</option>
                        <option value="7" selected>7 days</option>
                        <option value="14">14 days</option>
                        <option value="30">30 days</option>
                    </select>
                </div>
            </div>

            <div class="p-4 bg-gray-50 dark:bg-zinc-800">
                <p class="text-xs text-gray-600 dark:text-zinc-400">
                    <strong>Last Backup:</strong> <span id="last-backup-time">Loading...</span>
                </p>
                <p class="text-xs text-gray-600 dark:text-zinc-400 mt-1">
                    <strong>Total:</strong> <span id="backup-count">Loading...</span>
                </p>
            </div>

            <div class="flex gap-2">
                <button onclick="saveAutoBackupSettings()" class="flex-1 py-3 bg-black dark:bg-white text-white dark:text-black font-bold text-sm hover:bg-gray-800 dark:hover:bg-gray-200 transition">
                    Save
                </button>
                <button onclick="triggerBackupNow()" class="flex-1 py-3 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 font-bold text-sm hover:bg-gray-200 dark:hover:bg-zinc-700 transition">
                    Backup Now
                </button>
            </div>
        </div>
    </div>

    <!-- Export Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Export Data</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Download your data</p>
            </div>
        </div>

        <div class="space-y-3">
            <button onclick="exportData('zip')" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                Download ZIP
            </button>
            <button onclick="exportData('json')" class="w-full py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Raw JSON
            </button>
            <label class="flex items-center gap-3 p-3 border border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 cursor-pointer">
                <input type="checkbox" id="include-music-export" checked class="w-4 h-4 text-black rounded">
                <div>
                    <span class="text-sm font-bold text-gray-900 dark:text-zinc-100">Include Pomodoro music</span>
                    <p class="text-xs text-gray-500 dark:text-zinc-400">ZIP only</p>
                </div>
            </label>
        </div>
    </div>

    <!-- Import Section -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-8-7l4 4m0 0l4-4m-4 4V3"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Import Data</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Restore from backup</p>
            </div>
        </div>

        <p class="text-xs text-red-600 dark:text-red-400 mb-4 font-bold">
            ⚠️ This will OVERWRITE all current data for your account
        </p>

        <form id="import-form" class="space-y-4">
            <div class="relative">
                <input type="file" id="import-file" name="file" accept=".json,.zip" required
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                <div class="border-2 border-dashed border-gray-300 dark:border-zinc-700 p-6 text-center">
                    <p id="file-name" class="text-sm font-bold text-gray-500 dark:text-zinc-400">Tap to select file</p>
                </div>
            </div>
            <button type="submit" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition touch-target">
                Import Data
            </button>
        </form>
    </div>

    <!-- Wipe Data Section -->
    <div class="bg-red-50 dark:bg-red-900/20 border-2 border-red-200 dark:border-red-800 p-5 mb-6">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-lg font-bold text-red-900 dark:text-red-100">Wipe All Data</h3>
                <p class="text-xs text-red-700 dark:text-red-300">⚠️ DANGER: This will permanently delete ALL data</p>
            </div>
        </div>

        <div class="space-y-3">
            <label class="flex items-center gap-3 p-3 bg-white dark:bg-zinc-900 border border-red-200 dark:border-red-800">
                <input type="checkbox" id="create-backup-before-wipe" checked class="w-4 h-4 text-red-600 rounded focus:ring-red-500">
                <div>
                    <span class="text-sm font-bold text-gray-900 dark:text-zinc-100">Create backup before wiping</span>
                </div>
            </label>
            <label class="flex items-center gap-3 p-3 bg-white dark:bg-zinc-900 border border-red-200 dark:border-red-800">
                <input type="checkbox" id="keep-music-on-wipe" checked class="w-4 h-4 text-red-600 rounded focus:ring-red-500">
                <div>
                    <span class="text-sm font-bold text-gray-900 dark:text-zinc-100">Keep Pomodoro music</span>
                </div>
            </label>

            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-zinc-300 mb-2">Confirm Password</label>
                <input type="password" id="wipe-password" class="w-full px-4 py-3 border border-red-300 dark:border-red-800 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100" placeholder="Enter your password">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-zinc-300 mb-2">Type <code class="bg-red-100 dark:bg-red-900/30 px-2 py-1 rounded">DELETE ALL DATA</code></label>
                <input type="text" id="wipe-confirmation" class="w-full px-4 py-3 border border-red-300 dark:border-red-800 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100" placeholder="DELETE ALL DATA">
            </div>

            <div id="wipe-countdown" class="hidden text-center p-4 bg-red-100 dark:bg-red-900/30 rounded-xl">
                <p class="text-red-700 dark:text-red-300 font-bold">
                    ⚠️ Wiping in <span id="countdown-timer" class="text-2xl">10</span> seconds...
                </p>
                <button type="button" onclick="cancelWipe()" class="mt-2 text-xs text-gray-700 dark:text-zinc-300 underline">
                    Cancel
                </button>
            </div>

            <button type="button" onclick="initiateWipe()" id="wipe-button" class="w-full py-3.5 bg-red-600 text-white font-bold hover:bg-red-700 transition touch-target">
                Wipe All Data
            </button>
        </div>
    </div>

</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white opacity-0 transition-opacity duration-300 pointer-events-none z-50 safe-bottom">
    <span id="toast-message"></span>
</div>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>

<script>
const CSRF_TOKEN = '<?= Auth::csrfToken() ?>';
const APP_URL = '<?= APP_URL ?>';

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
    }
};

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        info: 'bg-gray-600',
        warning: 'bg-orange-600'
    };
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadAutoBackupSettings();
    loadBackupInfo();
});

async function loadAutoBackupSettings() {
    try {
        const response = await api.get('api/backup_settings.php');
        if (response.success && response.data) {
            const settings = response.data;
            document.getElementById('auto-backup-enabled').checked = Boolean(settings.enabled);
            document.getElementById('backup-frequency').value = settings.frequency || 'daily';
            document.getElementById('backup-retention').value = String(settings.retention || 7);
        }
    } catch (error) {
        console.error('Failed to load backup settings:', error);
    }
}

async function saveAutoBackupSettings() {
    const settings = {
        enabled: document.getElementById('auto-backup-enabled').checked,
        frequency: document.getElementById('backup-frequency').value,
        retention: parseInt(document.getElementById('backup-retention').value)
    };

    try {
        await api.post('api/backup_settings.php', {
            enabled: settings.enabled,
            frequency: settings.frequency,
            retention: settings.retention,
            csrf_token: CSRF_TOKEN
        });

        showToast('Auto backup settings saved!', 'success');
        loadBackupInfo();
    } catch (error) {
        showToast('Failed to save settings: ' + error.message, 'error');
    }
}

async function loadBackupInfo() {
    try {
        const [statsResponse, settingsResponse] = await Promise.all([
            api.get('api/backup.php?action=stats'),
            api.get('api/backup_settings.php')
        ]);

        if (statsResponse.success) {
            const autoBackups = statsResponse.data.auto_backups || [];
            document.getElementById('backup-count').textContent = autoBackups.length;

            const lastAuto = autoBackups[0] || null;
            const lastAutoAt = lastAuto?.created_at || settingsResponse?.data?.last_auto_backup_at || null;
            document.getElementById('last-backup-time').textContent =
                lastAutoAt ? new Date(lastAutoAt).toLocaleString() : 'Never';
        }
    } catch (error) {
        console.error('Failed to load backup info:', error);
        document.getElementById('last-backup-time').textContent = 'Unknown';
        document.getElementById('backup-count').textContent = '?';
    }
}

async function triggerBackupNow() {
    try {
        showToast('Creating backup...', 'info');

        const response = await api.post('api/backup.php?action=create', {
            type: 'full',
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Backup created!', 'success');
            loadBackupInfo();
        } else {
            throw new Error(response.message || 'Backup failed');
        }
    } catch (error) {
        showToast('Backup failed: ' + error.message, 'error');
    }
}

async function exportData(format) {
    showToast('Preparing export...', 'info');
    const includeMusic = document.getElementById('include-music-export')?.checked ? '1' : '0';
    const downloadUrl = `${APP_URL}/api/export.php?format=${encodeURIComponent(format)}&include_music=${includeMusic}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`;
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Import file handling
document.getElementById('import-file').addEventListener('change', function(e) {
    const name = e.target.files[0]?.name || 'Tap to select file';
    document.getElementById('file-name').textContent = name;
});

document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = document.getElementById('import-file').files[0];
    if (!file) return;

    if (!confirm('This will OVERWRITE all current data. Proceed?')) return;

    showToast('Importing data...', 'info');
    const formData = new FormData();
    formData.append('file', file);
    formData.append('csrf_token', CSRF_TOKEN);

    try {
        const response = await fetch(`${APP_URL}/api/export.php?action=import`, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();

        if (result.success) {
            showToast('Data imported successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || 'Import failed');
        }
    } catch (error) {
        showToast('Import failed: ' + error.message, 'error');
    }
});

// Wipe functionality
let wipeCountdown = null;
let wipeTimer = 10;

function initiateWipe() {
    const password = document.getElementById('wipe-password').value;
    const confirmation = document.getElementById('wipe-confirmation').value;
    const createBackup = document.getElementById('create-backup-before-wipe').checked;
    const keepMusic = document.getElementById('keep-music-on-wipe').checked;

    if (!password) {
        showToast('Please enter your password', 'error');
        return;
    }

    if (confirmation !== 'DELETE ALL DATA') {
        showToast('Please type "DELETE ALL DATA" exactly', 'error');
        return;
    }

    document.getElementById('wipe-countdown').classList.remove('hidden');
    document.getElementById('wipe-button').disabled = true;
    document.getElementById('wipe-button').textContent = 'Please Wait...';
    document.getElementById('wipe-button').classList.add('opacity-50', 'cursor-not-allowed');

    wipeTimer = 10;
    updateCountdown();

    wipeCountdown = setInterval(() => {
        wipeTimer--;
        updateCountdown();

        if (wipeTimer <= 0) {
            clearInterval(wipeCountdown);
            executeWipe(password, createBackup, keepMusic);
        }
    }, 1000);
}

function updateCountdown() {
    document.getElementById('countdown-timer').textContent = wipeTimer;
}

function cancelWipe() {
    clearInterval(wipeCountdown);
    document.getElementById('wipe-countdown').classList.add('hidden');
    document.getElementById('wipe-button').disabled = false;
    document.getElementById('wipe-button').textContent = 'Wipe All Data';
    document.getElementById('wipe-button').classList.remove('opacity-50', 'cursor-not-allowed');
    wipeTimer = 10;
}

async function executeWipe(password, createBackup, keepMusic) {
    try {
        const response = await api.post('api/wipe-data.php', {
            password: password,
            confirmation: 'DELETE ALL DATA',
            create_backup: createBackup,
            keep_music: keepMusic,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('All data wiped!', 'success');
            setTimeout(() => {
                window.location.href = '?page=setup';
            }, 3000);
        } else {
            throw new Error(response.message || 'Wipe failed');
        }
    } catch (error) {
        showToast('Wipe failed: ' + error.message, 'error');
        cancelWipe();
    }
}
</script>

</body>
</html>
