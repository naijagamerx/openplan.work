<?php
/**
 * Advanced Invoice View / Printable Template
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

$autoPrint = (($_GET['autoprint'] ?? '') === '1');
$autoDownload = (($_GET['autodownload'] ?? '') === '1');
$downloadOnly = (($_GET['downloadonly'] ?? '') === '1');
$embedded = (($_GET['embedded'] ?? '') === '1');
$autoClose = (($_GET['autoclose'] ?? '') === '1');
$returnTo = trim((string)($_GET['returnto'] ?? ''));
$pdfFileName = 'advanced-invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)($invoice['invoiceNumber'] ?? $invoice['id'])) . '.pdf';

// Redirect to modern template if selected
$template = $invoice['template'] ?? 'classic';
if ($template === 'modern') {
    include __DIR__ . '/advanced-invoice-view-modern.php';
    exit;
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
            <button id="downloadAdvancedInvoicePdfBtnDesktop" type="button" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Download PDF
            </button>
            <a href="?page=advanced-invoice-form&id=<?php echo e($invoice['id']); ?>" class="flex items-center gap-2 px-6 py-3 bg-white border border-gray-200 text-gray-900 rounded-xl font-bold hover:bg-gray-50 transition shadow-sm text-sm">
                Edit
            </a>
        </div>
    </div>
</div>

<!-- MAIN INVOICE PAPER - A4 Size Container -->
<div id="advanced-invoice-download-root" class="print-container bg-white shadow-lg mx-auto" style="max-width: 210mm;">
    <div class="invoice-content">

        <!-- Company Header with Logo -->
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3 pb-3 border-b-2 border-black">
            <div class="flex items-start gap-3">
                <?php if (!empty($invoice['companyHeader']['logoUrl'])): ?>
                    <img src="<?php echo e($invoice['companyHeader']['logoUrl']); ?>" alt="Logo" class="w-12 h-12 object-contain rounded-lg">
                <?php else: ?>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-xl md:text-2xl font-black uppercase tracking-tighter text-gray-900"><?php echo e($invoice['companyHeader']['companyName'] ?? 'Your Company'); ?></h1>
                    <?php if (!empty($invoice['companyHeader']['companyEmail']) || !empty($invoice['companyHeader']['companyPhone']) || !empty($invoice['companyHeader']['companyAddress'])): ?>
                        <div class="text-[9px] font-black uppercase tracking-widest text-gray-700 space-y-0.5 mt-1">
                            <?php if (!empty($invoice['companyHeader']['companyAddress'])): ?>
                                <p><?php echo e($invoice['companyHeader']['companyAddress']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($invoice['companyHeader']['companyEmail'])): ?>
                                <p><?php echo e($invoice['companyHeader']['companyEmail']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($invoice['companyHeader']['companyPhone'])): ?>
                                <p><?php echo e($invoice['companyHeader']['companyPhone']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Invoice Date & Number (Top Right) -->
            <div class="text-right">
                <div class="mb-2">
                    <p class="text-[8px] font-black uppercase tracking-[0.2em] text-gray-700 mb-0.5">Invoice Date</p>
                    <p class="text-sm font-bold text-gray-900"><?php echo formatDate($invoice['invoiceDate']); ?></p>
                </div>
                <div>
                    <p class="text-[8px] font-black uppercase tracking-[0.2em] text-gray-700 mb-0.5">Invoice Number</p>
                    <p class="text-lg font-black text-gray-900"><?php echo e($invoice['invoiceNumber']); ?></p>
                </div>

                <!-- Custom Fields in Company Header -->
                <?php
                $companyCustomFields = array_filter($invoice['customFields'] ?? [], function($f) { return ($f['section'] ?? '') === 'companyHeader' && ($f['showOnPrint'] ?? true); });
                if (!empty($companyCustomFields)):
                ?>
                    <div class="mt-2 text-[9px] space-y-0.5">
                        <?php foreach ($companyCustomFields as $field): ?>
                            <p class="text-gray-700 font-black uppercase tracking-widest"><?php echo e($field['label']); ?>: <span class="text-gray-900 font-bold"><?php echo e($field['value']); ?></span></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contract Period (before border) -->
        <?php if (!empty($invoice['customer']['contractFrom']) || !empty($invoice['customer']['contractTo'])): ?>
        <div class="pb-2">
            <p class="text-[8px] font-semibold uppercase tracking-[0.2em] text-gray-700">
                Contract Period:
                <?php if (!empty($invoice['customer']['contractFrom'])): ?>
                    From <?php echo formatDate($invoice['customer']['contractFrom']); ?>
                <?php endif; ?>
                <?php if (!empty($invoice['customer']['contractTo'])): ?>
                    To <?php echo formatDate($invoice['customer']['contractTo']); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Bill To Section -->
        <div class="py-3 border-b border-gray-100">
            <p class="text-[8px] font-black uppercase tracking-[0.2em] text-gray-700 mb-2">Bill To</p>
            <div class="bg-gray-50 rounded-xl p-4 block w-fit min-w-[280px]">
                <h3 class="text-base font-black text-gray-900 mb-1"><?php echo e($invoice['customer']['name'] ?? 'Unknown Customer'); ?></h3>
                <?php if (!empty($invoice['customer']['customerId'])): ?>
                    <p class="text-[9px] font-black uppercase tracking-widest text-gray-700 mb-2">ID: <?php echo e($invoice['customer']['customerId']); ?></p>
                <?php endif; ?>
                <div class="text-[9px] font-black uppercase tracking-widest text-gray-700 space-y-0.5">
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

            <!-- Custom Fields in Customer Section -->
            <?php
            $customerCustomFields = array_filter($invoice['customFields'] ?? [], function($f) { return ($f['section'] ?? '') === 'customer' && ($f['showOnPrint'] ?? true); });
            if (!empty($customerCustomFields)):
            ?>
                <div class="mt-2 text-[9px] space-y-0.5">
                    <?php foreach ($customerCustomFields as $field): ?>
                        <p class="text-gray-700 font-black uppercase tracking-widest"><?php echo e($field['label']); ?>: <span class="text-gray-900 font-bold"><?php echo e($field['value']); ?></span></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Line Items -->
        <div class="py-3 border-b border-gray-100">
            <p class="text-[8px] font-black uppercase tracking-[0.2em] text-gray-700 mb-2">Account Activity</p>
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[9px] font-semibold uppercase tracking-[0.25em] text-gray-700 border-b border-gray-100">
                        <th class="py-2 w-20 pr-2">Date</th>
                        <th class="py-2 w-32 px-1">Ref</th>
                        <th class="py-2 pl-1">Description</th>
                        <th class="py-2 w-28 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($invoice['lineItems'] as $item): ?>
                    <tr class="text-sm">
                        <td class="py-2 font-medium text-gray-800 whitespace-nowrap pr-2"><?php echo formatDate($item['date'] ?? ''); ?></td>
                        <td class="py-2 font-medium text-gray-800 whitespace-nowrap px-1"><?php echo e($item['reference'] ?? ''); ?></td>
                        <td class="py-2 font-medium text-gray-900 pl-1"><?php echo e($item['description'] ?? ''); ?></td>
                        <td class="py-2 text-right font-semibold text-gray-900"><?php echo formatCurrency($item['amount'] ?? 0, $currency); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Footer -->
        <div class="flex justify-end py-3 border-t border-dashed border-gray-200">
            <div class="w-full md:w-56">
                <div class="bg-black text-white rounded-xl p-4">
                    <div class="flex justify-between items-center">
                        <span class="text-[9px] font-black uppercase tracking-[0.15em] text-gray-400">Balance Due</span>
                        <span class="text-xl font-black"><?php echo formatCurrency($invoice['totalDue'] ?? 0, $currency); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Details -->
        <?php if (!empty($invoice['paymentDetails']['bankName']) || !empty($invoice['paymentDetails']['accountNumber']) || !empty($invoice['paymentDetails']['branchCode'])): ?>
        <div class="py-3 border-t border-gray-100">
            <p class="text-[8px] font-black uppercase tracking-[0.2em] text-gray-700 mb-2">Payment Details</p>
            <div class="bg-gray-50 rounded-xl p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-[9px]">
                    <?php if (!empty($invoice['paymentDetails']['bankName'])): ?>
                        <div>
                            <p class="text-gray-700 font-black uppercase tracking-widest mb-1">Bank</p>
                            <p class="font-bold text-gray-900"><?php echo e($invoice['paymentDetails']['bankName']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['paymentDetails']['accountNumber'])): ?>
                        <div>
                            <p class="text-gray-700 font-black uppercase tracking-widest mb-1">Account</p>
                            <p class="font-bold text-gray-900 font-mono text-[9px]"><?php echo e($invoice['paymentDetails']['accountNumber']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['paymentDetails']['branchCode'])): ?>
                        <div>
                            <p class="text-gray-700 font-black uppercase tracking-widest mb-1">Branch</p>
                            <p class="font-bold text-gray-900 font-mono"><?php echo e($invoice['paymentDetails']['branchCode']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($invoice['paymentDetails']['paymentReference'])): ?>
                        <div>
                            <p class="text-gray-700 font-black uppercase tracking-widest mb-1">Reference</p>
                            <p class="font-bold text-gray-900"><?php echo e($invoice['paymentDetails']['paymentReference']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Custom Fields in Payment Details -->
                <?php
                $paymentCustomFields = array_filter($invoice['customFields'] ?? [], function($f) { return ($f['section'] ?? '') === 'paymentDetails' && ($f['showOnPrint'] ?? true); });
                if (!empty($paymentCustomFields)):
                ?>
                    <div class="mt-3 pt-3 border-t border-gray-200 text-[9px] grid grid-cols-2 gap-2">
                        <?php foreach ($paymentCustomFields as $field): ?>
                            <p class="text-gray-700 font-black uppercase tracking-widest"><?php echo e($field['label']); ?>: <span class="text-gray-900 font-bold"><?php echo e($field['value']); ?></span></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($invoice['paymentDetails']['dueDate'])): ?>
                    <div class="mt-3 pt-3 border-t border-gray-200 flex justify-between items-center">
                        <p class="text-gray-700 font-black uppercase tracking-widest text-[9px]">Due Date</p>
                        <p class="font-bold text-gray-900 text-sm"><?php echo formatDate($invoice['paymentDetails']['dueDate']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notes -->
        <?php if (!empty($invoice['notes'])): ?>
        <div class="py-3 border-t border-gray-100">
            <p class="text-[8px] font-black uppercase tracking-[0.2em] text-gray-700 mb-1.5">Notes</p>
            <div class="text-[9px] text-gray-800 font-medium leading-snug bg-gray-50/50 p-3 rounded-lg">
                <?php echo nl2br(e($invoice['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Text -->
        <?php if (!empty($invoice['footerText'])): ?>
        <div class="py-2 border-t border-gray-100">
            <div class="text-[9px] text-gray-700 font-medium text-center">
                <?php echo nl2br(e($invoice['footerText'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Custom Fields -->
        <?php
        $footerCustomFields = array_filter($invoice['customFields'] ?? [], function($f) { return ($f['section'] ?? '') === 'footer' && ($f['showOnPrint'] ?? true); });
        if (!empty($footerCustomFields)):
        ?>
        <div class="py-2 border-t border-gray-100 text-center text-[9px] space-y-0">
            <?php foreach ($footerCustomFields as $field): ?>
                <p class="text-gray-700 font-black uppercase tracking-widest"><?php echo e($field['label']); ?>: <span class="text-gray-800"><?php echo e($field['value']); ?></span></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Generated Footer -->
        <div class="mt-3 text-center">
            <p class="text-[8px] font-black uppercase tracking-[0.35em] text-gray-600">Generated with <?php echo e(getSiteName()); ?></p>
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
        padding: 1.75rem;
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

    /* Remove all container constraints that cause scrolling */
    .standard-container,
    .main-content,
    .page-wrapper,
    .no-print {
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
    }

    /* Minimal inner padding */
    .invoice-content {
        padding: 6mm !important;
    }

    /* Prevent page breaks and scrolling */
    .print-container,
    .invoice-content,
    .invoice-content > div {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        overflow: visible !important;
    }

    /* Ensure proper text rendering */
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

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 250);
});
</script>
<?php endif; ?>

