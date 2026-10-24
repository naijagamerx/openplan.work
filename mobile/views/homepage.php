<?php
/**
 * Mobile Homepage — marketing landing.
 */
if (!defined('MOBILE_JS_URL')) { require_once __DIR__ . '/../config.php'; }

$siteName = getPublicAppName();
$pageTitle = $siteName . ' | The Encrypted PHP Workspace';
$pageDescription = 'Self-hosted PHP productivity suite for tasks, projects, notes, habits, invoices. AES-256 encrypted workspace that you own.';
$canonicalUrl = APP_URL . '/';
$repoUrl = 'https://github.com/naijagamerx/openplan.work';

$releaseDownloadUrl = '';
$hasHostedRelease = false;
$releaseArtifactsDir = ROOT_PATH . '/release-artifacts';
if (is_dir($releaseArtifactsDir)) {
    $hostedCandidates = glob($releaseArtifactsDir . '/*-hosted-clean-*.zip');
    if (is_array($hostedCandidates) && !empty($hostedCandidates)) {
        usort($hostedCandidates, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $releaseDownloadUrl = APP_URL . '/release-artifacts/' . basename($hostedCandidates[0]);
        $hasHostedRelease = true;
    }
}

$apkDownloadUrl = '';
$hasApkRelease = false;
$apkArtifactsDir = ROOT_PATH . '/release-artifacts/android';
if (is_dir($apkArtifactsDir)) {
    $apkCandidates = glob($apkArtifactsDir . '/*.apk');
    if (is_array($apkCandidates) && !empty($apkCandidates)) {
        usort($apkCandidates, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $apkDownloadUrl = APP_URL . '/release-artifacts/android/' . basename($apkCandidates[0]);
        $hasApkRelease = true;
    }
}

$isAuthenticated = Auth::check();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>"/>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>"/>
<meta property="og:type" content="website"/>
<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>"/>
<meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>"/>

<link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">
  @layer base { body { @apply bg-white text-black font-display antialiased; } }
  .touch-target { min-height: 44px; }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">

  <!-- Top bar -->
  <header class="sticky top-0 z-40 border-b border-black bg-white/95 backdrop-blur px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <div class="w-7 h-7 bg-black text-white flex items-center justify-center">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm0 11h7v7h-7v-7z"/></svg>
      </div>
      <span class="text-sm font-black tracking-tight"><?= htmlspecialchars($siteName) ?></span>
    </div>
    <div class="flex items-center gap-2 text-[10px] uppercase tracking-widest font-bold">
      <a href="?page=docs" class="text-gray-600">Docs</a>
      <?php if ($isAuthenticated): ?>
        <a href="?page=dashboard" class="bg-black text-white px-3 py-1.5">Dashboard</a>
      <?php else: ?>
        <a href="?page=login" class="bg-black text-white px-3 py-1.5">Sign In</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="flex-1">

    <!-- Hero -->
    <section class="px-4 py-12">
      <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-gray-400">Self-hosted &middot; Encrypted</p>
      <h1 class="text-4xl font-black tracking-tight leading-tight mt-3">
        Your workspace.<br>
        <span class="italic">Your data.</span>
      </h1>
      <p class="text-sm text-gray-600 mt-4 leading-relaxed">
        Tasks, projects, notes, habits, invoices, inventory. All on one PHP app you can self-host. AES-256-GCM encrypted at rest. No SaaS lock-in.
      </p>

      <div class="mt-6 space-y-2">
        <?php if (!$isAuthenticated): ?>
          <a href="?page=register" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Create Free Account</a>
          <a href="?page=login" class="block w-full text-center border border-black py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Sign In</a>
        <?php else: ?>
          <a href="?page=dashboard" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Open Dashboard</a>
        <?php endif; ?>
      </div>
    </section>

    <!-- Features -->
    <section class="px-4 py-8 border-t border-black space-y-4">
      <h2 class="text-2xl font-black">Everything in one place</h2>
      <ul class="space-y-3 text-sm">
        <?php
        $features = [
          ['Tasks &middot; Projects', 'Kanban + list view, subtasks, time tracking, AI task generation.'],
          ['Notes &middot; Knowledge Base', 'Tagged, encrypted, AI-augmented note editing.'],
          ['Habits &middot; Pomodoro', 'Streaks, timer, calendar view, history.'],
          ['Invoices &middot; Inventory', 'Multi-currency invoices + product stock management.'],
          ['AI Assistant', 'Groq, OpenRouter, Gemini, Ollama. Function-calling agent for the whole app.'],
          ['Subscriptions', 'Optional SaaS layer to share AI keys with paying users.'],
        ];
        foreach ($features as [$title, $desc]):
        ?>
          <li class="border border-black p-3">
            <p class="font-bold text-sm"><?= $title ?></p>
            <p class="text-xs text-gray-600 mt-0.5"><?= $desc ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

    <!-- Security -->
    <section class="px-4 py-8 border-t border-black bg-black text-white">
      <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-white/60">Security</p>
      <h2 class="text-2xl font-black mt-2">AES-256-GCM by default</h2>
      <ul class="mt-4 space-y-2 text-xs text-white/80">
        <li>&middot; Master password gates all stored data.</li>
        <li>&middot; CSRF on every state-changing request.</li>
        <li>&middot; Session fingerprint + timeout.</li>
        <li>&middot; Bcrypt password hashing.</li>
        <li>&middot; Audit log + rate limiter.</li>
      </ul>
    </section>

    <!-- Downloads -->
    <?php if ($hasHostedRelease || $hasApkRelease): ?>
    <section class="px-4 py-8 border-t border-black space-y-3">
      <h2 class="text-2xl font-black">Self-host or sideload</h2>
      <?php if ($hasHostedRelease): ?>
        <a href="<?= htmlspecialchars($releaseDownloadUrl) ?>" class="block border border-black p-3 active:bg-gray-50">
          <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Hosted Release ZIP</p>
          <p class="text-sm font-bold">Drop on any PHP 8 server</p>
        </a>
      <?php endif; ?>
      <?php if ($hasApkRelease): ?>
        <a href="<?= htmlspecialchars($apkDownloadUrl) ?>" class="block border border-black p-3 active:bg-gray-50">
          <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Android APK</p>
          <p class="text-sm font-bold">Direct install</p>
        </a>
      <?php endif; ?>
      <a href="<?= htmlspecialchars($repoUrl) ?>" target="_blank" class="block border border-black p-3 active:bg-gray-50">
        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">GitHub</p>
        <p class="text-sm font-bold">Source code &amp; issues</p>
      </a>
    </section>
    <?php endif; ?>

    <!-- CTA bottom -->
    <section class="px-4 py-12 border-t border-black text-center">
      <h2 class="text-2xl font-black mb-3">Ready to start?</h2>
      <?php if (!$isAuthenticated): ?>
        <a href="?page=register" class="inline-block w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest">Create Free Account</a>
      <?php else: ?>
        <a href="?page=dashboard" class="inline-block w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest">Open Dashboard</a>
      <?php endif; ?>
    </section>

  </main>

  <footer class="px-4 py-6 border-t border-black flex flex-col gap-3 text-[10px] uppercase tracking-widest font-bold text-gray-500">
    <div class="flex flex-wrap gap-4">
      <a href="?page=docs">Docs</a>
      <a href="?page=privacy">Privacy</a>
      <a href="?page=terms">Terms</a>
      <a href="<?= htmlspecialchars($repoUrl) ?>" target="_blank">GitHub</a>
    </div>
    <p class="text-gray-400 normal-case font-normal">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. Self-hosted PHP.</p>
  </footer>

</div>
</body>
</html>
