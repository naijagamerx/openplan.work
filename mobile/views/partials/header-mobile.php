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

// Auto-detect URL-like targets so pages don't need to always set $rightIsLink manually.
$targetIsUrlLike = is_string($rightTarget)
    && preg_match('#^(\\?|/|\\./|\\.\\./|https?://)#', $rightTarget) === 1;
$useLinkTarget = $rightIsLink || $targetIsUrlLike;

// Determine left icon
if ($leftAction === 'back') {
    $leftIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>';
    $leftClick = 'onclick="history.back()"';
} else {
    // Menu button - toggle off-canvas menu
    $leftIcon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>';
    $leftClick = 'onclick="Mobile.ui.toggleMenu()"';
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
<header class="sticky top-0 z-50 mb-2 bg-white/95 dark:bg-zinc-950/95 text-black dark:text-white backdrop-blur-sm border-b border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between">
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
    <?= $rightContent ?>
</header>
