<?php
/**
 * Mobile Verification Required — gate page for unverified users.
 */

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Seed the CSRF token before any read/validation. Served via the mobile routing
// path (index.php device-detection / mobile/index.php), which runs before the
// global CSRF generation in index.php. Without this the resend POST fails with
// "Invalid request token". Mirrors mobile/views/login.php & register.php.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$user = Auth::user();
$error = '';
$success = '';
$autoNotice = $_SESSION['verification_notice'] ?? '';
unset($_SESSION['verification_notice']);
$masterPassword = getMasterPassword();
$canResend = isEmailVerificationEnabled() && $masterPassword !== '' && $user !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canResend) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Auth::validateCsrf($csrfToken)) {
        $error = 'Invalid request token. Refresh and try again.';
    } else {
        $db = new Database($masterPassword);
        $auth = new Auth($db);
        $result = $auth->issueEmailVerification((string)$user['id']);
        if (!empty($result['success']) && !empty($result['updated']) && !empty($result['users'])) {
            if ($auth->persistUsers($result['users'])) {
                $userDb = new Database($masterPassword, (string)$user['id']);
                $mailer = new Mailer($userDb->load('config', false));
                $mailer->sendVerificationEmail($result['user'], $result['token']);
                $success = 'A new verification link has been sent.';
            } else {
                $error = 'Could not prepare a new verification email right now.';
            }
        } elseif (!empty($result['already_verified'])) {
            $_SESSION['auth_verification_required'] = false;
            header('Location: ?page=dashboard');
            exit;
        } else {
            $error = $result['error'] ?? 'Unable to resend verification email right now.';
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
<title>Verify Email - <?= htmlspecialchars($siteName) ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] } } } }</script>
<style type="text/tailwindcss">@layer base { body { @apply bg-white text-black font-display antialiased; } }</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white flex flex-col">

  <main class="flex-1 px-6 pt-16 pb-12">

    <div class="mb-10">
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-full border border-black mb-6">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
      </div>
      <h1 class="text-3xl font-black mb-2">Verification Required</h1>
      <p class="text-sm text-gray-500">Your account is signed in, but the dashboard stays locked until your email is verified.</p>
    </div>

    <?php if ($success !== ''): ?>
      <div class="mb-4 border border-green-300 bg-green-50 px-4 py-3 text-xs text-green-800"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($autoNotice !== ''): ?>
      <div class="mb-4 border border-blue-300 bg-blue-50 px-4 py-3 text-xs text-blue-800"><?= htmlspecialchars($autoNotice) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="mb-4 border border-red-300 bg-red-50 px-4 py-3 text-xs text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="border-2 border-black p-4 mb-6">
      <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Signed in as</p>
      <p class="text-sm font-bold mt-1"><?= htmlspecialchars($user['email'] ?? '') ?></p>
      <p class="text-xs text-gray-500 mt-2">Check your inbox for the verification link.</p>
    </div>

    <?php if ($canResend): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <button type="submit" class="w-full bg-black text-white py-3 font-bold text-xs uppercase tracking-widest active:scale-95 transition-transform">
          Resend Verification Link
        </button>
      </form>
    <?php endif; ?>

    <div class="mt-10 pt-6 border-t border-gray-200 text-center">
      <a href="api/auth.php?action=logout" class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest border border-black px-4 py-2">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back to Sign In
      </a>
    </div>

  </main>

</div>
</body>
</html>
