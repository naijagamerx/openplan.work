<?php
/**
 * Admin → Payments approval queue.
 * Pending payments at top; tabs for all statuses.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2></div>';
    return;
}

require_once INCLUDES_PATH . '/PaymentsAPI.php';
$api = new PaymentsAPI();

$filter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'under_review', 'approved', 'rejected', 'all'];
if (!in_array($filter, $validStatuses, true)) {
    $filter = 'pending';
}
$payments = $filter === 'all' ? $api->getAll() : $api->getAll($filter);

// Load user emails for display
$usersDb = new Database(APP_NAME);
$users = $usersDb->load('users', true);
$userMap = [];
foreach ($users as $u) {
    $userMap[$u['id'] ?? ''] = $u;
}
?>

<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="?page=dashboard" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Payment Approvals</h2>
            <p class="text-gray-500 font-medium tracking-tight">Review proofs and approve or reject incoming payments.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $val => $label):
            $active = ($filter === $val);
        ?>
            <a href="?page=admin-payments&status=<?php echo $val; ?>" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest transition <?php echo $active ? 'bg-black text-white' : 'bg-white border border-gray-200 text-gray-700 hover:border-black'; ?>">
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($payments)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm">No payments in this view.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($payments as $p):
                    $user = $userMap[$p['userId'] ?? ''] ?? null;
                    $userLabel = $user ? ($user['email'] ?? $user['name'] ?? 'Unknown') : 'Unknown user';
                    $symbol = getCurrencySymbol($p['currency'] ?? 'USD');
                    $proofMime = $p['proof']['mimeType'] ?? '';
                    $isPdf = ($proofMime === 'application/pdf');
                    $proofUrl = rtrim(APP_URL, '/') . '/api/payment-proof.php?id=' . urlencode($p['id'] ?? '');
                    $status = $p['status'] ?? 'pending';
                    $statusColor = match ($status) {
                        'approved' => 'text-green-700 bg-green-50 border-green-200',
                        'rejected' => 'text-red-700 bg-red-50 border-red-200',
                        'under_review' => 'text-blue-700 bg-blue-50 border-blue-200',
                        default => 'text-yellow-700 bg-yellow-50 border-yellow-200'
                    };
                ?>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2">
                            <div class="flex items-start justify-between mb-3 gap-3">
                                <div>
                                    <p class="font-bold text-lg"><?php echo e($p['planSnapshot']['name'] ?? '(plan)'); ?></p>
                                    <p class="text-sm text-gray-600 mt-1"><?php echo e($userLabel); ?></p>
                                </div>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest border px-2.5 py-1 rounded-full <?php echo $statusColor; ?>">
                                    <?php echo e($status); ?>
                                </span>
                            </div>
                            <dl class="grid grid-cols-2 gap-y-1 gap-x-4 text-xs">
                                <dt class="text-gray-500 uppercase tracking-widest font-bold">Amount</dt>
                                <dd><?php echo e($symbol . number_format((float)($p['amount'] ?? 0), 2)); ?></dd>
                                <dt class="text-gray-500 uppercase tracking-widest font-bold">Method</dt>
                                <dd><?php echo e($p['methodSnapshot']['label'] ?? ''); ?> (<?php echo e($p['methodSnapshot']['type'] ?? ''); ?>)</dd>
                                <dt class="text-gray-500 uppercase tracking-widest font-bold">Submitted</dt>
                                <dd><?php echo e(date('M j, Y g:i a', strtotime((string)($p['submittedAt'] ?? '')))); ?></dd>
                                <?php if (!empty($p['reviewedAt'])): ?>
                                    <dt class="text-gray-500 uppercase tracking-widest font-bold">Reviewed</dt>
                                    <dd><?php echo e(date('M j, Y g:i a', strtotime((string)$p['reviewedAt']))); ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($p['rejectionReason'])): ?>
                                    <dt class="text-gray-500 uppercase tracking-widest font-bold">Reason</dt>
                                    <dd class="text-red-700"><?php echo e($p['rejectionReason']); ?></dd>
                                <?php endif; ?>
                            </dl>

                            <?php if ($status === 'pending'): ?>
                                <div class="mt-4 flex gap-2">
                                    <button type="button" class="approve-btn px-4 py-2 bg-black text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-gray-800 transition" data-id="<?php echo e($p['id'] ?? ''); ?>">
                                        Approve
                                    </button>
                                    <button type="button" class="reject-btn px-4 py-2 border-2 border-gray-200 rounded-lg text-xs font-bold uppercase tracking-widest text-gray-700 hover:border-red-400 hover:text-red-600 transition" data-id="<?php echo e($p['id'] ?? ''); ?>">
                                        Reject
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-2">Proof</p>
                            <?php if ($isPdf): ?>
                                <a href="<?php echo e($proofUrl); ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold uppercase tracking-widest hover:border-black">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                    View PDF
                                </a>
                            <?php else: ?>
                                <a href="<?php echo e($proofUrl); ?>" target="_blank">
                                    <img src="<?php echo e($proofUrl); ?>" alt="Proof" class="w-full max-w-xs h-auto rounded-lg border border-gray-200">
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo e($proofUrl); ?>&download=1" class="block mt-2 text-xs text-gray-500 hover:text-black underline">Download original</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.approve-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            confirmAction('Approve this payment? A subscription will be activated for the user.', async () => {
                try {
                    const response = await api.post('api/payments.php?id=' + encodeURIComponent(id) + '&action=approve', { csrf_token: CSRF_TOKEN, note: '' });
                    if (response && response.success) {
                        showToast('Payment approved', 'success');
                        setTimeout(() => location.reload(), 300);
                    } else {
                        showToast(response?.error?.message || response?.error || 'Failed', 'error');
                    }
                } catch (err) {
                    showToast(err.response?.error?.message || 'Failed to approve', 'error');
                }
            });
        });
    });

    document.querySelectorAll('.reject-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const reason = prompt('Reason for rejection (visible to user):');
            if (!reason || reason.trim() === '') return;
            try {
                const response = await api.post('api/payments.php?id=' + encodeURIComponent(id) + '&action=reject', { csrf_token: CSRF_TOKEN, reason: reason.trim() });
                if (response && response.success) {
                    showToast('Payment rejected', 'success');
                    setTimeout(() => location.reload(), 300);
                } else {
                    showToast(response?.error?.message || response?.error || 'Failed', 'error');
                }
            } catch (err) {
                showToast(err.response?.error?.message || 'Failed to reject', 'error');
            }
        });
    });
})();
</script>
