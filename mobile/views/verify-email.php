<?php
/**
 * Mobile Verify Email — landing for the email verification link.
 */

if (!defined('MOBILE_JS_URL')) {
    require_once __DIR__ . '/../config.php';
}
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Seed the CSRF token before any read/validation. The mobile routing path in
// index.php (device-detection include + exit) runs BEFORE the global CSRF
// generation at index.php line ~217, so mobile auth views must seed their own.
// Without this the verify-email POST always fails with "Invalid request token".
// Mirrors mobile/views/login.php and mobile/views/register.php.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$success = false;
$pendingToken = '';
$featureAvailable = isEmailVerificationEnabled();
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if (!$featureAvailable) {
    $error = 'Email verification is not enabled in this installation.';
} elseif ($isPost) {
    $postedToken = trim((string)($_POST['token'] ?? ''));
    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (!Auth::validateCsrf($csrfToken)) {
        error_log(sprintf(
            '[verify-email] mobile CSRF fail | UA=%s | token_len=%d',
            $_SERVER['HTTP_USER_AGENT'] ?? '?',
            strlen($postedToken)
        ));
        $error = 'Invalid request token. Refresh and try again.';
        $pendingToken = $postedToken;
    } else {
        $db = new Database(APP_NAME);
        $auth = new Auth($db);
        $result = $auth->verifyEmailToken($postedToken);
        if ($result['success']) { $success = true; }
        else {
            error_log(sprintf(
                '[verify-email] mobile verify fail | err=%s | UA=%s',
                $result['error'] ?? '?',
                $_SERVER['HTTP_USER_AGENT'] ?? '?'
            ));
            $error = $result['error'];
        }
    }
} else {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        $error = 'Verification token is missing';
    } else {
        $db = new Database(APP_NAME);
        $auth = new Auth($db);
        $peek = $auth->peekVerificationToken($token);
        if (!empty($peek['success'])) { $pendingToken = $token; }
        elseif (!empty($peek['already_verified'])) { $success = true; }
        else { $error = $peek['error'] ?? 'This verification link is invalid or has already been used'; }
    }
}

$showConfirm = ($pendingToken !== '' && !$success && $error === '');
$ctaHref = (Auth::check() && !Auth::shouldRestrictToVerification()) ? '?page=dashboard' : '?page=login';
$ctaLabel = (Auth::check() && !Auth::shouldRestrictToVerification()) ? 'Continue to Dashboard' : 'Continue to Sign In';
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
      <div class="inline-flex items-center justify-center w-12 h-12 rounded-full border <?= ($success || $showConfirm) ? 'border-black' : 'border-gray-300' ?> mb-6">
        <?php if ($success): ?>
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <?php elseif ($showConfirm): ?>
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
        <?php else: ?>
          <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path></svg>
        <?php endif; ?>
      </div>

      <h1 class="text-3xl font-black leading-tight mb-3">
        <?php if ($success): echo 'Email<br>verified.';
        elseif ($showConfirm): echo 'Confirm your<br>email.';
        else: echo 'Verification<br>required.';
        endif; ?>
      </h1>

      <p class="text-sm text-gray-700 font-medium mb-2">
        <?php
        if ($success) echo htmlspecialchars('Your email has been verified.');
        elseif ($showConfirm) echo htmlspecialchars('One more tap to verify and unlock your account.');
        else echo htmlspecialchars('We could not verify this email link.');
        ?>
      </p>
      <?php if ($error && !$showConfirm): ?>
        <p class="text-xs text-red-600"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>
    </div>

    <?php if ($showConfirm): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($pendingToken) ?>">
        <button type="submit" class="w-full bg-black text-white py-4 font-bold text-xs uppercase tracking-[0.3em] active:scale-95 transition-transform">
          Verify Email Now
        </button>
      </form>
    <?php else: ?>
      <a href="<?= htmlspecialchars($ctaHref) ?>" class="block w-full text-center bg-black text-white py-4 font-bold text-xs uppercase tracking-[0.3em] active:scale-95 transition-transform">
        <?= htmlspecialchars($ctaLabel) ?>
      </a>
    <?php endif; ?>

  </main>

</div>
</body>
</html>
