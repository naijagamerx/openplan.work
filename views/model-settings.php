<?php
/**
 * Model Settings View
 */
$db = new Database(getMasterPassword(), Auth::userId());
$models = $db->load('models');
$config = $db->load('config', true);
$ollamaUrl = $config['ollamaUrl'] ?? 'http://localhost:11434';
$canUseOllama = canUseOllama(); // true only on localhost
?>
<script>const OLLAMA_URL = '<?php echo addslashes($ollamaUrl); ?>';
const OLLAMA_AVAILABLE = <?php echo $canUseOllama ? 'true' : 'false'; ?>;</script>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">AI Model Configuration</h2>
            <p class="text-gray-500">Manage models available for AI features</p>
        </div>
    </div>

    <!-- Groq Models -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between bg-gray-50">
            <h3 class="font-semibold text-gray-900">Groq Models</h3>
            <button onclick="openAddModelModal('groq')" class="px-3 py-1.5 bg-black text-white text-sm rounded-lg hover:bg-gray-800 transition">
                Add Groq Model
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-3">Display Name</th>
                        <th class="px-6 py-3">Model ID</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Default</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="groq-models-table">
                    <?php if (empty($models['groq'])): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No Groq models configured</td></tr>
                    <?php else: foreach ($models['groq'] as $model): ?>
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo e($model['displayName']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo e($model['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-gray-600"><?php echo e($model['modelId']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $model['enabled'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                    <?php echo $model['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($model['isDefault']): ?>
                                    <span class="text-xs font-bold text-black uppercase">Default</span>
                                <?php else: ?>
                                    <button onclick="setDefaultModel('<?php echo $model['id']; ?>', 'groq')" class="text-xs text-blue-600 hover:underline">Make Default</button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <button onclick='openEditModelModal(<?php echo json_encode($model); ?>)' class="text-gray-400 hover:text-black">Edit</button>
                                <?php if (!$model['isDefault']): ?>
                                    <button onclick="deleteModel('<?php echo $model['id']; ?>')" class="text-red-400 hover:text-red-600">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- OpenRouter Models -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between bg-gray-50">
            <h3 class="font-semibold text-gray-900">OpenRouter Models</h3>
            <button onclick="openAddModelModal('openrouter')" class="px-3 py-1.5 bg-black text-white text-sm rounded-lg hover:bg-gray-800 transition">
                Add OpenRouter Model
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-3">Display Name</th>
                        <th class="px-6 py-3">Model ID</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Default</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="openrouter-models-table">
                    <?php if (empty($models['openrouter'])): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No OpenRouter models configured</td></tr>
                    <?php else: foreach ($models['openrouter'] as $model): ?>
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo e($model['displayName']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo e($model['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-gray-600"><?php echo e($model['modelId']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $model['enabled'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                    <?php echo $model['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($model['isDefault']): ?>
                                    <span class="text-xs font-bold text-black uppercase">Default</span>
                                <?php else: ?>
                                    <button onclick="setDefaultModel('<?php echo $model['id']; ?>', 'openrouter')" class="text-xs text-blue-600 hover:underline">Make Default</button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <button onclick='openEditModelModal(<?php echo json_encode($model); ?>)' class="text-gray-400 hover:text-black">Edit</button>
                                <?php if (!$model['isDefault']): ?>
                                    <button onclick="deleteModel('<?php echo $model['id']; ?>')" class="text-red-400 hover:text-red-600">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Google Gemini Models -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between bg-gray-50">
            <div>
                <h3 class="font-semibold text-gray-900">Google Gemini Models</h3>
                <p class="text-xs text-gray-500 mt-0.5">Requires a Gemini API key in Settings</p>
            </div>
            <button onclick="openAddModelModal('gemini')" class="px-3 py-1.5 bg-black text-white text-sm rounded-lg hover:bg-gray-800 transition">
                Add Gemini Model
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-3">Display Name</th>
                        <th class="px-6 py-3">Model ID</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Default</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="gemini-models-table">
                    <?php if (empty($models['gemini'])): ?>
                        <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No Gemini models configured</td></tr>
                    <?php else: foreach ($models['gemini'] as $model): ?>
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo e($model['displayName']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo e($model['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-gray-600"><?php echo e($model['modelId']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $model['enabled'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                    <?php echo $model['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($model['isDefault']): ?>
                                    <span class="text-xs font-bold text-black uppercase">Default</span>
                                <?php else: ?>
                                    <button onclick="setDefaultModel('<?php echo $model['id']; ?>', 'gemini')" class="text-xs text-blue-600 hover:underline">Make Default</button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <button onclick='openEditModelModal(<?php echo json_encode($model); ?>)' class="text-gray-400 hover:text-black">Edit</button>
                                <?php if (!$model['isDefault']): ?>
                                    <button onclick="deleteModel('<?php echo $model['id']; ?>')" class="text-red-400 hover:text-red-600">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Ollama Models (Local) -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between bg-gray-50">
            <div>
                <h3 class="font-semibold text-gray-900">Ollama Models (Local)</h3>
                <?php if ($canUseOllama): ?>
                    <p class="text-xs text-gray-500 mt-0.5">Run <code class="bg-gray-100 px-1 rounded">ollama serve</code> locally — no API key needed. Models can be auto-detected.</p>
                <?php else: ?>
                    <p class="text-xs text-orange-600 mt-0.5 font-medium">⚠️ Online mode: Ollama AI chat is disabled. Shared hosting cannot reach local Ollama. Use Groq, OpenRouter, or Gemini for AI features. Model detection still works in browser if Ollama is running.</p>
                <?php endif; ?>
            </div>
            <div class="flex gap-2">
                <button onclick="detectOllamaModels()" id="detect-ollama-btn" class="px-3 py-1.5 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50 transition">
                    Detect Local Models
                </button>
                <button onclick="openAddModelModal('ollama')" class="px-3 py-1.5 bg-black text-white text-sm rounded-lg hover:bg-gray-800 transition">
                    Add Manually
                </button>
            </div>
        </div>
        <?php if (!$canUseOllama): ?>
        <div class="bg-orange-50 border-t border-orange-100 px-5 py-3">
            <p class="text-xs text-orange-700">
                <strong>To use Ollama for AI chat online:</strong> You need a VPS or use <a href="https://ngrok.com" target="_blank" class="underline">ngrok</a> to expose your local Ollama with a public HTTPS URL, then update the Ollama URL in Settings. 
                For immediate AI access, <a href="?page=model-settings" class="underline">configure Groq</a> (free API keys at console.groq.com) — it works immediately online.
            </p>
        </div>
        <?php endif; ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="text-xs font-semibold text-gray-500 uppercase bg-gray-50 border-b border-gray-200">
                        <th class="px-6 py-3">Display Name</th>
                        <th class="px-6 py-3">Model ID</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Default</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="ollama-models-table">
                    <?php if (empty($models['ollama'])): ?>
                        <tr id="ollama-empty-row"><td colspan="5" class="px-6 py-4 text-center text-gray-500">No Ollama models detected. Click "Detect Local Models" to auto-discover.</td></tr>
                    <?php else: foreach ($models['ollama'] as $model): ?>
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-900"><?php echo e($model['displayName']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo e($model['description']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-mono text-gray-600"><?php echo e($model['modelId']); ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $model['enabled'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'; ?>">
                                    <?php echo $model['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($model['isDefault']): ?>
                                    <span class="text-xs font-bold text-black uppercase">Default</span>
                                <?php else: ?>
                                    <button onclick="setDefaultModel('<?php echo $model['id']; ?>', 'ollama')" class="text-xs text-blue-600 hover:underline">Make Default</button>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <button onclick='openEditModelModal(<?php echo json_encode($model); ?>)' class="text-gray-400 hover:text-black">Edit</button>
                                <?php if (!$model['isDefault']): ?>
                                    <button onclick="deleteModel('<?php echo $model['id']; ?>')" class="text-red-400 hover:text-red-600">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Templates (will be moved to pages in Phase 3) -->
<script>
let currentProvider = '';

function openAddModelModal(provider) {
    currentProvider = provider;
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Add ${provider} Model</h3>
            <form id="add-model-form" class="space-y-4">
                <input type="hidden" name="provider" value="${provider}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                    <input type="text" name="displayName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model ID</label>
                    <input type="text" name="modelId" required placeholder="e.g., llama-3.3-70b-versatile" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono">
                    <p class="text-xs text-gray-500 mt-1">Use the provider model identifier. API keys are managed in Settings.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="enabled" checked id="model-enabled" class="rounded border-gray-300">
                    <label for="model-enabled" class="ml-2 text-sm text-gray-600">Enabled</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg">Add Model</button>
                </div>
            </form>
        </div>
    `);
    
    document.getElementById('add-model-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.enabled = !!data.enabled;

        try {
            const result = await api.post('api/models.php?action=add', data);
            if (result.success) {
                showToast(result.message || 'Model added successfully', 'success');
                closeModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.error?.message || 'Failed to add model', 'error');
            }
        } catch (error) {
            console.error('Add model error:', error);
            showToast('Failed to add model', 'error');
        }
    });
}

function openEditModelModal(model) {
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Edit Model</h3>
            <form id="edit-model-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                    <input type="text" name="displayName" value="${model.displayName}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model ID</label>
                    <input type="text" name="modelId" value="${model.modelId}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono">
                    <p class="text-xs text-gray-500 mt-1">Use the provider model identifier. API keys are managed in Settings.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg">${model.description}</textarea>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="enabled" ${model.enabled ? 'checked' : ''} id="edit-model-enabled" class="rounded border-gray-300">
                    <label for="edit-model-enabled" class="ml-2 text-sm text-gray-600">Enabled</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg">Update Model</button>
                </div>
            </form>
        </div>
    `);
    
    document.getElementById('edit-model-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.enabled = !!data.enabled;

        try {
            const result = await api.post(`api/models.php?action=update&id=${model.id}`, data);
            if (result.success) {
                showToast(result.message || 'Model updated successfully', 'success');
                closeModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(result.error?.message || 'Failed to update model', 'error');
            }
        } catch (error) {
            console.error('Update model error:', error);
            showToast('Failed to update model', 'error');
        }
    });
}

async function setDefaultModel(id, provider) {
    if (!confirm('Are you sure you want to set this as the default model?')) return;
    try {
        const result = await api.post(`api/models.php?action=set-default&id=${id}&provider=${provider}`);
        if (result.success) {
            showToast(result.message || 'Default model updated', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.error?.message || 'Failed to set default model', 'error');
        }
    } catch (error) {
        console.error('Set default error:', error);
        showToast('Failed to set default model', 'error');
    }
}

async function deleteModel(id) {
    if (!confirm('Are you sure you want to delete this model?')) return;
    try {
        const result = await api.delete(`api/models.php?action=delete&id=${id}`);
        if (result.success) {
            showToast(result.message || 'Model deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.error?.message || 'Failed to delete model', 'error');
        }
    } catch (error) {
        console.error('Delete model error:', error);
        showToast('Failed to delete model', 'error');
    }
}

function copyOllamaOriginCmd() {
    navigator.clipboard.writeText('setx OLLAMA_ORIGINS "https://openplan.work"')
        .then(() => showToast('Copied to clipboard', 'success'))
        .catch(() => showToast('Copy failed — select the command and copy manually', 'error'));
}

async function quickAddOllamaModels() {
    const input = document.getElementById('quick-add-models-input');
    const raw = (input?.value || '').trim();
    if (!raw) { showToast('Enter at least one model name', 'warning'); return; }

    const detected = raw.split(',').map(s => s.trim()).filter(Boolean).map(name => ({
        modelId: name,
        displayName: name,
        description: 'Local Ollama model'
    }));

    const btn = document.getElementById('quick-add-btn');
    btn.textContent = 'Saving...';
    btn.disabled = true;

    try {
        const result = await api.post('api/models.php?action=save_ollama_models', { detected });
        if (result.success) {
            const added = result.data?.added?.length ?? 0;
            closeModal();
            if (added > 0) {
                showToast(added + ' model(s) added. Reloading...', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast('All models already configured.', 'info');
            }
        } else {
            showToast(result.error?.message || 'Failed to save models', 'error');
            btn.textContent = 'Add Models';
            btn.disabled = false;
        }
    } catch (e) {
        showToast('Failed to save models', 'error');
        btn.textContent = 'Add Models';
        btn.disabled = false;
    }
}

function showOllamaSetupModal() {
    openModal(
        '<div class="p-6" style="max-width:550px">' +
            '<h3 class="text-lg font-semibold mb-1">Ollama Connection Failed</h3>' +
            '<p class="text-sm text-gray-500 mb-3">Ollama is not accessible from this website. The原因 depends on how you\'re using OpenPlan:</p>' +

            '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">' +
                '<h4 class="font-semibold text-blue-800 mb-2">🔒 For LOCAL Use (offline / localhost)</h4>' +
                '<p class="text-xs text-blue-700 mb-2">If you\'re running OpenPlan on your own computer, Ollama detection works through your browser.</p>' +
                '<p class="text-xs text-blue-700 mb-2"><strong>One-time setup on your PC:</strong></p>' +
                '<div class="bg-gray-900 rounded-lg p-3 space-y-2">' +
                    '<div class="flex items-stretch gap-2">' +
                        '<code class="flex-1 text-green-400 text-xs px-2 py-1 rounded font-mono bg-black break-all">setx OLLAMA_ORIGINS "https://openplan.work"</code>' +
                        '<button onclick="copyOllamaOriginCmd()" class="text-xs px-2 border border-gray-600 rounded bg-gray-800 text-white hover:bg-gray-700 shrink-0">Copy</button>' +
                    '</div>' +
                    '<p class="text-xs text-gray-400">Then restart Ollama: <code class="text-green-400">taskkill /F /IM ollama.exe</code> and relaunch</p>' +
                '</div>' +
            '</div>' +

            '<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">' +
                '<h4 class="font-semibold text-orange-800 mb-2">🌐 For ONLINE Use (Hostinger / shared hosting)</h4>' +
                '<p class="text-xs text-orange-700 mb-2"><strong>Ollama cannot use localhost on shared hosting.</strong> The server cannot reach your local Ollama.</p>' +
                '<p class="text-xs text-orange-700 mb-2"><strong>Option A — Use Cloud AI (Recommended):</strong></p>' +
                '<p class="text-xs text-orange-700 mb-3">Use Groq, OpenRouter, or Gemini instead. They work online immediately with no setup. Get free API keys at <a href="https://console.groq.com" target="_blank" class="underline">console.groq.com</a>.</p>' +
                '<p class="text-xs text-orange-700 mb-2"><strong>Option B — Use Ollama Online:</strong></p>' +
                '<p class="text-xs text-orange-700 mb-2">1. Install <a href="https://ngrok.com" target="_blank" class="underline">ngrok</a> on your PC</p>' +
                '<p class="text-xs text-orange-700 mb-2">2. Run: <code class="bg-gray-100 px-1 rounded">ngrok http 11434</code></p>' +
                '<p class="text-xs text-orange-700 mb-2">3. Copy the https URL (e.g. <code>https://abc123.ngrok.io</code>)</p>' +
                '<p class="text-xs text-orange-700 mb-3">4. Go to Settings → AI API Keys → Ollama URL, paste the ngrok URL, save</p>' +
                '<p class="text-xs text-orange-700">5. Also set OLLAMA_ORIGINS to your ngrok URL on your PC</p>' +
            '</div>' +

            '<details class="border border-gray-200 rounded-lg mb-4">' +
                '<summary class="px-4 py-3 text-sm font-semibold text-gray-700 cursor-pointer hover:bg-gray-50">Add models manually instead (no network needed)</summary>' +
                '<div class="px-4 pb-4 pt-3 border-t border-gray-100">' +
                    '<p class="text-xs text-gray-600 mb-2">Run <code class="bg-gray-100 px-1 rounded">ollama list</code> in Command Prompt to see your installed models. Enter names below:</p>' +
                    '<input id="quick-add-models-input" type="text" placeholder="e.g. llama3.2, mistral, phi4" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-2" />' +
                    '<button id="quick-add-btn" onclick="quickAddOllamaModels()" class="w-full bg-black text-white text-sm py-2 rounded-lg hover:bg-gray-800">Add Models</button>' +
                '</div>' +
            '</details>' +

            '<div class="flex gap-3 justify-end">' +
                '<button onclick="closeModal()" class="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg hover:bg-gray-50">Close</button>' +
                '<button onclick="closeModal();detectOllamaModels()" class="px-4 py-2 bg-black text-white text-sm rounded-lg hover:bg-gray-800">Try Auto-Detect Again</button>' +
            '</div>' +
        '</div>'
    );
}

async function detectOllamaModels() {
    const btn = document.getElementById('detect-ollama-btn');
    const original = btn.textContent;
    btn.textContent = 'Detecting...';
    btn.disabled = true;

    try {
        // Browser-side fetch: the browser (on the same PC as Ollama) can reach localhost:11434
        // directly. The hosted PHP server cannot — it's on a remote machine. So we fetch from
        // the browser, not the server. The only requirement is that Ollama's CORS allows this
        // origin, which is a one-time setup on the user's PC (see showOllamaSetupModal).
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 5000);
        let tags;
        try {
            const res = await fetch(OLLAMA_URL + '/api/tags', { signal: controller.signal });
            clearTimeout(timeout);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            tags = await res.json();
        } catch (fetchErr) {
            clearTimeout(timeout);
            showOllamaSetupModal();
            return;
        }

        const detected = (tags.models ?? []).map(m => ({
            modelId: m.name,
            displayName: m.name,
            description: (
                (m.details?.family ? m.details.family.charAt(0).toUpperCase() + m.details.family.slice(1) + ' — ' : '') +
                (m.details?.parameter_size ? m.details.parameter_size + ' parameters' : 'Local model')
            ).trim()
        }));

        if (detected.length === 0) {
            showToast('No Ollama models found. Pull one first: ollama pull llama3.2', 'warning');
            return;
        }

        const result = await api.post('api/models.php?action=save_ollama_models', { detected });
        if (result.success) {
            const added = result.data?.added?.length ?? 0;
            const total = result.data?.total ?? 0;
            if (added > 0) {
                showToast(`${added} new model(s) added. Reloading...`, 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(`All ${total} detected model(s) already configured.`, 'info');
            }
        } else {
            showToast(result.error?.message || 'Failed to save detected models', 'error');
        }
    } catch (error) {
        console.error('Detect Ollama error:', error);
        showToast('Detection failed. Check browser console for details.', 'error');
    } finally {
        btn.textContent = original;
        btn.disabled = false;
    }
}
</script>

