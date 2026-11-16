<?php
/**
 * "Start free trial" offer, shown only when the signed-in user can start one now.
 * Shared by desktop and mobile pages (plain Tailwind classes, dark-mode aware).
 *
 * Optional inputs (set before including):
 *   $trialOfferAfter    URL to open once the trial has started (default: AI assistant)
 *   $trialOfferCompact  true for a one-line version (inside banners)
 *   $trialOfferClass    extra classes for the outer box (e.g. "mb-6")
 * The inputs are cleared after use, so each include starts fresh.
 * Sets $trialOfferShown (bool) so the caller can choose what else to show.
 */
require_once INCLUDES_PATH . '/SubscriptionHelper.php';

$trialOfferShown = false;
$trialOfferUserId = (string)(Auth::userId() ?? '');
if ($trialOfferUserId !== '') {
    $trialOfferCheck = trialEligibility($trialOfferUserId);
    $trialOfferShown = !empty($trialOfferCheck['ok']);
}
if (!$trialOfferShown) {
    unset($trialOfferAfter, $trialOfferCompact, $trialOfferClass);
    return;
}
$trialOfferSettings = trialSettings();
$trialOfferAfter = isset($trialOfferAfter) && is_string($trialOfferAfter) && $trialOfferAfter !== '' ? $trialOfferAfter : '?page=ai-assistant';
$trialOfferCompact = !empty($trialOfferCompact);
$trialOfferExtraClass = isset($trialOfferClass) && is_string($trialOfferClass) ? $trialOfferClass : '';
unset($trialOfferClass);
$trialOfferId = 'trial-offer-' . bin2hex(random_bytes(3));
// Mobile pages have a class-based dark theme. The desktop layout has none (and
// Tailwind's default "media" dark mode would follow the OS), so desktop gets no
// dark: classes at all.
$trialOfferDark = defined('MOBILE_VIEW_PATH')
    ? static fn(string $classes): string => ' ' . $classes
    : static fn(string $classes): string => '';
?>
<div id="<?php echo e($trialOfferId); ?>" class="trial-offer rounded-xl border border-black/10 bg-white<?php echo $trialOfferDark('dark:border-white/15 dark:bg-white/5'); ?> <?php echo $trialOfferCompact ? 'p-4' : 'p-5'; ?> <?php echo e($trialOfferExtraClass); ?>">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
        <div class="flex-1 min-w-0">
            <p class="font-bold text-gray-900<?php echo $trialOfferDark('dark:text-white'); ?>">Try Pro free for <?php echo (int)$trialOfferSettings['days']; ?> days</p>
            <p class="text-sm text-gray-600 mt-1<?php echo $trialOfferDark('dark:text-gray-300'); ?>"><?php echo (int)$trialOfferSettings['requests']; ?> AI requests on our keys with fast models. No card, and nothing to cancel: it simply ends.</p>
        </div>
        <button type="button"
                class="trial-offer-start shrink-0 inline-flex items-center justify-center gap-2 px-5 py-3 min-h-[44px] bg-black text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:opacity-90 transition disabled:opacity-60<?php echo $trialOfferDark('dark:bg-white dark:text-black'); ?>"
                data-endpoint="<?php echo e(rtrim((string)APP_URL, '/') . '/api/subscriptions.php?action=start_trial'); ?>"
                data-csrf="<?php echo e(Auth::csrfToken()); ?>"
                data-after="<?php echo e($trialOfferAfter); ?>">
            Start free trial
        </button>
    </div>
    <p class="trial-offer-error hidden mt-3 text-sm text-red-700<?php echo $trialOfferDark('dark:text-red-400'); ?>" role="alert"></p>
</div>
<?php require __DIR__ . '/trial-offer-script.php'; ?>
<?php unset($trialOfferAfter, $trialOfferCompact); ?>
