<?php
/**
 * Mobile Client Detail View
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
$projects = $db->load('projects') ?? [];
$invoices = $db->load('invoices') ?? [];

$client = null;
foreach ($clients as $item) {
    if ((string)($item['id'] ?? '') === $clientId) {
        $client = $item;
        break;
    }
}

$clientProjects = array_values(array_filter($projects, fn($p) => (string)($p['clientId'] ?? '') === $clientId));
$clientInvoices = array_values(array_filter($invoices, fn($i) => (string)($i['clientId'] ?? '') === $clientId));

$paidTotal = 0.0;
$outstandingTotal = 0.0;
foreach ($clientInvoices as $invoice) {
    $total = (float)($invoice['total'] ?? 0);
    if (($invoice['status'] ?? '') === 'paid') {
        $paidTotal += $total;
    } else {
        $outstandingTotal += $total;
    }
}

$getProjectProgress = function(array $project): int {
    if (isset($project['progress']) && is_numeric($project['progress'])) {
        return max(0, min(100, (int)$project['progress']));
    }

    $tasks = $project['tasks'] ?? [];
    if (is_array($tasks) && count($tasks) > 0) {
        $done = 0;
        foreach ($tasks as $task) {
            if (!empty($task['completedAt'])) {
                $done++;
            }
        }
        return (int)round(($done / count($tasks)) * 100);
    }
    return 0;
};

$getDueText = function(array $project): string {
    $due = $project['dueDate'] ?? $project['deadline'] ?? $project['endDate'] ?? '';
    if (!$due) {
        return 'No due date';
    }
    $ts = strtotime((string)$due);
    if (!$ts) {
        return 'No due date';
    }
    return 'Due ' . date('M j', $ts);
};

$siteName = getSiteName() ?? 'LazyMan';

$toDisplayValue = static function ($value, string $fallback): string {
    if (is_array($value)) {
        $parts = [];
        foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($value)) as $part) {
            if (is_scalar($part)) {
                $clean = trim((string)$part);
                if ($clean !== '') {
                    $parts[] = $clean;
                }
            }
        }
        return !empty($parts) ? implode(', ', $parts) : $fallback;
    }
    if (is_scalar($value)) {
        $clean = trim((string)$value);
        return $clean !== '' ? $clean : $fallback;
    }
    return $fallback;
};

$clientEmail = $client ? $toDisplayValue($client['email'] ?? '', 'No email') : 'No email';
$clientPhone = $client ? $toDisplayValue($client['phone'] ?? '', 'No phone') : 'No phone';
$clientCompany = $client ? $toDisplayValue($client['company'] ?? '', 'No company') : 'No company';
$clientWebsite = $client ? $toDisplayValue($client['website'] ?? '', 'No website') : 'No website';
$clientAddress = $client ? $toDisplayValue($client['address'] ?? '', 'No address') : 'No address';
$clientNotes = $client ? $toDisplayValue($client['notes'] ?? '', 'No notes available') : 'No notes available';
$invoiceCount = count($clientInvoices);

$formatClientFieldLabel = static function (string $key): string {
    $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
    $key = str_replace(['_', '-'], ' ', (string)$key);
    $key = preg_replace('/\s+/', ' ', trim((string)$key));
    return ucwords($key);
};

$knownClientKeys = [
    'id', 'name', 'email', 'phone', 'company', 'website', 'address', 'notes',
    'createdAt', 'updatedAt', 'deletedAt', 'archivedAt'
];
$additionalClientDetails = [];
if ($client) {
    foreach ($client as $key => $value) {
        $normalizedKey = (string)$key;
        if (in_array($normalizedKey, $knownClientKeys, true)) {
            continue;
        }
        $displayValue = $toDisplayValue($value, '');
        if ($displayValue === '') {
            continue;
        }
        $additionalClientDetails[] = [
            'label' => $formatClientFieldLabel($normalizedKey),
            'value' => $displayValue
        ];
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Client Details - <?= htmlspecialchars($siteName) ?></title>

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
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-gray-200 dark:border-zinc-800 overflow-hidden">
<?php
$title = 'Client Details';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<?php if (!$client): ?>
    <main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-32 text-zinc-900 dark:text-zinc-100">
        <section class="border border-gray-200 dark:border-zinc-800 p-6 mt-2">
            <h2 class="text-xl font-black uppercase tracking-tight mb-2">Client not found</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">This client may have been removed.</p>
            <a href="?page=clients" class="inline-block bg-black dark:bg-white text-white dark:text-black px-4 py-2 text-[10px] font-black uppercase tracking-widest touch-target">
                Back to Clients
            </a>
        </section>
    </main>
<?php else: ?>
    <main class="flex-1 overflow-y-auto no-scrollbar pb-32 text-zinc-900 dark:text-zinc-100">
        <section class="px-6 py-8 border-b border-gray-100 dark:border-zinc-800">
            <div class="flex flex-col">
                <h2 class="text-4xl font-black tracking-tighter uppercase mb-2"><?= htmlspecialchars((string)($client['name'] ?? 'Unnamed')) ?></h2>
                <div class="flex flex-col gap-1">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 lowercase tracking-tight">
                        <?= htmlspecialchars($clientEmail) ?>
                    </span>
                    <?php if ($clientCompany !== 'No company'): ?>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                            <?= htmlspecialchars($clientCompany) ?>
                        </span>
                    <?php endif; ?>
                    <div class="mt-4 inline-flex">
                        <span class="px-3 py-1 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest">
                            <?= count($clientProjects) ?> Project<?= count($clientProjects) === 1 ? '' : 's' ?>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section class="px-6 py-6 grid grid-cols-3 gap-4 border-b border-gray-100 dark:border-zinc-800 bg-gray-50/50 dark:bg-zinc-900/40">
            <div class="flex flex-col">
                <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-gray-400">Total Paid</span>
                <span class="text-lg font-black tracking-tight"><?= htmlspecialchars(formatCurrency($paidTotal)) ?></span>
            </div>
            <div class="flex flex-col">
                <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-gray-400">Outstanding</span>
                <span class="text-lg font-black tracking-tight underline decoration-2"><?= htmlspecialchars(formatCurrency($outstandingTotal)) ?></span>
            </div>
            <div class="flex flex-col">
                <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-gray-400">Invoices</span>
                <span class="text-lg font-black tracking-tight"><?= $invoiceCount ?></span>
            </div>
        </section>

        <section class="px-6 py-8 border-b border-gray-100 dark:border-zinc-800">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-black dark:text-white">Client Details</h3>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Profile</span>
            </div>
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="border border-gray-200 dark:border-zinc-800 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Email</p>
                        <p class="text-xs font-semibold break-words"><?= htmlspecialchars($clientEmail) ?></p>
                    </div>
                    <div class="border border-gray-200 dark:border-zinc-800 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Phone</p>
                        <p class="text-xs font-semibold break-words"><?= htmlspecialchars($clientPhone) ?></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="border border-gray-200 dark:border-zinc-800 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Company</p>
                        <p class="text-xs font-semibold break-words"><?= htmlspecialchars($clientCompany) ?></p>
                    </div>
                    <div class="border border-gray-200 dark:border-zinc-800 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Website</p>
                        <?php if ($clientWebsite !== 'No website'): ?>
                            <?php
                            $websiteHref = $clientWebsite;
                            if (!preg_match('#^https?://#i', $websiteHref)) {
                                $websiteHref = 'https://' . $websiteHref;
                            }
                            ?>
                            <a href="<?= htmlspecialchars($websiteHref) ?>" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold underline break-words">
                                <?= htmlspecialchars($clientWebsite) ?>
                            </a>
                        <?php else: ?>
                            <p class="text-xs font-semibold"><?= htmlspecialchars($clientWebsite) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="border border-gray-200 dark:border-zinc-800 p-3">
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Address</p>
                    <p class="text-xs font-semibold break-words"><?= htmlspecialchars($clientAddress) ?></p>
                </div>
                <div class="border border-gray-200 dark:border-zinc-800 p-3">
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Notes</p>
                    <p class="text-xs font-semibold leading-relaxed whitespace-pre-wrap break-words"><?= htmlspecialchars($clientNotes) ?></p>
                </div>
                <?php if (!empty($additionalClientDetails)): ?>
                    <div class="border border-gray-200 dark:border-zinc-800 p-3">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Additional Details</p>
                        <div class="space-y-2">
                            <?php foreach ($additionalClientDetails as $detail): ?>
                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400"><?= htmlspecialchars($detail['label']) ?></span>
                                    <span class="text-xs font-semibold break-words text-right"><?= htmlspecialchars($detail['value']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="px-6 py-8 border-b border-gray-100 dark:border-zinc-800">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-black dark:text-white">Active Projects</h3>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Current</span>
            </div>
            <?php if (empty($clientProjects)): ?>
                <div class="border border-gray-200 dark:border-zinc-800 p-5 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No linked projects yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($clientProjects as $project): ?>
                        <?php
                        $progress = $getProjectProgress($project);
                        $dueText = $getDueText($project);
                        $projectName = (string)($project['name'] ?? 'Untitled Project');
                        $type = (string)($project['type'] ?? 'Project');
                        ?>
                        <a href="?page=view-project&id=<?= urlencode((string)($project['id'] ?? '')) ?>" class="block p-5 border border-black dark:border-white group cursor-pointer hover:bg-black dark:hover:bg-white hover:text-white dark:hover:text-black transition-all">
                            <div class="flex justify-between items-start mb-6">
                                <div class="flex flex-col">
                                    <span class="text-xs font-black uppercase tracking-wider"><?= htmlspecialchars($projectName) ?></span>
                                    <span class="text-[10px] opacity-60 uppercase mt-1"><?= htmlspecialchars($type) ?> &bull; <?= htmlspecialchars($dueText) ?></span>
                                </div>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 17L17 7M17 7H8M17 7v9"/>
                                </svg>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between items-end">
                                    <span class="text-[9px] font-bold uppercase tracking-widest">Progress</span>
                                    <span class="text-[10px] font-black"><?= $progress ?>%</span>
                                </div>
                                <div class="w-full h-[2px] bg-gray-100 dark:bg-zinc-700 overflow-hidden">
                                    <div class="bg-black dark:bg-white h-full transition-colors" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="px-6 py-6 space-y-3">
            <a href="?page=client-form&id=<?= urlencode($clientId) ?>" class="w-full h-14 bg-black dark:bg-white text-white dark:text-black text-xs font-black uppercase tracking-[0.2em] flex items-center justify-center hover:opacity-90 transition-opacity touch-target">
                Edit Client
            </a>
            <button onclick="deleteClient()" class="w-full h-14 bg-white dark:bg-zinc-900 border border-black dark:border-white text-black dark:text-white text-xs font-black uppercase tracking-[0.2em] flex items-center justify-center hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors touch-target">
                Delete
            </button>
        </section>
    </main>
<?php endif; ?>

<?php
$activePage = '';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-100 dark:bg-zinc-700 rounded-full"></div>
</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const CLIENT_ID = <?= json_encode($clientId) ?>;
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

async function deleteClient() {
    if (!confirm('Delete this client? This cannot be undone.')) {
        return;
    }
    try {
        const response = await App.api.delete('api/clients.php?id=' + encodeURIComponent(CLIENT_ID));
        if (!response.success) {
            throw new Error('Delete failed');
        }
        notify('Client deleted.', 'success');
        setTimeout(function() {
            window.location.href = '?page=clients';
        }, 120);
    } catch (error) {
        notify(getApiError(error, 'Failed to delete client.'), 'error');
    }
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