<script>
const advancedInvoicePdfConfig = {
    filename: <?= json_encode($pdfFileName) ?>,
    downloadOnly: <?= $downloadOnly ? 'true' : 'false' ?>,
    embedded: <?= $embedded ? 'true' : 'false' ?>,
    autoClose: <?= $autoClose ? 'true' : 'false' ?>,
    returnTo: <?= json_encode($returnTo) ?>,
    invoiceId: <?= json_encode((string)($invoice['id'] ?? '')) ?>
};

function notifyAdvancedInvoicePdfError(message) {
    if (typeof showToast === 'function') {
        showToast(message, 'error');
        return;
    }
    console.error(message);
}

function postAdvancedInvoicePdfStatus(status, reason = '') {
    if (!advancedInvoicePdfConfig.embedded || !window.parent || window.parent === window) {
        return;
    }
    const payload = {
        type: 'invoice-pdf',
        status,
        invoiceId: advancedInvoicePdfConfig.invoiceId
    };
    if (reason) {
        payload.reason = reason;
    }
    window.parent.postMessage(payload, '*');
}

function handleAdvancedInvoiceAutoClose() {
    if (!advancedInvoicePdfConfig.autoClose) {
        return;
    }
    setTimeout(() => {
        window.close();
        if (advancedInvoicePdfConfig.returnTo && !window.closed) {
            window.location.href = advancedInvoicePdfConfig.returnTo;
        }
    }, 800);
}

