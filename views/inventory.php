<?php
// Inventory View
$db = new Database(getMasterPassword(), Auth::userId());
$inventory = $db->load('inventory');

// Calculate inventory totals
$totalValue = 0;
$totalCost = 0;
foreach ($inventory as $item) {
    $qty = $item['quantity'] ?? $item['stock'] ?? 0;
    $price = $item['price'] ?? $item['unitPrice'] ?? 0;
    $cost = $item['costPrice'] ?? 0;
    $totalValue += ($qty * $price);
    $totalCost += ($qty * $cost);
}
$totalProfit = $totalValue - $totalCost;
?>

<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col gap-6">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-5xl font-black text-black tracking-tighter">Inventory</h2>
                <p class="text-slate-400 text-sm mt-2 font-medium tracking-tight uppercase tracking-widest">Global Product Stock Control</p>
            </div>
            <div class="flex gap-3">
                <button onclick="openAIGenerateInventory()" class="flex items-center justify-center h-12 px-6 bg-black text-white text-sm font-black uppercase tracking-widest hover:bg-slate-800 transition-opacity flex gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    AI Generate
                </button>
                <a href="?page=product-form" class="flex items-center justify-center h-12 px-6 bg-black text-white text-sm font-black uppercase tracking-widest hover:bg-slate-800 transition-opacity">
                    + Add Product
                </a>
                <button onclick="openInventorySummary()" class="flex items-center justify-center h-12 px-6 border-2 border-black bg-white text-black text-sm font-black uppercase tracking-widest hover:bg-slate-50 transition-colors">
                    Summary
                </button>
            </div>
        </div>

        <!-- Stats Bar -->
        <section class="grid grid-cols-4 gap-4">
            <div class="bg-white p-6 border border-slate-200">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Products Available</p>
                <h3 class="text-2xl font-black tracking-tight"><?php echo count($inventory); ?></h3>
            </div>
            <div class="bg-white p-6 border border-slate-200">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Total Value</p>
                <h3 class="text-2xl font-black tracking-tight"><?php echo formatCurrency($totalValue); ?></h3>
            </div>
            <div class="bg-white p-6 border border-slate-200">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Total Cost</p>
                <h3 class="text-2xl font-black tracking-tight"><?php echo formatCurrency($totalCost); ?></h3>
            </div>
            <div class="bg-black p-6 border border-black">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Gross Profit</p>
                <h3 class="text-2xl font-black tracking-tight text-white"><?php echo formatCurrency($totalProfit); ?></h3>
            </div>
        </section>
    </div>

    <!-- Products Grid -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-20">
        <?php if (empty($inventory)): ?>
            <div class="col-span-full text-center py-20 bg-white rounded-2xl border border-gray-100">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Inventory Empty</h3>
                <p class="text-gray-500 mt-2">Start by adding your first product from the button above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($inventory as $product):
                $stock = $product['stock'] ?? $product['quantity'] ?? 0;
                $minStock = $product['minStock'] ?? $product['reorderPoint'] ?? 5;
                $lowStock = $stock <= $minStock;
                $price = $product['price'] ?? $product['unitPrice'] ?? 0;
                $costPrice = $product['costPrice'] ?? 0;
                $itemCost = $costPrice * $stock;
                $itemValue = $price * $stock;
                $itemProfit = $itemValue - $itemCost;
            ?>
                <div class="group bg-white <?php echo $lowStock ? 'border-2 border-red-500' : 'border-2 border-black'; ?> p-8 flex flex-col gap-6 hover:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] transition-shadow <?php echo $lowStock ? 'hover:shadow-[8px_8px_0px_0px_rgba(239,68,68,1)]' : ''; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-3 opacity-0 group-hover:opacity-100 transition-opacity mb-2">
                                <a href="?page=product-form&id=<?php echo e($product['id']); ?>"
                                   class="p-1.5 bg-white border border-slate-200 rounded text-slate-400 hover:text-black hover:border-black transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </a>
                                <button onclick="deleteInventoryItem('<?php echo e($product['id']); ?>')"
                                        class="p-1.5 bg-white border border-slate-200 rounded text-slate-400 hover:text-red-600 hover:border-red-200 transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                            <h4 class="text-xl font-black tracking-tight"><?php echo e($product['name']); ?></h4>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">SKU: <?php echo e($product['sku'] ?? 'NO-SKU'); ?></span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black uppercase tracking-widest text-slate-400">Stock</span>
                            <span class="text-3xl font-black <?php echo $lowStock ? 'text-red-500' : ''; ?>"><?php echo $stock; ?></span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-y-4 pt-4 border-t border-slate-100">
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Cost</p>
                            <p class="text-sm font-bold"><?php echo formatCurrency($costPrice); ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Price</p>
                            <p class="text-sm font-bold"><?php echo formatCurrency($price); ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Total Value</p>
                            <p class="text-sm font-bold"><?php echo formatCurrency($itemValue); ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Profit</p>
                            <p class="text-sm font-black <?php echo $itemProfit >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>"><?php echo formatCurrency($itemProfit); ?></p>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <button onclick="openStockAdjustModal('<?php echo e($product['id']); ?>', 'in')" class="flex-1 h-10 border border-black bg-white font-black text-[11px] uppercase tracking-widest hover:bg-black hover:text-white transition-all">Stock In</button>
                        <button onclick="openStockAdjustModal('<?php echo e($product['id']); ?>', 'out')" class="flex-1 h-10 border border-black bg-white font-black text-[11px] uppercase tracking-widest hover:bg-black hover:text-white transition-all">Stock Out</button>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="toggleInventoryHistory('<?php echo e($product['id']); ?>')"
                                class="flex-1 h-9 border border-slate-200 bg-white font-semibold text-[10px] uppercase tracking-widest text-slate-600 hover:text-black hover:border-slate-400 transition-all">
                            History
                        </button>
                        <a href="?page=inventory-history&productId=<?php echo e($product['id']); ?>"
                           class="flex-1 h-9 border border-slate-200 bg-white font-semibold text-[10px] uppercase tracking-widest text-slate-600 hover:text-black hover:border-slate-400 transition-all text-center flex items-center justify-center">
                            View All
                        </a>
                    </div>
                    <div id="inventory-history-<?php echo e($product['id']); ?>" data-loaded="0" class="hidden mt-3 space-y-2 text-xs text-slate-600"></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<!-- Inventory Summary Off-Canvas -->
