<?php
/**
 * Admin → Payment Methods management.
 * CRUD for crypto wallets and bank accounts displayed to users at checkout.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2><p class="text-gray-500">You do not have permission to view this page.</p></div>';
    return;
}

require_once INCLUDES_PATH . '/PaymentMethodsAPI.php';
$methodsApi = new PaymentMethodsAPI(new Database(APP_NAME), 'payment_methods');
$methods = $methodsApi->findAll();
?>

<div>
    <div class="flex items-center justify-between gap-4 mb-8">
        <div class="flex items-center gap-4">
            <a href="?page=dashboard" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <h2 class="text-3xl font-bold text-gray-900">Payment Methods</h2>
                <p class="text-gray-500 font-medium tracking-tight">Manual payment options shown to users at checkout.</p>
            </div>
        </div>
        <button type="button" id="new-method-btn" class="px-6 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            New Method
        </button>
    </div>

    <div id="method-form-card" class="hidden bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <form id="method-form" class="p-8 space-y-6">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="flex items-center justify-between">
                <h3 id="method-form-title" class="text-xl font-bold">New Payment Method</h3>
                <button type="button" id="method-form-close" class="text-sm text-gray-500 hover:text-black uppercase tracking-widest font-bold">Cancel</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Type</label>
                    <select name="type" id="method-type" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        <option value="bank">Bank Transfer</option>
                        <option value="crypto">Crypto Wallet</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Label</label>
                    <input type="text" name="label" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="Standard Bank EFT / USDT TRC20">
                </div>

                <!-- Bank-specific fields -->
                <div class="md:col-span-2 bank-fields">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Bank Name</label>
                            <input type="text" name="bankName" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Account Name</label>
                            <input type="text" name="accountName" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Account Number</label>
                            <input type="text" name="accountNumber" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Branch Code</label>
                            <input type="text" name="branchCode" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">SWIFT / IBAN (optional)</label>
                            <input type="text" name="swift" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        </div>
                    </div>
                </div>

                <!-- Crypto-specific fields -->
                <div class="md:col-span-2 crypto-fields hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Network</label>
                            <input type="text" name="network" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="TRC20 / ERC20 / BTC">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">QR Code Image</label>
                            <input type="file" id="qr-file-input" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-black file:text-white hover:file:bg-gray-800">
                            <input type="hidden" name="qrCodeUrl" value="">
                            <p class="mt-1 text-xs text-gray-400">PNG, JPG or WEBP. Max 5 MB.</p>
                            <div id="qr-preview" class="mt-3 hidden flex items-start gap-3">
                                <img id="qr-preview-img" alt="QR preview" class="w-28 h-28 object-cover border border-gray-200 rounded-lg">
                                <button type="button" id="qr-clear-btn" class="text-xs text-red-600 hover:underline">Remove</button>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Wallet Address</label>
                            <input type="text" name="walletAddress" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white font-mono text-xs">
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Instructions</label>
                    <textarea name="instructions" rows="3" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="Reference: use your username. Email proof after payment."></textarea>
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
                    Save Method
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($methods)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm mb-4">No payment methods defined yet.</p>
                <p class="text-gray-400 text-xs">Click "New Method" to add a bank account or crypto wallet.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-widest">
                    <tr>
                        <th class="text-left px-6 py-4">Label</th>
                        <th class="text-left px-6 py-4">Type</th>
                        <th class="text-left px-6 py-4">Details</th>
                        <th class="text-left px-6 py-4">Active</th>
                        <th class="text-right px-6 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $m): ?>
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-6 py-4 font-bold"><?php echo e($m['label'] ?? ''); ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-block text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded bg-gray-100 text-gray-700"><?php echo e($m['type'] ?? ''); ?></span>
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-600">
                                <?php if (($m['type'] ?? '') === 'crypto'): ?>
                                    <?php echo e($m['network'] ?? ''); ?><br>
                                    <span class="font-mono"><?php echo e(substr((string)($m['walletAddress'] ?? ''), 0, 18)); ?>&hellip;</span>
                                <?php else: ?>
                                    <?php echo e($m['bankName'] ?? ''); ?><br>
                                    <?php echo e($m['accountName'] ?? ''); ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if (!empty($m['active'])): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">Yes</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-gray-500 bg-gray-50 border border-gray-200 px-2 py-0.5 rounded-full">No</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button" class="edit-method-btn text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-black mr-3" data-method='<?php echo e(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'>Edit</button>
                                <button type="button" class="delete-method-btn text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-red-600" data-id="<?php echo e($m['id'] ?? ''); ?>" data-label="<?php echo e($m['label'] ?? ''); ?>">Delete</button>
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
    const form = document.getElementById('method-form');
    const card = document.getElementById('method-form-card');
    const newBtn = document.getElementById('new-method-btn');
    const closeBtn = document.getElementById('method-form-close');
    const title = document.getElementById('method-form-title');
    const typeSelect = document.getElementById('method-type');

    function toggleFields() {
        const type = typeSelect.value;
        document.querySelector('.bank-fields').classList.toggle('hidden', type !== 'bank');
        document.querySelector('.crypto-fields').classList.toggle('hidden', type !== 'crypto');
    }

    function appUrl(path) {
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        const base = (typeof APP_URL !== 'undefined' && APP_URL) ? APP_URL.replace(/\/$/, '') + '/' : '';
        return base + path.replace(/^\/+/, '');
    }

    function updateQrPreview(url) {
        const preview = document.getElementById('qr-preview');
        const img = document.getElementById('qr-preview-img');
        if (!preview || !img) return;
        if (url) {
            img.src = appUrl(url) + (url.includes('?') ? '&' : '?') + 't=' + Date.now();
            preview.classList.remove('hidden');
        } else {
            img.removeAttribute('src');
            preview.classList.add('hidden');
        }
    }

    function resetForm(method) {
        form.reset();
        form.elements['id'].value = method?.id || '';
        title.textContent = method?.id ? 'Edit Payment Method' : 'New Payment Method';
        if (method) {
            ['type','label','bankName','accountName','accountNumber','branchCode','swift','network','qrCodeUrl','walletAddress','instructions','sortOrder'].forEach((field) => {
                if (form.elements[field] !== undefined) {
                    form.elements[field].value = method[field] ?? '';
                }
            });
            form.elements['active'].checked = !!method.active;
            updateQrPreview(method.qrCodeUrl || '');
        } else {
            form.elements['active'].checked = true;
            form.elements['type'].value = 'bank';
            updateQrPreview('');
        }
        toggleFields();
    }

    function openForm(method) {
        resetForm(method || null);
        card.classList.remove('hidden');
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function closeForm() { card.classList.add('hidden'); }

    typeSelect.addEventListener('change', toggleFields);
    newBtn.addEventListener('click', () => openForm(null));
    closeBtn.addEventListener('click', closeForm);

    document.querySelectorAll('.edit-method-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            try {
                openForm(JSON.parse(btn.dataset.method));
            } catch (err) {
                showToast('Failed to load method', 'error');
            }
        });
    });

    document.querySelectorAll('.delete-method-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const label = btn.dataset.label || 'this method';
            confirmAction(`Delete "${label}"?`, async () => {
                try {
                    const response = await api.delete('api/payment-methods.php?id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN));
                    if (response && response.success) {
                        showToast('Payment method deleted', 'success');
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
        const payload = {
            type: form.elements['type'].value,
            label: (form.elements['label'].value || '').trim(),
            instructions: form.elements['instructions'].value || '',
            active: form.elements['active'].checked,
            sortOrder: parseInt(form.elements['sortOrder'].value || '0', 10),
            csrf_token: CSRF_TOKEN
        };
        if (payload.type === 'bank') {
            payload.bankName = form.elements['bankName'].value || '';
            payload.accountName = form.elements['accountName'].value || '';
            payload.accountNumber = form.elements['accountNumber'].value || '';
            payload.branchCode = form.elements['branchCode'].value || '';
            payload.swift = form.elements['swift'].value || '';
        } else {
            payload.network = form.elements['network'].value || '';
            payload.walletAddress = form.elements['walletAddress'].value || '';
            payload.qrCodeUrl = form.elements['qrCodeUrl'].value || '';
        }

        if (!payload.label || !payload.type) {
            showToast('Type and label are required', 'error');
            return;
        }

        try {
            let response;
            if (id) {
                response = await api.put('api/payment-methods.php?id=' + encodeURIComponent(id), payload);
            } else {
                response = await api.post('api/payment-methods.php', payload);
            }
            if (response && response.success) {
                showToast(id ? 'Method updated' : 'Method created', 'success');
                setTimeout(() => location.reload(), 300);
            } else {
                showToast((response && (response.error?.message || response.error)) || 'Failed to save', 'error');
            }
        } catch (err) {
            showToast(err.response?.error?.message || err.message || 'Failed to save', 'error');
        }
    });

    // QR file upload — uploads on file selection, stores returned URL in hidden field.
    const qrFileInput = document.getElementById('qr-file-input');
    const qrClearBtn = document.getElementById('qr-clear-btn');
    if (qrFileInput) {
        qrFileInput.addEventListener('change', async (e) => {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) {
                showToast('File too large (max 5 MB)', 'error');
                qrFileInput.value = '';
                return;
            }
            const fd = new FormData();
            fd.append('file', file);
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const response = await fetch(appUrl('api/payment-qr-upload.php'), {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: fd
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    const url = result.data?.url || '';
                    form.elements['qrCodeUrl'].value = url;
                    updateQrPreview(url);
                    showToast('QR uploaded', 'success');
                } else {
                    showToast(result.error?.message || result.error || 'Upload failed', 'error');
                    qrFileInput.value = '';
                }
            } catch (err) {
                showToast(err.message || 'Upload failed', 'error');
                qrFileInput.value = '';
            }
        });
    }
    if (qrClearBtn) {
        qrClearBtn.addEventListener('click', () => {
            form.elements['qrCodeUrl'].value = '';
            updateQrPreview('');
            if (qrFileInput) qrFileInput.value = '';
        });
    }
})();
</script>
