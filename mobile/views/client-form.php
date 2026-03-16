<?php
/**
 * Mobile Client Form Page (Create/Edit)
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
        <p><a href="?page=clients">Back to clients</a></p>
    </body></html>');
}

$clientId = trim((string)($_GET['id'] ?? ''));
$clients = $db->load('clients') ?? [];
$client = null;
foreach ($clients as $item) {
    if ((string)($item['id'] ?? '') === $clientId) {
        $client = $item;
        break;
    }
}

$isEdit = is_array($client);
$pageTitle = $isEdit ? 'Edit Client' : 'New Client';
$siteName = getSiteName() ?? 'LazyMan';

$field = function(string $key) use ($client): string {
    if (!$client) {
        return '';
    }
    $value = $client[$key] ?? '';
    if (is_array($value)) {
        $flat = array_map(fn($v) => is_scalar($v) ? (string)$v : '', $value);
        return trim(implode(', ', array_filter($flat, fn($v) => $v !== '')));
    }
    return (string)$value;
};
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
                    "primary": "#000000",
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a",
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                },
            },
        },
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-[#f4f4f4] text-black font-display antialiased;
        }
        input, textarea {
            @apply focus:ring-0 focus:border-black dark:focus:border-white border-gray-200 dark:border-zinc-700 text-sm py-3 px-4 w-full transition-all bg-white dark:bg-zinc-900 rounded-none;
        }
        label {
            @apply text-[10px] font-black uppercase tracking-[0.2em] mb-2 block;
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
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-gray-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = $pageTitle;
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-6 py-2 pb-[260px] text-zinc-900 dark:text-zinc-100">
    <form id="client-form" class="space-y-8">
        <input type="hidden" name="id" value="<?= htmlspecialchars($clientId) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div class="group">
            <label for="client-name">Client / Company Name</label>
            <input id="client-name" name="name" placeholder="Acme Corp" type="text" required value="<?= htmlspecialchars($field('name')) ?>"/>
        </div>

        <div class="group">
            <label for="client-email">Email Address</label>
            <input id="client-email" name="email" placeholder="contact@company.com" type="email" required value="<?= htmlspecialchars($field('email')) ?>"/>
        </div>

        <div class="group">
            <label for="client-phone">Phone Number</label>
            <input id="client-phone" name="phone" placeholder="+1 (555) 000-0000" type="tel" value="<?= htmlspecialchars($field('phone')) ?>"/>
        </div>

        <div class="group">
            <label for="client-address">Business Address</label>
            <textarea id="client-address" name="address" placeholder="Street, City, Postcode" rows="3"><?= htmlspecialchars($field('address')) ?></textarea>
        </div>

        <div class="group">
            <div class="flex justify-between items-center mb-2">
                <label class="mb-0" for="client-company">Company</label>
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Optional</span>
            </div>
            <input id="client-company" name="company" placeholder="Acme Corporation Ltd." type="text" value="<?= htmlspecialchars($field('company')) ?>"/>
        </div>

        <div class="group">
            <div class="flex justify-between items-center mb-2">
                <label class="mb-0" for="client-website">Website</label>
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Optional</span>
            </div>
            <input id="client-website" name="website" placeholder="https://example.com" type="text" value="<?= htmlspecialchars($field('website')) ?>"/>
        </div>

        <div class="group">
            <div class="flex justify-between items-center mb-2">
                <label class="mb-0" for="client-notes">Notes</label>
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Optional</span>
            </div>
            <textarea id="client-notes" name="notes" placeholder="Client brief, communication notes..." rows="4"><?= htmlspecialchars($field('notes')) ?></textarea>
        </div>
    </form>
</main>

<div class="fixed left-1/2 -translate-x-1/2 bottom-[84px] w-full max-w-[420px] bg-white dark:bg-zinc-950 border-t border-gray-100 dark:border-zinc-800 p-6 space-y-3 z-30">
    <button
        id="save-client-btn"
        type="button"
        onclick="saveClient()"
        class="w-full bg-black dark:bg-white text-white dark:text-black py-4 text-xs font-black uppercase tracking-[0.3em] hover:opacity-90 transition-opacity touch-target"
    >
        <?= $isEdit ? 'Save Client' : 'Create Client' ?>
    </button>
    <a href="?page=clients" class="block w-full bg-transparent text-center text-black dark:text-white py-4 text-xs font-black uppercase tracking-[0.3em] hover:bg-gray-50 dark:hover:bg-zinc-900 transition-colors touch-target">
        Cancel
    </a>
    <div class="mt-4 flex justify-center">
        <div class="w-32 h-1 bg-gray-100 dark:bg-zinc-800 rounded-full"></div>
    </div>
</div>

<?php
$activePage = '';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const EDIT_CLIENT_ID = <?= json_encode($clientId) ?>;
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
function notify(message, type) {
    if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
        Mobile.ui.showToast(message, type || 'info');
        return;
    }
    alert(message);
}

function getApiError(error, fallback) {
    if (error && error.response) {
        const e = error.response.error;
        if (typeof e === 'string' && e) return e;
        if (e && typeof e.message === 'string') return e.message;
    }
    if (error && typeof error.message === 'string' && error.message) {
        return error.message;
    }
    return fallback;
}

async function saveClient() {
    const form = document.getElementById('client-form');
    const saveBtn = document.getElementById('save-client-btn');
    if (!form || !saveBtn) return;

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    payload.name = (payload.name || '').trim();
    payload.email = (payload.email || '').trim();
    payload.phone = (payload.phone || '').trim();
    payload.address = (payload.address || '').trim();
    payload.company = (payload.company || '').trim();
    payload.website = (payload.website || '').trim();
    payload.notes = (payload.notes || '').trim();
    payload.csrf_token = CSRF_TOKEN;

    if (!payload.name || !payload.email) {
        notify('Name and email are required.', 'error');
        return;
    }

    saveBtn.disabled = true;
    const oldText = saveBtn.textContent;
    saveBtn.textContent = IS_EDIT ? 'SAVING...' : 'CREATING...';

    try {
        let response;
        if (IS_EDIT && EDIT_CLIENT_ID) {
            response = await App.api.put('api/clients.php?id=' + encodeURIComponent(EDIT_CLIENT_ID), payload);
        } else {
            response = await App.api.post('api/clients.php?action=add', payload);
        }

        if (!response.success || !response.data || !response.data.id) {
            throw new Error('Failed to save client');
        }

        notify(IS_EDIT ? 'Client updated.' : 'Client created.', 'success');
        setTimeout(function() {
            window.location.href = '?page=view-client&id=' + encodeURIComponent(response.data.id);
        }, 180);
    } catch (error) {
        notify(getApiError(error, 'Failed to save client.'), 'error');
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
