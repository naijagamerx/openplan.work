<?php
/**
 * Mobile 404 Page
 */

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Not Found - <?= htmlspecialchars(getSiteName()) ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png">
</head>
<body class="min-h-screen bg-white text-black">
    <main class="min-h-screen flex flex-col items-center justify-center px-6 text-center">
        <p class="text-xs font-bold uppercase tracking-[0.25em] text-gray-500 mb-3">Error 404</p>
        <h1 class="text-3xl font-black tracking-tight mb-3">Page Not Found</h1>
        <p class="text-sm text-gray-600 max-w-xs mb-8">The page you requested is unavailable in the mobile interface.</p>
        <div class="flex gap-3">
            <a href="?page=dashboard" class="px-5 py-3 bg-black text-white rounded-lg text-sm font-bold uppercase tracking-wide">Dashboard</a>
            <a href="?page=dashboard&device=desktop" class="px-5 py-3 border border-black rounded-lg text-sm font-bold uppercase tracking-wide">Desktop</a>
        </div>
    </main>
</body>
</html>
