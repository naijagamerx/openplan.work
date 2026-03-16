<?php
/**
 * Mobile Invoices - High Contrast V2
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
    </body></html>');
}

$invoices = $db->load('invoices') ?? [];
$clients = $db->load('clients') ?? [];
$config = $db->load('config') ?? [];
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));

$clientLookup = [];
foreach ($clients as $client) {
    $clientLookup[(string)($client['id'] ?? '')] = $client['name'] ?? 'Unknown';
}

usort($invoices, function ($a, $b) {
    return strtotime($b['createdAt'] ?? 'now') <=> strtotime($a['createdAt'] ?? 'now');
});

$allCount = count($invoices);
$paidCount = count(array_filter($invoices, fn($i) => ($i['status'] ?? '') === 'paid'));
$sentCount = count(array_filter($invoices, fn($i) => ($i['status'] ?? '') === 'sent'));
$draftCount = count(array_filter($invoices, fn($i) => ($i['status'] ?? '') === 'draft'));
$overdueCount = count(array_filter($invoices, fn($i) => ($i['status'] ?? '') === 'overdue'));

$outstandingTotal = array_sum(array_map(function ($invoice) {
    $status = strtolower((string)($invoice['status'] ?? 'draft'));
    if ($status === 'paid' || $status === 'cancelled') {
        return 0;
    }
    return (float)($invoice['total'] ?? 0);
}, $invoices));

if ($statusFilter !== 'all') {
    $invoices = array_values(array_filter($invoices, function ($invoice) use ($statusFilter) {
        return strtolower((string)($invoice['status'] ?? 'draft')) === $statusFilter;
    }));
}

function statusBadgeClassMobileInvoice(string $status): string {
    return match ($status) {
        'paid' => 'text-green-600',
        'overdue' => 'text-red-600',
        'sent' => 'text-zinc-700 dark:text-zinc-300',
        'draft' => 'text-zinc-400',
        default => 'text-zinc-500',
    };
}

function statusLabelMobileInvoice(string $status): string {
    return match ($status) {
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'sent' => 'Pending',
        'draft' => 'Draft',
        'cancelled' => 'Cancelled',
        default => ucfirst($status ?: 'Draft'),
    };
}

$currency = $config['currency'] ?? 'USD';
$currencySymbol = getCurrencySymbol($currency);
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Invoices - <?= htmlspecialchars($siteName) ?></title>

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
        @apply bg-zinc-50 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
    }
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.invoice-row:active { @apply bg-zinc-50 dark:bg-zinc-900; }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden border-x border-zinc-200 dark:border-zinc-800">
<?php
$title = 'Invoices';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=invoice-form';
$rightIsLink = true;
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<section class="px-4 py-4 border-b border-black dark:border-white">
    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Total Outstanding</p>
    <h1 class="text-3xl font-black tracking-tight mt-1"><?= $currencySymbol . number_format($outstandingTotal, 2) ?></h1>

    <div class="mt-4 grid grid-cols-3 gap-2 text-[9px] font-black uppercase tracking-widest">
        <a href="?page=invoices&status=sent" class="border border-black dark:border-white p-2 text-center <?= $statusFilter === 'sent' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Pending <?= $sentCount ?></a>
        <a href="?page=invoices&status=paid" class="border border-black dark:border-white p-2 text-center <?= $statusFilter === 'paid' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Paid <?= $paidCount ?></a>
        <a href="?page=invoices&status=draft" class="border border-black dark:border-white p-2 text-center <?= $statusFilter === 'draft' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Draft <?= $draftCount ?></a>
    </div>

    <div class="mt-2 flex gap-2 text-[9px] font-black uppercase tracking-widest">
        <a href="?page=invoices&status=all" class="flex-1 border border-black dark:border-white p-2 text-center <?= $statusFilter === 'all' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">All <?= $allCount ?></a>
        <a href="?page=invoices&status=overdue" class="flex-1 border border-black dark:border-white p-2 text-center <?= $statusFilter === 'overdue' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Overdue <?= $overdueCount ?></a>
        <a href="?page=advanced-invoices" class="flex-1 border border-black dark:border-white p-2 text-center">Advanced</a>
    </div>
</section>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <?php if (empty($invoices)): ?>
        <div class="px-6 py-14 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">No invoices</p>
            <a href="?page=invoice-form" class="mt-4 inline-block px-4 py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest">Create Invoice</a>
        </div>
    <?php else: ?>
        <div class="px-4 py-3 flex justify-between bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 text-[9px] font-black uppercase tracking-widest">
            <span>Customer / Ref</span>
            <span>Amount</span>
        </div>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <?php foreach ($invoices as $invoice): ?>
                <?php
                $status = strtolower((string)($invoice['status'] ?? 'draft'));
                $clientName = $clientLookup[(string)($invoice['clientId'] ?? '')] ?? ($invoice['client'] ?? 'Unknown Client');
                $invoiceNumber = (string)($invoice['invoiceNumber'] ?? ('INV-' . substr((string)($invoice['id'] ?? ''), -6)));
                $dueDateRaw = $invoice['dueDate'] ?? null;
                $dueText = '';
                if (!empty($dueDateRaw)) {
                    $dueText = date('M j, Y', strtotime((string)$dueDateRaw));
                }
                $amount = (float)($invoice['total'] ?? 0);
                ?>
                <div class="invoice-row px-4 py-3 cursor-pointer" onclick="window.location.href='?page=invoice-view&id=<?= urlencode((string)($invoice['id'] ?? '')) ?>'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold tracking-tight truncate"><?= htmlspecialchars($clientName) ?></p>
                            <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-widest mt-1"><?= htmlspecialchars($invoiceNumber) ?></p>
                            <?php if ($dueText): ?>
                                <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-widest mt-1">Due <?= htmlspecialchars($dueText) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-black"><?= $currencySymbol . number_format($amount, 2) ?></p>
                            <p class="text-[9px] font-black uppercase tracking-widest mt-1 <?= statusBadgeClassMobileInvoice($status) ?>">
                                <?= statusLabelMobileInvoice($status) ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-end gap-2">
                        <a href="?page=invoice-view&id=<?= urlencode((string)($invoice['id'] ?? '')) ?>" class="px-3 py-0.5 text-[9px] font-black uppercase tracking-widest border border-black dark:border-white" onclick="event.stopPropagation()">View</a>
                        <a href="?page=invoice-form&id=<?= urlencode((string)($invoice['id'] ?? '')) ?>" class="px-3 py-0.5 text-[9px] font-black uppercase tracking-widest bg-black dark:bg-white text-white dark:text-black" onclick="event.stopPropagation()">Edit</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php
$activePage = 'dashboard';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php';
?>

<script>
const APP_URL = '<?= APP_URL ?>';
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});
</script>
</div>
</body>
</html>
