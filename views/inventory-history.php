<?php
/**
 * Inventory History Page - Per Product Ledger
 */

$pageTitle = 'Inventory History';
$productId = $_GET['productId'] ?? '';
?>

<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-3xl font-bold tracking-tight text-gray-900">Inventory History</h1>
        <p class="text-gray-500 mt-1" id="history-subtitle">Transaction ledger for this product</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="?page=inventory" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
    </div>
</div>

<div id="history-loading" class="flex items-center justify-center py-16">
    <div class="inline-flex items-center gap-3 text-gray-600">
        <div class="w-6 h-6 border-2 border-gray-300 border-t-transparent rounded-full animate-spin"></div>
        <span>Loading history...</span>
    </div>
</div>

<div id="history-error" class="hidden text-center py-16 bg-white rounded-2xl border border-gray-200">
    <h3 class="text-xl font-bold text-gray-900">Product not found</h3>
    <p class="text-gray-500 mt-2">Pick a product from inventory to view its history.</p>
    <a href="?page=inventory" class="mt-6 inline-block px-6 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">Back to Inventory</a>
</div>

<div id="history-content" class="hidden space-y-6">
    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
            <div>
                <h2 class="text-xl font-bold text-gray-900" id="history-product-name">Product</h2>
                <p class="text-sm text-gray-500" id="history-product-meta">SKU: --</p>
            </div>
            <div class="text-xs text-gray-400 uppercase tracking-widest">Filtered totals</div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-gray-50 rounded-xl p-4">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Total In</div>
                <div class="text-2xl font-bold text-gray-900" id="history-total-in">0</div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Total Out</div>
                <div class="text-2xl font-bold text-gray-900" id="history-total-out">0</div>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">Total Expense</div>
                <div class="text-2xl font-bold text-gray-900" id="history-total-expense">$0.00</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Type</label>
                <select id="history-filter-type" class="w-40 px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="all">All</option>
                    <option value="in">In</option>
                    <option value="out">Out</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">From</label>
                <input type="date" id="history-filter-from" class="w-44 px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">To</label>
                <input type="date" id="history-filter-to" class="w-44 px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <button onclick="resetHistoryFilters()" class="px-4 py-2 border border-gray-200 rounded-lg text-sm font-semibold hover:bg-gray-50 transition">
                Reset
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs font-bold text-gray-500 uppercase tracking-widest">
                        <th class="py-3 pr-4">#</th>
                        <th class="py-3 pr-4">Date</th>
                        <th class="py-3 pr-4">Type</th>
                        <th class="py-3 pr-4">Qty</th>
                        <th class="py-3 pr-4">Unit Cost</th>
                        <th class="py-3 pr-4">Total</th>
                        <th class="py-3 pr-4">Note</th>
                    </tr>
                </thead>
                <tbody id="history-table-body" class="divide-y divide-gray-100 text-sm text-gray-700">
                </tbody>
            </table>
        </div>
        <div id="history-empty" class="hidden text-center py-10 text-gray-500">
            No transactions found for this product.
        </div>
    </div>
</div>

<script>
const PRODUCT_ID = '<?php echo e($productId); ?>';
let allTransactions = [];
let currentProduct = null;

document.addEventListener('DOMContentLoaded', async () => {
    if (!PRODUCT_ID) {
        showHistoryError();
        return;
    }
    await loadInventoryHistory();
});

async function loadInventoryHistory() {
    try {
        const [productResponse, transactionResponse] = await Promise.all([
            api.get('api/inventory.php'),
            api.get(`api/inventory.php?action=transactions&productId=${encodeURIComponent(PRODUCT_ID)}`)
        ]);

        if (!productResponse.success) {
            showHistoryError();
            return;
        }

        currentProduct = (productResponse.data || []).find(item => item.id === PRODUCT_ID) || null;
        allTransactions = transactionResponse.success ? (transactionResponse.data || []) : [];

        if (!currentProduct) {
            showHistoryError();
            return;
        }

        document.getElementById('history-product-name').textContent = currentProduct.name || 'Product';
        document.getElementById('history-product-meta').textContent = 'SKU: ' + (currentProduct.sku || '--');
        document.getElementById('history-subtitle').textContent = 'Ledger for ' + (currentProduct.name || 'product');

        document.getElementById('history-loading').classList.add('hidden');
        document.getElementById('history-content').classList.remove('hidden');

        bindHistoryFilters();
        renderHistory(allTransactions);
    } catch (error) {
        console.error('Failed to load inventory history:', error);
        showHistoryError();
    }
}

