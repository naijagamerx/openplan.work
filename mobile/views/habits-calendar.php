<?php
/**
 * Mobile Habits Calendar — month view, completions per day.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$db = new Database(getMasterPassword(), Auth::userId());
$habits = $db->load('habits');
$completions = $db->load('habit_completions');

$ym = $_GET['ym'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { $ym = date('Y-m'); }
[$year, $month] = array_map('intval', explode('-', $ym));
$firstDay = strtotime("$ym-01");
$daysInMonth = (int)date('t', $firstDay);
$startDow = (int)date('w', $firstDay); // 0=Sun

$prevYm = date('Y-m', strtotime("$ym-01 -1 month"));
$nextYm = date('Y-m', strtotime("$ym-01 +1 month"));

$completedByDate = [];
foreach ($completions as $c) {
    if (($c['status'] ?? '') !== 'complete') continue;
    $d = $c['date'] ?? '';
    if (substr($d, 0, 7) !== $ym) continue;
    $completedByDate[$d] = ($completedByDate[$d] ?? 0) + 1;
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Habits Calendar - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Habits Calendar';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <div class="flex items-center justify-between">
    <a href="?page=habits-calendar&ym=<?= $prevYm ?>" class="border border-black px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest">‹ Prev</a>
    <h2 class="text-lg font-black"><?= htmlspecialchars(date('F Y', $firstDay)) ?></h2>
    <a href="?page=habits-calendar&ym=<?= $nextYm ?>" class="border border-black px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest">Next ›</a>
  </div>

  <div class="grid grid-cols-7 gap-1 text-center">
    <?php foreach (['S','M','T','W','T','F','S'] as $d): ?>
      <div class="text-[10px] font-bold uppercase tracking-widest text-gray-400 py-1"><?= $d ?></div>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < $startDow; $i++): ?>
      <div></div>
    <?php endfor; ?>
    <?php for ($day = 1; $day <= $daysInMonth; $day++):
      $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $count = $completedByDate[$date] ?? 0;
      $isToday = $date === date('Y-m-d');
    ?>
      <div class="aspect-square flex flex-col items-center justify-center border <?= $count > 0 ? 'bg-black text-white border-black' : 'border-gray-200' ?> <?= $isToday ? 'ring-2 ring-black ring-offset-1' : '' ?>">
        <p class="text-xs font-bold"><?= $day ?></p>
        <?php if ($count > 0): ?>
          <p class="text-[9px]"><?= $count ?></p>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

  <div class="flex gap-2">
    <a href="?page=habit-history" class="flex-1 text-center border border-black py-2 text-[10px] font-bold uppercase tracking-widest">History</a>
    <a href="?page=habits" class="flex-1 text-center bg-black text-white py-2 text-[10px] font-bold uppercase tracking-widest">All Habits</a>
  </div>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
</body>
</html>
