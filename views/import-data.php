<?php
/**
 * Data Management View (Import/Export)
 */
$db = new Database(getMasterPassword(), Auth::userId());
?>

<div class="max-w-full">
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=settings" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900">Data Management</h2>
    </div>

    <!-- Info Box - Unified Backup Structure -->
    <div class="bg-purple-50 border border-purple-200 rounded-2xl p-6 mb-8">
        <div class="flex items-start gap-4">
            <svg class="w-6 h-6 text-purple-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <div class="text-sm">
                <p class="font-semibold text-purple-900 mb-2">Unified Backup Structure</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-purple-800">
                    <div>
                        <strong class="block">Backup Management</strong>
                        <span class="text-xs block">Settings → Create, download, upload & restore backups</span>
                    </div>
                    <div>
                        <strong class="block">Data Recovery</strong>
                        <span class="text-xs block">Settings → Recover data locked with old password</span>
                    </div>
                    <div>
                        <strong class="block">This Page</strong>
                        <span class="text-xs block">Auto-backup settings, data export, and wipe</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto Backup Settings -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm mb-8">
        <div class="flex items-start gap-6">
            <div class="w-12 h-12 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
            </div>
            <div class="flex-1">
                <h3 class="text-xl font-bold text-gray-900 mb-2">Automatic Backups</h3>
                <p class="text-gray-500 text-sm mb-6 leading-relaxed">Schedule automatic backups for this workspace. The scheduler now creates backups per signed-in account instead of using the old shared data store.</p>

                <div class="space-y-6">
                    <!-- Enable/Disable -->
                    <label class="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition">
                        <div>
                            <span class="font-bold text-gray-900">Enable Automatic Backups</span>
                            <p class="text-xs text-gray-500 mt-1">Run scheduled backups for this workspace</p>
                        </div>
                        <input type="checkbox" id="auto-backup-enabled" class="w-6 h-6 text-green-600 rounded focus:ring-green-500">
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Frequency -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Backup Frequency</label>
                            <select id="backup-frequency" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="daily" selected>Daily (2 AM)</option>
                                <option value="weekly">Weekly (Sunday 2 AM)</option>
                            </select>
                        </div>

                        <!-- Retention -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Keep Backups For</label>
                            <select id="backup-retention" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                <option value="3">3 days</option>
                                <option value="7" selected>7 days</option>
                                <option value="14">14 days</option>
                                <option value="30">30 days</option>
                            </select>
                        </div>
                    </div>

                    <!-- Backup Info -->
                    <div id="backup-info" class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-sm text-gray-800">
                            <strong>Last Auto Backup:</strong> <span id="last-backup-time">Loading...</span>
                        </p>
                        <p class="text-sm text-gray-600 mt-1">
                            <strong>Total Auto Backups:</strong> <span id="backup-count">Loading...</span>
                        </p>
                        <p class="text-xs text-gray-500 mt-2">
                            Automatic backups use the current user's encrypted workspace storage.
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-4">
                        <button onclick="saveAutoBackupSettings()" class="px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
                            Save Settings
                        </button>
                        <button onclick="triggerBackupNow()" class="px-6 py-3 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition">
                            Backup Now
                        </button>
                        <span id="backup-status" class="text-sm text-gray-500"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Wipe All Data Section -->
    <div class="bg-red-50 border-2 border-red-200 rounded-2xl p-8 shadow-sm mb-8">
        <div class="flex items-start gap-6">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-xl font-bold text-red-900 mb-2">Wipe All Data</h3>
                <p class="text-red-700 text-sm mb-6 leading-relaxed">
                    <strong>⚠️ DANGER:</strong> This will <strong>permanently delete ALL data</strong> including tasks, projects, clients, invoices, finance records, inventory, notes, habits, water tracking, and settings. This action <strong>cannot be undone</strong>.
                </p>

                <div class="space-y-4">
                    <!-- Backup before wipe -->
                    <label class="flex items-center gap-3 p-4 bg-white rounded-xl border border-red-200 cursor-pointer hover:bg-red-50 transition">
                        <input type="checkbox" id="create-backup-before-wipe" checked class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                        <div>
                            <span class="font-bold text-gray-900">Create backup before wiping</span>
                            <p class="text-xs text-gray-500">Recommended - saves a backup before deletion</p>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-4 bg-white rounded-xl border border-red-200 cursor-pointer hover:bg-red-50 transition">
                        <input type="checkbox" id="keep-music-on-wipe" checked class="w-5 h-5 text-red-600 rounded focus:ring-red-500">
                        <div>
                            <span class="font-bold text-gray-900">Keep Pomodoro music when wiping</span>
                            <p class="text-xs text-gray-500">Preserves uploaded Pomodoro tracks for shared app copies</p>
                        </div>
                    </label>

                    <!-- Password confirmation -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Confirm Password
                        </label>
                        <input type="password" id="wipe-password"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="Enter your account password">
                    </div>

                    <!-- Type confirmation -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            Type <code class="bg-gray-100 px-2 py-1 rounded">DELETE ALL DATA</code> to confirm
                        </label>
                        <input type="text" id="wipe-confirmation"
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-transparent"
                               placeholder="DELETE ALL DATA">
                    </div>

                    <!-- Countdown display -->
                    <div id="wipe-countdown" class="hidden text-center p-6 bg-red-100 rounded-xl">
                        <p class="text-red-700 font-bold text-lg">
                            ⚠️ Wiping in <span id="countdown-timer" class="text-3xl">10</span> seconds...
                        </p>
                        <button type="button" onclick="cancelWipe()"
                                class="mt-3 text-sm text-gray-700 underline hover:text-gray-900 font-medium">
                            Cancel
                        </button>
                    </div>

                    <!-- Wipe button -->
                    <button type="button" onclick="initiateWipe()" id="wipe-button"
                            class="w-full py-4 bg-red-600 text-white rounded-xl font-bold hover:bg-red-700 transition shadow-lg">
                        Wipe All Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Export Section -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Export Data</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">Download only this workspace as an encrypted ZIP or JSON file for backup or migration.</p>
            
            <div class="space-y-3">
                <button onclick="exportData('zip')" class="w-full py-4 bg-black text-white rounded-2xl font-bold hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2">
                    Download Secure ZIP
                </button>
                <button onclick="exportData('json')" class="w-full py-4 bg-gray-50 text-gray-900 rounded-2xl font-bold hover:bg-gray-200 transition flex items-center justify-center gap-2">
                    Raw JSON Export
                </button>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl bg-gray-50 cursor-pointer">
                    <input type="checkbox" id="include-music-export" checked class="w-4 h-4 text-black rounded">
                    <div>
                        <span class="text-sm font-bold text-gray-900">Include Pomodoro music files (ZIP only)</span>
                        <p class="text-xs text-gray-500">ZIP exports include shared Pomodoro tracks from the app library. JSON export keeps only workspace metadata.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Import Section -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Import Data</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">Upload a previously exported JSON or ZIP file to restore this workspace. <span class="text-red-500 font-bold">This replaces current data for the signed-in account.</span></p>
            
            <form id="import-form" class="space-y-4">
                <div class="relative group">
                    <input type="file" id="import-file" name="file" accept=".json,.zip" required
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center group-hover:border-black transition-colors">
                        <p id="file-name" class="text-sm font-bold text-gray-400 uppercase tracking-widest">Drop file here or click</p>
                    </div>
                </div>
                <button type="submit" class="w-full py-4 bg-black text-white rounded-2xl font-bold hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2">
                    Run Import
                </button>
            </form>
        </div>
    </div>

    <!-- AI Tools Info -->
    <div class="mt-8 bg-black rounded-2xl p-8 text-white">
        <div class="flex items-start gap-6">
            <div class="w-12 h-12 bg-blue-500 rounded-2xl flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
            </div>
            <div>
                <h4 class="text-xl font-bold mb-2">AI-Driven Migration</h4>
                <p class="text-gray-400 text-sm leading-relaxed mb-4">Coming Soon: Use AI to transform CSV exports from other platforms (Trello, Jira, Asana) into <?php echo e(getSiteName()); ?> format automatically.</p>
                <div class="flex gap-2">
                    <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase">CSV to JSON</span>
                    <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase">Field Mapping</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ═══════════════════════════════════════════════════════════════
