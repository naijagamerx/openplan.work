<?php
/**
 * Mobile Subscription — plan browser.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/PlansAPI.php';
require_once INCLUDES_PATH . '/SubscriptionHelper.php';

$plansApi = new PlansAPI(new Database(APP_NAME), 'plans');
$plans = $plansApi->findAll(['activeOnly' => true]);
$currentSub = getActiveSubscription(Auth::userId());
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Plans - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Plans';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-5 text-zinc-900 dark:text-zinc-100">

  <section>
    <h2 class="text-2xl font-black">Subscription Plans</h2>
    <p class="text-xs text-gray-500 mt-1">Unlock shared platform AI access.</p>
  </section>

  <?php if ($currentSub): ?>
    <section class="bg-black text-white p-4">
      <p class="text-[10px] font-bold uppercase tracking-widest text-white/60">Active subscription</p>
      <p class="text-lg font-black mt-1"><?= htmlspecialchars($currentSub['planSnapshot']['name'] ?? 'Active plan') ?></p>
      <p class="text-xs text-white/70 mt-1">
        Expires <?= htmlspecialchars(date('M j, Y', strtotime((string)($currentSub['expiresAt'] ?? '')))) ?>
        &middot; <?= max(0, (int)((strtotime((string)$currentSub['expiresAt']) - time()) / 86400)) ?> days left
      </p>
      <a href="?page=my-subscription" class="inline-block mt-3 bg-white text-black px-3 py-2 text-[10px] font-bold uppercase tracking-widest">View details</a>
    </section>
  <?php endif; ?>

  <?php if (empty($plans)): ?>
    <section class="border-2 border-black p-6 text-center">
      <p class="text-sm text-gray-700 font-bold mb-1">No plans available yet.</p>
      <p class="text-xs text-gray-500">Check back later or contact support.</p>
    </section>
  <?php else: ?>
    <div class="space-y-4">
      <?php foreach ($plans as $plan):
        $price = (float)($plan['price'] ?? 0);
        $symbol = getCurrencySymbol($plan['currency'] ?? 'USD');
        $days = (int)($plan['durationDays'] ?? 30);
        $cap = (int)($plan['monthlyRequestCap'] ?? 0);
        $providers = $plan['providers'] ?? [];
      ?>
        <article class="border-2 border-black p-4 space-y-3">
          <div>
            <h3 class="text-xl font-black"><?= htmlspecialchars($plan['name'] ?? '') ?></h3>
            <?php if (!empty($plan['description'])): ?>
              <p class="text-xs text-gray-600 mt-1"><?= nl2br(htmlspecialchars($plan['description'])) ?></p>
            <?php endif; ?>
          </div>
          <div class="border-t border-black pt-3">
            <p class="flex items-baseline gap-1">
              <span class="text-3xl font-black"><?= htmlspecialchars($symbol . number_format($price, 2)) ?></span>
              <span class="text-xs text-gray-500">/ <?= $days ?> days</span>
            </p>
          </div>
          <ul class="text-xs space-y-1.5">
            <li class="flex items-start gap-2">
              <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              <span><?= $cap > 0 ? number_format($cap) . ' AI requests / month' : 'Unlimited AI requests' ?></span>
            </li>
            <?php if (!empty($providers)): ?>
              <li class="flex items-start gap-2">
                <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                <span>Providers: <?= htmlspecialchars(implode(', ', $providers)) ?></span>
              </li>
            <?php endif; ?>
            <li class="flex items-start gap-2">
              <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
              <span>Cancel anytime &middot; no auto-renew</span>
            </li>
          </ul>
          <a href="?page=buy-plan&id=<?= htmlspecialchars($plan['id']) ?>" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform touch-target">
            <?= $currentSub ? 'Switch / Extend' : 'Get this Plan' ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <section class="border border-gray-300 p-4 text-xs text-gray-600 space-y-2">
    <p class="font-bold text-black">How it works</p>
    <ol class="list-decimal pl-4 space-y-1">
      <li>Pick a plan above.</li>
      <li>Pay manually (bank or crypto).</li>
      <li>Upload proof.</li>
      <li>Admin approves within 24 hours.</li>
    </ol>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
</body>
</html>
