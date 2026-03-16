<?php
/**
 * Mobile Habits Page - LazyMan Tools
 *
 * Habit Tracker V2 design replicated from Google Stitch.
 * Calendar view with completion dots, active habits list, momentum stats.
 *
 * Route: ?page=habits
 */

// Get master password from session
$masterPassword = getMasterPassword();

// Check if master password is available
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

// Load data
try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=dashboard">Return to dashboard</a></p>
    </body></html>');
}

// Load habit data
$habits = filterActiveHabits($db->load('habits') ?? []);
$completions = $db->load('habit_completions') ?? [];

// Process habits: attach today's completion status
$today = date('Y-m-d');
foreach ($habits as $key => $habit) {
    $habitCompletions = array_filter($completions, fn($c) =>
        isset($c['habitId']) && $c['habitId'] === $habit['id'] && $c['date'] === $today
    );

    $habits[$key]['todayCompleted'] = false;
    foreach ($habitCompletions as $comp) {
        if (($comp['status'] ?? '') === 'complete') {
            $habits[$key]['todayCompleted'] = true;
            break;
        }
    }
}

// Calendar generation
$currentYear = date('Y');
$currentMonth = date('m');
$currentDay = date('j');

// Get month name
$monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
               'July', 'August', 'September', 'October', 'November', 'December'];
$currentMonthName = $monthNames[intval($currentMonth) - 1];

// Build calendar days array
$calendarDays = [];

// First day of month
$firstDayOfMonth = new DateTime("$currentYear-$currentMonth-01");
$startingDayOfWeek = intval($firstDayOfMonth->format('w')); // 0 = Sunday, 6 = Saturday

// Previous month days to fill first week
$prevMonthDays = [];
for ($i = $startingDayOfWeek - 1; $i >= 0; $i--) {
    $day = clone $firstDayOfMonth;
    $day->sub(new DateInterval('P' . ($i + 1) . 'D'));
    $prevMonthDays[] = [
        'number' => $day->format('j'),
        'showNumber' => true,
        'classes' => 'opacity-20 text-[10px]',
        'dotColor' => null
    ];
}

// Current month days
$daysInMonth = intval($firstDayOfMonth->format('t'));
for ($day = 1; $day <= $daysInMonth; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $currentYear, intval($currentMonth), $day);
    $isToday = ($day === intval($currentDay));

    // Get completion level for this day
    $dayCompletions = array_filter($completions, fn($c) => isset($c['date']) && $c['date'] === $dateStr);
    $totalHabits = count($habits);
    $completedCount = count(array_filter($dayCompletions, fn($c) => ($c['status'] ?? '') === 'complete'));

    // Determine dot color
    $dotColor = null;
    if ($dayCompletions && $totalHabits > 0) {
        if ($completedCount === $totalHabits) {
            $dotColor = 'bg-black'; // All complete
        } else if ($completedCount > 0) {
            $dotColor = 'bg-gray-400'; // Some complete
        } else {
            $dotColor = 'border border-gray-300'; // None complete
        }
    }

    $classes = ['flex-col'];
    if ($isToday) {
        $classes[] = 'font-bold underline decoration-2 underline-offset-4';
    }

    $calendarDays[] = [
        'number' => $day,
        'showNumber' => true,
        'classes' => implode(' ', $classes),
        'dotColor' => $dotColor
    ];
}

// Future days (fill remaining grid)
$totalCells = 42; // 6 rows of 7 columns
$filledCells = count($prevMonthDays) + $daysInMonth;
$remainingCells = $totalCells - $filledCells;

for ($i = 1; $i <= $remainingCells; $i++) {
    $calendarDays[] = [
        'number' => '...',
        'showNumber' => false,
        'classes' => 'flex-col opacity-50',
        'dotColor' => null
    ];
}

// Combine all days
$allCalendarDays = array_merge($prevMonthDays, $calendarDays);

// Calculate momentum stats
$currentStreak = 0;
if (!empty($completions)) {
    usort($completions, fn($a, $b) => strtotime($b['date'] ?? 0) - strtotime($a['date'] ?? 0));

    $checkDate = new DateTime('today');
    foreach ($completions as $completion) {
        if (($completion['status'] ?? '') !== 'complete') continue;

        $compDate = new DateTime($completion['date'] ?? '');
        if (!$compDate) continue;

        $diff = $checkDate->diff($compDate);
        if ($diff->days === 0 && $diff->invert === 0) {
            $currentStreak++;
            $checkDate->sub(new DateInterval('P1D'));
        } elseif ($diff->days === 1 && $diff->invert === 1) {
            $currentStreak++;
            $checkDate->sub(new DateInterval('P1D'));
        } else {
            break;
        }
    }
}

