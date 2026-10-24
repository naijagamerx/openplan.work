<?php
/**
 * Mobile Kanban Board — task columns by status.
 * Mobile uses a tabbed-column layout (one column visible at a time).
 */

if (!Auth::check()) { header('Location: ?page=login'); exit; }

$db = new Database(getMasterPassword(), Auth::userId());
$projects = $db->load('projects');

$projectIdFilter = $_GET['projectId'] ?? '';
$selectedProject = null;

$allTasks = [];
foreach ($projects as $project) {
    if (!empty($projectIdFilter) && ($project['id'] ?? '') !== $projectIdFilter) continue;
    if (!empty($projectIdFilter)) $selectedProject = $project;
    foreach (($project['tasks'] ?? []) as $task) {
        $task['projectName'] = $project['name'] ?? '';
        $task['projectId'] = $project['id'] ?? '';
        $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
        $allTasks[] = $task;
    }
}

$columns = [
    'todo' => 'To Do',
    'in_progress' => 'In Progress',
    'review' => 'Review',
    'done' => 'Done'
];

$tasksByStatus = [];
foreach ($columns as $key => $_) { $tasksByStatus[$key] = []; }
foreach ($allTasks as $task) {
    $st = $task['status'] ?? 'todo';
    if (isset($tasksByStatus[$st])) { $tasksByStatus[$st][] = $task; }
    elseif (isset($tasksByStatus['todo'])) { $tasksByStatus['todo'][] = $task; }
}

$active = $_GET['col'] ?? 'todo';
if (!isset($columns[$active])) { $active = 'todo'; }

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light overflow-x-hidden" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Kanban - <?= htmlspecialchars($siteName) ?></title>
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
$title = $selectedProject ? ('Kanban · ' . ($selectedProject['name'] ?? '')) : 'Kanban';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=task-form' . ($projectIdFilter ? ('&projectId=' . urlencode($projectIdFilter)) : '');
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-4 text-zinc-900 dark:text-zinc-100">

  <div class="flex gap-1 overflow-x-auto no-scrollbar">
    <?php foreach ($columns as $key => $label): $cnt = count($tasksByStatus[$key]); ?>
      <a href="?page=kanban-board&col=<?= $key ?><?= $projectIdFilter ? '&projectId=' . urlencode($projectIdFilter) : '' ?>" class="shrink-0 px-3 py-1.5 text-[10px] font-bold uppercase tracking-widest <?= $active === $key ? 'bg-black text-white' : 'border border-black' ?>">
        <?= htmlspecialchars($label) ?> · <?= $cnt ?>
      </a>
    <?php endforeach; ?>
  </div>

  <section class="space-y-2">
    <?php if (empty($tasksByStatus[$active])): ?>
      <div class="border-2 border-black p-6 text-center"><p class="text-sm font-bold">No tasks in <?= htmlspecialchars($columns[$active]) ?></p></div>
    <?php else: ?>
      <?php foreach ($tasksByStatus[$active] as $task):
        $priority = $task['priority'] ?? 'medium';
        $priColor = match($priority) {
          'urgent' => 'bg-red-100 text-red-800',
          'high' => 'bg-orange-100 text-orange-800',
          'low' => 'bg-gray-100 text-gray-700',
          default => 'bg-blue-100 text-blue-800'
        };
      ?>
        <article class="border border-black p-3 space-y-1" data-task-id="<?= htmlspecialchars($task['id'] ?? '') ?>" data-project-id="<?= htmlspecialchars($task['projectId'] ?? '') ?>">
          <div class="flex items-start justify-between gap-2">
            <p class="font-bold text-sm flex-1"><?= htmlspecialchars($task['title'] ?? '') ?></p>
            <span class="<?= $priColor ?> px-2 py-0.5 text-[9px] font-bold uppercase tracking-widest shrink-0"><?= htmlspecialchars($priority) ?></span>
          </div>
          <?php if (!empty($task['projectName']) && !$projectIdFilter): ?>
            <p class="text-[10px] text-gray-500"><?= htmlspecialchars($task['projectName']) ?></p>
          <?php endif; ?>
          <?php if (!empty($task['dueDate'])): ?>
            <p class="text-[10px] text-gray-600">Due <?= htmlspecialchars(date('M j', strtotime($task['dueDate']))) ?></p>
          <?php endif; ?>
          <div class="flex gap-1 pt-1 text-[9px] font-bold uppercase tracking-widest">
            <?php foreach ($columns as $k => $l): if ($k === $active) continue; ?>
              <button type="button" onclick="moveTask(this, '<?= $k ?>')" class="border border-black px-2 py-1 active:bg-gray-100">→ <?= htmlspecialchars(substr($l, 0, 4)) ?></button>
            <?php endforeach; ?>
            <a href="?page=view-task&id=<?= htmlspecialchars($task['id']) ?>&projectId=<?= htmlspecialchars($task['projectId']) ?>" class="ml-auto bg-black text-white px-2 py-1">Open</a>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>

</div>

<script>
async function moveTask(btn, newStatus) {
  const article = btn.closest('article');
  const taskId = article.dataset.taskId;
  const projectId = article.dataset.projectId;
  if (!taskId || !projectId) return;
  try {
    const r = await fetch((APP_URL ? APP_URL + '/' : '') + 'api/tasks.php?id=' + encodeURIComponent(taskId), {
      method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
      body: JSON.stringify({ projectId, status: newStatus, csrf_token: CSRF_TOKEN })
    });
    const res = await r.json();
    if (res && res.success) location.reload();
    else alert((res.error && res.error.message) || 'Failed');
  } catch (e) { alert('Network error'); }
}
</script>

</body>
</html>
