<?php
/**
 * Invoice View / Printable Template
 */
$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: ?page=invoices');
    exit;
}

$invoices = $db->load('invoices');
$invoice = null;
foreach ($invoices as $i) {
    if ($i['id'] === $id) {
        $invoice = $i;
        break;
    }
}

if (!$invoice) {
    die("Invoice not found");
}

$clients = $db->load('clients');
$client = array_values(array_filter($clients, fn($c) => $c['id'] === ($invoice['clientId'] ?? '')))[0] ?? null;
$config = $db->load('config');
$currency = $invoice['currency'] ?? ($config['currency'] ?? 'USD');
?>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-8 no-print">
        <div class="flex items-center gap-4">
            <a href="?page=invoices" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <h2 class="text-xl font-bold text-gray-900">Preview Invoice #<?php echo e($invoice['invoiceNumber']); ?></h2>
        </div>
        <div class="flex gap-3">
            <button onclick="window.print()" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print / Save PDF
            </button>
            <a href="?page=invoice-form&id=<?php echo e($invoice['id']); ?>" class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 text-gray-900 rounded-xl font-bold hover:bg-gray-50 transition shadow-sm text-sm">
                Edit Invoice
            </a>
        </div>
    </div>

    <!-- MAIN INVOICE PAPER -->
    <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 p-12 md:p-20 print:p-0 print:shadow-none print:border-none print:rounded-none">
        <!-- Brand Header -->
        <div class="flex border-b-4 border-black pb-12 mb-12">
            <div class="flex-1">
                <h1 class="text-4xl font-black uppercase tracking-tighter mb-4"><?php echo e($config['businessName'] ?? 'LAZYMAN TOOLS'); ?></h1>
                <div class="text-gray-400 text-xs font-black uppercase tracking-widest space-y-1">
                    <p><?php echo nl2br(e($config['businessAddress'] ?? '')); ?></p>
                    <p><?php echo e($config['businessEmail'] ?? ''); ?></p>
                    <p><?php echo e($config['businessPhone'] ?? ''); ?></p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300 mb-1">Invoice Number</p>
                <p class="text-2xl font-black text-gray-900 mb-6">#<?php echo e($invoice['invoiceNumber']); ?></p>
                
                <div class="grid grid-cols-2 gap-8 text-left">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300 mb-1">Date Issued</p>
                        <p class="text-sm font-bold text-gray-900"><?php echo formatDate($invoice['createdAt']); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300 mb-1">Due Date</p>
                        <p class="text-sm font-bold text-gray-900"><?php echo formatDate($invoice['dueDate']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bill To Section -->
        <div class="mb-16">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300 mb-4">Billed To</p>
            <div class="bg-gray-50 rounded-2xl p-8 inline-block min-w-[300px]">
                <h3 class="text-xl font-black text-gray-900 mb-1"><?php echo e($client['name'] ?? 'Untitled Client'); ?></h3>
                <p class="text-sm font-bold text-gray-600 mb-4"><?php echo e($client['company'] ?? 'Private Individual'); ?></p>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 space-y-1">
                    <p><?php echo e($client['email'] ?? ''); ?></p>
                    <p><?php echo nl2br(e($client['address'] ?? '')); ?></p>
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="mb-16">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300 border-b border-gray-100">
                        <th class="py-4">Description</th>
                        <th class="py-4 w-24 text-center">Qty</th>
                        <th class="py-4 w-40 text-right">Unit Price</th>
                        <th class="py-4 w-40 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($invoice['lineItems'] as $item): ?>
                    <tr class="text-sm">
                        <td class="py-6 font-bold text-gray-900"><?php echo e($item['description']); ?></td>
                        <td class="py-6 text-center text-gray-500 font-bold"><?php echo e($item['quantity']); ?></td>
                        <td class="py-6 text-right text-gray-500 font-bold"><?php echo formatCurrency($item['unitPrice'], $currency); ?></td>
                        <td class="py-6 text-right font-black text-gray-900"><?php echo formatCurrency($item['total'], $currency); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Footer -->
        <div class="flex justify-end border-t-2 border-dashed border-gray-100 pt-12">
            <div class="w-full md:w-80 space-y-4">
                <div class="flex justify-between items-center text-sm font-bold">
                    <span class="text-gray-400 uppercase tracking-widest text-[10px]">Subtotal</span>
                    <span class="text-gray-900"><?php echo formatCurrency($invoice['subtotal'], $currency); ?></span>
                </div>
                <div class="flex justify-between items-center text-sm font-bold pb-2">
                    <span class="text-gray-400 uppercase tracking-widest text-[10px]">Tax (<?php echo e($invoice['taxRate']); ?>%)</span>
                    <span class="text-gray-900"><?php echo formatCurrency($invoice['taxAmount'], $currency); ?></span>
                </div>
                <div class="flex justify-between items-center bg-black text-white rounded-2xl p-6">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">Grand Total</span>
                    <span class="text-2xl font-black"><?php echo formatCurrency($invoice['total'], $currency); ?></span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($invoice['notes'])): ?>
        <div class="mt-20 pt-12 border-t border-gray-50">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gray-300 mb-4">Additional Information</p>
            <div class="text-xs text-gray-500 font-medium leading-relaxed max-w-2xl bg-gray-50/50 p-6 rounded-2xl">
                <?php echo nl2br(e($invoice['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="mt-32 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.5em] text-gray-200">Generated with LazyMan Tools</p>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .standard-container { padding: 0 !important; max-width: none !important; }
}
</style>
