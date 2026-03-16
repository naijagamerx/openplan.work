<?php
// Dashboard view
$dashboardError = null;
$masterPassword = getMasterPassword();
$backupReminder = null;

if ($masterPassword === '') {
    $dashboardError = 'Your secure session is missing the master key required to unlock the dashboard. Sign in again to continue.';
} else {
    try {
        $db = new Database($masterPassword, Auth::userId());

        // Check for login success to request notification permission
        $loginSuccess = isset($_GET['login']) && $_GET['login'] === 'success';

        // Get statistics
        $projects = $db->load('projects');
        $invoices = $db->load('invoices');
        $finance = $db->load('finance');

        // Calculate stats
        $totalProjects = count($projects);
        $activeProjects = count(array_filter($projects, fn($p) => isProjectActive($p['status'] ?? null)));

        // Tasks stats
        $allTasks = [];
        foreach ($projects as $project) {
            if (isset($project['tasks'])) {
                foreach ($project['tasks'] as $task) {
                    $task['projectName'] = $project['name'];
                    $task['projectId'] = $project['id'];
                    $allTasks[] = $task;
                }
            }
        }

        // Normalize and sort
        $allTasks = array_map(function($task) {
            $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');
            return $task;
        }, $allTasks);

        // Sort by createdAt descending
        usort($allTasks, function($a, $b) {
            $dateA = $a['createdAt'] ?? '';
            $dateB = $b['createdAt'] ?? '';
            return strcmp($dateB, $dateA);
        });

        $totalTasks = count($allTasks);
        $completedTasks = count(array_filter($allTasks, fn($t) => isTaskRecordDone($t)));
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

        // Filter out inactive (archived) habits
        $habits = filterActiveHabits($habits);

        // Water tracker data
        $waterTracker = $db->load('water_tracker');
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
                'goal' => 8,
                'reminderInterval' => 60,
                'lastReminder' => null
            ];
        }

        $totalHabits = count($habits);
        $habitsCompletedToday = 0;
        $activeHabitsToday = 0;
        $habitCompletionRate = 0;

        $habitMap = [];
        foreach ($habits as $key => $habit) {
            $habitCompletions = array_filter($completions, fn($c) => $c['habitId'] === $habit['id'] && $c['date'] === $today);
            $todayCompleted = count(array_filter($habitCompletions, fn($c) => $c['status'] === 'complete'));
            $habit['todayCompleted'] = $todayCompleted > 0;

            if ($todayCompleted > 0) {
                $habitsCompletedToday++;
            }
            $activeHabitsToday++;

            $habits[$key] = $habit;
            $habitMap[$habit['id']] = $habit;
        }

        if ($totalHabits > 0) {
            $habitCompletionRate = round(($habitsCompletedToday / $totalHabits) * 100);
        }

        $backupService = new Backup($db);
        $backupSettings = array_merge([
            'enabled' => false,
            'frequency' => 'daily',
            'retention' => 7,
            'last_auto_backup_at' => null
        ], $backupService->getSettings());
        $backupList = $backupService->getBackupList();
        $latestBackup = $backupList[0] ?? null;
        $latestBackupAt = $latestBackup['created_at'] ?? $backupSettings['last_auto_backup_at'] ?? null;
        $thresholdDays = ($backupSettings['enabled'] ?? false)
            ? (($backupSettings['frequency'] ?? 'daily') === 'weekly' ? 8 : 2)
            : 7;

        $isBackupOverdue = true;
        if (!empty($latestBackupAt)) {
            $latestBackupTs = strtotime((string)$latestBackupAt);
            $isBackupOverdue = $latestBackupTs === false || ((time() - $latestBackupTs) > ($thresholdDays * 86400));
        }

        if ($isBackupOverdue) {
            $backupReminder = [
                'latest' => $latestBackupAt,
                'threshold_days' => $thresholdDays,
                'storage' => ($backupSettings['enabled'] ?? false)
                    ? ('Auto backups are enabled for this workspace on a ' . ($backupSettings['frequency'] ?? 'daily') . ' schedule.')
                    : 'Automatic backups are off for this workspace.'
            ];
        }

        $recentHabitCompletions = array_filter($completions, fn($c) => ($c['status'] ?? '') === 'complete');
        usort($recentHabitCompletions, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
        $recentHabitCompletions = array_slice($recentHabitCompletions, 0, 5);

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
    } catch (Exception $e) {
        $dashboardError = 'We could not unlock your dashboard data with the current security key. Sign in again and verify the master password you used for this installation.';
    }
}
?>

