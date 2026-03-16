<?php

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$flash = pullAuthFlash();
$title = trim((string)($flash['title'] ?? 'Account created.'));
$statusLabel = trim((string)($flash['status_label'] ?? 'Security Status'));
$message = trim((string)($flash['message'] ?? 'Your account is ready.'));
$detail = trim((string)($flash['detail'] ?? 'You can sign in to continue.'));
$ctaHref = trim((string)($flash['cta_href'] ?? '?page=login'));
$ctaLabel = trim((string)($flash['cta_label'] ?? 'Sign In'));
$mailSent = (bool)($flash['mail_sent'] ?? false);
$email = trim((string)($flash['email'] ?? ''));
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Welcome - <?= htmlspecialchars(getSiteName()) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer base {
            body {
                @apply bg-white text-black font-display antialiased;
            }
        }
        .touch-target {
            min-height: 44px;
            min-width: 44px;
        }
    </style>
</head>
<body class="bg-gray-50 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-gray-100 dark:border-zinc-800 overflow-hidden">
    <main class="flex-1 px-6 py-10 flex flex-col">
        <div class="flex items-center justify-center">
            <div class="size-16 border-2 border-black dark:border-white flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>

        <h1 class="mt-8 text-2xl font-black tracking-tight text-center text-black dark:text-white"><?= htmlspecialchars($title) ?></h1>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 text-center"><?= htmlspecialchars($message) ?></p>

        <div class="mt-6 border border-gray-200 dark:border-zinc-800 p-4">
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400"><?= htmlspecialchars($statusLabel) ?></p>
            <?php if ($email !== ''): ?>
                <p class="mt-2 text-sm font-semibold text-black dark:text-white"><?= htmlspecialchars($email) ?></p>
            <?php endif; ?>
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400"><?= htmlspecialchars($detail) ?></p>
            <?php if (isEmailVerificationEnabled()): ?>
                <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">Verification email: <?= $mailSent ? 'Sent' : 'Not sent' ?></p>
            <?php endif; ?>
        </div>

        <div class="mt-8">
            <a href="<?= htmlspecialchars($ctaHref) ?>"
               class="w-full bg-black dark:bg-white text-white dark:text-black py-4 font-bold uppercase tracking-widest text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity touch-target">
                <?= htmlspecialchars($ctaLabel) ?>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                </svg>
            </a>
        </div>

        <p class="mt-8 text-center text-[11px] text-gray-500 dark:text-gray-400">You can close this page after signing in.</p>
    </main>
</div>
</body>
</html>

