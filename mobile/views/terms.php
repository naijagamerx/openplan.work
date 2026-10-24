<?php
/**
 * Mobile Terms — public legal page.
 */
if (!defined('MOBILE_JS_URL')) { require_once __DIR__ . '/../config.php'; }
$siteName = getPublicAppName();
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Terms - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">
  <main class="flex-1 px-6 pt-12 pb-12">
    <div class="inline-flex items-center justify-center w-10 h-10 rounded-full border border-black mb-6">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
    </div>
    <h1 class="text-3xl font-black mb-1">Terms</h1>
    <p class="text-3xl font-black italic uppercase mb-8 text-gray-500">of use.</p>
    <div class="space-y-4 text-sm leading-6 text-gray-700">
      <p>By using <?= htmlspecialchars(getPublicAppName()) ?>, you accept responsibility for the information you create, store, and manage in the app.</p>
      <p>You are responsible for protecting your login credentials and master encryption key. That key is essential to accessing encrypted data.</p>
      <p>If you forget the encryption key, recovery may not be possible. The operator cannot guarantee access restoration and is not responsible for resulting data loss.</p>
      <p>Hosted features such as email verification, password reset, and SMTP delivery support account workflows — they do not replace your responsibility to keep access details secure and backed up.</p>
      <p>The service may be updated, reconfigured, or self-hosted in different environments. Operators and deployers remain responsible for environment configuration and security practices.</p>
    </div>
    <div class="mt-10">
      <a href="?page=login" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-[0.3em] active:scale-95 transition-transform">Back to Sign In</a>
    </div>
  </main>
</div>
</body>
</html>
