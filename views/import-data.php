<?php
/**
 * Data Management View (Import/Export)
 */
$db = new Database(getMasterPassword());
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=settings" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900">Data Management</h2>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Export Section -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Export Data</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">Download your entire workspace as an encrypted ZIP or JSON file for backup or migration.</p>
            
            <div class="space-y-3">
                <button onclick="exportData('zip')" class="w-full py-4 bg-black text-white rounded-2xl font-bold hover:bg-gray-800 transition shadow-lg flex items-center justify-center gap-2">
                    Download Secure ZIP
                </button>
                <button onclick="exportData('json')" class="w-full py-4 bg-gray-50 text-gray-900 rounded-2xl font-bold hover:bg-gray-200 transition flex items-center justify-center gap-2">
                    Raw JSON Export
                </button>
            </div>
        </div>

        <!-- Import Section -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-6">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Import Data</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">Upload a previously exported JSON or ZIP file to restore your data. <span class="text-red-500 font-bold">This replaces current data.</span></p>
            
            <form id="import-form" class="space-y-4">
                <div class="relative group">
                    <input type="file" id="import-file" name="file" accept=".json,.zip" required
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="border-2 border-dashed border-gray-200 rounded-2xl p-8 text-center group-hover:border-black transition-colors">
                        <p id="file-name" class="text-sm font-bold text-gray-400 uppercase tracking-widest">Drop file here or click</p>
                    </div>
                </div>
                <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 transition shadow-lg flex items-center justify-center gap-2">
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
                <p class="text-gray-400 text-sm leading-relaxed mb-4">Coming Soon: Use AI to transform CSV exports from other platforms (Trello, Jira, Asana) into LazyMan Tools format automatically.</p>
                <div class="flex gap-2">
                    <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase">CSV to JSON</span>
                    <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-bold uppercase">Field Mapping</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('import-file').addEventListener('change', function(e) {
    const name = e.target.files[0]?.name || 'Drop file here or click';
    document.getElementById('file-name').textContent = name;
});

async function exportData(format) {
    showToast('Preparing export...', 'info');
    window.location.href = `api/export.php?format=${format}&csrf_token=${CSRF_TOKEN}`;
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
