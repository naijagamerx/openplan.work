<?php
$siteName = getPublicAppName();
$pageTitle = $siteName . ' | The Encrypted PHP Workspace';
$releaseDownloadUrl = '';
$hasHostedRelease = false;
$releaseArtifactsDir = ROOT_PATH . '/release-artifacts';
if (is_dir($releaseArtifactsDir)) {
    $hostedCandidates = glob($releaseArtifactsDir . '/*-hosted-clean-*.zip');
    if (is_array($hostedCandidates) && !empty($hostedCandidates)) {
        usort($hostedCandidates, static function(string $a, string $b): int {
            return filemtime($b) <=> filemtime($a);
        });
        $latestHosted = $hostedCandidates[0];
        $releaseDownloadUrl = APP_URL . '/release-artifacts/' . basename($latestHosted);
        $hasHostedRelease = true;
    }
}
$repoUrl = 'https://github.com/naijagamerx/openplan.work';
$publicDocsUrl = APP_URL . '?page=docs';
$isAuthenticated = Auth::check();
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
                    borderRadius: {
                        DEFAULT: "0.5rem",
                    },
                },
            },
        };
    </script>
<style>
        body {
            font-family: 'Inter', sans-serif;
        }
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #e5e5e5;
            border-radius: 10px;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #262626;
        }
        .perspective-mockup {
            transform: perspective(1200px) rotateY(-10deg) rotateX(5deg) rotateZ(2deg);
            box-shadow: -20px 30px 60px rgba(0,0,0,0.5);
            transition: transform 0.5s ease;
        }
        .perspective-mockup:hover {
            transform: perspective(1200px) rotateY(-5deg) rotateX(2deg) rotateZ(1deg);
        }
        .bg-diagonal-split {
            background: linear-gradient(105deg, #000000 0%, #000000 55%, #f4f4f5 55%, #f4f4f5 100%);
        }
        .dark .bg-diagonal-split {
            background: linear-gradient(105deg, #000000 0%, #000000 55%, #09090b 55%, #09090b 100%);
        }
        .nav-shell {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }
        .nav-shell.is-sticky {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.92);
            border-color: rgba(0, 0, 0, 0.08);
        }
        .nav-shell.is-sticky .nav-text {
            color: rgba(0, 0, 0, 0.7);
        }
        .nav-shell.is-sticky .nav-text:hover {
            color: #000000;
        }
        .nav-shell.is-sticky .nav-title {
            color: #000000;
        }
        .nav-shell.is-sticky .nav-badge {
            color: rgba(0, 0, 0, 0.7);
        }
        .nav-shell.is-sticky .nav-badge:hover {
            color: #000000;
        }
        .nav-shell.is-sticky .nav-icon {
            color: rgba(0, 0, 0, 0.7);
        }
        .nav-shell.is-sticky .nav-icon:hover {
            color: #000000;
        }
        .nav-shell.is-sticky .nav-logo {
            background: #000000;
            color: #ffffff;
        }
        .dark .nav-shell.is-sticky {
            background: rgba(0, 0, 0, 0.8);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .dark .nav-shell.is-sticky .nav-text,
        .dark .nav-shell.is-sticky .nav-title,
        .dark .nav-shell.is-sticky .nav-icon {
            color: rgba(255, 255, 255, 0.85);
        }
        .dark .nav-shell.is-sticky .nav-text:hover,
        .dark .nav-shell.is-sticky .nav-icon:hover {
            color: #ffffff;
        }
        .dark .nav-shell.is-sticky .nav-logo {
            background: #ffffff;
            color: #000000;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-zinc-900 dark:text-zinc-100 antialiased transition-colors duration-300">
<nav class="nav-shell absolute top-0 w-full z-50 border-b border-white/10 dark:border-white/10 backdrop-blur">
<div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
<div class="flex items-center gap-2 group cursor-pointer text-white">
<div class="nav-logo w-8 h-8 bg-white text-black rounded flex items-center justify-center">
<svg class="w-5 h-5" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
    <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
</svg>
</div>
<span class="nav-title text-xl font-bold tracking-tight"><?php echo e($siteName); ?></span>
</div>
<div class="hidden md:flex items-center gap-10 text-white/80">
<a class="nav-text text-sm font-medium hover:text-white transition-colors" href="#features">Features</a>
<a class="nav-text text-sm font-medium hover:text-white transition-colors" href="#docs">Documentation</a>
<a class="nav-text text-sm font-bold hover:text-white transition-colors" href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">GitHub</a>
</div>
<div class="flex items-center gap-4">
<?php if ($isAuthenticated): ?>
<a class="nav-badge hidden sm:inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-black hover:text-black transition-colors" href="<?php echo APP_URL; ?>?page=dashboard">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3"></path>
</svg>
Dashboard
</a>
<?php else: ?>
<a class="nav-badge hidden sm:inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-black hover:text-black transition-colors" href="<?php echo APP_URL; ?>?page=login">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4m-4-4 4 4m0 0-4 4m4-4H3"></path>
</svg>
Login
</a>
<?php if (isRegistrationEnabled()): ?>
<a class="nav-badge hidden sm:inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-black hover:text-black transition-colors" href="<?php echo APP_URL; ?>?page=register">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
</svg>
Register
</a>
<?php endif; ?>
<?php endif; ?>
<button class="nav-icon p-2 text-white/80 hover:bg-white/10 rounded-full transition-colors" id="theme-toggle" type="button">
<svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.364 6.364-1.414-1.414M7.05 7.05 5.636 5.636m12.728 0-1.414 1.414M7.05 16.95l-1.414 1.414M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
</svg>
<svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"></path>
</svg>
</button>
<?php if ($isAuthenticated): ?>
<a class="bg-white text-black px-5 py-2.5 rounded-full text-sm font-semibold hover:opacity-90 transition-all" href="<?php echo APP_URL; ?>?page=dashboard">
                    Access Dashboard
                </a>
<?php elseif (isRegistrationEnabled()): ?>
<a class="bg-white text-black px-5 py-2.5 rounded-full text-sm font-semibold hover:opacity-90 transition-all" href="<?php echo APP_URL; ?>?page=register">
                    Get Started
                </a>
<?php else: ?>
<a class="bg-white text-black px-5 py-2.5 rounded-full text-sm font-semibold hover:opacity-90 transition-all" href="<?php echo APP_URL; ?>?page=login">
                    Sign In
                </a>
<?php endif; ?>
</div>
</div>
</nav>
<header class="relative pt-32 pb-24 lg:pt-48 lg:pb-40 overflow-hidden bg-diagonal-split">
<div class="max-w-7xl mx-auto px-6 relative z-10">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
<div class="lg:col-span-6 text-white relative z-20">
<h1 class="text-6xl sm:text-7xl lg:text-8xl font-black tracking-tighter mb-4 leading-[0.9] uppercase mix-blend-difference">
                OpenPlan<br/>Work
</h1>
<p class="text-2xl sm:text-3xl font-semibold text-white mb-6">
                The Encrypted PHP Workspace
            </p>
<p class="text-lg text-zinc-400 max-w-xl mb-10 leading-relaxed">
                A self-hosted PHP productivity app for tasks, projects, notes, habits, invoices, inventory, and lightweight team workflows. This release is the clean open-source edition.
            </p>
<div class="p-4 border border-zinc-800 rounded-2xl bg-black/50 backdrop-blur-sm max-w-xl">
<div class="flex flex-col sm:flex-row items-center gap-3">
<?php if ($hasHostedRelease): ?>
<a class="w-full sm:w-auto px-6 py-3.5 bg-white text-black rounded-xl font-bold flex items-center justify-center gap-2 hover:bg-zinc-200 transition-all" href="<?php echo e($releaseDownloadUrl); ?>">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-4.5-4.5L12 16.5m0 0L7.5 12m4.5 4.5V3"></path>
</svg>
                        Download Release
                    </a>
<?php else: ?>
<span class="w-full sm:w-auto px-6 py-3.5 bg-zinc-300 text-zinc-700 rounded-xl font-bold flex items-center justify-center gap-2 cursor-not-allowed">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-4.5-4.5L12 16.5m0 0L7.5 12m4.5 4.5V3"></path>
</svg>
                        Release Not Generated Yet
                    </span>
<?php endif; ?>
<a class="w-full sm:w-auto px-6 py-3.5 border border-zinc-700 bg-transparent text-white hover:bg-zinc-800 rounded-xl font-bold flex items-center justify-center gap-2 transition-all" href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">
<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"></path></svg>
                        Fork on GitHub
                    </a>
</div>
<?php if (!$hasHostedRelease): ?>
<p class="mt-3 text-xs text-zinc-400">No hosted release artifact is available yet. An admin can generate one from <code>Settings -> Developer Tools -> Release Export</code>.</p>
<?php endif; ?>
</div>
</div>
<div class="lg:col-span-6 relative z-10 lg:-ml-12 mt-16 lg:mt-0">
<div class="perspective-mockup bg-white dark:bg-[#111111] rounded-2xl overflow-hidden border border-zinc-200 dark:border-zinc-800 shadow-2xl flex h-[650px] overflow-hidden grayscale relative z-30">
<div class="w-64 border-r border-zinc-100 dark:border-zinc-900 hidden lg:flex flex-col p-6">
<div class="flex items-center gap-3 mb-10 px-2">
<div class="w-7 h-7 bg-black dark:bg-white rounded flex items-center justify-center">
<svg class="w-4 h-4 text-white dark:text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
</svg>
</div>
<span class="font-bold">OpenPlan.work</span>
</div>
<div class="space-y-1 overflow-y-auto">
<div class="bg-black dark:bg-white text-white dark:text-black p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0h4"></path>
</svg> Dashboard
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
</svg> Tasks
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h6l2 3h10v9H3z"></path>
</svg> Projects
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-4m-4 6H2v-2a4 4 0 015-4m6 6v-2a4 4 0 00-4-4H7"></path>
</svg> Clients
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20h9"></path>
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 3.5a2.121 2.121 0 113 3L7 19l-4 1 1-4L16.5 3.5z"></path>
</svg> Notes
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6l-2 4H6l3 3-1 4 4-2 4 2-1-4 3-3h-4l-2-4z"></path>
</svg> Knowledge Base
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 19h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"></path>
</svg> Calendar
                            </div>
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"></path>
    <circle cx="12" cy="12" r="9" stroke-width="2"></circle>
</svg> Pomodoro
                            </div>
</div>
<div class="mt-auto pt-6 border-t border-zinc-100 dark:border-zinc-900">
<div class="text-zinc-500 p-2.5 rounded-lg flex items-center gap-3 text-sm font-medium">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317a9 9 0 1110.683 10.683l-2.83-.944a1 1 0 00-1.074.26l-2.302 2.302a1 1 0 01-1.414 0l-2.302-2.302a1 1 0 00-1.074-.26l-2.83.944a9 9 0 0110.683-10.683z"></path>
</svg> Settings
                            </div>
</div>
</div>
<div class="flex-1 bg-zinc-50 dark:bg-[#0c0c0c] overflow-y-auto p-8">
<div class="flex justify-between items-center mb-8">
<h2 class="text-xl font-bold">Dashboard</h2>
<div class="flex items-center gap-4">
<div class="bg-black text-white px-4 py-2 rounded-lg text-xs font-bold">+ Quick Add</div>
<div class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center text-xs font-bold">L</div>
</div>
</div>
<div class="bg-black text-white p-8 rounded-2xl mb-8">
<h3 class="text-2xl font-bold mb-1">Welcome back, Lazy Man!</h3>
<p class="text-zinc-400 text-sm">Friday, March 6, 2026</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
<div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
<p class="text-[10px] text-zinc-400 uppercase tracking-wider font-bold mb-1">Pending Tasks</p>
<p class="text-2xl font-bold">1</p>
<p class="text-[10px] text-zinc-500 mt-2">0 completed</p>
</div>
<div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
<p class="text-[10px] text-zinc-400 uppercase tracking-wider font-bold mb-1">Active Projects</p>
<p class="text-2xl font-bold">5</p>
<p class="text-[10px] text-zinc-500 mt-2">5 total</p>
</div>
<div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
<p class="text-[10px] text-zinc-400 uppercase tracking-wider font-bold mb-1">Habits Today</p>
<p class="text-2xl font-bold">0/9</p>
<div class="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 rounded-full mt-2">
<div class="bg-zinc-300 dark:bg-zinc-600 h-1.5 rounded-full" style="width: 0%"></div>
</div>
</div>
<div class="bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800">
<p class="text-[10px] text-zinc-400 uppercase tracking-wider font-bold mb-1">Total Revenue</p>
<p class="text-2xl font-bold">$0.00</p>
<p class="text-[10px] text-zinc-500 mt-2">$0.00 pending</p>
</div>
</div>
<div class="grid grid-cols-3 gap-6">
<div class="col-span-2 bg-white dark:bg-zinc-900 p-6 rounded-2xl border border-zinc-100 dark:border-zinc-800 flex flex-col items-center justify-center text-center">
<h4 class="font-bold mb-1">Stay Hydrated</h4>
<p class="text-xs text-zinc-400 mb-6">Track your daily water intake with a visual cup</p>
<div class="flex items-center gap-8 w-full justify-center">
<div class="w-32 h-40 border-4 border-t-0 border-zinc-200 dark:border-zinc-700 rounded-b-xl relative flex flex-col justify-end overflow-hidden">
<div class="w-full bg-zinc-100 dark:bg-zinc-800 h-1/4 absolute bottom-0"></div>
<span class="absolute inset-0 flex items-center justify-center font-bold text-2xl text-zinc-400">0%</span>
</div>
<div class="text-left">
<p class="text-4xl font-bold mb-2">0.00 <span class="text-lg text-zinc-400 font-normal">/ 2.50 L</span></p>
<p class="text-xs text-zinc-400 mb-4">0% complete</p>
<div class="flex gap-2">
<div class="bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 rounded text-xs font-bold">+0.25 L</div>
<div class="bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 rounded text-xs font-bold">+0.50 L</div>
<div class="bg-zinc-100 dark:bg-zinc-800 px-3 py-1.5 rounded text-xs font-bold">+1.00 L</div>
</div>
</div>
</div>
</div>
<div class="bg-black text-white p-6 rounded-2xl flex flex-col items-center justify-center text-center">
<h4 class="font-bold mb-6 text-sm self-start">Pomodoro Timer</h4>
<div class="w-32 h-32 border-[8px] border-zinc-800 rounded-full flex items-center justify-center mb-6">
<span class="text-3xl font-bold">25:00</span>
</div>
<p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500 mb-4">READY</p>
<div class="flex gap-2 w-full justify-center">
<div class="bg-white text-black px-6 py-2 rounded-full text-xs font-bold">Start</div>
<div class="border border-zinc-600 text-white px-6 py-2 rounded-full text-xs font-bold">Reset</div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
</header>
<section class="max-w-7xl mx-auto px-6 py-24" id="features">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-16">
<div>
<h2 class="text-4xl font-black mb-10 tracking-tight">What is Included</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex flex-col gap-4 shadow-sm">
<svg class="w-8 h-8 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L3 21V7.5A2.25 2.25 0 015.25 5.25h13.5A2.25 2.25 0 0121 7.5v9a2.25 2.25 0 01-2.25 2.25H9.75z"></path>
</svg>
<p class="font-bold text-sm leading-tight">Desktop and mobile web interfaces</p>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex flex-col gap-4 shadow-sm">
<svg class="w-8 h-8 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5-3 8-7 9-4-1-7-4-7-9V7l7-4z"></path>
</svg>
<p class="font-bold text-sm leading-tight">JSON-encrypted local data storage</p>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex flex-col gap-4 shadow-sm">
<svg class="w-8 h-8 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11V7a4 4 0 00-8 0v4m-2 0h12a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2v-7a2 2 0 012-2z"></path>
</svg>
<p class="font-bold text-sm leading-tight">User authentication with master-key encryption</p>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex flex-col gap-4 shadow-sm">
<svg class="w-8 h-8 text-black dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m-4 4l4-4m0 0L3 8m4 4h14"></path>
</svg>
<p class="font-bold text-sm leading-tight">Core APIs and UI assets needed to run locally</p>
</div>
</div>
</div>
<div>
<h2 class="text-4xl font-black mb-10 tracking-tight">Intentionally Excluded</h2>
<div class="bg-black text-white p-10 rounded-[2rem] flex flex-col justify-center h-full shadow-2xl">
<p class="text-zinc-400 text-base mb-8 leading-relaxed">For technical transparency, the following elements are excluded from the repository to ensure a completely clean and private build environment:</p>
<ul class="space-y-6">
<li class="flex items-center gap-4">
<svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
</svg>
<span class="text-base font-bold">Encrypted user data</span>
</li>
<li class="flex items-center gap-4">
<svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
</svg>
<span class="text-base font-bold">Sessions and local runtime state</span>
</li>
<li class="flex items-center gap-4">
<svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
</svg>
<span class="text-base font-bold">API keys and MCP/local configuration</span>
</li>
<li class="flex items-center gap-4">
<svg class="w-5 h-5 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
</svg>
<span class="text-base font-bold">Vendor dependencies (Portable PHP runtime required)</span>
</li>
</ul>
</div>
</div>
</div>
</section>
<section class="w-full px-6 py-20 border-t border-zinc-100 dark:border-zinc-900">
<div class="max-w-7xl mx-auto">
<div class="relative w-full">
<div class="absolute -inset-6 bg-zinc-200 dark:bg-zinc-800 rounded-[2.5rem] blur-2xl opacity-40"></div>
<div class="relative rounded-[2rem] border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-950 p-4">
<img class="w-full rounded-[1.5rem] border border-zinc-100 dark:border-zinc-900" src="<?php echo APP_URL; ?>/assets/images/chrome_B3N3g51Yeo.png" alt="Workspace preview">
</div>
</div>
</div>
<div class="max-w-7xl mx-auto mt-16">
<h2 class="text-4xl font-black mb-10 tracking-tight">Core Capabilities</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11V7a4 4 0 00-8 0v4m-2 0h12a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2v-7a2 2 0 012-2z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Encrypted by Design</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        AES-256-GCM JSON storage keeps every record locked to your master key.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.674M12 3v2m0 14v2m-7-9h2m12 0h2m-2.879 6.879l-1.414-1.414M7.293 7.293 5.879 5.879m12.242 0l-1.414 1.414M7.293 16.707l-1.414 1.414M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">AI Assist, Local First</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Keep workflows fast with optional AI tools that never take control of your data.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16h8M8 12h8m-8-4h8M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H7l-2 2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Knowledge Base Ready</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Organize notes, documents, and references in a searchable workspace.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3M5 11h14M5 19h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Calendar + Scheduling</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Plan deliveries, reminders, and routines with built-in scheduling.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"></path>
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m-6 4h6m-6 4h4"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Mobile First Access</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Desktop and mobile views stay in sync for on-the-go operations.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Finance + Operations</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Track invoices, quotes, and inventory alongside projects, tasks, and notes.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h6l2 3h10v9H3z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Projects + Tasks</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Prioritize work, track progress, and deliver with shared task boards.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6l-2 4H6l3 3-1 4 4-2 4 2-1-4 3-3h-4l-2-4z"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Habits + Pomodoro</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Build consistent routines with habit tracking and focus timers.
                    </p>
</div>
</div>
<div class="p-8 border border-zinc-200 dark:border-zinc-800 rounded-2xl bg-white dark:bg-zinc-950 flex items-start gap-4 shadow-sm">
<div class="flex-shrink-0 w-12 h-12 bg-black text-white dark:bg-white dark:text-black rounded-xl flex items-center justify-center">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-4m-4 6H2v-2a4 4 0 015-4m6 6v-2a4 4 0 00-4-4H7"></path>
</svg>
</div>
<div>
<h3 class="font-bold text-lg mb-2">Clients + Billing</h3>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed">
                        Manage clients, invoices, and quotes with a clean CRM flow.
                    </p>
</div>
</div>
</div>
</div>
</section>
<section class="max-w-7xl mx-auto px-6 py-20 border-t border-zinc-100 dark:border-zinc-900" id="docs">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">
<div>
<h2 class="text-3xl font-black mb-4 tracking-tight">Public Documentation</h2>
<p class="text-zinc-500 dark:text-zinc-400 text-sm leading-relaxed mb-6">
                A concise, high-contrast reference for setup, security, and modules.
            </p>
<a class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] border border-black text-black dark:text-white dark:border-white px-6 py-2 rounded hover:bg-black hover:text-white dark:hover:bg-white dark:hover:text-black transition-all" href="<?php echo e($publicDocsUrl); ?>">
<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"></path>
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6"></path>
</svg>
                Open Public Docs
            </a>
</div>
<div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl p-8 bg-white dark:bg-zinc-950">
<p class="text-xs uppercase tracking-[0.25em] text-zinc-400 mb-4">What you get</p>
<ul class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
<li>Secure data model and encryption overview</li>
<li>Local, hosted, and export workflows</li>
<li>Core modules: tasks, projects, notes, finance</li>
</ul>
</div>
</div>
</section>
<footer class="py-20 px-6 border-t border-zinc-100 dark:border-zinc-900">
<div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
<div class="flex items-center gap-2">
<div class="w-6 h-6 bg-white dark:bg-white text-black rounded flex items-center justify-center">
<svg class="w-4 h-4" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
    <path clip-rule="evenodd" d="M24 4H6V17.3333V30.6667H24V44H42V30.6667V17.3333H24V4Z" fill="currentColor" fill-rule="evenodd"></path>
</svg>
</div>
<span class="font-bold tracking-tight"><?php echo e($siteName); ?></span>
</div>
<p class="text-zinc-500 text-sm font-medium">
                © <?php echo date('Y'); ?> <?php echo e($siteName); ?>. Built for builders.
            </p>
<div class="flex items-center gap-6">
<a class="text-zinc-400 hover:text-black dark:hover:text-white transition-colors" href="<?php echo e($repoUrl); ?>" target="_blank" rel="noreferrer">
<svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"></path></svg>
</a>
</div>
</div>
</footer>
<script>
        const themeToggle = document.getElementById('theme-toggle');
        const navShell = document.querySelector('.nav-shell');
        const html = document.documentElement;
        if (localStorage.theme === 'dark') {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }
        const handleNavState = () => {
            if (!navShell) {
                return;
            }
            if (window.scrollY > 40) {
                navShell.classList.add('is-sticky');
            } else {
                navShell.classList.remove('is-sticky');
            }
        };
        handleNavState();
        window.addEventListener('scroll', handleNavState);
        themeToggle.addEventListener('click', () => {
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                html.classList.add('dark');
                localStorage.theme = 'dark';
            }
        });
    </script>
</body>
</html>
