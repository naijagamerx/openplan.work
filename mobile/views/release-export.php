<?php
/**
 * Mobile Release Export Page
 * Admin-only release export for mobile
 */

require_once __DIR__ . '/../../config.php';

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
                    <p class="text-gray-600 mb-6">Only administrators can generate release exports.</p>
                    <div class="text-xs text-gray-400 mb-6 bg-gray-50 p-2 rounded">
                        Debug: Role=<?= htmlspecialchars(Auth::role()) ?>, ID=<?= htmlspecialchars(Auth::userId() ?? 'null') ?><br>
                        Session=<?= session_id() ?><br>
                        Config=<?= defined('ROOT_PATH') ? 'Loaded' : 'Not Loaded' ?>
                    </div>
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

$siteName = getSiteName() ?? 'LazyMan';
$csrfToken = Auth::csrfToken();
$outputDir = ROOT_PATH . '/release-artifacts';
$existingExports = [];

if (is_dir($outputDir)) {
    $files = glob($outputDir . '/*.zip');
    if ($files) {
        $existingExports = array_map(function($file) {
            return [
                'name' => basename($file),
                'size' => filesize($file),
                'modified' => filemtime($file)
            ];
        }, $files);
        usort($existingExports, fn($a, $b) => $b['modified'] - $a['modified']);
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Release Export - <?= htmlspecialchars($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: { primary: "#000000", secondary: "#333333", accent: "#666666" },
                fontFamily: { display: ["Inter", "sans-serif"] },
                borderRadius: { DEFAULT: "12px" },
            },
        },
    };
</script>
<style type="text/tailwindcss">
    body { font-family: 'Inter', sans-serif; -webkit-tap-highlight-color: transparent; }
    .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
    .rounded-2xl, .rounded-xl, .rounded-lg { border-radius: 0 !important; }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Release Export';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'document.getElementById(\'export-form\')?.requestSubmit()';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Card -->
    <div class="bg-black dark:bg-white rounded-2xl p-5 text-white dark:text-black mb-6">
        <h2 class="text-xl font-bold mb-2">Generate Clean Export</h2>
        <p class="text-sm opacity-80">Create a clean release artifact for deployment. Excludes development files, test files, and IDE configurations.</p>
    </div>

    <!-- Generate Form -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <h3 class="font-bold text-gray-900 dark:text-zinc-100 mb-4">New Export</h3>
        <form id="export-form" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-2">Export Type</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative">
                        <input type="radio" name="export_type" value="hosted" checked class="peer sr-only">
                        <div class="p-4 border-2 border-gray-200 dark:border-zinc-700 rounded-xl peer-checked:border-black peer-checked:bg-black peer-checked:text-white dark:peer-checked:bg-white dark:peer-checked:text-black transition cursor-pointer text-center">
                            <span class="font-bold text-sm">Hosted</span>
                        </div>
                    </label>
                    <label class="relative">
                        <input type="radio" name="export_type" value="local" class="peer sr-only">
                        <div class="p-4 border-2 border-gray-200 dark:border-zinc-700 rounded-xl peer-checked:border-black peer-checked:bg-black peer-checked:text-white dark:peer-checked:bg-white dark:peer-checked:text-black transition cursor-pointer text-center">
                            <span class="font-bold text-sm">Local</span>
                        </div>
                    </label>
                </div>
            </div>

            <button type="submit" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition">
                Generate Export
            </button>
        </form>
    </div>

    <!-- Existing Exports -->
    <?php if (!empty($existingExports)): ?>
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5">
        <h3 class="font-bold text-gray-900 dark:text-zinc-100 mb-4">Previous Exports</h3>
        <div class="space-y-3">
            <?php foreach ($existingExports as $export): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-gray-900 dark:text-zinc-100 truncate"><?= htmlspecialchars($export['name']) ?></p>
                    <p class="text-xs text-gray-500 dark:text-zinc-400"><?= date('M d, Y H:i', $export['modified']) ?> • <?= round($export['size'] / 1024 / 1024, 2) ?> MB</p>
                </div>
                <a href="<?= APP_URL ?>/release-artifacts/<?= htmlspecialchars($export['name']) ?>"
                   download class="p-2 text-gray-600 dark:text-zinc-400 hover:text-black dark:hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Toast -->
<div id="toast" class="fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white opacity-0 transition-opacity duration-300 pointer-events-none z-50 safe-bottom">
    <span id="toast-message"></span>
</div>

<?php include __DIR__ . '/partials/offcanvas-menu.php'; ?>

<script src="<?= APP_URL ?>/mobile/assets/js/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>

<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';
const APP_URL = '<?= APP_URL ?>';

function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    const colors = { success: 'bg-black dark:bg-white', error: 'bg-red-600', info: 'bg-gray-600' };
    toast.className = `fixed bottom-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-xl font-semibold text-white transition-opacity duration-300 pointer-events-none z-50 safe-bottom ${colors[type] || colors.info}`;
    toastMessage.textContent = message;
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

document.getElementById('export-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const exportType = formData.get('export_type');

    showToast('Generating export... This may take a moment.', 'info');

    try {
        const response = await fetch(`${APP_URL}/api/release-export.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                export_type: exportType,
                csrf_token: CSRF_TOKEN
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Export generated successfully!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(result.message || result.error?.message || 'Failed to generate export');
        }
    } catch (error) {
        showToast(error.message || 'Failed to generate export', 'error');
    }
});
</script>

</body>
</html>
