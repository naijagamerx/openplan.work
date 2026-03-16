<?php
/**
 * Mobile Invoice Form (Create/Edit)
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
$invoices = $db->load('invoices') ?? [];
$clients = $db->load('clients') ?? [];
$projects = $db->load('projects') ?? [];
$config = $db->load('config') ?? [];

$invoice = null;
if ($invoiceId !== '') {
    foreach ($invoices as $candidate) {
        if ((string)($candidate['id'] ?? '') === $invoiceId) {
            $invoice = $candidate;
            break;
        }
    }
}

$isEdit = is_array($invoice);
$pageTitle = $isEdit ? 'Edit Invoice' : 'New Invoice';
$siteName = getSiteName() ?? 'LazyMan';
$currency = (string)($config['currency'] ?? 'USD');
$currencySymbol = getCurrencySymbol($currency);
$taxRate = (float)($config['taxRate'] ?? 0);

$field = static function (string $key, string $default = '') use ($invoice): string {
    if (!$invoice) {
        return $default;
    }
    $value = $invoice[$key] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
};

$status = strtolower($field('status', 'draft'));
if (!in_array($status, ['draft', 'sent', 'paid', 'overdue', 'cancelled'], true)) {
    $status = 'draft';
}

$lineItems = $invoice['lineItems'] ?? [];
if (!is_array($lineItems)) {
    $lineItems = [];
}
if (empty($lineItems)) {
    $lineItems = [['description' => '', 'quantity' => 1, 'unitPrice' => 0]];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

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
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a"
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
            @apply bg-zinc-50 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
        }
        input, select, textarea {
            @apply w-full bg-white dark:bg-zinc-900 border border-black dark:border-white rounded-none px-4 py-3 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white outline-none transition-all placeholder:text-zinc-400 dark:placeholder:text-zinc-500 font-medium;
        }
        label {
            @apply block text-[10px] font-black uppercase tracking-[0.2em] mb-2;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    @supports (-webkit-touch-callout: none) {
        input, textarea, select {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-zinc-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = $pageTitle;
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-[320px] text-zinc-900 dark:text-zinc-100">
    <form id="invoice-form" class="space-y-6">
        <input type="hidden" name="id" value="<?= htmlspecialchars($invoiceId) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label for="invoice-client">Client</label>
            <div class="relative">
                <select id="invoice-client" name="clientId" required class="appearance-none">
                    <option value="">Select client</option>
                    <?php foreach ($clients as $client): ?>
                        <?php $clientId = (string)($client['id'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($clientId) ?>" <?= $clientId === $field('clientId') ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($client['name'] ?? 'Unnamed')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div>
            <label for="invoice-project">Linked Project</label>
            <div class="relative">
                <select id="invoice-project" name="projectId" class="appearance-none">
                    <option value="">No linked project</option>
                    <?php foreach ($projects as $project): ?>
                        <?php $projectId = (string)($project['id'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($projectId) ?>" <?= $projectId === $field('projectId') ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($project['name'] ?? 'Untitled')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="invoice-due-date">Due Date</label>
                <input id="invoice-due-date" name="dueDate" type="date" required value="<?= htmlspecialchars($field('dueDate', date('Y-m-d', strtotime('+30 days')))) ?>" />
            </div>
            <div>
                <label for="invoice-status">Status</label>
                <div class="relative">
                    <select id="invoice-status" name="status" class="appearance-none">
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                    <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        <section class="border border-black dark:border-white p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Line Items</h3>
                <button type="button" onclick="addLineItem()" class="text-[10px] font-black uppercase tracking-widest underline underline-offset-2 touch-target">Add</button>
            </div>
            <div id="line-items-list" class="space-y-2"></div>
        </section>

        <section class="border border-black dark:border-white p-4 bg-zinc-50 dark:bg-zinc-900/40">
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest mb-2">
                <span>Subtotal</span>
                <span id="summary-subtotal"><?= htmlspecialchars($currencySymbol) ?>0.00</span>
            </div>
            <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest mb-2">
                <span>Tax (<?= rtrim(rtrim(number_format($taxRate, 2, '.', ''), '0'), '.') ?>%)</span>
                <span id="summary-tax"><?= htmlspecialchars($currencySymbol) ?>0.00</span>
            </div>
            <div class="flex items-center justify-between text-sm font-black uppercase tracking-widest border-t border-black/10 dark:border-white/20 pt-2">
                <span>Total</span>
                <span id="summary-total"><?= htmlspecialchars($currencySymbol) ?>0.00</span>
            </div>
        </section>

        <div>
            <label for="invoice-notes">Notes</label>
            <textarea id="invoice-notes" name="notes" rows="4" placeholder="Payment notes, terms, or comments..."><?= htmlspecialchars($field('notes')) ?></textarea>
        </div>
    </form>
</main>

<div class="fixed left-1/2 -translate-x-1/2 bottom-[84px] w-full max-w-[420px] bg-white dark:bg-zinc-950 border-t border-zinc-100 dark:border-zinc-800 p-6 z-30">
    <div class="flex flex-col gap-3">
        <button id="save-invoice-btn" type="button" onclick="saveInvoice()" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.3em] hover:opacity-90 transition-opacity touch-target">
            <?= $isEdit ? 'Save Invoice' : 'Create Invoice' ?>
        </button>
        <a href="?page=invoices" class="w-full py-3 text-center text-zinc-400 dark:text-zinc-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-black dark:hover:text-white transition-colors touch-target">
            Cancel
        </a>
    </div>
    <div class="mt-4 mx-auto w-32 h-1 bg-zinc-100 dark:bg-zinc-800 rounded-full"></div>
</div>

<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 dark:bg-zinc-700 rounded-full z-40"></div>

</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const EDIT_INVOICE_ID = <?= json_encode($invoiceId) ?>;
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
    const TAX_RATE = <?= json_encode($taxRate) ?>;
    const CURRENCY = <?= json_encode($currency) ?>;
    const CURRENCY_SYMBOL = <?= json_encode($currencySymbol) ?>;
    const INITIAL_ITEMS = <?= json_encode($lineItems) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
let lineItems = [];

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getErrorMessage(error, fallback) {
    if (error && error.response && error.response.error) {
        if (typeof error.response.error === 'string') {
            return error.response.error;
        }
        if (typeof error.response.error.message === 'string') {
            return error.response.error.message;
        }
    }
    if (error && typeof error.message === 'string' && error.message) {
        return error.message;
    }
    return fallback;
}

function addLineItem(item = null) {
    lineItems.push({
        description: (item && item.description) ? String(item.description) : '',
        quantity: Number.isFinite(Number(item && item.quantity)) ? Number(item.quantity) : 1,
        unitPrice: Number.isFinite(Number(item && item.unitPrice)) ? Number(item.unitPrice) : 0
    });
    renderLineItems();
}

function removeLineItem(index) {
    lineItems.splice(index, 1);
    if (lineItems.length === 0) {
        addLineItem();
        return;
    }
    renderLineItems();
}

function updateLineItem(index, field, value) {
    if (!lineItems[index]) {
        return;
    }
    if (field === 'description') {
        lineItems[index][field] = value;
    } else {
        const parsed = Number(value);
        lineItems[index][field] = Number.isFinite(parsed) ? parsed : 0;
    }
    calculateTotals();
}

function renderLineItems() {
    const list = document.getElementById('line-items-list');
    if (!list) {
        return;
    }

    list.innerHTML = '';
    lineItems.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'border border-black/10 dark:border-white/20 p-3';
        row.innerHTML = `
            <div class="mb-2">
                <input type="text" value="${escapeHtml(item.description || '')}" placeholder="Description" oninput="updateLineItem(${index}, 'description', this.value)" />
            </div>
            <div class="grid grid-cols-2 gap-2 mb-2">
                <input type="number" min="0" step="1" value="${item.quantity}" placeholder="Qty" oninput="updateLineItem(${index}, 'quantity', this.value)" />
                <input type="number" min="0" step="0.01" value="${item.unitPrice}" placeholder="Unit price" oninput="updateLineItem(${index}, 'unitPrice', this.value)" />
            </div>
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-widest">Line Total</span>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-black">${CURRENCY_SYMBOL}${(Number(item.quantity) * Number(item.unitPrice)).toFixed(2)}</span>
                    <button type="button" onclick="removeLineItem(${index})" class="border border-black dark:border-white px-2 py-1 text-[10px] font-black uppercase tracking-widest touch-target">Del</button>
                </div>
            </div>
        `;
        list.appendChild(row);
    });

    calculateTotals();
}

function calculateTotals() {
    const subtotal = lineItems.reduce((sum, item) => {
        const qty = Number(item.quantity) || 0;
        const price = Number(item.unitPrice) || 0;
        return sum + (qty * price);
    }, 0);

    const taxAmount = subtotal * (Number(TAX_RATE) / 100);
    const total = subtotal + taxAmount;

    const subtotalEl = document.getElementById('summary-subtotal');
    const taxEl = document.getElementById('summary-tax');
    const totalEl = document.getElementById('summary-total');

    if (subtotalEl) subtotalEl.textContent = `${CURRENCY_SYMBOL}${subtotal.toFixed(2)}`;
    if (taxEl) taxEl.textContent = `${CURRENCY_SYMBOL}${taxAmount.toFixed(2)}`;
    if (totalEl) totalEl.textContent = `${CURRENCY_SYMBOL}${total.toFixed(2)}`;

    return { subtotal, taxAmount, total };
}

function buildPayload() {
    const clientId = (document.getElementById('invoice-client')?.value || '').trim();
    const projectId = (document.getElementById('invoice-project')?.value || '').trim();
    const dueDate = (document.getElementById('invoice-due-date')?.value || '').trim();
    const notes = (document.getElementById('invoice-notes')?.value || '').trim();
    const status = (document.getElementById('invoice-status')?.value || 'draft').trim();

    const filteredItems = lineItems
        .map((item) => ({
            description: String(item.description || '').trim(),
            quantity: Number(item.quantity) || 0,
            unitPrice: Number(item.unitPrice) || 0
        }))
        .filter((item) => item.description !== '' && item.quantity > 0);

    const totals = calculateTotals();

    return {
        clientId,
        projectId: projectId || null,
        dueDate,
        status,
        notes,
        lineItems: filteredItems,
        subtotal: totals.subtotal,
        taxRate: Number(TAX_RATE),
        taxAmount: totals.taxAmount,
        total: totals.total,
        currency: CURRENCY,
        csrf_token: CSRF_TOKEN
    };
}

async function saveInvoice() {
    const saveBtn = document.getElementById('save-invoice-btn');
    if (!saveBtn) {
        return;
    }

    const payload = buildPayload();
    if (!payload.clientId) {
        Mobile.ui.showToast('Client is required.', 'error');
        return;
    }
    if (!payload.dueDate) {
        Mobile.ui.showToast('Due date is required.', 'error');
        return;
    }
    if (!payload.lineItems.length) {
        Mobile.ui.showToast('Add at least one valid line item.', 'error');
        return;
    }

    saveBtn.disabled = true;
    const oldText = saveBtn.textContent;
    saveBtn.textContent = IS_EDIT ? 'SAVING...' : 'CREATING...';

    try {
        let response;
        if (IS_EDIT && EDIT_INVOICE_ID) {
            response = await App.api.put('api/invoices.php?id=' + encodeURIComponent(EDIT_INVOICE_ID), payload);
        } else {
            response = await App.api.post('api/invoices.php', payload);
        }

        if (!response.success) {
            throw new Error('Failed to save invoice');
        }

        Mobile.ui.showToast(IS_EDIT ? 'Invoice updated.' : 'Invoice created.', 'success');
        setTimeout(function() {
            window.location.href = '?page=invoices';
        }, 180);
    } catch (error) {
        Mobile.ui.showToast(getErrorMessage(error, 'Failed to save invoice.'), 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = oldText;
    }
}

lineItems = Array.isArray(INITIAL_ITEMS) ? INITIAL_ITEMS : [];
if (lineItems.length === 0) {
    lineItems = [{ description: '', quantity: 1, unitPrice: 0 }];
}
renderLineItems();

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
