<?php
/**
 * Mobile Create/Edit Water Plan
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

$planId = $_GET['planId'] ?? null;
$waterPlans = $db->load('water_plans') ?? [];
$editingPlan = null;
if ($planId) {
    foreach ($waterPlans as $plan) {
        if (($plan['id'] ?? '') === $planId) {
            $editingPlan = $plan;
            break;
        }
    }
}

$initialPlan = $editingPlan ?: [
    'id' => null,
    'name' => 'My Water Plan',
    'dailyGoal' => 2500,
    'glassSize' => 250,
    'schedule' => []
];

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title><?= $editingPlan ? 'Edit Water Plan' : 'New Water Plan' ?> - <?= htmlspecialchars($siteName) ?></title>
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
        extend: { fontFamily: { display: ["Inter", "sans-serif"] } }
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
$title = $editingPlan ? 'Edit Plan' : 'New Water Plan';
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-4 pt-4 pb-32 space-y-4">
    <section class="border-2 border-black dark:border-white p-4 bg-white dark:bg-zinc-900">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-400">Plan Setup</p>
        <h2 class="text-xl font-black tracking-tight mt-1"><?= $editingPlan ? 'Update your hydration plan' : 'Create your hydration plan' ?></h2>
    </section>

    <form id="mobile-water-plan-form" class="space-y-4">
        <div class="space-y-1">
            <label class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-500">Plan Name</label>
            <input id="plan-name" type="text" class="w-full border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-3 text-sm font-medium" value="<?= htmlspecialchars($initialPlan['name']) ?>">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-500">Daily Goal (L)</label>
                <input id="daily-goal" type="number" min="0.5" step="0.1" class="w-full border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-3 text-sm font-medium" value="<?= number_format(((float)$initialPlan['dailyGoal']) / 1000, 2, '.', '') ?>">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-zinc-500">Drink Size (L)</label>
                <input id="glass-size" type="number" min="0.1" step="0.05" class="w-full border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-3 text-sm font-medium" value="<?= number_format(((float)$initialPlan['glassSize']) / 1000, 2, '.', '') ?>">
            </div>
        </div>

        <section class="border border-zinc-200 dark:border-zinc-700">
            <div class="px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Quick Presets</h3>
                <button type="button" onclick="clearScheduleRows()" class="text-[10px] font-black uppercase tracking-widest text-red-600">Clear</button>
            </div>
            <div class="p-3 grid grid-cols-3 gap-2">
                <button type="button" onclick="applyPreset('fullDay')" class="py-2 border border-black dark:border-white text-[10px] font-black uppercase tracking-widest">Full Day</button>
                <button type="button" onclick="applyPreset('halfDay')" class="py-2 border border-black dark:border-white text-[10px] font-black uppercase tracking-widest">Half Day</button>
                <button type="button" onclick="applyPreset('workDay')" class="py-2 border border-black dark:border-white text-[10px] font-black uppercase tracking-widest">Work Day</button>
            </div>
        </section>

        <section class="border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="px-3 py-2 border-b border-zinc-200 dark:border-zinc-700 flex items-center justify-between">
                <h3 class="text-[10px] font-black uppercase tracking-[0.2em]">Schedule</h3>
                <button type="button" onclick="addScheduleRow()" class="px-2 py-1 bg-black dark:bg-white text-white dark:text-black text-[10px] font-black uppercase tracking-widest">Add</button>
            </div>
            <div id="schedule-empty" class="px-3 py-5 text-sm text-zinc-500">No reminders yet.</div>
            <div id="schedule-list" class="divide-y divide-zinc-100 dark:divide-zinc-800"></div>
        </section>

        <button type="submit" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.2em]">
            <?= $editingPlan ? 'Update Plan' : 'Save Plan' ?>
        </button>
    </form>
</main>

<?php
$activePage = 'dashboard';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php';
?>

<script>
(function() {
    const path = window.location.pathname;
    const baseMatch = path.match(/^(\/[^\/]*?)?\//);
    window.BASE_PATH = baseMatch ? (baseMatch[1] || '') : '';
})();
const APP_URL = window.location.origin + window.BASE_PATH;
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
const INITIAL_PLAN = <?= json_encode($initialPlan, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
const IS_EDIT = <?= $editingPlan ? 'true' : 'false' ?>;

const PRESETS = {
    fullDay: [
        { time: '07:00', amountL: 0.25 }, { time: '09:00', amountL: 0.25 }, { time: '11:00', amountL: 0.25 }, { time: '13:00', amountL: 0.25 },
        { time: '15:00', amountL: 0.25 }, { time: '17:00', amountL: 0.25 }, { time: '19:00', amountL: 0.25 }, { time: '21:00', amountL: 0.25 }
    ],
    halfDay: [
        { time: '08:00', amountL: 0.25 }, { time: '10:00', amountL: 0.25 }, { time: '12:00', amountL: 0.25 }, { time: '14:00', amountL: 0.25 }
    ],
    workDay: [
        { time: '09:00', amountL: 0.25 }, { time: '11:00', amountL: 0.25 }, { time: '13:00', amountL: 0.25 },
        { time: '15:00', amountL: 0.25 }, { time: '17:00', amountL: 0.25 }, { time: '19:00', amountL: 0.25 }
    ]
};

function litersToMl(value) {
    const parsed = parseFloat(value);
    if (!Number.isFinite(parsed) || parsed <= 0) return 0;
    return Math.round(parsed * 1000);
}

function normalizeRowAmountL(rawAmount, glassSizeMl) {
    const amount = Number(rawAmount) || 0;
    const amountMl = amount <= 20 ? Math.round(amount * glassSizeMl) : Math.round(amount);
    return (amountMl / 1000).toFixed(2);
}

function renderScheduleRow(time = '', amountL = '0.25') {
    const list = document.getElementById('schedule-list');
    const empty = document.getElementById('schedule-empty');
    const row = document.createElement('div');
    row.className = 'px-3 py-3 grid grid-cols-[1fr_auto_auto] items-center gap-2';
    row.innerHTML = `
        <input type="time" class="schedule-time border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 py-2 text-sm" value="${time}">
        <input type="number" min="0.1" step="0.05" class="schedule-amount border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2 py-2 text-sm w-20" value="${amountL}">
        <button type="button" class="text-red-600 text-[10px] font-black uppercase tracking-widest" onclick="this.closest('div').remove(); syncEmptyState();">Remove</button>
    `;
    list.appendChild(row);
    empty.classList.add('hidden');
}

function syncEmptyState() {
    const list = document.getElementById('schedule-list');
    const empty = document.getElementById('schedule-empty');
    empty.classList.toggle('hidden', list.children.length > 0);
}

function addScheduleRow() {
    renderScheduleRow('', '0.25');
}

function clearScheduleRows() {
    document.getElementById('schedule-list').innerHTML = '';
    syncEmptyState();
}

function applyPreset(name) {
    const preset = PRESETS[name];
    if (!preset) return;
    clearScheduleRows();
    preset.forEach(item => renderScheduleRow(item.time, Number(item.amountL).toFixed(2)));
}

async function submitPlan(e) {
    e.preventDefault();

    const name = document.getElementById('plan-name').value.trim() || 'My Water Plan';
    const dailyGoalMl = litersToMl(document.getElementById('daily-goal').value) || 2500;
    const glassSizeMl = litersToMl(document.getElementById('glass-size').value) || 250;

    const rows = Array.from(document.querySelectorAll('#schedule-list > div'));
    const schedule = rows.map(row => {
        const time = row.querySelector('.schedule-time').value;
        const amountMl = litersToMl(row.querySelector('.schedule-amount').value) || glassSizeMl;
        return { time, amount: amountMl };
    }).filter(item => item.time);

    if (schedule.length === 0) {
        alert('Please add at least one reminder.');
        return;
    }

    schedule.sort((a, b) => a.time.localeCompare(b.time));
    const endpoint = IS_EDIT ? 'api/habits.php?action=update_manual_plan' : 'api/habits.php?action=create_manual_plan';
    const payload = {
        name,
        dailyGoal: dailyGoalMl,
        glassSize: glassSizeMl,
        schedule,
        csrf_token: CSRF_TOKEN
    };
    if (IS_EDIT && INITIAL_PLAN.id) {
        payload.planId = INITIAL_PLAN.id;
    }

    try {
        const response = await App.api.post(endpoint, payload);
        if (response.success) {
            window.location.href = `?page=water-plan-details${response.data?.id ? '&planId=' + encodeURIComponent(response.data.id) : ''}`;
        } else {
            alert(response.error || 'Failed to save plan.');
        }
    } catch (error) {
        console.error('Failed to save mobile water plan:', error);
        alert('Failed to save plan.');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
    const schedule = Array.isArray(INITIAL_PLAN.schedule) ? INITIAL_PLAN.schedule : [];
    if (schedule.length === 0) {
        renderScheduleRow('', '0.25');
    } else {
        const glassSizeMl = Number(INITIAL_PLAN.glassSize) || 250;
        schedule.forEach(item => {
            renderScheduleRow(item.time || '', normalizeRowAmountL(item.amount, glassSizeMl));
        });
    }
    document.getElementById('mobile-water-plan-form').addEventListener('submit', submitPlan);
});
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
</div>
</body>
</html>
