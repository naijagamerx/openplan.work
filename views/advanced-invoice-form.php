<?php
/**
 * Advanced Invoice Form View (Create/Edit)
 */
$db = new Database(getMasterPassword(), Auth::userId());
$id = $_GET['id'] ?? null;
$invoice = null;

$config = $db->load('config');

if ($id) {
    $invoices = $db->load('advanced_invoices');
    foreach ($invoices as $i) {
        if ($i['id'] === $id) {
            $invoice = $i;
            break;
        }
    }
}

$title = $id ? 'Edit Advanced Invoice' : 'New Advanced Invoice';
$currencySymbol = getCurrencySymbol($config['currency'] ?? 'ZAR');
?>
<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=advanced-invoices" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
    </div>

    <form id="invoice-form" class="space-y-8">
        <input type="hidden" name="id" value="<?php echo e($id); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
        <input type="hidden" name="template" value="<?php echo e($invoice['template'] ?? 'classic'); ?>">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Company Header Section -->
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Company Header</h3>
                        <button type="button" onclick="openCustomFieldModal('companyHeader')" class="text-xs font-bold text-gray-400 hover:text-black transition flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Custom Field
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Logo URL</label>
                            <input type="url" name="companyLogo" value="<?php echo e($invoice['companyHeader']['logoUrl'] ?? ''); ?>"
                                   placeholder="https://example.com/logo.png"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Invoice Date</label>
                            <input type="date" name="invoiceDate" value="<?php echo e($invoice['invoiceDate'] ?? date('Y-m-d')); ?>"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Company Name</label>
                            <input type="text" name="companyName" value="<?php echo e($invoice['companyHeader']['companyName'] ?? ''); ?>"
                                   placeholder="Your Company Name"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Email</label>
                            <input type="email" name="companyEmail" value="<?php echo e($invoice['companyHeader']['companyEmail'] ?? ''); ?>"
                                   placeholder="billing@company.com"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Phone</label>
                            <input type="tel" name="companyPhone" value="<?php echo e($invoice['companyHeader']['companyPhone'] ?? ''); ?>"
                                   placeholder="+1 (555) 123-4567"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Address</label>
                            <input type="text" name="companyAddress" value="<?php echo e($invoice['companyHeader']['companyAddress'] ?? ''); ?>"
                                   placeholder="123 Business St, City, State 12345"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                    </div>
                    <div id="custom-fields-companyHeader" class="mt-6 space-y-4"></div>
                </div>

                <!-- Customer Section -->
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Customer Details</h3>
                        <button type="button" onclick="openCustomFieldModal('customer')" class="text-xs font-bold text-gray-400 hover:text-black transition flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Custom Field
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Customer Name</label>
                            <input type="text" name="customerName" value="<?php echo e($invoice['customer']['name'] ?? ''); ?>"
                                   placeholder="John Doe"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Customer ID</label>
                            <input type="text" name="customerId" value="<?php echo e($invoice['customer']['customerId'] ?? ''); ?>"
                                   placeholder="CUST-001"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Email</label>
                            <input type="email" name="customerEmail" value="<?php echo e($invoice['customer']['email'] ?? ''); ?>"
                                   placeholder="john@example.com"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Phone</label>
                            <input type="tel" name="customerPhone" value="<?php echo e($invoice['customer']['phone'] ?? ''); ?>"
                                   placeholder="+1 (555) 987-6543"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Property Address</label>
                            <textarea name="propertyAddress" rows="2"
                                      placeholder="123 Customer Rd, Customer City, ST 67890"
                                      class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium resize-none"><?php echo e($invoice['customer']['propertyAddress'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Contract From</label>
                            <input type="date" name="contractFrom" value="<?php echo e($invoice['customer']['contractFrom'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Contract To</label>
                            <input type="date" name="contractTo" value="<?php echo e($invoice['customer']['contractTo'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                    </div>
                    <div id="custom-fields-customer" class="mt-6 space-y-4"></div>
                </div>

                <!-- Line Items Section -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Line Items</h3>
                        <button type="button" onclick="addLineItem()" class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 text-gray-600 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Row
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50/50">
                                <tr class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Reference</th>
                                    <th class="px-6 py-4">Description</th>
                                    <th class="px-6 py-4 w-40 text-right">Amount</th>
                                    <th class="px-6 py-4 w-16"></th>
                                </tr>
                            </thead>
                            <tbody id="line-items-body" class="divide-y divide-gray-50"></tbody>
                        </table>
                    </div>
                    <?php if (empty($invoice['lineItems'])): ?>
                        <div id="empty-items-state" class="p-12 text-center">
                            <p class="text-sm font-medium text-gray-400">No items added yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Payment Details Section -->
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Payment Details</h3>
                        <button type="button" onclick="openCustomFieldModal('paymentDetails')" class="text-xs font-bold text-gray-400 hover:text-black transition flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Custom Field
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Bank Name</label>
                            <input type="text" name="bankName" value="<?php echo e($invoice['paymentDetails']['bankName'] ?? ''); ?>"
                                   placeholder="First National Bank"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Account Number</label>
                            <input type="text" name="accountNumber" value="<?php echo e($invoice['paymentDetails']['accountNumber'] ?? ''); ?>"
                                   placeholder="1234567890"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Branch Code</label>
                            <input type="text" name="branchCode" value="<?php echo e($invoice['paymentDetails']['branchCode'] ?? ''); ?>"
                                   placeholder="123-456"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Payment Reference</label>
                            <input type="text" name="paymentReference" value="<?php echo e($invoice['paymentDetails']['paymentReference'] ?? ''); ?>"
                                   placeholder="INV-001"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Due Date</label>
                            <input type="date" name="dueDate" value="<?php echo e($invoice['paymentDetails']['dueDate'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Currency</label>
                            <select name="currency" class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                                <option value="ZAR" <?php echo (($invoice['currency'] ?? 'ZAR') === 'ZAR') ? 'selected' : ''; ?>>ZAR (R)</option>
                                <option value="USD" <?php echo (($invoice['currency'] ?? '') === 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                <option value="EUR" <?php echo (($invoice['currency'] ?? '') === 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                                <option value="GBP" <?php echo (($invoice['currency'] ?? '') === 'GBP') ? 'selected' : ''; ?>>GBP (£)</option>
                            </select>
                        </div>
                    </div>
                    <div id="custom-fields-paymentDetails" class="mt-6 space-y-4"></div>
                </div>

                <!-- Notes & Footer -->
                <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-4 uppercase tracking-widest">Notes (Optional)</label>
                            <textarea name="notes" rows="3"
                                      placeholder="Thank you for your business..."
                                      class="w-full px-4 py-4 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-all min-h-[100px] font-medium resize-none"><?php echo e($invoice['notes'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-400 mb-4 uppercase tracking-widest">Footer Text (Optional)</label>
                            <textarea name="footerText" rows="2"
                                      placeholder="No refunds. All payments are final."
                                      class="w-full px-4 py-4 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-all min-h-[80px] font-medium resize-none"><?php echo e($invoice['footerText'] ?? ''); ?></textarea>
                            <p class="text-xs text-gray-400 mt-2">This text will appear at the bottom of the printed invoice.</p>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column: Summary -->
            <div class="space-y-8">
                <div class="bg-black text-white rounded-2xl p-8 shadow-xl sticky top-8">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400 mb-8 border-b border-white/10 pb-4">Invoice Summary</h3>

                    <div class="space-y-4 mb-8">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400 font-bold uppercase tracking-widest text-[10px]">Invoice #</span>
                            <span id="summary-number" class="font-bold text-sm"><?php echo e($invoice['invoiceNumber'] ?? 'Auto-generated'); ?></span>
                        </div>
                        <div class="flex justify-between items-center pt-4 border-t border-white/10">
                            <span class="text-gray-400 font-bold uppercase tracking-widest text-[10px]">Total Due</span>
                            <span id="summary-total" class="font-black text-2xl"><?php echo $currencySymbol; ?>0.00</span>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button type="submit" class="w-full py-4 bg-white text-black rounded-2xl font-black uppercase tracking-widest hover:bg-gray-100 transition shadow-lg">
                            <?php echo $id ? 'Update Invoice' : 'Create & Save'; ?>
                        </button>
                        <a href="?page=advanced-invoices" class="block w-full py-4 bg-white/10 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-white/20 transition text-center text-xs">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Custom Field Modal -->
<div id="custom-field-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-2xl">
        <h3 class="text-lg font-black text-gray-900 mb-6">Add Custom Field</h3>
        <input type="hidden" id="custom-field-section">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Field Label</label>
                <input type="text" id="custom-field-label" placeholder="e.g., VAT Number" class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Field Type</label>
                <select id="custom-field-type" class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="textarea">Text Area</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Default Value</label>
                <input type="text" id="custom-field-value" placeholder="Optional default value" class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="custom-field-show-print" checked class="w-4 h-4 rounded border-gray-300">
                <label for="custom-field-show-print" class="text-sm font-medium text-gray-700">Show on Print</label>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="addCustomField()" class="flex-1 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition">Add Field</button>
            <button onclick="closeCustomFieldModal()" class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl font-bold hover:bg-gray-200 transition">Cancel</button>
        </div>
    </div>
</div>

<script>
let lineItems = <?php echo json_encode($invoice['lineItems'] ?? []); ?>;
let customFields = <?php echo json_encode($invoice['customFields'] ?? []); ?>;
const CURRENCY_SYMBOL = '<?php echo $currencySymbol; ?>';

// Initialize custom fields display
function initCustomFields() {
    customFields.forEach((field, index) => {
        renderCustomField(field, index);
    });
}

function renderCustomField(field, index) {
    const container = document.getElementById(`custom-fields-${field.section}`);
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'flex items-center gap-3 p-3 bg-gray-50 rounded-xl';
    div.innerHTML = `
        <div class="flex-1">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest">${field.label}</label>
            <input type="text" value="${field.value || ''}" onchange="updateCustomFieldValue(${index}, this.value)"
                   class="w-full bg-transparent border-none focus:ring-0 font-medium text-gray-900 p-0" placeholder="Enter ${field.label}">
        </div>
        <button type="button" onclick="removeCustomField(${index})" class="text-gray-300 hover:text-red-500 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;
    container.appendChild(div);
}

function updateCustomFieldValue(index, value) {
    customFields[index].value = value;
}

function removeCustomField(index) {
    customFields.splice(index, 1);
    const field = customFields[index];
    const container = document.getElementById(`custom-fields-${field.section}`);
    container.innerHTML = '';
    customFields.forEach((f, i) => {
        if (f.section === field.section) renderCustomField(f, i);
    });
}

function openCustomFieldModal(section) {
    document.getElementById('custom-field-section').value = section;
    document.getElementById('custom-field-modal').classList.remove('hidden');
}

function closeCustomFieldModal() {
    document.getElementById('custom-field-modal').classList.add('hidden');
    document.getElementById('custom-field-label').value = '';
    document.getElementById('custom-field-value').value = '';
}

function addCustomField() {
    const section = document.getElementById('custom-field-section').value;
    const label = document.getElementById('custom-field-label').value;
    const type = document.getElementById('custom-field-type').value;
    const value = document.getElementById('custom-field-value').value;
    const showOnPrint = document.getElementById('custom-field-show-print').checked;

    if (!label) {
        showToast('Please enter a field label', 'error');
        return;
    }

    customFields.push({ label, value, section, type, showOnPrint });
    renderCustomField(customFields[customFields.length - 1], customFields.length - 1);
    closeCustomFieldModal();
}

// Line items management
function renderLineItems() {
    const body = document.getElementById('line-items-body');
    const emptyState = document.getElementById('empty-items-state');
    body.innerHTML = '';

    if (lineItems.length === 0) {
        if (emptyState) emptyState.classList.remove('hidden');
    } else {
        if (emptyState) emptyState.classList.add('hidden');
        lineItems.forEach((item, index) => {
            const row = document.createElement('tr');
            row.className = 'group hover:bg-gray-50/50 transition-colors';
            row.innerHTML = `
                <td class="px-6 py-4">
                    <input type="date" value="${item.date || ''}" onchange="updateLineItem(${index}, 'date', this.value)"
                           class="w-full bg-transparent border-none focus:ring-0 font-bold text-gray-900 p-0 text-sm">
                </td>
                <td class="px-6 py-4">
                    <input type="text" value="${item.reference || ''}" onchange="updateLineItem(${index}, 'reference', this.value)"
                           placeholder="Ref" class="w-full bg-transparent border-none focus:ring-0 font-bold text-gray-900 p-0">
                </td>
                <td class="px-6 py-4">
                    <input type="text" value="${item.description || ''}" onchange="updateLineItem(${index}, 'description', this.value)"
                           placeholder="Description" class="w-full bg-transparent border-none focus:ring-0 font-bold text-gray-900 p-0">
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center justify-end">
                        <span class="text-gray-400 text-xs mr-1">${CURRENCY_SYMBOL}</span>
                        <input type="number" value="${item.amount || 0}" step="0.01" onchange="updateLineItem(${index}, 'amount', this.value)"
                               class="w-full bg-transparent border-none focus:ring-0 text-right font-bold text-gray-900 p-0 max-w-[100px]">
                    </div>
                </td>
                <td class="px-6 py-4 text-right">
                    <button type="button" onclick="removeLineItem(${index})" class="text-gray-300 hover:text-red-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </td>
            `;
            body.appendChild(row);
        });
    }
    calculateTotal();
}

function calculateTotal() {
    const total = lineItems.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    document.getElementById('summary-total').textContent = `${CURRENCY_SYMBOL}${total.toFixed(2)}`;
}

function addLineItem() {
    lineItems.push({ date: '', reference: '', description: '', amount: 0 });
    renderLineItems();
}

function updateLineItem(index, field, value) {
    lineItems[index][field] = value;
    renderLineItems();
}

function removeLineItem(index) {
    lineItems.splice(index, 1);
    renderLineItems();
}

// Form submission
document.getElementById('invoice-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (lineItems.length === 0) {
        showToast('Please add at least one line item.', 'error');
        return;
    }

    const formData = new FormData(e.target);
    const data = {
        invoiceDate: formData.get('invoiceDate'),
        companyHeader: {
            logoUrl: formData.get('companyLogo'),
            companyName: formData.get('companyName'),
            companyEmail: formData.get('companyEmail'),
            companyPhone: formData.get('companyPhone'),
            companyAddress: formData.get('companyAddress')
        },
        customer: {
            name: formData.get('customerName'),
            customerId: formData.get('customerId'),
            email: formData.get('customerEmail'),
            phone: formData.get('customerPhone'),
            propertyAddress: formData.get('propertyAddress'),
            contractFrom: formData.get('contractFrom'),
            contractTo: formData.get('contractTo')
        },
        lineItems: lineItems,
        paymentDetails: {
            bankName: formData.get('bankName'),
            accountNumber: formData.get('accountNumber'),
            branchCode: formData.get('branchCode'),
            paymentReference: formData.get('paymentReference'),
            dueDate: formData.get('dueDate')
        },
        currency: formData.get('currency'),
        notes: formData.get('notes'),
        footerText: formData.get('footerText'),
        customFields: customFields,
        csrf_token: CSRF_TOKEN
    };

    let response;
    const id = formData.get('id');
    if (id && id !== '') {
        response = await api.put(`api/advanced-invoices.php?id=${id}`, data);
    } else {
        response = await api.post('api/advanced-invoices.php', data);
    }

    if (response.success) {
        showToast('Invoice saved!', 'success');
        setTimeout(() => location.href = '?page=advanced-invoices', 1000);
    } else {
        showToast(response.error || 'Failed to save invoice', 'error');
    }
});

// Initial render
initCustomFields();
renderLineItems();
</script>

