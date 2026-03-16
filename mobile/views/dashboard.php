<?php
/**
 * Mobile Dashboard Page - LazyMan Tools
 *
 * Modern mobile dashboard with stats, water tracker, habits, pomodoro timer, and recent tasks.
 * Uses Heroicons inline SVG (no Material Symbols).
 * Integrates with existing LazyMan backend data.
 *
 * Features:
 * - 4-stat cards grid (tasks, projects, habits, revenue)
 * - Water tracker with glass cup visualization
 * - Habits progress with streak counter
 * - Pomodoro timer with resume/reset
 * - Recent tasks table
 * - Dark mode support
 */

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

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
        <p><a href="?page=login">Return to login</a></p>
    </body></html>');
}

// Get all data collections
$projects = $db->load('projects');
$habits = $db->load('habits');
$habitCompletions = $db->load('habit_completions');

// Get current date info
$today = date('Y-m-d');
$todayFormatted = date('l, F j, Y');

// Calculate task stats
$allTasks = [];
foreach ($projects as $project) {
    if (isset($project['tasks']) && is_array($project['tasks'])) {
        foreach ($project['tasks'] as $task) {
            $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
            $allTasks[] = $task;
        }
    }
}
$pendingTasks = count(array_filter($allTasks, fn($t) => !isTaskRecordDone($t)));
$completedTasks = count(array_filter($allTasks, fn($t) => isTaskRecordDone($t)));

// Calculate project stats
$activeProjects = count(array_filter($projects, fn($p) => isProjectActive($p['status'] ?? null)));
$totalProjects = count($projects);

// Calculate habits stats for today
$habits = filterActiveHabits($habits);
$habitsToday = 0;
$totalHabits = count($habits);
foreach ($habits as $habit) {
    $isCompletedToday = false;
    foreach ($habitCompletions as $completion) {
        if ($completion['habitId'] === $habit['id'] && $completion['date'] === $today) {
            $isCompletedToday = true;
            break;
        }
    }
    if ($isCompletedToday) {
        $habitsToday++;
    }
}
$habitsProgress = $totalHabits > 0 ? ($habitsToday / $totalHabits) * 100 : 0;

// Calculate tasks this week
$oneWeekAgo = date('Y-m-d', strtotime('-1 week'));
$tasksCompletedThisWeek = count(array_filter($allTasks, function($t) use ($oneWeekAgo) {
    return isset($t['completedAt']) && $t['completedAt'] >= $oneWeekAgo;
}));
$tasksCreatedThisWeek = count(array_filter($allTasks, function($t) use ($oneWeekAgo) {
    return ($t['createdAt'] ?? '') >= $oneWeekAgo;
}));

// Get user name
$userName = Auth::user()['name'] ?? 'User';
$siteName = getSiteName() ?? 'LazyMan';

// Water tracker data (load from database)
$waterTracker = $db->load('water_tracker');
$today = date('Y-m-d');
$waterToday = null;
foreach ($waterTracker as $entry) {
    if (isset($entry['date']) && $entry['date'] === $today) {
        $waterToday = $entry;
        break;
    }
}
if (!$waterToday) {
    $waterToday = [
        'glasses' => 0,
        'goal' => 8
    ];
}
$waterIntakeMl = (int)($waterToday['intakeMl'] ?? (($waterToday['glasses'] ?? 0) * 250));
$waterGoalMl = (int)($waterToday['goalMl'] ?? (($waterToday['goal'] ?? 8) * 250));
$waterProgress = $waterGoalMl > 0 ? (($waterIntakeMl / $waterGoalMl) * 100) : 0;
$waterMissedCount = count($waterToday['missedReminders'] ?? []);

