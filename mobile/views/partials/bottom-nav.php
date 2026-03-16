<?php
/**
 * Universal Mobile Bottom Navigation Component
 *
 * 4-tab bottom navigation for mobile:
 * - Dashboard
 * - Tasks
 * - Habits
 * - Settings
 *
 * Safe area support for notched devices (iPhone X+)
 *
 * @var string $activePage - Currently active page (for highlighting)
 */

// Define navigation items
$navItems = [
    [
        'icon' => 'grid_view',
        'heroicon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
        'label' => 'Dashboard',
        'page' => 'dashboard',
    ],
    [
        'icon' => 'list_alt',
        'heroicon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'label' => 'Tasks',
        'page' => 'tasks',
    ],
    [
        'icon' => 'auto_awesome',
        'heroicon' => 'M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z',
        'label' => 'Habits',
        'page' => 'habits',
    ],
    [
        'icon' => 'settings',
        'heroicon' => 'M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.166-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'label' => 'Settings',
        'page' => 'settings',
    ],
];

// Get current page for highlighting
$currentPage = $activePage ?? $_GET['page'] ?? 'dashboard';
?>
<nav class="fixed bottom-0 left-0 right-0 z-40 bg-white dark:bg-zinc-950 border-t border-gray-100 dark:border-zinc-800 px-8 py-4 flex items-center justify-between">
    <?php $first = true; ?>
    <?php foreach ($navItems as $item): ?>
        <?php
        $isActive = ($currentPage === $item['page']);
        $textClass = $isActive ? 'text-black dark:text-white' : 'text-gray-400 dark:text-zinc-500';
        ?>
        <a href="?page=<?= $item['page'] ?>"
           class="flex flex-col items-center gap-1 <?= $textClass ?> transition-colors touch-target">
            <!-- Heroicon (replacing Material Symbol) -->
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?= $item['heroicon'] ?>"/>
            </svg>
            <span class="text-[10px] font-bold uppercase tracking-tighter">
                <?= htmlspecialchars($item['label']) ?>
            </span>
        </a>
    <?php endforeach; ?>
</nav>
<div id="mobile-pomodoro-overlay" class="fixed bottom-24 right-3 z-40 hidden w-[220px] bg-white dark:bg-zinc-950 border border-black dark:border-white p-2 shadow-lg">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-[9px] uppercase tracking-[0.18em] text-zinc-500">Pomodoro</p>
            <p id="mobile-pomodoro-overlay-track" class="text-[11px] font-semibold truncate">No track selected</p>
        </div>
        <button id="mobile-pomodoro-overlay-hide" type="button" class="h-6 w-6 border border-black dark:border-white flex items-center justify-center text-[10px]" aria-label="Hide overlay">×</button>
    </div>
    <div class="mt-1 flex items-center justify-between">
        <p id="mobile-pomodoro-overlay-status" class="text-[10px] text-zinc-600 dark:text-zinc-300">Ready</p>
        <p id="mobile-pomodoro-overlay-clock" class="text-[13px] font-semibold tabular-nums">25:00</p>
    </div>
    <div class="mt-2 grid grid-cols-5 gap-1">
        <button id="mobile-pomodoro-overlay-prev" type="button" class="h-7 border border-black dark:border-white text-[10px]" aria-label="Previous track">◀</button>
        <button id="mobile-pomodoro-overlay-play" type="button" class="h-7 border border-black dark:border-white text-[10px]" aria-label="Play or pause music">⏯</button>
        <button id="mobile-pomodoro-overlay-next" type="button" class="h-7 border border-black dark:border-white text-[10px]" aria-label="Next track">▶</button>
        <button id="mobile-pomodoro-overlay-toggle" type="button" class="h-7 border border-black dark:border-white text-[9px] font-semibold" aria-label="Start or pause timer">Start</button>
        <button id="mobile-pomodoro-overlay-reset" type="button" class="h-7 border border-black dark:border-white text-[9px] font-semibold" aria-label="Reset timer">Reset</button>
    </div>
    <div class="mt-2 flex items-center gap-1">
        <button id="mobile-pomodoro-overlay-pos-left" type="button" class="h-6 w-6 border border-black dark:border-white text-[9px]" aria-label="Move left">←</button>
        <button id="mobile-pomodoro-overlay-pos-right" type="button" class="h-6 w-6 border border-black dark:border-white text-[9px]" aria-label="Move right">→</button>
        <button id="mobile-pomodoro-overlay-pos-up" type="button" class="h-6 w-6 border border-black dark:border-white text-[9px]" aria-label="Move up">↑</button>
        <button id="mobile-pomodoro-overlay-pos-down" type="button" class="h-6 w-6 border border-black dark:border-white text-[9px]" aria-label="Move down">↓</button>
        <button id="mobile-pomodoro-overlay-pos-reset" type="button" class="h-6 px-2 border border-black dark:border-white text-[9px] font-semibold" aria-label="Reset position">Reset</button>
    </div>
    <div class="mt-2 flex items-center gap-2">
        <input id="mobile-pomodoro-overlay-volume" type="range" min="0" max="1" step="0.05" class="flex-1 accent-black" aria-label="Volume">
        <a href="?page=pomodoro" class="h-6 px-2 border border-black dark:border-white text-[9px] font-semibold flex items-center">Open</a>
    </div>
</div>
<button id="mobile-pomodoro-overlay-show" type="button" class="fixed bottom-24 right-3 z-40 hidden h-9 px-3 bg-black text-white text-[10px] font-semibold tracking-wide" aria-label="Show Pomodoro overlay">Pomodoro</button>
<audio id="pomodoro-audio" class="hidden" preload="auto" crossorigin="anonymous"></audio>
