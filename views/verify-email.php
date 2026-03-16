<?php
$error = '';
$success = false;
$token = trim((string)($_GET['token'] ?? ''));
$featureAvailable = isEmailVerificationEnabled();

if ($featureAvailable && $token !== '') {
    $db = new Database(APP_NAME);
    $auth = new Auth($db);
    $result = $auth->verifyEmailToken($token);
    if ($result['success']) {
        $success = true;
    } else {
        $error = $result['error'];
    }
} elseif ($featureAvailable) {
    $error = 'Verification token is missing';
}

$ctaHref = (Auth::check() && !Auth::shouldRestrictToVerification()) ? '?page=dashboard' : '?page=login';
$ctaLabel = (Auth::check() && !Auth::shouldRestrictToVerification()) ? 'Continue to Dashboard' : 'Continue to Sign In';
?>

<div class="mb-16">
    <div class="mb-8 inline-flex items-center justify-center w-12 h-12 rounded-full border <?php echo $success ? 'border-black' : 'border-gray-300'; ?>">
        <?php if ($success): ?>
            <svg class="w-6 h-6 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        <?php else: ?>
            <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path>
            </svg>
        <?php endif; ?>
    </div>
    <h2 class="text-[#000000] text-5xl lg:text-7xl font-light tracking-tight mb-8 leading-tight">
        <?php echo $success ? 'Email verified.<br><span class="font-black uppercase">Access restored.</span>' : 'Verification<br><span class="font-black uppercase">required.</span>'; ?>
    </h2>
    <div class="space-y-4 max-w-md">
        <p class="text-gray-900 text-lg font-medium">
            <?php echo e($success ? 'Your email has been verified successfully.' : 'We could not verify this email link.'); ?>
        </p>
        <p class="text-gray-500 text-sm leading-relaxed">
            <?php echo e($success ? 'You can continue with the same desktop flow and access the dashboard.' : ($featureAvailable ? $error : 'Email verification is not enabled in this installation.')); ?>
        </p>
    </div>
</div>

<div class="mt-12">
    <a href="<?php echo e($ctaHref); ?>" class="group inline-flex w-full lg:w-auto px-12 bg-black text-white h-16 rounded-none font-bold text-xs uppercase tracking-[0.4em] hover:bg-gray-900 transition-all items-center justify-center gap-4">
        <?php echo e($ctaLabel); ?>
        <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
    </a>
</div>

