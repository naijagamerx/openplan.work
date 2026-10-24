<?php
/**
 * Mobile Alexa Setup — shows the user the values they need to paste into the
 * Alexa Developer Console + their Hostinger control panel.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$user = Auth::user();
$userId = $user['id'] ?? '';
$endpoint = rtrim(APP_URL, '/') . '/api/alexa.php';
$modelUrl = rtrim(APP_URL, '/') . '/alexa-skill/interaction-model.json';
$siteName = getSiteName() ?? 'LazyMan';

// Probe whether the env vars are configured server-side (without exposing values).
$ownerIdConfigured = (bool)getenv('ALEXA_OWNER_USER_ID');
$masterKeyConfigured = (bool)getenv('LAZYMAN_MASTER_PASSWORD');
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Alexa Setup - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Alexa Setup';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section>
    <h2 class="text-2xl font-black">Alexa Skill</h2>
    <p class="text-xs text-gray-600 mt-1">Wire your dashboard into Echo / Alexa app. Single-user only.</p>
  </section>

  <!-- Server config status -->
  <section class="border-2 border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Server config</p>
    <div class="flex items-center justify-between text-xs">
      <span>ALEXA_OWNER_USER_ID</span>
      <span class="<?= $ownerIdConfigured ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest"><?= $ownerIdConfigured ? 'Set' : 'Not set' ?></span>
    </div>
    <div class="flex items-center justify-between text-xs">
      <span>LAZYMAN_MASTER_PASSWORD</span>
      <span class="<?= $masterKeyConfigured ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest"><?= $masterKeyConfigured ? 'Set' : 'Not set' ?></span>
    </div>
    <?php if (!$ownerIdConfigured || !$masterKeyConfigured): ?>
      <p class="text-[10px] text-yellow-800 bg-yellow-50 border border-yellow-200 p-2 mt-2">
        Set both env vars in Hostinger control panel before testing. Without them the skill answers "Alexa skill not configured."
      </p>
    <?php endif; ?>
  </section>

  <!-- Step 1: your user ID -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Step 1 — copy your user ID into Hostinger env</p>
    <div class="bg-gray-50 border border-gray-300 p-2 font-mono text-[10px] break-all" id="user-id"><?= htmlspecialchars($userId) ?></div>
    <button type="button" onclick="copyText('user-id')" class="w-full border border-black py-2 text-[10px] font-bold uppercase tracking-widest active:bg-gray-100">Copy User ID</button>
    <p class="text-[10px] text-gray-600">Hostinger → Advanced → PHP Configuration → Environment Variables.<br>Add: <code class="font-mono">ALEXA_OWNER_USER_ID</code> = the value above.<br>Also add: <code class="font-mono">LAZYMAN_MASTER_PASSWORD</code> = your master password.</p>
  </section>

  <!-- Step 2: endpoint URL -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Step 2 — Alexa Developer Console</p>
    <div>
      <p class="text-[10px] uppercase tracking-widest text-gray-500 mb-1">Endpoint URL (HTTPS tab)</p>
      <div id="endpoint-url" class="bg-gray-50 border border-gray-300 p-2 font-mono text-[10px] break-all"><?= htmlspecialchars($endpoint) ?></div>
      <button type="button" onclick="copyText('endpoint-url')" class="w-full mt-2 border border-black py-2 text-[10px] font-bold uppercase tracking-widest active:bg-gray-100">Copy Endpoint</button>
    </div>
    <p class="text-[10px] text-gray-600">In the console, choose: "My development endpoint has a certificate from a trusted certificate authority."</p>
  </section>

  <!-- Step 3: invocation name -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Step 3 — Invocation Name</p>
    <div class="bg-gray-50 border border-gray-300 p-2 font-mono text-[10px]" id="invocation-name">my plan</div>
    <button type="button" onclick="copyText('invocation-name')" class="w-full border border-black py-2 text-[10px] font-bold uppercase tracking-widest active:bg-gray-100">Copy Invocation</button>
    <p class="text-[10px] text-gray-600">Lowercase, two words. Sidebar → Invocation → Skill Invocation Name.</p>
  </section>

  <!-- Step 4: interaction model -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Step 4 — Interaction Model</p>
    <p class="text-[10px] text-gray-600">Sidebar → Interaction Model → JSON Editor. Paste the contents of:</p>
    <a href="<?= htmlspecialchars($modelUrl) ?>" target="_blank" class="block bg-gray-50 border border-gray-300 p-2 font-mono text-[10px] break-all underline"><?= htmlspecialchars($modelUrl) ?></a>
    <p class="text-[10px] text-gray-600">Click "Save Model" then "Build Model". Build takes ~1 minute.</p>
  </section>

  <!-- Step 5: test phrases -->
  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Step 5 — Test in Console</p>
    <p class="text-[10px] text-gray-600">Use the "Test" tab. Toggle "Skill testing is enabled in: Development". Try:</p>
    <ul class="space-y-1 text-xs">
      <li class="border border-gray-300 p-1.5 font-mono text-[10px]">open my plan</li>
      <li class="border border-gray-300 p-1.5 font-mono text-[10px]">ask my plan what is on my list today</li>
      <li class="border border-gray-300 p-1.5 font-mono text-[10px]">ask my plan read my todos</li>
      <li class="border border-gray-300 p-1.5 font-mono text-[10px]">ask my plan did I do my habits</li>
      <li class="border border-gray-300 p-1.5 font-mono text-[10px]">ask my plan how much water did I drink</li>
      <li class="border border-gray-300 p-1.5 font-mono text-[10px]">ask my plan to add todo buy groceries</li>
    </ul>
  </section>

  <section class="border border-gray-300 p-3 text-[10px] text-gray-600 space-y-1">
    <p class="font-bold text-black uppercase tracking-widest">Troubleshooting</p>
    <p>&middot; <strong>"Alexa skill not configured"</strong> → both env vars must be set + PHP-FPM restarted.</p>
    <p>&middot; <strong>"Signature verification failed"</strong> → check server clock (within 150s of Amazon's); cert URL must be on s3.amazonaws.com.</p>
    <p>&middot; <strong>Endpoint 500</strong> → tail <code class="font-mono">data/php_error.log</code> for the actual exception.</p>
    <p>&middot; <strong>Skill silent</strong> → in console "Test" tab, expand the JSON response to see what came back.</p>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
function copyText(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const text = el.textContent.trim();
  const finish = (ok) => alert(ok ? 'Copied: ' + text.slice(0, 60) + (text.length > 60 ? '...' : '') : 'Copy failed');
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(text).then(() => finish(true)).catch(() => finish(false));
  } else {
    try {
      const ta = document.createElement('textarea');
      ta.value = text; document.body.appendChild(ta); ta.select();
      const ok = document.execCommand('copy'); document.body.removeChild(ta);
      finish(ok);
    } catch (e) { finish(false); }
  }
}
</script>

</body>
</html>
