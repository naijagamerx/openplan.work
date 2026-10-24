<?php
/**
 * Transactions — user-facing payment history.
 * View past payments, download proof, print receipt of approved payments.
 */

require_once INCLUDES_PATH . '/PaymentsAPI.php';
$api = new PaymentsAPI();
$payments = $api->getByUser(Auth::userId());
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Transactions</h2>
            <p class="text-gray-500 font-medium tracking-tight">Your payment history.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($payments)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm mb-2">No transactions yet.</p>
                <a href="?page=subscription" class="inline-block mt-4 px-6 py-2 bg-black text-white rounded-lg text-xs font-bold uppercase tracking-widest hover:bg-gray-800 transition">Browse plans</a>
            </div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs font-bold uppercase tracking-widest">
                    <tr>
                        <th class="text-left px-6 py-4">Date</th>
                        <th class="text-left px-6 py-4">Plan</th>
                        <th class="text-left px-6 py-4">Amount</th>
                        <th class="text-left px-6 py-4">Method</th>
                        <th class="text-left px-6 py-4">Status</th>
                        <th class="text-right px-6 py-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p):
                        $symbol = getCurrencySymbol($p['currency'] ?? 'USD');
                        $status = $p['status'] ?? 'pending';
                        $statusColor = match ($status) {
                            'approved' => 'text-green-700 bg-green-50 border-green-200',
                            'rejected' => 'text-red-700 bg-red-50 border-red-200',
                            'under_review' => 'text-blue-700 bg-blue-50 border-blue-200',
                            default => 'text-yellow-700 bg-yellow-50 border-yellow-200'
                        };
                    ?>
                        <tr class="border-t border-gray-100">
                            <td class="px-6 py-4"><?php echo e(date('M j, Y', strtotime((string)($p['submittedAt'] ?? '')))); ?></td>
                            <td class="px-6 py-4 font-bold"><?php echo e($p['planSnapshot']['name'] ?? ''); ?></td>
                            <td class="px-6 py-4"><?php echo e($symbol . number_format((float)($p['amount'] ?? 0), 2)); ?></td>
                            <td class="px-6 py-4 text-xs text-gray-600"><?php echo e($p['methodSnapshot']['label'] ?? ''); ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest border px-2.5 py-1 rounded-full <?php echo $statusColor; ?>">
                                    <?php echo e($status); ?>
                                </span>
                                <?php if (!empty($p['rejectionReason'])): ?>
                                    <p class="text-xs text-red-600 mt-1"><?php echo e($p['rejectionReason']); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="api/payment-proof.php?id=<?php echo e($p['id'] ?? ''); ?>" target="_blank" class="text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-black mr-3">View proof</a>
                                <a href="api/payment-proof.php?id=<?php echo e($p['id'] ?? ''); ?>&download=1" class="text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-black mr-3">Download</a>
                                <?php if ($status === 'approved'): ?>
                                    <button type="button" onclick="printReceipt(<?php echo e(json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="text-xs font-bold uppercase tracking-widest text-gray-700 hover:text-black">Print Receipt</button>
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
function printReceipt(payment) {
    const symbolMap = { USD: '$', EUR: '€', GBP: '£', ZAR: 'R' };
    const symbol = symbolMap[payment.currency] || '$';
    const amount = symbol + parseFloat(payment.amount || 0).toFixed(2);
    const win = window.open('', '_blank', 'width=600,height=800');
    if (!win) return;
    const safe = (s) => String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    win.document.write(`<!DOCTYPE html><html><head><title>Receipt #${safe(payment.id)}</title>
        <style>
            body{font-family:system-ui,sans-serif;padding:48px;max-width:600px;margin:0 auto;color:#111}
            h1{margin:0 0 8px;font-size:28px}
            .meta{color:#666;font-size:12px;margin-bottom:24px}
            table{width:100%;border-collapse:collapse;margin-top:16px}
            th,td{text-align:left;padding:8px 0;border-bottom:1px solid #eee;font-size:14px}
            .total{font-size:24px;font-weight:bold;margin-top:24px}
            .small{font-size:11px;color:#999;margin-top:32px;text-align:center}
            @media print { body { padding: 24px; } }
        </style></head><body>
        <h1>Receipt</h1>
        <p class="meta">Receipt #${safe(payment.id)} &middot; ${safe(payment.submittedAt)}</p>
        <table>
            <tr><th>Plan</th><td>${safe(payment.planSnapshot?.name)}</td></tr>
            <tr><th>Duration</th><td>${safe(payment.planSnapshot?.durationDays)} days</td></tr>
            <tr><th>Method</th><td>${safe(payment.methodSnapshot?.label)} (${safe(payment.methodSnapshot?.type)})</td></tr>
            <tr><th>Status</th><td>${safe(payment.status)}</td></tr>
            <tr><th>Approved</th><td>${safe(payment.reviewedAt || '')}</td></tr>
        </table>
        <p class="total">Total: ${amount}</p>
        <p class="small">Thank you for your payment.</p>
        <script>setTimeout(()=>window.print(),300);<\/script>
    </body></html>`);
    win.document.close();
}
</script>