// Auto Backup System (Server-Side Scheduler)
// ═══════════════════════════════════════════════════════════════

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
    const statusEl = document.getElementById('backup-status');

    try {
        statusEl.textContent = 'Creating backup...';

        const response = await api.post('api/backup.php?action=create', {
            type: 'full',
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            statusEl.textContent = `Success: ${response.data.filename}`;
            showToast('Backup created successfully!', 'success');
            loadBackupInfo(); // Refresh info
        } else {
            throw new Error(response.message || 'Backup failed');
        }
    } catch (error) {
        console.error('Backup failed:', error);
        statusEl.textContent = 'Backup failed';
        showToast('Backup failed: ' + error.message, 'error');
    }
}

// ═══════════════════════════════════════════════════════════════
// Wipe All Data System
// ═══════════════════════════════════════════════════════════════

let wipeCountdown = null;
let wipeTimer = 10;

function initiateWipe() {
    const password = document.getElementById('wipe-password').value;
    const confirmation = document.getElementById('wipe-confirmation').value;
    const createBackup = document.getElementById('create-backup-before-wipe').checked;
    const keepMusic = document.getElementById('keep-music-on-wipe').checked;

    // Validate password
    if (!password) {
        showToast('Please enter your password', 'error');
        return;
    }

    // Validate confirmation
    if (confirmation !== 'DELETE ALL DATA') {
        showToast('Please type "DELETE ALL DATA" exactly', 'error');
        return;
    }

    // Show countdown
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
            executeWipe(password, confirmation, createBackup, keepMusic);
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

async function executeWipe(password, confirmation, createBackup, keepMusic) {
    try {
        const response = await api.post('api/wipe-data.php', {
            password: password,
            confirmation: confirmation,
            create_backup: createBackup,
            keep_music: keepMusic,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            let message = `All data wiped! Deleted ${response.data.deleted} files.`;
            if (response.data.backup_file) {
                message += `\n\nBackup created: ${response.data.backup_file}`;
            }

            showToast(message, 'success');

            // Redirect to setup after 3 seconds
            setTimeout(() => {
                window.location.href = '?page=setup';
            }, 3000);
        }
    } catch (error) {
        showToast('Wipe failed: ' + error.message, 'error');
        cancelWipe();
    }
}

// ═══════════════════════════════════════════════════════════════
// Import/Export Handlers
// ═══════════════════════════════════════════════════════════════

// Import file handling
document.getElementById('import-file').addEventListener('change', function(e) {
    const name = e.target.files[0]?.name || 'Drop file here or click';
    document.getElementById('file-name').textContent = name;
});

async function exportData(format) {
    showToast('Preparing export...', 'info');
    const includeMusic = document.getElementById('include-music-export')?.checked ? '1' : '0';
    window.location.href = `api/export.php?format=${encodeURIComponent(format)}&include_music=${includeMusic}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`;
}

document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const file = document.getElementById('import-file').files[0];
    if (!file) return;

    confirmAction('This will OVERWRITE all current data. Proceed?', async () => {
        showToast('Importing data...', 'info');
        const formData = new FormData();
        formData.append('file', file);
        formData.append('csrf_token', CSRF_TOKEN);

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
    });
});
</script>

