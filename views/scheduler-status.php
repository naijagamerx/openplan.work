<?php
// Require authentication
if (!Auth::check()) {
    header('Location: ' . APP_URL . '?page=login');
    exit;
}

$pageTitle = 'Scheduler Status';
$phpPath = file_exists(ROOT_PATH . '/php/php.exe') ? realpath(ROOT_PATH . '/php/php.exe') : 'System PHP';
?>

<!-- Status Header -->
<div class="mb-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Scheduler Status</h1>
            <p class="text-gray-500 text-sm">Monitor automated background jobs</p>
        </div>
        <div id="status-indicator" class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full bg-gray-300 animate-pulse" id="status-dot"></span>
            <span class="text-sm font-medium text-gray-600" id="status-text">Loading...</span>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500 uppercase tracking-wide mb-1">Uptime</div>
            <div class="text-2xl font-bold text-gray-900" id="uptime">-</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500 uppercase tracking-wide mb-1">Process ID</div>
            <div class="text-2xl font-bold text-gray-900" id="pid">-</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500 uppercase tracking-wide mb-1">Active Jobs</div>
            <div class="text-2xl font-bold text-gray-900" id="job-count">-</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-sm text-gray-500 uppercase tracking-wide mb-1">Last Check</div>
            <div class="text-2xl font-bold text-gray-900" id="last-check">-</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Scheduler Process</div>
            <div class="text-lg font-semibold text-gray-900" id="scheduler-process-status">-</div>
            <div class="text-xs text-gray-500 mt-1">PID: <span id="scheduler-process-pid">-</span></div>
            <div class="text-xs text-gray-500 mt-1">Status age: <span id="status-age">-</span></div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">Web Server</div>
            <div class="text-lg font-semibold text-gray-900" id="server-process-status">-</div>
            <div class="text-xs text-gray-500 mt-1">PID: <span id="server-process-pid">-</span></div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-2">PHP Status</div>
            <div class="text-lg font-semibold text-gray-900">PHP Active</div>
        </div>
    </div>
</div>

<!-- Jobs Table -->
<div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-lg font-semibold text-gray-900">Configured Jobs</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Job</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Schedule</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Run</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Result</th>
                </tr>
            </thead>
            <tbody id="jobs-table-body" class="divide-y divide-gray-200">
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center gap-3">
                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Loading scheduler status...</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Automation Settings -->
<div class="bg-white border border-gray-200 rounded-lg overflow-hidden mt-8">
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <h2 class="text-lg font-semibold text-gray-900">Automation Settings</h2>
        <p class="text-xs text-gray-500 mt-1">Changes apply within 1 minute or after restarting the scheduler.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Job</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Enabled</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Frequency</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Hour</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Day</th>
                </tr>
            </thead>
            <tbody id="automation-table-body" class="divide-y divide-gray-200">
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">Loading automation settings...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-gray-200 bg-white">
        <button onclick="saveAutomationSettings()" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
            Save Changes
        </button>
    </div>
</div>

<!-- Actions -->
<div class="mt-6 flex flex-wrap gap-3">
    <button onclick="refreshStatus()" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Refresh
    </button>
    <a href="?page=audit-logs" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        View Full Logs
    </a>
</div>

<!-- JavaScript -->
<script>
let refreshInterval;
let automationJobs = {};

// Job display names
const jobNames = {
    'invoice_reminders': 'Invoice Reminders',
    'task_reminders': 'Task Reminders',
    'update_overdue_invoices': 'Overdue Invoices',
    'audit_cleanup': 'Audit Log Cleanup',
    'inventory_alerts': 'Inventory Alerts',
    'rate_limit_cleanup': 'Rate Limit Cleanup'
};

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function formatSchedule(job) {
    const schedule = job.schedule || {};
    if (schedule.frequency === 'daily') {
        return `Daily at ${String(schedule.hour).padStart(2, '0')}:00`;
    }
    if (schedule.frequency === 'weekly') {
        return `Weekly on ${dayNames[schedule.day]} at ${String(schedule.hour).padStart(2, '0')}:00`;
    }
    return 'Unknown';
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString();
}

