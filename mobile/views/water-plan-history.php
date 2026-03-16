<?php
/**
 * Mobile Water Plan History
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
        <p><a href="?page=water-plan">Return to water plan</a></p>
    </body></html>');
}

$waterPlans = $db->load('water_plans') ?? [];
$waterPlanHistory = $db->load('water_plan_history') ?? [];
$waterTracker = $db->load('water_tracker') ?? [];
$now = new DateTime();

// Keep only same-day plans active.
$didDeactivate = false;
foreach ($waterPlans as $index => $plan) {
    $isActive = !empty($plan['isActive']);
    if (!$isActive) {
        continue;
    }
    $planDate = !empty($plan['createdAt']) ? new DateTime($plan['createdAt']) : $now;
    if ($planDate->format('Y-m-d') !== $now->format('Y-m-d')) {
        $waterPlans[$index]['isActive'] = false;
        $waterPlans[$index]['autoClosedAt'] = $now->format('c');
        $didDeactivate = true;
    }
}
if ($didDeactivate) {
    $db->save('water_plans', $waterPlans);
}

$allPlans = [];
foreach ($waterPlans as $plan) {
    $plan['source'] = 'manual';
    $allPlans[] = $plan;
}
foreach ($waterPlanHistory as $plan) {
    $plan['source'] = 'history';
    $allPlans[] = $plan;
}

usort($allPlans, function ($a, $b) {
    return strtotime($b['createdAt'] ?? 'now') <=> strtotime($a['createdAt'] ?? 'now');
});

$totalPlans = count($allPlans);
$activePlans = count(array_filter($allPlans, fn($p) => !empty($p['isActive'])));
$totalReminders = array_sum(array_map(fn($p) => count($p['schedule'] ?? []), $allPlans));

$dateRange = 'No plans';
if (!empty($allPlans)) {
    $dates = array_map(fn($p) => strtotime($p['createdAt'] ?? 'now'), $allPlans);
    $minDate = min($dates);
    $maxDate = max($dates);
    $dateRange = date('M j', $minDate) . ' - ' . date('M j', $maxDate);
}

$today = date('Y-m-d');
$todayEntry = null;
foreach ($waterTracker as $entry) {
    if (($entry['date'] ?? '') === $today) {
        $todayEntry = $entry;
        break;
    }
}

$todayDone = (float)($todayEntry['glasses'] ?? 0);
$todayPlanned = (int)($todayEntry['glassesPlanned'] ?? 0);
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Water Plan History - <?= htmlspecialchars($siteName) ?></title>
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
$title = 'Water Plan History';
$leftAction = 'back';
$rightAction = 'add';
$rightTarget = '?page=new-water-plan';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-4 pt-4 pb-32 space-y-4">
    <section class="grid grid-cols-2 gap-2">
        <div class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Total Plans</p>
            <p class="text-2xl font-bold mt-1"><?= (int)$totalPlans ?></p>
        </div>
        <div class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Active Plans</p>
            <p class="text-2xl font-bold mt-1"><?= (int)$activePlans ?></p>
        </div>
        <div class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Today</p>
            <p class="text-sm font-semibold mt-2"><?= rtrim(rtrim(number_format($todayDone, 2, '.', ''), '0'), '.') ?> / <?= (int)$todayPlanned ?></p>
        </div>
        <div class="border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-3">
            <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-zinc-500">Date Range</p>
            <p class="text-sm font-semibold mt-2 leading-tight"><?= htmlspecialchars($dateRange) ?></p>
        </div>
    </section>

    <?php if (empty($allPlans)): ?>
        <section class="border-2 border-black dark:border-white bg-white dark:bg-zinc-900 p-6 text-center">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-zinc-500 mb-2">No Plans Yet</p>
            <h2 class="text-xl font-bold tracking-tight">Create your first water plan</h2>
            <a href="?page=new-water-plan" class="mt-6 inline-block px-5 py-3 bg-black dark:bg-white text-white dark:text-black text-[11px] font-semibold uppercase tracking-[0.16em]">Create Plan</a>
        </section>
    <?php else: ?>
        <section class="space-y-3">
            <?php foreach ($allPlans as $plan): ?>
                <?php
                $schedule = $plan['schedule'] ?? [];
                $totalSlots = count($schedule);
                $completed = count(array_filter($schedule, fn($s) => !empty($s['completed'])));
                $progress = $totalSlots > 0 ? (int)round(($completed / $totalSlots) * 100) : 0;
                $goalLiters = number_format(((float)($plan['dailyGoal'] ?? 0)) / 1000, 2);
                $isActive = !empty($plan['isActive']);
                $isManual = ($plan['source'] ?? 'manual') === 'manual';
                $source = $isManual ? 'Manual' : 'History';
                $badgeClass = $isActive
                    ? 'bg-black dark:bg-white text-white dark:text-black'
                    : 'border border-zinc-300 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400';
                ?>
                <div class="border <?= $isActive ? 'border-black dark:border-white' : 'border-zinc-200 dark:border-zinc-700' ?> p-4 bg-white dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-base font-semibold truncate"><?= htmlspecialchars($plan['name'] ?? 'Water Plan') ?></p>
                            <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-zinc-500 mt-1">
                                <?= date('M j, Y g:i A', strtotime($plan['createdAt'] ?? 'now')) ?>
                            </p>
                        </div>
                        <span class="px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] <?= $badgeClass ?>">
                            <?= $isActive ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-4 border-y border-zinc-100 dark:border-zinc-800 py-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-zinc-500">Goal</p>
                            <p class="text-sm font-semibold mt-1"><?= $goalLiters ?>L</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-zinc-500">Reminders</p>
                            <p class="text-sm font-semibold mt-1"><?= (int)$totalSlots ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-zinc-500">Type</p>
                            <p class="text-sm font-semibold mt-1"><?= $source ?></p>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-20 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                <div class="h-full bg-black dark:bg-white" style="width: <?= max(0, min(100, $progress)) ?>%"></div>
                            </div>
                            <span class="text-xs font-semibold"><?= $progress ?>%</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="?page=water-plan-details&planId=<?= urlencode($plan['id'] ?? '') ?>" class="px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] border border-black dark:border-white">View</a>
                            <?php if (!$isActive && $isManual): ?>
                                <button onclick="activatePlan('<?= htmlspecialchars($plan['id'] ?? '', ENT_QUOTES) ?>')" class="px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] bg-black dark:bg-white text-white dark:text-black">Activate</button>
                            <?php endif; ?>
                            <button onclick="deleteWaterPlan('<?= htmlspecialchars($plan['id'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($plan['source'] ?? 'manual', ENT_QUOTES) ?>')" class="px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] border border-red-400 text-red-600">Delete</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <section class="text-center pb-2">
        <p class="text-xs font-medium uppercase tracking-[0.14em] text-zinc-500">
            Showing <?= (int)$totalPlans ?> plan(s) - <?= (int)$totalReminders ?> reminders total
        </p>
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
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});

async function activatePlan(planId) {
    Mobile.ui.confirmAction('Activate this plan for today?', async () => {
        try {
            const response = await App.api.post('api/habits.php?action=activate_plan', {
                planId,
                csrf_token: CSRF_TOKEN
            });
            if (response.success) {
                Mobile.ui.showToast('Plan activated', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                Mobile.ui.showToast(response.error || 'Failed to activate', 'error');
            }
        } catch (error) {
            Mobile.ui.showToast('Failed to activate', 'error');
        }
    });
}

async function deleteWaterPlan(planId, source) {
    const collection = source === 'manual' ? 'water_plans' : 'water_plan_history';
    Mobile.ui.confirmAction('Delete this water plan?', async () => {
        try {
            const response = await App.api.post('api/habits.php?action=delete_plan', {
                planId,
                collection,
                csrf_token: CSRF_TOKEN
            });
            if (response.success) {
                Mobile.ui.showToast('Plan deleted', 'success');
                setTimeout(() => window.location.reload(), 600);
            } else {
                Mobile.ui.showToast(response.error || 'Failed to delete', 'error');
            }
        } catch (error) {
            Mobile.ui.showToast('Failed to delete', 'error');
        }
    });
}
</script>
</div>
</body>
</html>


