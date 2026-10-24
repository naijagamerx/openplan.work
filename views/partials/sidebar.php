<?php
$currentPage = $page ?? 'dashboard';
$user = Auth::user();

$menuItems = [
    ['page' => 'dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label' => 'Dashboard'],
    ['page' => 'tasks', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Tasks'],
    ['page' => 'projects', 'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', 'label' => 'Projects'],
    ['page' => 'clients', 'icon' => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', 'label' => 'Clients'],
    ['page' => 'notes', 'icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => 'Notes'],
    ['page' => 'knowledge-base', 'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', 'label' => 'Knowledge Base'],
    ['page' => 'calendar', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'label' => 'Calendar'],
    ['page' => 'pomodoro', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Pomodoro'],
    ['page' => 'water-plan', 'icon' => 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z', 'label' => 'Water Plan'],
    ['page' => 'habits', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Habits'],
    ['page' => 'inventory', 'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', 'label' => 'Inventory'],
    ['page' => 'finance', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Finance'],
    ['page' => 'invoices', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Invoices'],
    ['page' => 'advanced-invoices', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'label' => 'Advanced Invoices'],
    ['page' => 'ai-assistant', 'icon' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'label' => 'AI Assistant'],
    ['page' => 'subscription', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'label' => 'Subscription'],
    ['page' => 'support', 'icon' => 'M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z', 'label' => 'Support'],
];

$isAdmin = Auth::isAdmin();
?>

<aside id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col h-full sticky top-0 overflow-x-hidden">
    <!-- Logo -->
    <div class="p-5 border-b border-gray-200 flex-shrink-0">
        <a href="?page=dashboard" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center overflow-hidden">
                <?php echo getSidebarLogoHtml(40); ?>
            </div>
            <span class="text-lg font-bold text-gray-900"><?php echo e(getSiteName()); ?></span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1 overflow-y-auto scrollbar-hide">
        <?php foreach ($menuItems as $item): ?>
            <a href="?page=<?php echo $item['page']; ?>" data-page="<?php echo $item['page']; ?>" style="<?php echo $currentPage === $item['page'] ? 'background-color:#000;color:#fff;' : ''; ?>"
               class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 transition <?php echo $currentPage === $item['page'] ? 'active bg-black text-white font-medium' : ''; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?php echo $item['icon']; ?>"></path>
                </svg>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Bottom Section -->
    <div class="p-4 border-t border-gray-200 flex-shrink-0">
        <a href="?page=settings" data-page="settings" style="<?php echo $currentPage === 'settings' ? 'background-color:#000;color:#fff;' : ''; ?>"
           class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 transition <?php echo $currentPage === 'settings' ? 'active bg-black text-white' : ''; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <span>Settings</span>
        </a>

        <?php
        $adminHubPages = ['admin-hub', 'users', 'release-export', 'admin-plans', 'admin-payment-methods', 'admin-payments', 'admin-subscriptions', 'admin-tickets', 'admin-shared-keys'];
        $adminActive = in_array($currentPage, $adminHubPages, true);
        if ($isAdmin):
        ?>
        <a href="?page=admin-hub" data-page="admin-hub" style="<?php echo $adminActive ? 'background-color:#000;color:#fff;' : ''; ?>"
           class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 transition mt-2 <?php echo $adminActive ? 'active bg-black text-white' : 'hover:bg-gray-100'; ?>">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
            </svg>
            <span>Admin Hub</span>
        </a>
        <?php endif; ?>

        <!-- Switch to Mobile -->
        <a href="?page=<?php echo $currentPage; ?>&device=mobile"
           class="sidebar-item flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-700 transition hover:bg-gray-100 mt-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
            </svg>
            <span>Switch to Mobile</span>
        </a>

        <!-- User Info -->
        <div class="mt-4 p-3 bg-gray-50 rounded-lg">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-gray-300 rounded-full flex items-center justify-center text-gray-600 font-medium text-sm">
                    <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?php echo e($user['name'] ?? 'User'); ?></p>
                    <p class="text-xs text-gray-500 truncate"><?php echo e($user['email'] ?? ''); ?></p>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-400 mt-1"><?php echo $isAdmin ? 'Admin' : 'User'; ?></p>
                </div>
            </div>
        </div>
    </div>
</aside>
