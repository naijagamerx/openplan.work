<?php
// Rendered by api/oauth/authorize.php. Expects these in scope:
//   $contextVars, $csrfToken, $displayName, $siteName, $redirectHost
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Authorize Alexa - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="max-w-md w-full bg-white rounded-2xl shadow-xl border-2 border-black p-6">

  <div class="mb-4">
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Authorize Access</p>
    <h1 class="text-2xl font-black mt-1">Link Alexa to <?= htmlspecialchars($siteName) ?></h1>
  </div>

  <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4 space-y-2 text-sm">
    <p>Signed in as <strong><?= $displayName ?></strong>.</p>
    <p>Amazon (<code class="font-mono text-xs"><?= htmlspecialchars($redirectHost) ?></code>) is asking to link your account.</p>
  </div>

  <div class="border-l-4 border-yellow-400 bg-yellow-50 p-3 mb-4 text-xs text-yellow-900">
    <p class="font-bold mb-1">What Alexa will be able to do:</p>
    <ul class="list-disc list-inside space-y-1">
      <li>Read your tasks, todos, habits, water, finance summary</li>
      <li>Add new tasks, todos, projects, clients, notes, meetings, habits</li>
      <li>Adjust inventory counts</li>
      <li>Start pomodoro timers</li>
    </ul>
    <p class="mt-2">You can unlink any time from Settings &rarr; Voice Assistants.</p>
  </div>

  <div class="border-l-4 border-blue-400 bg-blue-50 p-3 mb-4 text-xs text-blue-900">
    <p class="font-bold mb-1">Security note</p>
    <p>Your master code is wrapped with a server-side platform key so Alexa can decrypt your data on each request. This reduces zero-knowledge guarantees while linked.</p>
  </div>

  <form method="POST" action="/api/oauth/authorize.php" class="space-y-3">
    <?php foreach ($contextVars as $k => $v): ?>
    <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars((string)$v) ?>"/>
    <?php endforeach; ?>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"/>

    <button type="submit" name="consent" value="approve"
            class="w-full bg-black text-white py-3 rounded-lg font-bold text-sm uppercase tracking-widest hover:bg-gray-800 transition">
      Approve &amp; Link
    </button>

    <button type="submit" name="consent" value="deny"
            class="w-full border-2 border-gray-300 text-gray-700 py-3 rounded-lg font-bold text-sm uppercase tracking-widest hover:bg-gray-100 transition">
      Deny
    </button>
  </form>

  <p class="text-[10px] text-gray-500 mt-4 text-center">Not signed in as the right user? <a href="?page=logout" class="underline">Sign out</a> first.</p>
</div>
</body>
</html>
