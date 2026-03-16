<?php
/**
 * Model Settings View
 */
$db = new Database(getMasterPassword(), Auth::userId());
$models = $db->load('models');
?>

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
</script>

