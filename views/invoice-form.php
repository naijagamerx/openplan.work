<?php
/**
 * Invoice Form View (Add/Edit)
 */
$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;
$invoice = null;

$clients = $db->load('clients');
$projects = $db->load('projects');
$config = $db->load('config');

if ($id) {
    $invoices = $db->load('invoices');
    foreach ($invoices as $i) {
        if ($i['id'] === $id) {
            $invoice = $i;
            break;
        }
    }
}

$title = $id ? 'Edit Invoice' : 'New Invoice';
$taxRate = floatval($config['taxRate'] ?? 0);
$currencySymbol = getCurrencySymbol($config['currency'] ?? 'USD');
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=invoices" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
    </div>

    <form id="invoice-form" class="space-y-8">
        <input type="hidden" name="id" value="<?php echo e($id); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Left Column: Details -->
            <div class="md:col-span-2 space-y-8">
                <!-- Top Info Card -->
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Client</label>
                            <select name="clientId" required class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-white font-bold">
                                <option value="">Select a client...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo e($client['id']); ?>" <?php echo ($invoice['clientId'] ?? '') === $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($client['name']); ?> (<?php echo e($client['company'] ?? 'N/A'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Linked Project (Optional)</label>
                            <select name="projectId" id="project-selector" class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-white font-medium">
                                <option value="">No linked project</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo e($project['id']); ?>" <?php echo ($invoice['projectId'] ?? '') === $project['id'] ? 'selected' : ''; ?>>
                                        <?php echo e($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Due Date</label>
                            <input type="date" name="dueDate" value="<?php echo e($invoice['dueDate'] ?? date('Y-m-d', strtotime('+30 days'))); ?>" required
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Invoice #</label>
                            <input type="text" value="<?php echo e($invoice['invoiceNumber'] ?? 'Auto-generated'); ?>" readonly
                                   class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-xl text-gray-400 font-mono cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <!-- Line Items Card -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Line Items</h3>
                        <div class="flex gap-2">
                             <button type="button" id="ai-generate-items" class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-blue-100 transition">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
                                AI Suggest
                            </button>
                            <button type="button" id="add-item" class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 text-gray-600 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Add Row
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50">
                                <tr class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    <th class="px-6 py-4">Description</th>
                                    <th class="px-6 py-4 w-24 text-center">Qty</th>
                                    <th class="px-6 py-4 w-40 text-right">Price</th>
                                    <th class="px-6 py-4 w-40 text-right">Total</th>
                                    <th class="px-6 py-4 w-16"></th>
                                </tr>
                            </thead>
                            <tbody id="line-items-body" class="divide-y divide-gray-50">
                                <!-- Items will be injected here -->
                            </tbody>
                        </table>
                    </div>
                    <?php if (empty($invoice['lineItems'])): ?>
                        <div id="empty-items-state" class="p-12 text-center">
                            <p class="text-sm font-medium text-gray-400">No items added yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <label class="block text-sm font-bold text-gray-400 mb-4 uppercase tracking-widest">Notes & Terms</label>
                    <textarea name="notes" rows="4" 
                              placeholder="Payment instructions, bank details, or a thank you note..."
                              class="w-full px-4 py-4 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-all min-h-[120px] font-medium"><?php echo e($invoice['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Right Column: Summary & Actions -->
            <div class="space-y-8">
                <div class="bg-black text-white rounded-2xl p-8 shadow-xl sticky top-8">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400 mb-8 border-b border-white/10 pb-4">Invoice Summary</h3>
                    
                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 font-bold uppercase tracking-widest text-[10px]">Subtotal</span>
                            <span id="summary-subtotal" class="font-bold text-lg"><?php echo $currencySymbol; ?>0.00</span>
                        </div>
                        <div class="flex justify-between items-center pb-4 border-b border-white/10">
                            <span class="text-gray-400 font-bold uppercase tracking-widest text-[10px]">Tax (<?php echo $taxRate; ?>%)</span>
                            <span id="summary-tax" class="font-bold text-lg"><?php echo $currencySymbol; ?>0.00</span>
                        </div>
                        <div class="flex justify-between items-center pt-4">
                            <span class="text-blue-400 font-black uppercase tracking-widest text-xs">Grand Total</span>
                            <span id="summary-total" class="font-black text-3xl"><?php echo $currencySymbol; ?>0.00</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button type="submit" class="w-full py-4 bg-white text-black rounded-2xl font-black uppercase tracking-widest hover:bg-gray-100 transition shadow-lg">
                            <?php echo $id ? 'Update Invoice' : 'Create & Save'; ?>
                        </button>
                        <a href="?page=invoices" class="block w-full py-4 bg-white/10 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-white/20 transition text-center text-xs">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let items = <?php echo json_encode($invoice['lineItems'] ?? []); ?>;
const TAX_RATE = <?php echo $taxRate; ?>;
const CURRENCY_SYMBOL = '<?php echo $currencySymbol; ?>';

function renderItems() {
    const body = document.getElementById('line-items-body');
    const emptyState = document.getElementById('empty-items-state');
    body.innerHTML = '';
    
    if (items.length === 0) {
        if (emptyState) emptyState.classList.remove('hidden');
    } else {
        if (emptyState) emptyState.classList.add('hidden');
        items.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'group hover:bg-gray-50/50 transition-colors';
            row.innerHTML = `
                <td class="px-6 py-4">
                    <input type="text" value="${item.description || ''}" onchange="updateItem(${index}, 'description', this.value)"
                           placeholder="Service description..."
                           class="w-full bg-transparent border-none focus:ring-0 font-bold text-gray-900 p-0">
                </td>
                <td class="px-6 py-4">
                    <input type="number" value="${item.quantity || 1}" step="any" onchange="updateItem(${index}, 'quantity', this.value)"
                           class="w-full bg-transparent border-none focus:ring-0 text-center font-bold text-gray-900 p-0">
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center justify-end">
                        <span class="text-gray-400 text-xs mr-1">${CURRENCY_SYMBOL}</span>
                        <input type="number" value="${item.unitPrice || 0}" step="0.01" onchange="updateItem(${index}, 'unitPrice', this.value)"
                               class="w-full bg-transparent border-none focus:ring-0 text-right font-bold text-gray-900 p-0 max-w-[100px]">
                    </div>
                </td>
                <td class="px-6 py-4 text-right font-black text-gray-900">
                    ${CURRENCY_SYMBOL}${(item.quantity * item.unitPrice).toFixed(2)}
                </td>
                <td class="px-6 py-4 text-right">
                    <button type="button" onclick="removeItem(${index})" class="text-gray-300 hover:text-red-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            `;
            body.appendChild(row);
        });
    }
    calculateTotals();
}

function calculateTotals() {
    const subtotal = items.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
    const tax = subtotal * (TAX_RATE / 100);
    const total = subtotal + tax;
    
    document.getElementById('summary-subtotal').textContent = `${CURRENCY_SYMBOL}${subtotal.toFixed(2)}`;
    document.getElementById('summary-tax').textContent = `${CURRENCY_SYMBOL}${tax.toFixed(2)}`;
    document.getElementById('summary-total').textContent = `${CURRENCY_SYMBOL}${total.toFixed(2)}`;
}

function addItem() {
    items.push({ description: '', quantity: 1, unitPrice: 0 });
    renderItems();
}

function updateItem(index, field, value) {
    items[index][field] = field === 'description' ? value : parseFloat(value) || 0;
    renderItems();
}

function removeItem(index) {
    items.splice(index, 1);
    renderItems();
}

document.getElementById('add-item').addEventListener('click', addItem);

document.getElementById('ai-generate-items').addEventListener('click', async () => {
    const projectId = document.getElementById('project-selector').value;
    if (!projectId) {
        showToast('Please select a project first to suggest items.', 'info');
        return;
    }
    
    showToast('AI is dreaming up line items...', 'info');
    const response = await api.post('api/ai-generate.php?action=invoice_items', {
        projectId: projectId
    });
    
    if (response.success && response.data.items) {
        if (items.length > 0 && !confirm('Add AI suggestions to current items?')) {
            return;
        }
        items = [...items, ...response.data.items];
        renderItems();
        showToast('AI suggestions added!', 'success');
    } else {
        showToast(response.error || 'Failed to generate suggestions', 'error');
    }
});

document.getElementById('invoice-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (items.length === 0) {
        showToast('Please add at least one line item.', 'error');
        return;
    }
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Convert keys for API compatibility if needed
    const subtotal = items.reduce((sum, item) => sum + (item.quantity * item.unitPrice), 0);
    const taxAmount = subtotal * (TAX_RATE / 100);
    const total = subtotal + taxAmount;

    const payload = {
        clientId: data.clientId,
        projectId: data.projectId,
        dueDate: data.dueDate,
        notes: data.notes,
        lineItems: items.map(i => ({
            description: i.description,
            quantity: i.quantity,
            unitPrice: i.unitPrice,
            total: i.quantity * i.unitPrice
        })),
        subtotal: subtotal,
        taxRate: TAX_RATE,
        taxAmount: taxAmount,
        total: total,
        currency: '<?php echo $config['currency'] ?? 'USD'; ?>',
        csrf_token: CSRF_TOKEN
    };
    
    let response;
    if (data.id && data.id !== '') {
        response = await api.put(`api/invoices.php?id=${data.id}`, payload);
    } else {
        response = await api.post('api/invoices.php', payload);
    }
    
    if (response.success) {
        showToast('Invoice saved!', 'success');
        setTimeout(() => location.href = '?page=invoices', 1000);
    } else {
        showToast(response.error || 'Failed to save invoice', 'error');
    }
});

// Initial render
renderItems();
</script>