function bindHistoryFilters() {
    document.getElementById('history-filter-type').addEventListener('change', applyHistoryFilters);
    document.getElementById('history-filter-from').addEventListener('change', applyHistoryFilters);
    document.getElementById('history-filter-to').addEventListener('change', applyHistoryFilters);
}

function applyHistoryFilters() {
    const type = document.getElementById('history-filter-type').value;
    const from = document.getElementById('history-filter-from').value;
    const to = document.getElementById('history-filter-to').value;

    const fromDate = from ? new Date(from + 'T00:00:00') : null;
    const toDate = to ? new Date(to + 'T23:59:59') : null;

    const filtered = allTransactions.filter(entry => {
        if (type !== 'all' && entry.type !== type) {
            return false;
        }
        if (fromDate || toDate) {
            const created = entry.createdAt ? new Date(entry.createdAt) : null;
            if (!created) {
                return false;
            }
            if (fromDate && created < fromDate) {
                return false;
            }
            if (toDate && created > toDate) {
                return false;
            }
        }
        return true;
    });

    renderHistory(filtered);
}

function resetHistoryFilters() {
    document.getElementById('history-filter-type').value = 'all';
    document.getElementById('history-filter-from').value = '';
    document.getElementById('history-filter-to').value = '';
    renderHistory(allTransactions);
}

function renderHistory(transactions) {
    const tbody = document.getElementById('history-table-body');
    const emptyState = document.getElementById('history-empty');

    if (!transactions || transactions.length === 0) {
        tbody.innerHTML = '';
        emptyState.classList.remove('hidden');
        updateHistoryTotals([]);
        return;
    }

    emptyState.classList.add('hidden');

    tbody.innerHTML = transactions.map((entry, index) => {
        const typeLabel = entry.type === 'in' ? 'In' : 'Out';
        const typeClass = entry.type === 'in' ? 'text-green-700' : 'text-red-600';
        const dateLabel = entry.createdAt ? new Date(entry.createdAt).toLocaleString() : '--';
        const unitCost = typeof entry.unitCost === 'number' ? formatCurrency(entry.unitCost) : '-';
        const totalCost = typeof entry.totalCost === 'number' ? formatCurrency(entry.totalCost) : '-';
        const note = entry.note ? entry.note : '-';

        return `
            <tr>
                <td class="py-3 pr-4 text-xs text-gray-500">${index + 1}</td>
                <td class="py-3 pr-4">${dateLabel}</td>
                <td class="py-3 pr-4 font-semibold ${typeClass}">${typeLabel}</td>
                <td class="py-3 pr-4">${entry.quantity ?? 0}</td>
                <td class="py-3 pr-4">${unitCost}</td>
                <td class="py-3 pr-4">${totalCost}</td>
                <td class="py-3 pr-4">${note}</td>
            </tr>
        `;
    }).join('');

    updateHistoryTotals(transactions);
}

function updateHistoryTotals(transactions) {
    let totalIn = 0;
    let totalOut = 0;
    let totalExpense = 0;

    transactions.forEach(entry => {
        const qty = parseInt(entry.quantity || 0, 10);
        if (entry.type === 'in') {
            totalIn += qty;
            totalExpense += Number(entry.totalCost || 0);
        } else if (entry.type === 'out') {
            totalOut += qty;
        }
    });

    document.getElementById('history-total-in').textContent = totalIn;
    document.getElementById('history-total-out').textContent = totalOut;
    document.getElementById('history-total-expense').textContent = formatCurrency(totalExpense);
}

function showHistoryError() {
    document.getElementById('history-loading').classList.add('hidden');
    document.getElementById('history-content').classList.add('hidden');
    document.getElementById('history-error').classList.remove('hidden');
}
</script>
