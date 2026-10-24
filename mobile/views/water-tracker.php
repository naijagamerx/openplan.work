<?php
/**
 * Mobile Water Tracker — daily glasses + 7-day history.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$db = new Database(getMasterPassword(), Auth::userId());
$config = $db->load('config') ?? [];
$goal = (int)($config['waterGoal'] ?? 8);

// Try water_tracker collection first, then water_log as fallback (different installs).
$entries = $db->load('water_tracker');
if (!is_array($entries) || empty($entries)) { $entries = $db->load('water_log') ?? []; }

$today = date('Y-m-d');
$todayCount = 0;
foreach ($entries as $e) {
    if (($e['date'] ?? '') === $today) {
        $todayCount = (int)($e['glasses'] ?? $e['count'] ?? $todayCount + 1);
    }
}

$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $cnt = 0;
    foreach ($entries as $e) {
        if (($e['date'] ?? '') === $d) { $cnt = (int)($e['glasses'] ?? $e['count'] ?? $cnt + 1); }
    }
    $last7[] = ['date' => $d, 'glasses' => $cnt];
}

$totalGlasses = array_sum(array_column($last7, 'glasses'));
$avgGlasses = round($totalGlasses / 7, 1);
$goalMetDays = count(array_filter($last7, fn($d) => $d['glasses'] >= $goal));
$pct = $goal > 0 ? min(100, ($todayCount / $goal) * 100) : 0;

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Water Tracker - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Water Tracker';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section class="bg-black text-white p-5">
    <p class="text-[10px] font-bold uppercase tracking-widest text-white/60">Today</p>
    <div class="flex items-baseline gap-2 mt-1">
      <span id="water-count" class="text-5xl font-black"><?= $todayCount ?></span>
      <span class="text-lg text-white/70">/ <?= $goal ?> glasses</span>
    </div>
    <div class="h-1.5 bg-white/20 mt-3 overflow-hidden">
      <div id="water-bar" class="h-full bg-white transition-all" style="width: <?= (int)$pct ?>%"></div>
    </div>
    <div class="grid grid-cols-2 gap-2 mt-4">
      <button type="button" onclick="logWater(1)" class="bg-white text-black py-3 text-xs font-bold uppercase tracking-widest active:scale-95 transition-transform">+ 1 Glass</button>
      <button type="button" onclick="logWater(-1)" class="border border-white/40 py-3 text-xs font-bold uppercase tracking-widest active:scale-95 transition-transform">− 1 Glass</button>
    </div>
  </section>

  <section class="grid grid-cols-2 gap-3">
    <div class="border border-black p-3">
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">7-day avg</p>
      <p class="text-2xl font-black"><?= $avgGlasses ?></p>
    </div>
    <div class="border border-black p-3">
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Goal met</p>
      <p class="text-2xl font-black"><?= $goalMetDays ?> / 7</p>
    </div>
  </section>

  <section>
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Last 7 days</p>
    <div class="border border-black divide-y divide-gray-200">
      <?php foreach ($last7 as $d):
        $met = $d['glasses'] >= $goal;
      ?>
        <div class="px-3 py-2 flex items-center justify-between gap-2">
          <p class="text-xs"><?= htmlspecialchars(date('D, M j', strtotime($d['date']))) ?></p>
          <div class="flex items-center gap-2">
            <span class="text-sm font-bold"><?= $d['glasses'] ?></span>
            <span class="<?= $met ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?> px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest"><?= $met ? '✓' : 'low' ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
async function logWater(delta) {
  try {
    const action = delta > 0 ? 'log' : 'undo';
    const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/water.php?action=' + action, {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ csrf_token: CSRF_TOKEN, glasses: Math.abs(delta) })
    });
    const res = await r.json();
    if (res && res.success) {
      const newCount = parseInt(res.data && (res.data.glasses ?? res.data.count) || 0, 10);
      document.getElementById('water-count').textContent = newCount;
      const goal = <?= (int)$goal ?>;
      document.getElementById('water-bar').style.width = Math.min(100, (newCount / goal) * 100) + '%';
    } else {
      alert((res.error && res.error.message) || 'Failed');
    }
  } catch (e) {
    alert('Network error');
  }
}
</script>

</body>
</html>
