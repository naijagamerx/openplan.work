<?php

$missingMobilePage = isset($missingMobilePage) ? (string)$missingMobilePage : '';
$desktopUrl = APP_URL . '?page=' . urlencode($missingMobilePage) . '&device=desktop';
$dashboardUrl = '?page=dashboard';

?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Mobile Page Unavailable - <?= htmlspecialchars(getSiteName()) ?></title>

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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
        </div>

        <h1 class="mt-8 text-2xl font-black tracking-tight text-center text-black dark:text-white">Mobile page not available</h1>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300 text-center">
            This page isn’t built for mobile yet.
        </p>

        <div class="mt-8 space-y-3">
            <a href="<?= htmlspecialchars($desktopUrl) ?>"
               class="w-full bg-black dark:bg-white text-white dark:text-black py-4 font-bold uppercase tracking-widest text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity touch-target">
                Open Desktop Version
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                </svg>
            </a>

            <a href="<?= htmlspecialchars($dashboardUrl) ?>"
               class="w-full border border-gray-200 dark:border-zinc-800 text-gray-800 dark:text-gray-200 py-4 font-bold uppercase tracking-widest text-xs flex items-center justify-center gap-2 hover:border-black dark:hover:border-white transition-colors touch-target">
                Back to Mobile Dashboard
            </a>
        </div>
    </main>
</div>
</body>
</html>

