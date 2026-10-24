<?php
/**
 * Admin → SaaS Plans management.
 * List, create, edit, delete subscription plans.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2><p class="text-gray-500">You do not have permission to view this page.</p></div>';
    return;
}

require_once INCLUDES_PATH . '/PlansAPI.php';
$plansApi = new PlansAPI(new Database(APP_NAME), 'plans');
$plans = $plansApi->findAll();
?>

<div>
    <div class="flex items-center justify-between gap-4 mb-8">
        <div class="flex items-center gap-4">
            <a href="?page=dashboard" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900">Subscription Plans</h2>
                <p class="text-gray-500 font-medium tracking-tight">Manage the SaaS plans your users can buy.</p>
            </div>
        </div>
        <button type="button" id="new-plan-btn" class="px-6 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            New Plan
        </button>
    </div>

    <div id="plan-form-card" class="hidden bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <form id="plan-form" class="p-8 space-y-6">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="flex items-center justify-between">
                <h3 id="plan-form-title" class="text-xl font-bold">New Plan</h3>
                <button type="button" id="plan-form-close" class="text-sm text-gray-500 hover:text-black uppercase tracking-widest font-bold">Cancel</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Name</label>
                    <input type="text" name="name" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="Pro Monthly">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Price</label>
                    <input type="number" name="price" min="0" step="0.01" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="9.99">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Currency</label>
                    <select name="currency" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="ZAR">ZAR</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Duration (days)</label>
                    <input type="number" name="durationDays" min="1" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="30">
                    <p class="mt-1 text-xs text-gray-500">7 = weekly, 30 = monthly, 365 = yearly</p>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Monthly Request Cap</label>
                    <input type="number" name="monthlyRequestCap" min="0" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="1000">
                    <p class="mt-1 text-xs text-gray-500">0 = unlimited</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Providers Unlocked</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php foreach (['groq' => 'Groq', 'openrouter' => 'OpenRouter', 'gemini' => 'Gemini', 'ollama' => 'Ollama'] as $val => $label): ?>
                            <label class="flex items-center gap-2 px-3 py-2 border-2 border-gray-100 rounded-xl cursor-pointer hover:border-gray-300">
                                <input type="checkbox" name="providers[]" value="<?php echo $val; ?>" class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                                <span class="text-sm font-medium"><?php echo $label; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Description</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="What does this plan include?"></textarea>
                </div>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer mt-2">
                        <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-gray-300 text-black focus:ring-black">
                        <span class="text-sm font-bold uppercase tracking-widest text-gray-700">Active</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Sort Order</label>
                    <input type="number" name="sortOrder" value="0" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                </div>
            </div>

            <div class="flex justify-end pt-6 border-t-2 border-gray-100">
                <button type="submit" class="px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
                    Save Plan
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($plans)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm mb-4">No plans defined yet.</p>
                <p class="text-gray-400 text-xs">Click "New Plan" to create your first subscription plan.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-widest">
                    <tr>
                        <th class="text-left px-6 py-4">Name</th>
                        <th class="text-left px-6 py-4">Price</th>
                        <th class="text-left px-6 py-4">Duration</th>
                        <th class="text-left px-6 py-4">Providers</th>
                        <th class="text-left px-6 py-4">Cap</th>
                        <th class="text-left px-6 py-4">Active</th>
                        <th class="text-right px-6 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold"><?php echo e($plan['name'] ?? ''); ?></td>
                            <td class="px-6 py-4"><?php echo e(getCurrencySymbol($plan['currency'] ?? 'USD') . number_format((float)($plan['price'] ?? 0), 2)); ?></td>
                            <td class="px-6 py-4"><?php echo (int)($plan['durationDays'] ?? 0); ?> days</td>
                            <td class="px-6 py-4">
                                <?php $provs = $plan['providers'] ?? []; ?>
                                <?php if (empty($provs)): ?>
                                    <span class="text-gray-400">none</span>
                                <?php else: foreach ($provs as $p): ?>
                                    <span class="inline-block text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 mr-1 mb-1 rounded bg-gray-100 text-gray-700"><?php echo e($p); ?></span>
                                <?php endforeach; endif; ?>
                            </td>
                            <td class="px-6 py-4"><?php echo (int)($plan['monthlyRequestCap'] ?? 0) > 0 ? (int)$plan['monthlyRequestCap'] : 'unlimited'; ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($plan['active'])): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">Yes</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-gray-500 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" class="edit-plan-btn text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-black mr-3" data-plan='<?php echo e(json_encode($plan, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>Edit</button>
                                <button type="button" class="delete-plan-btn text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-red-600" data-id="<?php echo e($plan['id'] ?? ''); ?>" data-name="<?php echo e($plan['name'] ?? ''); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('plan-form');
    const card = document.getElementById('plan-form-card');
    const newBtn = document.getElementById('new-plan-btn');
    const closeBtn = document.getElementById('plan-form-close');
    const title = document.getElementById('plan-form-title');

    function resetForm(plan) {
        form.reset();
        form.elements['id'].value = plan?.id || '';
        title.textContent = plan?.id ? 'Edit Plan' : 'New Plan';
        if (plan) {
            form.elements['name'].value = plan.name || '';
            form.elements['price'].value = plan.price ?? '';
            form.elements['currency'].value = plan.currency || 'USD';
            form.elements['durationDays'].value = plan.durationDays || 30;
            form.elements['monthlyRequestCap'].value = plan.monthlyRequestCap || 0;
            form.elements['description'].value = plan.description || '';
            form.elements['active'].checked = !!plan.active;
            form.elements['sortOrder'].value = plan.sortOrder || 0;
            const providers = plan.providers || [];
            form.querySelectorAll('input[name="providers[]"]').forEach((cb) => {
                cb.checked = providers.includes(cb.value);
            });
        } else {
            form.elements['active'].checked = true;
        }
    }

    function openForm(plan) {
        resetForm(plan || null);
        card.classList.remove('hidden');
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function closeForm() {
        card.classList.add('hidden');
    }

    newBtn.addEventListener('click', () => openForm(null));
    closeBtn.addEventListener('click', closeForm);

    document.querySelectorAll('.edit-plan-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            try {
                const plan = JSON.parse(btn.dataset.plan);
                openForm(plan);
            } catch (err) {
                showToast('Failed to load plan data', 'error');
            }
        });
    });

    document.querySelectorAll('.delete-plan-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name || 'this plan';
            confirmAction(`Delete "${name}"? Existing subscribers keep access until expiry.`, async () => {
                try {
                    const response = await api.delete('api/plans.php?id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN));
                    if (response && response.success) {
                        showToast('Plan deleted', 'success');
                        setTimeout(() => location.reload(), 300);
                    } else {
                        showToast((response && (response.error?.message || response.error)) || 'Failed to delete', 'error');
                    }
                } catch (err) {
                    showToast(err.response?.error?.message || 'Failed to delete', 'error');
                }
            });
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = form.elements['id'].value;
        const providers = Array.from(form.querySelectorAll('input[name="providers[]"]:checked')).map((c) => c.value);
        const payload = {
            name: (form.elements['name'].value || '').trim(),
            price: parseFloat(form.elements['price'].value || '0'),
            currency: form.elements['currency'].value || 'USD',
            durationDays: parseInt(form.elements['durationDays'].value || '30', 10),
            monthlyRequestCap: parseInt(form.elements['monthlyRequestCap'].value || '0', 10),
            providers: providers,
            description: form.elements['description'].value || '',
            active: form.elements['active'].checked,
            sortOrder: parseInt(form.elements['sortOrder'].value || '0', 10),
            csrf_token: CSRF_TOKEN
        };

        try {
            let response;
            if (id) {
                response = await api.put('api/plans.php?id=' + encodeURIComponent(id), payload);
            } else {
                response = await api.post('api/plans.php', payload);
            }
            if (response && response.success) {
                showToast(id ? 'Plan updated' : 'Plan created', 'success');
                setTimeout(() => location.reload(), 300);
            } else {
                showToast((response && (response.error?.message || response.error)) || 'Failed to save', 'error');
            }
        } catch (err) {
            showToast(err.response?.error?.message || err.message || 'Failed to save', 'error');
        }
    });
})();
</script>
