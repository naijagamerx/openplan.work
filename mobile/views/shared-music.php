<?php
/**
 * Mobile Shared Music Management Page
 * Admin-only shared Pomodoro audio library management
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
                    <p class="text-gray-600 mb-6">Only administrators can manage the shared music library.</p>
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
$mediaDir = ROOT_PATH . '/assets/media/pomodoro';
$sharedTracks = [];

if (is_dir($mediaDir)) {
    $files = array_diff(scandir($mediaDir), ['.', '..', '.gitkeep']);
    foreach ($files as $file) {
        $filePath = $mediaDir . '/' . $file;
        if (is_file($filePath) && preg_match('/\.(mp3|wav|m4a)$/i', $file)) {
            $sharedTracks[] = [
                'name' => pathinfo($file, PATHINFO_FILENAME),
                'file' => $file,
                'size' => filesize($filePath),
                'ext' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Shared Music - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Shared Music';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'document.getElementById(\'track-file\')?.click()';
include __DIR__ . '/partials/header-mobile.php';
?>

<div class="flex-1 overflow-y-auto px-4 py-6 safe-bottom">

    <!-- Info Card -->
    <div class="bg-black dark:bg-white rounded-2xl p-5 text-white dark:text-black mb-6">
        <h2 class="text-xl font-bold mb-2">Shared Music Library</h2>
        <p class="text-sm opacity-80">Manage Pomodoro audio tracks available to all users. These tracks are included in clean app exports.</p>
    </div>

    <!-- Upload Form -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5 mb-6">
        <h3 class="font-bold text-gray-900 dark:text-zinc-100 mb-4">Upload Track</h3>
        <form id="upload-form" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-2">Track Name</label>
                <input type="text" name="track_name" required class="w-full px-4 py-3 border border-gray-300 dark:border-zinc-700 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent dark:bg-zinc-800 dark:text-zinc-100" placeholder="Deep Focus Loop">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-zinc-300 mb-2">Audio File</label>
                <div class="relative">
                    <input type="file" id="track-file" name="track_file" accept=".mp3,.wav,.m4a" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="border-2 border-dashed border-gray-300 dark:border-zinc-600 rounded-xl p-6 text-center">
                        <p id="file-label" class="text-sm font-bold text-gray-400 uppercase tracking-widest">Choose audio file</p>
                        <p class="text-xs text-gray-500 dark:text-zinc-400 mt-1">MP3, WAV, or M4A</p>
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black rounded-xl font-bold hover:bg-gray-800 dark:hover:bg-gray-200 transition">
                Upload Track
            </button>
        </form>
    </div>

    <!-- Library Tracks -->
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-gray-200 dark:border-zinc-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-gray-900 dark:text-zinc-100">Library Tracks</h3>
            <span class="text-xs text-gray-500 dark:text-zinc-400"><?= count($sharedTracks) ?> tracks</span>
        </div>

        <?php if (empty($sharedTracks)): ?>
        <div class="text-center py-8">
            <svg class="w-12 h-12 text-gray-300 dark:text-zinc-700 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
            </svg>
            <p class="text-sm text-gray-500 dark:text-zinc-400">No shared tracks yet</p>
        </div>
        <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($sharedTracks as $track): ?>
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-zinc-800 rounded-xl border border-gray-200 dark:border-zinc-700">
                <div class="w-10 h-10 bg-black dark:bg-white rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white dark:text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-gray-900 dark:text-zinc-100 truncate"><?= htmlspecialchars($track['name']) ?></p>
                    <p class="text-xs text-gray-500 dark:text-zinc-400 uppercase"><?= $track['ext'] ?> • <?= round($track['size'] / 1024 / 1024, 2) ?> MB</p>
                </div>
                <button type="button" onclick="deleteTrack('<?= htmlspecialchars($track['file']) ?>')" class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

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
const CSRF_TOKEN = '<?= Auth::csrfToken() ?>';
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

// File input label update
document.getElementById('track-file').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        document.getElementById('file-label').textContent = file.name;
    }
});

// Upload form
document.getElementById('upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);
    const fileInput = document.getElementById('track-file');

    if (!fileInput.files[0]) {
        showToast('Please select a file', 'error');
        return;
    }

    formData.append('action', 'upload_shared');
    formData.append('csrf_token', CSRF_TOKEN);

    showToast('Uploading track...', 'info');

    try {
        const response = await fetch(`${APP_URL}/api/pomodoro.php`, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('Track uploaded successfully!', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(result.message || result.error?.message || 'Failed to upload track');
        }
    } catch (error) {
        showToast(error.message || 'Failed to upload track', 'error');
    }
});

// Delete track
async function deleteTrack(filename) {
    const confirmed = window.confirm(`Delete "${filename}"? This cannot be undone.`);
    if (!confirmed) return;

    try {
        const response = await fetch(`${APP_URL}/api/pomodoro.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_shared',
                filename: filename,
                csrf_token: CSRF_TOKEN
            })
        });

        const result = await response.json();

        if (result.success) {
            showToast('Track deleted', 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            throw new Error(result.message || result.error?.message || 'Failed to delete track');
        }
    } catch (error) {
        showToast(error.message || 'Failed to delete track', 'error');
    }
}
</script>

</body>
</html>
