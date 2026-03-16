<?php
/**
 * Mobile Users Management Page
 * Admin-only user management for mobile
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
                    <p class="text-gray-600 mb-6">Only administrators can manage users and roles.</p>
                    <div class="text-xs text-gray-400 mb-6 bg-gray-50 p-2 rounded">
                        Debug: Role=<?= htmlspecialchars(Auth::role()) ?>, ID=<?= htmlspecialchars(Auth::userId() ?? 'null') ?><br>
                        Session=<?= session_id() ?><br>
                        Config=<?= defined('ROOT_PATH') ? 'Loaded' : 'Not Loaded' ?>
                    </div>
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
<title>Users - <?= htmlspecialchars($siteName) ?>

</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#000000",
                    secondary: "#333333",
                    accent: "#666666",
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"],
                },
                borderRadius: { DEFAULT: "12px" },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
    .touch-target { min-height: 44px; min-width: 44px; }
    .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
    .rounded-2xl, .rounded-xl, .rounded-lg { border-radius: 0 !important; }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Users';
$leftAction = 'menu';
$rightAction = 'menu';
$rightTarget = 'loadUsers()';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Card -->
    <div class="bg-black dark:bg-white rounded-2xl p-5 text-white dark:text-black mb-6">
        <h2 class="text-xl font-bold mb-2">User Directory</h2>
        <p class="text-sm opacity-80">Manage who can access and administer this installation. The last admin cannot be demoted or deleted.</p>
        <button type="button" onclick="bulkBanSpamUsers()" class="mt-4 w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-medium transition">
            Ban Spam Accounts
        </button>
    </div>

    <!-- Users List -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-zinc-800 flex items-center justify-between">
            <h3 class="font-bold text-gray-900 dark:text-zinc-100">All Users</h3>
            <button type="button" onclick="loadUsers()" class="px-3 py-1.5 bg-gray-100 dark:bg-zinc-800 text-gray-700 dark:text-zinc-300 rounded-lg text-xs font-bold hover:bg-gray-200 dark:hover:bg-zinc-700 transition">
                Refresh
            </button>
        </div>
        <div id="users-list" class="divide-y divide-gray-100 dark:divide-zinc-800">
            <div class="text-center py-8">
                <svg class="w-8 h-8 animate-spin text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Loading users...</p>
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
    const colors = { success: 'bg-black dark:bg-white', error: 'bg-red-600', warning: 'bg-orange-500', info: 'bg-gray-600' };
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function loadUsers() {
    const listEl = document.getElementById('users-list');
    listEl.innerHTML = `
        <div class="text-center py-8">
            <svg class="w-8 h-8 animate-spin text-gray-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-xs text-gray-500 dark:text-zinc-400">Loading users...</p>
        </div>
    `;

    try {
        const response = await api.get('api/users.php?action=list');
        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to load users');
        }

        const users = response.data?.users || [];
        if (!users.length) {
            listEl.innerHTML = `<div class="text-center py-8"><p class="text-sm text-gray-500 dark:text-zinc-400">No users found.</p></div>`;
            return;
        }

        listEl.innerHTML = users.map((user) => {
            const isCurrentUser = user.id === '<?= Auth::userId() ?>';
            const isLastAdmin = user.role === 'admin' && users.filter(u => u.role === 'admin').length === 1;
            const canModify = !isLastAdmin || isCurrentUser;
            
            const isVerified = user.emailVerifiedAt !== null && user.emailVerifiedAt !== '';
            const verificationBadge = isVerified
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-50 text-green-700 border border-green-200">Verified</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">Unverified</span>`;
            
            const bannedBadge = user.isBanned 
                ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-red-100 text-red-800">Banned</span>` 
                : '';

            return `
                <div class="p-4 hover:bg-gray-50 dark:hover:bg-zinc-800 transition">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-zinc-700 flex items-center justify-center font-bold text-gray-700 dark:text-zinc-300 shrink-0">
                            ${escapeHtml((user.name || 'U').charAt(0).toUpperCase())}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900 dark:text-zinc-100 truncate flex items-center gap-2">
                                ${escapeHtml(user.name || 'Unknown')}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-zinc-400 truncate">${escapeHtml(user.email || '')}</div>
                            <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                ${verificationBadge}
                                ${bannedBadge}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <select onchange="saveUserRole('${escapeHtml(user.id)}', this.value)"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-zinc-700 rounded-lg text-sm bg-white dark:bg-zinc-800 dark:text-zinc-100"
                                ${!canModify ? 'disabled' : ''}>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                        </select>
                        
                        ${!isLastAdmin && user.role !== 'admin' ? `
                            <button type="button" onclick="toggleBan('${escapeHtml(user.id)}')"
                                    class="px-3 py-2 rounded-lg text-xs font-bold transition ${
                                        user.isBanned 
                                        ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                                        : 'bg-orange-100 text-orange-800 hover:bg-orange-200'
                                    }">
                                ${user.isBanned ? 'Unban' : 'Ban'}
                            </button>
                        ` : ''}

                        ${!isLastAdmin ? `
                            <button type="button" onclick="confirmDeleteUser('${escapeHtml(user.id)}', '${escapeHtml(user.name || user.email || '')}')"
                                    class="p-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition shrink-0"
                                    title="Delete user">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        ` : ''}
                    </div>
                    ${isLastAdmin ? '<p class="text-xs text-orange-600 dark:text-orange-400 mt-2">Last admin cannot be demoted</p>' : ''}
                    ${isCurrentUser ? '<p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">This is you</p>' : ''}
                </div>
            `;
        }).join('');
    } catch (error) {
        listEl.innerHTML = `<div class="text-center py-8"><p class="text-sm text-red-500">${escapeHtml(error.message || 'Failed to load users')}</p></div>`;
    }
}

async function saveUserRole(userId, newRole) {
    try {
        const response = await api.post('api/users.php?action=update_role', {
            user_id: userId,
            role: newRole,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to update role');
        }

        showToast('Role updated successfully', 'success');
        loadUsers();
    } catch (error) {
        showToast(error.message || 'Failed to update role', 'error');
        loadUsers();
    }
}

async function toggleBan(userId) {
    try {
        const response = await api.post('api/users.php?action=toggle_ban', {
            user_id: userId,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to update ban status');
        }

        showToast(response.data.isBanned ? 'User banned' : 'User unbanned', 'success');
        loadUsers();
    } catch (error) {
        showToast(error.message || 'Failed to update status', 'error');
    }
}

async function bulkBanSpamUsers() {
    const confirmed = window.confirm("Scan and ban all users with disposable/spam emails? This logs them out immediately.");
    if (!confirmed) return;

    try {
        const response = await api.post('api/users.php?action=bulk_ban_spam', {
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to execute bulk ban');
        }

        showToast(`Success: Banned ${response.data.count} accounts`, 'success');
        loadUsers();
    } catch (error) {
        showToast(error.message || 'Failed to execute bulk ban', 'error');
    }
}

async function confirmDeleteUser(userId, userName) {
    const confirmed = window.confirm(`Delete "${userName}" and all associated data? This cannot be undone.`);
    if (!confirmed) return;

    try {
        const response = await api.post('api/users.php?action=delete_user', {
            user_id: userId,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to delete user');
        }

        showToast('User deleted successfully', 'success');
        loadUsers();
    } catch (error) {
        showToast(error.message || 'Failed to delete user', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
});
</script>

</body>
</html>
