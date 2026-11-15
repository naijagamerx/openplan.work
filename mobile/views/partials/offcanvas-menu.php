<?php
/**
 * Universal Mobile Off-Canvas Menu Component
 * Full-page off-canvas menu with bold styling
 */

$isAdmin = Auth::isAdmin();
$currentPage = $_GET['page'] ?? 'dashboard';

$mobNavItems = require __DIR__ . '/nav-items.php';
$menuItems = $mobNavItems['menu'];
$adminMenuItems = $mobNavItems['admin'];  // shown only when $isAdmin
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
