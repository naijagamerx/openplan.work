<?php
/**
 * Mobile Transaction Form (Create/Edit)
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
        <p><a href="?page=finance">Back to finance</a></p>
    </body></html>');
}

$transactionId = trim((string)($_GET['id'] ?? ''));
$finance = $db->load('finance') ?? [];
$transaction = null;

if ($transactionId !== '') {
    foreach ($finance as $entry) {
        if ((string)($entry['id'] ?? '') === $transactionId) {
            $transaction = $entry;
            break;
        }
    }
}

$isEdit = is_array($transaction);
$pageTitle = $isEdit ? 'Edit Transaction' : 'New Transaction';
$siteName = getSiteName() ?? 'LazyMan';

$field = static function (string $key, string $default = '') use ($transaction): string {
    if (!$transaction) {
        return $default;
    }
    $value = $transaction[$key] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
};

$type = strtolower($field('type', 'expense'));
if (!in_array($type, ['income', 'expense'], true)) {
    $type = 'expense';
}

$categories = ['General', 'Sales', 'Services', 'Subscription', 'Marketing', 'Hardware', 'Software', 'Rent', 'Utilities', 'Taxes', 'Other'];
$selectedCategory = $field('category', 'General');
$transactionDate = $field('date', date('Y-m-d'));
if (strpos($transactionDate, 'T') !== false || strpos($transactionDate, ' ') !== false) {
    $transactionDate = date('Y-m-d', strtotime($transactionDate));
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
    .type-chip input:checked + div {
        @apply bg-black dark:bg-white text-white dark:text-black;
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

<main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-[300px] text-zinc-900 dark:text-zinc-100">
    <form id="transaction-form" class="space-y-6">
        <input type="hidden" name="id" value="<?= htmlspecialchars($transactionId) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label>Type</label>
            <div class="grid grid-cols-2 gap-2">
                <label class="type-chip cursor-pointer m-0">
                    <input class="hidden" name="type" type="radio" value="income" <?= $type === 'income' ? 'checked' : '' ?> />
                    <div class="border border-black dark:border-white py-3 text-center text-[10px] font-black uppercase tracking-widest transition-colors">Income</div>
                </label>
                <label class="type-chip cursor-pointer m-0">
                    <input class="hidden" name="type" type="radio" value="expense" <?= $type === 'expense' ? 'checked' : '' ?> />
                    <div class="border border-black dark:border-white py-3 text-center text-[10px] font-black uppercase tracking-widest transition-colors">Expense</div>
                </label>
            </div>
        </div>

        <div>
            <label for="transaction-description">Description</label>
            <input id="transaction-description" name="description" type="text" required placeholder="Transaction description" value="<?= htmlspecialchars($field('description')) ?>" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="transaction-amount">Amount</label>
                <input id="transaction-amount" name="amount" type="number" min="0" step="0.01" required value="<?= htmlspecialchars($field('amount')) ?>" />
            </div>
            <div>
                <label for="transaction-date">Date</label>
                <input id="transaction-date" name="date" type="date" required value="<?= htmlspecialchars($transactionDate) ?>" />
            </div>
        </div>

        <div>
            <label for="transaction-category">Category</label>
            <div class="relative">
                <select id="transaction-category" name="category" class="appearance-none">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= htmlspecialchars($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>><?= htmlspecialchars($category) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div>
            <label for="transaction-notes">Notes</label>
            <textarea id="transaction-notes" name="notes" rows="4" placeholder="Optional notes..."><?= htmlspecialchars($field('notes')) ?></textarea>
        </div>
    </form>
</main>

<div class="fixed left-1/2 -translate-x-1/2 bottom-[84px] w-full max-w-[420px] bg-white dark:bg-zinc-950 border-t border-zinc-100 dark:border-zinc-800 p-6 z-30">
    <div class="flex flex-col gap-3">
        <button id="save-transaction-btn" type="button" onclick="saveTransaction()" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.3em] hover:opacity-90 transition-opacity touch-target">
            <?= $isEdit ? 'Save Transaction' : 'Create Transaction' ?>
        </button>
        <a href="?page=finance" class="w-full py-3 text-center text-zinc-400 dark:text-zinc-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-black dark:hover:text-white transition-colors touch-target">
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
    const EDIT_TRANSACTION_ID = <?= json_encode($transactionId) ?>;
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
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

async function saveTransaction() {
    const form = document.getElementById('transaction-form');
    const saveBtn = document.getElementById('save-transaction-btn');
    if (!form || !saveBtn) {
        return;
    }

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    payload.type = (payload.type || 'expense').trim();
    payload.description = (payload.description || '').trim();
    payload.amount = Number(payload.amount || 0);
    payload.date = (payload.date || '').trim();
    payload.category = (payload.category || 'General').trim();
    payload.notes = (payload.notes || '').trim();
    payload.csrf_token = CSRF_TOKEN;

    if (!payload.description) {
        Mobile.ui.showToast('Description is required.', 'error');
        return;
    }
    if (!Number.isFinite(payload.amount) || payload.amount <= 0) {
        Mobile.ui.showToast('Enter a valid amount.', 'error');
        return;
    }

    saveBtn.disabled = true;
    const oldText = saveBtn.textContent;
    saveBtn.textContent = IS_EDIT ? 'SAVING...' : 'CREATING...';

    try {
        let response;
        if (IS_EDIT && EDIT_TRANSACTION_ID) {
            response = await App.api.put('api/finance.php?id=' + encodeURIComponent(EDIT_TRANSACTION_ID), payload);
        } else {
            response = await App.api.post('api/finance.php', payload);
        }

        if (!response.success) {
            throw new Error('Failed to save transaction');
        }

        Mobile.ui.showToast(IS_EDIT ? 'Transaction updated.' : 'Transaction created.', 'success');
        setTimeout(function() {
            window.location.href = '?page=finance';
        }, 180);
    } catch (error) {
        Mobile.ui.showToast(getErrorMessage(error, 'Failed to save transaction.'), 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = oldText;
    }
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