// Best streak
$bestStreak = 0;
if (!empty($completions)) {
    $sorted = $completions;
    usort($sorted, fn($a, $b) => strtotime($a['date'] ?? 0) - strtotime($b['date'] ?? 0));

    $currentStreakTemp = 0;
    $prevDate = null;

    foreach ($sorted as $completion) {
        if (($completion['status'] ?? '') !== 'complete') continue;

        $currDate = new DateTime($completion['date'] ?? '');
        if (!$currDate) continue;

        if ($prevDate && $currDate->diff($prevDate)->days === 1) {
            $currentStreakTemp++;
        } else {
            $currentStreakTemp = 1;
        }

        if ($currentStreakTemp > $bestStreak) {
            $bestStreak = $currentStreakTemp;
        }

        $prevDate = $currDate;
    }
}

// Month completion percentage
$monthCompletion = 0;
$totalHabitsCount = count($habits);
if ($totalHabitsCount > 0) {
    $monthStart = new DateTime("$currentYear-$currentMonth-01");
    $monthEnd = clone $monthStart;
    $monthEnd->modify('last day of this month 23:59:59');

    $daysInMonth = [];
    for ($d = clone $monthStart; $d <= $monthEnd; $d->modify('+1 day')) {
        $daysInMonth[] = $d->format('Y-m-d');
    }

    $completedDays = 0;
    foreach ($daysInMonth as $dayStr) {
        $dayCompletions = array_filter($completions, fn($c) =>
            isset($c['date']) && $c['date'] === $dayStr && ($c['status'] ?? '') === 'complete'
        );
        if (count($dayCompletions) === $totalHabitsCount) {
            $completedDays++;
        }
    }

    $monthCompletion = $totalHabitsCount > 0 ? round(($completedDays / count($daysInMonth)) * 100) : 0;
}

$totalCompletions = count($completions);

// Get site name
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Habits - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#000000",
                    "background-light": "#ffffff",
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
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
    .calendar-cell {
        aspect-ratio: 1 / 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 0.5px solid #e5e5e5;
        font-size: 0.75rem;
        position: relative;
    }
    .habit-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-top: 2px;
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
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden">

<?php
$title = $currentMonthName . ' ' . $currentYear;
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = '?page=habit-form';
$rightIsLink = true;
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto no-scrollbar pt-4 pb-32">
    <!-- Calendar Section -->
    <section class="px-6 mb-8">
        <div class="calendar-grid border-t border-l border-black">
            <!-- Day Headers -->
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">S</div>
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">M</div>
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">T</div>
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">W</div>
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">T</div>
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">F</div>
            <div class="calendar-cell border-r border-b border-black font-bold bg-black text-white">S</div>

            <!-- Calendar Days -->
            <?php foreach ($allCalendarDays as $day): ?>
                <div class="calendar-cell border-r border-b border-black <?= $day['classes'] ?>">
                    <?php if ($day['showNumber']): ?>
                        <span><?= $day['number'] ?></span>
                    <?php endif; ?>
                    <?php if ($day['dotColor']): ?>
                        <div class="habit-dot <?= $day['dotColor'] ?>"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Legend -->
        <div class="mt-4 flex gap-6 items-center justify-center">
            <div class="flex items-center gap-2">
                <div class="size-2 rounded-full bg-black"></div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400">All</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="size-2 rounded-full bg-gray-400"></div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400">Some</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="size-2 rounded-full border border-gray-300"></div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-gray-400">None</span>
            </div>
        </div>
    </section>

    <?php if (!empty($habits)): ?>
    <!-- Active Habits Section -->
    <section class="px-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-black">Active Habits</h3>
            <span class="text-[10px] text-gray-400 uppercase tracking-widest">Today</span>
        </div>
        <div class="space-y-3">
            <?php
            // Sort habits: completed first, then alphabetical
            usort($habits, fn($a, $b) =>
                ($b['todayCompleted'] <=> $a['todayCompleted']) ?: strcmp($a['name'] ?? '', $b['name'] ?? '')
            );

            foreach ($habits as $habit):
                $isCompleted = $habit['todayCompleted'] ?? false;
                $habitId = htmlspecialchars($habit['id']);
                $habitName = htmlspecialchars($habit['name']);
                $category = htmlspecialchars(ucfirst($habit['category'] ?? 'General'));
                $targetDuration = $habit['targetDuration'] ?? 0;

                // Generate goal text
                $goalText = '';
                if ($targetDuration > 0) {
                    $hours = floor($targetDuration / 60);
                    $mins = $targetDuration % 60;
                    if ($hours > 0) {
                        $goalText = $hours . 'h ' . ($mins > 0 ? $mins . 'm' : '') . ' Daily';
                    } else {
                        $goalText = $mins . 'm Daily';
                    }
                }

                $borderClass = $isCompleted ? 'border border-black' : 'border border-gray-200';
                $hoverClass = $isCompleted ? 'hover:bg-black hover:text-white' : 'hover:border-black';
            ?>
            <div class="group flex items-center justify-between p-4 <?= $borderClass ?> <?= $hoverClass ?> transition-all cursor-pointer"
                 onclick="Mobile.habits.openDetail('<?= $habitId ?>')"
                 data-habit-id="<?= $habitId ?>"
                 data-completed="<?= $isCompleted ? '1' : '0' ?>">
                <div class="flex flex-col">
                    <span class="text-xs font-bold uppercase tracking-wider"><?= $habitName ?></span>
                    <span class="text-[9px] <?= $isCompleted ? 'opacity-60' : 'text-gray-400' ?> uppercase mt-0.5">
                        <?= $category ?><?php if ($goalText): ?> &bull; <?= $goalText ?><?php endif; ?>
                    </span>
                </div>
                <button
                    type="button"
                    class="p-1 -m-1 touch-target"
                    onclick="event.stopPropagation(); Mobile.habits.toggle('<?= $habitId ?>')"
                    aria-label="<?= $isCompleted ? 'Mark habit incomplete' : 'Mark habit complete' ?>"
                >
                    <?php if ($isCompleted): ?>
                        <!-- Heroicon: Check Circle (completed) -->
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    <?php else: ?>
                        <!-- Heroicon: Circle (uncompleted) -->
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    <?php endif; ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <!-- Empty State -->
    <section class="px-6 mb-8">
        <div class="text-center py-12">
            <p class="text-gray-500 text-sm mb-4">No habits yet.</p>
            <a href="?page=habit-form" class="inline-block bg-black text-white px-6 py-3 rounded-xl font-bold text-sm touch-target">
                Create Your First Habit
            </a>
        </div>
    </section>
    <?php endif; ?>

    <!-- Momentum Card -->
    <section class="px-6">
        <div class="w-full bg-black p-8 flex flex-col items-center justify-center text-white">
            <span class="text-[10px] font-light uppercase tracking-[0.4em] opacity-60 mb-2">Momentum</span>
            <div class="text-2xl font-black tracking-tighter uppercase mb-1">Current Streak</div>
            <div class="text-5xl font-black tracking-tighter"><?= $currentStreak ?> DAYS</div>
            <div class="mt-4 pt-4 border-t border-white/20 w-full flex justify-between items-center">
                <div class="text-center">
                    <div class="text-[10px] font-bold uppercase tracking-widest opacity-40">Best</div>
                    <div class="text-sm font-bold"><?= $bestStreak ?>d</div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] font-bold uppercase tracking-widest opacity-40">Month</div>
                    <div class="text-sm font-bold"><?= $monthCompletion ?>%</div>
                </div>
                <div class="text-center">
                    <div class="text-[10px] font-bold uppercase tracking-widest opacity-40">Total</div>
                    <div class="text-sm font-bold"><?= $totalCompletions ?></div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Universal Bottom Navigation -->
