<?php
/**
 * Mobile Inventory History — per-product transaction ledger.
 * Loads via API call to match desktop behavior.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$productId = $_GET['productId'] ?? '';
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Inventory History - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
<script>const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'; const APP_URL = '<?= APP_URL ?? '' ?>'; const PRODUCT_ID = <?= json_encode($productId) ?>;</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Inventory History';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <div id="loading" class="border border-black p-6 text-center">
    <p class="text-sm">Loading history...</p>
  </div>

  <div id="error-card" class="hidden border-2 border-red-500 p-4 text-center">
    <p class="text-sm font-bold text-red-700">Product not found</p>
    <a href="?page=inventory" class="inline-block mt-3 bg-black text-white px-4 py-2 text-[10px] font-bold uppercase tracking-widest">Back to Inventory</a>
  </div>

  <div id="content" class="hidden space-y-4">
    <section class="border border-black p-3">
      <p id="product-name" class="font-bold text-base"></p>
      <p id="product-sku" class="text-[10px] text-gray-500 uppercase tracking-widest"></p>
    </section>

    <section class="grid grid-cols-3 gap-2">
      <div class="border border-black p-2">
        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">In</p>
        <p id="total-in" class="text-lg font-black">0</p>
      </div>
      <div class="border border-black p-2">
        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Out</p>
        <p id="total-out" class="text-lg font-black">0</p>
      </div>
      <div class="border border-black p-2">
        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Expense</p>
        <p id="total-expense" class="text-lg font-black">$0</p>
      </div>
    </section>

    <section id="transactions" class="space-y-2"></section>
  </div>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
(async function() {
  if (!PRODUCT_ID) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('error-card').classList.remove('hidden');
    return;
  }
  try {
    const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/inventory.php?action=history&productId=' + encodeURIComponent(PRODUCT_ID), {
      headers: { 'X-CSRF-Token': CSRF_TOKEN }
    });
    const res = await r.json();
    if (!res || !res.success || !res.data || !res.data.product) {
      document.getElementById('loading').classList.add('hidden');
      document.getElementById('error-card').classList.remove('hidden');
      return;
    }
    const product = res.data.product;
    const transactions = res.data.transactions || [];
    document.getElementById('product-name').textContent = product.name || 'Product';
    document.getElementById('product-sku').textContent = 'SKU: ' + (product.sku || '—');
    let totalIn = 0, totalOut = 0, totalExpense = 0;
    transactions.forEach(t => {
      const qty = parseInt(t.quantity || 0, 10);
      if (t.type === 'in' || qty > 0) totalIn += Math.abs(qty);
      if (t.type === 'out' || qty < 0) totalOut += Math.abs(qty);
      if (t.expense) totalExpense += parseFloat(t.expense || 0);
    });
    document.getElementById('total-in').textContent = totalIn.toLocaleString();
    document.getElementById('total-out').textContent = totalOut.toLocaleString();
    document.getElementById('total-expense').textContent = '$' + totalExpense.toFixed(2);

    const list = document.getElementById('transactions');
    list.innerHTML = '';
    if (transactions.length === 0) {
      list.innerHTML = '<div class="border border-gray-300 p-4 text-center text-xs text-gray-500">No transactions yet.</div>';
    } else {
      transactions.forEach(t => {
        const div = document.createElement('div');
        div.className = 'border border-black p-2 flex items-center justify-between gap-2';
        const qty = parseInt(t.quantity || 0, 10);
        const sign = qty >= 0 ? '+' : '';
        div.innerHTML = `<div class="flex-1 min-w-0">
          <p class="text-xs font-bold">${(t.type || 'change').toUpperCase()}</p>
          <p class="text-[10px] text-gray-500">${new Date(t.createdAt || t.date || Date.now()).toLocaleString()}</p>
          ${t.note ? '<p class="text-[10px] text-gray-600 mt-1">' + (t.note + '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + '</p>' : ''}
        </div>
        <p class="text-sm font-bold shrink-0">${sign}${qty.toLocaleString()}</p>`;
        list.appendChild(div);
      });
    }
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('content').classList.remove('hidden');
  } catch (e) {
    document.getElementById('loading').classList.add('hidden');
    document.getElementById('error-card').classList.remove('hidden');
  }
})();
</script>

</body>
</html>