<?php if ($dashboardError !== null): ?>
    <div class="max-w-2xl mx-auto py-16">
        <div class="bg-white border border-red-100 rounded-3xl shadow-sm p-8 text-center">
            <div class="inline-flex w-14 h-14 items-center justify-center rounded-full bg-red-50 text-red-600 mb-5">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 mb-3">Dashboard unavailable</h2>
            <p class="text-gray-600 leading-relaxed mb-8"><?php echo e($dashboardError); ?></p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="?page=login" class="px-6 py-3 bg-black text-white rounded-xl font-semibold hover:bg-gray-800 transition">Sign In Again</a>
                <a href="api/auth.php?action=logout" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-semibold hover:bg-gray-50 transition">Clear Session</a>
            </div>
        </div>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-black text-white rounded-2xl p-6">
        <h2 class="text-2xl font-semibold">Welcome back, <?php echo e(Auth::user()['name'] ?? 'User'); ?>!</h2>
        <p class="text-gray-400 mt-1"><?php echo date('l, F j, Y'); ?></p>
    </div>

    <?php if ($backupReminder !== null): ?>
        <div id="backup-reminder-banner" class="bg-amber-50 border border-amber-200 rounded-2xl p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-amber-900">Backup reminder</p>
                <p class="text-sm text-amber-800 mt-1">
                    <?php if (!empty($backupReminder['latest'])): ?>
                        Your last backup was created on <?php echo e(date('F j, Y g:i A', strtotime((string)$backupReminder['latest']))); ?>.
                    <?php else: ?>
                        No backup has been created for this workspace yet.
                    <?php endif; ?>
                    Create a fresh backup to protect your encrypted data.
                </p>
                <p class="text-xs text-amber-700 mt-2"><?php echo e($backupReminder['storage']); ?></p>
            </div>
            <div class="flex items-center gap-3">
                <a href="?page=import-data" class="px-4 py-2 bg-black text-white rounded-xl font-medium hover:bg-gray-800 transition">Open Data Management</a>
                <button type="button" onclick="dismissBackupReminder()" class="px-4 py-2 border border-amber-300 text-amber-900 rounded-xl font-medium hover:bg-amber-100 transition">Dismiss</button>
            </div>
        </div>
        <script>
        (function() {
            const storageKey = 'backup_reminder_dismissed_<?php echo e((string)Auth::userId()); ?>';
            const banner = document.getElementById('backup-reminder-banner');
            if (!banner) {
                return;
            }

            try {
                const dismissedAt = localStorage.getItem(storageKey);
                if (dismissedAt) {
                    const elapsed = Date.now() - Number(dismissedAt);
                    if (!Number.isNaN(elapsed) && elapsed < 24 * 60 * 60 * 1000) {
                        banner.remove();
                    }
                }
            } catch (error) {
                console.warn('Backup reminder storage unavailable', error);
            }
        })();

        function dismissBackupReminder() {
            const banner = document.getElementById('backup-reminder-banner');
            if (banner) {
                banner.remove();
            }
            try {
                localStorage.setItem('backup_reminder_dismissed_<?php echo e((string)Auth::userId()); ?>', String(Date.now()));
            } catch (error) {
                console.warn('Backup reminder storage unavailable', error);
            }
        }
        </script>
    <?php endif; ?>
    
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
    </div>

    <!-- Hydration + Pomodoro -->
    <div class="grid grid-cols-1 lg:grid-cols-10 gap-6">
        <!-- Dedicated Water Tracker Section -->
        <div id="water-tracker-section" class="bg-gradient-to-br from-blue-50 via-cyan-50 to-white rounded-2xl border border-blue-200 p-8 lg:col-span-7">
            <div class="flex flex-col lg:flex-row items-center gap-8">
            <!-- SVG Cup Animation -->
            <div class="relative">
                <svg width="200" height="280" viewBox="0 0 200 280" class="drop-shadow-xl">
                    <!-- Cup body -->
                    <defs>
                        <clipPath id="cup-clip">
                            <path d="M30,60 L50,240 Q50,260 70,260 L130,260 Q150,260 150,240 L170,60 Z"/>
                        </clipPath>
                        <linearGradient id="water-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" style="stop-color:#3B82F6"/>
                            <stop offset="100%" style="stop-color:#1D4ED8"/>
                        </linearGradient>
                        <linearGradient id="glass-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" style="stop-color:#E0F2FE"/>
                            <stop offset="50%" style="stop-color:#BAE6FD"/>
                            <stop offset="100%" style="stop-color:#E0F2FE"/>
                        </linearGradient>
                        <filter id="glow">
                            <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
                            <feMerge>
                                <feMergeNode in="coloredBlur"/>
                                <feMergeNode in="SourceGraphic"/>
                            </feMerge>
                        </filter>
                    </defs>

                    <!-- Cup outline -->
                    <path d="M30,60 L50,240 Q50,260 70,260 L130,260 Q150,260 150,240 L170,60"
                          fill="url(#glass-gradient)" stroke="#0EA5E9" stroke-width="3"/>

                    <!-- Water fill with animation -->
                    <g clip-path="url(#cup-clip)">
                        <!-- Water background -->
                        <rect id="water-fill" x="0" y="260" width="200" height="200"
                              fill="url(#water-gradient)" class="transition-all duration-500 ease-out"/>

                        <!-- Wave animation layer 1 -->
                        <g id="wave1" class="transition-transform duration-500">
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
                        <g id="wave2" class="transition-transform duration-500">
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

                <!-- Percentage overlay -->
                <div id="water-percentage" class="absolute inset-0 flex items-center justify-center">
                    <span class="text-4xl font-bold text-white drop-shadow-md">0%</span>
                </div>
            </div>

            <!-- Water Controls -->
            <div class="flex-1 text-center lg:text-left">
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Stay Hydrated</h3>
                <p class="text-gray-600 mb-6">Track your daily water intake with a visual cup</p>

                <!-- Progress display -->
                <div class="mb-6">
                    <div class="text-5xl font-bold text-blue-600 mb-2">
                        <span id="water-liters-display"><?php echo number_format((($waterToday['intakeMl'] ?? (($waterToday['glasses'] ?? 0) * 250)) / 1000), 2); ?></span>
                        <span class="text-2xl text-gray-400">/ <span id="water-goal-liters"><?php echo number_format((($waterToday['goalMl'] ?? (($waterToday['goal'] ?? 8) * 250)) / 1000), 2); ?></span> L</span>
                    </div>
                    <div class="w-full bg-blue-200 rounded-full h-4 overflow-hidden">
                        <div id="water-progress-bar" class="bg-gradient-to-r from-blue-400 to-blue-600 h-4 rounded-full transition-all duration-500 ease-out"
                             style="width: <?php echo min(((($waterToday['intakeMl'] ?? (($waterToday['glasses'] ?? 0) * 250)) / max(1, ($waterToday['goalMl'] ?? (($waterToday['goal'] ?? 8) * 250)))) * 100), 100); ?>%"></div>
                    </div>
                    <p id="water-percentage-text" class="text-sm text-blue-600 mt-2 font-medium">
                        <?php echo round(min(((($waterToday['intakeMl'] ?? (($waterToday['glasses'] ?? 0) * 250)) / max(1, ($waterToday['goalMl'] ?? (($waterToday['goal'] ?? 8) * 250)))) * 100), 100)); ?>% complete
                    </p>
                </div>

                <!-- Quick add buttons -->
                <div class="flex flex-wrap gap-3 justify-center lg:justify-start mb-6">
                    <button onclick="addWaterMl(250)" class="px-6 py-3 bg-blue-500 text-white rounded-xl font-semibold hover:bg-blue-600 transition shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        +0.25 L
                    </button>
                    <button onclick="addWaterMl(500)" class="px-6 py-3 bg-cyan-500 text-white rounded-xl font-semibold hover:bg-cyan-600 transition shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                        +0.50 L
                    </button>
                    <button onclick="addWaterMl(1000)" class="px-6 py-3 bg-white text-blue-600 border-2 border-blue-200 rounded-xl font-semibold hover:bg-blue-50 transition">
                        +1.00 L
                    </button>
                </div>

                <div id="water-missed-status" class="hidden mb-6 border border-red-200 bg-red-50 rounded-xl p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-red-700 uppercase tracking-widest">Behind Schedule</p>
                            <p id="water-missed-count" class="text-sm text-red-600 mt-1">Missed 0 reminder(s) today</p>
                            <p id="water-next-due" class="text-xs text-red-500 mt-1">Next due: --:--</p>
                        </div>
                        <svg class="w-5 h-5 text-red-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-7.938 4h15.876c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L2.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Settings button -->
                <button onclick="showWaterSettings()" class="inline-flex items-center gap-2 px-4 py-2 text-gray-600 hover:text-gray-900 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Settings
                </button>
            </div>
            </div>
        </div>

        <!-- Pomodoro Widget -->
        <div class="bg-black text-white rounded-xl p-6 lg:col-span-3">
            <h3 class="font-semibold mb-4">Pomodoro Timer</h3>
            <div class="text-center">
                <div class="relative w-44 h-44 mx-auto flex items-center justify-center">
                    <svg class="absolute inset-0" viewBox="0 0 120 120">
                        <circle cx="60" cy="60" r="52" class="stroke-gray-700" stroke-width="6" fill="none" opacity="0.35"></circle>
                        <circle id="pomodoro-ring" cx="60" cy="60" r="52" stroke="#ffffff" stroke-width="6" fill="none" stroke-linecap="round" stroke-dasharray="326.7" stroke-dashoffset="326.7" class="transition-all duration-500 ease-linear opacity-90"></circle>
                    </svg>
                    <div id="pomodoro-display" class="text-4xl font-semibold tabular-nums">25:00</div>
                </div>
                <p id="pomodoro-status-mini" class="text-gray-400 text-xs mt-3 uppercase tracking-widest">Ready</p>
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
                Open Full Timer</a>
        </div>
    </div>

    <!-- Daily Hydration Inspiration (AI Quote) -->
    <div id="water-quote-section" class="bg-white rounded-2xl border border-gray-200 p-6 shadow-sm mb-6">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-widest mb-2">Daily Hydration Inspiration</h3>
                <p id="water-quote" class="text-lg font-medium text-gray-900 italic">"Water is the driving force of all nature." - Leonardo da Vinci</p>
                <div class="mt-3 flex items-center gap-4">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <span id="water-tip">Keep a water bottle at your desk</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span id="water-fact">Your body is 60% water</span>
                    </div>
                </div>
            </div>
            <button onclick="getWaterQuote()" class="px-4 py-2 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:text-gray-900 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                New Quote
            </button>
        </div>
    </div>

    <!-- Recent Tasks -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <div class="p-5 bg-gray-50/50 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Recent Tasks</h3>
            <a href="?page=tasks" class="text-sm text-gray-500 hover:text-gray-700">View All</a>
        </div>
        <div class="hidden md:grid grid-cols-12 gap-4 px-5 py-3 bg-white text-[11px] font-black uppercase tracking-widest text-gray-400 border-b border-gray-100">
            <div class="col-span-4">Task Name</div>
            <div class="col-span-2">Project</div>
            <div class="col-span-1">Status</div>
            <div class="col-span-1">Priority</div>
            <div class="col-span-3">Time Tracking</div>
            <div class="col-span-1 text-right">Actions</div>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (empty($allTasks)): ?>
                <div class="p-12 text-center">
                    <p class="text-gray-400 font-medium">No tasks found. Time to create some!</p>
                </div>
            <?php else: ?>
                <?php
                $recentTasksDisplay = array_slice($allTasks, 0, 5);
                foreach ($recentTasksDisplay as $task):
                    $createdAt = $task['createdAt'] ?? null;
                    $dueDate = $task['dueDate'] ?? null;
                    $todayStart = strtotime(date('Y-m-d'));
                    $dueStateLabel = 'No Due';
                    $dueStateClass = 'bg-gray-100 text-gray-500';
                    if (!empty($dueDate)) {
                        $dueTs = strtotime($dueDate);
                        if ($dueTs < $todayStart) {
                            $dueStateLabel = 'Overdue';
                            $dueStateClass = 'bg-red-100 text-red-700';
                        } elseif ($dueTs === $todayStart) {
                            $dueStateLabel = 'Due Today';
                            $dueStateClass = 'bg-amber-100 text-amber-700';
                        } elseif ($dueTs <= $todayStart + 2 * 86400) {
                            $dueStateLabel = 'Due Soon';
                            $dueStateClass = 'bg-yellow-100 text-yellow-700';
                        } else {
                            $dueStateLabel = 'Upcoming';
                            $dueStateClass = 'bg-green-100 text-green-700';
                        }
                    }
                ?>
                <div class="task-row grid grid-cols-1 md:grid-cols-12 gap-4 p-5 hover:bg-gray-50 transition-colors group items-center"
                     data-project="<?php echo e($task['projectId'] ?? ''); ?>"
                     data-status="<?php echo e($task['status'] ?? 'todo'); ?>"
                     data-priority="<?php echo e($task['priority'] ?? 'medium'); ?>"
                     data-task-id="<?php echo e($task['id']); ?>"
                     data-estimated-minutes="<?php echo (int)($task['estimatedMinutes'] ?? 0); ?>"
                     data-due-date="<?php echo e($task['dueDate'] ?? ''); ?>"
                     data-title="<?php echo e($task['title'] ?? ''); ?>">
                    <div class="md:col-span-4 flex items-start gap-3 min-w-0">
                        <input type="checkbox"
                               class="mt-1 w-6 h-6 rounded-lg border-2 border-gray-200 text-black focus:ring-black cursor-pointer transition-all"
                               <?php echo isTaskDone($task['status'] ?? '') ? 'checked' : ''; ?>
                               onchange="toggleTask('<?php echo e($task['id']); ?>', this.checked)">
                        <div class="min-w-0">
                            <p class="font-bold text-gray-900 truncate <?php echo isTaskDone($task['status'] ?? '') ? 'line-through text-red-500 decoration-red-500' : ''; ?>">
                                <?php echo e($task['title']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-500 truncate"><?php echo e($task['projectName'] ?? ''); ?></p>
                    </div>
                    <div class="md:col-span-1">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo statusClass($task['status'] ?? 'todo'); ?>">
                            <?php echo ucwords(str_replace('_', ' ', $task['status'] ?? 'todo')); ?>
                        </span>
                    </div>
                    <div class="md:col-span-1">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                            <?php echo $task['priority'] ?? 'medium'; ?>
                        </span>
                    </div>
                    <div class="md:col-span-3 flex items-center gap-2 flex-wrap">
                        <!-- Status Badges -->
                        <div class="deadline-badge-container" data-task-id="<?php echo e($task['id']); ?>"></div>
                        <!-- Timer Display -->
                        <div class="timer-display-container hidden" data-task-timer-display="<?php echo e($task['id']); ?>">
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-mono font-bold rounded-full" data-task-timer-remaining="<?php echo e($task['id']); ?>">00:00</span>
                        </div>
                        <!-- Estimated Time -->
                        <div class="flex items-center gap-1.5 text-xs text-gray-600">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><?php echo (int)($task['estimatedMinutes'] ?? 0); ?>m</span>
                        </div>
                        <!-- Time Spent Badge -->
                        <?php if (!empty($task['actualMinutes'])): ?>
                        <span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-medium rounded-full">
                            <?php echo (int)$task['actualMinutes']; ?>m spent
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="md:col-span-1 flex items-center justify-end">
                        <div class="grid grid-cols-2 gap-1">
                            <button onclick="toggleTaskTimer('<?php echo e($task['id']); ?>')" class="task-timer-btn p-1.5 bg-white border border-gray-200 rounded-lg text-gray-500 hover:text-blue-600 hover:border-blue-600 transition-all" title="Start timer" data-task-timer-btn="<?php echo e($task['id']); ?>">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-4.586-2.293A1 1 0 009 9.764v4.472a1 1 0 001.166.986l4.586-2.293a1 1 0 000-1.758z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                            <a href="?page=view-task&id=<?php echo e($task['id']); ?>&projectId=<?php echo e($task['projectId'] ?? ''); ?>" class="p-1.5 bg-white border border-gray-200 rounded-lg text-gray-500 hover:text-green-600 hover:border-green-600 transition-all flex items-center justify-center" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                <a href="?page=habits" class="text-sm text-gray-500 hover:text-gray-700">Manage</a>
            </div>
        </div>
        <div class="p-5">
            <?php if (empty($habits)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"></path>
                    </svg>
                    <p class="text-gray-500 mt-3">No habits yet</p>
                    <a href="?page=habit-form" class="inline-block mt-3 text-sm font-medium text-black hover:underline">Create your first habit</a>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-between mb-4">
                    <p class="text-sm text-gray-600">Showing 3 of <?php echo count($habits); ?> habits</p>
                    <a href="?page=habits" class="text-sm text-gray-600 hover:text-black font-medium">View All</a>
                </div>
                <div id="habits-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php
                    // Filter out completed habits, then take up to 3
                    $incompleteHabits = array_filter($habits, fn($h) => !$h['todayCompleted']);
                    $displayHabits = array_slice(array_values($incompleteHabits), 0, 3);
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
                    <label class="block text-sm font-medium text-gray-700 mb-2">Daily Goal (L)</label>
                    <input type="number" id="water-goal-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" min="0.5" step="0.1" value="<?php echo number_format((($waterToday['goalMl'] ?? (($waterToday['goal'] ?? 8) * 250)) / 1000), 2, '.', ''); ?>">
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
                <div id="notification-status" class="text-sm text-gray-500"></div>
                <button onclick="testWaterNotification()" class="w-full px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">
                    Test Notification
                </button>
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
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
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
            <a href="?page=data-recovery" class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 hover:border-gray-300 hover:shadow-sm transition">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </div>
                <span class="font-medium text-gray-700">Data Recovery</span>
            </a>
        </div>
    </div>
</div>




<script>
// Pomodoro handled by shared dashboard controller

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

                // Restart the interval for restored timers
                habitTimers.get(habitId).interval = setInterval(() => {
                    const timer = habitTimers.get(habitId);
                    if (timer?.running) {
                        timer.elapsed = Math.floor((new Date() - timer.startTime) / 1000);
                        updateHabitTimerDisplay(habitId);

                        // Check target
                        const habitCard = document.querySelector(`.habit-card[data-habit-id="${habitId}"]`);
                        const targetMinutes = parseInt(habitCard?.dataset.targetDuration) || 0;
                        if (targetMinutes > 0 && timer.elapsed >= targetMinutes * 60) {
                            const habitName = habitCard.dataset.habitName;
                            stopHabitTimer(habitId);
                            App.notifications.send(`Goal Reached: ${habitName}`, {
                                body: `You've completed your goal of ${targetMinutes} minutes!`,
                                requireInteraction: true
                            });
                        }
                    }
                }, 1000);

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

                        // Check target
                        const habitCard = document.querySelector(`.habit-card[data-habit-id="${habitId}"]`);
                        const targetMinutes = parseInt(habitCard?.dataset.targetDuration) || 0;
                        if (targetMinutes > 0 && timer.elapsed >= targetMinutes * 60) {
                            const habitName = habitCard.dataset.habitName;
                            stopHabitTimer(habitId);
                            App.notifications.send(`Goal Reached: ${habitName}`, {
                                body: `You've completed your goal of ${targetMinutes} minutes!`,
                                requireInteraction: true
                            });
                        }
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

            // Handle case where timer session no longer exists in backend
            if (error.status === 404 && error.response?.error?.code === 'NOT_FOUND') {
                // Timer session was not found, clear local state
                timer.running = false;
                timer.interval = null;
                updateTimerButtons(habitId, false);
                saveTimerState();
                showToast('Timer session expired or not found. State cleared.', 'warning');
            } else {
                showToast('Failed to stop timer', 'error');
            }
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
    getWaterQuote(); // Load initial quote
});

