<?php
$user = Auth::user();

require_once INCLUDES_PATH . '/SubscriptionHelper.php';
$headerSub = getActiveSubscription(Auth::userId() ?? '');
$headerCap = $headerSub ? (int)($headerSub['monthlyRequestCap'] ?? 0) : 0;
$headerUsed = 0;
if ($headerSub) {
    $headerUsage = getMonthlyUsage(Auth::userId() ?? '');
    $headerUsed = (int)($headerUsage['totalRequests'] ?? 0);
}
$headerRemaining = $headerCap > 0 ? max(0, $headerCap - $headerUsed) : null;

$pageLabels = [
    'dashboard' => 'Dashboard',
    'tasks' => 'Tasks',
    'projects' => 'Projects',
    'clients' => 'Clients',
    'invoices' => 'Invoices',
    'finance' => 'Finance',
    'inventory' => 'Inventory',
    'pomodoro' => 'Pomodoro Timer',
    'ai-assistant' => 'AI Assistant',
    'settings' => 'Settings'
];
$pageLabel = $pageLabels[$page] ?? ucfirst($page);
if ($page === 'advanced-invoices') {
    $pageLabel = 'Invoice';
}
?>

<header class="bg-white border-b border-gray-200 px-4 sm:px-6 py-4">
    <div class="flex items-center justify-between">
        <!-- Mobile Menu Button + Page Title -->
        <div class="flex items-center gap-3">
            <!-- Hamburger Menu (Mobile) -->
            <button onclick="toggleMobileSidebar()" id="mobile-menu-btn" class="lg:hidden p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>

            <!-- Page Title -->
            <div>
                <h1 class="text-lg sm:text-xl font-semibold text-gray-900"><?php echo e($pageLabel); ?></h1>
            </div>
        </div>

        <!-- Right Actions -->
        <div class="flex items-center gap-2 sm:gap-4">
            <!-- Quick Add Button -->
            <button onclick="openQuickAdd()" class="flex items-center gap-1 sm:gap-2 px-3 sm:px-4 py-2 bg-black text-white rounded-lg text-sm font-medium hover:bg-gray-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="hidden sm:inline">Quick Add</span>
            </button>

            <?php if ($headerSub): ?>
                <a href="?page=settings#ai-quota"
                   title="AI requests this month"
                   class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-xs font-medium text-gray-700 hover:border-black transition">
                    <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    <?php if ($headerRemaining !== null): ?>
                        <span><?php echo number_format($headerRemaining); ?> left</span>
                    <?php else: ?>
                        <span>Unlimited</span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>

            <!-- Notifications -->
            <div class="relative">
                <button type="button" data-notif-toggle="notif-panel-desktop" class="relative p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition" aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span data-notif-badge class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold leading-[18px] text-center rounded-full"></span>
                </button>
                <div id="notif-panel-desktop" data-notif-panel class="hidden absolute right-0 mt-2 w-80 max-h-[28rem] overflow-y-auto bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-900">Notifications</h3>
                        <a href="?page=tasks" class="text-xs text-gray-500 hover:text-gray-900">View all tasks</a>
                    </div>
                    <div data-notif-list></div>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="relative" x-data="{ open: false }">
                <button onclick="toggleUserMenu()" class="flex items-center gap-2 p-1 rounded-lg hover:bg-gray-100 transition">
                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-600 font-medium text-sm">
                        <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <!-- Dropdown -->
                <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                    <a href="?page=settings" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                    <a href="api/export.php?format=json" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Export Data</a>
                    <hr class="my-1 border-gray-200">
                    <a href="<?php echo APP_URL; ?>/api/auth.php?action=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Sign Out</a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleUserMenu() {
    const menu = document.getElementById('user-menu');
    menu.classList.toggle('hidden');
}

function openQuickAdd() {
    // Open quick add modal
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Add</h3>
            <div class="space-y-3">
                <a href="?page=tasks&action=new" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="font-medium text-gray-700">New Task</span>
                </a>
                <a href="?page=projects&action=new" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        </svg>
                    </div>
                    <span class="font-medium text-gray-700">New Project</span>
                </a>
                <a href="?page=invoices&action=new" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span class="font-medium text-gray-700">New Invoice</span>
                </a>
                <a href="?page=clients&action=new" class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition">
                    <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                    <span class="font-medium text-gray-700">New Client</span>
                </a>
            </div>
        </div>
    `);
}

// Close menu when clicking outside
document.addEventListener('click', function(e) {
    const menu = document.getElementById('user-menu');
    const button = e.target.closest('button');
    if (!button || !button.onclick?.toString().includes('toggleUserMenu')) {
        menu?.classList.add('hidden');
    }
});

// Mobile sidebar toggle
function toggleMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar?.classList.toggle('mobile-open');
    overlay?.classList.toggle('hidden');
}

// Close mobile sidebar when clicking overlay
document.addEventListener('click', function(e) {
    const overlay = document.getElementById('sidebar-overlay');
    if (e.target === overlay) {
        const sidebar = document.getElementById('sidebar');
        sidebar?.classList.remove('mobile-open');
        overlay?.classList.add('hidden');
    }
});

// Close mobile sidebar on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar?.classList.remove('mobile-open');
        overlay?.classList.add('hidden');
    }
});

// Notification bell — wire data-attribute-driven dropdown once App.js has loaded.
(function initNotifBell() {
    if (window.App?.notificationCenter?.initialize) {
        App.notificationCenter.initialize();
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            window.App?.notificationCenter?.initialize?.();
        });
    }
})();
</script>
