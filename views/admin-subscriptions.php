<?php
/**
 * Admin → Subscriptions oversight.
 * List all subscriptions; cancel or extend.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2></div>';
    return;
}

require_once INCLUDES_PATH . '/SubscriptionsAPI.php';
$api = new SubscriptionsAPI();
$subs = $api->getAll();

$usersDb = new Database(APP_NAME);
$users = $usersDb->load('users', true);
$userMap = [];
foreach ($users as $u) {
    $userMap[$u['id'] ?? ''] = $u;
}
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=dashboard" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Subscriptions</h2>
            <p class="text-gray-500 font-medium tracking-tight">Active subscribers and history.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($subs)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm">No subscriptions yet.</p>
            </div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-widest">
                    <tr>
                        <th class="text-left px-6 py-4">User</th>
                        <th class="text-left px-6 py-4">Plan</th>
                        <th class="text-left px-6 py-4">Started</th>
                        <th class="text-left px-6 py-4">Expires</th>
                        <th class="text-left px-6 py-4">Status</th>
                        <th class="text-right px-6 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subs as $s):
                        $user = $userMap[$s['userId'] ?? ''] ?? null;
                        $userLabel = $user ? ($user['email'] ?? $user['name'] ?? 'Unknown') : 'Unknown user';
                        $status = $s['status'] ?? 'expired';
                        $statusColor = match ($status) {
                            'active' => 'text-green-700 bg-green-50 border-green-200',
                            'cancelled' => 'text-gray-700 bg-gray-50 border-gray-200',
                            default => 'text-red-700 bg-red-50 border-red-200'
                        };
                    ?>
                        <tr class="border-t border-gray-100">
                            <td class="px-6 py-4 font-bold"><?php echo e($userLabel); ?></td>
                            <td class="px-6 py-4"><?php echo e($s['planSnapshot']['name'] ?? ''); ?></td>
                            <td class="px-6 py-4"><?php echo e(date('M j, Y', strtotime((string)($s['startsAt'] ?? '')))); ?></td>
                            <td class="px-6 py-4"><?php echo e(date('M j, Y', strtotime((string)($s['expiresAt'] ?? '')))); ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest border px-2.5 py-1 rounded-full <?php echo $statusColor; ?>">
                                    <?php echo e($status); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <?php if ($status === 'active'): ?>
                                    <button type="button" class="extend-btn text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-black mr-3" data-id="<?php echo e($s['id'] ?? ''); ?>">Extend</button>
                                    <button type="button" class="cancel-btn text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-red-600" data-id="<?php echo e($s['id'] ?? ''); ?>">Cancel</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.extend-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const daysStr = prompt('Extend by how many days?', '30');
            if (!daysStr) return;
            const days = parseInt(daysStr, 10);
            if (!Number.isFinite(days) || days <= 0) {
                showToast('Days must be a positive integer', 'error');
                return;
            }
            try {
                const response = await api.post('api/subscriptions.php?id=' + encodeURIComponent(id) + '&action=extend', { csrf_token: CSRF_TOKEN, days });
                if (response && response.success) {
                    showToast('Extended by ' + days + ' days', 'success');
                    setTimeout(() => location.reload(), 300);
                } else {
                    showToast(response?.error?.message || 'Failed', 'error');
                }
            } catch (err) {
                showToast(err.response?.error?.message || 'Failed to extend', 'error');
            }
        });
    });

    document.querySelectorAll('.cancel-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            confirmAction('Cancel this subscription? User loses shared-key access immediately.', async () => {
                try {
                    const response = await api.post('api/subscriptions.php?id=' + encodeURIComponent(id) + '&action=cancel', { csrf_token: CSRF_TOKEN });
                    if (response && response.success) {
                        showToast('Subscription cancelled', 'success');
                        setTimeout(() => location.reload(), 300);
                    } else {
                        showToast(response?.error?.message || 'Failed', 'error');
                    }
                } catch (err) {
                    showToast(err.response?.error?.message || 'Failed to cancel', 'error');
                }
            });
        });
    });
})();
</script>
