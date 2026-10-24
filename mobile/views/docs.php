<?php
/**
 * Mobile Docs — public documentation.
 */
if (!defined('MOBILE_JS_URL')) { require_once __DIR__ . '/../config.php'; }
$siteName = getPublicAppName();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Docs - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">

  <header class="sticky top-0 z-40 border-b border-black bg-white px-4 py-3 flex items-center justify-between">
    <span class="text-sm font-bold tracking-tight"><?= htmlspecialchars($siteName) ?></span>
    <div class="flex items-center gap-3 text-[10px] uppercase tracking-widest font-bold">
      <a href="?page=homepage">Home</a>
      <a href="?page=login">Login</a>
    </div>
  </header>

  <main class="flex-1 px-4 py-6 space-y-6">

    <section>
      <p class="text-[10px] uppercase tracking-[0.3em] text-gray-400">Public Documentation</p>
      <h1 class="text-3xl font-black mt-1"><?= htmlspecialchars($siteName) ?></h1>
      <p class="text-sm text-gray-600 mt-2">Encrypted PHP workspace for teams and solo builders.</p>
      <a href="?page=register" class="inline-flex items-center gap-2 mt-4 border border-black px-4 py-2 text-[10px] font-bold uppercase tracking-widest">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Create Account
      </a>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">What It Is</h2>
      <ul class="space-y-2 text-xs text-gray-700">
        <li>Self-hosted PHP productivity suite — tasks, projects, notes, habits, invoices, inventory, AI workflows.</li>
        <li>Local-first storage on encrypted JSON files.</li>
        <li>Portable for local + hosted deploys.</li>
      </ul>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">Core Modules</h2>
      <ul class="space-y-1 text-xs text-gray-700">
        <li>Tasks &middot; Projects</li>
        <li>Notes &middot; Knowledge Base</li>
        <li>Habits &middot; Pomodoro</li>
        <li>Invoices &middot; Quotes &middot; Inventory</li>
      </ul>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">Security</h2>
      <ul class="space-y-1 text-xs text-gray-700">
        <li>AES-256-GCM encryption.</li>
        <li>Master password gates data.</li>
        <li>Session timeout + audit log.</li>
      </ul>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">Data Model</h2>
      <p class="text-xs text-gray-700 mb-2">Encrypted JSON records per user workspace.</p>
      <ul class="space-y-1 text-xs text-gray-700">
        <li>Per-user storage isolation</li>
        <li>Backups regenerate clean folders</li>
      </ul>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">Auth Flow</h2>
      <p class="text-xs text-gray-700">Email + password + master encryption key. The master key encrypts data and must be retained.</p>
    </section>

    <section class="bg-black text-white p-4">
      <h2 class="text-base font-black mb-2">Local Run</h2>
      <p class="text-xs text-white/70 mb-3">PHP 8.0+, json, mbstring, openssl.</p>
      <div class="space-y-2 text-[10px] uppercase tracking-widest font-bold">
        <div class="flex items-center justify-between border-b border-white/20 py-1"><span class="font-mono normal-case">php start_server.php</span><span>CLI</span></div>
        <div class="flex items-center justify-between border-b border-white/20 py-1"><span class="font-mono normal-case">start_server.bat</span><span>Win</span></div>
      </div>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">Hosted Run</h2>
      <p class="text-xs text-gray-700">Hosted installs use .env.example as a baseline. Define mail + branding + feature flags via env vars.</p>
    </section>

    <section class="border border-black p-4">
      <h2 class="text-[10px] font-bold uppercase tracking-widest mb-3">Exports</h2>
      <p class="text-xs text-gray-700">Release ZIPs ship clean data skeleton — no user data, sessions, or secrets.</p>
    </section>

  </main>

</div>
</body>
</html>