<div id="inventory-summary-panel" class="fixed inset-y-0 right-0 w-full max-w-lg bg-white border-l border-slate-200 shadow-2xl transform translate-x-full transition-transform duration-300 z-50">
    <div class="p-6 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Inventory Summary</h3>
            <p class="text-xs text-slate-500">Totals and recent stock moves</p>
        </div>
        <button onclick="closeInventorySummary()" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
    <div class="p-6 space-y-6 overflow-y-auto h-full">
        <div class="grid grid-cols-2 gap-4">
            <div class="border border-slate-200 rounded-xl p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total In</p>
                <p class="text-xl font-bold text-gray-900" id="summary-total-in">-</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Out</p>
                <p class="text-xl font-bold text-gray-900" id="summary-total-out">-</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Expense</p>
                <p class="text-xl font-bold text-gray-900" id="summary-total-expense">-</p>
            </div>
            <div class="border border-slate-200 rounded-xl p-4">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Inventory Value</p>
                <p class="text-xl font-bold text-gray-900"><?php echo formatCurrency($totalValue); ?></p>
            </div>
        </div>
        <div>
            <h4 class="text-sm font-bold text-gray-900 mb-3">Recent Moves</h4>
            <div id="summary-transactions" class="space-y-2 text-sm text-slate-600">Loading...</div>
        </div>
    </div>
</div>

