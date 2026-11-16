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

// Card checkout (PayFast, ZAR): offered when PayFast is configured and the plan has a rand price.
require_once INCLUDES_PATH . '/PayFast.php';
$cardAmount = PayFast::isConfigured() ? PayFast::zarAmountForPlan($plan) : null;
$cardFrequency = $cardAmount !== null ? PayFast::frequencyFor((int)($plan['durationDays'] ?? 30)) : null;
$cardRenews = [3 => 'every month', 4 => 'every 3 months', 5 => 'every 6 months', 6 => 'every year'][$cardFrequency[0] ?? 0] ?? '';
$cardCancelled = ($_GET['payfast'] ?? '') === 'cancelled';
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

    <?php if ($cardCancelled): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-6 text-sm text-gray-700" role="status">
            Card payment cancelled. Nothing was charged. You can try again, or pay another way below.
        </div>
    <?php endif; ?>

    <?php if ($cardAmount !== null): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
            <div class="p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h3 class="font-bold text-lg">Pay by card</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        <?php if ($cardRenews !== ''): ?>
                            R<?php echo e(number_format($cardAmount, 2)); ?> <?php echo e($cardRenews); ?>.
                        <?php else: ?>
                            R<?php echo e(number_format($cardAmount, 2)); ?> once, for <?php echo (int)($plan['durationDays'] ?? 30); ?> days.
                        <?php endif; ?>
                        Secure checkout by PayFast. Your plan starts as soon as the payment clears.
                    </p>
                    <ul class="text-xs text-gray-600 mt-3 space-y-1 list-disc pl-4">
                        <li>Charged in South African rand (ZAR). You enter your card on PayFast's page; we never see your card details.</li>
                        <?php if ($cardRenews !== ''): ?>
                            <li>Renews <?php echo e($cardRenews); ?> at R<?php echo e(number_format($cardAmount, 2)); ?> until you cancel. Cancel any time in My Subscription and keep access until the paid period ends.</li>
                            <?php if (($cardFrequency[0] ?? 0) === 6): ?><li>Cancel an annual plan early and you can ask for a refund of the unused full months.</li><?php endif; ?>
                        <?php endif; ?>
                        <li>Full refund if you ask within 7 days of your first payment for a plan.</li>
                    </ul>
                    <label class="flex items-start gap-2 mt-3 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox" id="card-accept-terms" class="mt-0.5 rounded border-gray-300">
                        <span>I have read and accept the <a href="?page=terms" target="_blank" rel="noopener" class="underline">Terms</a> and the <a href="?page=refunds" target="_blank" rel="noopener" class="underline">Refund and Cancellation Policy</a>.</span>
                    </label>
                </div>
                <button type="button" id="card-pay-btn" class="shrink-0 px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition">
                    Pay R<?php echo e(number_format($cardAmount, 2)); ?>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($methods) && $cardAmount === null): ?>
        <div class="bg-yellow-50 border-2 border-yellow-200 rounded-2xl p-6 mb-6">
            <p class="text-sm text-yellow-900 font-bold mb-1">No payment methods available</p>
            <p class="text-sm text-yellow-800">The admin has not configured any payment methods yet. Please check back later or contact support.</p>
        </div>
    <?php elseif (!empty($methods)): ?>
        <?php if ($cardAmount !== null): ?>
            <p class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Or pay by bank transfer or crypto</p>
        <?php endif; ?>
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
                    <label class="flex items-start gap-2 text-xs text-gray-600 cursor-pointer max-w-md"><input type="checkbox" name="acceptTerms" value="1" required class="mt-0.5 rounded border-gray-300"><span>I paid with the method above, and I accept the <a href="?page=terms" target="_blank" rel="noopener" class="underline">Terms</a> and the <a href="?page=refunds" target="_blank" rel="noopener" class="underline">Refund and Cancellation Policy</a>.</span></label>
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
    // Card checkout: ask the server for a signed PayFast form, then post it to PayFast.
    const cardBtn = document.getElementById('card-pay-btn');
    if (cardBtn) {
        cardBtn.addEventListener('click', async () => {
            const accept = document.getElementById('card-accept-terms');
            if (!accept || !accept.checked) {
                showToast('Please accept the Terms and the Refund and Cancellation Policy first.', 'error');
                if (accept) { accept.focus(); }
                return;
            }
            const label = cardBtn.innerHTML;
            cardBtn.disabled = true;
            cardBtn.innerHTML = 'Opening PayFast...';
            try {
                const response = await fetch((typeof APP_URL !== 'undefined' ? APP_URL + '/' : '') + 'api/payfast.php?action=checkout', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ planId: <?php echo json_encode($planId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, acceptTerms: true, csrf_token: CSRF_TOKEN })
                });
                const result = await response.json();
                const data = result && result.data;
                if (!response.ok || !result.success || !data || !/^https:\/\/(www|sandbox)\.payfast\.co\.za\//.test(data.action || '')) {
                    throw new Error((result && result.error && result.error.message) || 'Could not start the card payment');
                }
                const payForm = document.createElement('form');
                payForm.method = 'POST';
                payForm.action = data.action;
                Object.entries(data.fields || {}).forEach(([name, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    payForm.appendChild(input);
                });
                document.body.appendChild(payForm);
                payForm.submit();
            } catch (err) {
                showToast(err.message || 'Could not start the card payment', 'error');
                cardBtn.disabled = false;
                cardBtn.innerHTML = label;
            }
        });
    }

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
