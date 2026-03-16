<?php
/**
 * Mobile Backup Settings View
 *
 * Mobile-optimized backup settings interface for:
 * - Enabling/disabling auto backups
 * - Setting backup frequency (daily/weekly)
 * - Configuring retention policy
 * - Viewing last backup info
 * - Triggering manual backups
 *
 * ADMIN ONLY: Backup settings affect all user data
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
                    <p class="text-gray-600 mb-6">Only administrators can configure backup settings.</p>
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
<title>Backup Settings - <?= htmlspecialchars($siteName) ?></title>

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
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Auto Backup';
$leftAction = 'back';
$backUrl = '?page=data-management';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Banner -->
    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            <div class="text-sm text-green-900 dark:text-green-100">
                <p class="font-semibold mb-1">Automatic Backups</p>
                <p class="text-xs text-green-700 dark:text-green-300">Never lose data - automate your backups</p>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100 mb-4">Backup Configuration</h3>

        <div class="space-y-5">
            <!-- Enable/Disable Toggle -->
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-800 rounded-xl">
                <div class="flex-1">
                    <p class="font-bold text-gray-900 dark:text-zinc-100">Enable Auto Backup</p>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">Automatic backups for this workspace</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="auto-backup-enabled" class="sr-only peer">
                    <div class="w-14 h-8 bg-gray-300 dark:bg-zinc-700 rounded-full peer peer-checked:bg-green-600 peer-checked:after:translate-x-full peer-focus:ring-2 peer-focus:ring-green-500 after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:rounded-full after:h-7 after:w-7 after:transition-all"></div>
                </label>
            </div>

            <!-- Frequency -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-300 mb-2">
                    Backup Frequency
                </label>
                <select id="backup-frequency" class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100">
                    <option value="daily" selected>Daily (2 AM)</option>
                    <option value="weekly">Weekly (Sunday 2 AM)</option>
                </select>
            </div>

            <!-- Retention -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-300 mb-2">
                    Keep Backups For
                </label>
                <select id="backup-retention" class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100">
                    <option value="3">3 days</option>
                    <option value="7" selected>7 days</option>
                    <option value="14">14 days</option>
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                </select>
            </div>

            <!-- Save Button -->
            <button onclick="saveSettings()" id="save-btn" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Settings
            </button>
        </div>
    </div>

    <!-- Backup Info -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100 mb-4">Backup Status</h3>

        <div class="space-y-4">
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-800 rounded-xl">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mb-1">Last Auto Backup</p>
                    <p id="last-backup-time" class="font-bold text-gray-900 dark:text-zinc-100">Loading...</p>
                </div>
                <svg class="w-6 h-6 text-gray-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>

            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-zinc-800 rounded-xl">
                <div>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 mb-1">Total Auto Backups</p>
                    <p id="backup-count" class="font-bold text-gray-900 dark:text-zinc-100">Loading...</p>
                </div>
                <svg class="w-6 h-6 text-gray-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Manual Backup -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100 mb-4">Manual Backup</h3>

        <button onclick="triggerBackupNow()" id="backup-now-btn" class="w-full py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 rounded-xl font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition flex items-center justify-center gap-2 touch-target">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Backup Now
        </button>
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
        info: 'bg-blue-600',
        warning: 'bg-orange-600'
    };
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

async function loadSettings() {
    try {
        const response = await api.get('api/backup_settings.php');
        if (response.success && response.data) {
            const settings = response.data;
            document.getElementById('auto-backup-enabled').checked = Boolean(settings.enabled);
            document.getElementById('backup-frequency').value = settings.frequency || 'daily';
            document.getElementById('backup-retention').value = String(settings.retention || 7);
        }
        await loadBackupInfo();
    } catch (error) {
        console.error('Failed to load settings:', error);
        showToast('Failed to load settings', 'error');
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

async function saveSettings() {
    const settings = {
        enabled: document.getElementById('auto-backup-enabled').checked,
        frequency: document.getElementById('backup-frequency').value,
        retention: parseInt(document.getElementById('backup-retention').value)
    };

    const btn = document.getElementById('save-btn');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Saving...';

    try {
        await api.post('api/backup_settings.php', {
            enabled: settings.enabled,
            frequency: settings.frequency,
            retention: settings.retention,
            csrf_token: CSRF_TOKEN
        });
        showToast('Settings saved!', 'success');
        loadBackupInfo();
    } catch (error) {
        showToast('Failed to save: ' + error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

async function triggerBackupNow() {
    const btn = document.getElementById('backup-now-btn');
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
            loadBackupInfo();
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

// Load settings on page load
loadSettings();
</script>

</body>
</html>
