<?php
/**
 * Mobile My Subscription — active sub + history.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/SubscriptionsAPI.php';
require_once INCLUDES_PATH . '/SubscriptionHelper.php';

$api = new SubscriptionsAPI();
$userId = Auth::userId();
$active = $api->getActiveByUser($userId);
$history = $api->getByUser($userId);
$usage = getMonthlyUsage($userId);
$usageTotal = (int)($usage['totalRequests'] ?? 0);
$siteName = getSiteName() ?? 'LazyMan';
$cardReturn = ($_GET['payfast'] ?? '') === 'return';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>My Subscription - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'My Subscription';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-5 text-zinc-900 dark:text-zinc-100">

  <?php if ($cardReturn): ?>
    <section class="border border-black p-3 text-xs text-gray-700" role="status">
      <?php if ($active): ?>
        Thank you. Your card payment went through and your plan is active.
      <?php else: ?>
        Thank you. PayFast is confirming your payment; your plan appears here within a minute or two.
        <a href="?page=my-subscription" class="font-bold underline">Refresh</a>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($active):
    $expiresAt = strtotime((string)($active['expiresAt'] ?? ''));
    $daysLeft = max(0, (int)ceil(($expiresAt - time()) / 86400));
    $cap = (int)($active['monthlyRequestCap'] ?? 0);
    $isTrial = !empty($active['trial']);
    // A trial's requests count over the whole trial; a plan's over this month.
    $capUsed = (int)subscriptionUsage($userId, $active)['used'];
    $usagePct = ($cap > 0) ? min(100, ($capUsed / $cap) * 100) : 0;
    $symbol = getCurrencySymbol($active['planSnapshot']['currency'] ?? 'USD');
  ?>
    <section class="bg-black text-white p-5">
      <p class="text-[10px] font-bold uppercase tracking-widest text-white/60"><?= $isTrial ? 'Free trial' : 'Active Plan' ?></p>
      <h2 class="text-2xl font-black mt-1"><?= htmlspecialchars($active['planSnapshot']['name'] ?? '') ?></h2>
      <p class="text-xs text-white/70 mt-1">
        <?php if ($isTrial): ?>
          No card &middot; ends by itself after <?= (int)($active['planSnapshot']['durationDays'] ?? 14) ?> days
        <?php else: ?>
          <?= htmlspecialchars($symbol . number_format((float)($active['planSnapshot']['price'] ?? 0), 2)) ?>
          &middot; <?= (int)($active['planSnapshot']['durationDays'] ?? 30) ?> days
        <?php endif; ?>
      </p>

      <div class="mt-4 pt-4 border-t border-white/20">
        <p class="text-[10px] font-bold uppercase tracking-widest text-white/60"><?= $isTrial ? 'Ends in' : 'Expires' ?></p>
        <p class="text-xl font-black mt-1"><?= $daysLeft ?> days left</p>
        <p class="text-xs text-white/70"><?= htmlspecialchars(date('M j, Y', $expiresAt)) ?></p>
      </div>

      <?php if ($cap > 0): ?>
        <div class="mt-4 pt-4 border-t border-white/20">
          <div class="flex items-center justify-between text-xs mb-1">
            <span><?= $isTrial ? 'Trial requests' : 'This month' ?></span>
            <span><?= number_format($capUsed) ?> / <?= number_format($cap) ?></span>
          </div>
          <div class="h-1.5 bg-white/20 overflow-hidden">
            <div class="h-full bg-white" style="width: <?= (int)$usagePct ?>%"></div>
          </div>
        </div>
      <?php else: ?>
        <div class="mt-4 pt-4 border-t border-white/20 text-xs text-white/70">
          Unlimited &middot; <?= number_format($usageTotal) ?> used
        </div>
      <?php endif; ?>

      <?php if (!empty($active['providers'])): ?>
        <p class="mt-3 text-xs text-white/70"><span class="font-bold">Providers:</span> <?= htmlspecialchars(implode(', ', $active['providers'])) ?></p>
      <?php endif; ?>

      <?php if (($active['provider'] ?? '') === 'payfast'): ?>
        <p class="mt-3 text-xs text-white/70">
          <?php if (!empty($active['autoRenew'])): ?>
            <span class="font-bold">Card auto-renew is on.</span> Cancel any time; access stays until <?= htmlspecialchars(date('M j, Y', $expiresAt)) ?>.
          <?php else: ?>
            <span class="font-bold">Auto-renew is off.</span> Your plan ends on <?= htmlspecialchars(date('M j, Y', $expiresAt)) ?>.
          <?php endif; ?>
        </p>
      <?php endif; ?>

      <div class="mt-4 flex flex-col gap-2">
        <?php if ($isTrial): ?>
          <a href="?page=subscription" class="block bg-white text-black px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center touch-target">Choose a plan</a>
          <a href="?page=ai-assistant" class="block border border-white/40 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center touch-target">Open AI assistant</a>
        <?php else: ?>
        <a href="?page=settings" class="block bg-white text-black px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center">Platform AI Settings</a>
        <a href="?page=subscription" class="block border border-white/40 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center">Extend / Switch</a>
        <?php endif; ?>
        <?php if (($active['provider'] ?? '') === 'payfast' && !empty($active['autoRenew'])): ?>
          <button type="button" id="cancel-autorenew-btn" data-csrf="<?= htmlspecialchars(Auth::csrfToken()) ?>" class="block w-full border border-white/40 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center touch-target">Cancel Auto-Renew</button>
        <?php endif; ?>
      </div>
    </section>
  <?php else: ?>
    <?php require __DIR__ . '/../../views/partials/trial-offer.php'; ?>
    <section class="border-2 border-black dark:border-white p-6 text-center">
      <p class="text-sm font-bold mb-1">No active plan</p>
      <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">A plan lets you use the AI on our keys. Without one, the AI works only with your own API keys from Settings.</p>
      <a href="?page=subscription" class="inline-block bg-black text-white px-4 py-2 text-xs font-bold uppercase tracking-widest touch-target">See plans</a>
    </section>
  <?php endif; ?>

  <?php if (!empty($history)): ?>
    <section>
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">History</p>
      <div class="border border-black divide-y divide-black">
        <?php foreach ($history as $h):
          $status = $h['status'] ?? 'expired';
          $statusBg = match ($status) {
            'active' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-gray-100 text-gray-700',
            default => 'bg-red-100 text-red-800'
          };
        ?>
          <div class="p-3 flex items-center justify-between gap-3">
            <div class="flex-1 min-w-0">
              <p class="font-bold text-sm truncate"><?= htmlspecialchars($h['planSnapshot']['name'] ?? '') ?></p>
              <p class="text-[10px] text-gray-500">
                <?= htmlspecialchars(date('M j', strtotime((string)($h['startsAt'] ?? '')))) ?>
                — <?= htmlspecialchars(date('M j, Y', strtotime((string)($h['expiresAt'] ?? '')))) ?>
              </p>
            </div>
            <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest"><?= htmlspecialchars($status) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
<script>
(function() {
  const btn = document.getElementById('cancel-autorenew-btn');
  if (!btn) return;
  const say = (message) => { if (window.Mobile && Mobile.ui && Mobile.ui.toast) { Mobile.ui.toast(message); } else { alert(message); } };
  btn.addEventListener('click', async () => {
    if (!confirm('Stop charging your card? Your plan stays active until it expires.')) return;
    btn.disabled = true;
    try {
      const token = btn.getAttribute('data-csrf') || '';
      const response = await fetch(<?= json_encode(APP_URL . '/api/payfast.php?action=cancel', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
        body: JSON.stringify({ csrf_token: token })
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error((result && result.error && result.error.message) || 'Could not cancel auto-renew');
      }
      say(result.message || 'Auto-renew is off');
      setTimeout(() => window.location.reload(), 900);
    } catch (err) {
      say(err.message || 'Could not cancel auto-renew');
      btn.disabled = false;
    }
  });
})();
</script>
</body>
</html>
