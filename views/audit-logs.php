<?php
// Audit Logs View
$db = new Database(getMasterPassword(), Auth::userId());
$audit = new Audit($db);

// Get event types and resource types for filters
$eventTypes = $audit->getEventTypes();
$resourceTypes = $audit->getResourceTypes();

// Get stats
$stats = $audit->getStats();
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Audit Logs</h1>
            <p class="text-sm text-gray-500 mt-1">Track all user activities and system events</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="exportLogs('csv')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </button>
            <button onclick="exportLogs('json')" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export JSON
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Total Events</p>
            <p class="text-2xl font-bold text-gray-900" id="stat-total"><?php echo e($stats['total_logs']); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Today</p>
            <p class="text-2xl font-bold text-gray-900" id="stat-today">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Unique Users</p>
            <p class="text-2xl font-bold text-gray-900"><?php echo e($stats['unique_users']); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-sm text-gray-500">Most Active</p>
            <p class="text-lg font-medium text-gray-900 truncate" id="stat-active">
                <?php echo e(!empty($stats['by_user']) ? ($stats['by_user'][0]['user_name'] ?? 'N/A') : 'N/A'); ?>
            </p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wider">Search</label>
                <input type="text" id="filter-search" placeholder="Search logs..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm">
            </div>
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wider">Event Type</label>
                <select id="filter-event" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm">
                    <option value="">All Events</option>
                    <?php foreach ($eventTypes as $key => $type): ?>
                        <option value="<?php echo e($key); ?>"><?php echo e($type['icon'] . ' ' . $type['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wider">From</label>
                <input type="date" id="filter-from" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm">
            </div>
            <div class="w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wider">To</label>
                <input type="date" id="filter-to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm">
            </div>
            <div class="pt-6">
                <button onclick="applyFilters()" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">
                    Apply Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-3">Timestamp</th>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">User</th>
                        <th class="px-4 py-3">IP Address</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Details</th>
                    </tr>
                </thead>
                <tbody id="audit-logs-body" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <div class="spinner mx-auto mb-2"></div>
                            <p class="text-gray-500">Loading audit logs...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
            <p class="text-sm text-gray-500" id="pagination-info">Showing 0 of 0 logs</p>
            <div class="flex items-center gap-2">
                <button onclick="changePage(-1)" id="btn-prev" class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Previous
                </button>
                <span class="text-sm text-gray-600" id="pagination-page">Page 1</span>
                <button onclick="changePage(1)" id="btn-next" class="px-3 py-1 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Next
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Log Detail Modal -->
<div id="log-detail-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Log Details</h3>
            <button onclick="closeLogModal()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="log-detail-content" class="p-6 overflow-y-auto max-h-[60vh]">
            <!-- Content populated by JS -->
        </div>
    </div>
</div>

<script>
// State
let currentPage = 1;
const pageSize = 25;
let currentFilters = {};
let logsData = { logs: [], total: 0 };

// Event type icons
const eventIcons = {
    'user.login': '🔓',
    'user.logout': '🔒',
    'user.login_failed': '⚠️',
    'user.password_change': '🔑',
    'user.master_password_change': '🔐',
    'data.create': '➕',
    'data.update': '✏️',
    'data.delete': '🗑️',
    'system.backup': '💾',
    'system.restore': '♻️',
    'data.export': '📤',
    'data.import': '📥'
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadLogs();
    loadStats();

    // Debounced search
    document.getElementById('filter-search').addEventListener('input', debounce(() => {
        currentPage = 1;
        applyFilters();
    }, 300));
});

async function loadLogs() {
    const tbody = document.getElementById('audit-logs-body');

    const params = new URLSearchParams({
        action: 'list',
        limit: pageSize,
        offset: (currentPage - 1) * pageSize,
        ...currentFilters
    });

    try {
        const response = await api.get(`api/audit.php?${params.toString()}`);
        logsData = response.data;

        if (response.success && response.data.logs.length > 0) {
            tbody.innerHTML = response.data.logs.map(log => `
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="text-sm text-gray-900">${formatDate(log.timestamp)}</span>
                        <span class="text-xs text-gray-500 block">${formatTime(log.timestamp)}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                            ${eventIcons[log.event] || '📋'} ${formatEventLabel(log.event)}
                        </span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-gray-200 rounded-full flex items-center justify-center text-xs font-medium text-gray-600">
                                ${(log.user_name || 'U').charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">${escapeHtml(log.user_name || 'Unknown')}</p>
                                <p class="text-xs text-gray-500">${escapeHtml(log.user_email || '')}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">${escapeHtml(log.ip_address || 'N/A')}</code>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm text-gray-600 max-w-xs truncate" title="${escapeHtml(log.description || '')}">
                            ${escapeHtml(log.description || '')}
                        </p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button onclick="showLogDetail('${log.id}')" class="text-gray-400 hover:text-blue-600 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">No Audit Logs</h3>
                        <p class="text-sm text-gray-500 mt-1">User activities will appear here once recorded.</p>
                    </td>
                </tr>
            `;
        }

        updatePagination(response.data.total, response.data.limit, response.data.offset);

    } catch (error) {
        console.error('Failed to load audit logs:', error);
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-12 text-center text-red-500">
                    Failed to load audit logs. Please try again.
                </td>
            </tr>
        `;
    }
}

async function loadStats() {
    try {
        const response = await api.get('api/audit.php?action=stats');
        if (response.success) {
            const stats = response.data;

            // Update today stat
            const todayKey = new Date().toISOString().split('T')[0];
            const todayCount = stats.by_day?.[todayKey] || 0;
            document.getElementById('stat-today').textContent = todayCount;
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

function applyFilters() {
    currentFilters = {
        search: document.getElementById('filter-search').value || undefined,
        event: document.getElementById('filter-event').value || undefined,
        from: document.getElementById('filter-from').value || undefined,
        to: document.getElementById('filter-to').value || undefined
    };
    currentPage = 1;
    loadLogs();
}

function changePage(delta) {
    const newPage = currentPage + delta;
    const maxPage = Math.ceil(logsData.total / pageSize);
    if (newPage >= 1 && newPage <= maxPage) {
        currentPage = newPage;
        loadLogs();
    }
}

function updatePagination(total, limit, offset) {
    const start = offset + 1;
    const end = Math.min(offset + limit, total);
    const maxPage = Math.ceil(total / pageSize);

    document.getElementById('pagination-info').textContent =
        total > 0 ? `Showing ${start}-${end} of ${total} logs` : 'No logs found';
    document.getElementById('pagination-page').textContent = `Page ${currentPage} of ${maxPage || 1}`;

    document.getElementById('btn-prev').disabled = currentPage <= 1;
    document.getElementById('btn-next').disabled = currentPage >= maxPage;
}

async function showLogDetail(logId) {
    try {
        const response = await api.get(`api/audit.php?action=get&id=${logId}`);
        if (response.success && response.data) {
            const log = response.data;

            document.getElementById('log-detail-content').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Event</label>
                            <p class="text-sm font-medium text-gray-900">
                                ${eventIcons[log.event] || '📋'} ${formatEventLabel(log.event)}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Timestamp</label>
                            <p class="text-sm font-medium text-gray-900">${log.timestamp}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">User</label>
                            <p class="text-sm font-medium text-gray-900">${escapeHtml(log.user_name || 'Unknown')}</p>
                            <p class="text-xs text-gray-500">${escapeHtml(log.user_email || '')}</p>
                            <p class="text-xs text-gray-500 font-mono">ID: ${escapeHtml(log.user_id || 'N/A')}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">IP Address</label>
                            <code class="text-sm bg-gray-100 px-2 py-1 rounded">${escapeHtml(log.ip_address || 'N/A')}</code>
                        </div>
                    </div>

                    ${log.resource_type ? `
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Resource</label>
                        <p class="text-sm text-gray-900">${escapeHtml(log.resource_type)} ${log.resource_id ? `(${escapeHtml(log.resource_id)})` : ''}</p>
                    </div>
                    ` : ''}

                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Description</label>
                        <p class="text-sm text-gray-900">${escapeHtml(log.description || 'No description')}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${log.success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                            ${log.success ? '✓ Success' : '✗ Failed'}
                        </span>
                    </div>

                    ${log.details ? `
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Additional Details</label>
                        <pre class="text-xs bg-gray-100 p-3 rounded-lg overflow-x-auto">${JSON.stringify(log.details, null, 2)}</pre>
                    </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('log-detail-modal').classList.remove('hidden');
            document.getElementById('log-detail-modal').classList.add('flex');
        }
    } catch (error) {
        showToast('Failed to load log details', 'error');
    }
}

function closeLogModal() {
    document.getElementById('log-detail-modal').classList.add('hidden');
    document.getElementById('log-detail-modal').classList.remove('flex');
}

function exportLogs(format) {
    const params = new URLSearchParams({
        action: 'export',
        format: format,
        ...currentFilters
    });

    window.location.href = `api/audit.php?${params.toString()}&csrf_token=${CSRF_TOKEN}`;
}

function formatDate(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function formatEventLabel(event) {
    return event.split('.').map(word =>
        word.charAt(0).toUpperCase() + word.slice(1).replace(/_/g, ' ')
    ).join(' > ');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Close modal on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLogModal();
});

// Close modal on backdrop click
document.getElementById('log-detail-modal').addEventListener('click', (e) => {
    if (e.target === document.getElementById('log-detail-modal')) {
        closeLogModal();
    }
});
</script>

