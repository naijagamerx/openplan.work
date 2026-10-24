<?php
/**
 * Mobile Reset Password — set new password from a reset token.
 */

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$featureAvailable = isPasswordResetEnabled();
$tokenValid = false;

if ($featureAvailable && $token !== '') {
    $db = new Database(APP_NAME);
    $auth = new Auth($db);
    $tokenValid = $auth->isValidPasswordResetToken($token);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            $error = 'Invalid request token. Refresh and try again.';
        } elseif (!$tokenValid) {
            $error = 'This reset link is invalid or expired';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $result = $auth->resetPasswordWithToken($token, $password);
            if ($result['success']) { $success = true; }
            else { $error = $result['error']; }
        }
    }
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>New Password - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">

  <main class="flex-1 px-6 pt-16 pb-12">

    <div class="mb-10">
      <h1 class="text-3xl font-black mb-2">New Password</h1>
      <p class="text-sm text-gray-500">Set a new sign-in password.</p>
    </div>

    <?php if (!$featureAvailable): ?>
      <div class="border border-gray-300 bg-gray-50 px-4 py-3 text-xs text-gray-600">Password reset is not available.</div>
    <?php elseif ($success): ?>
      <div class="border border-green-300 bg-green-50 px-4 py-3 text-xs text-green-800 mb-6">Password updated. You can sign in again.</div>
      <a href="?page=login" class="block w-full text-center bg-black text-white py-3 font-bold text-xs uppercase tracking-widest">Sign In</a>
    <?php elseif (!$tokenValid): ?>
      <div class="border border-red-300 bg-red-50 px-4 py-3 text-xs text-red-700">This reset link is invalid or has expired.</div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="mb-4 border-l-4 border-red-500 bg-red-50 px-4 py-3 text-xs text-red-700"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">New password</label>
          <input id="rp-pw" type="password" name="password" required minlength="8"
            class="w-full px-3 py-3 border-b-2 border-gray-300 focus:border-black bg-transparent text-sm outline-none"
            placeholder="At least 8 characters">
        </div>

        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Confirm password</label>
          <input id="rp-pw2" type="password" name="confirm_password" required minlength="8"
            class="w-full px-3 py-3 border-b-2 border-gray-300 focus:border-black bg-transparent text-sm outline-none"
            placeholder="Repeat password">
        </div>

        <label class="flex items-center gap-2 text-xs text-gray-600">
          <input type="checkbox" onchange="['rp-pw','rp-pw2'].forEach(id=>{const el=document.getElementById(id); if(el) el.type=this.checked?'text':'password'})" class="accent-black">
          Show passwords
        </label>

        <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Save New Password</button>
      </form>
    <?php endif; ?>

    <div class="mt-10 pt-6 border-t border-gray-200 text-center">
      <a href="?page=login" class="inline-block text-[10px] font-bold uppercase tracking-widest border border-black px-4 py-2">Back to Sign In</a>
    </div>

  </main>

</div>
</body>
</html>