<?php
$activePage = 'habits';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>

<!-- Universal Off-Canvas Menu -->
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<!-- iPhone Home Indicator -->
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<!-- App Config -->
<script>
    // Dynamic base path detection for mobile views
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

<!-- Mobile JS -->
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

<!-- Habits-Specific JS -->
<script>
Mobile.habits = (function() {
    'use strict';
    const _pending = new Set();

    function toast(message, type) {
        if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
            Mobile.ui.showToast(message, type || 'info');
            return;
        }
        alert(message);
    }

    function getLocalDateYmd() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function openDetail(habitId) {
        window.location.href = '?page=view-habit&id=' + encodeURIComponent(habitId);
    }

    // Toggle habit completion
    async function toggle(habitId) {
        if (!habitId || _pending.has(habitId)) {
            return;
        }

        try {
            // Show loading state
            const habitEl = document.querySelector(`[data-habit-id="${habitId}"]`);
            if (habitEl) {
                habitEl.style.opacity = '0.5';
            }
            _pending.add(habitId);

            // Get today's date
            const today = getLocalDateYmd();
            const currentlyCompleted = habitEl?.dataset?.completed === '1';
            const nextStatus = currentlyCompleted ? 'incomplete' : 'complete';

            // Call API to toggle completion
            const response = await App.api.post('api/habits.php?action=complete', {
                habitId: habitId,
                date: today,
                status: nextStatus,
                csrf_token: CSRF_TOKEN
            });

            if (response.success) {
                // Reload page to show updated state
                if (window.Mobile && Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
                    Mobile.ui.queueToast(nextStatus === 'complete' ? 'Habit marked complete.' : 'Habit marked incomplete.', 'success');
                }
                window.location.reload();
            } else {
                throw new Error(response.error || 'Failed to toggle habit');
            }
        } catch (error) {
            console.error('Error toggling habit:', error);
            toast('Failed to update habit', 'error');
            // Reset opacity
            const habitEl = document.querySelector(`[data-habit-id="${habitId}"]`);
            if (habitEl) {
                habitEl.style.opacity = '1';
            }
        } finally {
            _pending.delete(habitId);
        }
    }

    // Public API
    return {
        toggle,
        openDetail
    };

})();
</script>

<!-- Initialize Mobile -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
});
</script>

</body>
</html>


