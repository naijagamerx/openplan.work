<?php
/**
 * Mobile Buy Plan — checkout flow.
 * Mirrors views/buy-plan.php with mobile-first monochrome layout.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

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
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Buy <?= htmlspecialchars($plan['name'] ?? '') ?> - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">
  @layer base { body { @apply bg-white text-black font-display antialiased; } }
  .touch-target { min-height: 44px; }
</style>
<script>const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'; const APP_URL = '<?= APP_URL ?? '' ?>';</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Buy Plan';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-5 text-zinc-900 dark:text-zinc-100">

  <section class="border-2 border-black p-4">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Plan</p>
    <h2 class="text-2xl font-black mt-1"><?= htmlspecialchars($plan['name'] ?? '') ?></h2>
    <p class="text-sm text-gray-700 mt-1">
      <span class="font-bold"><?= htmlspecialchars($symbol . number_format((float)($plan['price'] ?? 0), 2)) ?></span>
      &middot; <?= (int)($plan['durationDays'] ?? 30) ?> days
    </p>
    <?php if (!empty($plan['providers'])): ?>
      <p class="text-xs text-gray-500 mt-2">Providers: <?= htmlspecialchars(implode(', ', $plan['providers'])) ?></p>
    <?php endif; ?>
  </section>

  <?php if (empty($methods)): ?>
    <section class="border-2 border-yellow-400 bg-yellow-50 p-4">
      <p class="text-sm font-bold text-yellow-900">No payment methods available</p>
      <p class="text-xs text-yellow-800 mt-1">Admin has not configured any payment methods yet.</p>
    </section>
  <?php else: ?>

    <section>
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">1. Choose Payment Method</p>
      <div class="border border-black divide-y divide-black">
        <?php foreach ($methods as $idx => $m): ?>
          <button type="button"
            class="method-btn w-full text-left p-3 flex items-start gap-3 touch-target hover:bg-gray-50 active:bg-gray-100"
            data-method='<?= htmlspecialchars(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
            data-id="<?= htmlspecialchars($m['id'] ?? '') ?>">
            <input type="radio" name="methodRadio" value="<?= htmlspecialchars($m['id'] ?? '') ?>" <?= $idx === 0 ? 'checked' : '' ?> class="mt-1 h-4 w-4 accent-black flex-shrink-0">
            <div class="flex-1 min-w-0">
              <p class="font-bold text-sm truncate"><?= htmlspecialchars($m['label'] ?? '') ?></p>
              <span class="inline-block mt-1 text-[9px] font-bold uppercase tracking-widest px-2 py-0.5 bg-black text-white"><?= htmlspecialchars($m['type'] ?? '') ?></span>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    </section>

    <section>
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">2. Payment Details</p>
      <div id="method-details" class="border border-black p-3 text-sm text-gray-700 min-h-[80px]">
        <p class="text-gray-400 text-xs">Select a payment method.</p>
      </div>
    </section>

    <section>
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">3. Upload Proof</p>
      <form id="payment-form" enctype="multipart/form-data" class="border border-black p-3 space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <input type="hidden" name="planId" value="<?= htmlspecialchars($planId) ?>">
        <input type="hidden" name="methodId" id="selected-method-id" value="<?= htmlspecialchars($methods[0]['id'] ?? '') ?>">

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Proof file (PNG / JPG / WEBP / PDF, max 10MB)</label>
          <input type="file" name="proof" required accept=".png,.jpg,.jpeg,.webp,.pdf,image/png,image/jpeg,image/webp,application/pdf" class="w-full text-xs">
        </div>

        <p class="text-[10px] text-gray-500">By submitting, you confirm payment was made manually using the selected method.</p>

        <button type="submit" id="submit-btn" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform touch-target">
          I Have Paid
        </button>
      </form>
    </section>

  <?php endif; ?>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
(function() {
  const methodButtons = document.querySelectorAll('.method-btn');
  const detailsEl = document.getElementById('method-details');
  const hiddenId = document.getElementById('selected-method-id');
  const form = document.getElementById('payment-form');
  const submitBtn = document.getElementById('submit-btn');
  const escape = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  function renderDetails(method) {
    if (!method) { detailsEl.innerHTML = '<p class="text-gray-400 text-xs">Select a payment method.</p>'; return; }
    let html = '<dl class="space-y-1.5 text-xs">';
    const addRow = (k, v) => { if (v) html += `<dt class="font-bold uppercase tracking-widest text-[9px] text-gray-500">${escape(k)}</dt><dd class="font-mono break-all">${escape(v)}</dd>`; };
    if (method.type === 'bank') {
      addRow('Bank', method.bankName);
      addRow('Account name', method.accountName);
      addRow('Account number', method.accountNumber);
      addRow('Branch code', method.branchCode);
      addRow('SWIFT/IBAN', method.swift);
    } else {
      addRow('Network', method.network);
    }
    html += '</dl>';
    if (method.type !== 'bank' && method.walletAddress) {
      const walletEsc = escape(method.walletAddress);
      const walletAttr = method.walletAddress.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      html += `<div class="mt-3"><p class="text-[9px] font-bold uppercase tracking-widest text-gray-500 mb-1">Wallet address</p>
        <div class="flex items-center gap-2 border border-black bg-gray-50 px-2 py-1.5">
          <code class="flex-1 font-mono text-[10px] break-all">${walletEsc}</code>
          <button type="button" data-wallet="${walletAttr}" onclick="copyWallet(this)" class="p-1.5 active:bg-gray-200 shrink-0" aria-label="Copy">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
          </button>
        </div></div>`;
    }
    if (method.qrCodeUrl) {
      html += `<div class="mt-3"><img src="${escape(method.qrCodeUrl)}" alt="QR" class="w-32 h-32 border border-black"></div>`;
    }
    if (method.instructions) {
      html += `<div class="mt-3 p-2 bg-gray-50 border border-gray-300 text-[10px] whitespace-pre-line">${escape(method.instructions)}</div>`;
    }
    detailsEl.innerHTML = html;
  }

  methodButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      try {
        const method = JSON.parse(btn.dataset.method);
        methodButtons.forEach((b) => { const r = b.querySelector('input[type=radio]'); if (r) r.checked = (b === btn); });
        hiddenId.value = btn.dataset.id || '';
        renderDetails(method);
      } catch (e) { /* ignore */ }
    });
  });

  if (methodButtons.length > 0) {
    try { renderDetails(JSON.parse(methodButtons[0].dataset.method)); } catch (e) {}
  }

  window.copyWallet = function(btn) {
    const text = btn.getAttribute('data-wallet') || '';
    const finish = (ok) => { if (window.Mobile && Mobile.ui && Mobile.ui.toast) Mobile.ui.toast(ok ? 'Wallet copied' : 'Copy failed'); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(() => finish(true)).catch(() => finish(false));
    } else {
      try { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); const ok = document.execCommand('copy'); document.body.removeChild(ta); finish(ok); }
      catch (e) { finish(false); }
    }
  };

  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!hiddenId.value) { alert('Pick a payment method first'); return; }
      submitBtn.disabled = true;
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = 'Submitting...';
      try {
        const fd = new FormData(form);
        const response = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/payments.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': CSRF_TOKEN },
          body: fd
        });
        const result = await response.json();
        if (response.ok && result.success) {
          alert('Payment submitted. Awaiting admin approval.');
          window.location.href = '?page=transactions';
        } else {
          alert((result.error && result.error.message) || result.error || 'Failed to submit');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      } catch (err) {
        alert('Failed to submit payment');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }
})();
</script>

</body>
</html>
