<?php
/**
 * Mobile Privacy — public legal page.
 */
if (!defined('MOBILE_JS_URL')) { require_once __DIR__ . '/../config.php'; }
$siteName = getPublicAppName();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Privacy - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">
  <main class="flex-1 px-6 pt-12 pb-12">
    <div class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-black mb-6">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z"/></svg>
    </div>
    <h1 class="text-3xl font-black mb-1">Privacy</h1>
    <p class="text-3xl font-black italic uppercase mb-8 text-gray-500">policy.</p>
    <div class="space-y-4 text-sm leading-6 text-gray-700">
      <p><?= htmlspecialchars(getPublicAppName()) ?> helps users manage tasks, projects, notes, and productivity data without giving the operator ownership of that information.</p>
      <p>We do not claim rights over content you create in the app. Your records, notes, schedules, and uploaded information remain your responsibility and your data.</p>
      <p>Where encryption is used, your master key or password is critical. The platform keeps your information private from unauthorized access — but we may not be able to recover encrypted data if you lose the key.</p>
      <p>Mail, verification, and password-reset services may rely on third-party SMTP providers. Those services are used only to support account access and operational messaging.</p>
      <p>If you choose a hosted deployment, you are responsible for operational settings, retention decisions, and security practices.</p>
    </div>
    <div class="mt-10">
      <a href="?page=login" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-[0.3em] active:scale-95 transition-transform">Back to Sign In</a>
    </div>
  </main>
</div>
</body>
</html>
