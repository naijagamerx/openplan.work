<?php
/**
 * Mobile Admin Shared Keys — import + manual entry of admin's shared AI keys.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div style="padding:24px;font-family:system-ui">Admin access required.</div>';
    exit;
}

require_once INCLUDES_PATH . '/AISharedKeysAPI.php';
$api = new AISharedKeysAPI();
$status = $api->getMaskedKeys();
$models = $status['models'] ?? [];

$providers = [
    'groq' => ['field' => 'groqApiKey', 'label' => 'Groq', 'placeholder' => 'gsk_...'],
    'openrouter' => ['field' => 'openrouterApiKey', 'label' => 'OpenRouter', 'placeholder' => 'sk-or-...'],
    'gemini' => ['field' => 'geminiApiKey', 'label' => 'Gemini', 'placeholder' => 'AIza...']
];

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Shared AI Keys - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Shared AI Keys';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section class="bg-black text-white p-4">
    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Recommended</p>
    <p class="text-base font-bold mt-1">Import from your Settings</p>
    <p class="text-xs text-white/70 mt-1 mb-3">Copy your API keys + models from your own Settings into the shared pool.</p>
    <button type="button" id="import-btn" class="w-full bg-white text-black py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Import Now</button>
  </section>

  <section class="space-y-3">
    <?php foreach ($providers as $providerId => $cfg):
      $st = $status[$cfg['field']] ?? ['configured' => false, 'lastFour' => null];
      $modelStat = $models[$providerId] ?? ['total' => 0, 'enabled' => 0, 'default' => null];
    ?>
      <article class="border-2 <?= $st['configured'] ? 'border-black' : 'border-gray-300' ?> p-3">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Provider</p>
            <p class="text-base font-bold"><?= htmlspecialchars($cfg['label']) ?></p>
          </div>
          <span class="<?= $st['configured'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?> px-2 py-1 text-[9px] font-bold uppercase tracking-widest">
            <?= $st['configured'] ? 'Configured' : 'Not set' ?>
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-[10px] mt-2 pt-2 border-t border-gray-200">
          <div><span class="text-gray-500 uppercase tracking-widest">Key</span><br><?= $st['configured'] ? '••' . htmlspecialchars($st['lastFour']) : '—' ?></div>
          <div><span class="text-gray-500 uppercase tracking-widest">Models</span><br><?= (int)$modelStat['enabled'] ?> / <?= (int)$modelStat['total'] ?> enabled</div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="border border-black p-3 space-y-3">
    <p class="text-sm font-bold uppercase tracking-widest">Manual entry</p>
    <p class="text-[10px] text-gray-500">Leave blank to keep existing value.</p>
    <form id="manual-form" class="space-y-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <?php foreach ($providers as $providerId => $cfg): ?>
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1"><?= htmlspecialchars($cfg['label']) ?> key</label>
          <input type="password" name="<?= $cfg['field'] ?>" class="w-full px-3 py-2 border border-black text-sm font-mono" placeholder="<?= htmlspecialchars($cfg['placeholder']) ?>">
        </div>
      <?php endforeach; ?>
      <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Save Keys</button>
    </form>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
document.getElementById('import-btn').addEventListener('click', async () => {
  if (!confirm('Import keys + models from your own Settings into the shared pool?')) return;
  const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/admin-shared-keys.php?action=import', {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify({ csrf_token: CSRF_TOKEN })
  });
  const res = await r.json();
  if (res && res.success) location.reload();
  else alert((res.error && res.error.message) || 'Failed');
});

document.getElementById('manual-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const payload = { csrf_token: CSRF_TOKEN };
  ['groqApiKey', 'openrouterApiKey', 'geminiApiKey'].forEach(k => {
    const v = fd.get(k);
    if (v && v.trim() !== '') payload[k] = v.trim();
  });
  if (Object.keys(payload).length === 1) { alert('Enter at least one key'); return; }
  const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/admin-shared-keys.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
    body: JSON.stringify(payload)
  });
  const res = await r.json();
  if (res && res.success) location.reload();
  else alert((res.error && res.error.message) || 'Failed');
});
</script>

</body>
</html>
