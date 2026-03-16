<?php
/**
 * Invoice View / Printable Template
 */
$db = new Database(getMasterPassword(), Auth::userId());
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
$clientId = $invoice['clientId'] ?? '';
$client = null;
foreach ($clients as $c) {
    if (($c['id'] ?? '') === $clientId) {
        $client = $c;
        break;
    }
}
$pdfDebug = (($_GET['pdfdebug'] ?? '') === '1');

// Debug logging for PDF issues
if ($pdfDebug && !$client && !empty($clientId)) {
    error_log("Invoice PDF Debug: Client not found for ID: " . $clientId);
}
$config = $db->load('config');
$currency = $invoice['currency'] ?? ($config['currency'] ?? 'USD');
$lineItems = is_array($invoice['lineItems'] ?? null) ? $invoice['lineItems'] : [];
$autoPrint = (($_GET['autoprint'] ?? '') === '1');
$autoDownload = (($_GET['autodownload'] ?? '') === '1');
$downloadOnly = (($_GET['downloadonly'] ?? '') === '1');
$embedded = (($_GET['embedded'] ?? '') === '1');
$autoClose = (($_GET['autoclose'] ?? '') === '1');
$returnTo = trim((string)($_GET['returnto'] ?? ''));
$pdfFileName = 'invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)($invoice['invoiceNumber'] ?? $invoice['id'])) . '.pdf';
?>

<div class="">
    <div class="flex items-center justify-between mb-8 no-print">
        <div class="flex items-center gap-4">
            <a href="?page=invoices" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <h2 class="text-xl font-bold text-gray-900">Preview Invoice #<?php echo e($invoice['invoiceNumber']); ?></h2>
        </div>
        <div class="flex gap-3">
            <button id="downloadInvoicePdfBtnDesktop" type="button" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Download PDF
            </button>
            <a href="?page=invoice-form&id=<?php echo e($invoice['id']); ?>" class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 text-gray-900 rounded-xl font-bold hover:bg-gray-50 transition shadow-sm text-sm">
                Edit Invoice
            </a>
        </div>
    </div>

    <!-- MAIN INVOICE PAPER -->
    <div id="invoice-download-root" class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 p-12 md:p-20 print:p-0 print:shadow-none print:border-none print:rounded-none">
        <!-- Brand Header -->
        <div class="flex border-b-4 border-black pb-12 mb-12">
            <div class="flex-1">
                <h1 class="text-4xl font-black uppercase tracking-tighter mb-4"><?php echo e($config['businessName'] ?? 'LAZYMAN TOOLS'); ?></h1>
                <div class="text-gray-700 text-xs font-black uppercase tracking-widest space-y-1 invoice-business-address">
                    <?php
                    // Handle business address as array or string
                    $businessAddress = $config['businessAddress'] ?? '';
                    if (is_array($businessAddress)) {
                        $bizAddressParts = [];
                        foreach (['street', 'city', 'state', 'zip', 'country'] as $key) {
                            if (!empty($businessAddress[$key])) {
                                $bizAddressParts[] = $businessAddress[$key];
                            }
                        }
                        $businessAddress = implode(", ", $bizAddressParts);
                    }
                    ?>
                    <p><?php echo nl2br(e($businessAddress)); ?></p>
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
            <div class="bg-gray-50 rounded-2xl p-8 block w-fit min-w-[300px]">
                <h3 class="text-xl font-black text-gray-900 mb-1"><?php echo e($client['name'] ?? 'Untitled Client'); ?></h3>
                <p class="text-sm font-bold text-gray-600 mb-4"><?php echo e($client['company'] ?? 'Private Individual'); ?></p>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-700 space-y-1 invoice-client-address">
                    <p><?php echo e($client['email'] ?? ''); ?></p>
                    <?php
                    // Address can be stored as array OR string - handle both
                    $address = $client['address'] ?? null;
                    $addressParts = [];
                    if (is_array($address)) {
                        foreach (['street', 'city', 'state', 'zip', 'country'] as $key) {
                            if (!empty($address[$key])) {
                                $addressParts[] = $address[$key];
                            }
                        }
                    } elseif (is_string($address) && trim($address) !== '') {
                        // Handle string address - split by newlines or commas
                        $parts = preg_split('/[\n,]+/', $address);
                        foreach ($parts as $part) {
                            $part = trim($part);
                            if ($part !== '') {
                                $addressParts[] = $part;
                            }
                        }
                    }
                    ?>
                    <p><?php echo nl2br(e(implode("\n", $addressParts))); ?></p>
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
                    <?php foreach ($lineItems as $item): ?>
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
            <p class="text-[10px] font-black uppercase tracking-[0.5em] text-gray-200">Generated with <?php echo e(getSiteName()); ?></p>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print { display: none !important; }
    body { background: white !important; }
    .standard-container { padding: 0 !important; max-width: none !important; }

    /* Force text colors and background rendering for PDF visibility */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Ensure all text is dark enough in PDF */
    #invoice-download-root h1,
    #invoice-download-root h2,
    #invoice-download-root h3,
    #invoice-download-root p,
    #invoice-download-root span,
    #invoice-download-root div,
    #invoice-download-root td,
    #invoice-download-root th,
    #invoice-download-root li,
    #invoice-download-root strong,
    #invoice-download-root em,
    #invoice-download-root b,
    #invoice-download-root i,
    .invoice-business-address,
    .invoice-client-address {
        color: #000000 !important;
    }

    /* Preserve white text on dark backgrounds specifically */
    .bg-black *,
    .bg-gray-900 * {
        color: #ffffff !important;
    }

    /* Fix light gray text specifically for print */
    .text-gray-300,
    .text-gray-400,
    .text-gray-500 {
        color: #374151 !important; /* gray-700 equivalent */
    }

    /* Ensure background colors are rendered */
    .bg-gray-50 {
        background-color: #f9fafb !important;
    }
}
</style>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 250);
});
</script>
<?php endif; ?>

