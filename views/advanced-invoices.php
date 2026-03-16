<?php
/**
 * Advanced Invoices List View
 */

$db = new Database(getMasterPassword(), Auth::userId());
$configResult = $db->safeLoad('config');
$config = is_array($configResult['data'] ?? null) ? $configResult['data'] : [];
$invoicesResult = $db->safeLoad('advanced_invoices');
$invoices = is_array($invoicesResult['data'] ?? null) ? $invoicesResult['data'] : [];
$invoiceDataWarning = !($invoicesResult['success'] ?? true);

$statusFilter = $_GET['status'] ?? 'all';
if ($statusFilter !== 'all') {
    $invoices = array_filter($invoices, function($i) use ($statusFilter) {
        return ($i['status'] ?? 'unpaid') === $statusFilter;
    });
}

// Calculate stats
$totalInvoices = count($invoices);
$totalBalance = array_sum(array_column($invoices, 'totalDue') ?? [0]);

$thisMonth = date('Y-m');
$monthInvoices = array_filter($invoices, function($i) use ($thisMonth) {
    return str_starts_with($i['invoiceDate'] ?? '', $thisMonth);
});
$thisMonthCount = count($monthInvoices);

$currencySymbol = getCurrencySymbol($config['currency'] ?? 'ZAR');
?>

<style>
    @media (max-width: 767px) {
        .invoice-row {
            @apply border-b border-black/10 transition-all duration-200 cursor-pointer;
        }
        .invoice-row:active {
            @apply bg-gray-50;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .fab-shadow {
            box-shadow: 4px 4px 0px 0px rgba(0,0,0,1);
        }
    }
</style>

<div class="hidden md:block">
    <?php if ($invoiceDataWarning): ?>
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Advanced invoice data could not be decrypted with the current master password. The page is available, but invoice history may be incomplete.
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-gray-900">Advanced Invoices</h1>
            <p class="text-gray-400 font-medium mt-1">Create custom invoices with flexible fields</p>
        </div>
        <a href="?page=advanced-invoice-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-2xl font-black uppercase tracking-widest hover:bg-gray-800 transition shadow-lg text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            New Invoice
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Total Invoices</span>
                <div class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-gray-900"><?php echo $totalInvoices; ?></p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">This Month</span>
                <div class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-gray-900"><?php echo $thisMonthCount; ?></p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Total Balance</span>
                <div class="w-10 h-10 bg-black rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-gray-900"><?php echo $currencySymbol . number_format($totalBalance, 2); ?></p>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($invoices)): ?>
            <!-- Empty State -->
            <div class="p-16 text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-3xl flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <h3 class="text-lg font-black text-gray-900 mb-2">No invoices yet</h3>
                <p class="text-gray-400 font-medium mb-6">Create your first advanced invoice to get started</p>
                <a href="?page=advanced-invoice-form" class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Create Invoice
                </a>
            </div>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50/50">
                    <tr class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                        <th class="px-6 py-4 text-left">Invoice #</th>
                        <th class="px-6 py-4 text-left">Customer</th>
                        <th class="px-6 py-4 text-left">Date</th>
                        <th class="px-6 py-4 text-right">Amount</th>
                        <th class="px-6 py-4 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($invoices as $invoice): ?>
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-mono font-bold text-sm"><?php echo e($invoice['invoiceNumber'] ?? ''); ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-900"><?php echo e($invoice['customer']['name'] ?? 'Unknown'); ?></div>
                            <div class="text-xs text-gray-400"><?php echo e($invoice['customer']['email'] ?? ''); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-sm font-bold text-gray-600"><?php echo formatDate($invoice['invoiceDate'] ?? ''); ?></span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-black text-gray-900"><?php echo $currencySymbol . number_format($invoice['totalDue'] ?? 0, 2); ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <a href="?page=advanced-invoice-view&id=<?php echo e($invoice['id']); ?>"
                                   class="w-8 h-8 flex items-center justify-center bg-gray-50 rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-900"
                                   title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                </a>
                                <a href="?page=advanced-invoice-form&id=<?php echo e($invoice['id']); ?>"
                                   class="w-8 h-8 flex items-center justify-center bg-gray-50 rounded-lg hover:bg-gray-100 transition text-gray-400 hover:text-gray-900"
                                   title="Edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                </a>
                                <button onclick="deleteInvoice('<?php echo e($invoice['id']); ?>')"
                                        class="w-8 h-8 flex items-center justify-center bg-gray-50 rounded-lg hover:bg-red-50 transition text-gray-400 hover:text-red-500"
                                        title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile View -->
