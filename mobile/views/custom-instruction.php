<?php
/**
 * Mobile Custom Instructions Page
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
$config = $db->load('config');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token.';
    } else {
        $config['customInstructions'] = trim((string)($_POST['customInstructions'] ?? ''));
        $config['customInstructionsUpdatedAt'] = date('c');
        if ($db->save('config', $config)) {
            $success = 'Custom instructions saved.';
        } else {
            $error = 'Failed to save custom instructions.';
        }
    }
}

$siteName = getSiteName() ?? 'LazyMan';
$instructions = (string)($config['customInstructions'] ?? '');
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Custom Instructions - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Custom Instructions';
$leftAction = 'back';
$backUrl = '?page=settings';
include __DIR__ . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto px-4 py-6 space-y-4 pb-28">
  <?php if ($success !== ''): ?>
    <div class="p-3 bg-green-50 text-green-700 text-sm border border-green-200"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="p-3 bg-red-50 text-red-700 text-sm border border-red-200"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
    <h2 class="text-sm font-bold uppercase tracking-wider">AI Behavior Settings</h2>
    <p class="text-xs text-gray-500 mt-2">These instructions can be referenced by your AI workflows and agents.</p>

    <form method="POST" class="mt-4 space-y-3">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <textarea
        name="customInstructions"
        rows="12"
        placeholder="Example: Prioritize security checks before feature work."
        class="w-full border border-gray-300 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800 p-3 text-sm focus:ring-0 focus:outline-none"
      ><?= htmlspecialchars($instructions) ?></textarea>
      <button type="submit" class="w-full bg-black text-white py-3 text-sm font-bold uppercase tracking-wider">Save Instructions</button>
    </form>
  </section>

  <section class="border border-gray-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4">
    <h3 class="text-sm font-bold uppercase tracking-wider">Notes</h3>
    <ul class="text-xs text-gray-600 mt-2 space-y-2 list-disc pl-4">
      <li>Keep instructions short and actionable.</li>
      <li>Avoid putting secrets in this field.</li>
      <li>Use this as your mobile quick-edit source for AI behavior text.</li>
    </ul>
  </section>
</main>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>
</div>
</body>
</html>
