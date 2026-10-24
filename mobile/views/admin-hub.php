<?php
/**
 * Mobile Admin Hub — admin landing page.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/PaymentsAPI.php';
require_once INCLUDES_PATH . '/TicketsAPI.php';
require_once INCLUDES_PATH . '/SubscriptionsAPI.php';
require_once INCLUDES_PATH . '/PlansAPI.php';
require_once INCLUDES_PATH . '/PaymentMethodsAPI.php';
require_once INCLUDES_PATH . '/AISharedKeysAPI.php';

$globalDb = new Database(APP_NAME);
$pendingPayments = count((new PaymentsAPI())->getAll('pending'));
$awaitingTickets = count(array_filter((new TicketsAPI())->getAll(), fn($t) => ($t['status'] ?? '') === 'awaiting_admin'));
$activeSubsCount = count(array_filter((new SubscriptionsAPI())->getAll(), fn($s) => ($s['status'] ?? '') === 'active'));
$plansCount = count((new PlansAPI($globalDb, 'plans'))->findAll(['activeOnly' => true]));
$methodsCount = count((new PaymentMethodsAPI($globalDb, 'payment_methods'))->findAll(['activeOnly' => true]));
$sharedKeys = (new AISharedKeysAPI())->getMaskedKeys();
$keysConfiguredCount = 0;
foreach (['groqApiKey', 'openrouterApiKey', 'geminiApiKey'] as $f) {
    if (!empty($sharedKeys[$f]['configured'])) { $keysConfiguredCount++; }
}

$cards = [
    ['page' => 'admin-plans', 'title' => 'Plans', 'desc' => 'Subscription plans.', 'count' => $plansCount, 'label' => 'active'],
    ['page' => 'admin-payment-methods', 'title' => 'Payment Methods', 'desc' => 'Bank + crypto wallets.', 'count' => $methodsCount, 'label' => 'method' . ($methodsCount === 1 ? '' : 's')],
    ['page' => 'admin-payments', 'title' => 'Payment Approvals', 'desc' => 'Approve / reject payments.', 'count' => $pendingPayments, 'label' => 'pending', 'highlight' => $pendingPayments > 0],
    ['page' => 'admin-subscriptions', 'title' => 'Subscribers', 'desc' => 'Cancel or extend subs.', 'count' => $activeSubsCount, 'label' => 'active'],
    ['page' => 'admin-tickets', 'title' => 'Support Tickets', 'desc' => 'Reply to user tickets.', 'count' => $awaitingTickets, 'label' => 'awaiting', 'highlight' => $awaitingTickets > 0],
    ['page' => 'admin-shared-keys', 'title' => 'Shared AI Keys', 'desc' => 'Admin keys for subscribers.', 'count' => $keysConfiguredCount, 'label' => 'of 3'],
    ['page' => 'users', 'title' => 'Users', 'desc' => 'Promote, ban, remove.'],
    ['page' => 'release-export', 'title' => 'Release Export', 'desc' => 'Signed release artifacts.'],
    ['page' => 'mcp', 'title' => 'MCP', 'desc' => 'Connect AI agents to workspace.'],
    ['page' => 'custom-instruction', 'title' => 'Custom Instructions', 'desc' => 'AI task creation rules.'],
];

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Admin Hub - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Admin Hub';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section>
    <p class="text-xs text-gray-500">Everything administrative. Tap a card to manage.</p>
  </section>

  <?php if ($pendingPayments > 0 || $awaitingTickets > 0): ?>
    <section class="border-2 border-black p-3 space-y-1 text-xs">
      <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500">Needs Attention</p>
      <?php if ($pendingPayments > 0): ?>
        <a href="?page=admin-payments" class="block font-bold"><?= $pendingPayments ?> pending payment<?= $pendingPayments === 1 ? '' : 's' ?></a>
      <?php endif; ?>
      <?php if ($awaitingTickets > 0): ?>
        <a href="?page=admin-tickets" class="block font-bold"><?= $awaitingTickets ?> ticket<?= $awaitingTickets === 1 ? '' : 's' ?> awaiting reply</a>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <div class="grid grid-cols-2 gap-3">
    <?php foreach ($cards as $card):
      $hasCount = array_key_exists('count', $card);
      $highlight = !empty($card['highlight']);
    ?>
      <a href="?page=<?= htmlspecialchars($card['page']) ?>" class="block border-2 <?= $highlight ? 'border-black' : 'border-gray-300' ?> p-3 active:bg-gray-50">
        <?php if ($hasCount): ?>
          <p class="text-2xl font-black leading-none"><?= (int)$card['count'] ?></p>
          <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400"><?= htmlspecialchars($card['label'] ?? '') ?></p>
        <?php else: ?>
          <p class="text-2xl font-black leading-none text-gray-300">—</p>
          <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">&nbsp;</p>
        <?php endif; ?>
        <p class="text-sm font-bold mt-3"><?= htmlspecialchars($card['title']) ?></p>
        <p class="text-[10px] text-gray-500 mt-0.5"><?= htmlspecialchars($card['desc']) ?></p>
      </a>
    <?php endforeach; ?>
  </div>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
</body>
</html>
