<?php

$flash = pullAuthFlash();
$title = trim((string)($flash['title'] ?? 'Account created.'));
$statusLabel = trim((string)($flash['status_label'] ?? 'Security Status'));
$message = trim((string)($flash['message'] ?? 'Your account is ready.'));
$detail = trim((string)($flash['detail'] ?? 'You can sign in to continue.'));
$ctaHref = trim((string)($flash['cta_href'] ?? '?page=login'));
$ctaLabel = trim((string)($flash['cta_label'] ?? 'Sign In'));
$mailSent = (bool)($flash['mail_sent'] ?? false);
$email = trim((string)($flash['email'] ?? ''));

?>

<div class="flex flex-col items-center text-center">
    <div class="w-16 h-16 rounded-full border-2 border-black flex items-center justify-center">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>

    <h2 class="mt-8 text-3xl font-bold tracking-tight text-gray-900"><?php echo e($title); ?></h2>
    <p class="mt-3 text-sm text-gray-600 max-w-md"><?php echo e($message); ?></p>

    <div class="mt-8 w-full border border-gray-200 rounded-xl p-5 text-left bg-white">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400"><?php echo e($statusLabel); ?></p>
        <?php if ($email !== ''): ?>
            <p class="mt-2 text-sm font-semibold text-gray-900"><?php echo e($email); ?></p>
        <?php endif; ?>
        <p class="mt-2 text-sm text-gray-600"><?php echo e($detail); ?></p>
        <?php if (isEmailVerificationEnabled()): ?>
            <p class="mt-3 text-xs text-gray-500">Verification email: <?php echo $mailSent ? 'Sent' : 'Not sent'; ?></p>
        <?php endif; ?>
    </div>

    <a href="<?php echo e($ctaHref); ?>"
       class="mt-8 w-full py-3.5 bg-black text-white rounded-lg font-bold text-sm uppercase tracking-[0.2em] hover:bg-gray-800 transition-all flex items-center justify-center gap-2">
        <?php echo e($ctaLabel); ?>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
        </svg>
    </a>
</div>

