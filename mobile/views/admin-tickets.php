<?php
/**
 * Mobile Admin Tickets — support queue.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/TicketsAPI.php';
$api = new TicketsAPI();
$tickets = $api->getAll();

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
<title>Admin Tickets - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Support Tickets';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-3 text-zinc-900 dark:text-zinc-100">
  <?php if (empty($tickets)): ?>
    <section class="border-2 border-black p-6 text-center">
      <p class="text-sm font-bold">No tickets yet</p>
    </section>
  <?php else: ?>
    <?php foreach ($tickets as $t):
      $user = $userMap[$t['userId'] ?? ''] ?? null;
      $userLabel = $user ? ($user['email'] ?? $user['name'] ?? 'Unknown') : 'Unknown user';
      $status = $t['status'] ?? 'awaiting_admin';
      $statusBg = match ($status) {
        'closed' => 'bg-gray-100 text-gray-700',
        'awaiting_admin' => 'bg-yellow-100 text-yellow-800',
        'awaiting_user' => 'bg-blue-100 text-blue-800',
        default => 'bg-green-100 text-green-800'
      };
      $msgCount = count($t['messages'] ?? []);
    ?>
      <a href="?page=ticket-view&id=<?= htmlspecialchars($t['id'] ?? '') ?>" class="block border border-black p-3 active:bg-gray-50">
        <div class="flex items-start justify-between gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-bold text-sm truncate"><?= htmlspecialchars($t['subject'] ?? '') ?></p>
            <p class="text-[10px] text-gray-500 mt-1">
              <?= htmlspecialchars($userLabel) ?> &middot; <?= $msgCount ?> msg<?= $msgCount === 1 ? '' : 's' ?>
            </p>
            <p class="text-[10px] text-gray-400"><?= htmlspecialchars(date('M j, g:i a', strtotime((string)($t['updatedAt'] ?? '')))) ?></p>
          </div>
          <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
</body>
</html>