<div class="block md:hidden -m-4 sm:-m-6 lg:-m-8 bg-white min-h-screen relative overflow-hidden flex flex-col">
    <div class="px-6 pt-6 pb-4 border-b border-black">
        <div class="flex justify-between items-start mb-6">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-gray-400">Total Outstanding</p>
                <h1 class="text-4xl font-black tracking-tighter uppercase leading-none mt-1"><?php echo $currencySymbol . number_format($totalBalance, 2); ?></h1>
            </div>
            <button class="size-8 flex items-center justify-center border border-black hover:bg-black hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            </button>
        </div>
        <div class="flex gap-2">
            <a href="?page=advanced-invoices&status=unpaid" class="flex-1 border border-black p-2 <?php echo $statusFilter === 'unpaid' ? 'bg-black text-white' : ''; ?> text-center">
                <span class="text-[9px] font-black uppercase tracking-widest">Unpaid</span>
            </a>
            <a href="?page=advanced-invoices&status=paid" class="flex-1 border border-black p-2 <?php echo $statusFilter === 'paid' ? 'bg-black text-white' : ''; ?> text-center">
                <span class="text-[9px] font-black uppercase tracking-widest">Paid</span>
            </a>
            <a href="?page=advanced-invoices&status=draft" class="flex-1 border border-black p-2 <?php echo $statusFilter === 'draft' ? 'bg-black text-white' : ''; ?> text-center">
                <span class="text-[9px] font-black uppercase tracking-widest">Draft</span>
            </a>
            <?php if ($statusFilter !== 'all'): ?>
            <a href="?page=advanced-invoices&status=all" class="border border-black p-2 text-center">
                <span class="text-[9px] font-black uppercase tracking-widest">X</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <main class="flex-1 overflow-y-auto no-scrollbar pb-24">
        <div class="w-full">
            <div class="flex justify-between px-6 py-3 bg-gray-50 border-b border-black text-[9px] font-black uppercase tracking-widest">
                <span>Customer / Ref</span>
                <span>Amount</span>
            </div>
            
            <?php if (empty($invoices)): ?>
                <div class="px-6 py-12 text-center">
                    <p class="text-gray-400 font-bold uppercase tracking-widest text-xs">No invoices found</p>
                </div>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): 
                    $dueDate = $invoice['paymentDetails']['dueDate'] ?? null;
                    $isOverdue = false;
                    $dueText = '';
                    if ($dueDate) {
                        $dueTimestamp = strtotime($dueDate);
                        $today = strtotime(date('Y-m-d'));
                        $diff = ($dueTimestamp - $today) / (60 * 60 * 24);
                        if ($diff < 0) {
                            $isOverdue = true;
                            $dueText = 'Overdue';
                        } elseif ($diff == 0) {
                            $dueText = 'Due Today';
                        } elseif ($diff <= 7) {
                            $dueText = "Due in $diff days";
                        } else {
                            $dueText = "Due " . date('M d', $dueTimestamp);
                        }
                    }
                ?>
                <div class="invoice-row px-6 py-5 border-b border-black/5" onclick="location.href='?page=advanced-invoice-view&id=<?php echo $invoice['id']; ?>'">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold uppercase tracking-tight"><?php echo e($invoice['customer']['name'] ?? 'Unknown'); ?></span>
                            <?php if ($dueText): ?>
                                <span class="text-[9px] <?php echo $isOverdue ? 'text-red-600' : 'text-gray-400'; ?> font-bold uppercase mt-0.5 tracking-widest"><?php echo $dueText; ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm font-black <?php echo $isOverdue ? 'text-red-600' : ''; ?>"><?php echo $currencySymbol . number_format($invoice['totalDue'] ?? 0, 2); ?></span>
                    </div>
                    <div class="flex justify-between items-end border-t border-black/5 pt-3 mt-1">
                        <div class="space-y-0.5">
                            <p class="text-[9px] text-gray-400 uppercase tracking-widest"><?php echo e($invoice['invoiceNumber'] ?? ''); ?></p>
                            <p class="text-[9px] text-gray-400 uppercase tracking-widest"><?php echo formatDate($invoice['invoiceDate'] ?? ''); ?></p>
                        </div>
                        <div class="flex gap-2">
                            <a href="?page=advanced-invoice-form&id=<?php echo $invoice['id']; ?>" class="px-3 py-1 text-[9px] font-bold border border-black uppercase hover:bg-black hover:text-white transition-colors" onclick="event.stopPropagation()">Edit</a>
                            <a href="?page=advanced-invoice-view&id=<?php echo $invoice['id']; ?>" class="px-3 py-1 text-[9px] font-bold bg-black text-white border border-black uppercase" onclick="event.stopPropagation()">View</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <a href="?page=advanced-invoice-form" class="fixed bottom-8 right-6 size-14 bg-black text-white border border-black flex items-center justify-center fab-shadow z-40 hover:bg-white hover:text-black transition-all">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    </a>
</div>

<script>
async function deleteInvoice(id) {
    const confirmed = await confirmAction('Are you sure you want to delete this invoice?');
    if (!confirmed) return;

    try {
        const response = await api.delete(`api/advanced-invoices.php?id=${id}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`);
        if (response.success) {
            showToast('Invoice deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(response.error || 'Failed to delete invoice', 'error');
        }
    } catch (error) {
        showToast('Failed to delete invoice', 'error');
    }
}
</script>

