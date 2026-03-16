<?php
/**
 * Data Recovery Page
 * Allows users to recover data locked with old master passwords
 */

$pageTitle = 'Data Recovery';
?>

<div class="max-w-7xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Data Recovery</h1>

    <!-- Info Box -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm text-blue-800">
                <p class="font-semibold mb-1">Password Recovery Tool</p>
                <p class="mb-2">Use this tool <strong>only if you changed your master password</strong> and some data became inaccessible. For backup and restore options, see <a href="?page=settings" class="underline font-medium">Settings → Backup Management</a>.</p>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>Diagnostic</strong> shows which collections can be decrypted with your current password</li>
                    <li><strong>Recover</strong> requires your <strong>OLD master password</strong> (the one used when the data was encrypted)</li>
                    <li>For regular backups and restores, use <a href="?page=settings" class="underline font-medium">Settings → Backup Management</a></li>
                    <li>For data export/import, use <a href="?page=import-data" class="underline font-medium">Import/Export Data</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Diagnostic Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Diagnostic</h2>
        <p class="text-gray-600 mb-4">Check which collections can be decrypted with your current master password:</p>
        <button onclick="runDiagnostic()" class="bg-black text-white px-4 py-2 rounded hover:bg-gray-800">
            Run Diagnostic
        </button>
        <div id="diagnostic-results" class="mt-4"></div>
    </div>

    <!-- Recovery Section -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Recover Locked Collections</h2>
        <p class="text-gray-600 mb-4">
            If you changed your master password and some data became inaccessible,
            use this tool to recover it using your old password.
        </p>

        <form id="recovery-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Old Master Password
                </label>
                <input type="password" id="old-password" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Enter your old master password">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Collections to Recover
                </label>
                <div class="space-y-2">
                    <label class="flex items-center p-2 border rounded hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" value="notes" checked class="mr-3 h-4 w-4 text-blue-600">
                        <span class="flex-1 font-medium">Notes</span>
                        <span id="notes-status" class="text-sm text-gray-500">Checking...</span>
                    </label>
                    <label class="flex items-center p-2 border rounded hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" value="knowledge-base" checked class="mr-3 h-4 w-4 text-blue-600">
                        <span class="flex-1 font-medium">Knowledge Base</span>
                        <span id="knowledge-base-status" class="text-sm text-gray-500">Checking...</span>
                    </label>
                    <label class="flex items-center p-2 border rounded hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" value="advanced_invoices" class="mr-3 h-4 w-4 text-blue-600">
                        <span class="flex-1 font-medium">Advanced Invoices</span>
                    </label>
                    <label class="flex items-center p-2 border rounded hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" value="pomodoro_sessions" class="mr-3 h-4 w-4 text-blue-600">
                        <span class="flex-1 font-medium">Pomodoro Sessions</span>
                    </label>
                    <label class="flex items-center p-2 border rounded hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" value="pomodoro_music" class="mr-3 h-4 w-4 text-blue-600">
                        <span class="flex-1 font-medium">Pomodoro Music</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 font-medium">
                Recover Selected Collections
            </button>
        </form>

        <div id="recovery-results" class="mt-4"></div>
    </div>
</div>

<script>
async function runDiagnostic() {
    const resultsDiv = document.getElementById('diagnostic-results');
    resultsDiv.innerHTML = '<p class="text-gray-500">Running diagnostic...</p>';

    try {
        const response = await api.post('api/data-recovery.php?action=diagnostic', {
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            let html = '<div class="space-y-1">';
            response.data.collections.forEach(col => {
                const statusClass = col.accessible ? 'text-green-600' : 'text-red-600';
                const statusText = col.accessible ? '✓ Accessible' : '✗ Locked';
                const recordInfo = col.record_count !== null ? ` (${col.record_count} records)` : '';
                const sizeInfo = col.file_size ? ` - ${formatBytes(col.file_size)}` : '';
                html += `<div class="${statusClass} text-sm">
                    <strong>${col.collection}:</strong> ${statusText}${recordInfo}${sizeInfo}
                </div>`;
            });
            html += '</div>';
            resultsDiv.innerHTML = html;

            // Update checkbox statuses
            response.data.collections.forEach(col => {
                const statusEl = document.getElementById(`${col.collection}-status`);
                if (statusEl) {
                    if (col.accessible) {
                        statusEl.textContent = `✓ ${col.record_count} records`;
                        statusEl.className = 'text-sm text-green-600';
                        // Uncheck accessible collections since they don't need recovery
                        const checkbox = document.querySelector(`input[value="${col.collection}"]`);
                        if (checkbox) checkbox.checked = false;
                    } else {
                        statusEl.textContent = '✗ Locked - needs recovery';
                        statusEl.className = 'text-sm text-red-600';
                    }
                }
            });
        }
    } catch (error) {
        resultsDiv.innerHTML = `<p class="text-red-500">Error: ${error.message}</p>`;
    }
}

document.getElementById('recovery-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const resultsDiv = document.getElementById('recovery-results');
    const oldPassword = document.getElementById('old-password').value;
    const checkboxes = document.querySelectorAll('#recovery-form input[type="checkbox"]:checked');
    const collections = Array.from(checkboxes).map(cb => cb.value);

    if (collections.length === 0) {
        resultsDiv.innerHTML = '<p class="text-orange-500">Please select at least one collection to recover.</p>';
        return;
    }

    resultsDiv.innerHTML = '<p class="text-gray-500"><span class="inline-block animate-spin mr-2">⏳</span>Recovering data... Please wait.</p>';

    try {
        const response = await api.post('api/data-recovery.php?action=recover', {
            old_password: oldPassword,
            collections: collections,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            let html = '<div class="bg-green-50 border border-green-200 rounded-lg p-4">';
            html += '<h3 class="text-green-800 font-semibold mb-2">✓ Recovery Successful!</h3>';

            if (response.data.recovered.length > 0) {
                html += '<p class="text-green-700 mb-2">The following collections were recovered:</p>';
                html += '<ul class="text-green-700 list-disc list-inside">';
                response.data.recovered.forEach(col => {
                    html += `<li><strong>${col.collection}:</strong> ${col.count} records</li>`;
                });
                html += '</ul>';
            }

            if (response.data.failed.length > 0) {
                html += '<p class="text-red-700 mt-3 mb-2">Some collections could not be recovered:</p>';
                html += '<ul class="text-red-700 list-disc list-inside">';
                response.data.failed.forEach(col => {
                    html += `<li><strong>${col.collection}:</strong> ${col.error}</li>`;
                });
                html += '</ul>';
            }

            html += '</div>';
            resultsDiv.innerHTML = html;

            // Refresh diagnostic after 2 seconds
            setTimeout(runDiagnostic, 2000);
        }
    } catch (error) {
        let errorMsg = error.message;
        // Provide clearer guidance for common errors
        if (errorMsg.includes('Decryption failed') || errorMsg.includes('invalid key')) {
            errorMsg = 'The password entered is incorrect. For recovery, you must enter the OLD master password that was used to encrypt the locked data, not your current password.';
        }
        resultsDiv.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-700">Error: ${errorMsg}</p></div>`;
    }
});

// Helper function to format bytes
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// Run diagnostic on page load
runDiagnostic();
</script>
