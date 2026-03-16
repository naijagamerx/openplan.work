<?php
/**
 * Universal Mobile Off-Canvas Menu Component
 * Full-page off-canvas menu with bold styling
 */

$isAdmin = Auth::isAdmin();
$currentPage = $_GET['page'] ?? 'dashboard';

// Base menu items (visible to all users)
$menuItems = [
    [
        'icon' => 'home',
        'heroicon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'label' => 'Dashboard',
        'page' => 'dashboard',
    ],
    [
        'icon' => 'apps',
        'heroicon' => 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z',
        'label' => 'App',
        'page' => 'app',
    ],
    [
        'icon' => 'check_circle',
        'heroicon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'label' => 'Tasks',
        'page' => 'tasks',
    ],
    [
        'icon' => 'repeat',
        'heroicon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'label' => 'Habits',
        'page' => 'habits',
    ],
    [
        'icon' => 'description',
        'heroicon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        'label' => 'Notes',
        'page' => 'notes',
    ],
    [
        'icon' => 'library_books',
        'heroicon' => 'M4 19.5A2.25 2.25 0 016.25 17.25h13.5M6.75 4.5h11.25A2.25 2.25 0 0120.25 6.75v10.5A2.25 2.25 0 0118 19.5H6.75A2.25 2.25 0 014.5 17.25V6.75A2.25 2.25 0 016.75 4.5zM8.25 8.25h8.25M8.25 11.25h8.25M8.25 14.25h5.25',
        'label' => 'Knowledge Base',
        'page' => 'knowledge-base',
    ],
    [
        'icon' => 'people',
        'heroicon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        'label' => 'Clients',
        'page' => 'clients',
    ],
    [
        'icon' => 'folder',
        'heroicon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        'label' => 'Projects',
        'page' => 'projects',
    ],
    [
        'icon' => 'calendar_today',
        'heroicon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'label' => 'Calendar',
        'page' => 'calendar',
    ],
    [
        'icon' => 'timer',
        'heroicon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'label' => 'Pomodoro',
        'page' => 'pomodoro',
    ],
    [
        'icon' => 'water_drop',
        'heroicon' => 'M12 2.25C8.25 5.625 6 8.58 6 11.625A6 6 0 0018 11.625C18 8.58 15.75 5.625 12 2.25z',
        'label' => 'Water Plan',
        'page' => 'water-plan',
    ],
    [
        'icon' => 'receipt_long',
        'heroicon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'label' => 'Invoices',
        'page' => 'invoices',
    ],
    [
        'icon' => 'receipt',
        'heroicon' => 'M16.5 3.75v16.5l-4.5-2.25-4.5 2.25V3.75A2.25 2.25 0 019.75 1.5h4.5A2.25 2.25 0 0116.5 3.75z',
        'label' => 'Advanced Invoices',
        'page' => 'advanced-invoices',
    ],
    [
        'icon' => 'payments',
        'heroicon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'label' => 'Finance',
        'page' => 'finance',
    ],
    [
        'icon' => 'inventory_2',
        'heroicon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'label' => 'Inventory',
        'page' => 'inventory',
    ],
    [
        'icon' => 'psychology',
        'heroicon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z',
        'label' => 'AI Assistant',
        'page' => 'ai-assistant',
    ],
    [
        'icon' => 'settings',
        'heroicon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'label' => 'Settings',
        'page' => 'settings',
    ],
    [
        'icon' => 'storage',
        'heroicon' => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
        'label' => 'Data Management',
        'page' => 'data-management',
    ],
];

