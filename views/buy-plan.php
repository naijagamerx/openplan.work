<?php
/**
 * Buy Plan — checkout flow.
 * 1. Pick a payment method (bank or crypto).
 * 2. View its details (account number / wallet address).
 * 3. Click "I have paid" + upload proof (PNG/JPG/WEBP/PDF).
 * 4. Wait for admin approval.
 */

require_once INCLUDES_PATH . '/PlansAPI.php';
require_once INCLUDES_PATH . '/PaymentMethodsAPI.php';

$planId = (string)($_GET['id'] ?? '');
if ($planId === '') {
    header('Location: ?page=subscription');
    exit;
}

$globalDb = new Database(APP_NAME);
$plansApi = new PlansAPI($globalDb, 'plans');
$methodsApi = new PaymentMethodsAPI($globalDb, 'payment_methods');

$plan = $plansApi->find($planId);
if (!$plan || empty($plan['active'])) {
    header('Location: ?page=subscription');
    exit;
}
$methods = $methodsApi->findAll(['activeOnly' => true]);
$symbol = getCurrencySymbol($plan['currency'] ?? 'USD');
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=subscription" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Buy <?php echo e($plan['name'] ?? ''); ?></h2>
            <p class="text-gray-500 font-medium tracking-tight">
                <?php echo e($symbol . number_format((float)($plan['price'] ?? 0), 2)); ?>
                &middot; <?php echo (int)($plan['durationDays'] ?? 30); ?> days
            </p>
        </div>
    </div>

    <?php if (empty($methods)): ?>
        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-6 mb-6">
            <p class="text-sm text-yellow-900 font-bold mb-1">No payment methods available</p>
            <p class="text-sm text-yellow-800">The admin has not configured any payment methods yet. Please check back later or contact support.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-bold text-lg">1. Choose Payment Method</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($methods as $idx => $m): ?>
                        <button type="button"
                            class="method-btn w-full text-left p-6 hover:bg-gray-50 transition flex items-start gap-3"
                            data-method='<?php echo e(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>'
                            data-id="<?php echo e($m['id'] ?? ''); ?>">
                            <input type="radio" name="methodRadio" value="<?php echo e($m['id'] ?? ''); ?>" <?php echo $idx === 0 ? 'checked' : ''; ?> class="mt-1 h-4 w-4 text-black focus:ring-black">
                            <div>
                                <p class="font-bold"><?php echo e($m['label'] ?? ''); ?></p>
                                <span class="inline-block mt-1 text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded bg-gray-100 text-gray-700"><?php echo e($m['type'] ?? ''); ?></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-gray-100">
                    <h3 class="font-bold text-lg">2. Payment Details</h3>
                </div>
                <div id="method-details" class="p-6 text-sm text-gray-700 space-y-3">
                    <p class="text-gray-400">Select a payment method on the left to see details.</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">
            <div class="p-6 border-b border-gray-100">
                <h3 class="font-bold text-lg">3. Upload Proof of Payment</h3>
                <p class="text-sm text-gray-500 mt-1">PNG, JPG, WEBP, or PDF &middot; max 10MB</p>
            </div>
            <form id="payment-form" enctype="multipart/form-data" class="p-6 space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                <input type="hidden" name="planId" value="<?php echo e($planId); ?>">
                <input type="hidden" name="methodId" id="selected-method-id" value="<?php echo e($methods[0]['id'] ?? ''); ?>">

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Proof file</label>
                    <input type="file" name="proof" required accept=".png,.jpg,.jpeg,.webp,.pdf,image/png,image/jpeg,image/webp,application/pdf" class="w-full text-sm">
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500">By submitting, you confirm payment was made manually using the selected method.</p>
                    <button type="submit" id="submit-btn" class="px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
                        I Have Paid
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const methodButtons = document.querySelectorAll('.method-btn');
    const detailsEl = document.getElementById('method-details');
    const hiddenId = document.getElementById('selected-method-id');
    const form = document.getElementById('payment-form');
    const submitBtn = document.getElementById('submit-btn');

    function renderDetails(method) {
        if (!method) {
            detailsEl.innerHTML = '<p class="text-gray-400">Select a payment method on the left.</p>';
            return;
        }
        const escape = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        let html = '';
        if (method.type === 'bank') {
            html += `<dl class="grid grid-cols-3 gap-y-2 text-sm">`;
            const rows = [
                ['Bank', method.bankName],
                ['Account name', method.accountName],
                ['Account number', method.accountNumber],
                ['Branch code', method.branchCode],
                ['SWIFT/IBAN', method.swift]
            ];
            rows.forEach(([k, v]) => {
                if (v) html += `<dt class="font-bold text-gray-500 uppercase text-[10px] tracking-widest">${escape(k)}</dt><dd class="col-span-2 font-mono text-sm">${escape(v)}</dd>`;
            });
            html += `</dl>`;
        } else {
            html += `<dl class="grid grid-cols-3 gap-y-2 text-sm">`;
            if (method.network) {
                html += `<dt class="font-bold text-gray-500 uppercase text-[10px] tracking-widest">Network</dt><dd class="col-span-2 font-mono text-xs break-all">${escape(method.network)}</dd>`;
            }
            html += `</dl>`;
            if (method.walletAddress) {
                const walletEscaped = escape(method.walletAddress);
                const walletAttr = method.walletAddress.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                html += `
                <div class="mt-4">
                    <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Wallet address</p>
                    <div class="flex items-center gap-2 border border-gray-300 rounded-lg bg-gray-50 px-3 py-2">
                        <code class="flex-1 font-mono text-xs break-all">${walletEscaped}</code>
                        <button type="button"
                                data-wallet="${walletAttr}"
                                onclick="copyWallet(this)"
                                class="p-1.5 rounded-md text-gray-600 hover:bg-gray-200 transition shrink-0"
                                title="Copy address"
                                aria-label="Copy wallet address">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>
                </div>`;
            }
            if (method.qrCodeUrl) {
                html += `<div class="mt-4"><img src="${escape(method.qrCodeUrl)}" alt="QR code" class="w-40 h-40 border border-gray-200 rounded-lg"></div>`;
            }
        }
        if (method.instructions) {
            html += `<div class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-lg text-xs whitespace-pre-line">${escape(method.instructions)}</div>`;
        }
        detailsEl.innerHTML = html || '<p class="text-gray-400">No additional details.</p>';
    }

    methodButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            try {
                const method = JSON.parse(btn.dataset.method);
                methodButtons.forEach((b) => {
                    const radio = b.querySelector('input[type=radio]');
                    if (radio) radio.checked = (b === btn);
                });
                hiddenId.value = btn.dataset.id || '';
                renderDetails(method);
            } catch (err) {
                showToast('Failed to load method', 'error');
            }
        });
    });

    // Auto-render first method
    if (methodButtons.length > 0) {
        try { renderDetails(JSON.parse(methodButtons[0].dataset.method)); } catch (e) {}
    }

    window.copyWallet = function(btn) {
        const text = btn.getAttribute('data-wallet') || '';
        const finish = (ok) => {
            if (!ok) {
                showToast('Copy failed', 'error');
                return;
            }
            showToast('Wallet address copied', 'success');
            btn.classList.add('text-green-600');
            setTimeout(() => btn.classList.remove('text-green-600'), 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(() => finish(true)).catch(() => finish(false));
        } else {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                document.body.removeChild(ta);
                finish(ok);
            } catch (e) { finish(false); }
        }
    };

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!hiddenId.value) {
                showToast('Please select a payment method', 'error');
                return;
            }
            submitBtn.disabled = true;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Submitting...';

            try {
                const fd = new FormData(form);
                const response = await fetch((typeof APP_URL !== 'undefined' ? APP_URL + '/' : '') + 'api/payments.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': CSRF_TOKEN },
                    body: fd
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    showToast('Payment submitted. Awaiting admin approval.', 'success');
                    setTimeout(() => { window.location.href = '?page=transactions'; }, 800);
                } else {
                    showToast(result.error?.message || result.error || 'Failed to submit', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (err) {
                showToast('Failed to submit payment', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
})();
</script>
