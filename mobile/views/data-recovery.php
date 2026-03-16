<?php
/**
 * Mobile Data Recovery View
 *
 * Mobile-optimized data recovery interface for:
 * - Running diagnostic checks on collections
 * - Recovering data locked with old password
 * - Re-encrypting collections with current password
 *
 * ADMIN ONLY: Data recovery affects all user data
 */

require_once '../../config.php';

if (!Auth::isAdmin()) {
    http_response_code(403);
    $siteName = getSiteName() ?? 'LazyMan';
    ?>
    <!DOCTYPE html>
    <html class="light" lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
        <title>Access Denied - <?= htmlspecialchars($siteName) ?></title>
        <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
        <style>body { font-family: 'Inter', sans-serif; }</style>
    </head>
    <body class="bg-gray-100 flex justify-center">
        <div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden">
            <div class="flex-1 flex items-center justify-center p-6">
                <div class="text-center">
                    <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <h2 class="text-2xl font-bold text-gray-900 mb-3">Administrator Access Required</h2>
                    <p class="text-gray-600 mb-6">Only administrators can perform data recovery.</p>
                    <a href="?page=dashboard" class="inline-flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return;
}

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired.</p>
        <p><a href="?page=login">Log in again</a>.</p>
    </body></html>');
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Data Recovery - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#000000",
                    "background-light": "#F9FAFB",
                    "background-dark": "#0A0A0A",
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"],
                },
                borderRadius: {
                    DEFAULT: "12px",
                },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    body {
        font-family: 'Inter', sans-serif;
        -webkit-tap-highlight-color: transparent;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .safe-bottom {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Data Recovery';
$leftAction = 'back';
$backUrl = '?page=data-management';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Banner -->
    <div class="bg-gray-100 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-800 p-4 mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-gray-600 dark:text-zinc-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <div class="text-sm text-gray-900 dark:text-zinc-100">
                <p class="font-semibold mb-1">Recover Locked Data</p>
                <p class="text-xs text-gray-600 dark:text-zinc-400">If you changed your password, some collections may still be encrypted with the old one.</p>
            </div>
        </div>
    </div>

    <!-- Step 1: Run Diagnostic -->
    <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <span class="text-lg font-bold">1</span>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Run Diagnostic</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Check which collections are accessible</p>
            </div>
        </div>

        <button onclick="runDiagnostic()" id="diagnostic-btn" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Check Collections
        </button>
    </div>

    <!-- Step 2: Recovery Form (shown after diagnostic) -->
    <div id="recovery-section" class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 mb-4 shadow-sm hidden">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-black dark:bg-white text-white dark:text-black flex items-center justify-center">
                <span class="text-lg font-bold">2</span>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-zinc-100">Recover Collections</h3>
                <p class="text-xs text-gray-500 dark:text-zinc-400">Enter old password to decrypt</p>
            </div>
        </div>

        <!-- Diagnostic Results -->
        <div id="diagnostic-results" class="mb-4 p-4 bg-gray-50 dark:bg-zinc-800">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-bold text-gray-700 dark:text-zinc-300">Collections Status</span>
                <span id="stats-summary" class="text-xs text-gray-500 dark:text-zinc-400"></span>
            </div>
            <div id="collections-list" class="space-y-2"></div>
        </div>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-zinc-300 mb-2">
                    Old Master Password
                </label>
                <input type="password" id="old-password"
                       class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100"
                       placeholder="Enter your previous password">
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">
                    This is the password you used BEFORE changing it
                </p>
            </div>

            <button onclick="recoverCollections()" id="recover-btn" class="w-full py-3.5 bg-black dark:bg-white text-white dark:text-black font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition shadow-lg flex items-center justify-center gap-2 touch-target">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Recover Locked Collections
            </button>
        </div>
    </div>

    <!-- Help Section -->
    <div class="bg-gray-50 dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 p-4">
        <h4 class="font-bold text-gray-900 dark:text-zinc-100 mb-2 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            When to use this
        </h4>
        <ul class="text-xs text-gray-600 dark:text-zinc-400 space-y-1">
            <li>• You recently changed your master password</li>
            <li>• Some collections show as "locked" or "inaccessible"</li>
            <li>• You get decryption errors when loading data</li>
            <li>• You need to re-encrypt data with a new password</li>
        </ul>
    </div>

</div>

<!-- Toast Notification -->
<div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white opacity-0 transition-opacity duration-300 pointer-events-none z-50 safe-bottom">
    <span id="toast-message"></span>
</div>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>

<script>
const CSRF_TOKEN = '<?= Auth::csrfToken() ?>';
const APP_URL = '<?= APP_URL ?>';

const api = {
    async get(url) {
        const response = await fetch(`${APP_URL}/${url}`, {
            headers: { 'Accept': 'application/json' }
        });
        return response.json();
    },
    async post(url, data) {
        const response = await fetch(`${APP_URL}/${url}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    }
};

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const colors = {
        success: 'bg-black',
        error: 'bg-red-600',
        info: 'bg-gray-600',
        warning: 'bg-gray-800'
    };
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

let diagnosticResults = null;

async function runDiagnostic() {
    const btn = document.getElementById('diagnostic-btn');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Checking...';

    try {
        const response = await api.post('api/data-recovery.php?action=diagnostic', {
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            diagnosticResults = response.data;
            displayDiagnosticResults(response.data);
            document.getElementById('recovery-section').classList.remove('hidden');
            showToast('Diagnostic complete', 'success');
        } else {
            throw new Error(response.message || 'Diagnostic failed');
        }
    } catch (error) {
        showToast('Diagnostic failed: ' + error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}

function displayDiagnosticResults(data) {
    const collections = data.collections || [];
    const accessible = collections.filter(c => c.accessible).length;
    const locked = collections.filter(c => !c.accessible).length;

    document.getElementById('stats-summary').textContent = `${accessible} accessible, ${locked} locked`;

    const listEl = document.getElementById('collections-list');
    listEl.innerHTML = collections.map(col => `
        <div class="flex items-center justify-between py-2 px-3 bg-white dark:bg-zinc-900 rounded-lg">
            <span class="text-sm font-medium text-gray-700 dark:text-zinc-300 capitalize">${col.collection}</span>
            <span class="px-2 py-1 text-xs font-bold rounded ${col.accessible ? 'bg-black text-white dark:bg-white dark:text-black' : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'}">
                ${col.accessible ? 'OK' : 'LOCKED'}
            </span>
        </div>
    `).join('');

    // Hide recovery section if all collections are accessible
    if (locked === 0) {
        document.getElementById('recovery-section').innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-black dark:text-white mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm font-bold text-gray-900 dark:text-zinc-100">All Collections Accessible</p>
                <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">No recovery needed</p>
            </div>
        `;
    }
}

async function recoverCollections() {
    const oldPassword = document.getElementById('old-password').value.trim();

    if (!oldPassword) {
        showToast('Please enter your old password', 'error');
        return;
    }

    const btn = document.getElementById('recover-btn');
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Recovering...';

    try {
        const response = await api.post('api/data-recovery.php?action=recover', {
            old_password: oldPassword,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            const recovered = response.data.recovered || [];
            const failed = response.data.failed || [];

            let message = `Recovered ${recovered.length} collection(s)`;
            if (failed.length > 0) {
                message += `, ${failed.length} failed`;
            }

            showToast(message, 'success');

            // Refresh diagnostic
            setTimeout(() => {
                runDiagnostic();
            }, 2000);
        } else {
            throw new Error(response.message || 'Recovery failed');
        }
    } catch (error) {
        showToast('Recovery failed: ' + error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}
</script>

</body>
</html>
