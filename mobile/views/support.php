<?php
/**
 * Mobile Support — ticket list + new ticket form.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/TicketsAPI.php';
$api = new TicketsAPI();
$tickets = $api->getByUser(Auth::userId());
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Support - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Support';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = "document.getElementById('ticket-form-card').classList.toggle('hidden')";
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section id="ticket-form-card" class="hidden border-2 border-black p-4">
    <form id="ticket-form" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">

      <div class="flex items-center justify-between">
        <h3 class="text-sm font-bold uppercase tracking-widest">New Ticket</h3>
        <button type="button" onclick="document.getElementById('ticket-form-card').classList.add('hidden')" class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Cancel</button>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Subject</label>
        <input type="text" name="subject" required class="w-full px-3 py-2 border border-black text-sm" placeholder="Brief summary">
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Priority</label>
        <select name="priority" class="w-full px-3 py-2 border border-black text-sm">
          <option value="low">Low</option>
          <option value="normal" selected>Normal</option>
          <option value="high">High</option>
        </select>
      </div>

      <div>
        <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Message</label>
        <textarea name="body" rows="5" required class="w-full px-3 py-2 border border-black text-sm" placeholder="Describe..."></textarea>
      </div>

      <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Submit Ticket</button>
    </form>
  </section>

  <?php if (empty($tickets)): ?>
    <section class="border-2 border-black p-6 text-center">
      <p class="text-sm font-bold text-gray-900 mb-1">No tickets yet</p>
      <p class="text-xs text-gray-500">Tap + to open a new ticket.</p>
    </section>
  <?php else: ?>
    <div class="space-y-3">
      <?php foreach ($tickets as $t):
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
                <?= $msgCount ?> message<?= $msgCount === 1 ? '' : 's' ?>
                &middot; <?= htmlspecialchars(date('M j, g:i a', strtotime((string)($t['updatedAt'] ?? '')))) ?>
              </p>
            </div>
            <span class="<?= $statusBg ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
(function() {
  const form = document.getElementById('ticket-form');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const payload = {
      subject: (fd.get('subject') || '').trim(),
      body: (fd.get('body') || '').trim(),
      priority: fd.get('priority') || 'normal',
      csrf_token: fd.get('csrf_token') || ''
    };
    if (!payload.subject || !payload.body) { alert('Subject and message required'); return; }
    try {
      const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/tickets.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify(payload)
      });
      const result = await r.json();
      if (result && result.success) {
        window.location.href = '?page=ticket-view&id=' + encodeURIComponent((result.data && result.data.id) || '');
      } else {
        alert((result.error && result.error.message) || 'Failed');
      }
    } catch (err) { alert('Failed to submit'); }
  });
})();
</script>

</body>
</html>
