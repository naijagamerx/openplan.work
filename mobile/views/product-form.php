<?php
/**
 * Mobile Product Form (Create/Edit)
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
        <p><a href="?page=inventory">Back to inventory</a></p>
    </body></html>');
}

$productId = trim((string)($_GET['id'] ?? ''));
$inventory = $db->load('inventory') ?? [];
$product = null;

if ($productId !== '') {
    foreach ($inventory as $item) {
        if ((string)($item['id'] ?? '') === $productId) {
            $product = $item;
            break;
        }
    }
}

$isEdit = is_array($product);
$pageTitle = $isEdit ? 'Edit Product' : 'New Product';
$submitLabel = $isEdit ? 'Save Product' : 'Create Product';
$siteName = getSiteName() ?? 'LazyMan';

$field = static function (string $key, string $default = '') use ($product): string {
    if (!$product) {
        return $default;
    }
    $value = $product[$key] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
};

$selectedCategory = $field('category', 'General');
$categories = ['General', 'Office Supplies', 'Electronics', 'Furniture', 'Raw Materials', 'Packaging', 'Maintenance', 'Groceries'];
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
        input, select, textarea {
            @apply border border-black rounded-none focus:ring-0 focus:border-black text-sm px-4 py-3 w-full placeholder:text-gray-300 bg-white;
        }
        label {
            @apply text-[10px] font-black uppercase tracking-widest mb-1.5 block;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden">

<?php
$title = $pageTitle;
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-40">
    <form id="product-form" class="p-4 space-y-5">
        <input type="hidden" name="id" value="<?= htmlspecialchars($productId) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label for="product-name">Product Name</label>
            <input id="product-name" name="name" type="text" required placeholder="e.g. Ergonomic Office Chair" value="<?= htmlspecialchars($field('name')) ?>">
        </div>

        <div>
            <label for="product-sku">SKU / Code</label>
            <input id="product-sku" name="sku" type="text" placeholder="SKU-000-000" value="<?= htmlspecialchars($field('sku')) ?>">
        </div>

        <div>
            <label for="product-category">Category</label>
            <select id="product-category" name="category">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category) ?>" <?= $selectedCategory === $category ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="product-unit-price">Unit Price ($)</label>
                <input id="product-unit-price" name="unitPrice" type="number" step="0.01" min="0" required placeholder="0.00" value="<?= htmlspecialchars($field('unitPrice')) ?>">
            </div>
            <div>
                <label for="product-cost-price">Cost Price ($)</label>
                <input id="product-cost-price" name="costPrice" type="number" step="0.01" min="0" placeholder="0.00" value="<?= htmlspecialchars($field('costPrice')) ?>">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="product-quantity">Current Stock</label>
                <input id="product-quantity" name="quantity" type="number" min="0" step="1" required placeholder="0" value="<?= htmlspecialchars($field('quantity', '0')) ?>">
            </div>
            <div>
                <label for="product-reorder">Minimum Stock</label>
                <input id="product-reorder" name="reorderPoint" type="number" min="0" step="1" placeholder="5" value="<?= htmlspecialchars($field('reorderPoint', '5')) ?>">
            </div>
        </div>

        <div>
            <label for="product-description">Description</label>
            <textarea id="product-description" name="description" rows="4" placeholder="Enter product details, specifications, or notes..."><?= htmlspecialchars($field('description')) ?></textarea>
        </div>
    </form>
</main>

<footer class="absolute bottom-0 left-0 right-0 p-4 bg-white border-t border-gray-100 space-y-2">
    <button id="save-product-btn" type="button" onclick="saveProduct()" class="w-full bg-black text-white py-4 flex items-center justify-center gap-2 hover:bg-gray-800 transition-colors">
        <span class="text-[11px] font-black uppercase tracking-[0.2em]"><?= htmlspecialchars($submitLabel) ?></span>
    </button>
    <a href="?page=inventory" class="w-full border border-black text-black py-4 flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors">
        <span class="text-[11px] font-black uppercase tracking-[0.2em]">Cancel</span>
    </a>
</footer>

</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const EDIT_PRODUCT_ID = <?= json_encode($productId) ?>;
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
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
    } else {
        alert(message);
    }
}

async function saveProduct() {
    const form = document.getElementById('product-form');
    const saveBtn = document.getElementById('save-product-btn');
    if (!form || !saveBtn) {
        return;
    }

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    payload.name = (payload.name || '').trim();
    payload.sku = (payload.sku || '').trim();
    payload.category = (payload.category || 'General').trim();
    payload.description = (payload.description || '').trim();
    payload.unitPrice = Number(payload.unitPrice || 0);
    payload.costPrice = Number(payload.costPrice || 0);
    payload.quantity = Number.parseInt(payload.quantity || '0', 10);
    payload.reorderPoint = Number.parseInt(payload.reorderPoint || '0', 10);
    payload.csrf_token = CSRF_TOKEN;

    if (!payload.name) {
        toast('Product name is required.', 'error');
        return;
    }

    saveBtn.disabled = true;
    const oldLabel = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="text-[11px] font-black uppercase tracking-[0.2em]">' + (IS_EDIT ? 'Saving...' : 'Creating...') + '</span>';

    try {
        let response;
        if (IS_EDIT && EDIT_PRODUCT_ID) {
            response = await App.api.put('api/inventory.php?id=' + encodeURIComponent(EDIT_PRODUCT_ID), payload);
        } else {
            response = await App.api.post('api/inventory.php', payload);
        }

        if (!response.success) {
            throw new Error('Failed to save product');
        }

        toast(IS_EDIT ? 'Product updated.' : 'Product created.', 'success');
        setTimeout(function() {
            window.location.href = '?page=inventory';
        }, 180);
    } catch (error) {
        toast(getErrorMessage(error, 'Failed to save product.'), 'error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldLabel;
    }
}
</script>
</body>
</html>
