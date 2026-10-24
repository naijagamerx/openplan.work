<?php
/**
 * Mobile Admin Payment Methods — bank + crypto wallet configuration.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/PaymentMethodsAPI.php';
$api = new PaymentMethodsAPI(new Database(APP_NAME), 'payment_methods');
$methods = $api->findAll();

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Payment Methods - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script>const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'; const APP_URL = '<?= APP_URL ?? '' ?>';</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Payment Methods';
$leftAction = 'back';
$rightAction = 'add';
$rightTarget = "openMethodForm()";
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-3 text-zinc-900 dark:text-zinc-100">

  <section id="method-form-card" class="hidden border-2 border-black p-3">
    <form id="method-form" class="space-y-3">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

      <div class="flex items-center justify-between">
        <h3 id="method-form-title" class="text-sm font-bold uppercase tracking-widest">New Method</h3>
        <button type="button" onclick="document.getElementById('method-form-card').classList.add('hidden')" class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cancel</button>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Label</label>
        <input type="text" name="label" required class="w-full px-3 py-2 border border-black text-sm" placeholder="Standard Bank">
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Type</label>
        <select name="type" onchange="toggleMethodType()" class="w-full px-3 py-2 border border-black text-sm">
          <option value="bank">Bank</option>
          <option value="crypto">Crypto</option>
        </select>
      </div>

      <div id="bank-fields" class="space-y-2">
        <input type="text" name="bankName" placeholder="Bank name" class="w-full px-3 py-2 border border-black text-sm">
        <input type="text" name="accountName" placeholder="Account name" class="w-full px-3 py-2 border border-black text-sm">
        <input type="text" name="accountNumber" placeholder="Account number" class="w-full px-3 py-2 border border-black text-sm">
        <input type="text" name="branchCode" placeholder="Branch / Sort code" class="w-full px-3 py-2 border border-black text-sm">
        <input type="text" name="swift" placeholder="SWIFT / IBAN" class="w-full px-3 py-2 border border-black text-sm">
      </div>

      <div id="crypto-fields" class="hidden space-y-2">
        <input type="text" name="network" placeholder="Network (e.g. USDT TRC20)" class="w-full px-3 py-2 border border-black text-sm">
        <input type="text" name="walletAddress" placeholder="Wallet address" class="w-full px-3 py-2 border border-black text-sm font-mono">
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Instructions (shown to user)</label>
        <textarea name="instructions" rows="3" class="w-full px-3 py-2 border border-black text-sm" placeholder="Optional notes"></textarea>
      </div>

      <label class="flex items-center gap-2 text-xs">
        <input type="checkbox" name="active" value="1" checked class="accent-black">
        Active (shown at checkout)
      </label>

      <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Save</button>
    </form>
  </section>

  <?php if (empty($methods)): ?>
    <section class="border-2 border-black p-6 text-center"><p class="text-sm font-bold">No payment methods yet</p><p class="text-xs text-gray-500 mt-1">Tap + to add one.</p></section>
  <?php else: ?>
    <?php foreach ($methods as $m): ?>
      <article class="border border-black p-3 space-y-2">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm truncate"><?= htmlspecialchars($m['label'] ?? '') ?></p>
            <p class="text-[10px] uppercase tracking-widest text-gray-500"><?= htmlspecialchars($m['type'] ?? '') ?></p>
          </div>
          <span class="<?= !empty($m['active']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest"><?= !empty($m['active']) ? 'Active' : 'Inactive' ?></span>
        </div>
        <?php if (($m['type'] ?? '') === 'bank' && !empty($m['accountNumber'])): ?>
          <p class="text-[10px] text-gray-600 font-mono break-all"><?= htmlspecialchars($m['bankName'] ?? '') ?> &middot; <?= htmlspecialchars($m['accountNumber']) ?></p>
        <?php elseif (!empty($m['walletAddress'])): ?>
          <p class="text-[10px] text-gray-600 font-mono break-all"><?= htmlspecialchars($m['network'] ?? '') ?> &middot; <?= htmlspecialchars($m['walletAddress']) ?></p>
        <?php endif; ?>
        <div class="flex gap-2 pt-1">
          <button type="button" onclick='editMethod(<?= htmlspecialchars(json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)' class="flex-1 border border-black py-1.5 text-[10px] font-bold uppercase tracking-widest active:bg-gray-100">Edit</button>
          <button type="button" onclick='deleteMethod("<?= htmlspecialchars($m['id']) ?>")' class="flex-1 border border-red-500 text-red-600 py-1.5 text-[10px] font-bold uppercase tracking-widest active:bg-red-50">Delete</button>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
function toggleMethodType() {
  const t = document.querySelector('#method-form [name=type]').value;
  document.getElementById('bank-fields').classList.toggle('hidden', t !== 'bank');
  document.getElementById('crypto-fields').classList.toggle('hidden', t !== 'crypto');
}

function openMethodForm() {
  const card = document.getElementById('method-form-card');
  card.classList.remove('hidden');
  card.querySelector('[name=id]').value = '';
  card.querySelector('form').reset();
  card.querySelector('[name=type]').value = 'bank';
  toggleMethodType();
  document.getElementById('method-form-title').textContent = 'New Method';
  card.scrollIntoView({behavior:'smooth'});
}

function editMethod(m) {
  const card = document.getElementById('method-form-card');
  card.classList.remove('hidden');
  ['id','label','type','bankName','accountName','accountNumber','branchCode','swift','network','walletAddress','instructions'].forEach(k => {
    const el = card.querySelector('[name=' + k + ']');
    if (el) el.value = m[k] || '';
  });
  card.querySelector('[name=active]').checked = !!m.active;
  toggleMethodType();
  document.getElementById('method-form-title').textContent = 'Edit Method';
  card.scrollIntoView({behavior:'smooth'});
}

async function deleteMethod(id) {
  if (!confirm('Delete this payment method?')) return;
  const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/payment-methods.php?id=' + encodeURIComponent(id), {
    method: 'DELETE', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify({ csrf_token: CSRF_TOKEN })
  });
  const res = await r.json();
  if (res && res.success) location.reload();
  else alert((res.error && res.error.message) || 'Failed');
}

document.getElementById('method-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const payload = {
    label: fd.get('label'),
    type: fd.get('type'),
    bankName: fd.get('bankName'),
    accountName: fd.get('accountName'),
    accountNumber: fd.get('accountNumber'),
    branchCode: fd.get('branchCode'),
    swift: fd.get('swift'),
    network: fd.get('network'),
    walletAddress: fd.get('walletAddress'),
    instructions: fd.get('instructions'),
    active: fd.get('active') ? true : false,
    csrf_token: CSRF_TOKEN
  };
  const id = fd.get('id');
  const url = (APP_URL ? APP_URL + '/' : '') + 'api/payment-methods.php' + (id ? '?id=' + encodeURIComponent(id) : '');
  const method = id ? 'PUT' : 'POST';
  const r = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN }, body: JSON.stringify(payload) });
  const res = await r.json();
  if (res && res.success) location.reload();
  else alert((res.error && res.error.message) || 'Failed');
});
</script>

</body>
</html>