// Water Tracker System
let waterReminderInterval = null;

function checkNotificationPermission() {
    if (!('Notification' in window)) {
        return 'unsupported';
    }
    return Notification.permission;
}

async function requestNotificationPermission() {
    if (!('Notification' in window)) {
        return 'unsupported';
    }
    if (Notification.permission === 'granted') {
        return 'granted';
    }
    if (Notification.permission === 'denied') {
        return 'denied';
    }
    return await Notification.requestPermission();
}

function sendNotification(title, options = {}) {
    if (!('Notification' in window)) {
        return null;
    }
    if (Notification.permission === 'granted') {
        return new Notification(title, {
            ...options,
            requireInteraction: true,
            vibrate: [200, 100, 200]
        });
    }
    return null;
}

async function enableNotifications() {
    const permission = await requestNotificationPermission();

    if (permission === 'granted') {
        localStorage.setItem('waterTracker', JSON.stringify({
            ...JSON.parse(localStorage.getItem('waterTracker') || '{}'),
            notificationsEnabled: true
        }));
        startWaterReminder();
    }
}

async function loadWaterTracker() {
    // Load notification settings from localStorage
    const saved = localStorage.getItem('waterTracker');
    if (saved) {
        const data = JSON.parse(saved);
        if (data.notificationsEnabled && 'Notification' in window) {
            if (Notification.permission === 'granted') {
                startWaterReminder();
            }
        }
    }

    // Fetch water tracker data from API and update display
    try {
        const [trackerResponse, trackingResponse] = await Promise.all([
            api.get('api/habits.php?action=get_water_tracker'),
            api.get('api/habits.php?action=get_daily_tracking')
        ]);

        let glassSizeMl = 250;
        let intakeMl = 0;
        let goalMl = 2000;

        if (trackingResponse.success && trackingResponse.data?.activePlan) {
            glassSizeMl = Number(trackingResponse.data.activePlan.glassSize) || 250;
            goalMl = Number(trackingResponse.data.activePlan.dailyGoal) || goalMl;
        }

        if (trackerResponse.success && trackerResponse.data) {
            const tracker = trackerResponse.data;
            intakeMl = Number(tracker.intakeMl ?? ((Number(tracker.glasses) || 0) * glassSizeMl)) || 0;
            goalMl = Number(tracker.goalMl ?? (goalMl || ((Number(tracker.goal) || 8) * glassSizeMl))) || goalMl;
        }

        window.waterGlassSizeMl = glassSizeMl;
        window.waterGoalMl = goalMl;
        updateWaterDisplay(intakeMl, goalMl);
        updateWaterMissedStatus(trackingResponse.success ? trackingResponse.data : null);
    } catch (error) {
        console.error('Failed to load water tracker:', error);
        updateWaterDisplay(0, 2000);
    }
}