// Admin-only menu items (added conditionally below)
$adminMenuItems = [
    [
        'icon' => 'people',
        'heroicon' => 'M17 20h5v-1a4 4 0 00-5-4m-4 5H2v-1a4 4 0 015-4m6 5v-1a4 4 0 00-4-4H7m6-8a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z',
        'label' => 'Users',
        'page' => 'users',
    ],
    [
        'icon' => 'file_download',
        'heroicon' => 'M12 5v14m0 0l-5-5m5 5l5-5M19 20H5',
        'label' => 'Release Export',
        'page' => 'release-export',
    ],
    [
        'icon' => 'music_note',
        'heroicon' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3',
        'label' => 'Shared Music',
        'page' => 'shared-music',
    ],
];
?>
<!-- Full-Page Off-Canvas Menu -->
<div id="offcanvas-menu" class="fixed inset-0 z-[100] hidden">
    <!-- Full Backdrop -->
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="Mobile.ui.closeMenu()"></div>

    <!-- Full-Page Menu Panel -->
    <div class="absolute inset-0 bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 flex flex-col transform transition-transform duration-300 translate-x-full" id="offcanvas-panel">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 dark:border-zinc-800 bg-white dark:bg-zinc-950">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-black dark:bg-white text-white dark:text-black flex items-center justify-center font-bold text-lg">
                    <?= strtoupper(substr(Auth::user()['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-900 dark:text-zinc-100"><?= htmlspecialchars(Auth::user()['name'] ?? 'User') ?></h2>
                    <p class="text-xs text-gray-500 dark:text-zinc-400"><?= htmlspecialchars(Auth::user()['email'] ?? '') ?></p>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-zinc-500 mt-0.5">
                        <?= $isAdmin ? 'ADMIN' : 'USER' ?>
                    </p>
                </div>
            </div>
            <button onclick="Mobile.ui.closeMenu()" class="p-3 -mr-2 text-gray-500 dark:text-zinc-400 hover:text-black dark:hover:text-white hover:bg-gray-100 dark:hover:bg-zinc-900 rounded-full transition-colors">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Menu Items -->
        <div class="flex-1 overflow-y-auto px-4 py-4">
            <div class="space-y-1">
                <?php foreach ($menuItems as $item): ?>
                    <?php $isActive = $currentPage === $item['page']; ?>
                    <a href="?page=<?= urlencode($item['page']) ?>"
                       onclick="Mobile.ui.closeMenu()"
                       class="flex items-center gap-4 px-4 py-4 rounded-xl transition-colors <?= $isActive ? 'bg-black dark:bg-white text-white dark:text-black' : 'hover:bg-gray-100 dark:hover:bg-zinc-900 text-gray-900 dark:text-zinc-100' ?>">
                        <svg class="w-7 h-7 <?= $isActive ? 'text-white dark:text-black' : 'text-gray-600 dark:text-zinc-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['heroicon'] ?>"/>
                        </svg>
                        <span class="text-lg font-bold tracking-tight">
                            <?= htmlspecialchars($item['label']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($isAdmin): ?>
            <hr class="my-6 border-gray-200 dark:border-zinc-800">

            <!-- Admin-Only Menu Items -->
            <div class="space-y-1">
                <p class="px-4 text-xs font-bold uppercase tracking-[0.2em] text-gray-400 dark:text-zinc-500 mb-2">Admin</p>
                <?php foreach ($adminMenuItems as $item): ?>
                    <?php $isActive = $currentPage === $item['page']; ?>
                    <a href="?page=<?= urlencode($item['page']) ?>"
                       onclick="Mobile.ui.closeMenu()"
                       class="flex items-center gap-4 px-4 py-4 rounded-xl transition-colors <?= $isActive ? 'bg-black dark:bg-white text-white dark:text-black' : 'hover:bg-gray-100 dark:hover:bg-zinc-900 text-gray-900 dark:text-zinc-100' ?>">
                        <svg class="w-7 h-7 <?= $isActive ? 'text-white dark:text-black' : 'text-gray-600 dark:text-zinc-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['heroicon'] ?>"/>
                        </svg>
                        <span class="text-lg font-bold tracking-tight">
                            <?= htmlspecialchars($item['label']) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <hr class="my-6 border-gray-200 dark:border-zinc-800">

            <!-- Secondary Actions -->
            <div class="space-y-1">
                <button type="button"
                   onclick="if (window.Mobile && Mobile.theme) { Mobile.theme.toggle(); }"
                   class="w-full flex items-center justify-between gap-4 px-4 py-4 rounded-xl hover:bg-gray-100 dark:hover:bg-zinc-900 transition-colors text-gray-700 dark:text-zinc-200">
                    <div class="flex items-center gap-4">
                        <svg class="w-7 h-7 text-gray-600 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
                        </svg>
                        <span class="text-lg font-bold tracking-tight">Theme</span>
                    </div>
                    <span data-theme-label class="text-xs uppercase tracking-[0.2em] text-gray-500 dark:text-zinc-400">Light</span>
                </button>

                <a href="?page=<?= $currentPage ?>&device=desktop"
                   onclick="Mobile.ui.closeMenu()"
                   class="flex items-center gap-4 px-4 py-4 rounded-xl hover:bg-gray-100 dark:hover:bg-zinc-900 transition-colors text-gray-700 dark:text-zinc-200">
                    <svg class="w-7 h-7 text-gray-600 dark:text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-lg font-bold tracking-tight">Switch to Desktop</span>
                </a>

                <a href="?page=logout"
                   class="flex items-center gap-4 px-4 py-4 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors text-red-600 dark:text-red-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="text-lg font-bold tracking-tight">Sign Out</span>
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 border-t border-gray-100 dark:border-zinc-800 bg-gray-50 dark:bg-zinc-900">
            <p class="text-center text-xs text-gray-400 dark:text-zinc-500 font-medium uppercase tracking-widest">
                <?= htmlspecialchars(getSiteName()) ?> Mobile v<?= MOBILE_VERSION ?>
            </p>
        </div>
    </div>
</div>