function formatResult(job) {
    if (job.status === 'pending') {
        return '<span class="text-gray-400">-</span>';
    }

    if (job.status === 'error') {
        return `<span class="text-red-600">${job.error || 'Unknown error'}</span>`;
    }

    if (job.status === 'disabled') {
        return '<span class="text-gray-400">Disabled</span>';
    }

    if (job.result) {
        const parts = [];
        if (job.result.reminders_sent !== undefined) {
            parts.push(`${job.result.reminders_sent} sent`);
        } else if (job.result.removed !== undefined) {
            parts.push(`${job.result.removed} removed`);
        } else if (job.result.updated !== undefined) {
            parts.push(`${job.result.updated} updated`);
        } else if (job.result.low_stock_count !== undefined) {
            parts.push(`${job.result.low_stock_count} items`);
        }

        if (parts.length > 0) {
            return '<span class="text-green-600">' + parts.join(', ') + '</span>';
        }
    }

    return '<span class="text-green-600">Completed</span>';
}

function getStatusBadge(job) {
    if (job.status === 'pending') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Pending</span>';
    }
    if (job.status === 'success') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">OK</span>';
    }
    if (job.status === 'error') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed</span>';
    }
    if (job.status === 'disabled') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Disabled</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Unknown</span>';
}

function renderLogs(logs) {
    return;
}

function formatStatusAge(seconds) {
    if (seconds === null || seconds === undefined) return '-';
    if (seconds < 60) return `${seconds}s`;
    const mins = Math.floor(seconds / 60);
    return `${mins}m`;
}

function updateProcessInfo(data) {
    document.getElementById('scheduler-process-status').textContent = data.scheduler_running ? 'Running' : 'Stopped';
    document.getElementById('scheduler-process-pid').textContent = data.scheduler_pid || '-';
    document.getElementById('status-age').textContent = formatStatusAge(data.status_file_age_seconds);
    document.getElementById('server-process-status').textContent = data.server_running ? 'Running' : 'Stopped';
    document.getElementById('server-process-pid').textContent = data.server_pid || '-';
}

// Load status from API
async function loadStatus() {
    try {
        const response = await fetch('<?= APP_URL ?>/api/scheduler_status.php');
        const data = await response.json().catch(() => null);

        if (!response.ok || !data || !data.running) {
            updateStatusNotRunning(data);
            return;
        }

        updateStatusRunning(data);
    } catch (error) {
        console.error('Failed to load scheduler status:', error);
        updateStatusError();
    }
}

