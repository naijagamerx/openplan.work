<?php
/**
 * Mobile Transactions — payment history.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/PaymentsAPI.php';
$api = new PaymentsAPI();
$payments = $api->getByUser(Auth::userId());
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Transactions - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Transactions';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <?php if (empty($payments)): ?>
    <section class="border-2 border-black p-6 text-center">
      <p class="text-sm font-bold text-gray-900 mb-1">No transactions yet</p>
      <p class="text-xs text-gray-500 mb-4">Your payments will show up here.</p>
      <a href="?page=subscription" class="inline-block bg-black text-white px-4 py-2 text-xs font-bold uppercase tracking-widest">Browse Plans</a>
    </section>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($payments as $p):
        $symbol = getCurrencySymbol($p['currency'] ?? 'USD');
        $status = $p['status'] ?? 'pending';
        $statusBg = match ($status) {
          'approved' => 'bg-green-100 text-green-800',
          'rejected' => 'bg-red-100 text-red-800',
          'under_review' => 'bg-blue-100 text-blue-800',
          default => 'bg-yellow-100 text-yellow-800'
        };
      ?>
        <article class="border border-black p-3 space-y-2">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <p class="font-bold text-sm truncate"><?= htmlspecialchars($p['planSnapshot']['name'] ?? '') ?></p>
              <p class="text-[10px] text-gray-500 mt-0.5"><?= htmlspecialchars(date('M j, Y', strtotime((string)($p['submittedAt'] ?? '')))) ?></p>
            </div>
            <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars($status) ?></span>
          </div>

          <div class="flex items-center justify-between text-xs pt-2 border-t border-gray-200">
            <span class="text-gray-500"><?= htmlspecialchars($p['methodSnapshot']['label'] ?? '') ?></span>
            <span class="font-bold"><?= htmlspecialchars($symbol . number_format((float)($p['amount'] ?? 0), 2)) ?></span>
          </div>

          <?php if (!empty($p['rejectionReason'])): ?>
            <p class="text-[10px] text-red-700 bg-red-50 p-2 border border-red-200">Rejection: <?= htmlspecialchars($p['rejectionReason']) ?></p>
          <?php endif; ?>

          <div class="flex gap-2 text-[10px] font-bold uppercase tracking-widest pt-1">
            <a href="<?= rtrim(APP_URL, '/') ?>/api/payment-proof.php?id=<?= htmlspecialchars($p['id'] ?? '') ?>" target="_blank" class="flex-1 text-center border border-black py-1.5">View Proof</a>
            <a href="<?= rtrim(APP_URL, '/') ?>/api/payment-proof.php?id=<?= htmlspecialchars($p['id'] ?? '') ?>&download=1" class="flex-1 text-center border border-black py-1.5">Download</a>
            <?php if ($status === 'approved'): ?>
              <button type="button" onclick='printReceipt(<?= htmlspecialchars(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)' class="flex-1 bg-black text-white py-1.5">Receipt</button>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
function printReceipt(payment) {
  const symbolMap = { USD: '$', EUR: '€', GBP: '£', ZAR: 'R' };
  const symbol = symbolMap[payment.currency] || '$';
  const amount = symbol + parseFloat(payment.amount || 0).toFixed(2);
  const win = window.open('', '_blank', 'width=600,height=800');
  if (!win) return;
  const safe = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  win.document.write('<!DOCTYPE html><html><head><title>Receipt</title><style>body{font-family:system-ui,sans-serif;padding:32px;max-width:560px;margin:0 auto;color:#111}h1{margin:0 0 8px;font-size:24px}.meta{color:#666;font-size:11px;margin-bottom:20px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{text-align:left;padding:6px 0;border-bottom:1px solid #eee;font-size:13px}.total{font-size:20px;font-weight:bold;margin-top:20px}.small{font-size:10px;color:#999;margin-top:24px;text-align:center}@media print{body{padding:20px}}</style></head><body><h1>Receipt</h1><p class="meta">#' + safe(payment.id) + ' · ' + safe(payment.submittedAt) + '</p><table><tr><th>Plan</th><td>' + safe(payment.planSnapshot && payment.planSnapshot.name) + '</td></tr><tr><th>Duration</th><td>' + safe(payment.planSnapshot && payment.planSnapshot.durationDays) + ' days</td></tr><tr><th>Method</th><td>' + safe(payment.methodSnapshot && payment.methodSnapshot.label) + ' (' + safe(payment.methodSnapshot && payment.methodSnapshot.type) + ')</td></tr><tr><th>Status</th><td>' + safe(payment.status) + '</td></tr><tr><th>Approved</th><td>' + safe(payment.reviewedAt || '') + '</td></tr></table><p class="total">Total: ' + amount + '</p><p class="small">Thank you for your payment.</p><scr' + 'ipt>setTimeout(function(){window.print()},300)</scr' + 'ipt></body></html>');
  win.document.close();
}
</script>

</body>
</html>
