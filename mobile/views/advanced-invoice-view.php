<?php
/**
 * Mobile Advanced Invoice Preview
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=advanced-invoices">Back to advanced invoices</a></p>
    </body></html>');
}

$invoiceId = trim((string)($_GET['id'] ?? ''));
if ($invoiceId === '') {
    header('Location: ?page=advanced-invoices');
    exit;
}

$configResult = method_exists($db, 'safeLoad') ? $db->safeLoad('config') : ['success' => true, 'data' => $db->load('config')];
$invoiceResult = method_exists($db, 'safeLoad') ? $db->safeLoad('advanced_invoices') : ['success' => true, 'data' => $db->load('advanced_invoices')];
$config = is_array($configResult['data'] ?? null) ? $configResult['data'] : [];
$invoices = is_array($invoiceResult['data'] ?? null) ? $invoiceResult['data'] : [];
$invoiceDataWarning = !($invoiceResult['success'] ?? true);

$invoice = null;
foreach ($invoices as $candidate) {
    if ((string)($candidate['id'] ?? '') === $invoiceId) {
        $invoice = $candidate;
        break;
    }
}

if (!is_array($invoice)) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Advanced Invoice Not Found</title>
    </head>
    <body style="font-family: sans-serif; padding: 24px; text-align: center;">
        <h2>Advanced invoice not found</h2>
        <p><a href="?page=advanced-invoices">Back to advanced invoices</a></p>
    </body>
    </html>
    <?php
    exit;
}

$lineItems = is_array($invoice['lineItems'] ?? null) ? $invoice['lineItems'] : [];
$companyHeader = is_array($invoice['companyHeader'] ?? null) ? $invoice['companyHeader'] : [];
$customer = is_array($invoice['customer'] ?? null) ? $invoice['customer'] : [];
$paymentDetails = is_array($invoice['paymentDetails'] ?? null) ? $invoice['paymentDetails'] : [];
$currencyCode = (string)($invoice['currency'] ?? ($config['currency'] ?? 'ZAR'));
$currencySymbol = getCurrencySymbol($currencyCode);
$template = strtolower((string)($invoice['template'] ?? 'classic'));
$siteName = getSiteName() ?? 'LazyMan';
$csrfToken = $_SESSION['csrf_token'] ?? '';

$invoiceNumber = (string)($invoice['invoiceNumber'] ?? ('INV-' . substr($invoiceId, -6)));
$invoiceDate = !empty($invoice['invoiceDate']) ? date('M j, Y', strtotime((string)$invoice['invoiceDate'])) : '--';
$contractFrom = !empty($customer['contractFrom']) ? date('M j, Y', strtotime((string)$customer['contractFrom'])) : '';
$contractTo = !empty($customer['contractTo']) ? date('M j, Y', strtotime((string)$customer['contractTo'])) : '';
$totalDue = (float)($invoice['totalDue'] ?? 0);
$pdfDebug = (($_GET['pdfdebug'] ?? '') === '1');
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Advanced Invoice Preview - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: { extend: { fontFamily: { display: ["Inter", "sans-serif"] } } }
}
</script>
<style type="text/tailwindcss">
@layer base {
    body {
        @apply bg-zinc-100 text-black font-display antialiased;
    }
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.btn-outline {
    @apply border border-black px-4 py-2 text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-colors flex items-center gap-2;
}
@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
}
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden border-x border-zinc-200">
    <header class="no-print px-4 pt-4 pb-3 flex items-center justify-between border-b border-zinc-200 sticky top-0 bg-white z-20">
        <div class="flex items-center gap-3 min-w-0">
            <button onclick="history.back()" class="size-8 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1 class="text-[11px] font-black uppercase tracking-[0.14em] truncate">Invoice #<?= htmlspecialchars($invoiceNumber) ?></h1>
        </div>
    </header>

    <?php if ($invoiceDataWarning): ?>
        <section class="no-print mx-4 mt-3 p-3 border border-amber-300 bg-amber-50 text-amber-800 text-xs font-medium">
            Advanced invoice data may be incomplete for this session key.
        </section>
    <?php endif; ?>

    <div class="no-print px-4 py-3">
        <div class="flex p-1 border border-black bg-white">
            <button type="button" onclick="switchTemplate('classic')" class="flex-1 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] <?= $template === 'classic' ? 'bg-black text-white' : 'text-black' ?>">Classic</button>
            <button type="button" onclick="switchTemplate('modern')" class="flex-1 py-1.5 text-[9px] font-black uppercase tracking-[0.2em] <?= $template === 'modern' ? 'bg-black text-white' : 'text-black' ?>">Modern</button>
        </div>
    </div>

    <div class="no-print px-4 pb-3 flex gap-2">
        <button id="downloadAdvancedInvoiceBtn" type="button" class="btn-outline flex-1 justify-center">Download PDF</button>
        <a href="<?= APP_URL ?>?page=advanced-invoice-form&id=<?= urlencode($invoiceId) ?>&device=desktop" class="btn-outline flex-1 justify-center">Edit</a>
    </div>

    <main class="flex-1 overflow-y-auto no-scrollbar pb-8">
        <div class="px-4 space-y-6">
            <div class="flex justify-between items-start pt-2">
                <div>
                    <h2 class="text-xl font-black uppercase tracking-tight leading-none mb-2"><?= htmlspecialchars((string)($companyHeader['companyName'] ?? 'Company')) ?></h2>
                    <p class="text-[10px] text-zinc-500 uppercase tracking-widest leading-relaxed">
                        <?php if (!empty($companyHeader['companyAddress'])): ?><?= nl2br(htmlspecialchars((string)$companyHeader['companyAddress'])) ?><br/><?php endif; ?>
                        <?php if (!empty($companyHeader['companyEmail'])): ?><?= htmlspecialchars((string)$companyHeader['companyEmail']) ?><br/><?php endif; ?>
                        <?php if (!empty($companyHeader['companyPhone'])): ?><?= htmlspecialchars((string)$companyHeader['companyPhone']) ?><?php endif; ?>
                    </p>
                </div>
                <?php if (!empty($companyHeader['logoUrl'])): ?>
                    <img src="<?= htmlspecialchars((string)$companyHeader['logoUrl']) ?>" alt="Logo" class="size-12 object-contain border border-zinc-200 p-1">
                <?php else: ?>
                    <div class="size-12 bg-black flex items-center justify-center">
                        <span class="text-white font-black text-xl"><?= htmlspecialchars(strtoupper(substr((string)($companyHeader['companyName'] ?? 'A'), 0, 1))) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 gap-4 pt-2 border-t border-black/10">
                <?php if ($contractFrom !== '' || $contractTo !== ''): ?>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-2">Contract Period</h3>
                        <p class="text-[11px] font-bold uppercase tracking-widest">
                            <?= htmlspecialchars(trim(($contractFrom !== '' ? $contractFrom : '--') . ' to ' . ($contractTo !== '' ? $contractTo : '--'))) ?>
                        </p>
                    </div>
                <?php endif; ?>
                <div>
                    <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-2">Bill To</h3>
                    <p class="text-xs font-black uppercase mb-1"><?= htmlspecialchars((string)($customer['name'] ?? 'Unknown Customer')) ?></p>
                    <p class="text-[10px] text-zinc-500 uppercase tracking-widest leading-relaxed">
                        <?php if (!empty($customer['customerId'])): ?>ID: <?= htmlspecialchars((string)$customer['customerId']) ?><br/><?php endif; ?>
                        <?php if (!empty($customer['propertyAddress'])): ?><?= nl2br(htmlspecialchars((string)$customer['propertyAddress'])) ?><br/><?php endif; ?>
                        <?php if (!empty($customer['email'])): ?><?= htmlspecialchars((string)$customer['email']) ?><br/><?php endif; ?>
                        <?php if (!empty($customer['phone'])): ?><?= htmlspecialchars((string)$customer['phone']) ?><?php endif; ?>
                    </p>
                </div>
                <div>
                    <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-2">Invoice Details</h3>
                    <p class="text-[11px] font-bold uppercase tracking-widest">#<?= htmlspecialchars($invoiceNumber) ?></p>
                    <p class="text-[10px] text-zinc-500 uppercase tracking-widest mt-1">Issued: <?= htmlspecialchars($invoiceDate) ?></p>
                </div>
            </div>

            <div class="pt-1">
                <h3 class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-3 pb-2 border-b border-black">Activity Summary</h3>
                <?php if (empty($lineItems)): ?>
                    <p class="text-sm text-zinc-500">No line items available.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($lineItems as $item): ?>
                            <?php $amount = (float)($item['amount'] ?? 0); ?>
                            <div class="flex justify-between items-center gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-tight truncate"><?= htmlspecialchars((string)($item['description'] ?? 'Activity')) ?></p>
                                    <p class="text-[9px] text-zinc-400 uppercase tracking-widest mt-1">
                                        <?php if (!empty($item['date'])): ?><?= htmlspecialchars(date('M j, Y', strtotime((string)$item['date']))) ?><?php endif; ?>
                                        <?php if (!empty($item['reference'])): ?><?= !empty($item['date']) ? ' - ' : '' ?><?= htmlspecialchars((string)$item['reference']) ?><?php endif; ?>
                                    </p>
                                </div>
                                <span class="text-xs font-black"><?= $currencySymbol . number_format($amount, 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-black text-white p-5 mt-2">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-black uppercase tracking-[0.3em]">Balance Due</span>
                    <span class="text-2xl font-black"><?= $currencySymbol . number_format($totalDue, 2) ?></span>
                </div>
            </div>

            <?php if (!empty($paymentDetails)): ?>
                <div class="p-4 border border-black border-dashed">
                    <h3 class="text-[9px] font-black uppercase tracking-[0.2em] mb-3">Payment Details</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <?php if (!empty($paymentDetails['bankName'])): ?>
                            <div>
                                <p class="text-[8px] text-zinc-400 uppercase tracking-widest">Bank</p>
                                <p class="text-[10px] font-bold"><?= htmlspecialchars((string)$paymentDetails['bankName']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($paymentDetails['accountNumber'])): ?>
                            <div>
                                <p class="text-[8px] text-zinc-400 uppercase tracking-widest">Account</p>
                                <p class="text-[10px] font-bold"><?= htmlspecialchars((string)$paymentDetails['accountNumber']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($paymentDetails['branchCode'])): ?>
                            <div>
                                <p class="text-[8px] text-zinc-400 uppercase tracking-widest">Branch</p>
                                <p class="text-[10px] font-bold"><?= htmlspecialchars((string)$paymentDetails['branchCode']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($paymentDetails['paymentReference'])): ?>
                            <div class="col-span-2">
                                <p class="text-[8px] text-zinc-400 uppercase tracking-widest">Reference</p>
                                <p class="text-[10px] font-bold"><?= htmlspecialchars((string)$paymentDetails['paymentReference']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($paymentDetails['dueDate'])): ?>
                            <div class="col-span-2">
                                <p class="text-[8px] text-zinc-400 uppercase tracking-widest">Due Date</p>
                                <p class="text-[10px] font-bold"><?= htmlspecialchars(date('M j, Y', strtotime((string)$paymentDetails['dueDate']))) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($invoice['notes'])): ?>
                <div>
                    <h3 class="text-[9px] font-black uppercase tracking-[0.2em] mb-2">Notes</h3>
                    <p class="text-[10px] text-zinc-500 uppercase tracking-widest leading-relaxed"><?= nl2br(htmlspecialchars((string)$invoice['notes'])) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($invoice['footerText'])): ?>
                <div class="text-center pb-6">
                    <p class="text-[11px] font-black uppercase tracking-[0.3em]">Thank You</p>
                    <p class="text-[9px] text-zinc-500 uppercase tracking-widest mt-1"><?= nl2br(htmlspecialchars((string)$invoice['footerText'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
const INVOICE_ID = <?= json_encode($invoiceId) ?>;
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const advancedInvoiceDownloadButton = document.getElementById('downloadAdvancedInvoiceBtn');

async function switchTemplate(template) {
    try {
        const response = await fetch(`api/advanced-invoices.php?id=${encodeURIComponent(INVOICE_ID)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                template: template,
                csrf_token: CSRF_TOKEN
            })
        });

        if (!response.ok) {
            throw new Error('Template update failed');
        }

        window.location.reload();
    } catch (error) {
        alert('Failed to switch template. Please try again.');
    }
}

window.addEventListener('load', () => {
    if (advancedInvoiceDownloadButton) {
        advancedInvoiceDownloadButton.addEventListener('click', () => {
            window.open('?page=print-invoice&type=advanced&id=' + encodeURIComponent(INVOICE_ID), '_blank');
        });
    }
});
</script>
</body>
</html>
