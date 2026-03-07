<?php
// Dashboard view
$db = new Database(getMasterPassword());

// Get statistics
$projects = $db->load('projects');
$clients = $db->load('clients');
$invoices = $db->load('invoices');
$finance = $db->load('finance');

// Calculate stats
$totalProjects = count($projects);
$activeProjects = count(array_filter($projects, fn($p) => ($p['status'] ?? '') === 'in_progress'));

// Tasks stats
$allTasks = [];
foreach ($projects as $project) {
    if (isset($project['tasks'])) {
        $allTasks = array_merge($allTasks, $project['tasks']);
    }
}
$totalTasks = count($allTasks);
$completedTasks = count(array_filter($allTasks, fn($t) => ($t['status'] ?? '') === 'done'));
$pendingTasks = $totalTasks - $completedTasks;

// Invoice stats
$totalRevenue = array_sum(array_map(fn($i) => ($i['status'] ?? '') === 'paid' ? ($i['total'] ?? 0) : 0, $invoices));
$pendingInvoices = count(array_filter($invoices, fn($i) => ($i['status'] ?? '') === 'sent'));
$pendingAmount = array_sum(array_map(fn($i) => ($i['status'] ?? '') === 'sent' ? ($i['total'] ?? 0) : 0, $invoices));

// Today's tasks
$today = date('Y-m-d');
$todayTasks = array_filter($allTasks, function($t) use ($today) {
    $dueDate = $t['dueDate'] ?? null;
    return $dueDate && date('Y-m-d', strtotime($dueDate)) === $today;
});

// Habits stats
$habits = $db->load('habits');
$completions = $db->load('habit_completions');
$timerSessions = $db->load('habit_timer_sessions');

// Water tracker data
$waterTracker = $db->load('water_tracker');
$waterToday = null;
foreach ($waterTracker as $entry) {
    if ($entry['date'] === $today) {
        $waterToday = $entry;
        break;
    }
}

if (!$waterToday) {
    $waterToday = [
        'glasses' => 0,
        'goal' => 8,
        'reminderInterval' => 60,
        'lastReminder' => null
    ];
}

$totalHabits = count($habits);
$habitsCompletedToday = 0;
$activeHabitsToday = 0;
$habitCompletionRate = 0;

foreach ($habits as $habit) {
    $habitCompletions = array_filter($completions, fn($c) => $c['habitId'] === $habit['id'] && $c['date'] === $today);
    $todayCompleted = count(array_filter($habitCompletions, fn($c) => $c['status'] === 'complete'));

    if ($todayCompleted > 0) {
        $habitsCompletedToday++;
    }
    $activeHabitsToday++;
}

if ($totalHabits > 0) {
    $habitCompletionRate = round(($habitsCompletedToday / $totalHabits) * 100);
}

// Calculate streak
$uniqueCompletionDates = array_unique(array_column($completions, 'date'));
rsort($uniqueCompletionDates);

$streakDays = 0;
$checkDate = new DateTime();
$checkDate->setTime(0, 0, 0);

foreach ($uniqueCompletionDates as $dateStr) {
    $compDate = new DateTime($dateStr);
    $interval = $checkDate->diff($compDate);

    if ($interval->days <= 1) {
        $streakDays++;
        $checkDate->modify('-1 day');
    } else {
        break;
    }
}
?>

