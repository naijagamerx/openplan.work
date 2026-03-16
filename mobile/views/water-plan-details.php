<?php
/**
 * Mobile Water Plan Details
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

$planId = $_GET['planId'] ?? '';
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Water Plan Details - <?= htmlspecialchars($siteName) ?></title>
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
    theme: { extend: { fontFamily: { display: ["Inter", "sans-serif"] } } }
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
$title = 'Plan Details';
$leftAction = 'back';
$rightAction = 'add';
$rightTarget = '?page=new-water-plan';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-4 pt-4 pb-32 space-y-4">
    <section id="loading-state" class="border border-zinc-200 dark:border-zinc-700 p-5 text-sm text-zinc-500">Loading plan details...</section>
    <section id="error-state" class="hidden border border-red-200 bg-red-50 p-5">
        <p class="text-sm font-bold text-red-700">Failed to load plan details.</p>
        <a href="?page=water-plan" class="mt-3 inline-block text-[10px] font-black uppercase tracking-widest text-red-700">Back to Water Plan</a>
    </section>

    <section id="details-state" class="hidden space-y-4">
        <section class="grid grid-cols-2 gap-2">
            <div class="border-2 border-black dark:border-white bg-black text-white dark:bg-white dark:text-black p-3">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] opacity-70">Planned</p>
                <p id="stat-planned" class="text-2xl font-black mt-1">0.00</p>
                <p class="text-[9px] mt-1">L</p>
            </div>
            <div class="border border-zinc-200 dark:border-zinc-700 p-3 bg-white dark:bg-zinc-900">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400">Completed</p>
                <p id="stat-completed" class="text-2xl font-black mt-1">0.00</p>
                <p class="text-[9px] mt-1">L</p>
            </div>
            <div class="border border-zinc-200 dark:border-zinc-700 p-3 bg-white dark:bg-zinc-900">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400">Missed</p>
                <p id="stat-missed" class="text-2xl font-black mt-1">0</p>
            </div>
            <div class="border border-zinc-200 dark:border-zinc-700 p-3 bg-white dark:bg-zinc-900">
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-zinc-400">Progress</p>
                <p id="stat-progress" class="text-2xl font-black mt-1">0%</p>
            </div>
        </section>

        <section class="border border-zinc-200 dark:border-zinc-700 p-4 bg-zinc-50 dark:bg-zinc-900">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Today's Progress</h3>
                <span id="progress-summary" class="text-[10px] font-black uppercase tracking-widest text-zinc-500">0.00 / 0.00 L</span>
            </div>
            <div class="w-full h-3 bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
                <div id="progress-fill" class="h-full bg-black dark:bg-white" style="width: 0%"></div>
            </div>
            <button id="quick-log-btn" onclick="quickLogOneDrink()" class="mt-4 w-full py-3 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-[0.2em] disabled:opacity-50 disabled:cursor-not-allowed">
                Log 1 Drink
            </button>
            <p id="quick-log-hint" class="mt-2 text-[11px] text-zinc-500"></p>
        </section>

        <section class="border border-zinc-200 dark:border-zinc-700 p-4 bg-zinc-50 dark:bg-zinc-900 relative overflow-hidden">
            <div class="absolute -right-5 -top-4 text-zinc-200 dark:text-zinc-800 text-7xl leading-none">💧</div>
            <div class="relative z-10">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em] mb-3">Hydration Inspiration</h3>
                <p class="text-sm italic font-medium leading-relaxed mb-3">"Water is the driving force of all nature."</p>
                <div class="flex flex-wrap gap-2">
                    <span class="px-2 py-1 bg-black dark:bg-white text-white dark:text-black text-[9px] font-black uppercase tracking-widest rounded-full">Stay Sharp</span>
                    <span class="px-2 py-1 border border-black dark:border-white text-[9px] font-black uppercase tracking-widest rounded-full">Drink on time</span>
                </div>
            </div>
        </section>

        <section class="border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Planned vs Actual</h3>
            </div>
            <div id="schedule-empty" class="px-3 py-5 text-sm text-zinc-500 hidden">No reminders in this plan.</div>
            <div id="schedule-list" class="divide-y divide-zinc-100 dark:divide-zinc-800"></div>
        </section>
    </section>
</main>

<?php
$activePage = 'dashboard';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php';
?>

<script>
const APP_URL = '<?= APP_URL ?>';
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
const PLAN_ID = '<?= htmlspecialchars($planId, ENT_QUOTES) ?>';

const MISSED_GRACE_MINUTES = 10;
const PRE_ALERT_MINUTES = 5;

let currentPlan = null;
let dailyTracking = null;
let dueItemId = null;
let autoMissInterval = null;
let aggressiveReminderInterval = null;
let reminderMinuteMap = {};

function toLiters(ml) { return (Number(ml || 0) / 1000).toFixed(2); }
function getPlanCreatedAt() { return currentPlan?.createdAt ? new Date(currentPlan.createdAt) : new Date(); }
function getGlassSizeMl() { return Math.max(1, Number(currentPlan?.glassSize) || 250); }
function formatTime(time) {
    if (!time) return '--:--';
    const [h, m] = String(time).split(':');
    const hour = parseInt(h || '0', 10);
    const hour12 = hour % 12 || 12;
    const ampm = hour >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${ampm}`;
}

function getAmountMl(item) {
    const raw = Number(item?.amount) || 0;
    const size = getGlassSizeMl();
    if (raw <= 0) return size;
    return raw <= 20 ? Math.round(raw * size) : Math.round(raw);
}

function getScheduledTime(item) {
    const date = new Date(getPlanCreatedAt());
    const [h, m] = String(item?.time || '').split(':');
    date.setHours(parseInt(h || '0', 10), parseInt(m || '0', 10), 0, 0);
    return date;
}

async function refreshTracking() {
    try {
        const tracking = await App.api.get('api/habits.php?action=get_daily_tracking');
        if (tracking.success) {
            dailyTracking = tracking.data;
            if (!PLAN_ID && tracking.data?.activePlan) {
                currentPlan = tracking.data.activePlan;
            }
            return true;
        }
    } catch (error) {
        console.warn('Mobile daily tracking unavailable, continuing with plan load:', error);
    }
    return false;
}

async function loadPlanDetails() {
    try {
        await refreshTracking();
        if (!currentPlan) {
            const url = PLAN_ID ? `api/habits.php?action=get_water_plan&planId=${encodeURIComponent(PLAN_ID)}` : 'api/habits.php?action=get_water_plan';
            const response = await App.api.get(url);
            if (response.success) currentPlan = response.data;
        }

        if (!currentPlan) {
            showErrorState();
            return;
        }

        const didMark = await autoMarkMissed();
        if (didMark) {
            await refreshTracking();
        }

        renderDetails();
        startAggressiveReminders();
        showDetailsState();
    } catch (error) {
        console.error('Failed to load mobile water details:', error);
        showErrorState();
    }
}

async function autoMarkMissed() {
    if (!currentPlan || !Array.isArray(currentPlan.schedule) || !currentPlan.id) return false;
    const now = new Date();
    const planCreatedAt = getPlanCreatedAt();
    const graceMs = MISSED_GRACE_MINUTES * 60 * 1000;
    const dueMissed = currentPlan.schedule.filter(item => {
        if (!item || item.completed || item.missed || !item.id) return false;
        const scheduled = getScheduledTime(item);
        if (scheduled < planCreatedAt) return false;
        return now.getTime() - scheduled.getTime() > graceMs;
    });

    if (dueMissed.length === 0) return false;
    let changed = false;
    for (const item of dueMissed) {
        try {
            const response = await App.api.post('api/habits.php?action=mark_reminder_missed', {
                planId: currentPlan.id,
                scheduleItemId: item.id,
                csrf_token: CSRF_TOKEN
            });
            if (response.success) {
                item.missed = true;
                changed = true;
            }
        } catch (error) {
            console.error('Failed to mark missed reminder on mobile:', error);
        }
    }
    return changed;
}

function renderDetails() {
    const schedule = currentPlan.schedule || [];
    const plannedMl = schedule.reduce((sum, item) => sum + getAmountMl(item), 0);
    const completedMl = schedule.reduce((sum, item) => sum + (item.completed ? getAmountMl(item) : 0), 0);
    const missedCount = schedule.filter(item => item?.missed).length;
    const progress = plannedMl > 0 ? Math.round((completedMl / plannedMl) * 100) : 0;

    document.getElementById('stat-planned').textContent = toLiters(plannedMl);
    document.getElementById('stat-completed').textContent = toLiters(completedMl);
    document.getElementById('stat-missed').textContent = String(missedCount);
    document.getElementById('stat-progress').textContent = `${progress}%`;
    document.getElementById('progress-summary').textContent = `${toLiters(completedMl)} / ${toLiters(plannedMl)} L`;
    document.getElementById('progress-fill').style.width = `${progress}%`;

    const list = document.getElementById('schedule-list');
    const empty = document.getElementById('schedule-empty');
    list.innerHTML = '';
    if (schedule.length === 0) {
        empty.classList.remove('hidden');
    } else {
        empty.classList.add('hidden');
        const now = new Date();
        const planCreatedAt = getPlanCreatedAt();
        const isPastDay = planCreatedAt.toDateString() !== now.toDateString() && planCreatedAt < now;

        schedule.forEach(item => {
            const due = getScheduledTime(item);
            const isPast = due < now;
            const isBeforePlan = due < planCreatedAt;
            let status = 'Scheduled';
            let statusClass = 'text-blue-600';
            if (item.completed) {
                status = 'Completed';
                statusClass = 'text-green-600';
            } else if (item.missed) {
                status = 'Missed';
                statusClass = 'text-red-600';
            } else if (isPastDay) {
                status = 'Expired';
                statusClass = 'text-zinc-400';
            } else if (isPast && isBeforePlan) {
                status = 'N/A';
                statusClass = 'text-zinc-400';
            } else if (isPast) {
                status = 'Due';
                statusClass = 'text-zinc-700 dark:text-zinc-300';
            }

            const row = document.createElement('div');
            row.className = 'px-3 py-3 flex items-center justify-between';
            row.innerHTML = `
                <div>
                    <p class="text-sm font-bold">${formatTime(item.time)}</p>
                    <p class="text-[11px] text-zinc-500">${toLiters(getAmountMl(item))} L</p>
                </div>
                <span class="text-[10px] font-black uppercase tracking-widest ${statusClass}">${status}</span>
            `;
            list.appendChild(row);
        });
    }

    updateQuickLogState(schedule);
}

function updateQuickLogState(schedule) {
    const btn = document.getElementById('quick-log-btn');
    const hint = document.getElementById('quick-log-hint');
    dueItemId = null;

    if (!Array.isArray(schedule) || schedule.length === 0) {
        btn.disabled = true;
        hint.textContent = 'Create a schedule to enable quick log.';
        return;
    }

    const now = new Date();
    const planCreatedAt = getPlanCreatedAt();
    const dueItems = schedule
        .filter(item => item && !item.completed && !item.missed && item.time)
        .filter(item => {
            const when = getScheduledTime(item);
            return when >= planCreatedAt && when <= now;
        })
        .sort((a, b) => getScheduledTime(a) - getScheduledTime(b));

    const dueItem = dueItems[0] || null;
    dueItemId = dueItem?.id || null;
    btn.disabled = !dueItemId;

    if (dueItem) {
        hint.textContent = `1 tap logs ${toLiters(getAmountMl(dueItem))} L.`;
        return;
    }

    const nextItem = schedule
        .filter(item => item && !item.completed && !item.missed && item.time)
        .filter(item => getScheduledTime(item) >= now)
        .sort((a, b) => getScheduledTime(a) - getScheduledTime(b))[0];
    hint.textContent = nextItem ? `Quick log unlocks at ${formatTime(nextItem.time)}.` : 'No pending reminders right now.';
}

async function markComplete(scheduleItemId) {
    if (!currentPlan?.id || !scheduleItemId) return;
    try {
        const response = await App.api.post('api/habits.php?action=complete_reminder', {
            planId: currentPlan.id,
            scheduleItemId,
            csrf_token: CSRF_TOKEN
        });
        if (response.success) {
            if (window.App && App.notifications && typeof App.notifications.playSound === 'function') {
                App.notifications.playSound();
            }
            await loadPlanDetails();
        }
    } catch (error) {
        console.error('Failed to complete reminder on mobile:', error);
    }
}

async function quickLogOneDrink() {
    if (!dueItemId) return;
    await markComplete(dueItemId);
}

function maybeSendAggressiveReminder(item, due, now) {
    const amountText = `${toLiters(getAmountMl(item))} L`;
    const body = now < due
        ? `Drink ${amountText} at ${formatTime(item.time)}.`
        : `Drink ${amountText}. This reminder is still pending.`;

    if (window.App && App.notifications && typeof App.notifications.playSound === 'function') {
        App.notifications.playSound();
    }
    if (window.App && App.notifications && typeof App.notifications.send === 'function') {
        App.notifications.send('Water Reminder', {
            body,
            tag: `mobile-water-reminder-${item.id || item.time}`,
            requireInteraction: true
        });
    }
}

function reminderTick() {
    if (!currentPlan || !Array.isArray(currentPlan.schedule)) return;
    const now = new Date();
    const minuteKey = Math.floor(now.getTime() / 60000);
    const planCreatedAt = getPlanCreatedAt();
    const graceMs = MISSED_GRACE_MINUTES * 60 * 1000;

    currentPlan.schedule.forEach(item => {
        if (!item || item.completed || item.missed || !item.time) return;
        const due = getScheduledTime(item);
        if (due < planCreatedAt) return;
        const startWindow = due.getTime() - (PRE_ALERT_MINUTES * 60 * 1000);
        const endWindow = due.getTime() + graceMs;
        if (now.getTime() < startWindow) return;
        if (now.getTime() > endWindow) return;

        const key = item.id || item.time;
        if (reminderMinuteMap[key] === minuteKey) return;
        reminderMinuteMap[key] = minuteKey;
        maybeSendAggressiveReminder(item, due, now);
    });
}

function stopAggressiveReminders() {
    if (aggressiveReminderInterval) {
        clearInterval(aggressiveReminderInterval);
        aggressiveReminderInterval = null;
    }
    reminderMinuteMap = {};
}

function startAggressiveReminders() {
    stopAggressiveReminders();
    reminderTick();
    aggressiveReminderInterval = setInterval(reminderTick, 60000);
}

function showErrorState() {
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('details-state').classList.add('hidden');
    document.getElementById('error-state').classList.remove('hidden');
}

function showDetailsState() {
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('error-state').classList.add('hidden');
    document.getElementById('details-state').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', async () => {
    Mobile.init();
    await loadPlanDetails();
    if (autoMissInterval) clearInterval(autoMissInterval);
    autoMissInterval = setInterval(async () => {
        const changed = await autoMarkMissed();
        if (changed) {
            await refreshTracking();
            renderDetails();
        }
    }, 60000);
});

window.addEventListener('beforeunload', () => {
    stopAggressiveReminders();
    if (autoMissInterval) {
        clearInterval(autoMissInterval);
        autoMissInterval = null;
    }
});
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
</div>
</body>
</html>
