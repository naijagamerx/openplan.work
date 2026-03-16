<?php
/**
 * Modern Invoice Template - Creative Design
 */
$db = new Database(getMasterPassword(), Auth::userId());
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: ?page=advanced-invoices');
    exit;
}

$invoices = $db->load('advanced_invoices');
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

$config = $db->load('config');
$currency = $invoice['currency'] ?? ($config['currency'] ?? 'ZAR');
$currencySymbol = getCurrencySymbol($currency);
?>

<div class="no-print px-4 py-4">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <a href="?page=advanced-invoices" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <h2 class="text-xl font-bold text-gray-900">Invoice #<?php echo e($invoice['invoiceNumber']); ?></h2>
        </div>
        <div class="flex items-center gap-3">
            <!-- Template Selector -->
            <div class="flex items-center gap-2 bg-gray-100 rounded-xl p-1">
                <button onclick="switchTemplate('classic')" class="template-btn flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-bold transition <?php echo (($invoice['template'] ?? 'classic') === 'classic') ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-900'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Classic
                </button>
                <button onclick="switchTemplate('modern')" class="template-btn flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-bold transition <?php echo (($invoice['template'] ?? 'classic') === 'modern') ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-900'; ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                    Modern
                </button>
            </div>
            <button id="downloadAdvancedInvoicePdfBtnDesktopModern" type="button" class="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition shadow-lg text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Download PDF
            </button>
            <a href="?page=advanced-invoice-form&id=<?php echo e($invoice['id']); ?>" class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 text-gray-900 rounded-xl font-bold hover:bg-gray-50 transition shadow-sm text-sm">
                Edit
            </a>
        </div>
    </div>
</div>

