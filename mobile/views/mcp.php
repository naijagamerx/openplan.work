<?php
/**
 * Mobile MCP — connection guide for Claude Code + other AI agents.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$currentUser = Auth::user();
$apiUrl = APP_URL . '/api';
$mcpEndpoint = APP_URL . '/api/mcp-server.php';
$csrfToken = Auth::csrfToken();
$siteName = getSiteName() ?? 'LazyMan';

// Check if user already has a token (masked).
$masterPassword = getMasterPassword();
$hasToken = false;
$tokenMask = null;
if ($masterPassword !== '') {
    try {
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $hasToken = $auth->hasMcpApiToken($currentUser['id'] ?? '');
        $tokenMask = $hasToken ? $auth->getMcpApiToken($currentUser['id'] ?? '') : null;
    } catch (Throwable $e) { /* ignore */ }
}
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>MCP - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } } .touch-target { min-height: 44px; }</style>
<script>const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken) ?>'; const APP_URL = '<?= APP_URL ?? '' ?>';</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'MCP Setup';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section>
    <h2 class="text-2xl font-black">MCP Connection</h2>
    <p class="text-xs text-gray-600 mt-1">Let Claude Code (or any MCP-compatible AI) talk to your workspace using a personal API token.</p>
  </section>

  <section class="border-2 border-black p-3 space-y-3">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Your MCP API Token</p>
    <?php if ($hasToken): ?>
      <div class="bg-gray-50 border border-gray-300 p-2 font-mono text-xs break-all"><?= htmlspecialchars($tokenMask ?? '••••') ?></div>
      <p class="text-[10px] text-gray-500">Shown masked. Tap below to regenerate (existing AI clients must be updated).</p>
      <button type="button" onclick="generateToken()" class="w-full border border-black py-3 font-bold text-xs uppercase tracking-widest active:bg-gray-100">Regenerate Token</button>
      <button type="button" onclick="revokeToken()" class="w-full border border-red-500 text-red-600 py-3 font-bold text-xs uppercase tracking-widest active:bg-red-50">Revoke Token</button>
    <?php else: ?>
      <p class="text-xs text-gray-600">No token yet. Generate one to connect AI agents.</p>
      <button type="button" onclick="generateToken()" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Generate Token</button>
    <?php endif; ?>
    <div id="new-token-display" class="hidden bg-yellow-50 border-2 border-yellow-400 p-2 space-y-2">
      <p class="text-[10px] font-bold uppercase tracking-widest text-yellow-900">Copy now — shown once</p>
      <code id="new-token-value" class="block font-mono text-xs break-all bg-white border border-yellow-300 p-2"></code>
      <button type="button" onclick="copyNewToken()" class="w-full bg-black text-white py-2 text-[10px] font-bold uppercase tracking-widest">Copy</button>
    </div>
  </section>

  <section class="border border-black p-3 space-y-3">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">AI agent config (HTTP)</p>
    <pre class="bg-gray-50 border border-gray-300 p-2 font-mono text-[10px] overflow-x-auto whitespace-pre">{
  "mcpServers": {
    "openplan-work": {
      "type": "http",
      "url": "<?= htmlspecialchars($mcpEndpoint) ?>",
      "headers": {
        "X-MCP-Api-Token": "your-token-here"
      }
    }
  }
}</pre>
    <p class="text-[10px] text-gray-500">Paste into your agent's MCP config (Claude Code <code class="font-mono">.mcp.json</code>, Hermes, etc.) and replace <code class="font-mono">your-token-here</code> with the generated value. Token only — no password needed.</p>
  </section>

  <section class="border border-black p-3 space-y-2">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">How it works</p>
    <ol class="list-decimal pl-5 text-xs space-y-1 text-gray-700">
      <li>Generate a token above.</li>
      <li>Add it to your AI client's MCP config.</li>
      <li>Client sends token in <code class="font-mono">X-MCP-Api-Token</code> header.</li>
      <li>Server matches against your account, runs requested action.</li>
      <li>Revoke any time to instantly cut access.</li>
    </ol>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
async function generateToken() {
  if (!confirm('Generate a new MCP token? Any existing AI clients using the old token will stop working until updated.')) return;
  try {
    const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/users.php?action=generate_mcp_token', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ csrf_token: CSRF_TOKEN })
    });
    const res = await r.json();
    if (res && res.success && res.data && res.data.token) {
      document.getElementById('new-token-value').textContent = res.data.token;
      document.getElementById('new-token-display').classList.remove('hidden');
    } else {
      alert((res.error && res.error.message) || 'Failed to generate');
    }
  } catch (e) { alert('Network error'); }
}

async function revokeToken() {
  if (!confirm('Revoke the current token? All AI clients will lose access.')) return;
  try {
    const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/users.php?action=revoke_mcp_token', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ csrf_token: CSRF_TOKEN })
    });
    const res = await r.json();
    if (res && res.success) location.reload();
    else alert((res.error && res.error.message) || 'Failed');
  } catch (e) { alert('Network error'); }
}

function copyNewToken() {
  const t = document.getElementById('new-token-value').textContent;
  if (!t) return;
  if (navigator.clipboard) navigator.clipboard.writeText(t).then(() => alert('Token copied'));
  else { const ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); alert('Token copied'); }
}
</script>

</body>
</html>