<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-black text-white rounded-2xl p-6">
        <h2 class="text-2xl font-semibold">Welcome back, <?php echo e(Auth::user()['name'] ?? 'User'); ?>!</h2>
        <p class="text-gray-400 mt-1"><?php echo date('l, F j, Y'); ?></p>
    </div>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <!-- Tasks -->
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending Tasks</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $pendingTasks; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo $completedTasks; ?> completed</p>
        </div>

        <!-- Projects -->
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Active Projects</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $activeProjects; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo $totalProjects; ?> total</p>
        </div>

        <!-- Water Intake -->
        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border border-blue-200">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-sm text-blue-600 font-medium">Water Intake</p>
                    <p class="text-2xl font-bold text-blue-900 mt-1">
                        <?php echo $waterToday['glasses']; ?> / <?php echo $waterToday['goal']; ?>
                    </p>
                </div>
                <div class="w-10 h-10 bg-blue-200 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2c-5.33 4.55-8 8.48-8 11.8 0 4.98 3.8 9.2 8 9.2s8-4.22 8-9.2c0-3.32-2.67-7.25-8-11.8zm0 18c-3.35 0-6-2.57-6-6.2 0-2.62 1.8-5.2 4.8-7.2 1.5 1.3 3.3 2.4 5.2 3.1V20z"/>
                    </svg>
                </div>
            </div>
            <div class="flex gap-2 mb-3">
                <button onclick="addWaterGlass(1)" class="flex-1 py-2 px-3 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition">
                    +1 Glass
                </button>
                <button onclick="showWaterSettings()" class="py-2 px-3 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </button>
            </div>
            <div class="w-full bg-blue-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all" style="width: <?php echo min(($waterToday['glasses'] / $waterToday['goal']) * 100, 100); ?>%"></div>
            </div>
        </div>

        <!-- Habits -->
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Habits Today</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $habitsCompletedToday; ?>/<?php echo $totalHabits; ?></p>
                </div>
                <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-2 flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-2">
                    <div class="bg-amber-500 h-2 rounded-full transition-all" style="width: <?php echo $habitCompletionRate; ?>%"></div>
                </div>
                <span class="text-xs font-medium text-gray-600"><?php echo $habitCompletionRate; ?>%</span>
            </div>
        </div>

        <!-- Revenue -->
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Revenue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo formatCurrency($totalRevenue); ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo formatCurrency($pendingAmount); ?> pending</p>
        </div>

        <!-- Clients -->
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Clients</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo count($clients); ?></p>
                </div>
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>
            <p class="text-sm text-gray-500 mt-2"><?php echo $pendingInvoices; ?> pending invoices</p>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recent Tasks -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200">
            <div class="p-5 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">Recent Tasks</h3>
                <a href="?page=tasks" class="text-sm text-gray-500 hover:text-gray-700">View All →</a>
            </div>
            <div class="p-5">
                <?php if (empty($allTasks)): ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-500 mt-3">No tasks yet</p>
                        <a href="?page=tasks&action=new" class="inline-block mt-3 text-sm font-medium text-black hover:underline">Create your first task →</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php
                        $recentTasks = array_slice($allTasks, 0, 5);
                        foreach ($recentTasks as $task):
                            $isHabitLinked = !empty($task['linkedHabitId']);
                            $borderClass = $isHabitLinked ? 'border-l-4 border-l-green-500' : 'border-l-4 border-l-blue-400';
                        ?>
                            <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition <?php echo $borderClass; ?>">
                                <?php if ($isHabitLinked): ?>
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0" title="Linked to habit">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <input type="checkbox" class="w-5 h-5 rounded border-gray-300" <?php echo ($task['status'] ?? '') === 'done' ? 'checked' : ''; ?>>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 truncate <?php echo ($task['status'] ?? '') === 'done' ? 'line-through text-gray-400' : ''; ?>">
                                        <?php echo e($task['title'] ?? 'Untitled'); ?>
                                    </p>
                                    <?php if (!empty($task['dueDate'])): ?>
                                        <p class="text-sm text-gray-500"><?php echo formatDate($task['dueDate']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                                    <?php echo strtoupper($task['priority'] ?? 'MEDIUM'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pomodoro Widget -->
        <div class="bg-black text-white rounded-xl p-6">
            <h3 class="font-semibold mb-4">Pomodoro Timer</h3>
            <div class="text-center">
                <div id="pomodoro-display" class="text-6xl font-light tabular-nums">25:00</div>
                <p class="text-gray-400 text-sm mt-2">Focus Session</p>
                <div class="flex gap-3 justify-center mt-6">
                    <button onclick="togglePomodoro()" id="pomodoro-btn" class="px-6 py-2 bg-white text-black rounded-full font-medium hover:bg-gray-200 transition">
                        Start
                    </button>
                    <button onclick="resetPomodoro()" class="px-6 py-2 border border-gray-600 text-white rounded-full hover:bg-gray-800 transition">
                        Reset
                    </button>
                </div>
            </div>
            <a href="?page=pomodoro" class="block text-center text-gray-400 text-sm mt-6 hover:text-white transition">
                Open Full Timer →
            </a>
        </div>
    </div>

    <!-- Habits with Timer Section -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="font-semibold text-gray-900">Today's Habits</h3>
                <p class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="flex items-center gap-1 text-sm text-gray-600">
                    <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                    </svg>
                    <?php echo $streakDays; ?> day streak
                </span>
                <a href="?page=habits" class="text-sm text-gray-500 hover:text-gray-700">Manage →</a>
            </div>
        </div>
        <div class="p-5">
            <?php if (empty($habits)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                    </svg>
                    <p class="text-gray-500 mt-3">No habits yet</p>
                    <a href="?page=habit-form" class="inline-block mt-3 text-sm font-medium text-black hover:underline">Create your first habit →</a>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-600">Showing 3 of <?php echo count($habits); ?> habits</p>
                    <a href="?page=habits" class="text-sm text-gray-600 hover:text-black font-medium">View All →</a>
                </div>
                <div id="habits-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php
                    $displayHabits = array_slice($habits, 0, 3);
                    foreach ($displayHabits as $habit):
                        $habitCompletions = array_filter($completions, fn($c) => $c['habitId'] === $habit['id'] && $c['date'] === $today);
                        $todayCompleted = count(array_filter($habitCompletions, fn($c) => $c['status'] === 'complete')) > 0;
                        $habitTimerSessions = array_filter($timerSessions, fn($s) => $s['habitId'] === $habit['id']);
                        $totalMinutes = round(array_sum(array_map(fn($s) => $s['duration'] ?? 0, $habitTimerSessions)) / 60, 2);
                    ?>
                        <div class="habit-card bg-gray-50 rounded-xl p-4 border border-gray-200 hover:border-gray-300 transition"
                             data-habit-id="<?php echo e($habit['id']); ?>"
                             data-habit-name="<?php echo e($habit['name']); ?>"
                             data-target-duration="<?php echo (int)($habit['targetDuration'] ?? 0); ?>"
                             data-today-completed="<?php echo $todayCompleted ? 'true' : 'false'; ?>">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox"
                                           class="habit-checkbox w-5 h-5 rounded border-gray-300 cursor-pointer"
                                           <?php echo $todayCompleted ? 'checked' : ''; ?>
                                           onchange="toggleHabitCompletion('<?php echo e($habit['id']); ?>', this.checked)">
                                    <div>
                                        <p class="font-semibold text-gray-900 <?php echo $todayCompleted ? 'line-through text-gray-400' : ''; ?>">
                                            <?php echo e($habit['name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo ucfirst($habit['category'] ?? 'general'); ?> • <?php echo $totalMinutes; ?> min total
                                        </p>
                                    </div>
                                </div>
                                <button onclick="showManualTimeModal('<?php echo e($habit['id']); ?>', '<?php echo e($habit['name']); ?>')"
                                        class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-lg transition"
                                        title="Log time manually">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="bg-white rounded-lg p-3 border border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-2xl font-mono font-light tabular-nums timer-display" data-habit-id="<?php echo e($habit['id']); ?>">00:00:00</span>
                                    <div class="flex gap-2">
                                        <button onclick="toggleHabitTimer('<?php echo e($habit['id']); ?>')"
                                                class="timer-start-btn p-2 bg-black text-white rounded-lg hover:bg-gray-800 transition"
                                                data-habit-id="<?php echo e($habit['id']); ?>"
                                                title="Start timer">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M8 5v14l11-7z"/>
                                            </svg>
                                        </button>
                                        <button onclick="stopHabitTimer('<?php echo e($habit['id']); ?>')"
                                                class="timer-stop-btn p-2 bg-gray-200 text-gray-600 rounded-lg hover:bg-gray-300 transition hidden"
                                                data-habit-id="<?php echo e($habit['id']); ?>"
                                                title="Stop timer">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <rect x="6" y="6" width="12" height="12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <?php if (!empty($habit['targetDuration'])): ?>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="target-progress bg-amber-500 h-1.5 rounded-full transition-all" style="width: 0%"
                                             data-habit-id="<?php echo e($habit['id']); ?>"
                                             data-target="<?php echo $habit['targetDuration']; ?>"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Manual Time Modal -->
    <div id="manual-time-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Log Time Manually</h3>
            <p class="text-sm text-gray-600 mb-4">Enter the time spent on <span id="modal-habit-name" class="font-medium"></span></p>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes)</label>
                <input type="number" id="manual-time-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent" min="1" placeholder="e.g., 30">
            </div>
            <div class="flex gap-3 justify-end">
                <button onclick="closeManualTimeModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                <button onclick="submitManualTime()" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 transition">Save Time</button>
            </div>
        </div>
    </div>

    <!-- Water Settings Modal -->
    <div id="water-settings-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Water Tracker Settings</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Daily Goal (glasses)</label>
                    <input type="number" id="water-goal-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" min="1" value="<?php echo $waterToday['goal']; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Reminder Interval (minutes)</label>
                    <select id="water-reminder-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="30" <?php echo $waterToday['reminderInterval'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                        <option value="60" <?php echo $waterToday['reminderInterval'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                        <option value="120" <?php echo $waterToday['reminderInterval'] == 120 ? 'selected' : ''; ?>>2 hours</option>
                        <option value="180" <?php echo $waterToday['reminderInterval'] == 180 ? 'selected' : ''; ?>>3 hours</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="water-notifications-enabled" class="w-4 h-4 rounded border-gray-300" checked>
                    <label for="water-notifications-enabled" class="text-sm text-gray-700">Enable desktop notifications</label>
                </div>
            </div>
            <div class="flex gap-3 justify-end mt-6">
                <button onclick="closeWaterSettings()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                <button onclick="saveWaterSettings()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Save Settings</button>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-3">
            <a href="?page=ai-assistant" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition">
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                </div>
                <span class="font-medium text-gray-700">AI Assistant</span>
            </a>
            <a href="api/export.php?format=zip" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                </div>
                <span class="font-medium text-gray-700">Export Data</span>
            </a>
            <a href="api/export.php?action=export_habits&format=csv" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition">
                <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                    </svg>
                </div>
                <span class="font-medium text-gray-700">Export Habits</span>
            </a>
            <a href="?page=invoices&action=new" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                </div>
                <span class="font-medium text-gray-700">New Invoice</span>
            </a>
            <a href="?page=settings" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <span class="font-medium text-gray-700">Settings</span>
            </a>
        </div>
    </div>
</div>

<script>
// Mini Pomodoro Timer
let pomodoroSeconds = 25 * 60;
let pomodoroRunning = false;
let pomodoroInterval = null;

function updatePomodoroDisplay() {
    const mins = Math.floor(pomodoroSeconds / 60);
    const secs = pomodoroSeconds % 60;
    document.getElementById('pomodoro-display').textContent =
        `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function togglePomodoro() {
    const btn = document.getElementById('pomodoro-btn');
    if (pomodoroRunning) {
        clearInterval(pomodoroInterval);
        btn.textContent = 'Resume';
    } else {
        pomodoroInterval = setInterval(() => {
            pomodoroSeconds--;
            updatePomodoroDisplay();
            if (pomodoroSeconds <= 0) {
                clearInterval(pomodoroInterval);
                pomodoroRunning = false;
                btn.textContent = 'Start';
                showToast('Pomodoro complete! Take a break.', 'success');
            }
        }, 1000);
        btn.textContent = 'Pause';
    }
    pomodoroRunning = !pomodoroRunning;
}

function resetPomodoro() {
    clearInterval(pomodoroInterval);
    pomodoroRunning = false;
    pomodoroSeconds = 25 * 60;
    updatePomodoroDisplay();
    document.getElementById('pomodoro-btn').textContent = 'Start';
}

// Habit Timer System
const habitTimers = new Map();
let currentManualHabitId = null;

function formatTimerDisplay(seconds) {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

function updateHabitTimerDisplay(habitId) {
    const display = document.querySelector(`.timer-display[data-habit-id="${habitId}"]`);
    if (display) {
        display.textContent = formatTimerDisplay(habitTimers.get(habitId)?.elapsed || 0);

        const progressBar = document.querySelector(`.target-progress[data-habit-id="${habitId}"]`);
        if (progressBar) {
            const target = parseInt(progressBar.dataset.target) * 60;
            const current = habitTimers.get(habitId)?.elapsed || 0;
            const percentage = Math.min((current / target) * 100, 100);
            progressBar.style.width = `${percentage}%`;
        }
    }
}

function saveTimerState() {
    const state = {};
    habitTimers.forEach((value, key) => {
        if (value.running) {
            state[key] = {
                elapsed: value.elapsed,
                startTime: value.startTime,
                running: true,
                sessionId: value.sessionId
            };
        }
    });
    localStorage.setItem('habitTimers', JSON.stringify(state));
}

function loadTimerState() {
    const saved = localStorage.getItem('habitTimers');
    if (saved) {
        const state = JSON.parse(saved);
        Object.entries(state).forEach(([habitId, timerState]) => {
            if (timerState.running && timerState.sessionId) {
                const startTime = new Date(timerState.startTime);
                const now = new Date();
                const elapsed = timerState.elapsed + Math.floor((now - startTime) / 1000);

                habitTimers.set(habitId, {
                    elapsed,
                    startTime,
                    running: true,
                    sessionId: timerState.sessionId,
                    interval: null
                });

                updateHabitTimerDisplay(habitId);
                updateTimerButtons(habitId, true);
            }
        });
    }
}

async function toggleHabitTimer(habitId) {
    const timer = habitTimers.get(habitId);

    if (timer?.running) {
        stopHabitTimer(habitId);
    } else {
        try {
            const response = await api.post('api/habits.php?action=start_timer', {
                habitId: habitId,
                csrf_token: CSRF_TOKEN
            });

            if (response.success) {
                const now = new Date();
                habitTimers.set(habitId, {
                    elapsed: 0,
                    startTime: now,
                    running: true,
                    sessionId: response.data.id,
                    interval: null
                });

                habitTimers.get(habitId).interval = setInterval(() => {
                    const timer = habitTimers.get(habitId);
                    if (timer?.running) {
                        timer.elapsed = Math.floor((new Date() - timer.startTime) / 1000);
                        updateHabitTimerDisplay(habitId);
                    }
                }, 1000);

                updateTimerButtons(habitId, true);
                saveTimerState();
                showToast('Timer started', 'success');
            }
        } catch (error) {
            console.error('Failed to start timer:', error);
            showToast('Failed to start timer', 'error');
        }
    }
}

async function stopHabitTimer(habitId) {
    const timer = habitTimers.get(habitId);

    if (timer?.running && timer.sessionId) {
        if (timer.interval) {
            clearInterval(timer.interval);
        }

        try {
            const response = await api.post('api/habits.php?action=stop_timer', {
                sessionId: timer.sessionId,
                csrf_token: CSRF_TOKEN
            });

            if (response.success) {
                timer.running = false;
                timer.interval = null;
                updateTimerButtons(habitId, false);
                saveTimerState();
                showToast(`Timer stopped: ${formatTimerDisplay(response.data.duration)}`, 'success');
            }
        } catch (error) {
            console.error('Failed to stop timer:', error);
            showToast('Failed to stop timer', 'error');
        }
    }
}

function updateTimerButtons(habitId, running) {
    const startBtn = document.querySelector(`.timer-start-btn[data-habit-id="${habitId}"]`);
    const stopBtn = document.querySelector(`.timer-stop-btn[data-habit-id="${habitId}"]`);

    if (startBtn && stopBtn) {
        if (running) {
            startBtn.classList.add('hidden');
            stopBtn.classList.remove('hidden');
        } else {
            startBtn.classList.remove('hidden');
            stopBtn.classList.add('hidden');
        }
    }
}

async function toggleHabitCompletion(habitId, completed) {
    const today = new Date().toISOString().split('T')[0];

    try {
        const response = await api.post('api/habits.php?action=complete', {
            habitId: habitId,
            date: today,
            status: completed ? 'complete' : 'missed',
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(completed ? 'Habit completed!' : 'Habit reopened', 'success');

            const habitCard = document.querySelector(`.habit-card[data-habit-id="${habitId}"]`);
            const habitName = habitCard.dataset.habitName;
            const habitNameEl = habitCard.querySelector('.font-semibold');

            habitNameEl.classList.toggle('line-through', completed);
            habitNameEl.classList.toggle('text-gray-400', completed);

            if (completed) {
                habitCard.dataset.todayCompleted = 'true';
            }
        }
    } catch (error) {
        console.error('Failed to toggle habit:', error);
        showToast('Failed to update habit', 'error');
    }
}

function showManualTimeModal(habitId, habitName) {
    currentManualHabitId = habitId;
    document.getElementById('modal-habit-name').textContent = habitName;
    document.getElementById('manual-time-input').value = '';
    document.getElementById('manual-time-modal').classList.remove('hidden');
    document.getElementById('manual-time-modal').classList.add('flex');
}

function closeManualTimeModal() {
    document.getElementById('manual-time-modal').classList.add('hidden');
    document.getElementById('manual-time-modal').classList.remove('flex');
    currentManualHabitId = null;
}

async function submitManualTime() {
    const duration = parseInt(document.getElementById('manual-time-input').value);

    if (!duration || duration <= 0) {
        showToast('Please enter a valid duration', 'error');
        return;
    }

    try {
        const response = await api.post('api/habits.php?action=manual_log', {
            habitId: currentManualHabitId,
            duration: duration * 60,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Logged ${duration} minutes`, 'success');
            closeManualTimeModal();

            const timer = habitTimers.get(currentManualHabitId);
            if (timer) {
                timer.elapsed += duration * 60;
                updateHabitTimerDisplay(currentManualHabitId);
            }
        }
    } catch (error) {
        console.error('Failed to log manual time:', error);
        showToast('Failed to log time', 'error');
    }
}

window.addEventListener('beforeunload', () => {
    habitTimers.forEach((timer, habitId) => {
        if (timer?.running) {
            stopHabitTimer(habitId);
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    loadTimerState();
    loadWaterTracker();
    startWaterReminder();
});

// Water Tracker System
let waterReminderInterval = null;

function loadWaterTracker() {
    const saved = localStorage.getItem('waterTracker');
    if (saved) {
        const data = JSON.parse(saved);
        if (data.notificationsEnabled && 'Notification' in window) {
            Notification.requestPermission();
        }
    }
}

async function addWaterGlass(count) {
    try {
        const response = await api.post('api/habits.php?action=add_water_glass', {
            count: count,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Added ${count} glass${count > 1 ? 'es' : ''}!`, 'success');
            updateWaterDisplay(response.data.glasses, response.data.goal);
        }
    } catch (error) {
        console.error('Failed to add water glass:', error);
        showToast('Failed to update water intake', 'error');
    }
}

function updateWaterDisplay(glasses, goal) {
    const counterEl = document.querySelector('.bg-gradient-to-br .text-2xl');
    if (counterEl) {
        counterEl.textContent = `${glasses} / ${goal}`;
    }

    const progressBar = document.querySelector('.bg-blue-200 .bg-blue-600');
    if (progressBar) {
        const percentage = Math.min((glasses / goal) * 100, 100);
        progressBar.style.width = `${percentage}%`;
    }
}

function showWaterSettings() {
    document.getElementById('water-settings-modal').classList.remove('hidden');
    document.getElementById('water-settings-modal').classList.add('flex');
}

function closeWaterSettings() {
    document.getElementById('water-settings-modal').classList.add('hidden');
    document.getElementById('water-settings-modal').classList.remove('flex');
}

async function saveWaterSettings() {
    const goal = parseInt(document.getElementById('water-goal-input').value);
    const reminderInterval = parseInt(document.getElementById('water-reminder-input').value);
    const notificationsEnabled = document.getElementById('water-notifications-enabled').checked;

    try {
        const response = await api.post('api/habits.php?action=set_water_goal', {
            goal: goal,
            reminderInterval: reminderInterval,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Water settings saved!', 'success');
            closeWaterSettings();

            localStorage.setItem('waterTracker', JSON.stringify({
                notificationsEnabled,
                reminderInterval,
                goal
            }));

            if (notificationsEnabled && 'Notification' in window && Notification.permission === 'default') {
                await Notification.requestPermission();
            }

            startWaterReminder();
        }
    } catch (error) {
        console.error('Failed to save water settings:', error);
        showToast('Failed to save settings', 'error');
    }
}

function startWaterReminder() {
    const saved = localStorage.getItem('waterTracker');
    if (!saved) return;

    const settings = JSON.parse(saved);
    if (!settings.notificationsEnabled) return;

    if (waterReminderInterval) {
        clearInterval(waterReminderInterval);
    }

    const intervalMs = (settings.reminderInterval || 60) * 60 * 1000;

    waterReminderInterval = setInterval(() => {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification('💧 Drink Water!', {
                body: 'Stay hydrated! Time for a glass of water.',
                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233B82F6"><path d="M12 2c-5.33 4.55-8 8.48-8 11.8 0 4.98 3.8 9.2 8 9.2s8-4.22 8-9.2c0-3.32-2.67-7.25-8-11.8zm0 18c-3.35 0-6-2.57-6-6.2 0-2.62 1.8-5.2 4.8-7.2 1.5 1.3 3.3 2.4 5.2 3.1V20z"/></svg>'
            });

            notification.onclick = function() {
                window.focus();
                notification.close();
            };

            setTimeout(() => notification.close(), 10000);
        }
    }, intervalMs);
}
</script>
