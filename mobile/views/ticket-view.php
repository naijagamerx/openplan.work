<?php
/**
 * Mobile Ticket View — thread + reply.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/TicketsAPI.php';
$api = new TicketsAPI();
$id = (string)($_GET['id'] ?? '');
$ticket = $id !== '' ? $api->find($id) : null;
$isOwner = $ticket && ($ticket['userId'] ?? '') === Auth::userId();
$isAdmin = Auth::isAdmin();
if (!$ticket || (!$isOwner && !$isAdmin)) {
    header('Location: ?page=support');
    exit;
}
$status = $ticket['status'] ?? 'awaiting_admin';
$statusBg = match ($status) {
    'closed' => 'bg-gray-100 text-gray-700',
    'awaiting_admin' => 'bg-yellow-100 text-yellow-800',
    'awaiting_user' => 'bg-blue-100 text-blue-800',
    default => 'bg-green-100 text-green-800'
};
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Ticket - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script>const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>'; const APP_URL = '<?= APP_URL ?? '' ?>'; const TICKET_ID = '<?= htmlspecialchars($id) ?>';</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Ticket';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section class="border-2 border-black p-4">
    <div class="flex items-start justify-between gap-3">
      <div class="flex-1 min-w-0">
        <h2 class="text-lg font-black"><?= htmlspecialchars($ticket['subject'] ?? '') ?></h2>
        <p class="text-[10px] text-gray-500 mt-1">Opened <?= htmlspecialchars(date('M j, Y g:i a', strtotime((string)($ticket['createdAt'] ?? '')))) ?></p>
      </div>
      <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
    </div>
  </section>

  <section class="space-y-3">
    <?php foreach ($ticket['messages'] ?? [] as $msg):
      $isAdminMsg = (($msg['authorRole'] ?? '') === 'admin');
    ?>
      <div class="flex <?= $isAdminMsg ? 'justify-end' : 'justify-start' ?>">
        <div class="max-w-[85%] <?= $isAdminMsg ? 'bg-black text-white' : 'bg-white border border-black text-black' ?> p-3">
          <div class="flex items-center justify-between gap-2 mb-1">
            <p class="text-[9px] font-bold uppercase tracking-widest <?= $isAdminMsg ? 'text-white/60' : 'text-gray-500' ?>"><?= $isAdminMsg ? 'Admin' : 'You' ?></p>
            <p class="text-[9px] <?= $isAdminMsg ? 'text-white/60' : 'text-gray-400' ?>"><?= htmlspecialchars(date('M j, g:i a', strtotime((string)($msg['createdAt'] ?? '')))) ?></p>
          </div>
          <p class="text-xs whitespace-pre-line break-words"><?= htmlspecialchars($msg['body'] ?? '') ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </section>

  <?php if ($status !== 'closed'): ?>
    <section class="border-2 border-black p-4">
      <form id="reply-form" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Reply</label>
          <textarea name="body" rows="4" required class="w-full px-3 py-2 border border-black text-sm" placeholder="Write your reply..."></textarea>
        </div>
        <div class="flex items-center justify-between gap-2">
          <button type="button" id="close-ticket-btn" class="text-[10px] font-bold uppercase tracking-widest text-gray-500 active:text-red-600">Close ticket</button>
          <button type="submit" class="bg-black text-white px-5 py-2 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Send Reply</button>
        </div>
      </form>
    </section>
  <?php else: ?>
    <section class="border border-gray-300 p-4 text-center text-xs text-gray-500">This ticket is closed.</section>
  <?php endif; ?>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
(function() {
  const form = document.getElementById('reply-form');
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(form);
      const payload = { body: (fd.get('body') || '').trim(), csrf_token: fd.get('csrf_token') || '' };
      if (!payload.body) { alert('Reply required'); return; }
      try {
        const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/tickets.php?id=' + encodeURIComponent(TICKET_ID) + '&action=reply', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
          body: JSON.stringify(payload)
        });
        const result = await r.json();
        if (result && result.success) { location.reload(); }
        else { alert((result.error && result.error.message) || 'Failed'); }
      } catch (err) { alert('Failed to send'); }
    });
  }
  const closeBtn = document.getElementById('close-ticket-btn');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      if (!confirm('Close this ticket?')) return;
      fetch((APP_URL ? APP_URL + '/' : '') + 'api/tickets.php?id=' + encodeURIComponent(TICKET_ID) + '&action=close', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ csrf_token: CSRF_TOKEN })
      }).then(r => r.json()).then(result => {
        if (result && result.success) location.reload();
        else alert((result.error && result.error.message) || 'Failed');
      }).catch(() => alert('Failed to close'));
    });
  }
})();
</script>

</body>
</html>
