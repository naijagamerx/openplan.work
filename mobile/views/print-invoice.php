<?php
/**
 * Mobile Print Invoice View
 * Supports: Standard, Advanced (Classic & Modern)
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('Session error');
}

try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('Database error');
}

$invoiceId = trim((string)($_GET['id'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'standard')); // 'standard' or 'advanced'

if ($invoiceId === '') {
    die('Invalid invoice ID');
}

$configResult = method_exists($db, 'safeLoad') ? $db->safeLoad('config') : ['success' => true, 'data' => $db->load('config')];
$config = is_array($configResult['data'] ?? null) ? $configResult['data'] : [];
$siteName = getSiteName() ?? 'LazyMan';

// --- Data Fetching Logic ---

if ($type === 'advanced') {
    $invoiceResult = method_exists($db, 'safeLoad') ? $db->safeLoad('advanced_invoices') : ['success' => true, 'data' => $db->load('advanced_invoices')];
    $invoices = is_array($invoiceResult['data'] ?? null) ? $invoiceResult['data'] : [];
    
    $invoice = null;
    foreach ($invoices as $candidate) {
        if ((string)($candidate['id'] ?? '') === $invoiceId) {
            $invoice = $candidate;
            break;
        }
    }
    
    if (!$invoice) die('Invoice not found');
    
    // Advanced Data Preparation
    $lineItems = is_array($invoice['lineItems'] ?? null) ? $invoice['lineItems'] : [];
    $companyHeader = is_array($invoice['companyHeader'] ?? null) ? $invoice['companyHeader'] : [];
    $customer = is_array($invoice['customer'] ?? null) ? $invoice['customer'] : [];
    $paymentDetails = is_array($invoice['paymentDetails'] ?? null) ? $invoice['paymentDetails'] : [];
    $currencyCode = (string)($invoice['currency'] ?? ($config['currency'] ?? 'ZAR'));
    $currencySymbol = getCurrencySymbol($currencyCode);
    $template = strtolower((string)($invoice['template'] ?? 'classic'));
    
    $invoiceNumber = (string)($invoice['invoiceNumber'] ?? ('INV-' . substr($invoiceId, -6)));
    $invoiceDate = !empty($invoice['invoiceDate']) ? date('M j, Y', strtotime((string)$invoice['invoiceDate'])) : '--';
    $contractFrom = !empty($customer['contractFrom']) ? date('M j, Y', strtotime((string)$customer['contractFrom'])) : '';
    $contractTo = !empty($customer['contractTo']) ? date('M j, Y', strtotime((string)$customer['contractTo'])) : '';
    $totalDue = (float)($invoice['totalDue'] ?? 0);
    
} else {
    // Standard Invoice
    $invoiceResult = method_exists($db, 'safeLoad') ? $db->safeLoad('invoices') : ['success' => true, 'data' => $db->load('invoices')];
    $invoices = is_array($invoiceResult['data'] ?? null) ? $invoiceResult['data'] : [];
    
    $invoice = null;
    foreach ($invoices as $candidate) {
        if ((string)($candidate['id'] ?? '') === $invoiceId) {
            $invoice = $candidate;
            break;
        }
    }
    
    if (!$invoice) die('Invoice not found');
    
    // Standard Data Preparation
    $clientResult = method_exists($db, 'safeLoad') ? $db->safeLoad('clients') : ['success' => true, 'data' => $db->load('clients')];
    $clients = is_array($clientResult['data'] ?? null) ? $clientResult['data'] : [];
    
    $clientId = (string)($invoice['clientId'] ?? '');
    $client = null;
    foreach ($clients as $c) {
        if ((string)($c['id'] ?? '') === $clientId) {
            $client = $c;
            break;
        }
    }
    
    $clientName = $client ? (string)($client['name'] ?? 'Unknown Client') : 'Unknown Client';
    $clientCompany = $client ? (string)($client['company'] ?? '') : '';
    $clientEmail = $client ? (string)($client['email'] ?? '') : '';
    $clientAddress = $client ? (string)($client['address'] ?? '') : '';
    $clientPhone = $client ? (string)($client['phone'] ?? '') : ''; // Add Phone for Standard
    $addressLines = array_filter(array_map('trim', explode("\n", $clientAddress)));
    
    $businessName = trim((string)($config['businessName'] ?? $config['company_name'] ?? 'My Business'));
    $rawBusinessAddress = $config['businessAddress'] ?? ($config['company_address'] ?? '');
    if (is_array($rawBusinessAddress)) {
        $addressParts = [];
        foreach (['street', 'city', 'state', 'zip', 'country'] as $key) {
            $part = trim((string)($rawBusinessAddress[$key] ?? ''));
            if ($part !== '') {
                $addressParts[] = $part;
            }
        }
        $businessAddress = implode("\n", $addressParts);
    } else {
        $businessAddress = trim((string)$rawBusinessAddress);
    }
    $businessEmail = trim((string)($config['businessEmail'] ?? $config['company_email'] ?? ''));
    $businessPhone = trim((string)($config['businessPhone'] ?? $config['company_phone'] ?? ''));
    
    $currencyCode = (string)($invoice['currency'] ?? ($config['currency'] ?? 'ZAR'));
    $currencySymbol = getCurrencySymbol($currencyCode);
    
    $issuedSource = $invoice['createdAt'] ?? ($invoice['issuedAt'] ?? null);
    $issuedAt = !empty($issuedSource) ? date('M j, Y', strtotime((string)$issuedSource)) : '--';
    $dueDate = !empty($invoice['dueDate']) ? date('M j, Y', strtotime((string)$invoice['dueDate'])) : '--';

    $rawLineItems = is_array($invoice['lineItems'] ?? null)
        ? $invoice['lineItems']
        : (is_array($invoice['items'] ?? null) ? $invoice['items'] : []);
    $lineItems = array_map(static function ($item) {
        $description = trim((string)($item['description'] ?? ''));
        $qty = (float)($item['quantity'] ?? 1);
        if ($qty <= 0) {
            $qty = 1;
        }
        $unitPrice = (float)($item['unitPrice'] ?? ($item['amount'] ?? 0));
        $total = isset($item['total'])
            ? (float)$item['total']
            : ($qty * $unitPrice);

        return [
            'description' => $description !== '' ? $description : 'Line Item',
            'quantity' => $qty,
            'unitPrice' => $unitPrice,
            'total' => $total
        ];
    }, $rawLineItems);
    $subtotal = (float)($invoice['subtotal'] ?? 0);
    $taxRate = (float)($invoice['taxRate'] ?? 0);
    $taxAmount = (float)($invoice['taxAmount'] ?? 0);
    $total = (float)($invoice['total'] ?? 0);
    
    $template = 'standard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Invoice #<?= htmlspecialchars($invoiceNumber ?? $invoice['invoiceNumber'] ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        @media print {
            @page { margin: 0; size: auto; }
            body { padding: 20mm; }
        }
    </style>
    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</head>
<body class="bg-white text-black p-8 max-w-4xl mx-auto">

<?php if ($type === 'standard'): ?>
    <!-- Standard Invoice Layout -->
    <div class="border-b-4 border-black pb-6 mb-8 flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-black uppercase mb-4"><?= htmlspecialchars($businessName) ?></h1>
            <div class="text-xs font-bold uppercase text-gray-700 leading-relaxed">
                <?php if ($businessAddress): ?><div><?= nl2br(htmlspecialchars($businessAddress)) ?></div><?php endif; ?>
                <?php if ($businessEmail): ?><div><?= htmlspecialchars($businessEmail) ?></div><?php endif; ?>
                <?php if ($businessPhone): ?><div><?= htmlspecialchars($businessPhone) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Invoice Number</div>
            <div class="text-2xl font-black mb-6">#<?= htmlspecialchars((string)($invoice['invoiceNumber'] ?? '')) ?></div>
            <div class="grid grid-cols-2 gap-8 text-left">
                <div>
                    <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Date Issued</div>
                    <div class="text-sm font-bold"><?= htmlspecialchars($issuedAt) ?></div>
                </div>
                <div>
                    <div class="text-[10px] font-black uppercase text-gray-400 mb-1">Due Date</div>
                    <div class="text-sm font-bold"><?= htmlspecialchars($dueDate) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-12">
        <div class="text-[10px] font-black uppercase text-gray-400 mb-4">Billed To</div>
        <div class="bg-gray-100 rounded-2xl p-6 w-full max-w-xs">
            <h3 class="text-xl font-black mb-1"><?= htmlspecialchars($clientName) ?></h3>
            <div class="text-sm font-bold text-gray-600 mb-4"><?= $clientCompany ? htmlspecialchars($clientCompany) : 'Private Individual' ?></div>
            <div class="text-[10px] font-bold uppercase text-gray-700 leading-relaxed">
                <?php if ($clientEmail): ?><div><?= htmlspecialchars($clientEmail) ?></div><?php endif; ?>
                <?php if ($clientPhone): ?><div><?= htmlspecialchars($clientPhone) ?></div><?php endif; ?>
                <?php foreach ($addressLines as $line): ?>
                    <div><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <table class="w-full border-collapse mb-12">
        <thead>
            <tr class="border-b border-gray-200">
                <th class="text-left py-4 text-[10px] font-black uppercase text-gray-400">Description</th>
                <th class="text-center py-4 text-[10px] font-black uppercase text-gray-400 w-20">Qty</th>
                <th class="text-right py-4 text-[10px] font-black uppercase text-gray-400 w-32">Unit Price</th>
                <th class="text-right py-4 text-[10px] font-black uppercase text-gray-400 w-32">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lineItems as $item): ?>
            <tr class="border-b border-gray-100">
                <td class="py-5 text-sm font-bold"><?= htmlspecialchars((string)($item['description'] ?? '')) ?></td>
                <td class="py-5 text-center text-sm font-bold text-gray-500"><?= htmlspecialchars((string)($item['quantity'] ?? '1')) ?></td>
                <td class="py-5 text-right text-sm font-bold text-gray-500"><?= $currencySymbol . number_format((float)($item['unitPrice'] ?? 0), 2) ?></td>
                <td class="py-5 text-right text-sm font-black"><?= $currencySymbol . number_format((float)($item['total'] ?? 0), 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="flex justify-end border-t-2 border-dashed border-gray-200 pt-8">
        <div class="w-80">
            <div class="flex justify-between mb-4">
                <span class="text-sm font-bold text-gray-400 uppercase">Subtotal</span>
                <span class="text-sm font-bold"><?= $currencySymbol . number_format($subtotal, 2) ?></span>
            </div>
            <div class="flex justify-between mb-4">
                <span class="text-sm font-bold text-gray-400 uppercase">Tax (<?= number_format($taxRate, 2) ?>%)</span>
                <span class="text-sm font-bold"><?= $currencySymbol . number_format($taxAmount, 2) ?></span>
            </div>
            <div class="bg-black text-white rounded-2xl p-5 flex justify-between items-center">
                <span class="text-[10px] font-black uppercase tracking-widest">Grand Total</span>
                <span class="text-2xl font-black"><?= $currencySymbol . number_format($total, 2) ?></span>
            </div>
        </div>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
    <div class="mt-16 pt-8 border-t border-gray-100">
        <div class="text-[10px] font-black uppercase text-gray-400 mb-4">Additional Information</div>
        <div class="text-xs text-gray-500 leading-relaxed bg-gray-50 p-6 rounded-2xl max-w-2xl">
            <?= nl2br(htmlspecialchars((string)$invoice['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

<?php elseif ($type === 'advanced' && $template === 'modern'): ?>
    <!-- Advanced Invoice MODERN Layout -->
    <div class="flex justify-between items-start mb-8">
        <div>
            <h1 class="text-4xl font-black uppercase bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 mb-2">
                <?= htmlspecialchars((string)($companyHeader['companyName'] ?? 'Company')) ?>
            </h1>
            <div class="text-[10px] font-bold text-gray-500 uppercase tracking-widest leading-relaxed">
                <?php if (!empty($companyHeader['companyAddress'])): ?><div><?= nl2br(htmlspecialchars((string)$companyHeader['companyAddress'])) ?></div><?php endif; ?>
                <?php if (!empty($companyHeader['companyEmail'])): ?><div><?= htmlspecialchars((string)$companyHeader['companyEmail']) ?></div><?php endif; ?>
                <?php if (!empty($companyHeader['companyPhone'])): ?><div><?= htmlspecialchars((string)$companyHeader['companyPhone']) ?></div><?php endif; ?>
            </div>
        </div>
        <?php if (!empty($companyHeader['logoUrl'])): ?>
            <img src="<?= htmlspecialchars((string)$companyHeader['logoUrl']) ?>" alt="Logo" class="h-16 object-contain">
        <?php else: ?>
            <div class="size-16 bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center rounded-xl shadow-lg text-white font-black text-2xl">
                <?= htmlspecialchars(strtoupper(substr((string)($companyHeader['companyName'] ?? 'A'), 0, 1))) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-12 gap-8 mb-12">
        <div class="col-span-8 bg-gray-50 rounded-2xl p-8">
            <div class="text-[10px] font-black uppercase text-blue-600 tracking-widest mb-4">Bill To</div>
            <h3 class="text-xl font-black mb-1"><?= htmlspecialchars((string)($customer['name'] ?? 'Unknown Customer')) ?></h3>
            <?php if (!empty($customer['customerId'])): ?><div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">ID: <?= htmlspecialchars((string)$customer['customerId']) ?></div><?php endif; ?>
            <div class="text-[11px] font-medium text-gray-600 leading-relaxed">
                <?php if (!empty($customer['propertyAddress'])): ?><div><?= nl2br(htmlspecialchars((string)$customer['propertyAddress'])) ?></div><?php endif; ?>
                <?php if (!empty($customer['email'])): ?><div><?= htmlspecialchars((string)$customer['email']) ?></div><?php endif; ?>
                <?php if (!empty($customer['phone'])): ?><div><?= htmlspecialchars((string)$customer['phone']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="col-span-4 space-y-4">
            <div class="bg-blue-600 text-white rounded-2xl p-6 text-center shadow-lg shadow-blue-200">
                <div class="text-[10px] font-black uppercase tracking-widest opacity-80 mb-1">Total Due</div>
                <div class="text-3xl font-black"><?= $currencySymbol . number_format($totalDue, 2) ?></div>
            </div>
            <div class="bg-gray-900 text-white rounded-2xl p-6 text-center">
                <div class="text-[10px] font-black uppercase tracking-widest opacity-60 mb-1">Invoice No</div>
                <div class="text-xl font-bold">#<?= htmlspecialchars($invoiceNumber) ?></div>
                <div class="text-[10px] font-black uppercase tracking-widest opacity-60 mt-4 mb-1">Date</div>
                <div class="text-sm font-bold"><?= htmlspecialchars($invoiceDate) ?></div>
            </div>
        </div>
    </div>

    <div class="mb-12">
        <div class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-4 px-4">Line Items</div>
        <table class="w-full">
            <thead>
                <tr class="text-left text-[10px] font-black uppercase text-gray-400 border-b border-gray-200">
                    <th class="px-4 py-3 w-32">Date</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($lineItems as $index => $item): ?>
                <tr class="<?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-50' ?>">
                    <td class="px-4 py-4 text-xs font-bold text-gray-500">
                        <?= !empty($item['date']) ? htmlspecialchars(date('M j, Y', strtotime((string)$item['date']))) : '' ?>
                    </td>
                    <td class="px-4 py-4">
                        <div class="text-sm font-bold text-gray-900"><?= htmlspecialchars((string)($item['description'] ?? '')) ?></div>
                        <?php if (!empty($item['reference'])): ?>
                        <div class="text-[10px] font-medium text-gray-400 mt-1 uppercase tracking-wider"><?= htmlspecialchars((string)$item['reference']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-right text-sm font-black text-gray-900">
                        <?= $currencySymbol . number_format((float)($item['amount'] ?? 0), 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-2 gap-12 border-t border-gray-200 pt-8">
        <div>
            <?php if (!empty($paymentDetails)): ?>
            <div class="mb-8">
                <div class="text-[10px] font-black uppercase text-gray-400 tracking-widest mb-4">Payment Info</div>
                <div class="space-y-2 text-xs">
                    <?php if (!empty($paymentDetails['bankName'])): ?>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Bank</span>
                            <span class="font-bold"><?= htmlspecialchars((string)$paymentDetails['bankName']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($paymentDetails['accountNumber'])): ?>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Account</span>
                            <span class="font-bold"><?= htmlspecialchars((string)$paymentDetails['accountNumber']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($paymentDetails['branchCode'])): ?>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Branch</span>
                            <span class="font-bold"><?= htmlspecialchars((string)$paymentDetails['branchCode']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($paymentDetails['paymentReference'])): ?>
                        <div class="flex justify-between border-b border-gray-100 pb-2">
                            <span class="text-gray-500">Ref</span>
                            <span class="font-bold"><?= htmlspecialchars((string)$paymentDetails['paymentReference']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div>
            <?php if (!empty($invoice['notes'])): ?>
            <div class="bg-amber-50 border-l-4 border-amber-400 p-6 rounded-r-xl">
                <div class="text-[10px] font-black uppercase text-amber-700 tracking-widest mb-2">Notes</div>
                <div class="text-xs text-amber-900 leading-relaxed"><?= nl2br(htmlspecialchars((string)$invoice['notes'])) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($invoice['footerText'])): ?>
            <div class="mt-8 text-center text-[10px] font-medium text-gray-400 uppercase tracking-widest">
                <?= nl2br(htmlspecialchars((string)$invoice['footerText'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- Advanced Invoice CLASSIC Layout -->
    <div class="flex items-start gap-4 border-b-2 border-black pb-6 mb-6">
        <?php if (!empty($companyHeader['logoUrl'])): ?>
            <img src="<?= htmlspecialchars((string)$companyHeader['logoUrl']) ?>" alt="Logo" class="h-12 w-12 object-contain border border-gray-200 p-1">
        <?php else: ?>
            <div class="h-12 w-12 bg-black flex items-center justify-center text-white font-black text-xl">
                <?= htmlspecialchars(strtoupper(substr((string)($companyHeader['companyName'] ?? 'A'), 0, 1))) ?>
            </div>
        <?php endif; ?>
        <div class="flex-1">
            <h1 class="text-xl font-black uppercase"><?= htmlspecialchars((string)($companyHeader['companyName'] ?? 'Company')) ?></h1>
            <div class="text-[10px] text-gray-600 leading-tight mt-1">
                <?php if (!empty($companyHeader['companyAddress'])): ?><div><?= nl2br(htmlspecialchars((string)$companyHeader['companyAddress'])) ?></div><?php endif; ?>
                <?php if (!empty($companyHeader['companyEmail'])): ?><div><?= htmlspecialchars((string)$companyHeader['companyEmail']) ?></div><?php endif; ?>
                <?php if (!empty($companyHeader['companyPhone'])): ?><div><?= htmlspecialchars((string)$companyHeader['companyPhone']) ?></div><?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <div class="text-[9px] font-bold uppercase text-gray-500">Invoice Number</div>
            <div class="text-lg font-black">#<?= htmlspecialchars($invoiceNumber) ?></div>
            <div class="text-[9px] font-bold uppercase text-gray-500 mt-2">Date</div>
            <div class="text-sm font-bold"><?= htmlspecialchars($invoiceDate) ?></div>
        </div>
    </div>

    <?php if ($contractFrom !== '' || $contractTo !== ''): ?>
    <div class="text-[10px] text-gray-600 mb-4">
        <strong>Contract Period:</strong> <?= htmlspecialchars(($contractFrom !== '' ? $contractFrom : '--') . ' to ' . ($contractTo !== '' ? $contractTo : '--')) ?>
    </div>
    <?php endif; ?>

    <div class="border-b border-gray-100 pb-6 mb-6">
        <div class="text-[9px] font-black uppercase text-gray-500 mb-2">Bill To</div>
        <div class="bg-gray-100 rounded-xl p-4 max-w-xs">
            <h3 class="text-base font-black mb-1"><?= htmlspecialchars((string)($customer['name'] ?? 'Unknown Customer')) ?></h3>
            <?php if (!empty($customer['customerId'])): ?><div class="text-[10px] text-gray-500 mb-2">ID: <?= htmlspecialchars((string)$customer['customerId']) ?></div><?php endif; ?>
            <div class="text-[10px] text-gray-700 leading-tight">
                <?php if (!empty($customer['propertyAddress'])): ?><div><?= nl2br(htmlspecialchars((string)$customer['propertyAddress'])) ?></div><?php endif; ?>
                <?php if (!empty($customer['email'])): ?><div><?= htmlspecialchars((string)$customer['email']) ?></div><?php endif; ?>
                <?php if (!empty($customer['phone'])): ?><div><?= htmlspecialchars((string)$customer['phone']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mb-8">
        <div class="text-[9px] font-black uppercase text-gray-500 mb-2">Activity Summary</div>
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="text-left py-2 text-[9px] font-bold uppercase text-gray-500">Date</th>
                    <th class="text-left py-2 text-[9px] font-bold uppercase text-gray-500">Description</th>
                    <th class="text-right py-2 text-[9px] font-bold uppercase text-gray-500">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lineItems as $item): ?>
                <tr class="border-b border-gray-50">
                    <td class="py-2 text-[11px] text-gray-700"><?= !empty($item['date']) ? htmlspecialchars(date('M j, Y', strtotime((string)$item['date']))) : '' ?></td>
                    <td class="py-2 text-[11px] font-semibold">
                        <?= htmlspecialchars((string)($item['description'] ?? '')) ?>
                        <?php if (!empty($item['reference'])): ?>
                            <span class="text-gray-400 ml-1">(<?= htmlspecialchars((string)$item['reference']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 text-[11px] font-bold text-right"><?= $currencySymbol . number_format((float)($item['amount'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-end border-t border-dashed border-gray-200 pt-4 mb-8">
        <div class="bg-black text-white rounded-xl p-4 min-w-[200px] flex justify-between items-center">
            <span class="text-[9px] font-black uppercase">Balance Due</span>
            <span class="text-xl font-black"><?= $currencySymbol . number_format($totalDue, 2) ?></span>
        </div>
    </div>

    <?php if (!empty($paymentDetails['bankName'])): ?>
    <div class="bg-gray-100 rounded-xl p-4 mb-6">
        <div class="text-[9px] font-black uppercase text-gray-500 mb-3">Payment Details</div>
        <div class="grid grid-cols-2 gap-3 text-[10px]">
            <?php if (!empty($paymentDetails['bankName'])): ?><div><span class="text-gray-500">Bank:</span> <strong><?= htmlspecialchars((string)$paymentDetails['bankName']) ?></strong></div><?php endif; ?>
            <?php if (!empty($paymentDetails['accountNumber'])): ?><div><span class="text-gray-500">Acc:</span> <strong><?= htmlspecialchars((string)$paymentDetails['accountNumber']) ?></strong></div><?php endif; ?>
            <?php if (!empty($paymentDetails['branchCode'])): ?><div><span class="text-gray-500">Branch:</span> <strong><?= htmlspecialchars((string)$paymentDetails['branchCode']) ?></strong></div><?php endif; ?>
            <?php if (!empty($paymentDetails['paymentReference'])): ?><div><span class="text-gray-500">Ref:</span> <strong><?= htmlspecialchars((string)$paymentDetails['paymentReference']) ?></strong></div><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($invoice['notes'])): ?>
    <div class="bg-amber-50 border-l-4 border-amber-400 rounded-r-xl p-4 mb-4">
        <div class="text-[9px] font-black uppercase text-amber-700 mb-1">Notes</div>
        <div class="text-[10px] text-amber-900 leading-relaxed"><?= nl2br(htmlspecialchars((string)$invoice['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($invoice['footerText'])): ?>
    <div class="text-center border-t border-gray-100 pt-4">
        <div class="text-[10px] font-black uppercase text-black">Thank You</div>
        <div class="text-[9px] text-gray-500 mt-1"><?= nl2br(htmlspecialchars((string)$invoice['footerText'])) ?></div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<div class="mt-12 text-center">
    <div class="text-[8px] font-black uppercase text-gray-300 tracking-[0.3em]">Generated with <?= htmlspecialchars($siteName) ?></div>
</div>

</body>
</html>
