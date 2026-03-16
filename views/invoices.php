<?php
// Invoices View
$db = new Database(getMasterPassword(), Auth::userId());
$invoices = $db->load('invoices');
$clients = $db->load('clients');
$config = $db->load('config');

$statusLabels = [
    'draft' => 'Draft',
    'sent' => 'Sent',
    'paid' => 'Paid',
    'overdue' => 'Overdue',
    'cancelled' => 'Cancelled'
];
?>

<div class="space-y-6">
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <?php
        $totalPaid = array_sum(array_map(fn($i) => $i['status'] === 'paid' ? $i['total'] : 0, $invoices));
        $totalPending = array_sum(array_map(fn($i) => $i['status'] === 'sent' ? $i['total'] : 0, $invoices));
        $totalDraft = count(array_filter($invoices, fn($i) => $i['status'] === 'draft'));
        $totalOverdue = count(array_filter($invoices, fn($i) => $i['status'] === 'overdue'));
        $currencySymbol = getCurrencySymbol($config['currency'] ?? 'USD');
        ?>
        <div class="bg-white rounded-2xl border-2 border-green-50 p-6 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Total Revenue</p>
            <p class="text-3xl font-black text-green-600"><?php echo formatCurrency($totalPaid, $config['currency'] ?? 'USD'); ?></p>
        </div>
        <div class="bg-white rounded-2xl border-2 border-blue-50 p-6 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Pending</p>
            <p class="text-3xl font-black text-blue-600"><?php echo formatCurrency($totalPending, $config['currency'] ?? 'USD'); ?></p>
        </div>
        <div class="bg-white rounded-2xl border-2 border-gray-50 p-6 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Drafts</p>
            <p class="text-3xl font-black text-gray-900"><?php echo $totalDraft; ?></p>
        </div>
        <div class="bg-white rounded-2xl border-2 border-red-50 p-6 shadow-sm">
            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2">Overdue</p>
            <p class="text-3xl font-black text-red-600"><?php echo $totalOverdue; ?></p>
        </div>
    </div>
    
    <!-- Header -->
    <div class="flex items-center justify-between">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400"><?php echo count($invoices); ?> professional invoices</p>
        <a href="?page=invoice-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Invoice
        </a>
    </div>
    
    <!-- Invoices List -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($invoices)): ?>
            <div class="p-20 text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">No Invoices</h3>
                <p class="text-gray-500 mt-2">Generate your first invoice to get paid.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50">
                        <tr class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <th class="px-6 py-4">Invoice #</th>
                            <th class="px-6 py-4">Client</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Due Date</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach (array_reverse($invoices) as $invoice): 
                            $client = array_values(array_filter($clients, fn($c) => $c['id'] === ($invoice['clientId'] ?? '')))[0] ?? null;
                        ?>
                            <tr class="hover:bg-gray-50/50 transition-colors group">
                                <td class="px-6 py-4">
                                    <span class="font-mono font-bold text-gray-900">#<?php echo e($invoice['invoiceNumber']); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900"><?php echo e($client['name'] ?? 'Unknown'); ?></div>
                                    <div class="text-[10px] uppercase font-black text-gray-400 tracking-tighter"><?php echo e($client['company'] ?? 'N/A'); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-[10px] font-black uppercase tracking-widest rounded-full <?php echo statusClass($invoice['status']); ?>">
                                        <?php echo e($invoice['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-bold text-gray-500">
                                    <?php echo formatDate($invoice['dueDate']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-black text-gray-900">
                                        <?php echo formatCurrency($invoice['total'], $invoice['currency'] ?? 'USD'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="?page=invoice-view&id=<?php echo e($invoice['id']); ?>"
                                           class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-blue-600 hover:border-blue-100 transition-all shadow-sm" title="View">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        </a>
                                        <a href="?page=invoice-form&id=<?php echo e($invoice['id']); ?>"
                                           class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-black hover:border-black transition-all shadow-sm" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <button onclick="confirmAction('Delete this invoice?', () => deleteInvoice('<?php echo e($invoice['id']); ?>'))"
                                                class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-100 transition-all shadow-sm" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function downloadInvoice(id) {
    showToast('Preparing document...', 'info');
    window.location.href = `?page=invoice-view&id=${id}&autodownload=1`;
}

async function deleteInvoice(id) {
    try {
        const response = await api.delete(`api/invoices.php?id=${id}`);
        if (response.success) {
            showToast('Invoice deleted', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 500);
        } else {
            showToast(response.error || 'Failed to delete invoice', 'error');
        }
    } catch (error) {
        showToast('Failed to delete invoice', 'error');
    }
}
</script>

