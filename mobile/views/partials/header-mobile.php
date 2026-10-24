<?php
/**
 * Universal Mobile Header Component
 *
 * Consistent header across all mobile pages - matches Stitch design pattern.
 * Uses Heroicons inline SVG (no Material Symbols).
 *
 * @var string $title - Page title
 * @var string $leftAction - 'menu' or 'back' (default: 'menu')
 * @var string $rightAction - 'add', 'search', 'filter', 'menu', or 'none' (default: 'none')
 * @var string $rightTarget - URL or function for right button click
 * @var bool $rightIsLink - If true, rightTarget is treated as a navigation link
 */

$leftAction = $leftAction ?? 'menu';
$rightAction = $rightAction ?? 'none';
$rightTarget = $rightTarget ?? '';
$rightIsLink = $rightIsLink ?? false;

require_once INCLUDES_PATH . '/SubscriptionHelper.php';
$mobHeaderSub = Auth::check() ? getActiveSubscription(Auth::userId() ?? '') : null;
$mobHeaderRemaining = null;
if ($mobHeaderSub) {
    $cap = (int)($mobHeaderSub['monthlyRequestCap'] ?? 0);
    if ($cap > 0) {
        $usage = getMonthlyUsage(Auth::userId() ?? '');
        $mobHeaderRemaining = max(0, $cap - (int)($usage['totalRequests'] ?? 0));
    }
}

// Auto-detect URL-like targets so pages don't need to always set $rightIsLink manually.
$targetIsUrlLike = is_string($rightTarget)
    && preg_match('#^(\\?|/|\\./|\\.\\./|https?://)#', $rightTarget) === 1;
$useLinkTarget = $rightIsLink || $targetIsUrlLike;

// Determine left icon
if ($leftAction === 'back') {
    $leftIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>';
    $leftClick = 'onclick="history.back()"';
} else {
    // Home button - navigate to dashboard
    $leftIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>';
    $leftClick = 'onclick="window.location.href=\'?page=dashboard\'"';
}

// Determine right icon
$rightContent = '';
if ($rightAction === 'add') {
    if ($useLinkTarget && $rightTarget) {
        // Use as navigation link
        $rightContent = '<a href="' . htmlspecialchars($rightTarget) . '" class="bg-black dark:bg-white text-white dark:text-black p-2 rounded-full flex items-center justify-center touch-target">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
        </a>';
    } else {
        // Use as onclick function
        $rightContent = '<button ' . ($rightTarget ? 'onclick="' . htmlspecialchars($rightTarget) . '"' : '') . ' class="bg-black dark:bg-white text-white dark:text-black p-2 rounded-full flex items-center justify-center touch-target">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
        </button>';
    }
} elseif ($rightAction === 'menu') {
    // Three-dot menu icon (for more options)
    $rightContent = '<button ' . ($rightTarget ? 'onclick="' . htmlspecialchars($rightTarget) . '"' : '') . ' class="p-2 -mr-2 touch-target">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
        </svg>
    </button>';
} elseif ($rightAction === 'search') {
    $rightContent = '<button ' . ($rightTarget ? 'onclick="' . htmlspecialchars($rightTarget) . '"' : '') . ' class="p-2 -mr-2 touch-target">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </button>';
} elseif ($rightAction === 'filter') {
    $rightContent = '<button ' . ($rightTarget ? 'onclick="' . htmlspecialchars($rightTarget) . '"' : '') . ' class="p-2 -mr-2 touch-target">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4-4v3.586a1 1 0 00.293.707l6.414 6.414a1 1 0 00.293.707V21a1 1 0 001.707 0l6.414-6.414a1 1 0 00.293-.707V8.586a1 1 0 00-.293-.707L3.293 2.293A1 1 0 013 4z"/>
        </svg>
    </button>';
} else {
    // Empty spacer to maintain center alignment
    $rightContent = '<div class="w-10"></div>';
}
?>
<!-- Universal Mobile Header -->
<header class="sticky top-0 z-50 mb-2 bg-white/95 dark:bg-zinc-950/95 text-black dark:text-white backdrop-blur-sm border-b border-gray-200 dark:border-gray-800 px-4 py-3 pb-3 flex items-center justify-between" style="padding-top: max(0.75rem, env(safe-area-inset-top)); min-height: calc(3.5rem + env(safe-area-inset-top))">
    <button <?= $leftClick ?> class="p-2 -ml-2 touch-target">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <?= $leftIcon ?>
        </svg>
    </button>
    <?php if (trim((string)($title ?? '')) !== ''): ?>
        <h1 class="font-bold tracking-tight text-lg"><?= htmlspecialchars($title) ?></h1>
    <?php else: ?>
        <div class="flex-1"></div>
    <?php endif; ?>
    <div class="flex items-center gap-1">
        <?php if (Auth::check()): ?>
        <button type="button" data-notif-toggle="notif-panel-mobile" class="relative p-2 touch-target" aria-label="Notifications">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span data-notif-badge class="hidden absolute top-0.5 right-0.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold leading-[18px] text-center rounded-full"></span>
        </button>
        <?php endif; ?>
        <?= $rightContent ?>
    </div>
</header>
<?php if (Auth::check()): ?>
<div id="notif-panel-mobile" data-notif-panel class="hidden fixed top-[3.5rem] right-2 left-2 max-w-[420px] mx-auto z-50 max-h-[70vh] overflow-y-auto bg-white dark:bg-zinc-900 rounded-lg shadow-xl border border-gray-200 dark:border-gray-800" style="top: calc(3.5rem + env(safe-area-inset-top))">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</h3>
        <a href="?page=tasks" class="text-xs text-gray-500 hover:text-gray-900 dark:text-gray-400">View all tasks</a>
    </div>
    <div data-notif-list></div>
</div>
<script>
(function initMobileNotifBell() {
    if (window.App?.notificationCenter?.initialize) {
        App.notificationCenter.initialize();
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            window.App?.notificationCenter?.initialize?.();
        });
    }
})();
</script>
<?php endif; ?>
<?php if ($mobHeaderSub): ?>
<a href="?page=settings#ai-quota" class="block px-4 py-1.5 bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-100 dark:border-yellow-900/40 text-xs text-yellow-900 dark:text-yellow-200 flex items-center gap-2">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
    </svg>
    <?php if ($mobHeaderRemaining !== null): ?>
        <span class="font-medium"><?= number_format($mobHeaderRemaining) ?> AI requests left</span>
    <?php else: ?>
        <span class="font-medium">Unlimited AI requests</span>
    <?php endif; ?>
</a>
<?php endif; ?>
