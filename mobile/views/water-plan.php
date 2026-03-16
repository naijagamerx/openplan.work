<?php
/**
 * Mobile Water Plan Overview
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
        <p><a href="?page=dashboard">Return to dashboard</a></p>
    </body></html>');
}

$waterPlans = $db->load('water_plans') ?? [];
$activePlan = null;
$todayDate = date('Y-m-d');
$didDeactivate = false;
foreach ($waterPlans as $index => $plan) {
    if (($plan['isActive'] ?? false) !== true) {
        continue;
    }

    $planDate = !empty($plan['createdAt']) ? date('Y-m-d', strtotime((string)$plan['createdAt'])) : $todayDate;
    if ($planDate !== $todayDate) {
        $waterPlans[$index]['isActive'] = false;
        $waterPlans[$index]['autoClosedAt'] = date('c');
        $didDeactivate = true;
        continue;
    }

    if (!$activePlan || (($plan['createdAt'] ?? '') > ($activePlan['createdAt'] ?? ''))) {
        $activePlan = $plan;
    }
}

if ($didDeactivate) {
    $db->save('water_plans', $waterPlans);
}

if (!function_exists('formatLitersMobile')) {
    function formatLitersMobile($ml) {
        return number_format(((float)$ml) / 1000, 2);
    }
}

$schedule = $activePlan['schedule'] ?? [];
$glassSizeMl = (int)($activePlan['glassSize'] ?? 250);
if ($glassSizeMl <= 0) {
    $glassSizeMl = 250;
}

$plannedMl = 0;
$completedMl = 0;
$missedCount = 0;
foreach ($schedule as $item) {
    $rawAmount = (float)($item['amount'] ?? 0);
    $amountMl = $rawAmount <= 20 ? (int)round($rawAmount * $glassSizeMl) : (int)round($rawAmount);
    if ($amountMl <= 0) {
        $amountMl = $glassSizeMl;
    }
    $plannedMl += $amountMl;
    if (!empty($item['completed'])) {
        $completedMl += $amountMl;
    }
    if (!empty($item['missed'])) {
        $missedCount++;
    }
}

$goalMl = (int)($activePlan['dailyGoal'] ?? 2000);
$progress = $plannedMl > 0 ? min(100, round(($completedMl / $plannedMl) * 100)) : 0;
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Water Plan - <?= htmlspecialchars($siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            fontFamily: { display: ["Inter", "sans-serif"] }
        }
    }
}
</script>
<style type="text/tailwindcss">
@layer base {
    body {
        @apply bg-zinc-50 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
    }
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden border-x border-zinc-200 dark:border-zinc-800">
<?php
$title = 'Water Plan';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=new-water-plan';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-4 pt-4 pb-32 space-y-4">
    <?php if (!$activePlan): ?>
        <section class="border-2 border-black dark:border-white bg-white dark:bg-zinc-900 p-6 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400 mb-2">No Active Plan</p>
            <h2 class="text-xl font-black tracking-tight">Create your hydration schedule</h2>
            <p class="text-sm text-zinc-500 mt-2">Track water reminders in liters and log each drink on time.</p>
            <div class="mt-6 flex gap-2 justify-center">
                <a href="?page=new-water-plan" class="inline-block px-5 py-3 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.2em]">Create Plan</a>
                <a href="?page=water-plan-history" class="inline-block px-5 py-3 border border-black dark:border-white text-[11px] font-black uppercase tracking-[0.2em]">History</a>
            </div>
        </section>
    <?php else: ?>
        <section class="border-2 border-black dark:border-white bg-black text-white dark:bg-white dark:text-black p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] opacity-70">Current Plan</p>
                    <h2 class="text-xl font-black tracking-tight mt-1"><?= htmlspecialchars($activePlan['name'] ?? 'My Water Plan') ?></h2>
                </div>
                <span class="text-3xl font-black"><?= $progress ?>%</span>
            </div>
            <div class="mt-4 h-2 bg-white/20 dark:bg-black/20">
                <div class="h-full bg-white dark:bg-black" style="width: <?= $progress ?>%"></div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="px-2 py-1 text-[10px] font-black uppercase tracking-widest border border-white/40 dark:border-black/40">
                    <?= formatLitersMobile($completedMl) ?> / <?= formatLitersMobile($goalMl) ?> L
                </span>
                <span class="px-2 py-1 text-[10px] font-black uppercase tracking-widest border border-white/40 dark:border-black/40">
                    <?= count($schedule) ?> reminders
                </span>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-2">
            <div class="border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-900">
                <p class="text-[9px] font-black uppercase tracking-widest text-zinc-400">Planned</p>
                <p class="text-lg font-black mt-1"><?= formatLitersMobile($plannedMl) ?><span class="text-xs ml-1">L</span></p>
            </div>
            <div class="border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-900">
                <p class="text-[9px] font-black uppercase tracking-widest text-zinc-400">Completed</p>
                <p class="text-lg font-black mt-1"><?= formatLitersMobile($completedMl) ?><span class="text-xs ml-1">L</span></p>
            </div>
            <div class="border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-900">
                <p class="text-[9px] font-black uppercase tracking-widest text-zinc-400">Missed</p>
                <p id="mobile-missed-count" class="text-lg font-black mt-1"><?= (int)$missedCount ?></p>
            </div>
        </section>

        <section class="border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-4 py-3 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Today Schedule</h3>
            </div>
            <?php if (empty($schedule)): ?>
                <div class="p-5 text-sm text-zinc-500">No reminders configured yet.</div>
            <?php else: ?>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <?php foreach ($schedule as $item): ?>
                        <?php
                        $rawAmount = (float)($item['amount'] ?? 0);
                        $amountMl = $rawAmount <= 20 ? (int)round($rawAmount * $glassSizeMl) : (int)round($rawAmount);
                        if ($amountMl <= 0) {
                            $amountMl = $glassSizeMl;
                        }
                        $statusText = 'Scheduled';
                        $statusClass = 'text-blue-600';
                        if (!empty($item['completed'])) {
                            $statusText = 'Completed';
                            $statusClass = 'text-green-600';
                        } elseif (!empty($item['missed'])) {
                            $statusText = 'Missed';
                            $statusClass = 'text-red-600';
                        }
                        $scheduleId = (string)($item['id'] ?? '');
                        $scheduleTime = (string)($item['time'] ?? '');
                        $completedFlag = !empty($item['completed']) ? '1' : '0';
                        $missedFlag = !empty($item['missed']) ? '1' : '0';
                        ?>
                        <div
                            class="px-4 py-3 flex items-center justify-between"
                            data-water-row="1"
                            data-schedule-id="<?= htmlspecialchars($scheduleId, ENT_QUOTES) ?>"
                            data-time="<?= htmlspecialchars($scheduleTime, ENT_QUOTES) ?>"
                            data-completed="<?= $completedFlag ?>"
                            data-missed="<?= $missedFlag ?>"
                        >
                            <div>
                                <p class="text-sm font-bold"><?= htmlspecialchars($item['time'] ?? '--:--') ?></p>
                                <p class="text-[11px] text-zinc-500"><?= formatLitersMobile($amountMl) ?> L</p>
                            </div>
                            <span data-water-status-text="1" class="text-[10px] font-black uppercase tracking-widest <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <div class="grid grid-cols-3 gap-2">
            <a href="?page=water-plan-details<?= !empty($activePlan['id']) ? '&planId=' . urlencode($activePlan['id']) : '' ?>" class="text-center py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-[0.2em]">View Details</a>
            <a href="?page=new-water-plan<?= !empty($activePlan['id']) ? '&planId=' . urlencode($activePlan['id']) : '' ?>" class="text-center py-3 border border-black dark:border-white text-[10px] font-black uppercase tracking-[0.2em]">Edit Plan</a>
            <a href="?page=water-plan-history" class="text-center py-3 border border-black dark:border-white text-[10px] font-black uppercase tracking-[0.2em]">History</a>
        </div>
    <?php endif; ?>
</main>

<?php
$activePage = 'dashboard';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php';
?>

<script>
const APP_URL = '<?= APP_URL ?>';
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
const ACTIVE_PLAN_ID = <?= json_encode($activePlan['id'] ?? '') ?>;
const PLAN_CREATED_AT = <?= json_encode($activePlan['createdAt'] ?? '') ?>;
const MISSED_GRACE_MINUTES = 10;
const pendingMissMarks = new Set();

function getPlanCreatedAt() {
    return PLAN_CREATED_AT ? new Date(PLAN_CREATED_AT) : null;
}

function getScheduledTimeToday(timeValue) {
    const now = new Date();
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
    const [hours, minutes] = String(timeValue || '').split(':');
    date.setHours(parseInt(hours || '0', 10), parseInt(minutes || '0', 10), 0, 0);
    return date;
}

function applyStatusStyle(statusEl, status) {
    if (!statusEl) return;

    statusEl.textContent = status;
    statusEl.classList.remove('text-blue-600', 'text-green-600', 'text-red-600', 'text-zinc-700', 'text-zinc-400', 'dark:text-zinc-300');

    if (status === 'Completed') {
        statusEl.classList.add('text-green-600');
    } else if (status === 'Missed') {
        statusEl.classList.add('text-red-600');
    } else if (status === 'Due') {
        statusEl.classList.add('text-zinc-700', 'dark:text-zinc-300');
    } else if (status === 'N/A') {
        statusEl.classList.add('text-zinc-400');
    } else {
        statusEl.classList.add('text-blue-600');
    }
}

async function markReminderMissed(scheduleItemId, rowEl) {
    if (!ACTIVE_PLAN_ID || !scheduleItemId || pendingMissMarks.has(scheduleItemId)) {
        return;
    }

    pendingMissMarks.add(scheduleItemId);
    try {
        const response = await App.api.post('api/habits.php?action=mark_reminder_missed', {
            planId: ACTIVE_PLAN_ID,
            scheduleItemId: scheduleItemId,
            csrf_token: CSRF_TOKEN
        });

        if (response && response.success && rowEl) {
            rowEl.dataset.missed = '1';
        }
    } catch (error) {
        console.warn('Failed to auto-mark missed reminder on mobile overview:', error);
    } finally {
        pendingMissMarks.delete(scheduleItemId);
    }
}

async function refreshWaterStatuses() {
    const rows = document.querySelectorAll('[data-water-row="1"]');
    if (!rows.length) {
        return;
    }

    const now = new Date();
    const graceMs = MISSED_GRACE_MINUTES * 60 * 1000;
    const planCreatedAt = getPlanCreatedAt();
    let missedCount = 0;
    const toMarkMissed = [];

    rows.forEach((row) => {
        const statusEl = row.querySelector('[data-water-status-text="1"]');
        const timeValue = row.dataset.time || '';
        const scheduleId = row.dataset.scheduleId || '';
        const isCompleted = row.dataset.completed === '1';
        const isMissed = row.dataset.missed === '1';

        if (isCompleted) {
            applyStatusStyle(statusEl, 'Completed');
            return;
        }

        if (isMissed) {
            missedCount += 1;
            applyStatusStyle(statusEl, 'Missed');
            return;
        }

        if (!timeValue) {
            applyStatusStyle(statusEl, 'Scheduled');
            return;
        }

        const due = getScheduledTimeToday(timeValue);
        const isBeforePlan = planCreatedAt
            && !Number.isNaN(planCreatedAt.getTime())
            && due.toDateString() === planCreatedAt.toDateString()
            && due < planCreatedAt;

        if (isBeforePlan) {
            applyStatusStyle(statusEl, 'N/A');
            return;
        }

        const overdueByMs = now.getTime() - due.getTime();
        if (overdueByMs > graceMs) {
            missedCount += 1;
            applyStatusStyle(statusEl, 'Missed');
            if (scheduleId) {
                toMarkMissed.push({ scheduleId, row });
            }
            return;
        }

        if (overdueByMs >= 0) {
            applyStatusStyle(statusEl, 'Due');
            return;
        }

        applyStatusStyle(statusEl, 'Scheduled');
    });

    const missedCountEl = document.getElementById('mobile-missed-count');
    if (missedCountEl) {
        missedCountEl.textContent = String(missedCount);
    }

    for (const item of toMarkMissed) {
        await markReminderMissed(item.scheduleId, item.row);
    }
}
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
    refreshWaterStatuses();
    setInterval(() => {
        refreshWaterStatuses();
    }, 60000);
});
</script>
</div>
</body>
</html>
