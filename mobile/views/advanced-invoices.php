<?php
/**
 * Mobile Advanced Invoices
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

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
$configResult = method_exists($db, 'safeLoad') ? $db->safeLoad('config') : ['success' => true, 'data' => $db->load('config')];
$invoiceResult = method_exists($db, 'safeLoad') ? $db->safeLoad('advanced_invoices') : ['success' => true, 'data' => $db->load('advanced_invoices')];
$config = is_array($configResult['data'] ?? null) ? $configResult['data'] : [];
$invoices = is_array($invoiceResult['data'] ?? null) ? $invoiceResult['data'] : [];
$invoiceDataWarning = !($invoiceResult['success'] ?? true);

usort($invoices, function ($a, $b) {
    return strtotime($b['createdAt'] ?? 'now') <=> strtotime($a['createdAt'] ?? 'now');
});

$allCount = count($invoices);
$paidCount = count(array_filter($invoices, fn($i) => strtolower((string)($i['status'] ?? 'unpaid')) === 'paid'));
$unpaidCount = count(array_filter($invoices, fn($i) => strtolower((string)($i['status'] ?? 'unpaid')) === 'unpaid'));
$draftCount = count(array_filter($invoices, fn($i) => strtolower((string)($i['status'] ?? 'unpaid')) === 'draft'));

$outstanding = array_sum(array_map(function ($invoice) {
    $status = strtolower((string)($invoice['status'] ?? 'unpaid'));
    if ($status === 'paid') {
        return 0;
    }
    return (float)($invoice['totalDue'] ?? 0);
}, $invoices));

if ($statusFilter !== 'all') {
    $invoices = array_values(array_filter($invoices, function ($invoice) use ($statusFilter) {
        return strtolower((string)($invoice['status'] ?? 'unpaid')) === $statusFilter;
    }));
}

$currencySymbol = getCurrencySymbol($config['currency'] ?? 'ZAR');
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Advanced Invoices - <?= htmlspecialchars($siteName) ?></title>

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
$title = 'Advanced Invoices';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = APP_URL . '?page=advanced-invoice-form&device=desktop';
$rightIsLink = true;
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<?php if ($invoiceDataWarning): ?>
    <section class="mx-4 mt-3 p-3 border border-amber-300 bg-amber-50 text-amber-800 text-xs font-medium">
        Advanced invoice history is partially unavailable with the current session key.
    </section>
<?php endif; ?>

<section class="px-4 py-4 border-b border-black dark:border-white">
    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Total Outstanding</p>
    <h1 class="text-3xl font-black tracking-tight mt-1"><?= $currencySymbol . number_format($outstanding, 2) ?></h1>
    <div class="mt-4 grid grid-cols-3 gap-2 text-[9px] font-black uppercase tracking-widest">
        <a href="?page=advanced-invoices&status=unpaid" class="border border-black dark:border-white p-2 text-center <?= $statusFilter === 'unpaid' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Unpaid <?= $unpaidCount ?></a>
        <a href="?page=advanced-invoices&status=paid" class="border border-black dark:border-white p-2 text-center <?= $statusFilter === 'paid' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Paid <?= $paidCount ?></a>
        <a href="?page=advanced-invoices&status=draft" class="border border-black dark:border-white p-2 text-center <?= $statusFilter === 'draft' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">Draft <?= $draftCount ?></a>
    </div>
    <div class="mt-2">
        <a href="?page=advanced-invoices&status=all" class="block border border-black dark:border-white p-2 text-center text-[9px] font-black uppercase tracking-widest <?= $statusFilter === 'all' ? 'bg-black dark:bg-white text-white dark:text-black' : '' ?>">All <?= $allCount ?></a>
    </div>
</section>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <?php if (empty($invoices)): ?>
        <div class="px-6 py-14 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">No advanced invoices</p>
            <a href="<?= APP_URL ?>?page=advanced-invoice-form&device=desktop" class="mt-4 inline-block px-4 py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest">New Advanced Invoice</a>
        </div>
    <?php else: ?>
        <div class="px-4 py-3 flex justify-between bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 text-[9px] font-black uppercase tracking-widest">
            <span>Customer / Ref</span>
            <span>Amount</span>
        </div>
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            <?php foreach ($invoices as $invoice): ?>
                <?php
                $invoiceId = (string)($invoice['id'] ?? '');
                $customerName = $invoice['customer']['name'] ?? 'Unknown';
                $invoiceNumber = (string)($invoice['invoiceNumber'] ?? ('INV-' . substr($invoiceId, -6)));
                $invoiceDate = !empty($invoice['invoiceDate']) ? date('M j, Y', strtotime((string)$invoice['invoiceDate'])) : '';
                $dueDate = $invoice['paymentDetails']['dueDate'] ?? null;
                $amount = (float)($invoice['totalDue'] ?? 0);
                $status = strtolower((string)($invoice['status'] ?? 'unpaid'));

                $dueText = '';
                if (!empty($dueDate)) {
                    $dueTs = strtotime((string)$dueDate);
                    $todayTs = strtotime(date('Y-m-d'));
                    $diff = (int)floor(($dueTs - $todayTs) / 86400);
                    if ($diff < 0) {
                        $dueText = 'Overdue';
                    } elseif ($diff === 0) {
                        $dueText = 'Due Today';
                    } elseif ($diff <= 7) {
                        $dueText = 'Due in ' . $diff . ' day' . ($diff === 1 ? '' : 's');
                    } else {
                        $dueText = 'Due ' . date('M j', $dueTs);
                    }
                }
                ?>
                <div class="invoice-row px-4 py-3 cursor-pointer" onclick="window.location.href='?page=advanced-invoice-view&id=<?= urlencode($invoiceId) ?>'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-bold tracking-tight truncate"><?= htmlspecialchars($customerName) ?></p>
                            <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-widest mt-1"><?= htmlspecialchars($invoiceNumber) ?></p>
                            <?php if (!empty($invoiceDate)): ?>
                                <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-widest mt-1"><?= htmlspecialchars($invoiceDate) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($dueText)): ?>
                                <p class="text-[10px] font-bold uppercase tracking-widest mt-1 <?= str_contains(strtolower($dueText), 'overdue') ? 'text-red-600' : 'text-zinc-400' ?>">
                                    <?= htmlspecialchars($dueText) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-black <?= $status === 'overdue' ? 'text-red-600' : '' ?>"><?= $currencySymbol . number_format($amount, 2) ?></p>
                            <p class="text-[9px] font-black uppercase tracking-widest mt-1 <?= $status === 'paid' ? 'text-green-600' : ($status === 'draft' ? 'text-zinc-400' : 'text-zinc-700 dark:text-zinc-300') ?>">
                                <?= htmlspecialchars(ucfirst($status)) ?>
                            </p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-end gap-2">
                        <a href="?page=advanced-invoice-view&id=<?= urlencode($invoiceId) ?>" class="px-3 py-0.5 text-[9px] font-black uppercase tracking-widest border border-black dark:border-white" onclick="event.stopPropagation()">View</a>
                        <a href="<?= APP_URL ?>?page=advanced-invoice-form&id=<?= urlencode($invoiceId) ?>&device=desktop" class="px-3 py-0.5 text-[9px] font-black uppercase tracking-widest bg-black dark:bg-white text-white dark:text-black" onclick="event.stopPropagation()">Edit</a>
                        <button onclick="event.stopPropagation(); deleteAdvancedInvoice('<?= htmlspecialchars($invoiceId, ENT_QUOTES) ?>')" class="px-3 py-0.5 text-[9px] font-black uppercase tracking-widest border border-red-400 text-red-600">Delete</button>
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

function deleteAdvancedInvoice(id) {
    Mobile.ui.confirmAction('Delete this advanced invoice?', async () => {
        try {
            const response = await App.api.delete(`api/advanced-invoices.php?id=${encodeURIComponent(id)}`);
            if (response && response.success) {
                Mobile.ui.showToast('Invoice deleted', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                Mobile.ui.showToast(response?.error || 'Failed to delete', 'error');
            }
        } catch (error) {
            Mobile.ui.showToast('Failed to delete', 'error');
        }
    });
}
</script>
</div>
</body>
</html>