// Get recent tasks (first 5 incomplete, sorted by priority)
$recentTasks = array_filter($allTasks, fn($t) => !isTaskRecordDone($t));
usort($recentTasks, function($a, $b) {
    $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    $aPriority = $priorityOrder[$a['priority'] ?? 'medium'] ?? 2;
    $bPriority = $priorityOrder[$b['priority'] ?? 'medium'] ?? 2;
    return $aPriority - $bPriority;
});
$recentTasks = array_slice($recentTasks, 0, 5);
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Dashboard - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
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
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e5e7eb;
        border-radius: 10px;
    }
    .glass-cup {
        width: 80px;
        height: 112px;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .safe-bottom {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
</style>
<script src="<?= MOBILE_JS_URL ?>/mobile.js?v=1.0.1"></script>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Dashboard';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'Mobile.ui.openTaskModal()';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto max-w-md w-full mx-auto px-4 pt-6 pb-40 space-y-6 text-zinc-900 dark:text-zinc-100">
    <!-- Welcome Section -->
    <section>
        <h2 class="text-2xl font-bold">Welcome back, <?= htmlspecialchars(explode(' ', $userName)[0]) ?>!</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mt-1 uppercase tracking-wider"><?= $todayFormatted ?></p>
    </section>

    <!-- 4-Stat Cards Grid -->
    <div class="grid grid-cols-2 gap-3">
        <!-- Pending Tasks -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-4 rounded-2xl">
            <div class="flex justify-between items-start mb-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Pending Tasks</span>
                <!-- Heroicon: Check Circle -->
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div class="text-3xl font-bold"><?= $pendingTasks ?></div>
            <div class="text-xs text-gray-400 mt-1"><?= $completedTasks ?> completed</div>
        </div>

        <!-- Active Projects -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-4 rounded-2xl">
            <div class="flex justify-between items-start mb-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Active Projects</span>
                <!-- Heroicon: Folder -->
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0V6A2.25 2.25 0 016 3.75h3.879a1.5 1.5 0 011.06.44l2.122 2.12a1.5 1.5 0 001.06.44H18A2.25 2.25 0 0120.25 9v.776"/>
                </svg>
            </div>
            <div class="text-3xl font-bold"><?= $activeProjects ?></div>
            <div class="text-xs text-gray-400 mt-1"><?= $totalProjects ?> total</div>
        </div>

        <!-- Habits Today -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-4 rounded-2xl">
            <div class="flex justify-between items-start mb-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Habits Today</span>
                <!-- Heroicon: Fire (approximated with sun) -->
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.252 8.252 0 016.038 7.047 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z"/>
                </svg>
            </div>
            <div class="text-3xl font-bold"><?= $habitsToday ?>/<?= $totalHabits ?></div>
            <div class="w-full bg-gray-100 dark:bg-zinc-800 h-1 rounded-full mt-3 overflow-hidden">
                <div class="bg-black dark:bg-white h-full transition-all duration-500" style="width: <?= $habitsProgress ?>%"></div>
            </div>
        </div>

        <!-- Tasks This Week -->
        <div class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-4 rounded-2xl">
            <div class="flex justify-between items-start mb-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Tasks This Week</span>
                <!-- Heroicon: Calendar -->
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V7.5m-18 0V7.5"/>
                </svg>
            </div>
            <div class="text-3xl font-bold"><?= $tasksCreatedThisWeek + $tasksCompletedThisWeek ?></div>
            <div class="text-xs text-gray-400 mt-1"><?= $tasksCompletedThisWeek ?> completed this week</div>
        </div>
    </div>

    <!-- Water Tracker Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 rounded-3xl">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center gap-2">
                <h3 class="font-bold uppercase text-sm tracking-widest">Stay Hydrated</h3>
                <!-- Heroicon: Beaker/Droplet approximation -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25z"/>
                </svg>
            </div>
            <span id="mobile-water-total-label" class="text-xs font-bold text-gray-400 uppercase tracking-widest"><?= number_format($waterIntakeMl / 1000, 2) ?>/<?= number_format($waterGoalMl / 1000, 2) ?> L</span>
        </div>
        <div class="flex items-center gap-6 mb-8">
            <!-- SVG Water Cup -->
            <svg class="glass-cup" viewBox="0 0 200 280" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <!-- Cup clip path for water fill -->
                    <clipPath id="cup-clip-mobile">
                        <path d="M30,60 L50,240 Q50,260 70,260 L130,260 Q150,260 150,240 L170,60 Z"/>
                    </clipPath>
                    <!-- Water gradient -->
                    <linearGradient id="water-gradient-mobile" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" style="stop-color:#3B82F6"/>
                        <stop offset="100%" style="stop-color:#1D4ED8"/>
                    </linearGradient>
                    <!-- Glass gradient -->
                    <linearGradient id="glass-gradient-mobile" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" style="stop-color:#E0F2FE"/>
                        <stop offset="50%" style="stop-color:#BAE6FD"/>
                        <stop offset="100%" style="stop-color:#E0F2FE"/>
                    </linearGradient>
                </defs>

                <!-- Cup outline -->
                <path d="M30,60 L50,240 Q50,260 70,260 L130,260 Q150,260 150,240 L170,60"
                      fill="url(#glass-gradient-mobile)" stroke="#0EA5E9" stroke-width="3"/>

                <!-- Water fill with animation -->
                <g clip-path="url(#cup-clip-mobile)">
                    <!-- Water background -->
                    <rect id="water-fill-mobile" x="0" y="260" width="200" height="200"
                          fill="url(#water-gradient-mobile)" class="transition-all duration-500 ease-out"/>

                    <!-- Wave animation layer 1 -->
                    <g id="wave1-mobile" class="transition-transform duration-500">
                        <path d="M0,200 Q50,180 100,200 T200,200 T300,200 V280 H0 Z"
                              fill="#60A5FA" opacity="0.6">
                            <animate attributeName="d"
                                     dur="3s"
                                     repeatCount="indefinite"
                                     values="M0,200 Q50,180 100,200 T200,200 T300,200 V280 H0 Z;
                                             M0,200 Q50,220 100,200 T200,200 T300,200 V280 H0 Z;
                                             M0,200 Q50,180 100,200 T200,200 T300,200 V280 H0 Z"/>
                        </path>
                    </g>

                    <!-- Wave animation layer 2 -->
                    <g id="wave2-mobile" class="transition-transform duration-500">
                        <path d="M0,210 Q50,230 100,210 T200,210 T300,210 V280 H0 Z"
                              fill="#93C5FD" opacity="0.5">
                            <animate attributeName="d"
                                     dur="2.5s"
                                     repeatCount="indefinite"
                                     values="M0,210 Q50,190 100,210 T200,210 T300,210 V280 H0 Z;
                                             M0,210 Q50,230 100,210 T200,210 T300,210 V280 H0 Z;
                                             M0,210 Q50,190 100,210 T200,210 T300,210 V280 H0 Z"/>
                        </path>
                    </g>

                    <!-- Bubbles -->
                    <circle cx="80" cy="220" r="4" fill="white" opacity="0.6">
                        <animate attributeName="cy" values="220;180;220" dur="2s" repeatCount="indefinite"/>
                        <animate attributeName="opacity" values="0.6;0.2;0.6" dur="2s" repeatCount="indefinite"/>
                    </circle>
                    <circle cx="120" cy="240" r="3" fill="white" opacity="0.5">
                        <animate attributeName="cy" values="240;190;240" dur="2.5s" repeatCount="indefinite"/>
                        <animate attributeName="opacity" values="0.5;0.1;0.5" dur="2.5s" repeatCount="indefinite"/>
                    </circle>
                    <circle cx="95" cy="250" r="5" fill="white" opacity="0.4">
                        <animate attributeName="cy" values="250;200;250" dur="3s" repeatCount="indefinite"/>
                        <animate attributeName="opacity" values="0.4;0.1;0.4" dur="3s" repeatCount="indefinite"/>
                    </circle>
                </g>

                <!-- Cup shine -->
                <path d="M55,80 Q60,120 55,160" stroke="white" stroke-width="4" fill="none" opacity="0.4" stroke-linecap="round"/>

                <!-- Cup handle -->
                <path d="M170,80 Q195,80 195,120 Q195,160 170,160"
                      fill="none" stroke="#0EA5E9" stroke-width="8" stroke-linecap="round"/>
                <path d="M170,85 Q190,85 190,120 Q190,155 170,155"
                      fill="none" stroke="#E0F2FE" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <div class="flex-1">
                <div class="w-full bg-gray-100 dark:bg-zinc-800 h-2 rounded-full overflow-hidden mb-2">
                    <div id="mobile-water-progress-bar" class="bg-black dark:bg-white h-full transition-all duration-500" style="width: <?= $waterProgress ?>%"></div>
                </div>
                <span id="mobile-water-percent-label" class="text-[10px] font-bold uppercase tracking-widest text-gray-400"><?= round($waterProgress) ?>% complete</span>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-2">
            <button onclick="Mobile.water.add(250)" class="bg-black dark:bg-white text-white dark:text-black py-3 rounded-xl font-bold text-xs uppercase tracking-tighter active:scale-95 transition-transform touch-target">+0.25 L</button>
            <button onclick="Mobile.water.add(500)" class="bg-black dark:bg-white text-white dark:text-black py-3 rounded-xl font-bold text-xs uppercase tracking-tighter active:scale-95 transition-transform touch-target">+0.50 L</button>
            <button onclick="Mobile.water.add(1000)" class="border border-black dark:border-white py-3 rounded-xl font-bold text-xs uppercase tracking-tighter active:scale-95 transition-transform touch-target">+1.00 L</button>
        </div>
        <a href="?page=water-plan" class="mt-3 block text-center border border-black dark:border-white py-2 rounded-xl font-bold text-[10px] uppercase tracking-widest touch-target">Open Water Plan</a>
        <?php if ($waterMissedCount > 0): ?>
            <div class="mt-3 border border-red-200 bg-red-50 rounded-xl p-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-red-700">Behind Schedule</p>
                <p class="text-xs text-red-600 mt-1">Missed <?= (int)$waterMissedCount ?> reminder(s) today</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Daily Inspiration -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 rounded-3xl">
        <div class="flex items-center gap-2 mb-4">
            <h3 class="font-bold uppercase text-[10px] tracking-[0.2em] text-gray-400">Daily Hydration Inspiration</h3>
        </div>
        <blockquote class="mb-4">
            <p class="text-lg font-medium italic">"Water is the driving force of all nature."</p>
            <footer class="text-xs text-gray-400 mt-1">— Leonardo da Vinci</footer>
        </blockquote>
        <div class="flex flex-wrap gap-2 mb-6">
            <span class="bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-tight">Keep a water bottle at your desk</span>
            <span class="bg-gray-100 dark:bg-zinc-800 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-tight">Your body is 60% water</span>
        </div>
        <button onclick="Mobile.water.newQuote()" class="w-full border border-black dark:border-white py-2 rounded-xl font-bold text-[10px] uppercase tracking-widest active:scale-95 transition-transform touch-target">New Quote</button>
    </section>

    <!-- Pomodoro Timer Section -->
    <section class="bg-black dark:bg-white text-white dark:text-black p-8 rounded-3xl text-center shadow-xl">
        <span class="text-[10px] font-bold uppercase tracking-[0.3em] mb-2 block text-gray-400">Pomodoro Timer</span>
        <div class="text-7xl font-light mb-8 tabular-nums" id="pomodoro-display">25:00</div>
        <div class="grid grid-cols-2 gap-3">
            <button onclick="Mobile.pomodoro.start()" id="pomodoro-start-btn" class="bg-white dark:bg-black text-black dark:text-white py-4 rounded-2xl font-bold uppercase tracking-widest text-sm active:scale-95 transition-all touch-target">Start</button>
            <button onclick="Mobile.pomodoro.reset()" class="border border-white/30 dark:border-black/20 py-4 rounded-2xl font-bold uppercase tracking-widest text-sm active:scale-95 transition-all touch-target">Reset</button>
        </div>
    </section>

    <!-- Recent Tasks Section -->
    <section class="bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-5 rounded-3xl">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold uppercase text-sm tracking-widest">Recent Tasks</h3>
            <button onclick="Mobile.navigation.navigateTo('tasks')" class="text-[10px] font-bold uppercase tracking-widest text-gray-400 touch-target">View All</button>
        </div>
        <?php if (empty($recentTasks)): ?>
            <div class="text-center py-8">
                <!-- Heroicon: Check Circle (empty state) -->
                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-gray-400 text-sm">No pending tasks</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b border-gray-100 dark:border-zinc-800">
                        <tr>
                            <th class="py-3 text-[10px] font-bold uppercase tracking-widest text-gray-400">Task</th>
                            <th class="py-3 text-[10px] font-bold uppercase tracking-widest text-gray-400 text-right">Priority</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-zinc-800">
                        <?php foreach ($recentTasks as $task):
                            $priority = $task['priority'] ?? 'medium';
                            $priorityColors = [
                                'urgent' => 'bg-red-600 text-white',
                                'high' => 'bg-orange-500 text-white',
                                'medium' => 'bg-yellow-500 text-black',
                                'low' => 'bg-green-500 text-white',
                            ];
                            $priorityClass = $priorityColors[$priority] ?? 'bg-yellow-500 text-black';
                        ?>
                        <tr class="group">
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <!-- Heroicon: Circle (unchecked) -->
                                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm font-medium"><?= htmlspecialchars($task['title']) ?></span>
                                </div>
                            </td>
                            <td class="py-4 text-right">
                                <span class="px-2 py-1 rounded <?= $priorityClass ?> text-[10px] font-bold uppercase tracking-tighter">
                                    <?= htmlspecialchars(ucfirst($priority)) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Universal Bottom Navigation -->
<?php
$activePage = 'dashboard';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>

<!-- Universal Off-Canvas Menu -->
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<!-- Theme Toggle -->
<button onclick="if (window.Mobile && Mobile.theme) { Mobile.theme.toggle(); } else { document.documentElement.classList.toggle('dark'); }"
        data-theme-toggle
        class="fixed right-4 bottom-24 bg-white dark:bg-zinc-900 border border-gray-200 dark:border-zinc-800 p-3 rounded-full shadow-lg z-40 active:scale-90 transition-transform touch-target">
    <svg class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
    </svg>
    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
    </svg>
</button>

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

<!-- Mobile JS moved to head -->

<!-- Dashboard-Specific JS -->
<script>
// Water tracking module
Mobile.water = (function() {
    let intakeMl = <?= $waterIntakeMl ?>;
    let goalMl = <?= $waterGoalMl ?>;
    let glassSizeMl = 250;

    const quotes = [
        { text: "Water is the driving force of all nature.", author: "Leonardo da Vinci" },
        { text: "Drinking water is like washing out your insides.", author: "Unknown" },
        { text: "Water is life's matter and matrix.", author: "Albert Szent-Györgyi" },
        { text: "The cure for anything is salt water: sweat, tears, or the sea.", author: "Isak Dinesen" },
        { text: "Pure water is the world's first and foremost medicine.", author: "Slovakian Proverb" }
    ];

    function updateUI() {
        const progress = goalMl > 0 ? ((intakeMl / goalMl) * 100) : 0;
        // Update SVG water fill Y position (cup height: y=60 to y=260 = 200px total)
        // Empty at 0% = y=260, Full at 100% = y=60
        const waterFill = document.getElementById('water-fill-mobile');
        if (waterFill) {
            const yPos = 260 - (progress / 100 * 200);
            waterFill.setAttribute('y', yPos);
        }
        // Update progress bar width
        const progressBar = document.getElementById('mobile-water-progress-bar');
        if (progressBar) {
            progressBar.style.width = progress + '%';
        }
        // Update text labels
        const totalLabel = document.getElementById('mobile-water-total-label');
        if (totalLabel) {
            totalLabel.textContent = (intakeMl / 1000).toFixed(2) + '/' + (goalMl / 1000).toFixed(2) + ' L';
        }
        const percentLabel = document.getElementById('mobile-water-percent-label');
        if (percentLabel) {
            percentLabel.textContent = Math.round(progress) + '% complete';
        }
    }

    async function syncPlanContext() {
        try {
            const response = await App.api.get('api/habits.php?action=get_daily_tracking');
            if (response.success && response.data?.activePlan) {
                glassSizeMl = Number(response.data.activePlan.glassSize) || glassSizeMl;
                goalMl = Number(response.data.activePlan.dailyGoal) || goalMl;
                updateUI();
            }
        } catch (error) {
            console.error('Failed to sync mobile water context:', error);
        }
    }

    return {
        init() {
            updateUI();
            syncPlanContext();
        },
        async add(amountMl) {
            try {
                const response = await App.api.post('api/habits.php?action=add_water_glass', {
                    amountMl: amountMl,
                    count: Number((amountMl / glassSizeMl).toFixed(2)),
                    glassSizeMl: glassSizeMl,
                    goalMl: goalMl,
                    csrf_token: CSRF_TOKEN
                });
                if (response.success) {
                    intakeMl = Number(response.data.intakeMl ?? intakeMl);
                    goalMl = Number(response.data.goalMl ?? goalMl);
                    updateUI();
                }
            } catch (error) {
                console.error('Failed to add water intake:', error);
            }
        },
        newQuote() {
            const quote = quotes[Math.floor(Math.random() * quotes.length)];
            const blockquote = document.querySelector('blockquote');
            blockquote.innerHTML = `
                <p class="text-lg font-medium italic">"${quote.text}"</p>
                <footer class="text-xs text-gray-400 mt-1">— ${quote.author}</footer>
            `;
        }
    };
})();

// Pomodoro timer module (matches PC version - 25 min focus, 5 min break)
Mobile.pomodoro = (function() {
    let timeLeft = 25 * 60; // 25 minutes in seconds (standard Pomodoro)
    let timerInterval = null;
    let isRunning = false;
    let isBreak = false;

    function updateDisplay() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        const display = document.getElementById('pomodoro-display');
        if (display) {
            display.textContent =
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
    }

    return {
        start() {
            const btn = document.getElementById('pomodoro-start-btn');
            if (isRunning) {
                // Pause
                clearInterval(timerInterval);
                isRunning = false;
                btn.textContent = 'Resume';
            } else {
                // Start
                isRunning = true;
                btn.textContent = 'Pause';
                timerInterval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        isRunning = false;
                        btn.textContent = 'Start';
                        // Play sound or vibrate
                        if ('vibrate' in navigator) {
                            navigator.vibrate([200, 100, 200]);
                        }
                    }
                    updateDisplay();
                }, 1000);
            }
        },
        reset() {
            clearInterval(timerInterval);
            isRunning = false;
            timeLeft = 5 * 60;
            updateDisplay();
            document.getElementById('pomodoro-start-btn').textContent = 'Start';
        }
    };
})();
</script>

<!-- Initialize Mobile -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    Mobile.init();
    if (Mobile.water && typeof Mobile.water.init === 'function') {
        Mobile.water.init();
    }
});
</script>

<!-- iPhone Home Indicator -->
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>
</body>
</html>

