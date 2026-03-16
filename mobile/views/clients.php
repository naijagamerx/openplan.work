<?php
/**
 * Mobile Clients List Page
 *
 * High-contrast clients list based on provided UX sample.
 * Supports search and navigation to create/view flows.
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
        <p><a href="?page=dashboard">Back to dashboard</a></p>
    </body></html>');
}

$clients = $db->load('clients') ?? [];
$projects = $db->load('projects') ?? [];
$invoices = $db->load('invoices') ?? [];

$clientStats = [];
$paidInvoiceCount = 0;
foreach ($invoices as $invoice) {
    if (($invoice['status'] ?? '') === 'paid') {
        $paidInvoiceCount++;
    }
}

foreach ($clients as $client) {
    $id = (string)($client['id'] ?? '');
    $projectCount = count(array_filter($projects, fn($p) => (string)($p['clientId'] ?? '') === $id));

    $paid = 0.0;
    $outstanding = 0.0;
    foreach ($invoices as $invoice) {
        if ((string)($invoice['clientId'] ?? '') !== $id) {
            continue;
        }
        $total = (float)($invoice['total'] ?? 0);
        if (($invoice['status'] ?? '') === 'paid') {
            $paid += $total;
        } else {
            $outstanding += $total;
        }
    }

    $clientStats[$id] = [
        'projectCount' => $projectCount,
        'paid' => $paid,
        'outstanding' => $outstanding,
    ];
}

usort($clients, fn($a, $b) => strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Clients - <?= htmlspecialchars($siteName) ?></title>

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
            @apply bg-white text-black font-display antialiased;
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
</style>
</head>
<body class="bg-gray-50 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-gray-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = 'Clients';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=client-form';
$rightIsLink = true;
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32 text-zinc-900 dark:text-zinc-100">
    <section class="px-6 pt-2 pb-4">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                id="client-search"
                class="w-full bg-gray-50 dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 rounded-none py-3 pl-10 pr-4 text-sm font-medium focus:ring-0 focus:border-black dark:focus:border-white"
                placeholder="Search clients..."
                type="text"
                oninput="filterClients(this.value)"
            />
        </div>
    </section>

    <section class="px-6 mb-6">
        <div class="grid grid-cols-3 gap-3">
            <div class="border border-black dark:border-white p-3 flex flex-col items-center">
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Clients</span>
                <span class="text-lg font-black"><?= count($clients) ?></span>
            </div>
            <div class="border border-black dark:border-white p-3 flex flex-col items-center">
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Projects</span>
                <span class="text-lg font-black"><?= count($projects) ?></span>
            </div>
            <div class="border border-black dark:border-white p-3 flex flex-col items-center bg-gray-50 dark:bg-zinc-900">
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Paid</span>
                <span class="text-lg font-black"><?= $paidInvoiceCount ?></span>
            </div>
        </div>
    </section>

    <section class="px-6 mb-8">
        <a href="?page=client-form" class="w-full bg-black dark:bg-white text-white dark:text-black py-4 font-bold uppercase tracking-widest text-xs flex items-center justify-center gap-2 hover:opacity-90 transition-opacity touch-target">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Client
        </a>
    </section>

    <section class="px-6 pb-4">
        <?php if (empty($clients)): ?>
            <div class="text-center py-12 border border-gray-200 dark:border-zinc-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">No clients yet.</p>
            </div>
        <?php else: ?>
            <div id="clients-list" class="space-y-4">
                <?php foreach ($clients as $client): ?>
                    <?php
                    $id = (string)($client['id'] ?? '');
                    $name = (string)($client['name'] ?? 'Unnamed Client');
                    $email = (string)($client['email'] ?? 'No email');
                    $initial = strtoupper(substr($name, 0, 1));
                    $stats = $clientStats[$id] ?? ['projectCount' => 0, 'paid' => 0, 'outstanding' => 0];
                    ?>
                    <a
                        href="?page=view-client&id=<?= urlencode($id) ?>"
                        class="flex items-center justify-between p-4 border border-gray-200 dark:border-zinc-800 hover:border-black dark:hover:border-white transition-colors cursor-pointer group"
                        data-client-search="<?= htmlspecialchars(strtolower($name . ' ' . $email)) ?>"
                    >
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="size-12 border border-black dark:border-white flex items-center justify-center text-xl font-black flex-shrink-0">
                                <?= htmlspecialchars($initial) ?>
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-sm font-bold uppercase tracking-tight truncate"><?= htmlspecialchars($name) ?></span>
                                <span class="text-[10px] text-gray-400 truncate"><?= htmlspecialchars($email) ?></span>
                                <span class="text-[9px] font-bold uppercase mt-1">
                                    <?= (int)$stats['projectCount'] ?> Project<?= ((int)$stats['projectCount'] === 1 ? '' : 's') ?>
                                </span>
                            </div>
                        </div>
                        <svg class="w-5 h-5 text-gray-300 group-hover:text-black dark:group-hover:text-white transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

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
        let basePath = path.replace(/\/index\.php$/i, '');
        basePath = basePath.replace(/\/+$/, '');
        basePath = basePath.replace(/\/mobile$/i, '');
        window.BASE_PATH = basePath;
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
function filterClients(query) {
    const rows = document.querySelectorAll('[data-client-search]');
    const q = (query || '').toLowerCase().trim();
    rows.forEach(row => {
        const haystack = row.getAttribute('data-client-search') || '';
        row.style.display = haystack.includes(q) ? '' : 'none';
    });
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