// Update UI when running
function updateStatusRunning(data) {
    document.getElementById('status-dot').className = 'w-3 h-3 rounded-full bg-green-500';
    document.getElementById('status-text').textContent = `Running - ${data.uptime}`;

    document.getElementById('uptime').textContent = data.uptime;
    document.getElementById('pid').textContent = data.pid;
    document.getElementById('job-count').textContent = Object.keys(data.jobs || {}).length;
    document.getElementById('last-check').textContent = data.last_check ? new Date(data.last_check).toLocaleTimeString() : '-';

    updateProcessInfo(data);

    const tbody = document.getElementById('jobs-table-body');
    tbody.innerHTML = '';

    for (const [jobKey, job] of Object.entries(data.jobs || {})) {
        const label = job.label || jobNames[jobKey] || jobKey;
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50';
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${label}</div>
                ${job.description ? `<div class="text-xs text-gray-500">${job.description}</div>` : ''}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">${formatSchedule(job)}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-600">${job.enabled ? formatDateTime(job.last_run) : '-'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                ${getStatusBadge(job)}
            </td>
            <td class="px-6 py-4">
                <div class="text-sm">${formatResult(job)}</div>
            </td>
        `;
        tbody.appendChild(row);
    }
}

// Update UI when not running
function updateStatusNotRunning(data) {
    document.getElementById('status-dot').className = 'w-3 h-3 rounded-full bg-red-500';
    document.getElementById('status-text').textContent = 'Stopped';
    document.getElementById('uptime').textContent = '-';
    document.getElementById('pid').textContent = '-';
    document.getElementById('job-count').textContent = '-';
    document.getElementById('last-check').textContent = '-';

    updateProcessInfo({
        scheduler_running: false,
        scheduler_pid: null,
        status_file_age_seconds: null,
        server_running: data?.server_running || false,
        server_pid: data?.server_pid || null
    });

    const tbody = document.getElementById('jobs-table-body');
    tbody.innerHTML = `
        <tr>
            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                <div class="flex flex-col items-center gap-3">
                    <svg class="w-12 h-12 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">Scheduler is not running</span>
                    <span class="text-sm">Start it by running start_server.bat</span>
                </div>
            </td>
        </tr>
    `;
}

// Update UI on error
function updateStatusError() {
    document.getElementById('status-dot').className = 'w-3 h-3 rounded-full bg-yellow-500';
    document.getElementById('status-text').textContent = 'Error loading status';
}

async function loadAutomationSettings() {
    try {
        const response = await fetch('<?= APP_URL ?>/api/scheduler_config.php');
        const data = await response.json();
        automationJobs = data?.data?.jobs || {};
        renderAutomationTable();
    } catch (error) {
        console.error('Failed to load automation settings:', error);
        const tbody = document.getElementById('automation-table-body');
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Failed to load automation settings</td></tr>';
    }
}

function renderAutomationTable() {
    const tbody = document.getElementById('automation-table-body');
    const rows = [];

    Object.entries(automationJobs).forEach(([jobKey, job]) => {
        const schedule = job.schedule || {};
        const label = job.label || jobNames[jobKey] || jobKey;
        const description = job.description ? `<div class="text-xs text-gray-500">${job.description}</div>` : '';

        const hourOptions = Array.from({ length: 24 }).map((_, hour) => {
            const selected = hour === Number(schedule.hour) ? 'selected' : '';
            return `<option value="${hour}" ${selected}>${String(hour).padStart(2, '0')}:00</option>`;
        }).join('');

        const dayOptions = dayNames.map((day, idx) => {
            const selected = idx === Number(schedule.day) ? 'selected' : '';
            return `<option value="${idx}" ${selected}>${day}</option>`;
        }).join('');

        rows.push(`
            <tr data-job="${jobKey}">
                <td class="px-6 py-4">
                    <div class="text-sm font-medium text-gray-900">${label}</div>
                    ${description}
                </td>
                <td class="px-6 py-4">
                    <input type="checkbox" class="job-enabled" ${schedule.enabled ? 'checked' : ''}>
                </td>
                <td class="px-6 py-4">
                    <select class="job-frequency border border-gray-200 rounded px-2 py-1 text-sm">
                        <option value="daily" ${schedule.frequency === 'daily' ? 'selected' : ''}>Daily</option>
                        <option value="weekly" ${schedule.frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
                    </select>
                </td>
                <td class="px-6 py-4">
                    <select class="job-hour border border-gray-200 rounded px-2 py-1 text-sm">${hourOptions}</select>
                </td>
                <td class="px-6 py-4">
                    <select class="job-day border border-gray-200 rounded px-2 py-1 text-sm">${dayOptions}</select>
                </td>
            </tr>
        `);
    });

    tbody.innerHTML = rows.join('') || '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No jobs configured</td></tr>';

    tbody.querySelectorAll('.job-frequency').forEach(select => {
        select.addEventListener('change', () => {
            const row = select.closest('tr');
            const daySelect = row.querySelector('.job-day');
            if (select.value === 'daily') {
                daySelect.disabled = true;
                daySelect.classList.add('opacity-50');
            } else {
                daySelect.disabled = false;
                daySelect.classList.remove('opacity-50');
            }
        });
        select.dispatchEvent(new Event('change'));
    });
}

async function saveAutomationSettings() {
    const rows = document.querySelectorAll('#automation-table-body tr[data-job]');
    const jobs = {};

    rows.forEach(row => {
        const jobKey = row.dataset.job;
        const enabled = row.querySelector('.job-enabled')?.checked || false;
        const frequency = row.querySelector('.job-frequency')?.value || 'daily';
        const hour = parseInt(row.querySelector('.job-hour')?.value || '0', 10);
        const day = parseInt(row.querySelector('.job-day')?.value || '0', 10);

        jobs[jobKey] = { enabled, frequency, hour, day };
    });

    try {
        const response = await fetch('<?= APP_URL ?>/api/scheduler_config.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
            },
            body: JSON.stringify({ jobs })
        });
        const result = await response.json();
        if (result.success) {
            if (typeof showToast !== 'undefined') showToast('Automation settings saved', 'success');
            loadStatus();
        } else {
            if (typeof showToast !== 'undefined') showToast(result.error || 'Failed to save settings', 'error');
        }
    } catch (error) {
        console.error('Failed to save automation settings:', error);
        if (typeof showToast !== 'undefined') showToast('Failed to save settings', 'error');
    }
}

// Manual refresh
function refreshStatus() {
    loadStatus();
}

// Initial load
loadStatus();
loadAutomationSettings();

// Auto-refresh every 30 seconds
refreshInterval = setInterval(loadStatus, 30000);

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