<!-- MODERN INVOICE DESIGN -->
<div id="advanced-invoice-download-root" class="print-container bg-white shadow-lg mx-auto" style="max-width: 210mm;">
    <!-- Gradient Header -->
    <div class="header-gradient bg-gradient-to-r from-blue-600 to-blue-800 px-6 py-4 print:px-6 print:py-3">
        <div class="flex justify-between items-start">
            <div class="flex items-center gap-3">
                <?php if (!empty($invoice['companyHeader']['logoUrl'])): ?>
                    <img src="<?php echo e($invoice['companyHeader']['logoUrl']); ?>" alt="Logo" class="w-12 h-12 bg-white rounded-xl p-1 object-contain">
                <?php else: ?>
                    <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                <?php endif; ?>
                <div class="text-white">
                    <h1 class="text-lg font-black uppercase tracking-tight"><?php echo e($invoice['companyHeader']['companyName'] ?? 'Your Company'); ?></h1>
                    <?php if (!empty($invoice['companyHeader']['companyAddress'])): ?>
                        <p class="text-[8px] text-blue-100"><?php echo e($invoice['companyHeader']['companyAddress']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right text-white">
                <p class="text-[8px] uppercase tracking-widest text-blue-200 mb-1">Invoice Number</p>
                <p class="text-lg font-black"><?php echo e($invoice['invoiceNumber']); ?></p>
                <p class="text-[8px] uppercase tracking-widest text-blue-200 mb-1 mt-2">Invoice Date</p>
                <p class="text-sm font-bold"><?php echo formatDate($invoice['invoiceDate']); ?></p>
            </div>
        </div>
    </div>

    <div class="invoice-content p-5 print:p-5">

        <!-- Contract Period -->
        <?php if (!empty($invoice['customer']['contractFrom']) || !empty($invoice['customer']['contractTo'])): ?>
        <div class="mb-3 pb-2 border-b border-gray-100">
            <p class="text-[8px] uppercase tracking-widest text-gray-500">
                <span class="font-bold text-blue-600">Contract Period:</span>
                <?php if (!empty($invoice['customer']['contractFrom'])): ?>
                    From <span class="font-medium"><?php echo formatDate($invoice['customer']['contractFrom']); ?></span>
                <?php endif; ?>
                <?php if (!empty($invoice['customer']['contractTo'])): ?>
                    To <span class="font-medium"><?php echo formatDate($invoice['customer']['contractTo']); ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <!-- Bill To Card -->
            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-4">
                <p class="text-[8px] uppercase tracking-widest text-gray-500 mb-2 font-bold">Bill To</p>
                <h3 class="text-base font-bold text-gray-900 mb-1"><?php echo e($invoice['customer']['name'] ?? 'Unknown Customer'); ?></h3>
                <?php if (!empty($invoice['customer']['customerId'])): ?>
                    <p class="text-[9px] text-gray-500 mb-2">ID: <?php echo e($invoice['customer']['customerId']); ?></p>
                <?php endif; ?>
                <div class="text-[9px] text-gray-700 space-y-0.5">
                    <?php if (!empty($invoice['customer']['email'])): ?>
                        <p><?php echo e($invoice['customer']['email']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoice['customer']['phone'])): ?>
                        <p><?php echo e($invoice['customer']['phone']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($invoice['customer']['propertyAddress'])): ?>
                        <p class="whitespace-pre-line"><?php echo e($invoice['customer']['propertyAddress']); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Balance Card -->
            <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 text-white">
                <p class="text-[8px] uppercase tracking-widest text-blue-200 mb-2">Balance Due</p>
                <p class="text-2xl font-black"><?php echo formatCurrency($invoice['totalDue'] ?? 0, $currency); ?></p>
                <?php if (!empty($invoice['paymentDetails']['dueDate'])): ?>
                    <p class="text-[8px] text-blue-200 mt-2">Due: <?php echo formatDate($invoice['paymentDetails']['dueDate']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Line Items Table -->
        <div class="mb-4">
            <table class="w-full">
                <thead>
                    <tr class="bg-gradient-to-r from-blue-50 to-blue-100">
                        <th class="text-[8px] uppercase tracking-widest text-blue-800 font-bold py-2 px-3 text-left rounded-tl-lg">Date</th>
                        <th class="text-[8px] uppercase tracking-widest text-blue-800 font-bold py-2 px-2 text-left">Ref</th>
                        <th class="text-[8px] uppercase tracking-widest text-blue-800 font-bold py-2 px-3 text-left">Description</th>
                        <th class="text-[8px] uppercase tracking-widest text-blue-800 font-bold py-2 px-3 text-right rounded-tr-lg">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($invoice['lineItems'] as $item): ?>
                    <tr class="text-sm hover:bg-blue-50/50">
                        <td class="py-2 px-3 text-gray-600 whitespace-nowrap"><?php echo formatDate($item['date'] ?? ''); ?></td>
                        <td class="py-2 px-2 text-gray-600 whitespace-nowrap"><?php echo e($item['reference'] ?? ''); ?></td>
                        <td class="py-2 px-3 text-gray-900 font-medium"><?php echo e($item['description'] ?? ''); ?></td>
                        <td class="py-2 px-3 text-right text-gray-900 font-bold"><?php echo formatCurrency($item['amount'] ?? 0, $currency); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-blue-600">
                        <td colspan="3" class="py-3 px-3 text-white text-[9px] uppercase tracking-widest font-bold">Total</td>
                        <td class="py-3 px-3 text-right text-white text-lg font-black"><?php echo formatCurrency($invoice['totalDue'] ?? 0, $currency); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Payment Details -->
        <?php if (!empty($invoice['paymentDetails']['bankName']) || !empty($invoice['paymentDetails']['accountNumber'])): ?>
        <div class="bg-gray-50 rounded-xl p-4 mb-3">
            <p class="text-[9px] uppercase tracking-widest text-gray-500 mb-3 font-bold">Payment Details</p>
            <div class="grid grid-cols-4 gap-3 text-[10px]">
                <?php if (!empty($invoice['paymentDetails']['bankName'])): ?>
                    <div>
                        <p class="text-gray-500 mb-1">Bank</p>
                        <p class="font-bold text-gray-900"><?php echo e($invoice['paymentDetails']['bankName']); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['paymentDetails']['accountNumber'])): ?>
                    <div>
                        <p class="text-gray-500 mb-1">Account</p>
                        <p class="font-bold text-gray-900 font-mono text-[9px]"><?php echo e($invoice['paymentDetails']['accountNumber']); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['paymentDetails']['branchCode'])): ?>
                    <div>
                        <p class="text-gray-500 mb-1">Branch</p>
                        <p class="font-bold text-gray-900 font-mono"><?php echo e($invoice['paymentDetails']['branchCode']); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($invoice['paymentDetails']['paymentReference'])): ?>
                    <div>
                        <p class="text-gray-500 mb-1">Reference</p>
                        <p class="font-bold text-gray-900"><?php echo e($invoice['paymentDetails']['paymentReference']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($invoice['notes'])): ?>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 rounded-r-xl p-3 mb-3">
            <p class="text-[8px] uppercase tracking-widest text-yellow-700 mb-1 font-bold">Notes</p>
            <p class="text-[9px] text-gray-700"><?php echo nl2br(e($invoice['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <?php if (!empty($invoice['footerText'])): ?>
        <div class="text-center py-2 border-t border-gray-100">
            <p class="text-[8px] text-gray-500"><?php echo nl2br(e($invoice['footerText'])); ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-2 text-center">
            <p class="text-[7px] uppercase tracking-widest text-gray-400">Generated with <?php echo e(getSiteName()); ?></p>
        </div>
    </div>
</div>

<style>
/* Screen styles */
.print-container {
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.invoice-content {
    padding: 1.25rem;
}

@media (min-width: 768px) {
    .invoice-content {
        padding: 1.5rem;
    }
}

/* Print styles - A4 size */
@page {
    size: A4;
    margin: 5mm;
}

@media print {
    /* Hide all non-print elements */
    .no-print {
        display: none !important;
    }

    /* Reset body and remove scrollbars */
    html, body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: 100% !important;
        overflow: visible !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Hide ALL layout containers that cause sidebar/scrollbar issues */
    .standard-container,
    .main-content,
    .page-wrapper,
    .sidebar,
    .no-print,
    nav,
    aside {
        padding: 0 !important;
        max-width: none !important;
        margin: 0 !important;
        width: 100% !important;
        display: none !important;
    }

    /* Invoice container - exact A4 width with NO borders */
    .print-container {
        width: 200mm !important;
        max-width: 200mm !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border: none !important;
        border-left: none !important;
        border-right: none !important;
        border-radius: 0 !important;
        overflow: visible !important;
        background: white !important;
        position: static !important;
    }

    /* Minimal inner padding */
    .invoice-content {
        padding: 5mm !important;
    }

    /* Prevent page breaks and scrolling */
    .print-container,
    .invoice-content,
    .invoice-content > div {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        overflow: visible !important;
    }

    /* Ensure proper text rendering and colors */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Remove any borders */
    * {
        border-left-width: 0 !important;
        border-right-width: 0 !important;
    }
}
</style>

<script>
async function switchTemplate(template) {
    const invoiceId = '<?php echo e($invoice['id']); ?>';
    const csrfToken = '<?php echo Auth::csrfToken(); ?>';

    try {
        const response = await fetch('api/advanced-invoices.php?id=' + invoiceId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                template: template,
                csrf_token: csrfToken
            })
        });

        if (response.ok) {
            // Reload the page to see the new template
            window.location.reload();
        } else {
            showToast('Failed to switch template. Please try again.', 'error');
        }
    } catch (error) {
        console.error('Error switching template:', error);
        showToast('Failed to switch template. Please try again.', 'error');
    }
}
</script>

<?php if (!empty($autoPrint)): ?>
<script>
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 250);
});
</script>
<?php endif; ?>

