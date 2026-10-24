<?php
/**
 * Mobile Admin Subscriptions — extend / cancel.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/SubscriptionsAPI.php';
$api = new SubscriptionsAPI();
$subs = $api->getAll();

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
<title>Admin Subscriptions - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Subscriptions';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-3 text-zinc-900 dark:text-zinc-100">
  <?php if (empty($subs)): ?>
    <section class="border-2 border-black p-6 text-center"><p class="text-sm font-bold">No subscriptions yet</p></section>
  <?php else: ?>
    <?php foreach ($subs as $s):
      $user = $userMap[$s['userId'] ?? ''] ?? null;
      $userLabel = $user ? ($user['email'] ?? $user['name'] ?? 'Unknown') : 'Unknown user';
      $status = $s['status'] ?? 'expired';
      $statusBg = match ($status) {
        'active' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-gray-100 text-gray-700',
        default => 'bg-red-100 text-red-800'
      };
    ?>
      <article class="border border-black p-3 space-y-2">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm truncate"><?= htmlspecialchars($userLabel) ?></p>
            <p class="text-[10px] text-gray-500 mt-0.5"><?= htmlspecialchars($s['planSnapshot']['name'] ?? '') ?></p>
          </div>
          <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars($status) ?></span>
        </div>
        <div class="grid grid-cols-2 gap-2 text-[10px] pt-2 border-t border-gray-200">
          <div><span class="text-gray-500 uppercase tracking-widest">Start</span><br><?= htmlspecialchars(date('M j, Y', strtotime((string)($s['startsAt'] ?? '')))) ?></div>
          <div><span class="text-gray-500 uppercase tracking-widest">Expires</span><br><?= htmlspecialchars(date('M j, Y', strtotime((string)($s['expiresAt'] ?? '')))) ?></div>
        </div>
        <?php if ($status === 'active'): ?>
          <div class="flex gap-2 pt-1">
            <button type="button" data-id="<?= htmlspecialchars($s['id'] ?? '') ?>" class="extend-btn flex-1 border border-black py-1.5 text-[10px] font-bold uppercase tracking-widest active:bg-gray-100">Extend</button>
            <button type="button" data-id="<?= htmlspecialchars($s['id'] ?? '') ?>" class="cancel-btn flex-1 border border-red-500 text-red-600 py-1.5 text-[10px] font-bold uppercase tracking-widest active:bg-red-50">Cancel</button>
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
  document.querySelectorAll('.extend-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const daysStr = prompt('Extend by how many days?', '30');
      if (!daysStr) return;
      const days = parseInt(daysStr, 10);
      if (!Number.isFinite(days) || days <= 0) { alert('Positive integer required'); return; }
      const res = await post('api/subscriptions.php?id=' + encodeURIComponent(btn.dataset.id) + '&action=extend', { csrf_token: CSRF_TOKEN, days });
      if (res && res.success) location.reload();
      else alert((res.error && res.error.message) || 'Failed');
    });
  });
  document.querySelectorAll('.cancel-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Cancel this subscription? User loses shared-key access immediately.')) return;
      const res = await post('api/subscriptions.php?id=' + encodeURIComponent(btn.dataset.id) + '&action=cancel', { csrf_token: CSRF_TOKEN });
      if (res && res.success) location.reload();
      else alert((res.error && res.error.message) || 'Failed');
    });
  });
})();
</script>

</body>
</html>