<!-- AI Generate Inventory Modal -->
<div id="ai-inventory-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white border-2 border-black rounded-xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto shadow-[8px_8px_0px_0px_rgba(0,0,0,0.3)]">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-900">AI Generate Inventory</h3>
                    <p class="text-sm text-slate-500">Describe items to generate inventory suggestions</p>
                </div>
            </div>
            <button onclick="closeAIGenerateInventory()" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-500 mb-2 uppercase tracking-widest">Description</label>
                <textarea id="ai-inventory-description" rows="3" placeholder="e.g., Office supplies including pens, notebooks, staplers, paper clips..." class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-black outline-none transition-colors"></textarea>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-500 mb-2 uppercase tracking-widest">Category</label>
                <select id="ai-inventory-category" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-black outline-none transition-colors">
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

            <button onclick="generateInventory()" id="ai-inventory-generate-btn" class="w-full py-4 bg-black text-white font-bold rounded-xl hover:bg-slate-800 transition shadow-lg flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Generate Inventory
            </button>
        </div>

        <!-- Generated Items Container -->
        <div id="ai-inventory-results" class="mt-6 space-y-3 hidden">
            <div class="flex items-center justify-between">
                <h4 class="font-bold text-gray-900">Generated Items</h4>
                <button onclick="saveAllInventoryItems()" class="px-4 py-2 bg-black text-white text-sm font-bold rounded-lg hover:bg-slate-800 transition">
                    Save All
                </button>
            </div>
            <div id="ai-inventory-items" class="space-y-3 max-h-80 overflow-y-auto"></div>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stock-adjust-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
    <div class="bg-white border-2 border-black rounded-xl p-6 w-full max-w-md mx-4 shadow-[8px_8px_0px_0px_rgba(0,0,0,0.2)]">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900" id="stock-adjust-title">Adjust Stock</h3>
            <button onclick="closeStockAdjustModal()" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Quantity</label>
                <input id="stock-adjust-qty" type="number" min="1" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-black outline-none" value="1">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Unit Cost</label>
                <input id="stock-adjust-cost" type="number" step="0.01" min="0" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-black outline-none">
                <p class="text-xs text-slate-400 mt-1">Uses product cost price by default.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Note</label>
                <input id="stock-adjust-note" type="text" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:border-black outline-none" placeholder="Optional note">
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="submitStockAdjust()" class="flex-1 py-3 bg-black text-white rounded-xl font-bold">Save</button>
            <button onclick="closeStockAdjustModal()" class="flex-1 py-3 bg-white border border-slate-200 rounded-xl font-bold text-slate-700">Cancel</button>
        </div>
    </div>
</div>

<script>
let stockAdjustState = {
    productId: null,
    direction: 'in'
};

function openStockAdjustModal(productId, direction) {
    stockAdjustState.productId = productId;
    stockAdjustState.direction = direction;

    const title = document.getElementById('stock-adjust-title');
    const qtyInput = document.getElementById('stock-adjust-qty');
    const costInput = document.getElementById('stock-adjust-cost');
    const noteInput = document.getElementById('stock-adjust-note');

    if (title) {
        title.textContent = direction === 'in' ? 'Stock In' : 'Stock Out';
    }
    if (qtyInput) qtyInput.value = 1;
    if (costInput) costInput.value = '';
    if (noteInput) noteInput.value = '';

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
    const qty = parseInt(document.getElementById('stock-adjust-qty').value || '0', 10);
    const unitCostValue = document.getElementById('stock-adjust-cost').value;
    const note = document.getElementById('stock-adjust-note').value || '';

    if (!qty || qty <= 0) {
        showToast('Enter a valid quantity', 'warning');
        return;
    }

    const adjustment = stockAdjustState.direction === 'in' ? qty : -qty;
    const payload = {
        id: stockAdjustState.productId,
        adjustment: adjustment,
        note: note,
        csrf_token: CSRF_TOKEN
    };
    if (unitCostValue !== '') {
        payload.unitCost = parseFloat(unitCostValue);
    }

    const response = await api.post('api/inventory.php?action=adjust', payload);

    if (response.success) {
        showToast('Stock updated!', 'success');
        closeStockAdjustModal();
        setTimeout(() => location.reload(), 300);
    } else {
        showToast(response.error || 'Update failed', 'error');
    }
}

function openInventorySummary() {
    const panel = document.getElementById('inventory-summary-panel');
    panel.classList.remove('translate-x-full');
    loadInventorySummary();
}