<script>
const invoicePdfConfig = {
    filename: <?= json_encode($pdfFileName) ?>,
    autoDownload: <?= $autoDownload ? 'true' : 'false' ?>,
    downloadOnly: <?= $downloadOnly ? 'true' : 'false' ?>,
    embedded: <?= $embedded ? 'true' : 'false' ?>,
    autoClose: <?= $autoClose ? 'true' : 'false' ?>,
    returnTo: <?= json_encode($returnTo) ?>,
    invoiceId: <?= json_encode((string)($invoice['id'] ?? '')) ?>,
    debug: <?= $pdfDebug ? 'true' : 'false' ?>
};

function debugInvoicePdf(message, meta = null) {
    if (!invoicePdfConfig.debug) {
        return;
    }
    if (meta === null) {
        console.log('[invoice-pdf]', message);
        return;
    }
    console.log('[invoice-pdf]', message, meta);
}

function notifyInvoicePdfError(message) {
    if (typeof showToast === 'function') {
        showToast(message, 'error');
        return;
    }
    console.error(message);
}

function postInvoicePdfStatus(status, reason = '') {
    if (!invoicePdfConfig.embedded || !window.parent || window.parent === window) {
        return;
    }
    const payload = {
        type: 'invoice-pdf',
        status,
        invoiceId: invoicePdfConfig.invoiceId
    };
    if (reason) {
        payload.reason = reason;
    }
    debugInvoicePdf('posting status to parent', payload);
    window.parent.postMessage(payload, '*');
}

function handleAutoClose() {
    if (!invoicePdfConfig.autoClose) {
        return;
    }
    setTimeout(() => {
        window.close();
        if (invoicePdfConfig.returnTo && !window.closed) {
            window.location.href = invoicePdfConfig.returnTo;
        }
    }, 800);
}

async function downloadInvoicePdf(options = {}) {
    const root = document.getElementById('invoice-download-root');
    const allowPrintFallback = options.allowPrintFallback === true && !invoicePdfConfig.downloadOnly;
    debugInvoicePdf('downloadInvoicePdf invoked', { allowPrintFallback });

    if (!root || typeof html2pdf === 'undefined') {
        debugInvoicePdf('missing root or html2pdf', {
            rootExists: !!root,
            html2pdfType: typeof html2pdf
        });
        postInvoicePdfStatus('error', 'pdf-library-missing');
        if (allowPrintFallback) {
            setTimeout(() => window.print(), 200);
            return false;
        }
        notifyInvoicePdfError('PDF download is not available right now.');
        return false;
    }

    // Temporary styles to fix html2canvas rendering issues
    const originalShadow = root.style.boxShadow;
    const originalBorder = root.style.border;
    root.style.boxShadow = 'none';
    root.style.border = 'none';

    try {
        postInvoicePdfStatus('started');
        await html2pdf()
            .set({
                margin: [0, 0, 0, 0],
                filename: invoicePdfConfig.filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true, 
                    logging: false,
                    letterRendering: true,
                    allowTaint: true
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] }
            })
            .from(root)
            .save();

        debugInvoicePdf('download completed');
        postInvoicePdfStatus('success');
        
        // Restore styles
        root.style.boxShadow = originalShadow;
        root.style.border = originalBorder;
        
        handleAutoClose();
        return true;
    } catch (error) {
        console.error('Invoice PDF download failed:', error);
        
        // Restore styles on error too
        root.style.boxShadow = originalShadow;
        root.style.border = originalBorder;
        
        postInvoicePdfStatus('error', 'pdf-generation-failed');
        if (allowPrintFallback) {
            setTimeout(() => window.print(), 200);
            return false;
        }
        notifyInvoicePdfError('PDF download failed. Please try again.');
        return false;
    }
}

window.downloadInvoicePdf = downloadInvoicePdf;

// Simple initialization - matches working advanced-invoice-view.php pattern
window.addEventListener('load', () => {
    const downloadBtn = document.getElementById('downloadInvoicePdfBtnDesktop');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            downloadInvoicePdf();
        });
        debugInvoicePdf('download button bound');
    }
    if (invoicePdfConfig.autoDownload) {
        debugInvoicePdf('autoDownload enabled');
        setTimeout(() => {
            downloadInvoicePdf({ allowPrintFallback: true });
        }, 120);
    }
});
</script>

