<?php
if (!Auth::isAdmin()) {
    http_response_code(403);
    ?>
    <div class="max-w-2xl mx-auto py-16">
        <div class="bg-white border border-red-100 rounded-3xl shadow-sm p-8 text-center">
            <h2 class="text-2xl font-semibold text-gray-900 mb-3">Administrator access required</h2>
            <p class="text-gray-600">Only administrators can manage users and roles for this installation.</p>
        </div>
    </div>
    <?php
    return;
}

$verificationEnabled = isEmailVerificationEnabled();
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <div class="flex items-start justify-between gap-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Users</h2>
                <p class="text-sm text-gray-500 mt-1">Manage who can administer the installation. The last admin cannot be demoted.</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Email Verification</p>
                <p class="text-sm font-medium text-gray-900 mt-1"><?= $verificationEnabled ? 'Enabled' : 'Disabled' ?></p>
            </div>
        </div>
    </div>

    <!-- App-Style Notification Banner -->
    <div id="app-notification" class="hidden rounded-xl p-4 flex items-center gap-3 transition-all duration-300">
        <div id="notification-icon"></div>
        <p id="notification-message" class="text-sm font-medium flex-1"></p>
        <button onclick="hideNotification()" class="p-1 hover:bg-white/20 rounded transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">User Directory</h3>
                <p class="text-sm text-gray-500">Roles are installation-wide. Shared music and developer tools are admin-only.</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="bulkBanSpamUsers()" class="px-4 py-2 border border-red-200 bg-red-50 text-red-700 rounded-lg text-sm font-medium hover:bg-red-100 transition">Ban Spam Accounts</button>
                <button type="button" onclick="loadUsers()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Refresh</button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <th class="px-5 py-3">Name</th>
                        <th class="px-5 py-3">Email</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Verification</th>
                        <th class="px-5 py-3">Last Login</th>
                        <th class="px-5 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="users-table" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-gray-500">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
});

// App-Style Notification Banner (not toast)
let notificationTimeout = null;

function showNotification(message, type = 'info') {
    const banner = document.getElementById('app-notification');
    const iconContainer = document.getElementById('notification-icon');
    const messageEl = document.getElementById('notification-message');

    // Clear existing timeout
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
    }

    // Set styles based on type
    const styles = {
        success: 'bg-green-50 border border-green-200 text-green-800',
        error: 'bg-red-50 border border-red-200 text-red-800',
        info: 'bg-blue-50 border border-blue-200 text-blue-800'
    };

    const icons = {
        success: `<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`,
        error: `<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`,
        info: `<svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`
    };

    // Remove old classes and add new ones
    banner.className = `rounded-xl p-4 flex items-center gap-3 transition-all duration-300 ${styles[type] || styles.info}`;
    iconContainer.innerHTML = icons[type] || icons.info;
    messageEl.textContent = message;

    // Show banner
    banner.classList.remove('hidden');

    // Auto-hide after 4 seconds
    notificationTimeout = setTimeout(() => {
        hideNotification();
    }, 4000);
}

function hideNotification() {
    const banner = document.getElementById('app-notification');
    banner.classList.add('hidden');
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
        notificationTimeout = null;
    }
}

