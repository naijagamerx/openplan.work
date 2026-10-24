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

  <?php if ($active):
    $expiresAt = strtotime((string)($active['expiresAt'] ?? ''));
    $daysLeft = max(0, (int)(($expiresAt - time()) / 86400));
    $cap = (int)($active['monthlyRequestCap'] ?? 0);
    $usagePct = ($cap > 0) ? min(100, ($usageTotal / $cap) * 100) : 0;
    $symbol = getCurrencySymbol($active['planSnapshot']['currency'] ?? 'USD');
  ?>
    <section class="bg-black text-white p-5">
      <p class="text-[10px] font-bold uppercase tracking-widest text-white/60">Active Plan</p>
      <h2 class="text-2xl font-black mt-1"><?= htmlspecialchars($active['planSnapshot']['name'] ?? '') ?></h2>
      <p class="text-xs text-white/70 mt-1">
        <?= htmlspecialchars($symbol . number_format((float)($active['planSnapshot']['price'] ?? 0), 2)) ?>
        &middot; <?= (int)($active['planSnapshot']['durationDays'] ?? 30) ?> days
      </p>

      <div class="mt-4 pt-4 border-t border-white/20">
        <p class="text-[10px] font-bold uppercase tracking-widest text-white/60">Expires</p>
        <p class="text-xl font-black mt-1"><?= $daysLeft ?> days left</p>
        <p class="text-xs text-white/70"><?= htmlspecialchars(date('M j, Y', $expiresAt)) ?></p>
      </div>

      <?php if ($cap > 0): ?>
        <div class="mt-4 pt-4 border-t border-white/20">
          <div class="flex items-center justify-between text-xs mb-1">
            <span>This month</span>
            <span><?= number_format($usageTotal) ?> / <?= number_format($cap) ?></span>
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

      <div class="mt-4 flex flex-col gap-2">
        <a href="?page=settings" class="block bg-white text-black px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center">Configure AI Quota</a>
        <a href="?page=subscription" class="block border border-white/40 px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-center">Extend / Switch</a>
      </div>
    </section>
  <?php else: ?>
    <section class="border-2 border-black p-6 text-center">
      <p class="text-sm font-bold text-gray-900 mb-1">No active subscription</p>
      <p class="text-xs text-gray-500 mb-4">Using your own keys. Subscribe to consume admin's shared quota.</p>
      <a href="?page=subscription" class="inline-block bg-black text-white px-4 py-2 text-xs font-bold uppercase tracking-widest">Browse Plans</a>
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
</body>
</html>
