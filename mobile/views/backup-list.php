<?php
/**
 * Mobile Backup List View
 *
 * Mobile-optimized backup management interface for:
 * - Viewing all backups
 * - Creating new backups
 * - Restoring from backups
 * - Downloading backup files
 * - Deleting old backups
 *
 * ADMIN ONLY: Backup management affects all user data
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
                    <p class="text-gray-600 mb-6">Only administrators can manage backups.</p>
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
<title>Backup List - <?= htmlspecialchars($siteName) ?></title>

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
$title = 'Backups';
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
                <p class="font-semibold mb-1">Backup History</p>
                <p class="text-xs text-green-700 dark:text-green-300">All backups are encrypted with AES-256-GCM</p>
            </div>
        </div>
    </div>

    <!-- Create Backup Button -->
    <button onclick="createBackupNow()" id="create-backup-btn" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target mb-6">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Create Backup Now
    </button>

    <!-- Stats -->
    <div id="backup-stats" class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-gray-50 dark:bg-zinc-900 rounded-xl p-4 border border-gray-200 dark:border-zinc-800">
            <p class="text-xs text-gray-500 dark:text-zinc-400 mb-1">Total Backups</p>
            <p id="total-count" class="text-2xl font-bold text-gray-900 dark:text-zinc-100">-</p>
        </div>
        <div class="bg-gray-50 dark:bg-zinc-900 rounded-xl p-4 border border-gray-200 dark:border-zinc-800">
            <p class="text-xs text-gray-500 dark:text-zinc-400 mb-1">Latest</p>
            <p id="latest-backup" class="text-sm font-bold text-gray-900 dark:text-zinc-100">-</p>
        </div>
    </div>

    <!-- Backups List -->
    <div class="space-y-3" id="backups-list">
        <!-- Loading state -->
        <div class="text-center py-8">
            <svg class="w-8 h-8 animate-spin text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm text-gray-500 dark:text-zinc-400 mt-3">Loading backups...</p>
        </div>
    </div>

</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white opacity-0 transition-opacity duration-300 pointer-events-none z-50 safe-bottom">
    <span id="toast-message"></span>
</div>

<!-- Confirmation Modal -->
<div id="confirm-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal()"></div>
    <div class="absolute bottom-0 left-0 right-0 bg-white dark:bg-zinc-950 rounded-t-2xl p-6 transform transition-transform duration-300 translate-y-full" id="modal-content">
        <h3 id="modal-title" class="text-xl font-bold text-gray-900 dark:text-zinc-100 mb-2"></h3>
        <p id="modal-message" class="text-sm text-gray-600 dark:text-zinc-400 mb-6"></p>
        <div class="flex gap-3">
            <button onclick="closeModal()" class="flex-1 py-3.5 bg-gray-100 dark:bg-zinc-800 text-gray-900 dark:text-zinc-100 rounded-xl font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition">
                Cancel
            </button>
            <button id="modal-confirm-btn" class="flex-1 py-3.5 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition">
                Confirm
            </button>
        </div>
    </div>
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

function showConfirmModal(title, message, onConfirm, confirmText = 'Confirm') {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-message').textContent = message;
    const confirmBtn = document.getElementById('modal-confirm-btn');
    confirmBtn.textContent = confirmText;
    confirmBtn.onclick = () => {
        closeModal();
        onConfirm();
    };
    document.getElementById('confirm-modal').classList.remove('hidden');
    setTimeout(() => {
        document.getElementById('modal-content').classList.remove('translate-y-full');
    }, 10);
}

function closeModal() {
    document.getElementById('modal-content').classList.add('translate-y-full');
    setTimeout(() => {
        document.getElementById('confirm-modal').classList.add('hidden');
    }, 300);
}

async function loadBackups() {
    try {
        const response = await api.get('api/backup.php?action=stats');
        if (response.success) {
            const stats = response.data;
            const allBackups = stats.all_backups || [];
            const autoBackups = stats.auto_backups || [];
            const manualBackups = stats.manual_backups || [];

            // Update stats
            document.getElementById('total-count').textContent = allBackups.length;
            const latestBackup = allBackups[0];
            document.getElementById('latest-backup').textContent = latestBackup
                ? new Date(latestBackup.created_at).toLocaleDateString()
                : 'Never';

            // Render list
            const listEl = document.getElementById('backups-list');
            if (allBackups.length === 0) {
                listEl.innerHTML = `
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 dark:text-zinc-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                        <p class="text-sm text-gray-500 dark:text-zinc-400">No backups yet</p>
                        <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">Create your first backup to get started</p>
                    </div>
                `;
                return;
            }

            listEl.innerHTML = allBackups.map((backup, index) => {
                const date = new Date(backup.created_at);
                const isToday = date.toDateString() === new Date().toDateString();
                const timeStr = isToday ? 'Today at ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : date.toLocaleDateString();

                return `
                    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-gray-200 dark:border-zinc-800 p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="px-2 py-0.5 bg-${backup.type === 'auto' ? 'blue' : 'black'}-100 dark:bg-${backup.type === 'auto' ? 'blue' : 'black'}-900/30 text-${backup.type === 'auto' ? 'blue' : 'black'}-700 dark:text-${backup.type === 'auto' ? 'blue' : 'black'}-300 text-xs font-bold rounded uppercase">${backup.type || 'manual'}</span>
                                    <span class="text-xs text-gray-500 dark:text-zinc-400">${backup.size_formatted || 'Unknown size'}</span>
                                </div>
                                <p class="font-bold text-gray-900 dark:text-zinc-100 truncate">${backup.filename}</p>
                                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">${timeStr}</p>
                                ${backup.description ? `<p class="text-xs text-gray-600 dark:text-zinc-300 mt-2">${backup.description}</p>` : ''}
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-zinc-800">
                            <button onclick="downloadBackup('${backup.filename}')" class="py-2 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 rounded-lg font-semibold text-xs hover:bg-gray-200 dark:hover:bg-zinc-700 transition flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download
                            </button>
                            <button onclick="restoreBackup('${backup.filename}')" class="py-2 bg-black dark:bg-white text-white dark:text-black rounded-lg font-semibold text-xs hover:bg-gray-800 dark:hover:bg-gray-200 transition flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Restore
                            </button>
                            <button onclick="deleteBackup('${backup.filename}')" class="py-2 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg font-semibold text-xs hover:bg-red-100 dark:hover:bg-red-900/30 transition flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Failed to load backups:', error);
        document.getElementById('backups-list').innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-red-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm text-gray-500 dark:text-zinc-400">Failed to load backups</p>
                <p class="text-xs text-gray-400 dark:text-zinc-500 mt-1">${error.message}</p>
            </div>
        `;
    }
}

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
            loadBackups();
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

function downloadBackup(filename) {
    // Use a temporary anchor to trigger download
    const downloadUrl = `${APP_URL}/api/backup.php?action=download&filename=${encodeURIComponent(filename)}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`;
    const link = document.createElement('a');
    link.href = downloadUrl;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast('Download starting...', 'info');
}

function restoreBackup(filename) {
    showConfirmModal(
        'Restore Backup',
        `⚠️ This will OVERWRITE all current data with the backup "${filename}". This cannot be undone. Proceed?`,
        () => executeRestore(filename),
        'Restore'
    );
}

async function executeRestore(filename) {
    showToast('Restoring backup...', 'info');
    try {
        const response = await api.post('api/backup.php?action=restore', {
            filename: filename,
            csrf_token: CSRF_TOKEN
        });
        if (response.success) {
            showToast('Backup restored successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(response.message || 'Restore failed');
        }
    } catch (error) {
        showToast('Restore failed: ' + error.message, 'error');
    }
}

function deleteBackup(filename) {
    showConfirmModal(
        'Delete Backup',
        `Are you sure you want to delete "${filename}"? This cannot be undone.`,
        () => executeDelete(filename),
        'Delete'
    );
}

async function executeDelete(filename) {
    showToast('Deleting backup...', 'info');
    try {
        const response = await api.post('api/backup.php?action=delete', {
            filename: filename,
            csrf_token: CSRF_TOKEN
        });
        if (response.success) {
            showToast('Backup deleted', 'success');
            loadBackups();
        } else {
            throw new Error(response.message || 'Delete failed');
        }
    } catch (error) {
        showToast('Delete failed: ' + error.message, 'error');
    }
}

// Load backups on page load
loadBackups();
</script>

</body>
</html>
