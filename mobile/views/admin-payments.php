<?php
/**
 * Mobile Admin Payments — approve / reject queue.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/PaymentsAPI.php';
$api = new PaymentsAPI();

$filter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'under_review', 'approved', 'rejected', 'all'];
if (!in_array($filter, $validStatuses, true)) { $filter = 'pending'; }
$payments = $filter === 'all' ? $api->getAll() : $api->getAll($filter);

$usersDb = new Database(APP_NAME);
$users = $usersDb->load('users', true);
$userMap = [];
foreach ($users as $u) { $userMap[$u['id'] ?? ''] = $u; }

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Admin Payments - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Payments';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-3 text-zinc-900 dark:text-zinc-100">

  <div class="flex gap-1 overflow-x-auto no-scrollbar">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $val => $label):
      $active = ($filter === $val);
    ?>
      <a href="?page=admin-payments&status=<?= $val ?>" class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest shrink-0 <?= $active ? 'bg-black text-white' : 'border border-black' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($payments)): ?>
    <section class="border-2 border-black p-6 text-center"><p class="text-sm font-bold">No payments in this view</p></section>
  <?php else: ?>
    <?php foreach ($payments as $p):
      $user = $userMap[$p['userId'] ?? ''] ?? null;
      $userLabel = $user ? ($user['email'] ?? $user['name'] ?? 'Unknown') : 'Unknown user';
      $symbol = getCurrencySymbol($p['currency'] ?? 'USD');
      $proofMime = $p['proof']['mimeType'] ?? '';
      $isPdf = ($proofMime === 'application/pdf');
      $proofUrl = rtrim(APP_URL, '/') . '/api/payment-proof.php?id=' . urlencode($p['id'] ?? '');
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
            <p class="font-bold text-sm truncate"><?= htmlspecialchars($p['planSnapshot']['name'] ?? '(plan)') ?></p>
            <p class="text-[10px] text-gray-500 mt-0.5 truncate"><?= htmlspecialchars($userLabel) ?></p>
          </div>
          <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars($status) ?></span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-[10px] pt-2 border-t border-gray-200">
          <div><span class="text-gray-500 uppercase tracking-widest">Amount</span><br><span class="font-bold"><?= htmlspecialchars($symbol . number_format((float)($p['amount'] ?? 0), 2)) ?></span></div>
          <div><span class="text-gray-500 uppercase tracking-widest">Method</span><br><?= htmlspecialchars($p['methodSnapshot']['label'] ?? '') ?></div>
          <div class="col-span-2"><span class="text-gray-500 uppercase tracking-widest">Submitted</span><br><?= htmlspecialchars(date('M j, Y g:i a', strtotime((string)($p['submittedAt'] ?? '')))) ?></div>
          <?php if (!empty($p['rejectionReason'])): ?>
            <div class="col-span-2 text-red-700"><span class="uppercase tracking-widest font-bold">Reason</span><br><?= htmlspecialchars($p['rejectionReason']) ?></div>
          <?php endif; ?>
        </div>

        <div class="pt-2 border-t border-gray-200">
          <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Proof</p>
          <?php if ($isPdf): ?>
            <a href="<?= htmlspecialchars($proofUrl) ?>" target="_blank" class="inline-block border border-black px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest">View PDF</a>
          <?php else: ?>
            <a href="<?= htmlspecialchars($proofUrl) ?>" target="_blank"><img src="<?= htmlspecialchars($proofUrl) ?>" alt="Proof" class="w-full max-h-48 object-contain border border-black"></a>
          <?php endif; ?>
          <a href="<?= htmlspecialchars($proofUrl) ?>&download=1" class="block mt-1 text-[10px] text-gray-500 underline">Download original</a>
        </div>

        <?php if ($status === 'pending'): ?>
          <div class="flex gap-2 pt-1">
            <button type="button" data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" class="approve-btn flex-1 bg-black text-white py-2 text-[10px] font-bold uppercase tracking-widest active:scale-95 transition-transform">Approve</button>
            <button type="button" data-id="<?= htmlspecialchars($p['id'] ?? '') ?>" class="reject-btn flex-1 border border-red-500 text-red-600 py-2 text-[10px] font-bold uppercase tracking-widest active:bg-red-50">Reject</button>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
(function() {
  const post = async (url, body) => {
    const r = await fetch((APP_URL ? APP_URL + '/' : '') + url, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify(body)
    });
    return r.json();
  };
  document.querySelectorAll('.approve-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Approve this payment? A subscription will be activated for the user.')) return;
      const res = await post('api/payments.php?id=' + encodeURIComponent(btn.dataset.id) + '&action=approve', { csrf_token: CSRF_TOKEN, note: '' });
      if (res && res.success) location.reload();
      else alert((res.error && res.error.message) || res.error || 'Failed');
    });
  });
  document.querySelectorAll('.reject-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const reason = prompt('Reason for rejection (visible to user):');
      if (!reason || reason.trim() === '') return;
      const res = await post('api/payments.php?id=' + encodeURIComponent(btn.dataset.id) + '&action=reject', { csrf_token: CSRF_TOKEN, reason: reason.trim() });
      if (res && res.success) location.reload();
      else alert((res.error && res.error.message) || res.error || 'Failed');
    });
  });
})();
</script>

</body>
</html>
