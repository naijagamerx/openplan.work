<?php
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
            '[verify-email] desktop CSRF fail | UA=%s | token_len=%d',
            $_SERVER['HTTP_USER_AGENT'] ?? '?',
            strlen($postedToken)
        ));
        $error = 'Invalid request token. Please refresh and try again.';
        $pendingToken = $postedToken;
    } else {
        $db = new Database(APP_NAME);
        $auth = new Auth($db);
        $result = $auth->verifyEmailToken($postedToken);
        if ($result['success']) {
            $success = true;
        } else {
            error_log(sprintf(
                '[verify-email] desktop verify fail | err=%s | UA=%s',
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
        if (!empty($peek['success'])) {
            $pendingToken = $token;
        } elseif (!empty($peek['already_verified'])) {
            $success = true;
        } else {
            $error = $peek['error'] ?? 'This verification link is invalid or has already been used';
        }
    }
}

$showConfirm = ($pendingToken !== '' && !$success && $error === '');
$ctaHref = (Auth::check() && !Auth::shouldRestrictToVerification()) ? '?page=dashboard' : '?page=login';
$ctaLabel = (Auth::check() && !Auth::shouldRestrictToVerification()) ? 'Continue to Dashboard' : 'Continue to Sign In';
?>

<div class="mb-16">
    <div class="mb-8 inline-flex items-center justify-center w-12 h-12 rounded-full border <?php echo ($success || $showConfirm) ? 'border-black' : 'border-gray-300'; ?>">
        <?php if ($success): ?>
            <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        <?php elseif ($showConfirm): ?>
            <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
            </svg>
        <?php else: ?>
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path>
            </svg>
        <?php endif; ?>
    </div>
    <h2 class="text-[#000000] text-5xl lg:text-7xl font-light tracking-tight mb-8 leading-tight">
        <?php if ($success): ?>
            Email verified.<br><span class="font-black uppercase">Access restored.</span>
        <?php elseif ($showConfirm): ?>
            Confirm your<br><span class="font-black uppercase">email.</span>
        <?php else: ?>
            Verification<br><span class="font-black uppercase">required.</span>
        <?php endif; ?>
    </h2>
    <div class="space-y-4 max-w-md">
        <p class="text-gray-900 text-lg font-medium">
            <?php
            if ($success) {
                echo e('Your email has been verified successfully.');
            } elseif ($showConfirm) {
                echo e('One more click to verify your email address.');
            } else {
                echo e('We could not verify this email link.');
            }
            ?>
        </p>
        <p class="text-gray-500 text-sm leading-relaxed">
            <?php
            if ($success) {
                echo e('You can continue with the same desktop flow and access the dashboard.');
            } elseif ($showConfirm) {
                echo e('Click the button below to confirm and unlock your account.');
            } else {
                echo e($featureAvailable ? $error : 'Email verification is not enabled in this installation.');
            }
            ?>
        </p>
    </div>
</div>

<?php if ($showConfirm): ?>
    <form method="POST" class="mt-12">
        <input type="hidden" name="csrf_token" value="<?php echo e(Auth::csrfToken()); ?>">
        <input type="hidden" name="token" value="<?php echo e($pendingToken); ?>">
        <button type="submit" class="group inline-flex w-full lg:w-auto px-12 bg-black text-white h-16 rounded-none font-bold text-xs uppercase tracking-[0.4em] hover:bg-gray-900 transition-all items-center justify-center gap-4">
            Verify Email Now
            <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
        </button>
    </form>
<?php else: ?>
    <div class="mt-12">
        <a href="<?php echo e($ctaHref); ?>" class="group inline-flex w-full lg:w-auto px-12 bg-black text-white h-16 rounded-none font-bold text-xs uppercase tracking-[0.4em] hover:bg-gray-900 transition-all items-center justify-center gap-4">
            <?php echo e($ctaLabel); ?>
            <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
        </a>
    </div>
<?php endif; ?>
