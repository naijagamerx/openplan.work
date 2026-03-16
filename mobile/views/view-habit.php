<?php
/**
 * Mobile Habit Detail View
 *
 * Detailed mobile page for an individual habit.
 * Uses existing habit data model (no new fields).
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
        <p><a href="?page=habits">Back to Habits</a></p>
    </body></html>');
}

$habitId = trim((string)($_GET['id'] ?? ''));
$habits = $db->load('habits') ?? [];
$completions = $db->load('habit_completions') ?? [];
$timerSessions = $db->load('habit_timer_sessions') ?? [];

$habit = null;
foreach ($habits as $item) {
    if (($item['id'] ?? '') === $habitId) {
        $habit = $item;
        break;
    }
}

$habitCompletions = [];
foreach ($completions as $completion) {
    if (($completion['habitId'] ?? '') === $habitId) {
        $habitCompletions[] = $completion;
    }
}

$habitSessions = [];
foreach ($timerSessions as $session) {
    if (($session['habitId'] ?? '') === $habitId) {
        $habitSessions[] = $session;
    }
}

usort($habitCompletions, fn($a, $b) => strtotime($b['date'] ?? '') <=> strtotime($a['date'] ?? ''));
usort($habitSessions, fn($a, $b) => strtotime($b['createdAt'] ?? '') <=> strtotime($a['createdAt'] ?? ''));

$today = date('Y-m-d');
$todayCompleted = false;
$completeDates = [];

foreach ($habitCompletions as $completion) {
    if (($completion['status'] ?? '') === 'complete') {
        $date = (string)($completion['date'] ?? '');
        if ($date !== '') {
            $completeDates[$date] = true;
            if ($date === $today) {
                $todayCompleted = true;
            }
        }
    }
}

$completeDateKeys = array_keys($completeDates);
sort($completeDateKeys);

$currentStreak = 0;
$checkDate = new DateTime($today);
for ($i = 0; $i < 366; $i++) {
    $dateStr = $checkDate->format('Y-m-d');
    if (isset($completeDates[$dateStr])) {
        $currentStreak++;
        $checkDate->modify('-1 day');
        continue;
    }

    if ($i === 0 && !$todayCompleted) {
        $checkDate->modify('-1 day');
        continue;
    }
    break;
}

$bestStreak = 0;
$run = 0;
$prevDate = null;
foreach ($completeDateKeys as $dateStr) {
    $current = new DateTime($dateStr);
    if ($prevDate !== null) {
        $days = (int)$current->diff($prevDate)->days;
        $run = ($days === 1) ? ($run + 1) : 1;
    } else {
        $run = 1;
    }
    $bestStreak = max($bestStreak, $run);
    $prevDate = $current;
}

$totalCompletions = count($completeDateKeys);
$totalSeconds = 0;
foreach ($habitSessions as $session) {
    $totalSeconds += (int)($session['duration'] ?? 0);
}

$formatDuration = function(int $seconds): string {
    if ($seconds < 60) {
        return $seconds . ' sec';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . ' min';
    }
    $hours = floor($seconds / 3600);
    $mins = floor(($seconds % 3600) / 60);
    return $hours . 'h ' . $mins . 'm';
};

$category = ucfirst((string)($habit['category'] ?? 'general'));
$frequency = ucfirst((string)($habit['frequency'] ?? 'daily'));
$targetDuration = (int)($habit['targetDuration'] ?? 0);
$reminderTime = (string)($habit['reminderTime'] ?? '');
$isActive = !isset($habit['isActive']) || (bool)$habit['isActive'];
$isAiGenerated = !empty($habit['isAiGenerated']);
$createdAt = (string)($habit['createdAt'] ?? '');
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Habit Details - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#000000",
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a",
                },
                fontFamily: {
                    display: ["Inter", "sans-serif"],
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
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">
<?php
$title = $habit ? ($habit['name'] ?? 'Habit') : 'Habit';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<?php if (!$habit): ?>
    <main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-32 text-zinc-900 dark:text-zinc-100">
        <section class="border border-gray-200 dark:border-zinc-800 rounded-2xl p-6 mt-2 bg-white dark:bg-zinc-900">
            <h2 class="text-lg font-bold mb-2">Habit not found</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">The habit may have been deleted.</p>
            <a href="?page=habits" class="inline-block bg-black dark:bg-white text-white dark:text-black px-4 py-2 text-xs font-bold uppercase tracking-widest rounded-lg touch-target">
                Back to Habits
            </a>
        </section>
    </main>
<?php else: ?>
    <main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-32 text-zinc-900 dark:text-zinc-100">
        <section class="mt-2 mb-6">
            <div class="flex flex-wrap gap-2 mb-4">
                <span class="px-2 py-1 text-[10px] font-black uppercase tracking-widest border border-black dark:border-white">
                    <?= htmlspecialchars($category) ?>
                </span>
                <span class="px-2 py-1 text-[10px] font-black uppercase tracking-widest border border-gray-300 dark:border-zinc-700">
                    <?= htmlspecialchars($frequency) ?>
                </span>
                <span class="px-2 py-1 text-[10px] font-black uppercase tracking-widest <?= $isActive ? 'bg-black text-white dark:bg-white dark:text-black border border-black dark:border-white' : 'bg-gray-100 dark:bg-zinc-800 text-gray-500 dark:text-zinc-300 border border-gray-300 dark:border-zinc-700' ?>">
                    <?= $isActive ? 'Active' : 'Archived' ?>
                </span>
            </div>

            <h2 class="text-3xl font-black leading-none tracking-tighter uppercase mb-3">
                <?= htmlspecialchars((string)($habit['name'] ?? 'Habit')) ?>
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Created <?= $createdAt ? date('M j, Y', strtotime($createdAt)) : 'Unknown' ?>
            </p>
        </section>

        <section class="mb-6 border border-black/10 dark:border-zinc-800 rounded-2xl p-4 bg-white dark:bg-zinc-900">
            <div class="grid grid-cols-2 gap-3">
                <button
                    type="button"
                    id="today-toggle-btn"
                    data-completed="<?= $todayCompleted ? '1' : '0' ?>"
                    onclick="toggleTodayCompletion()"
                    class="<?= $todayCompleted ? 'bg-black dark:bg-white text-white dark:text-black' : 'border border-black dark:border-white' ?> text-[10px] font-black uppercase tracking-widest px-3 py-3 touch-target"
                >
                    <?= $todayCompleted ? 'Completed Today' : 'Mark Today' ?>
                </button>

                <a
                    href="?page=habit-form&id=<?= urlencode($habitId) ?>"
                    class="text-center border border-black dark:border-white text-[10px] font-black uppercase tracking-widest px-3 py-3 touch-target"
                >
                    Edit Habit
                </a>

                <button
                    type="button"
                    onclick="toggleArchive()"
                    class="border border-black dark:border-white text-[10px] font-black uppercase tracking-widest px-3 py-3 touch-target"
                >
                    <?= $isActive ? 'Archive' : 'Activate' ?>
                </button>

                <button
                    type="button"
                    onclick="deleteHabit()"
                    class="border border-red-400 text-red-600 text-[10px] font-black uppercase tracking-widest px-3 py-3 touch-target"
                >
                    Delete
                </button>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 mb-6">
            <div class="border border-black/10 dark:border-zinc-800 p-4 bg-white dark:bg-zinc-900">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Current Streak</div>
                <div class="text-2xl font-black"><?= (int)$currentStreak ?></div>
            </div>
            <div class="border border-black/10 dark:border-zinc-800 p-4 bg-white dark:bg-zinc-900">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Best Streak</div>
                <div class="text-2xl font-black"><?= (int)$bestStreak ?></div>
            </div>
            <div class="border border-black/10 dark:border-zinc-800 p-4 bg-white dark:bg-zinc-900">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Completions</div>
                <div class="text-2xl font-black"><?= (int)$totalCompletions ?></div>
            </div>
            <div class="border border-black/10 dark:border-zinc-800 p-4 bg-white dark:bg-zinc-900">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Time</div>
                <div class="text-2xl font-black"><?= htmlspecialchars($formatDuration((int)$totalSeconds)) ?></div>
            </div>
        </section>

        <section class="border border-black/10 dark:border-zinc-800 p-4 mb-6 bg-white dark:bg-zinc-900">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Habit Details</h3>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Reminder</span>
                    <span class="font-medium"><?= $reminderTime ? htmlspecialchars(date('g:i A', strtotime($reminderTime))) : 'Not set' ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Target Duration</span>
                    <span class="font-medium"><?= $targetDuration > 0 ? (int)$targetDuration . ' min' : 'Not set' ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-500 dark:text-gray-400">Source</span>
                    <span class="font-medium"><?= $isAiGenerated ? 'AI Suggested' : 'Manual' ?></span>
                </div>
            </div>
        </section>

        <section class="border border-black/10 dark:border-zinc-800 p-4 mb-6 bg-white dark:bg-zinc-900">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Recent Completions</h3>
            <?php if (empty($habitCompletions)): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No completion history yet.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach (array_slice($habitCompletions, 0, 10) as $completion): ?>
                        <?php if (($completion['status'] ?? '') !== 'complete') continue; ?>
                        <div class="flex items-center justify-between border-b border-black/5 dark:border-zinc-800 pb-2">
                            <span class="text-sm"><?= htmlspecialchars(date('D, M j Y', strtotime((string)($completion['date'] ?? 'now')))) ?></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Complete</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="border border-black/10 dark:border-zinc-800 p-4 bg-white dark:bg-zinc-900">
            <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3">Recent Timer Sessions</h3>
            <?php if (empty($habitSessions)): ?>
                <p class="text-sm text-gray-500 dark:text-gray-400">No timer sessions yet.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach (array_slice($habitSessions, 0, 8) as $session): ?>
                        <div class="flex items-center justify-between border-b border-black/5 dark:border-zinc-800 pb-2">
                            <span class="text-sm">
                                <?= htmlspecialchars(date('M j, g:i A', strtotime((string)($session['createdAt'] ?? 'now')))) ?>
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                <?= htmlspecialchars($formatDuration((int)($session['duration'] ?? 0))) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
<?php endif; ?>

<?php
$activePage = 'habits';
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
    const HABIT_ID = <?= json_encode($habitId) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
let isHabitTogglePending = false;

function notify(message, type) {
    if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
        Mobile.ui.showToast(message, type || 'info');
        return;
    }
    alert(message);
}

function getApiError(error, fallback) {
    if (error && error.response) {
        const apiErr = error.response.error;
        if (typeof apiErr === 'string' && apiErr) return apiErr;
        if (apiErr && typeof apiErr.message === 'string') return apiErr.message;
    }
    return (error && error.message) ? error.message : fallback;
}

function getLocalDateYmd() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

async function toggleTodayCompletion() {
    if (isHabitTogglePending) {
        return;
    }

    const btn = document.getElementById('today-toggle-btn');
    const currentlyCompleted = btn?.dataset?.completed === '1';
    const today = getLocalDateYmd();
    const nextStatus = currentlyCompleted ? 'incomplete' : 'complete';
    isHabitTogglePending = true;
    if (btn) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
    }

    try {
        const response = await App.api.post('api/habits.php?action=complete', {
            habitId: HABIT_ID,
            date: today,
            status: nextStatus,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            throw new Error('Failed to update habit');
        }
        if (window.Mobile && Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
            Mobile.ui.queueToast(nextStatus === 'complete' ? 'Habit marked complete.' : 'Habit marked incomplete.', 'success');
        }
        window.location.reload();
    } catch (error) {
        notify(getApiError(error, 'Failed to update habit status.'), 'error');
    } finally {
        isHabitTogglePending = false;
        if (btn) {
            btn.disabled = false;
            btn.style.opacity = '';
        }
    }
}

async function toggleArchive() {
    try {
        const response = await App.api.post('api/habits.php?action=toggle_active', {
            habitId: HABIT_ID,
            csrf_token: CSRF_TOKEN
        });
        if (!response.success) {
            throw new Error('Failed to update habit');
        }
        if (window.Mobile && Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
            Mobile.ui.queueToast(response.message || 'Habit updated.', 'success');
        }
        window.location.reload();
    } catch (error) {
        notify(getApiError(error, 'Failed to change archive state.'), 'error');
    }
}

async function deleteHabit() {
    if (!confirm('Delete this habit and its completion history?')) {
        return;
    }
    try {
        const response = await App.api.delete('api/habits.php?id=' + encodeURIComponent(HABIT_ID));
        if (!response.success) {
            throw new Error('Failed to delete habit');
        }
        if (window.Mobile && Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
            Mobile.ui.queueToast('Habit deleted.', 'success');
        }
        window.location.href = '?page=habits';
    } catch (error) {
        notify(getApiError(error, 'Failed to delete habit.'), 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.Mobile && typeof Mobile.init === 'function') {
        Mobile.init();
    }
});
</script>
</body>
</html>
