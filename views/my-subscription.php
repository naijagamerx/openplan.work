<?php
/**
 * My Subscription — user-facing active subscription details + history.
 */

require_once INCLUDES_PATH . '/SubscriptionsAPI.php';
require_once INCLUDES_PATH . '/SubscriptionHelper.php';

$api = new SubscriptionsAPI();
$userId = Auth::userId();
$active = $api->getActiveByUser($userId);
$history = $api->getByUser($userId);
$usage = getMonthlyUsage($userId);
$usageTotal = (int)($usage['totalRequests'] ?? 0);
$cardReturn = ($_GET['payfast'] ?? '') === 'return';
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">My Subscription</h2>
            <p class="text-gray-500 font-medium tracking-tight">Your active plan and history.</p>
        </div>
    </div>

    <?php if ($cardReturn): ?>
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-4 mb-6 text-sm text-gray-700" role="status">
            <?php if ($active): ?>
                Thank you. Your card payment went through and your plan is active.
            <?php else: ?>
                Thank you. PayFast is confirming your payment; your plan appears here within a minute or two.
                <a href="?page=my-subscription" class="font-bold underline">Refresh</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($active):
        $expiresAt = strtotime((string)($active['expiresAt'] ?? ''));
        $daysLeft = max(0, (int)(($expiresAt - time()) / 86400));
        $cap = (int)($active['monthlyRequestCap'] ?? 0);
        $usagePct = ($cap > 0) ? min(100, ($usageTotal / $cap) * 100) : 0;
        $symbol = getCurrencySymbol($active['planSnapshot']['currency'] ?? 'USD');
    ?>
        <div class="bg-black text-white rounded-2xl p-8 mb-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Active Plan</p>
                    <h3 class="text-3xl font-bold mb-2"><?php echo e($active['planSnapshot']['name'] ?? ''); ?></h3>
                    <p class="text-white/70">
                        <?php echo e($symbol . number_format((float)($active['planSnapshot']['price'] ?? 0), 2)); ?>
                        &middot; <?php echo (int)($active['planSnapshot']['durationDays'] ?? 30); ?> days
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/60 mb-2">Expires</p>
                    <p class="text-2xl font-bold"><?php echo $daysLeft; ?> days</p>
                    <p class="text-white/70 text-sm"><?php echo e(date('M j, Y', $expiresAt)); ?></p>
                </div>
            </div>

            <?php if ($cap > 0): ?>
                <div class="mt-6 pt-6 border-t border-white/20">
                    <div class="flex items-center justify-between text-sm mb-2">
                        <span>This month&apos;s usage</span>
                        <span><?php echo number_format($usageTotal); ?> / <?php echo number_format($cap); ?></span>
                    </div>
                    <div class="h-2 bg-white/20 rounded-full overflow-hidden">
                        <div class="h-full bg-white" style="width: <?php echo (int)$usagePct; ?>%"></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-6 pt-6 border-t border-white/20 text-sm text-white/70">
                    Unlimited AI requests this month &middot; used <?php echo number_format($usageTotal); ?> so far
                </div>
            <?php endif; ?>

            <?php if (!empty($active['providers'])): ?>
                <div class="mt-4 text-sm text-white/70">
                    <span class="font-bold">Providers unlocked:</span> <?php echo e(implode(', ', $active['providers'])); ?>
                </div>
            <?php endif; ?>

            <?php if (($active['provider'] ?? '') === 'payfast'): ?>
                <div class="mt-4 text-sm text-white/70">
                    <?php if (!empty($active['autoRenew'])): ?>
                        <span class="font-bold">Card auto-renew is on.</span> PayFast charges your card each period; cancel any time and keep access until <?php echo e(date('M j, Y', $expiresAt)); ?>.
                    <?php else: ?>
                        <span class="font-bold">Auto-renew is off.</span> Your plan ends on <?php echo e(date('M j, Y', $expiresAt)); ?>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="mt-6 flex flex-wrap gap-2">
                <a href="?page=settings#ai-quota" class="px-4 py-2 bg-white text-black rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-gray-100 transition">Platform AI Settings</a>
                <a href="?page=subscription" class="px-4 py-2 border border-white/40 text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-white/10 transition">Extend / Switch Plan</a>
                <?php if (($active['provider'] ?? '') === 'payfast' && !empty($active['autoRenew'])): ?>
                    <button type="button" id="cancel-autorenew-btn" class="px-4 py-2 border border-white/40 text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-white/10 transition">Cancel Auto-Renew</button>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 mb-6 text-center">
            <p class="text-gray-700 font-bold mb-2">No active subscription</p>
            <p class="text-sm text-gray-500 mb-4">You&apos;re using your own AI API keys. Subscribe to consume the admin&apos;s shared quota instead.</p>
            <a href="?page=subscription" class="inline-block px-6 py-2 bg-black text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-gray-800 transition">Browse Plans</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($history)): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-gray-100">
                <h3 class="font-bold">History</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-widest">
                    <tr>
                        <th class="text-left px-6 py-3">Plan</th>
                        <th class="text-left px-6 py-3">Started</th>
                        <th class="text-left px-6 py-3">Expires</th>
                        <th class="text-left px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h):
                        $status = $h['status'] ?? 'expired';
                        $statusColor = match ($status) {
                            'active' => 'text-green-700 bg-green-50 border-green-200',
                            'cancelled' => 'text-gray-700 bg-gray-50 border-gray-200',
                            default => 'text-red-700 bg-red-50 border-red-200'
                        };
                    ?>
                        <tr class="border-t border-gray-100">
                            <td class="px-6 py-3 font-bold"><?php echo e($h['planSnapshot']['name'] ?? ''); ?></td>
                            <td class="px-6 py-3"><?php echo e(date('M j, Y', strtotime((string)($h['startsAt'] ?? '')))); ?></td>
                            <td class="px-6 py-3"><?php echo e(date('M j, Y', strtotime((string)($h['expiresAt'] ?? '')))); ?></td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest border px-2.5 py-1 rounded-full <?php echo $statusColor; ?>">
                                    <?php echo e($status); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const btn = document.getElementById('cancel-autorenew-btn');
    if (!btn) return;
    btn.addEventListener('click', () => {
        confirmAction('Stop charging your card? Your plan stays active until it expires.', async () => {
            btn.disabled = true;
            try {
                const response = await fetch((typeof APP_URL !== 'undefined' ? APP_URL + '/' : '') + 'api/payfast.php?action=cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify({ csrf_token: CSRF_TOKEN })
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error((result && result.error && result.error.message) || 'Could not cancel auto-renew');
                }
                showToast(result.message || 'Auto-renew is off', 'success');
                setTimeout(() => window.location.reload(), 800);
            } catch (err) {
                showToast(err.message || 'Could not cancel auto-renew', 'error');
                btn.disabled = false;
            }
        });
    });
})();
</script>