<script>
const advancedInvoiceModernPdfConfig = {
    filename: <?= json_encode($pdfFileName ?? ('advanced-invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)($invoice['invoiceNumber'] ?? $invoice['id'])) . '.pdf')) ?>,
    downloadOnly: <?= !empty($downloadOnly) ? 'true' : 'false' ?>,
    embedded: <?= !empty($embedded) ? 'true' : 'false' ?>,
    autoClose: <?= !empty($autoClose) ? 'true' : 'false' ?>,
    returnTo: <?= json_encode($returnTo ?? '') ?>,
    invoiceId: <?= json_encode((string)($invoice['id'] ?? '')) ?>
};

function notifyAdvancedModernInvoicePdfError(message) {
    if (typeof showToast === 'function') {
        showToast(message, 'error');
        return;
    }
    console.error(message);
}

function postAdvancedModernInvoicePdfStatus(status, reason = '') {
    if (!advancedInvoiceModernPdfConfig.embedded || !window.parent || window.parent === window) {
        return;
    }
    const payload = {
        type: 'invoice-pdf',
        status,
        invoiceId: advancedInvoiceModernPdfConfig.invoiceId
    };
    if (reason) {
        payload.reason = reason;
    }
    window.parent.postMessage(payload, '*');
}

function handleAdvancedModernInvoiceAutoClose() {
    if (!advancedInvoiceModernPdfConfig.autoClose) {
        return;
    }
    setTimeout(() => {
        window.close();
        if (advancedInvoiceModernPdfConfig.returnTo && !window.closed) {
            window.location.href = advancedInvoiceModernPdfConfig.returnTo;
        }
    }, 800);
}

async function downloadAdvancedInvoicePdfModern(options = {}) {
    const root = document.getElementById('advanced-invoice-download-root') || document.querySelector('.print-container');
    const allowPrintFallback = options.allowPrintFallback === true && !advancedInvoiceModernPdfConfig.downloadOnly;

    if (!root || typeof html2pdf === 'undefined') {
        postAdvancedModernInvoicePdfStatus('error', 'pdf-library-missing');
        if (allowPrintFallback) {
            setTimeout(() => window.print(), 200);
            return false;
        }
        notifyAdvancedModernInvoicePdfError('PDF download is not available right now.');
        return false;
    }

    try {
        await html2pdf()
            .set({
                margin: [0, 0, 0, 0],
                filename: advancedInvoiceModernPdfConfig.filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] }
            })
            .from(root)
            .save();

        postAdvancedModernInvoicePdfStatus('success');
        handleAdvancedModernInvoiceAutoClose();
        return true;
    } catch (error) {
        console.error('Advanced modern invoice PDF download failed:', error);
        postAdvancedModernInvoicePdfStatus('error', 'pdf-generation-failed');
        if (allowPrintFallback) {
            setTimeout(() => window.print(), 200);
            return false;
        }
        notifyAdvancedModernInvoicePdfError('PDF download failed. Please try again.');
        return false;
    }
}

window.addEventListener('load', () => {
    const downloadBtn = document.getElementById('downloadAdvancedInvoicePdfBtnDesktopModern');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            downloadAdvancedInvoicePdfModern();
        });
    }
    <?php if (!empty($autoDownload)): ?>
    setTimeout(() => {
        downloadAdvancedInvoicePdfModern({ allowPrintFallback: true });
    }, 120);
    <?php endif; ?>
});
</script>

