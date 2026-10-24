<?php
/**
 * Mobile Forgot Password — request a reset link.
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
$featureAvailable = isPasswordResetEnabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $featureAvailable) {
    $email = trim($_POST['email'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Refresh and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address';
    } else {
        $db = new Database(APP_NAME);
        $auth = new Auth($db);
        $result = $auth->issuePasswordReset($email);
        if ($result !== null) {
            $mailer = new Mailer();
            $mailer->sendPasswordResetEmail($result['user'], $result['token']);
        }
        $success = true;
    }
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
<title>Reset Password - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">

  <main class="flex-1 px-6 pt-16 pb-12">

    <div class="mb-10">
      <h1 class="text-3xl font-black mb-2">Reset Password</h1>
      <p class="text-sm text-gray-500">Enter your email to receive a secure reset link.</p>
    </div>

    <?php if (!$featureAvailable): ?>
      <div class="border border-gray-300 bg-gray-50 px-4 py-3 text-xs text-gray-600">Password reset is not available in this installation.</div>
    <?php elseif ($success): ?>
      <div class="border border-green-300 bg-green-50 px-4 py-3 text-xs text-green-800">If the email exists, a reset link has been sent.</div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="mb-4 border-l-4 border-red-500 bg-red-50 px-4 py-3 text-xs text-red-700"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Email</label>
          <input type="email" name="email" required autofocus
            class="w-full px-3 py-3 border-b-2 border-gray-300 focus:border-black bg-transparent text-sm outline-none"
            placeholder="name@company.com"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">Send Reset Link</button>
      </form>
    <?php endif; ?>

    <div class="mt-10 pt-6 border-t border-gray-200 text-center">
      <a href="?page=login" class="inline-block text-[10px] font-bold uppercase tracking-widest border border-black px-4 py-2">Back to Sign In</a>
    </div>

  </main>

</div>
</body>
</html>
