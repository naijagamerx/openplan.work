<?php
/**
 * Mobile Finance Page - Sample UI/UX aligned
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
        <p><a href="?page=login">Return to login</a></p>
    </body></html>');
}

$invoices = $db->load('invoices') ?? [];
$finance = $db->load('finance') ?? [];
$config = $db->load('config') ?? [];

$transactionFilter = strtolower(trim((string)($_GET['filter'] ?? 'all')));

// Revenue = paid invoices + finance income
$invoiceRevenue = array_sum(array_map(static function ($i) {
    return (($i['status'] ?? '') === 'paid') ? (float)($i['total'] ?? 0) : 0;
}, $invoices));
$financeIncome = array_sum(array_map(static function ($f) {
    return (($f['type'] ?? 'expense') === 'income') ? (float)($f['amount'] ?? 0) : 0;
}, $finance));
$totalRevenue = $invoiceRevenue + $financeIncome;

// Expenses = finance expenses only
$totalExpenses = array_sum(array_map(static function ($f) {
    return (($f['type'] ?? 'expense') === 'expense') ? (float)($f['amount'] ?? 0) : 0;
}, $finance));
$netProfit = $totalRevenue - $totalExpenses;

// Build last 6 months chart buckets
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i months"));
    $monthlyData[$monthKey] = ['income' => 0.0, 'expenses' => 0.0];
}

foreach ($invoices as $invoice) {
    if (($invoice['status'] ?? '') !== 'paid') {
        continue;
    }
    $monthKey = date('Y-m', strtotime((string)($invoice['createdAt'] ?? 'now')));
    if (isset($monthlyData[$monthKey])) {
        $monthlyData[$monthKey]['income'] += (float)($invoice['total'] ?? 0);
    }
}

foreach ($finance as $row) {
    $monthKey = date('Y-m', strtotime((string)($row['date'] ?? $row['createdAt'] ?? 'now')));
    if (!isset($monthlyData[$monthKey])) {
        continue;
    }
    if (($row['type'] ?? 'expense') === 'income') {
        $monthlyData[$monthKey]['income'] += (float)($row['amount'] ?? 0);
    } else {
        $monthlyData[$monthKey]['expenses'] += (float)($row['amount'] ?? 0);
    }
}

// Net values for pulse line
$netValues = array_map(static function ($bucket) {
    return (float)$bucket['income'] - (float)$bucket['expenses'];
}, $monthlyData);
$minVal = min($netValues ?: [0]);
$maxVal = max($netValues ?: [0]);
$range = max(1, $maxVal - $minVal);
$count = max(1, count($netValues));
$polyPoints = [];
foreach (array_values($netValues) as $index => $value) {
    $x = ($count <= 1) ? 0 : (100 * $index / ($count - 1));
    $y = 90 - ((($value - $minVal) / $range) * 80);
    $polyPoints[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
}
$polyline = implode(' ', $polyPoints);

usort($finance, static function ($a, $b) {
    $dateA = (string)($a['date'] ?? $a['createdAt'] ?? '');
    $dateB = (string)($b['date'] ?? $b['createdAt'] ?? '');
    return strcmp($dateB, $dateA);
});

$filteredFinance = array_values(array_filter($finance, static function ($row) use ($transactionFilter) {
    if ($transactionFilter === 'all') {
        return true;
    }
    return strtolower((string)($row['type'] ?? 'expense')) === $transactionFilter;
}));

$currency = (string)($config['currency'] ?? 'USD');
$currencySymbol = getCurrencySymbol($currency);
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Finance - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            fontFamily: { display: ["Inter", "sans-serif"] }
        }
    }
}
</script>
<style type="text/tailwindcss">
@layer base {
    body {
        @apply bg-zinc-100 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
    }
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.tx-card { @apply p-4 border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 transition-colors; }
.tx-card:active { @apply bg-zinc-50 dark:bg-zinc-800; }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-zinc-200 dark:border-zinc-800 overflow-hidden">
<?php
$title = 'Finance';
$leftAction = 'menu';
$rightAction = 'search';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32 px-4">
    <section class="space-y-3 mt-2">
        <div class="p-5 border-2 border-black dark:border-white bg-black dark:bg-white text-white dark:text-black">
            <span class="text-[10px] font-bold uppercase tracking-[0.2em] opacity-60">Total Revenue</span>
            <span class="text-3xl font-black tracking-tight mt-1 block"><?= $currencySymbol . number_format($totalRevenue, 2) ?></span>
        </div>
        <div class="p-5 border-2 border-black dark:border-white bg-white dark:bg-zinc-900">
            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-400">Total Expenses</span>
            <span class="text-3xl font-black tracking-tight mt-1 block"><?= $currencySymbol . number_format($totalExpenses, 2) ?></span>
        </div>
        <div class="p-5 border-2 border-black dark:border-white bg-white dark:bg-zinc-900">
            <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-zinc-400">Net Profit</span>
            <span class="text-3xl font-black tracking-tight mt-1 block <?= $netProfit < 0 ? 'text-red-600' : '' ?>"><?= $currencySymbol . number_format($netProfit, 2) ?></span>
        </div>
    </section>

    <section class="mt-7">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Financial Pulse (6 Months)</h3>
            <span class="text-[10px] font-bold text-zinc-400"><?= date('M', strtotime('-5 months')) ?> - <?= date('M') ?></span>
        </div>
        <div class="w-full h-32 border border-black dark:border-white p-3 relative overflow-hidden bg-white dark:bg-zinc-900">
            <svg class="absolute inset-0 w-full h-full p-3" preserveAspectRatio="none" viewBox="0 0 100 100" aria-hidden="true">
                <polyline points="<?= htmlspecialchars($polyline) ?>" fill="none" stroke="currentColor" stroke-width="2" class="text-black dark:text-white"></polyline>
            </svg>
            <div class="relative h-full grid grid-cols-6 gap-0">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <div class="<?= $i < 5 ? 'border-r border-zinc-100 dark:border-zinc-800' : '' ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <section class="mt-6">
        <div class="flex gap-2 overflow-x-auto no-scrollbar pb-2">
            <a href="?page=finance&filter=all" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest whitespace-nowrap border <?= $transactionFilter === 'all' ? 'bg-black dark:bg-white text-white dark:text-black border-black dark:border-white' : 'border-zinc-200 dark:border-zinc-700' ?>">All</a>
            <a href="?page=finance&filter=income" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest whitespace-nowrap border <?= $transactionFilter === 'income' ? 'bg-black dark:bg-white text-white dark:text-black border-black dark:border-white' : 'border-zinc-200 dark:border-zinc-700' ?>">Income</a>
            <a href="?page=finance&filter=expense" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest whitespace-nowrap border <?= $transactionFilter === 'expense' ? 'bg-black dark:bg-white text-white dark:text-black border-black dark:border-white' : 'border-zinc-200 dark:border-zinc-700' ?>">Expenses</a>
            <a href="?page=transaction-form" class="px-4 py-2 text-[10px] font-bold uppercase tracking-widest whitespace-nowrap border border-zinc-200 dark:border-zinc-700">New</a>
        </div>
    </section>

    <section class="mt-4 mb-12 space-y-3">
        <?php if (empty($filteredFinance)): ?>
            <div class="tx-card text-center py-8">
                <p class="text-sm text-zinc-500">No transactions for this filter.</p>
            </div>
        <?php else: ?>
            <?php foreach (array_slice($filteredFinance, 0, 40) as $index => $row): ?>
                <?php
                $id = (string)($row['id'] ?? '');
                $isIncome = strtolower((string)($row['type'] ?? 'expense')) === 'income';
                $amount = (float)($row['amount'] ?? 0);
                $desc = (string)($row['description'] ?? 'Untitled');
                $category = (string)($row['category'] ?? 'Uncategorized');
                $dateLabel = !empty($row['date']) ? date('M j', strtotime((string)$row['date'])) : (!empty($row['createdAt']) ? date('M j', strtotime((string)$row['createdAt'])) : '--');
                ?>
                <a href="?page=transaction-form&id=<?= urlencode($id) ?>" class="tx-card block">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[9px] font-bold text-zinc-400">#<?= str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT) ?></span>
                        <span class="text-xs font-black <?= $isIncome ? 'text-green-600' : 'text-red-600' ?>">
                            <?= $isIncome ? '+' : '-' ?> <?= $currencySymbol . number_format($amount, 2) ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-end gap-3">
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold uppercase tracking-wider truncate"><?= htmlspecialchars($desc) ?></h4>
                            <p class="text-[9px] uppercase tracking-widest text-zinc-400 mt-0.5 truncate"><?= htmlspecialchars($category) ?> - <?= htmlspecialchars($dateLabel) ?></p>
                        </div>
                        <div class="size-6 bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center shrink-0">
                            <?php if ($isIncome): ?>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17L17 7m0 0H9m8 0v8"/></svg>
                            <?php else: ?>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17L7 7m0 0h8M7 7v8"/></svg>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php';
?>

<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-zinc-200 rounded-full z-40"></div>
</div>

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
</body>
</html>