function closeInventorySummary() {
    const panel = document.getElementById('inventory-summary-panel');
    panel.classList.add('translate-x-full');
}

async function loadInventorySummary() {
    const response = await api.get('api/inventory.php?action=summary');
    if (response.success) {
        const data = response.data || {};
        document.getElementById('summary-total-in').textContent = data.total_in ?? 0;
        document.getElementById('summary-total-out').textContent = data.total_out ?? 0;
        document.getElementById('summary-total-expense').textContent = formatCurrency(data.total_expense ?? 0);

        const list = document.getElementById('summary-transactions');
        const recent = data.recent || [];
        if (!recent.length) {
            list.innerHTML = '<p class="text-sm text-slate-400">No transactions yet.</p>';
        } else {
            list.innerHTML = recent.map(entry => {
                const typeLabel = entry.type === 'in' ? 'IN' : 'OUT';
                const color = entry.type === 'in' ? 'text-emerald-600' : 'text-red-600';
                const date = new Date(entry.createdAt).toLocaleString();
                return `
                    <div class="flex items-start justify-between border border-slate-100 rounded-lg p-3">
                        <div>
                            <p class="font-medium text-gray-900">${entry.productName || 'Item'}</p>
                            <p class="text-xs text-slate-500">${date}</p>
                            ${entry.note ? `<p class="text-xs text-slate-400">${entry.note}</p>` : ''}
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-black ${color}">${typeLabel}</p>
                            <p class="font-bold text-gray-900">${entry.quantity}</p>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }
}

async function loadInventoryTotals() {
    try {
        const response = await api.get('api/inventory.php?action=summary');
        if (response.success) {
            const data = response.data || {};
            // Only update if elements exist (they're in the summary panel)
            const totalInEl = document.getElementById('summary-total-in');
            const totalOutEl = document.getElementById('summary-total-out');
            const totalExpenseEl = document.getElementById('summary-total-expense');

            if (totalInEl) totalInEl.textContent = data.total_in ?? 0;
            if (totalOutEl) totalOutEl.textContent = data.total_out ?? 0;
            if (totalExpenseEl) totalExpenseEl.textContent = formatCurrency(data.total_expense ?? 0);
        }
    } catch (error) {
        console.error('Failed to load inventory totals:', error);
    }
}

async function toggleInventoryHistory(productId) {
    const container = document.getElementById(`inventory-history-${productId}`);
    if (!container) return;

    if (container.classList.contains('hidden')) {
        container.classList.remove('hidden');
        if (container.dataset.loaded === '1') {
            return;
        }
        container.innerHTML = '<p class="text-slate-400">Loading history...</p>';
        const response = await api.get(`api/inventory.php?action=transactions&productId=${encodeURIComponent(productId)}&limit=5`);
        if (!response.success) {
            container.innerHTML = '<p class="text-red-500">Failed to load history.</p>';
            return;
        }

        const history = response.data || [];
        if (!history.length) {
            container.innerHTML = '<p class="text-slate-400">No history yet.</p>';
            container.dataset.loaded = '1';
            return;
        }

        container.innerHTML = history.map(entry => {
            const typeLabel = entry.type === 'in' ? 'IN' : 'OUT';
            const color = entry.type === 'in' ? 'text-emerald-600' : 'text-red-600';
            const date = new Date(entry.createdAt).toLocaleString();
            const cost = entry.totalCost ? formatCurrency(entry.totalCost) : '-';
            return `
                <div class="border border-slate-100 rounded-lg p-3 flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black ${color}">${typeLabel}</p>
                        <p class="text-xs text-slate-500">${date}</p>
                        ${entry.note ? `<p class="text-[10px] text-slate-400">${entry.note}</p>` : ''}
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900">${entry.quantity}</p>
                        <p class="text-[10px] text-slate-400">${cost}</p>
                    </div>
                </div>
            `;
        }).join('');

        container.dataset.loaded = '1';
    } else {
        container.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadInventoryTotals();
});

// AI Inventory Generation
let generatedInventoryItems = [];

function openAIGenerateInventory() {
    document.getElementById('ai-inventory-modal').classList.remove('hidden');
    document.getElementById('ai-inventory-modal').classList.add('flex');
    document.getElementById('ai-inventory-description').value = '';
    document.getElementById('ai-inventory-results').classList.add('hidden');
    generatedInventoryItems = [];
}

function closeAIGenerateInventory() {
    document.getElementById('ai-inventory-modal').classList.add('hidden');
    document.getElementById('ai-inventory-modal').classList.remove('flex');
}

async function generateInventory() {
    const description = document.getElementById('ai-inventory-description').value.trim();
    const category = document.getElementById('ai-inventory-category').value;

    if (!description) {
        showToast('Please describe the inventory items you need', 'info');
        return;
    }

    const btn = document.getElementById('ai-inventory-generate-btn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Generating...';

    try {
        // Get model info
        const modelsResponse = await api.get('api/models.php');
        const models = modelsResponse.data || {};
        const groqModels = models?.groq || [];
        const defaultModel = groqModels.find(m => m.isDefault) || groqModels[0];
        const model = defaultModel?.modelId;
        if (!model) {
            showToast('No AI model configured. Please set up a model in Model Settings.', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        const response = await api.post('api/ai.php?action=generate_inventory', {
            description: description,
            category: category,
            provider: 'groq',
            model: model,
            csrf_token: CSRF_TOKEN
        });

        if (response.success && Array.isArray(response.data?.items)) {
            generatedInventoryItems = response.data.items;
            renderGeneratedInventory(response.data.items);
            document.getElementById('ai-inventory-results').classList.remove('hidden');
            showToast('Inventory generated!', 'success');
        } else {
            showToast('Failed to generate inventory', 'error');
        }
    } catch (error) {
        console.error('Inventory generation error:', error);
        let errorMsg = 'Failed to generate inventory';
        if (error.message) {
            errorMsg += ': ' + error.message;
        } else if (error.response?.error?.message) {
            errorMsg += ': ' + error.response.error.message;
        } else if (error.response?.message) {
            errorMsg += ': ' + error.response.message;
        }
        showToast(errorMsg, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function renderGeneratedInventory(items) {
    const container = document.getElementById('ai-inventory-items');
    container.innerHTML = '';

    items.forEach((item, index) => {
        const div = document.createElement('div');
        div.className = 'bg-slate-50 rounded-xl p-4 hover:bg-slate-100 transition cursor-pointer';
        div.innerHTML = `
            <div class="flex items-start gap-3">
                <input type="checkbox" id="inventory-item-${index}" class="w-5 h-5 border-2 border-slate-300 rounded bg-white cursor-pointer mt-1" onchange="updateSelectionState()">
                <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-slate-400 border border-slate-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-gray-900 text-sm">${item.name || 'Unnamed Item'}</p>
                    <p class="text-xs text-slate-500 truncate">${item.description || 'No description'}</p>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="px-2 py-0.5 bg-white border border-slate-200 rounded text-xs text-slate-600">${item.sku || 'No SKU'}</span>
                        <span class="px-2 py-0.5 bg-white border border-slate-200 rounded text-xs text-slate-600">$${(item.price || 0).toFixed(2)}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="saveInventoryItem(${index})" class="px-3 py-1 bg-black text-white text-xs font-bold rounded-lg hover:bg-slate-800 transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Save
                    </button>
                    <button onclick="removeInventoryItem(${index})" class="px-3 py-1 bg-white border border-slate-200 text-slate-400 text-xs font-bold rounded-lg hover:border-red-300 hover:text-red-600 transition flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(div);
    });

    if (items.length > 1) {
        const controlsDiv = document.createElement('div');
        controlsDiv.innerHTML = `
            <div class="flex gap-3 mb-3">
                <button onclick="selectAllInventory()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg font-bold hover:border-black hover:text-black transition text-xs">
                    Select All
                </button>
                <button onclick="deselectAllInventory()" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg font-bold hover:border-black hover:text-black transition text-xs">
                    Deselect All
                </button>
                <button onclick="saveSelectedInventoryItems()" class="flex-1 py-2 bg-black text-white font-bold rounded-lg hover:bg-slate-800 transition flex items-center justify-center gap-2 text-xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Save Selected
                </button>
            </div>
            <button onclick="saveAllInventoryItems()" class="w-full py-3 bg-slate-100 border border-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition flex items-center justify-center gap-2 text-xs">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Save All ${items.length} Items
            </button>
        `;
        container.insertBefore(controlsDiv, container.firstChild);
    }
}

function updateSelectionState() {
    const checkboxes = document.querySelectorAll('[id^="inventory-item-"]');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    const someChecked = Array.from(checkboxes).some(cb => cb.checked);
}

function selectAllInventory() {
    const checkboxes = document.querySelectorAll('[id^="inventory-item-"]');
    checkboxes.forEach(cb => cb.checked = true);
    updateSelectionState();
}

function deselectAllInventory() {
    const checkboxes = document.querySelectorAll('[id^="inventory-item-"]');
    checkboxes.forEach(cb => cb.checked = false);
    updateSelectionState();
}

async function removeInventoryItem(index) {
    generatedInventoryItems.splice(index, 1);
    if (generatedInventoryItems.length > 0) {
        renderGeneratedInventory(generatedInventoryItems);
    } else {
        document.getElementById('ai-inventory-results').classList.add('hidden');
    }
}

async function saveSelectedInventoryItems() {
    const checkboxes = document.querySelectorAll('[id^="inventory-item-"]:checked');
    const selectedIndices = Array.from(checkboxes).map(cb => parseInt(cb.id.replace('inventory-item-', '')));

    if (selectedIndices.length === 0) {
        showToast('Please select at least one item', 'warning');
        return;
    }

    let saved = 0;
    let failed = 0;

    for (const index of selectedIndices) {
        const item = generatedInventoryItems[index];
        if (!item) continue;

        try {
            const response = await api.post('api/ai.php?action=create_inventory_items', {
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
        showToast(`${saved} item(s) saved!`, 'success');
        const remainingItems = generatedInventoryItems.filter((_, idx) => !selectedIndices.includes(idx));
        if (remainingItems.length > 0) {
            generatedInventoryItems = remainingItems;
            renderGeneratedInventory(generatedInventoryItems);
        } else {
            document.getElementById('ai-inventory-results').classList.add('hidden');
        }
    }
    if (failed > 0) {
        showToast(`${failed} item(s) failed to save`, 'error');
    }
}

async function saveInventoryItem(index) {
    const item = generatedInventoryItems[index];

    try {
        const response = await api.post('api/ai.php?action=create_inventory_items', {
            items: [item],
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Item saved!', 'success');
            // Remove from list
            generatedInventoryItems.splice(index, 1);
            if (generatedInventoryItems.length > 0) {
                renderGeneratedInventory(generatedInventoryItems);
            } else {
                document.getElementById('ai-inventory-results').classList.add('hidden');
            }
        } else {
            showToast(response.error || 'Failed to save item', 'error');
        }
    } catch (error) {
        showToast('Failed to save item', 'error');
    }
}

async function saveAllInventoryItems() {
    if (generatedInventoryItems.length === 0) return;

    let saved = 0;
    let failed = 0;

    for (const item of generatedInventoryItems) {
        try {
            const response = await api.post('api/ai.php?action=create_inventory_items', {
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
        showToast(`${saved} item(s) saved!`, 'success');
        closeAIGenerateInventory();
        setTimeout(() => location.reload(), 500);
    }
    if (failed > 0) {
        showToast(`${failed} item(s) failed to save`, 'error');
    }
}

async function deleteInventoryItem(id) {
    confirmAction('Are you sure you want to delete this inventory item?', async () => {
        try {
            const response = await api.delete('api/inventory.php?id=' + id);
            if (response.success) {
                showToast('Inventory item deleted!', 'success');
                location.reload();
            } else {
                showToast(response.error || 'Failed to delete item', 'error');
            }
        } catch (error) {
            console.error('Failed to delete inventory item:', error);
            showToast('Failed to delete item', 'error');
        }
    });
}
</script>

