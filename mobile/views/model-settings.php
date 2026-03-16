<?php
/**
 * Mobile Model Settings Overview Page
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if ($masterPassword === '') {
    header('Location: ?page=login&error=session_missing');
    exit;
}

$db = new Database($masterPassword, Auth::userId());
$modelsLoad = $db->safeLoad('models');
$models = $modelsLoad['success'] ? $modelsLoad['data'] : [];
$groqModels = is_array($models['groq'] ?? null) ? $models['groq'] : [];
$openRouterModels = is_array($models['openrouter'] ?? null) ? $models['openrouter'] : [];

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Model Settings - <?= htmlspecialchars($siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } }
};
</script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">
<?php
$title = 'Model Settings';
$leftAction = 'back';
$backUrl = '?page=settings';
include __DIR__ . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto px-4 py-6 space-y-4 pb-28">
  <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
    <h2 class="text-sm font-bold uppercase tracking-wider">Configured Models</h2>
    <p class="text-xs text-gray-500 mt-2">Review enabled/default models currently stored for this workspace.</p>
  </section>

  <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
    <h3 class="text-sm font-bold uppercase tracking-wider">Groq (<?= count($groqModels) ?>)</h3>
    <div class="mt-3 space-y-2">
      <?php if (empty($groqModels)): ?>
        <p class="text-xs text-gray-500">No Groq models configured.</p>
      <?php else: ?>
        <?php foreach ($groqModels as $model): ?>
          <div class="border border-gray-200 dark:border-zinc-700 p-3">
            <p class="text-sm font-semibold"><?= htmlspecialchars((string)($model['displayName'] ?? $model['modelId'] ?? 'Unnamed model')) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars((string)($model['modelId'] ?? '')) ?></p>
            <p class="text-xs mt-1">
              <?= !empty($model['enabled']) ? 'Enabled' : 'Disabled' ?>
              <?= !empty($model['isDefault']) ? ' • Default' : '' ?>
            </p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
    <h3 class="text-sm font-bold uppercase tracking-wider">OpenRouter (<?= count($openRouterModels) ?>)</h3>
    <div class="mt-3 space-y-2">
      <?php if (empty($openRouterModels)): ?>
        <p class="text-xs text-gray-500">No OpenRouter models configured.</p>
      <?php else: ?>
        <?php foreach ($openRouterModels as $model): ?>
          <div class="border border-gray-200 dark:border-zinc-700 p-3">
            <p class="text-sm font-semibold"><?= htmlspecialchars((string)($model['displayName'] ?? $model['modelId'] ?? 'Unnamed model')) ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars((string)($model['modelId'] ?? '')) ?></p>
            <p class="text-xs mt-1">
              <?= !empty($model['enabled']) ? 'Enabled' : 'Disabled' ?>
              <?= !empty($model['isDefault']) ? ' • Default' : '' ?>
            </p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </section>

  <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
    <a href="?page=settings#api-section" class="block w-full bg-black text-white py-3 text-center text-sm font-bold uppercase tracking-wider">Manage API Keys in Settings</a>
  </section>
</main>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>
</div>
</body>
</html>
