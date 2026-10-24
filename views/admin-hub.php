<?php
/**
 * Admin Hub — single landing page for all admin-only actions.
 * Replaces a wall of sidebar links with a card grid.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2><p class="text-gray-500">You do not have permission to view this page.</p></div>';
    return;
}

require_once INCLUDES_PATH . '/PaymentsAPI.php';
require_once INCLUDES_PATH . '/TicketsAPI.php';
require_once INCLUDES_PATH . '/SubscriptionsAPI.php';
require_once INCLUDES_PATH . '/PlansAPI.php';
require_once INCLUDES_PATH . '/PaymentMethodsAPI.php';
require_once INCLUDES_PATH . '/AISharedKeysAPI.php';

$globalDb = new Database(APP_NAME);

// Aggregate quick counts.
$paymentsApi = new PaymentsAPI();
$pendingPayments = count($paymentsApi->getAll('pending'));

$ticketsApi = new TicketsAPI();
$awaitingTickets = count(array_filter($ticketsApi->getAll(), fn($t) => ($t['status'] ?? '') === 'awaiting_admin'));

$subsApi = new SubscriptionsAPI();
$activeSubsCount = count(array_filter($subsApi->getAll(), fn($s) => ($s['status'] ?? '') === 'active'));

$plansApi = new PlansAPI($globalDb, 'plans');
$plansCount = count($plansApi->findAll(['activeOnly' => true]));

$methodsApi = new PaymentMethodsAPI($globalDb, 'payment_methods');
$methodsCount = count($methodsApi->findAll(['activeOnly' => true]));

$sharedKeysApi = new AISharedKeysAPI();
$sharedKeys = $sharedKeysApi->getMaskedKeys();
$keysConfiguredCount = 0;
foreach (['groqApiKey', 'openrouterApiKey', 'geminiApiKey'] as $f) {
    if (!empty($sharedKeys[$f]['configured'])) {
        $keysConfiguredCount++;
    }
}

$cards = [
    [
        'page' => 'admin-plans',
        'title' => 'Plans',
        'description' => 'Subscription plans your users can buy.',
        'count' => $plansCount,
        'countLabel' => 'active plan' . ($plansCount === 1 ? '' : 's'),
        'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
    ],
    [
        'page' => 'admin-payment-methods',
        'title' => 'Payment Methods',
        'description' => 'Bank accounts and crypto wallets shown at checkout.',
        'count' => $methodsCount,
        'countLabel' => 'method' . ($methodsCount === 1 ? '' : 's'),
        'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'
    ],
    [
        'page' => 'admin-payments',
        'title' => 'Payment Approvals',
        'description' => 'Review proof uploads and approve or reject payments.',
        'count' => $pendingPayments,
        'countLabel' => 'pending' . ($pendingPayments === 1 ? '' : ''),
        'highlight' => $pendingPayments > 0,
        'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
    ],
    [
        'page' => 'admin-subscriptions',
        'title' => 'Subscribers',
        'description' => 'Active subscribers. Cancel or extend subscriptions.',
        'count' => $activeSubsCount,
        'countLabel' => 'active',
        'icon' => 'M17 20h5v-1a4 4 0 00-5-4m-4 5H2v-1a4 4 0 015-4m6 5v-1a4 4 0 00-4-4H7m6-8a4 4 0 11-8 0 4 4 0 018 0zm6 2a3 3 0 11-6 0 3 3 0 016 0z'
    ],
    [
        'page' => 'admin-tickets',
        'title' => 'Support Tickets',
        'description' => 'Reply to user support tickets.',
        'count' => $awaitingTickets,
        'countLabel' => 'awaiting reply',
        'highlight' => $awaitingTickets > 0,
        'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'
    ],
    [
        'page' => 'admin-shared-keys',
        'title' => 'Shared AI Keys',
        'description' => 'Admin AI keys shared with subscribed users.',
        'count' => $keysConfiguredCount,
        'countLabel' => 'of 3 providers',
        'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'
    ],
    [
        'page' => 'users',
        'title' => 'Users',
        'description' => 'View, promote, ban, or remove users.',
        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
    ],
    [
        'page' => 'release-export',
        'title' => 'Release Export',
        'description' => 'Generate signed release artifacts.',
        'icon' => 'M12 5v14m0 0l-5-5m5 5l5-5M19 20H5'
    ],
    [
        'page' => 'mcp',
        'title' => 'MCP',
        'description' => 'Connect Claude Code and AI agents to your workspace.',
        'icon' => 'M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
    ],
    [
        'page' => 'custom-instruction',
        'title' => 'Custom Instructions',
        'description' => 'Guidelines AI agents follow when creating tasks.',
        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
    ]
];
?>

<div>
    <div class="flex items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Admin Hub</h2>
            <p class="text-gray-500 font-medium tracking-tight">Everything administrative. Cards with attention badges show what needs action.</p>
        </div>
    </div>

    <?php if ($pendingPayments > 0 || $awaitingTickets > 0): ?>
        <div class="mb-6 p-5 border-2 border-black rounded-2xl flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-gray-500">Needs attention</span>
            <?php if ($pendingPayments > 0): ?>
                <a href="?page=admin-payments" class="font-bold text-black hover:underline">
                    <?php echo $pendingPayments; ?> pending payment<?php echo $pendingPayments === 1 ? '' : 's'; ?>
                </a>
            <?php endif; ?>
            <?php if ($awaitingTickets > 0): ?>
                <a href="?page=admin-tickets" class="font-bold text-black hover:underline">
                    <?php echo $awaitingTickets; ?> ticket<?php echo $awaitingTickets === 1 ? '' : 's'; ?> awaiting reply
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($cards as $card):
            $hasCount = array_key_exists('count', $card);
            $highlight = !empty($card['highlight']);
            $borderClass = $highlight ? 'border-black' : 'border-gray-200';
        ?>
            <a href="?page=<?php echo e($card['page']); ?>" class="group bg-white border-2 <?php echo $borderClass; ?> rounded-2xl p-6 hover:border-black hover:shadow-md transition-all flex flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-10 h-10 rounded-xl bg-black flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?php echo $card['icon']; ?>"></path>
                        </svg>
                    </div>
                    <?php if ($hasCount): ?>
                        <div class="text-right">
                            <p class="text-2xl font-black leading-none <?php echo $highlight ? 'text-black' : 'text-gray-900'; ?>"><?php echo (int)$card['count']; ?></p>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mt-1"><?php echo e($card['countLabel'] ?? ''); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 group-hover:text-black"><?php echo e($card['title']); ?></h3>
                    <p class="text-sm text-gray-500 mt-1"><?php echo e($card['description']); ?></p>
                </div>
                <div class="mt-auto flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-gray-500 group-hover:text-black">
                    Open
                    <svg class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
