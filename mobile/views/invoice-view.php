<?php
/**
 * Mobile Invoice Preview
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
        <p><a href="?page=invoices">Back to invoices</a></p>
    </body></html>');
}

$invoiceId = trim((string)($_GET['id'] ?? ''));
if ($invoiceId === '') {
    header('Location: ?page=invoices');
    exit;
}

$invoices = $db->load('invoices') ?? [];
$clients = $db->load('clients') ?? [];
$config = $db->load('config') ?? [];

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
        <title>Invoice Not Found</title>
    </head>
    <body style="font-family: sans-serif; padding: 24px; text-align: center;">
        <h2>Invoice not found</h2>
        <p><a href="?page=invoices">Back to invoices</a></p>
    </body>
    </html>
    <?php
    exit;
}

$client = null;
foreach ($clients as $candidate) {
    if ((string)($candidate['id'] ?? '') === (string)($invoice['clientId'] ?? '')) {
        $client = $candidate;
        break;
    }
}

$lineItems = is_array($invoice['lineItems'] ?? null) ? $invoice['lineItems'] : [];
$currencyCode = (string)($invoice['currency'] ?? ($config['currency'] ?? 'USD'));
$currencySymbol = getCurrencySymbol($currencyCode);
$businessName = trim((string)($config['businessName'] ?? getSiteName() ?? 'LazyMan Tools'));
$businessAddressLines = [];
$businessAddress = $config['businessAddress'] ?? '';
if (is_array($businessAddress)) {
    foreach (['street', 'city', 'state', 'zip', 'country'] as $addressKey) {
        $value = trim((string)($businessAddress[$addressKey] ?? ''));
        if ($value !== '') {
            $businessAddressLines[] = $value;
        }
    }
} elseif (is_string($businessAddress) && trim($businessAddress) !== '') {
    $parts = preg_split('/[\r\n,]+/', $businessAddress);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $businessAddressLines[] = $part;
        }
    }
}
$businessAddress = implode("\n", $businessAddressLines);
$businessEmail = trim((string)($config['businessEmail'] ?? ''));
$businessPhone = trim((string)($config['businessPhone'] ?? ''));
$siteName = getSiteName() ?? 'LazyMan';
$pdfDebug = (($_GET['pdfdebug'] ?? '') === '1');

$issuedAt = !empty($invoice['createdAt']) ? date('M j, Y', strtotime((string)$invoice['createdAt'])) : '--';
$dueDate = !empty($invoice['dueDate']) ? date('M j, Y', strtotime((string)$invoice['dueDate'])) : '--';
$subtotal = (float)($invoice['subtotal'] ?? 0);
$taxRate = (float)($invoice['taxRate'] ?? 0);
$taxAmount = (float)($invoice['taxAmount'] ?? 0);
$total = (float)($invoice['total'] ?? 0);
$status = ucfirst(strtolower((string)($invoice['status'] ?? 'draft')));

$clientName = trim((string)($client['name'] ?? 'Unknown Client'));
$clientCompany = trim((string)($client['company'] ?? ''));
$clientEmail = trim((string)($client['email'] ?? ''));

$addressLines = [];
$address = $client['address'] ?? null;
if (is_array($address)) {
    foreach (['street', 'city', 'state', 'zip', 'country'] as $addressKey) {
        $value = trim((string)($address[$addressKey] ?? ''));
        if ($value !== '') {
            $addressLines[] = $value;
        }
    }
} elseif (is_string($address) && trim($address) !== '') {
    $parts = preg_split('/[\r\n,]+/', $address);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $addressLines[] = $part;
        }
    }
}

if ($pdfDebug && !$client && !empty($invoice['clientId'])) {
    error_log('Mobile Invoice PDF Debug: Client not found for ID: ' . (string)$invoice['clientId']);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Preview Invoice - <?= htmlspecialchars($siteName) ?></title>

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
@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
}
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden border-x border-zinc-200">
    <header class="no-print px-4 pt-4 pb-3 border-b border-black flex items-center gap-3 sticky top-0 bg-white z-20">
        <button onclick="history.back()" class="size-9 flex items-center justify-center border border-black hover:bg-black hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <h1 class="text-[11px] font-black uppercase tracking-[0.16em] truncate">Preview Invoice #<?= htmlspecialchars((string)($invoice['invoiceNumber'] ?? '')) ?></h1>
    </header>

    <div class="no-print px-4 py-3 flex gap-2 border-b border-zinc-100">
        <button id="downloadInvoiceBtn" type="button" class="flex-1 bg-black text-white text-[10px] font-black uppercase tracking-[0.14em] py-3 px-2 border border-black hover:bg-white hover:text-black transition-colors">
            Download PDF
        </button>
        <a href="?page=invoice-form&id=<?= urlencode((string)($invoice['id'] ?? '')) ?>" class="flex-1 bg-black text-white text-[10px] font-black uppercase tracking-[0.14em] py-3 px-2 border border-black hover:bg-white hover:text-black transition-colors text-center">
            Edit Invoice
        </a>
    </div>

    <main class="flex-1 overflow-y-auto no-scrollbar pb-10">
        <div class="p-4">
            <div class="border border-zinc-200 bg-white p-5 space-y-6">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <h2 class="text-lg font-black uppercase tracking-tight"><?= htmlspecialchars($businessName) ?></h2>
                        <div class="text-[10px] text-zinc-500 uppercase leading-relaxed font-medium">
                            <?php if ($businessAddress !== ''): ?><p><?= nl2br(htmlspecialchars($businessAddress)) ?></p><?php endif; ?>
                            <?php if ($businessEmail !== ''): ?><p><?= htmlspecialchars($businessEmail) ?></p><?php endif; ?>
                            <?php if ($businessPhone !== ''): ?><p><?= htmlspecialchars($businessPhone) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <span class="inline-block px-2 py-1 bg-black text-white text-[8px] font-black uppercase tracking-widest"><?= htmlspecialchars($status) ?></span>
                </div>

                <div class="grid grid-cols-2 gap-5">
                    <div>
                        <h3 class="text-[8px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-1">Billed To</h3>
                        <div class="text-[10px] font-bold uppercase space-y-0.5">
                            <p><?= htmlspecialchars($clientName) ?></p>
                            <?php if ($clientCompany !== ''): ?><p class="font-normal text-zinc-500"><?= htmlspecialchars($clientCompany) ?></p><?php endif; ?>
                            <?php if ($clientEmail !== ''): ?><p class="font-normal text-zinc-500"><?= htmlspecialchars($clientEmail) ?></p><?php endif; ?>
                            <?php foreach ($addressLines as $line): ?>
                                <p class="font-normal text-zinc-500"><?= htmlspecialchars($line) ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <h3 class="text-[8px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-1">Invoice Details</h3>
                        <div class="text-[10px] font-bold uppercase space-y-0.5">
                            <p>#<?= htmlspecialchars((string)($invoice['invoiceNumber'] ?? '')) ?></p>
                            <p class="font-normal text-zinc-500">Issued: <?= htmlspecialchars($issuedAt) ?></p>
                            <p class="font-normal text-zinc-500">Due: <?= htmlspecialchars($dueDate) ?></p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    <div class="flex justify-between items-center border-b border-black pb-2">
                        <span class="text-[8px] font-black uppercase tracking-[0.2em]">Description</span>
                        <span class="text-[8px] font-black uppercase tracking-[0.2em]">Total</span>
                    </div>

                    <?php if (empty($lineItems)): ?>
                        <p class="text-sm text-zinc-500">No line items available.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($lineItems as $item): ?>
                                <?php
                                $qty = (float)($item['quantity'] ?? 0);
                                $unitPrice = (float)($item['unitPrice'] ?? 0);
                                $itemTotal = (float)($item['total'] ?? ($qty * $unitPrice));
                                ?>
                                <div class="flex justify-between items-start gap-3">
                                    <div class="max-w-[72%]">
                                        <p class="text-[11px] font-black uppercase tracking-tight"><?= htmlspecialchars((string)($item['description'] ?? 'Line Item')) ?></p>
                                        <p class="text-[10px] text-zinc-500 mt-1 uppercase">Qty: <?= rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') ?> x <?= $currencySymbol . number_format($unitPrice, 2) ?></p>
                                    </div>
                                    <span class="text-[11px] font-black"><?= $currencySymbol . number_format($itemTotal, 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="border-t border-black pt-4 space-y-2">
                        <div class="flex justify-between items-center text-[10px] font-bold uppercase text-zinc-500">
                            <span>Subtotal</span>
                            <span><?= $currencySymbol . number_format($subtotal, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center text-[10px] font-bold uppercase text-zinc-500">
                            <span>Tax (<?= number_format($taxRate, 2) ?>%)</span>
                            <span><?= $currencySymbol . number_format($taxAmount, 2) ?></span>
                        </div>
                        <div class="flex justify-between items-center bg-black text-white p-3 mt-4">
                            <span class="text-[10px] font-black uppercase tracking-widest">Amount Due</span>
                            <span class="text-lg font-black tracking-tight"><?= $currencySymbol . number_format($total, 2) ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($invoice['notes'])): ?>
                    <div class="pt-4 border-t border-zinc-100">
                        <h3 class="text-[8px] font-black uppercase tracking-[0.2em] mb-1">Payment Terms</h3>
                        <p class="text-[10px] text-zinc-500 uppercase leading-relaxed"><?= nl2br(htmlspecialchars((string)$invoice['notes'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="w-full bg-white h-8 flex justify-center items-center">
        <div class="w-12 h-1 bg-zinc-200 rounded-full"></div>
    </div>
</div>

<script>
    const MOBILE_INVOICE_ID = <?= json_encode((string)($invoice['id'] ?? '')) ?>;
    const invoiceDownloadButton = document.getElementById('downloadInvoiceBtn');

    // Simple initialization
    window.addEventListener('load', () => {
        if (invoiceDownloadButton) {
            invoiceDownloadButton.addEventListener('click', () => {
                window.open('?page=print-invoice&type=standard&id=' + encodeURIComponent(MOBILE_INVOICE_ID), '_blank');
            });
        }
    });
</script>
</body>
</html>