async function downloadAdvancedInvoicePdf(options = {}) {
    const root = document.getElementById('advanced-invoice-download-root') || document.querySelector('.print-container');
    const allowPrintFallback = options.allowPrintFallback === true && !advancedInvoicePdfConfig.downloadOnly;

    if (!root || typeof html2pdf === 'undefined') {
        postAdvancedInvoicePdfStatus('error', 'pdf-library-missing');
        if (allowPrintFallback) {
            setTimeout(() => window.print(), 200);
            return false;
        }
        notifyAdvancedInvoicePdfError('PDF download is not available right now.');
        return false;
    }

    try {
        await html2pdf()
            .set({
                margin: [0, 0, 0, 0],
                filename: advancedInvoicePdfConfig.filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'] }
            })
            .from(root)
            .save();

        postAdvancedInvoicePdfStatus('success');
        handleAdvancedInvoiceAutoClose();
        return true;
    } catch (error) {
        console.error('Advanced invoice PDF download failed:', error);
        postAdvancedInvoicePdfStatus('error', 'pdf-generation-failed');
        if (allowPrintFallback) {
            setTimeout(() => window.print(), 200);
            return false;
        }
        notifyAdvancedInvoicePdfError('PDF download failed. Please try again.');
        return false;
    }
}

window.addEventListener('load', () => {
    const downloadBtn = document.getElementById('downloadAdvancedInvoicePdfBtnDesktop');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            downloadAdvancedInvoicePdf();
        });
    }
    <?php if ($autoDownload): ?>
    setTimeout(() => {
        downloadAdvancedInvoicePdf({ allowPrintFallback: true });
    }, 120);
    <?php endif; ?>
});
</script>