async function loadUsers() {
    const table = document.getElementById('users-table');
    table.innerHTML = '<tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">Loading users...</td></tr>';

    try {
        const response = await api.get('api/users.php?action=list');
        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to load users');
        }

        const users = response.data?.users || [];
        if (!users.length) {
            table.innerHTML = '<tr><td colspan="6" class="px-5 py-10 text-center text-gray-500">No users found.</td></tr>';
            return;
        }

        table.innerHTML = users.map((user) => {
            const isVerified = user.emailVerifiedAt !== null && user.emailVerifiedAt !== '';
            const verificationBadge = isVerified
                ? `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Verified</span>`
                : `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700 border border-yellow-200">Unverified</span>`;

            const verificationHtml = <?= json_encode($verificationEnabled) ?> ? verificationBadge : `<span class="text-xs text-gray-400">N/A</span>`;

            return `
            <tr>
                <td class="px-5 py-4">
                    <div class="font-medium text-gray-900">${escapeHtml(user.name || 'Unknown')}</div>
                    <div class="text-xs text-gray-500 mt-1">${escapeHtml(user.id || '')}</div>
                </td>
                <td class="px-5 py-4 text-sm text-gray-700">${escapeHtml(user.email || '')}</td>
                <td class="px-5 py-4">
                    <select data-user-id="${escapeHtml(user.id)}" class="user-role-select px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                        <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                    </select>
                </td>
                <td class="px-5 py-4">
                    <div class="flex items-center gap-2">
                        ${verificationHtml}
                        ${user.isBanned 
                            ? `<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">Banned</span>` 
                            : ''}
                    </div>
                </td>
                <td class="px-5 py-4 text-sm text-gray-600">${user.lastLogin ? new Date(user.lastLogin).toLocaleString() : 'Never'}</td>
                <td class="px-5 py-4 text-right">
                    <div class="inline-flex items-center gap-2">
                        <button 
                            type="button" 
                            onclick='toggleBan(${JSON.stringify(user.id).replace(/'/g, "&#39;")})' 
                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition ${
                                user.isBanned 
                                ? 'bg-green-100 text-green-800 hover:bg-green-200' 
                                : 'bg-orange-100 text-orange-800 hover:bg-orange-200'
                            }"
                            title="${user.isBanned ? 'Unban User' : 'Ban User'}"
                        >
                            ${user.isBanned ? 'Unban' : 'Ban'}
                        </button>
                        <button type="button" onclick='saveUserRole(${JSON.stringify(user.id).replace(/'/g, "&#39;")})' class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition">Save</button>
                        ${user.canDelete ? `
                            <button
                                type="button"
                                onclick='confirmDeleteUser(${JSON.stringify(user.id).replace(/'/g, "&#39;")}, ${JSON.stringify(user.name || "").replace(/'/g, "&#39;")}, ${JSON.stringify(user.email || "").replace(/'/g, "&#39;")})'
                                class="p-2 border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition"
                                title="Delete user and all data"
                                aria-label="Delete user and all data"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2m-9 0h10l-1 14H8L7 6Z"></path>
                                </svg>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `}).join('');
    } catch (error) {
        table.innerHTML = `<tr><td colspan="6" class="px-5 py-10 text-center text-red-500">${escapeHtml(error.message || 'Failed to load users')}</td></tr>`;
        showNotification(error.message || 'Failed to load users', 'error');
    }
}

async function saveUserRole(userId) {
    const select = document.querySelector(`select[data-user-id="${CSS.escape(userId)}"]`);
    if (!select) {
        return;
    }

    try {
        const response = await api.post('api/users.php?action=update_role', {
            user_id: userId,
            role: select.value,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to update role');
        }

        showNotification('Role updated successfully.', 'success');
        loadUsers();
    } catch (error) {
        showNotification(error.message || 'Failed to update role', 'error');
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

            showNotification(response.data.isBanned ? 'User has been banned.' : 'User ban has been lifted.', 'success');
            loadUsers();
        } catch (error) {
            showNotification(error.message || 'Failed to update status', 'error');
        }
    }

    async function bulkBanSpamUsers() {
        const confirmed = window.confirm("Are you sure you want to scan and ban all non-admin users with disposable or plus-addressed emails? This will immediately log them out.");
        if (!confirmed) {
            return;
        }

        try {
            const response = await api.post('api/users.php?action=bulk_ban_spam', {
                csrf_token: CSRF_TOKEN
            });

            if (!response.success) {
                throw new Error(response.error?.message || response.message || 'Failed to execute bulk ban');
            }

            showNotification(`Success: Banned ${response.data.count} spam accounts.`, 'success');
            loadUsers();
        } catch (error) {
            showNotification(error.message || 'Failed to execute bulk ban', 'error');
        }
    }

    async function confirmDeleteUser(userId, userName, userEmail) {
    const label = userName || userEmail || userId;
    const confirmed = window.confirm(`Delete "${label}" and all associated data? This cannot be undone.`);
    if (!confirmed) {
        return;
    }

    try {
        const response = await api.post('api/users.php?action=delete_user', {
            user_id: userId,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to delete user');
        }

        showNotification('User deleted successfully.', 'success');
        loadUsers();
    } catch (error) {
        showNotification(error.message || 'Failed to delete user', 'error');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
