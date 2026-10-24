<?php
/**
 * Admin Shared AI Keys — admin-only configuration page.
 *
 * Admin can:
 *   - Import keys + models directly from their own Settings (one-click sync)
 *   - Manually enter / overwrite keys if needed
 *
 * Models are pulled from the admin's per-user `models` collection and copied
 * into the global admin_shared_keys.json.enc record. Subscribed users with
 * the "shared keys" toggle on will use these keys AND models.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2><p class="text-gray-500">You do not have permission to view this page.</p></div>';
    return;
}

require_once INCLUDES_PATH . '/AISharedKeysAPI.php';
$sharedKeysApi = new AISharedKeysAPI();
$status = $sharedKeysApi->getMaskedKeys();
$models = $status['models'] ?? [];

$providers = [
    'groq' => [
        'field' => 'groqApiKey',
        'label' => 'Groq',
        'placeholder' => 'gsk_...',
        'docs' => 'https://console.groq.com/keys'
    ],
    'openrouter' => [
        'field' => 'openrouterApiKey',
        'label' => 'OpenRouter',
        'placeholder' => 'sk-or-...',
        'docs' => 'https://openrouter.ai/keys'
    ],
    'gemini' => [
        'field' => 'geminiApiKey',
        'label' => 'Gemini',
        'placeholder' => 'AIza...',
        'docs' => 'https://aistudio.google.com/app/apikey'
    ]
];
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=admin-hub" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Shared AI Keys</h2>
            <p class="text-gray-500 font-medium tracking-tight">Keys + models that subscribed users will consume.</p>
        </div>
    </div>

    <!-- Quick import banner -->
    <div class="mb-6 bg-black text-white rounded-2xl p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/60 mb-1">Recommended</p>
            <p class="text-lg font-bold">Import from your Settings</p>
            <p class="text-sm text-white/70 mt-1">Copy the API keys and models you already configured in Settings &amp; Model Settings.</p>
        </div>
        <button type="button" id="import-btn" class="px-6 py-3 bg-white text-black rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-100 transition inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            Import from my Settings
        </button>
    </div>

    <!-- Provider status grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <?php foreach ($providers as $providerId => $cfg):
            $providerStatus = $status[$cfg['field']] ?? ['configured' => false, 'lastFour' => null];
            $modelStat = $models[$providerId] ?? ['total' => 0, 'enabled' => 0, 'default' => null];
        ?>
            <div class="bg-white rounded-2xl border-2 <?php echo $providerStatus['configured'] ? 'border-black' : 'border-gray-200'; ?> p-6 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400">Provider</p>
                        <h3 class="text-xl font-bold"><?php echo e($cfg['label']); ?></h3>
                    </div>
                    <?php if ($providerStatus['configured']): ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            Set
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-gray-500 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full">Not set</span>
                    <?php endif; ?>
                </div>
                <dl class="grid grid-cols-2 gap-y-1 text-xs">
                    <dt class="text-gray-500 uppercase tracking-widest font-bold">API key</dt>
                    <dd class="font-mono"><?php echo $providerStatus['configured'] ? '••••' . e((string)$providerStatus['lastFour']) : '—'; ?></dd>
                    <dt class="text-gray-500 uppercase tracking-widest font-bold">Models</dt>
                    <dd><?php echo (int)$modelStat['enabled']; ?> enabled / <?php echo (int)$modelStat['total']; ?> total</dd>
                    <dt class="text-gray-500 uppercase tracking-widest font-bold">Default</dt>
                    <dd class="font-mono text-[10px] break-all"><?php echo e($modelStat['default'] ?? '—'); ?></dd>
                </dl>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-5 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-yellow-700 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12c0 4.971-4.029 9-9 9s-9-4.029-9-9 4.029-9 9-9 9 4.029 9 9z"></path>
            </svg>
            <div class="text-sm text-yellow-900">
                <p class="font-bold mb-1">Where models come from</p>
                <p>Manage your provider models in <a href="?page=model-settings" class="underline font-bold">Model Settings</a>. Then click <strong>Import</strong> above to copy them here. Users with active subscriptions will use these models when their "shared keys" toggle is on.</p>
            </div>
        </div>
    </div>

    <!-- Advanced manual entry -->
    <details class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <summary class="p-5 cursor-pointer flex items-center justify-between hover:bg-gray-50">
            <div>
                <h3 class="font-bold">Manual entry</h3>
                <p class="text-xs text-gray-500 mt-1">Type keys directly (overrides imported values for the listed providers).</p>
            </div>
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </summary>
        <form id="shared-keys-form" class="p-8 border-t-2 border-gray-100 space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <?php foreach ($providers as $providerId => $cfg): ?>
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">
                        <?php echo e($cfg['label']); ?> API Key
                    </label>
                    <input
                        type="password"
                        name="<?php echo e($cfg['field']); ?>"
                        autocomplete="new-password"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white font-mono text-sm"
                        placeholder="<?php echo e($cfg['placeholder']); ?>">
                    <p class="mt-2 text-xs text-gray-500">
                        Leave blank to keep current value.
                        <?php if (!empty($cfg['docs'])): ?>
                            &middot; <a href="<?php echo e($cfg['docs']); ?>" target="_blank" rel="noopener" class="underline text-gray-700 hover:text-black">Get a key &rarr;</a>
                        <?php endif; ?>
                    </p>
                </div>
            <?php endforeach; ?>

            <div class="flex items-center justify-between pt-6 border-t-2 border-gray-100">
                <div class="text-xs text-gray-500">
                    <?php if (!empty($status['updatedAt'])): ?>
                        Last updated: <?php echo e(date('M j, Y g:i a', strtotime((string)$status['updatedAt']))); ?>
                    <?php else: ?>
                        Never updated
                    <?php endif; ?>
                </div>
                <button type="submit" class="px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
                    Save Keys
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </button>
            </div>
        </form>
    </details>
</div>

<script>
(function() {
    const importBtn = document.getElementById('import-btn');
    if (importBtn) {
        importBtn.addEventListener('click', async () => {
            const originalText = importBtn.innerHTML;
            importBtn.disabled = true;
            importBtn.innerHTML = 'Importing...';
            try {
                const response = await api.post('api/admin-shared-keys.php?action=import', { csrf_token: CSRF_TOKEN });
                if (response && response.success) {
                    const data = response.data || {};
                    const imp = data.imported || {};
                    const msg = `Imported ${(imp.providersImported || []).length} key(s) and ${imp.modelsImported || 0} model(s)`;
                    showToast(msg, 'success');
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    showToast(response?.error?.message || response?.error || 'Import failed', 'error');
                    importBtn.disabled = false;
                    importBtn.innerHTML = originalText;
                }
            } catch (err) {
                showToast(err.response?.error?.message || err.message || 'Import failed', 'error');
                importBtn.disabled = false;
                importBtn.innerHTML = originalText;
            }
        });
    }

    const form = document.getElementById('shared-keys-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const payload = { csrf_token: formData.get('csrf_token') || '' };
            ['groqApiKey', 'openrouterApiKey', 'geminiApiKey'].forEach((field) => {
                const value = (formData.get(field) || '').toString();
                if (value !== '') {
                    payload[field] = value.trim();
                }
            });
            if (Object.keys(payload).length <= 1) {
                showToast('Enter at least one key to save', 'info');
                return;
            }
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Saving...';
            try {
                const response = await api.post('api/admin-shared-keys.php', payload);
                if (response && response.success) {
                    showToast('Admin shared keys updated', 'success');
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    showToast(response?.error?.message || 'Failed to save', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (err) {
                showToast(err.response?.error?.message || err.message || 'Failed to save', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
})();
</script>
