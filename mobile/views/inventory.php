<?php
/**
 * Mobile Inventory Page
 *
 * Redesigned mobile inventory with PC feature parity:
 * - Product list/search
 * - Stock in/out adjustment
 * - Inline + full transaction history
 * - Inventory summary panel
 * - AI inventory generation and save
 * - Edit/delete actions
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

$inventory = $db->load('inventory');
$transactions = $db->load('inventory_transactions', true);
if (!is_array($transactions)) {
    $transactions = [];
}

$totalValue = 0.0;
$totalCost = 0.0;
$lowStockCount = 0;
$totalIn = 0;
$totalOut = 0;
$totalExpense = 0.0;

foreach ($inventory as $item) {
    $qty = (int)($item['quantity'] ?? $item['stock'] ?? 0);
    $price = (float)($item['price'] ?? $item['unitPrice'] ?? 0);
    $cost = (float)($item['costPrice'] ?? 0);
    $minStock = (int)($item['minStock'] ?? $item['reorderPoint'] ?? 5);

    $totalValue += ($qty * $price);
    $totalCost += ($qty * $cost);
    if ($qty <= $minStock) {
        $lowStockCount++;
    }
}

foreach ($transactions as $entry) {
    $qty = (int)($entry['quantity'] ?? 0);
    if (($entry['type'] ?? '') === 'in') {
        $totalIn += $qty;
        $totalExpense += (float)($entry['totalCost'] ?? 0);
    } elseif (($entry['type'] ?? '') === 'out') {
        $totalOut += $qty;
    }
}

$totalProfit = $totalValue - $totalCost;

usort($inventory, static function (array $a, array $b): int {
    return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});

$inventoryMeta = [];
foreach ($inventory as $item) {
    $id = (string)($item['id'] ?? '');
    if ($id === '') {
        continue;
    }
    $inventoryMeta[$id] = [
        'id' => $id,
        'name' => (string)($item['name'] ?? 'Unnamed'),
        'sku' => (string)($item['sku'] ?? ''),
        'costPrice' => (float)($item['costPrice'] ?? 0),
    ];
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Inventory - <?= htmlspecialchars($siteName) ?></title>

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
            colors: {
                primary: "#000000",
                "border-gray": "#e5e5e5"
            },
            fontFamily: {
                display: ["Inter", "sans-serif"]
            }
        }
    }
}
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-gray-50 text-black font-display antialiased;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .mono-card {
        @apply border border-black bg-white p-4;
    }
    .summary-card {
        @apply border border-black bg-white p-4 min-w-[170px] flex-shrink-0;
    }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden">

<?php
$title = 'Inventory';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <section class="mt-4">
        <div class="flex overflow-x-auto no-scrollbar gap-3 px-4 pb-2">
            <div class="summary-card">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Products Available</p>
                <p class="text-2xl font-black italic tracking-tight"><?= count($inventory) ?></p>
            </div>
            <div class="summary-card">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Total Value</p>
                <p class="text-2xl font-black italic tracking-tight"><?= formatCurrency((float)$totalValue) ?></p>
            </div>
            <div class="summary-card">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1">Gross Profit</p>
                <p class="text-2xl font-black italic tracking-tight"><?= formatCurrency((float)$totalProfit) ?></p>
            </div>
        </div>
    </section>

    <section class="px-4 mt-5 grid grid-cols-3 gap-2">
        <a href="?page=product-form" class="bg-black text-white py-3 px-2 flex items-center justify-center gap-1 hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Add</span>
        </a>
        <button onclick="openAIGenerateInventory()" class="bg-black text-white py-3 px-2 flex items-center justify-center gap-1 hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">AI</span>
        </button>
        <button onclick="openInventorySummary()" class="bg-black text-white py-3 px-2 flex items-center justify-center gap-1 hover:bg-gray-800 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m4 6V7m4 10v-3M3 4.5h18v15H3v-15z"/>
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest">Summary</span>
        </button>
    </section>

    <section class="px-4 mt-4">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="inventory-search"
                type="text"
                oninput="filterInventory(this.value)"
                class="w-full border border-black rounded-none py-2 pl-9 pr-3 text-sm focus:ring-0 focus:border-black placeholder:text-gray-300"
                placeholder="Search products or SKU"
            />
        </div>
    </section>

    <?php if ($lowStockCount > 0): ?>
        <section class="px-4 mt-3">
            <div class="border border-black bg-gray-50 p-3 flex items-center justify-between">
                <p class="text-[11px] font-bold uppercase tracking-widest">Low Stock Alerts</p>
                <span class="text-sm font-black"><?= (int)$lowStockCount ?></span>
            </div>
        </section>
    <?php endif; ?>

    <section class="px-4 mt-6 space-y-4" id="inventory-list">
        <div class="flex items-center justify-between border-b border-black pb-2">
            <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Product Feed</h3>
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">A-Z</span>
        </div>

        <?php if (empty($inventory)): ?>
            <div class="mono-card text-center py-10">
                <p class="text-sm font-black uppercase tracking-widest">Inventory Empty</p>
                <p class="text-xs text-gray-500 mt-2">Add your first product to get started.</p>
            </div>
        <?php else: ?>
            <?php foreach ($inventory as $item): ?>
                <?php
                    $itemId = (string)($item['id'] ?? '');
                    $stock = (int)($item['stock'] ?? $item['quantity'] ?? 0);
                    $minStock = (int)($item['minStock'] ?? $item['reorderPoint'] ?? 5);
                    $price = (float)($item['price'] ?? $item['unitPrice'] ?? 0);
                    $costPrice = (float)($item['costPrice'] ?? 0);
                    $sku = (string)($item['sku'] ?? $item['code'] ?? '');
                    $itemValue = $price * $stock;
                    $itemProfit = ($price - $costPrice) * $stock;
                    $stockStatus = $stock <= 0 ? 'Out' : ($stock <= $minStock ? 'Low' : 'Good');
                ?>
                <article
                    class="mono-card inventory-card"
                    data-item-name="<?= htmlspecialchars(strtolower((string)($item['name'] ?? ''))) ?>"
                    data-item-sku="<?= htmlspecialchars(strtolower($sku)) ?>"
                >
                    <div class="flex justify-between items-start mb-3">
                        <div class="min-w-0">
                            <h4 class="text-sm font-black uppercase tracking-tight truncate"><?= htmlspecialchars((string)($item['name'] ?? 'Unnamed')) ?></h4>
                            <p class="text-[10px] font-mono text-gray-500 mt-0.5">SKU: <?= htmlspecialchars($sku !== '' ? $sku : 'NO-SKU') ?></p>
                        </div>
                        <div class="flex items-center gap-2 pl-2">
                            <a href="?page=product-form&id=<?= urlencode($itemId) ?>" class="p-1 border border-black hover:bg-black hover:text-white transition-colors" title="Edit">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </a>
                            <button onclick="deleteInventoryItem('<?= htmlspecialchars($itemId) ?>')" class="p-1 border border-black hover:bg-black hover:text-white transition-colors" title="Delete">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 border-y border-gray-100 py-3 mb-3">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Stock</p>
                            <p class="text-base font-black"><?= $stock ?> <span class="text-xs font-semibold text-gray-500">(<?= $stockStatus ?>)</span></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Price</p>
                            <p class="text-base font-bold"><?= formatCurrency($price) ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Value</p>
                            <p class="text-base font-bold"><?= formatCurrency($itemValue) ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Profit</p>
                            <p class="text-base font-black <?= $itemProfit >= 0 ? 'text-emerald-700' : 'text-red-600' ?>"><?= formatCurrency($itemProfit) ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <button onclick="openStockAdjustModal('<?= htmlspecialchars($itemId) ?>', 'in')" class="py-2 border border-black text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-all">Stock In</button>
                        <button onclick="openStockAdjustModal('<?= htmlspecialchars($itemId) ?>', 'out')" class="py-2 border border-black text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-all">Stock Out</button>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="toggleInventoryHistory('<?= htmlspecialchars($itemId) ?>')" class="py-2 border border-black text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-all">History</button>
                        <button onclick="openAllHistory('<?= htmlspecialchars($itemId) ?>')" class="py-2 border border-black text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-all">View All</button>
                    </div>

                    <div id="inventory-history-<?= htmlspecialchars($itemId) ?>" data-loaded="0" class="hidden mt-3 space-y-2 text-xs text-gray-700"></div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="px-4 mt-10 mb-8">
        <div class="bg-black text-white p-5">
            <h3 class="text-[10px] font-light uppercase tracking-[0.35em] opacity-70 mb-4">Inventory Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between items-end border-b border-white/20 pb-2">
                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-70">Total Stock In</span>
                    <span class="text-xl font-black italic tracking-tight" id="mini-total-in"><?= (int)$totalIn ?> Units</span>
                </div>
                <div class="flex justify-between items-end border-b border-white/20 pb-2">
                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-70">Total Stock Out</span>
                    <span class="text-xl font-black italic tracking-tight" id="mini-total-out"><?= (int)$totalOut ?> Units</span>
                </div>
                <div class="flex justify-between items-end border-b border-white/20 pb-2">
                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-70">Total Expense</span>
                    <span class="text-xl font-black italic tracking-tight" id="mini-total-expense"><?= formatCurrency($totalExpense) ?></span>
                </div>
                <div class="flex justify-between items-end pt-1">
                    <span class="text-[10px] font-bold uppercase tracking-widest opacity-70">Inventory Value</span>
                    <span class="text-2xl font-black tracking-tight"><?= formatCurrency($totalValue) ?></span>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<div id="inventory-summary-panel" class="fixed inset-0 bg-black/50 hidden z-50">
    <div class="absolute inset-y-0 right-0 w-full max-w-[420px] bg-white border-l border-black transform translate-x-full transition-transform duration-300" id="inventory-summary-sheet">
        <div class="p-4 border-b border-black flex items-center justify-between">
            <div>
                <h3 class="text-base font-black uppercase tracking-wider">Inventory Summary</h3>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest">Totals and recent moves</p>
            </div>
            <button onclick="closeInventorySummary()" class="p-2 border border-black hover:bg-black hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-4 space-y-4 h-[calc(100%-73px)] overflow-y-auto no-scrollbar">
            <div class="grid grid-cols-2 gap-3">
                <div class="mono-card">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Total In</p>
                    <p class="text-xl font-black" id="summary-total-in">-</p>
                </div>
                <div class="mono-card">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Total Out</p>
                    <p class="text-xl font-black" id="summary-total-out">-</p>
                </div>
                <div class="mono-card">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Total Expense</p>
                    <p class="text-xl font-black" id="summary-total-expense">-</p>
                </div>
                <div class="mono-card">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Inventory Value</p>
                    <p class="text-xl font-black"><?= formatCurrency($totalValue) ?></p>
                </div>
            </div>
            <div>
                <h4 class="text-[10px] font-black uppercase tracking-[0.2em] border-b border-black pb-2 mb-2">Recent Moves</h4>
                <div id="summary-transactions" class="space-y-2 text-sm text-gray-700">Loading...</div>
            </div>
        </div>
    </div>
</div>

<div id="stock-adjust-modal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4">
    <div class="w-full max-w-sm bg-white border border-black p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-black uppercase tracking-widest" id="stock-adjust-title">Adjust Stock</h3>
            <button onclick="closeStockAdjustModal()" class="p-1 border border-black hover:bg-black hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="space-y-3">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest mb-1">Quantity</label>
                <input id="stock-adjust-qty" type="number" min="1" value="1" class="w-full border border-black rounded-none px-3 py-2 text-sm focus:ring-0 focus:border-black">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest mb-1">Unit Cost</label>
                <input id="stock-adjust-cost" type="number" min="0" step="0.01" class="w-full border border-black rounded-none px-3 py-2 text-sm focus:ring-0 focus:border-black">
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest mb-1">Note</label>
                <input id="stock-adjust-note" type="text" placeholder="Optional note" class="w-full border border-black rounded-none px-3 py-2 text-sm focus:ring-0 focus:border-black">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-2 mt-4">
            <button onclick="submitStockAdjust()" class="py-2 bg-black text-white text-[10px] font-black uppercase tracking-widest">Save</button>
            <button onclick="closeStockAdjustModal()" class="py-2 border border-black text-[10px] font-black uppercase tracking-widest">Cancel</button>
        </div>
    </div>
</div>

<div id="product-history-modal" class="fixed inset-0 bg-black/60 hidden z-50">
    <div class="absolute inset-y-0 right-0 w-full max-w-[420px] bg-white border-l border-black transform translate-x-full transition-transform duration-300" id="product-history-sheet">
        <div class="p-4 border-b border-black flex items-center justify-between">
            <div>
                <h3 class="text-sm font-black uppercase tracking-widest" id="product-history-title">Product History</h3>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest" id="product-history-subtitle">Recent transactions</p>
            </div>
            <button onclick="closeAllHistory()" class="p-2 border border-black hover:bg-black hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="p-4 h-[calc(100%-73px)] overflow-y-auto no-scrollbar">
            <div class="grid grid-cols-3 gap-2 mb-4">
                <div class="mono-card p-3">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">In</p>
                    <p class="text-lg font-black" id="history-total-in">0</p>
                </div>
                <div class="mono-card p-3">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Out</p>
                    <p class="text-lg font-black" id="history-total-out">0</p>
                </div>
                <div class="mono-card p-3">
                    <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Expense</p>
                    <p class="text-sm font-black" id="history-total-expense">$0.00</p>
                </div>
            </div>
            <div id="product-history-list" class="space-y-2 text-sm text-gray-700">Loading...</div>
        </div>
    </div>
</div>

<div id="ai-inventory-modal" class="fixed inset-0 bg-black/60 hidden z-50 p-4 overflow-y-auto">
    <div class="w-full max-w-[420px] mx-auto bg-white border border-black p-4 mt-8 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-black uppercase tracking-widest">AI Generate Inventory</h3>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest">Generate from prompt</p>
            </div>
            <button onclick="closeAIGenerateInventory()" class="p-2 border border-black hover:bg-black hover:text-white transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="space-y-3">
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest mb-1">Description</label>
                <textarea id="ai-inventory-description" rows="3" class="w-full border border-black rounded-none px-3 py-2 text-sm focus:ring-0 focus:border-black" placeholder="e.g., office supplies for a 20-person team"></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest mb-1">Category</label>
                <select id="ai-inventory-category" class="w-full border border-black rounded-none px-3 py-2 text-sm focus:ring-0 focus:border-black">
                    <option value="General">General</option>
                    <option value="Office Supplies">Office Supplies</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Furniture">Furniture</option>
                    <option value="Raw Materials">Raw Materials</option>
                    <option value="Packaging">Packaging</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Groceries">Groceries</option>
                </select>
            </div>
            <button onclick="generateInventory()" id="ai-inventory-generate-btn" class="w-full bg-black text-white py-3 text-[11px] font-black uppercase tracking-[0.2em]">
                Generate
            </button>
        </div>

        <div id="ai-inventory-results" class="hidden mt-5">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-[11px] font-black uppercase tracking-widest">Generated Items</h4>
                <button onclick="saveSelectedInventoryItems()" class="px-3 py-1 border border-black text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-colors">Save Selected</button>
            </div>
            <div id="ai-inventory-items" class="space-y-2"></div>
            <button onclick="saveAllInventoryItems()" class="w-full mt-3 py-3 bg-black text-white text-[10px] font-black uppercase tracking-widest">Save All</button>
        </div>
    </div>
</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const INVENTORY_META = <?= json_encode($inventoryMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}

let stockAdjustState = {
    productId: null,
    direction: 'in'
};
let generatedInventoryItems = [];

function money(value) {
    const amount = Number(value || 0);
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(amount);
}

function getErrorMessage(error, fallback) {
    if (error && error.response && typeof error.response.error === 'string') {
        return error.response.error;
    }
    if (error && error.response && error.response.error && typeof error.response.error.message === 'string') {
        return error.response.error.message;
    }
    if (error && error.response && typeof error.response.message === 'string' && error.response.message.trim() !== '') {
        return error.response.message;
    }
    if (error && typeof error.message === 'string' && error.message.trim() !== '') {
        return error.message;
    }
    return fallback;
}

function toast(message, type) {
    if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
        Mobile.ui.showToast(message, type || 'info');
        return;
    }
    alert(message);
}

function filterInventory(query) {
    const q = (query || '').toLowerCase().trim();
    document.querySelectorAll('.inventory-card').forEach((card) => {
        const name = (card.dataset.itemName || '').toLowerCase();
        const sku = (card.dataset.itemSku || '').toLowerCase();
        const show = q === '' || name.includes(q) || sku.includes(q);
        card.style.display = show ? '' : 'none';
    });
}

function openStockAdjustModal(productId, direction) {
    stockAdjustState.productId = productId;
    stockAdjustState.direction = direction === 'out' ? 'out' : 'in';

    const title = document.getElementById('stock-adjust-title');
    const qty = document.getElementById('stock-adjust-qty');
    const cost = document.getElementById('stock-adjust-cost');
    const note = document.getElementById('stock-adjust-note');
    const meta = INVENTORY_META[productId] || null;

    if (title) {
        title.textContent = stockAdjustState.direction === 'in' ? 'Stock In' : 'Stock Out';
    }
    if (qty) qty.value = '1';
    if (cost) cost.value = meta && typeof meta.costPrice === 'number' ? String(meta.costPrice) : '';
    if (note) note.value = '';

    const modal = document.getElementById('stock-adjust-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeStockAdjustModal() {
    const modal = document.getElementById('stock-adjust-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function submitStockAdjust() {
    const qtyValue = parseInt(document.getElementById('stock-adjust-qty').value || '0', 10);
    const unitCostValue = document.getElementById('stock-adjust-cost').value;
    const note = (document.getElementById('stock-adjust-note').value || '').trim();

    if (!stockAdjustState.productId) {
        toast('Product not selected.', 'error');
        return;
    }
    if (!qtyValue || qtyValue <= 0) {
        toast('Enter a valid quantity.', 'warning');
        return;
    }

    const payload = {
        id: stockAdjustState.productId,
        adjustment: stockAdjustState.direction === 'in' ? qtyValue : -qtyValue,
        note: note,
        csrf_token: CSRF_TOKEN
    };
    if (unitCostValue !== '' && !Number.isNaN(Number(unitCostValue))) {
        payload.unitCost = Number(unitCostValue);
    }

    try {
        const response = await App.api.post('api/inventory.php?action=adjust', payload);
        if (!response.success) {
            throw new Error('Failed to adjust stock.');
        }
        toast('Stock updated.', 'success');
        closeStockAdjustModal();
        setTimeout(function() {
            window.location.reload();
        }, 200);
    } catch (error) {
        toast(getErrorMessage(error, 'Failed to adjust stock.'), 'error');
    }
}

function openInventorySummary() {
    const backdrop = document.getElementById('inventory-summary-panel');
    const panel = document.getElementById('inventory-summary-sheet');
    backdrop.classList.remove('hidden');
    setTimeout(function() {
        panel.classList.remove('translate-x-full');
    }, 10);
    loadInventorySummary();
}

function closeInventorySummary() {
    const backdrop = document.getElementById('inventory-summary-panel');
    const panel = document.getElementById('inventory-summary-sheet');
    panel.classList.add('translate-x-full');
    setTimeout(function() {
        backdrop.classList.add('hidden');
    }, 200);
}

async function loadInventorySummary() {
    try {
        const response = await App.api.get('api/inventory.php?action=summary');
        if (!response.success) {
            throw new Error('Failed to load summary');
        }

        const data = response.data || {};
        const totalIn = Number(data.total_in || 0);
        const totalOut = Number(data.total_out || 0);
        const totalExpense = Number(data.total_expense || 0);
        const recent = Array.isArray(data.recent) ? data.recent : [];

        const totalInEl = document.getElementById('summary-total-in');
        const totalOutEl = document.getElementById('summary-total-out');
        const totalExpenseEl = document.getElementById('summary-total-expense');
        if (totalInEl) totalInEl.textContent = String(totalIn);
        if (totalOutEl) totalOutEl.textContent = String(totalOut);
        if (totalExpenseEl) totalExpenseEl.textContent = money(totalExpense);

        const miniIn = document.getElementById('mini-total-in');
        const miniOut = document.getElementById('mini-total-out');
        const miniExpense = document.getElementById('mini-total-expense');
        if (miniIn) miniIn.textContent = totalIn + ' Units';
        if (miniOut) miniOut.textContent = totalOut + ' Units';
        if (miniExpense) miniExpense.textContent = money(totalExpense);

        const list = document.getElementById('summary-transactions');
        if (!list) return;

        if (!recent.length) {
            list.innerHTML = '<p class="text-xs text-gray-500">No transactions yet.</p>';
            return;
        }

        list.innerHTML = recent.map((entry) => {
            const type = entry.type === 'in' ? 'IN' : 'OUT';
            const color = entry.type === 'in' ? 'text-emerald-700' : 'text-red-600';
            const created = entry.createdAt ? new Date(entry.createdAt).toLocaleString() : '--';
            const qty = Number(entry.quantity || 0);
            const note = entry.note ? `<p class="text-[10px] text-gray-500 mt-0.5">${escapeHtml(entry.note)}</p>` : '';
            return `
                <div class="border border-black p-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-wide truncate">${escapeHtml(entry.productName || 'Item')}</p>
                            <p class="text-[10px] text-gray-500">${created}</p>
                            ${note}
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-[10px] font-black ${color}">${type}</p>
                            <p class="text-sm font-black">${qty}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        const list = document.getElementById('summary-transactions');
        if (list) {
            list.innerHTML = '<p class="text-xs text-red-600">Failed to load summary.</p>';
        }
    }
}

async function toggleInventoryHistory(productId) {
    const container = document.getElementById('inventory-history-' + productId);
    if (!container) {
        return;
    }

    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');

        if (container.dataset.loaded === '1') {
            return;
        }

        container.innerHTML = '<p class="text-[10px] text-gray-500 uppercase tracking-widest">Loading...</p>';
        try {
            const response = await App.api.get('api/inventory.php?action=transactions&productId=' + encodeURIComponent(productId) + '&limit=5');
            if (!response.success) {
                throw new Error('Unable to load history.');
            }
            const history = Array.isArray(response.data) ? response.data : [];
            if (!history.length) {
                container.innerHTML = '<p class="text-[10px] text-gray-500 uppercase tracking-widest">No history yet.</p>';
                container.dataset.loaded = '1';
                return;
            }

            container.innerHTML = history.map((entry) => {
                const type = entry.type === 'in' ? 'IN' : 'OUT';
                const color = entry.type === 'in' ? 'text-emerald-700' : 'text-red-600';
                const created = entry.createdAt ? new Date(entry.createdAt).toLocaleString() : '--';
                const qty = Number(entry.quantity || 0);
                const totalCost = Number(entry.totalCost || 0);
                const note = entry.note ? `<p class="text-[10px] text-gray-500">${escapeHtml(entry.note)}</p>` : '';
                return `
                    <div class="border border-gray-200 p-2">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <p class="text-[10px] font-black ${color}">${type}</p>
                                <p class="text-[10px] text-gray-500">${created}</p>
                                ${note}
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-black">${qty}</p>
                                <p class="text-[10px] text-gray-500">${money(totalCost)}</p>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            container.dataset.loaded = '1';
        } catch (error) {
            container.innerHTML = '<p class="text-[10px] text-red-600 uppercase tracking-widest">Failed to load history.</p>';
        }
    } else {
        container.classList.add('hidden');
    }
}

async function openAllHistory(productId) {
    const backdrop = document.getElementById('product-history-modal');
    const panel = document.getElementById('product-history-sheet');
    const list = document.getElementById('product-history-list');
    const title = document.getElementById('product-history-title');
    const subtitle = document.getElementById('product-history-subtitle');

    backdrop.classList.remove('hidden');
    setTimeout(function() {
        panel.classList.remove('translate-x-full');
    }, 10);

    const meta = INVENTORY_META[productId] || null;
    title.textContent = meta ? meta.name : 'Product History';
    subtitle.textContent = meta && meta.sku ? ('SKU: ' + meta.sku) : 'Transaction ledger';
    list.innerHTML = 'Loading...';

    try {
        const response = await App.api.get('api/inventory.php?action=transactions&productId=' + encodeURIComponent(productId));
        if (!response.success) {
            throw new Error('Unable to load history.');
        }
        const rows = Array.isArray(response.data) ? response.data : [];
        let totalIn = 0;
        let totalOut = 0;
        let totalExpense = 0;

        rows.forEach((entry) => {
            const qty = Number(entry.quantity || 0);
            if (entry.type === 'in') {
                totalIn += qty;
                totalExpense += Number(entry.totalCost || 0);
            } else if (entry.type === 'out') {
                totalOut += qty;
            }
        });

        document.getElementById('history-total-in').textContent = String(totalIn);
        document.getElementById('history-total-out').textContent = String(totalOut);
        document.getElementById('history-total-expense').textContent = money(totalExpense);

        if (!rows.length) {
            list.innerHTML = '<p class="text-sm text-gray-500">No transactions yet.</p>';
            return;
        }

        list.innerHTML = rows.map((entry) => {
            const type = entry.type === 'in' ? 'IN' : 'OUT';
            const color = entry.type === 'in' ? 'text-emerald-700' : 'text-red-600';
            const created = entry.createdAt ? new Date(entry.createdAt).toLocaleString() : '--';
            const qty = Number(entry.quantity || 0);
            const unitCost = Number(entry.unitCost || 0);
            const totalCost = Number(entry.totalCost || 0);
            const note = entry.note ? `<p class="text-[10px] text-gray-500">${escapeHtml(entry.note)}</p>` : '';

            return `
                <div class="border border-black p-3">
                    <div class="flex items-start justify-between">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black ${color}">${type}</p>
                            <p class="text-[10px] text-gray-500">${created}</p>
                            <p class="text-[10px] text-gray-500">Unit: ${money(unitCost)} | Total: ${money(totalCost)}</p>
                            ${note}
                        </div>
                        <p class="text-lg font-black">${qty}</p>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        list.innerHTML = '<p class="text-sm text-red-600">Failed to load history.</p>';
    }
}

function closeAllHistory() {
    const backdrop = document.getElementById('product-history-modal');
    const panel = document.getElementById('product-history-sheet');
    panel.classList.add('translate-x-full');
    setTimeout(function() {
        backdrop.classList.add('hidden');
    }, 200);
}

function openAIGenerateInventory() {
    const modal = document.getElementById('ai-inventory-modal');
    modal.classList.remove('hidden');
    document.getElementById('ai-inventory-description').value = '';
    document.getElementById('ai-inventory-results').classList.add('hidden');
    document.getElementById('ai-inventory-items').innerHTML = '';
    generatedInventoryItems = [];
}

function closeAIGenerateInventory() {
    document.getElementById('ai-inventory-modal').classList.add('hidden');
}

async function generateInventory() {
    const description = (document.getElementById('ai-inventory-description').value || '').trim();
    const category = document.getElementById('ai-inventory-category').value || 'General';
    const btn = document.getElementById('ai-inventory-generate-btn');

    if (!description) {
        toast('Please enter a description first.', 'warning');
        return;
    }

    const oldText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating...';

    try {
        const modelsResponse = await App.api.get('api/models.php');
        const models = modelsResponse.data || {};
        const groqModels = Array.isArray(models.groq) ? models.groq : [];
        const defaultModel = groqModels.find((m) => m.isDefault) || groqModels[0];
        const model = defaultModel ? defaultModel.modelId : '';

        if (!model) {
            throw new Error('No AI model configured. Configure one in Model Settings.');
        }

        const response = await App.api.post('api/ai.php?action=generate_inventory', {
            description: description,
            category: category,
            provider: 'groq',
            model: model,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success || !response.data || !Array.isArray(response.data.items)) {
            throw new Error('Failed to generate inventory items.');
        }

        generatedInventoryItems = response.data.items;
        renderGeneratedInventory(generatedInventoryItems);
        document.getElementById('ai-inventory-results').classList.remove('hidden');
        toast('Inventory items generated.', 'success');
    } catch (error) {
        toast(getErrorMessage(error, 'Failed to generate inventory items.'), 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = oldText;
    }
}

function renderGeneratedInventory(items) {
    const container = document.getElementById('ai-inventory-items');
    if (!container) {
        return;
    }

    if (!items.length) {
        container.innerHTML = '<p class="text-sm text-gray-500">No generated items.</p>';
        return;
    }

    container.innerHTML = items.map((item, index) => {
        const name = escapeHtml(item.name || 'Unnamed');
        const description = escapeHtml(item.description || 'No description');
        const sku = escapeHtml(item.sku || 'NO-SKU');
        const price = Number(item.price || 0);
        return `
            <div class="border border-black p-3">
                <div class="flex items-start gap-2">
                    <input type="checkbox" id="inventory-item-${index}" class="mt-1 w-4 h-4 border border-black rounded-none">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-black uppercase tracking-tight">${name}</p>
                        <p class="text-[10px] text-gray-500 mt-1">${description}</p>
                        <p class="text-[10px] font-mono text-gray-500 mt-1">SKU: ${sku}</p>
                        <p class="text-xs font-bold mt-1">${money(price)}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button onclick="saveInventoryItem(${index})" class="px-2 py-1 bg-black text-white text-[10px] font-black uppercase tracking-widest">Save</button>
                        <button onclick="removeInventoryItem(${index})" class="px-2 py-1 border border-black text-[10px] font-black uppercase tracking-widest">Drop</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function removeInventoryItem(index) {
    generatedInventoryItems.splice(index, 1);
    renderGeneratedInventory(generatedInventoryItems);
    if (!generatedInventoryItems.length) {
        document.getElementById('ai-inventory-results').classList.add('hidden');
    }
}

async function saveInventoryItem(index) {
    const item = generatedInventoryItems[index];
    if (!item) return;

    try {
        const response = await App.api.post('api/ai.php?action=create_inventory_items', {
            items: [item],
            csrf_token: CSRF_TOKEN
        });
        if (!response.success) {
            throw new Error('Failed to save item.');
        }
        toast('Item saved.', 'success');
        removeInventoryItem(index);
        if (!generatedInventoryItems.length) {
            setTimeout(function() {
                window.location.reload();
            }, 250);
        }
    } catch (error) {
        toast(getErrorMessage(error, 'Failed to save item.'), 'error');
    }
}

async function saveSelectedInventoryItems() {
    const checked = Array.from(document.querySelectorAll('[id^="inventory-item-"]:checked'));
    if (!checked.length) {
        toast('Select at least one item.', 'warning');
        return;
    }

    const selectedIndexes = checked.map((cb) => Number(cb.id.replace('inventory-item-', ''))).filter((n) => !Number.isNaN(n));
    let saved = 0;
    let failed = 0;

    for (const idx of selectedIndexes) {
        const item = generatedInventoryItems[idx];
        if (!item) continue;
        try {
            const response = await App.api.post('api/ai.php?action=create_inventory_items', {
                items: [item],
                csrf_token: CSRF_TOKEN
            });
            if (response.success) {
                saved++;
            } else {
                failed++;
            }
        } catch (error) {
            failed++;
        }
    }

    if (saved > 0) {
        toast(saved + ' item(s) saved.', 'success');
        generatedInventoryItems = generatedInventoryItems.filter((_, idx) => !selectedIndexes.includes(idx));
        if (generatedInventoryItems.length) {
            renderGeneratedInventory(generatedInventoryItems);
        } else {
            closeAIGenerateInventory();
            setTimeout(function() {
                window.location.reload();
            }, 250);
        }
    }
    if (failed > 0) {
        toast(failed + ' item(s) failed.', 'error');
    }
}

async function saveAllInventoryItems() {
    if (!generatedInventoryItems.length) {
        return;
    }

    let saved = 0;
    let failed = 0;
    for (const item of generatedInventoryItems) {
        try {
            const response = await App.api.post('api/ai.php?action=create_inventory_items', {
                items: [item],
                csrf_token: CSRF_TOKEN
            });
            if (response.success) {
                saved++;
            } else {
                failed++;
            }
        } catch (error) {
            failed++;
        }
    }

    if (saved > 0) {
        toast(saved + ' item(s) saved.', 'success');
    }
    if (failed > 0) {
        toast(failed + ' item(s) failed.', 'error');
    }

    if (saved > 0) {
        closeAIGenerateInventory();
        setTimeout(function() {
            window.location.reload();
        }, 250);
    }
}

function deleteInventoryItem(id) {
    if (!id) return;

    if (window.Mobile && Mobile.ui && typeof Mobile.ui.confirmAction === 'function') {
        Mobile.ui.confirmAction('Delete this product?', async function() {
            await doDeleteInventoryItem(id);
        });
        return;
    }

    if (window.confirm('Delete this product?')) {
        doDeleteInventoryItem(id);
    }
}

async function doDeleteInventoryItem(id) {
    try {
        const response = await App.api.delete('api/inventory.php?id=' + encodeURIComponent(id));
        if (!response.success) {
            throw new Error('Failed to delete product.');
        }
        toast('Product deleted.', 'success');
        setTimeout(function() {
            window.location.reload();
        }, 200);
    } catch (error) {
        toast(getErrorMessage(error, 'Failed to delete product.'), 'error');
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.getElementById('inventory-summary-panel').addEventListener('click', function(event) {
    if (event.target === this) {
        closeInventorySummary();
    }
});
document.getElementById('product-history-modal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeAllHistory();
    }
});
document.getElementById('ai-inventory-modal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeAIGenerateInventory();
    }
});
document.getElementById('stock-adjust-modal').addEventListener('click', function(event) {
    if (event.target === this) {
        closeStockAdjustModal();
    }
});
</script>

</body>
</html>
