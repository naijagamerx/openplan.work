<?php
$siteName = getPublicAppName();
$pageTitle = $siteName . ' | Public Documentation';
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo e($pageTitle); ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo APP_URL; ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo APP_URL; ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?php echo APP_URL; ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo APP_URL; ?>/assets/favicons/apple-touch-icon.png"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#000000",
                        "background-light": "#FFFFFF",
                        "background-dark": "#0A0A0A",
                    },
                    fontFamily: {
                        display: ["Inter", "sans-serif"],
                        sans: ["Inter", "sans-serif"],
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark text-zinc-900 dark:text-zinc-100 antialiased transition-colors duration-300">
<header class="sticky top-0 z-40 border-b border-black/10 dark:border-white/10 bg-white/90 dark:bg-black/80 backdrop-blur">
<div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
<div class="flex items-center gap-3">
<div class="w-7 h-7 bg-black text-white dark:bg-white dark:text-black rounded flex items-center justify-center">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm0 11h7v7h-7v-7z"></path>
</svg>
</div>
<span class="text-sm font-bold tracking-tight"><?php echo e($siteName); ?></span>
</div>
<div class="flex items-center gap-4 text-xs uppercase tracking-[0.25em] font-semibold text-black/60 dark:text-white/70">
<a class="hover:text-black dark:hover:text-white transition-colors" href="<?php echo APP_URL; ?>?page=homepage">Home</a>
<a class="hover:text-black dark:hover:text-white transition-colors" href="<?php echo APP_URL; ?>?page=login">Login</a>
</div>
</div>
</header>
<main class="max-w-6xl mx-auto px-6 py-16">
<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-8 border-b border-zinc-200 dark:border-zinc-800 pb-10">
<div>
<p class="text-xs uppercase tracking-[0.3em] text-zinc-400">Public Documentation</p>
<h1 class="text-4xl sm:text-5xl font-black tracking-tight mt-4"><?php echo e($siteName); ?></h1>
<p class="text-lg text-zinc-500 dark:text-zinc-400 mt-4 max-w-2xl">
                The encrypted PHP workspace for teams and solo builders who want ownership of their data.
            </p>
</div>
<div class="flex gap-3">
<a class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] border border-black text-black dark:text-white dark:border-white px-6 py-2 rounded hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-all" href="<?php echo APP_URL; ?>?page=register">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
</svg>
                Create Account
            </a>
</div>
</div>
<section class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-12">
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 bg-white dark:bg-zinc-950">
<h2 class="text-sm font-bold uppercase tracking-[0.2em] mb-4">What It Is</h2>
<ul class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
<li>A self-hosted PHP productivity suite for tasks, projects, notes, habits, invoices, inventory, and AI-assisted workflows.</li>
<li>Local-first storage built on encrypted JSON files.</li>
<li>Portable deployments for both local and hosted environments.</li>
</ul>
</div>
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 bg-white dark:bg-zinc-950">
<h2 class="text-sm font-bold uppercase tracking-[0.2em] mb-4">Core Modules</h2>
<ul class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
<li>Tasks and Projects</li>
<li>Notes and Knowledge Base</li>
<li>Habits and Pomodoro</li>
<li>Invoices, Quotes, Inventory</li>
</ul>
</div>
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 bg-white dark:bg-zinc-950">
<h2 class="text-sm font-bold uppercase tracking-[0.2em] mb-4">Security</h2>
<ul class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
<li>AES-256-GCM encryption for stored JSON data.</li>
<li>Master password controls data access.</li>
<li>Session timeout enforcement and audit logging.</li>
</ul>
</div>
</section>
<section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-12">
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 bg-white dark:bg-zinc-950">
<h2 class="text-xl font-black mb-4">Data Model</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 mb-4">
                Data is stored as encrypted JSON records per user workspace. Each module persists its own collections, keeping tasks, projects, notes, clients, and invoices isolated.
            </p>
<ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
<li>Encrypted JSON collections per module</li>
<li>Per-user storage isolation</li>
<li>Backups and exports regenerate clean data folders</li>
</ul>
</div>
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 bg-white dark:bg-zinc-950">
<h2 class="text-xl font-black mb-4">Authentication Flow</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 mb-4">
                Users authenticate with email + password and a master key. The master key encrypts all stored data and must be retained for future access.
            </p>
<ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
<li>Master key encryption</li>
<li>Optional email verification</li>
<li>Session timeout configuration</li>
</ul>
</div>
</section>
<section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-12">
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 bg-white dark:bg-zinc-950">
<h2 class="text-xl font-black mb-4">Configuration</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 mb-4">
                Configure email, branding, and hosted-only toggles through environment variables and the app settings UI.
            </p>
<ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
<li>.env.example for baseline configuration</li>
<li>MAIL, SMTP, and app branding values</li>
<li>Hosted feature flags for auth + image service</li>
</ul>
</div>
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 bg-white dark:bg-zinc-950">
<h2 class="text-xl font-black mb-4">Exports</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 mb-4">
                Exports create clean release artifacts without secrets or live data, ready for public distribution or handoff.
            </p>
<ul class="space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
<li>Clean data skeleton</li>
<li>No user data or sessions</li>
<li>Release manifest included</li>
</ul>
</div>
</section>
<section class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-12">
<div class="border border-black dark:border-white rounded-2xl p-8 bg-black text-white dark:bg-white dark:text-black">
<h2 class="text-2xl font-black mb-4">Local Run</h2>
<p class="text-sm text-white/70 dark:text-black/70 mb-6">
                Requirements: PHP 8.0+, json, mbstring, openssl.
            </p>
<div class="text-xs uppercase tracking-[0.2em] font-bold">
<div class="flex items-center justify-between border-b border-white/20 dark:border-black/20 py-2">
<span>php start_server.php</span>
<span>CLI</span>
</div>
<div class="flex items-center justify-between border-b border-white/20 dark:border-black/20 py-2">
<span>start_server.bat</span>
<span>Windows</span>
</div>
</div>
</div>
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 bg-white dark:bg-zinc-950">
<h2 class="text-2xl font-black mb-4">Hosted Run</h2>
<p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">
                Hosted installs rely on environment configuration for auth and mail features.
            </p>
<p class="text-sm text-zinc-600 dark:text-zinc-300">
                Use .env.example as a starting point for deployment settings, then define hosted-only flags and secrets in your server environment.
            </p>
</div>
</section>
<section class="mt-12 border-t border-zinc-200 dark:border-zinc-800 pt-10">
<h2 class="text-xl font-black mb-4">Export and Releases</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 max-w-3xl">
            Release exports exclude live data, sessions, and secrets. Generated ZIPs include a clean data structure for safe distribution and onboarding.
        </p>
</section>
<section class="mt-12 border-t border-zinc-200 dark:border-zinc-800 pt-10">
<h2 class="text-xl font-black mb-4">Branding</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 max-w-3xl">
            Clean high-contrast design language with light and dark modes. Logos and icons are intentionally minimal.
        </p>
</section>
<section class="mt-12 border-t border-zinc-200 dark:border-zinc-800 pt-10">
<h2 class="text-xl font-black mb-4">License</h2>
<p class="text-sm text-zinc-600 dark:text-zinc-300 max-w-3xl">
            Add a LICENSE file before distributing as open source.
        </p>
</section>
</main>
<script>
        const html = document.documentElement;
        if (localStorage.theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
    </script>
</body>
</html>
