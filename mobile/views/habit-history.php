<?php
/**
 * Mobile Habit History — all completed habit records grouped by date.
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$db = new Database(getMasterPassword(), Auth::userId());
$habits = $db->load('habits');
$completions = $db->load('habit_completions');

$habitMap = [];
foreach ($habits as $h) { $habitMap[$h['id']] = $h; }

$completedHistory = array_values(array_filter($completions, fn($c) => ($c['status'] ?? '') === 'complete'));
usort($completedHistory, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

$totalCompletions = count($completedHistory);
$uniqueHabitsCompleted = count(array_unique(array_column($completedHistory, 'habitId')));

$historyByDate = [];
foreach ($completedHistory as $c) {
    $d = $c['date'] ?? '';
    if (!isset($historyByDate[$d])) { $historyByDate[$d] = []; }
    $historyByDate[$d][] = $c;
}
krsort($historyByDate);

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Habit History - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Habit History';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <section class="grid grid-cols-2 gap-3">
    <div class="border border-black p-3">
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Total</p>
      <p class="text-2xl font-black"><?= number_format($totalCompletions) ?></p>
    </div>
    <div class="border border-black p-3">
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Unique habits</p>
      <p class="text-2xl font-black"><?= number_format($uniqueHabitsCompleted) ?></p>
    </div>
  </section>

  <div class="flex gap-2">
    <a href="?page=habits-calendar" class="flex-1 text-center border border-black py-2 text-[10px] font-bold uppercase tracking-widest">Calendar</a>
    <a href="?page=habits" class="flex-1 text-center bg-black text-white py-2 text-[10px] font-bold uppercase tracking-widest">All Habits</a>
  </div>

  <?php if (empty($historyByDate)): ?>
    <section class="border-2 border-black p-6 text-center"><p class="text-sm font-bold">No history yet</p><p class="text-xs text-gray-500 mt-1">Complete a habit to see it here.</p></section>
  <?php else: ?>
    <?php foreach ($historyByDate as $date => $items): ?>
      <section class="border border-black">
        <div class="bg-black text-white px-3 py-2 text-[10px] font-bold uppercase tracking-widest">
          <?= htmlspecialchars(date('D, M j, Y', strtotime($date))) ?> &middot; <?= count($items) ?>
        </div>
        <div class="divide-y divide-gray-200">
          <?php foreach ($items as $c):
            $h = $habitMap[$c['habitId'] ?? ''] ?? null;
            $name = $h['name'] ?? '(deleted habit)';
          ?>
            <div class="px-3 py-2 flex items-center justify-between gap-2">
              <p class="text-sm truncate"><?= htmlspecialchars($name) ?></p>
              <?php if (!empty($c['completedAt'])): ?>
                <span class="text-[10px] text-gray-500 shrink-0"><?= htmlspecialchars(date('g:i a', strtotime($c['completedAt']))) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>
</body>
</html>