async function addWaterMl(amountMl) {
    const safeAmountMl = Math.max(1, parseInt(amountMl, 10) || 250);
    try {
        const response = await api.post('api/habits.php?action=add_water_glass', {
            amountMl: safeAmountMl,
            count: Number((safeAmountMl / (window.waterGlassSizeMl || 250)).toFixed(2)),
            glassSizeMl: window.waterGlassSizeMl || 250,
            goalMl: window.waterGoalMl || 2000,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Added ${(safeAmountMl / 1000).toFixed(2)} L`, 'success');
            const intakeMl = Number(response.data.intakeMl ?? ((Number(response.data.glasses) || 0) * (window.waterGlassSizeMl || 250))) || 0;
            const goalMl = Number(response.data.goalMl ?? window.waterGoalMl || 2000) || 2000;
            window.waterGoalMl = goalMl;
            updateWaterDisplay(intakeMl, goalMl);
            try {
                const tracking = await api.get('api/habits.php?action=get_daily_tracking');
                if (tracking.success) {
                    updateWaterMissedStatus(tracking.data);
                }
            } catch (e) {
                console.error('Failed to refresh daily water tracking:', e);
            }
        }
    } catch (error) {
        console.error('Failed to add water intake:', error);
        showToast('Failed to update water intake', 'error');
    }
}

async function addWaterGlass(count) {
    const normalizedCount = Number(count) || 1;
    const ml = Math.round(normalizedCount * (window.waterGlassSizeMl || 250));
    await addWaterMl(ml);
}

async function getWaterQuote() {
    const quoteEl = document.getElementById('water-quote');
    const tipEl = document.getElementById('water-tip');
    const factEl = document.getElementById('water-fact');

    // Show loading state
    if (quoteEl) quoteEl.textContent = 'Loading inspiration...';

    try {
        const response = await api.get('api/habits.php?action=get_water_quote');

        // Check if response is successful and has data
        if (response && response.success && response.data && response.data.quote) {
            if (quoteEl) quoteEl.textContent = `"${response.data.quote}"`;
            if (tipEl) tipEl.textContent = response.data.tip || 'Keep a water bottle at your desk';
            if (factEl) factEl.textContent = response.data.fact || 'Your body is 60% water';
        } else {
            // Response didn't have expected data, use fallback
            setFallbackQuote();
        }
    } catch (error) {
        console.error('Failed to load quote:', error);
        // Fallback to default quote on error
        setFallbackQuote();
    }

    function setFallbackQuote() {
        const fallbacks = [
            ['"Water is the driving force of all nature." - Leonardo da Vinci', 'Keep a water bottle at your desk', 'Your body is 60% water'],
            ['"Thousands have lived without love, not one without water." - W. H. Auden', 'Drink a glass of water before each meal', 'Your brain is 75% water'],
            ['"Pure water is the world\'s first and foremost medicine." - Slovakian proverb', 'Set reminders on your phone', 'Blood is 90% water']
        ];
        const random = fallbacks[Math.floor(Math.random() * fallbacks.length)];

        if (quoteEl) quoteEl.textContent = random[0];
        if (tipEl) tipEl.textContent = random[1];
        if (factEl) factEl.textContent = random[2];
    }
}

function updateWaterDisplay(intakeMl, goalMl) {
    const safeGoalMl = Math.max(1, Number(goalMl) || 2000);
    const safeIntakeMl = Math.max(0, Number(intakeMl) || 0);
    const percentage = Math.min((safeIntakeMl / safeGoalMl) * 100, 100);

    const litersDisplay = document.getElementById('water-liters-display');
    if (litersDisplay) {
        litersDisplay.textContent = (safeIntakeMl / 1000).toFixed(2);
    }
    const goalDisplay = document.getElementById('water-goal-liters');
    if (goalDisplay) {
        goalDisplay.textContent = (safeGoalMl / 1000).toFixed(2);
    }

    // Update progress bar in new section
    const progressBar = document.getElementById('water-progress-bar');
    if (progressBar) {
        progressBar.style.width = `${percentage}%`;
    }

    // Update percentage text
    const percentageText = document.getElementById('water-percentage-text');
    if (percentageText) {
        percentageText.textContent = `${Math.round(percentage)}% complete`;
    }

    // Update SVG water fill
    const waterFill = document.getElementById('water-fill');
    if (waterFill) {
        // Calculate Y position based on percentage (empty at 0%, full at 100%)
        // Cup height in SVG is from y=60 to y=260, so 200px total
        // At 0%: y=260 (water below cup), At 100%: y=60 (water at top)
        const fillHeight = (percentage / 100) * 200;
        const fillY = 260 - fillHeight;
        waterFill.setAttribute('y', fillY);
        waterFill.setAttribute('height', fillHeight);
    }

    // Update wave position
    const wave1 = document.getElementById('wave1');
    const wave2 = document.getElementById('wave2');
    if (wave1) {
        wave1.setAttribute('transform', `translate(0, ${-60 + (percentage / 100) * 200})`);
    }
    if (wave2) {
        wave2.setAttribute('transform', `translate(0, ${-60 + (percentage / 100) * 200})`);
    }

    // Update percentage overlay
    const percentageOverlay = document.querySelector('#water-percentage span');
    if (percentageOverlay) {
        percentageOverlay.textContent = `${Math.round(percentage)}%`;
    }
}

function formatTimeSimple(time) {
    if (!time) return '--:--';
    const [hours, minutes] = String(time).split(':');
    const hour = parseInt(hours || '0', 10);
    const hour12 = hour % 12 || 12;
    const ampm = hour >= 12 ? 'PM' : 'AM';
    return `${hour12}:${minutes} ${ampm}`;
}

function updateWaterMissedStatus(trackingData) {
    const container = document.getElementById('water-missed-status');
    const missedCountEl = document.getElementById('water-missed-count');
    const nextDueEl = document.getElementById('water-next-due');
    if (!container || !missedCountEl || !nextDueEl) return;

    const missedCount = Array.isArray(trackingData?.missedReminders)
        ? trackingData.missedReminders.length
        : (Number(trackingData?.missed) || 0);

    if (missedCount <= 0) {
        container.classList.add('hidden');
        return;
    }

    let nextDueText = 'Next due: none remaining today';
    const schedule = trackingData?.activePlan?.schedule || [];
    if (Array.isArray(schedule) && schedule.length > 0) {
        const now = new Date();
        const nextPending = schedule
            .filter(item => item && !item.completed && !item.missed && item.time)
            .sort((a, b) => String(a.time).localeCompare(String(b.time)))
            .find(item => {
                const [h, m] = String(item.time).split(':');
                const due = new Date();
                due.setHours(parseInt(h || '0', 10), parseInt(m || '0', 10), 0, 0);
                return due >= now;
            });
        if (nextPending) {
            nextDueText = `Next due: ${formatTimeSimple(nextPending.time)}`;
        }
    }

    missedCountEl.textContent = `Missed ${missedCount} reminder(s) today`;
    nextDueEl.textContent = nextDueText;
    container.classList.remove('hidden');
}

function showWaterSettings() {
    document.getElementById('water-settings-modal').classList.remove('hidden');
    document.getElementById('water-settings-modal').classList.add('flex');
    updateNotificationStatus();
}

function closeWaterSettings() {
    document.getElementById('water-settings-modal').classList.add('hidden');
    document.getElementById('water-settings-modal').classList.remove('flex');
}

function testWaterNotification() {
    const permission = checkNotificationPermission();

    if (permission === 'unsupported') {
        showToast('Notifications are not supported in your browser', 'error');
        return;
    }

    if (permission !== 'granted') {
        requestNotificationPermission().then(result => {
            if (result === 'granted') {
                sendNotification('💧 Water Reminder Enabled!', {
                    body: 'You will receive reminders to drink water.',
                    icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233B82F6"><path d="M12 2c-5.33 4.55-8 8.48-8 11.8 0 4.98 3.8 9.2 8 9.2s8-4.22 8-9.2c0-3.32-2.67-7.25-8-11.8zm0 18c-3.35 0-6-2.57-6-6.2 0-2.62 1.8-5.2 4.8-7.2 1.5 1.3 3.3 2.4 5.2 3.1V20z"/></svg>'
                });
                showToast('Notifications enabled!', 'success');
            } else {
                showToast('Notification permission denied', 'error');
            }
            updateNotificationStatus();
        });
        return;
    }

    sendNotification('💧 Stay Hydrated!', {
        body: 'Remember to drink water regularly throughout the day.',
        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233B82F6"><path d="M12 2c-5.33 4.55-8 8.48-8 11.8 0 4.98 3.8 9.2 8 9.2s8-4.22 8-9.2c0-3.32-2.67-7.25-8-11.8zm0 18c-3.35 0-6-2.57-6-6.2 0-2.62 1.8-5.2 4.8-7.2 1.5 1.3 3.3 2.4 5.2 3.1V20z"/></svg>'
    });
    showToast('Test notification sent!', 'success');
}

function updateNotificationStatus() {
    const statusEl = document.getElementById('notification-status');
    if (!statusEl) return;

    const permission = checkNotificationPermission();
    switch (permission) {
        case 'granted':
            statusEl.textContent = '✓ Notifications enabled';
            statusEl.className = 'text-sm text-green-600';
            break;
        case 'denied':
            statusEl.textContent = '✗ Notifications blocked - please enable in browser settings';
            statusEl.className = 'text-sm text-red-600';
            break;
        default:
            statusEl.textContent = '⚠ Notifications not requested';
            statusEl.className = 'text-sm text-yellow-600';
    }
}

async function saveWaterSettings() {
    const goalLiters = parseFloat(document.getElementById('water-goal-input').value);
    const reminderInterval = parseInt(document.getElementById('water-reminder-input').value);
    const notificationsEnabled = document.getElementById('water-notifications-enabled').checked;
    const goalMl = Math.max(500, Math.round((Number(goalLiters) || 2) * 1000));
    const glassesGoal = Math.max(1, Math.round(goalMl / (window.waterGlassSizeMl || 250)));

    try {
        const response = await api.post('api/habits.php?action=set_water_goal', {
            goal: glassesGoal,
            reminderInterval: reminderInterval,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast('Water settings saved!', 'success');
            closeWaterSettings();
            window.waterGoalMl = goalMl;
            updateWaterDisplay(
                Number(response.data?.intakeMl ?? 0),
                goalMl
            );

            localStorage.setItem('waterTracker', JSON.stringify({
                notificationsEnabled,
                reminderInterval,
                goalMl
            }));

            if (notificationsEnabled && 'Notification' in window) {
                const permission = await requestNotificationPermission();
                if (permission === 'granted') {
                    startWaterReminder();
                }
            }
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
            sendNotification('💧 Drink Water!', {
                body: 'Stay hydrated! Time for a glass of water.',
                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%233B82F6"><path d="M12 2c-5.33 4.55-8 8.48-8 11.8 0 4.98 3.8 9.2 8 9.2s8-4.22 8-9.2c0-3.32-2.67-7.25-8-11.8zm0 18c-3.35 0-6-2.57-6-6.2 0-2.62 1.8-5.2 4.8-7.2 1.5 1.3 3.3 2.4 5.2 3.1V20z"/></svg>'
            });
        }
    }, intervalMs);
}
</script>

