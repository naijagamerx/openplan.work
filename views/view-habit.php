<?php
// View Habit Page
$db = new Database(getMasterPassword(), Auth::userId());

// Get habit ID from URL
$habitId = $_GET['id'] ?? null;

if (!$habitId) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Habit not found</div></div>';
    return;
}

// Find the habit
$habits = $db->load('habits');
$habit = null;

foreach ($habits as $h) {
    if ($h['id'] === $habitId) {
        $habit = $h;
        break;
    }
}

if (!$habit) {
    echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Habit not found</div></div>';
    return;
}

// Get completions and timer sessions
$completions = $db->load('habit_completions');
$timerSessions = $db->load('habit_timer_sessions');

$habitCompletions = array_filter($completions, fn($c) => $c['habitId'] === $habitId);
$habitTimerSessions = array_filter($timerSessions, fn($s) => $s['habitId'] === $habitId);

// Sort completions by date descending
usort($habitCompletions, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

// Sort timer sessions by date descending
usort($habitTimerSessions, fn($a, $b) => strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));

// Calculate statistics
$totalDays = count($habitCompletions);
$today = date('Y-m-d');
$todayCompleted = in_array($today, array_column($habitCompletions, 'date'));

// Calculate current streak
$streak = 0;
$checkDate = new DateTime($today);
for ($i = 0; $i < 365; $i++) {
    $checkDateStr = $checkDate->format('Y-m-d');
    $hasCompletion = in_array($checkDateStr, array_column($habitCompletions, 'date'));
    if ($hasCompletion) {
        $streak++;
        $checkDate->modify('-1 day');
    } else {
        // Allow today to be skipped if not yet completed
        if ($checkDateStr === $today && !$todayCompleted) {
            $checkDate->modify('-1 day');
            continue;
        }
        break;
    }
}

// Calculate total time
$totalSeconds = array_sum(array_column($habitTimerSessions, 'duration'));

// Define formatDuration if not already defined (to avoid conflicts with habits.php)
if (!function_exists('formatDuration')) {
    function formatDuration($seconds) {
        if ($seconds < 60) return "{$seconds} sec";
        if ($seconds < 3600) return floor($seconds / 60) . " min";
        $hours = floor($seconds / 3600);
        $mins = floor(($seconds % 3600) / 60);
        return "{$hours}h {$mins}m";
    }
}

// Group completions by month for chart
$monthlyCompletions = [];
foreach ($habitCompletions as $comp) {
    $month = substr($comp['date'] ?? '', 0, 7);
    if (!isset($monthlyCompletions[$month])) {
        $monthlyCompletions[$month] = 0;
    }
    $monthlyCompletions[$month]++;
}

// Get last 30 days for activity chart
$activityData = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $activityData[$date] = in_array($date, array_column($habitCompletions, 'date')) ? 1 : 0;
}
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=habits" class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900"><?php echo e($habit['name']); ?></h1>
                <?php if ($todayCompleted): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold uppercase rounded-full">Completed Today</span>
                <?php endif; ?>
            </div>
            <p class="text-gray-500 mt-1"><?php echo ucfirst($habit['category'] ?? 'General'); ?> • Created <?php echo isset($habit['createdAt']) ? date('M j, Y', strtotime($habit['createdAt'])) : 'Unknown'; ?></p>
        </div>
        <div class="flex items-center gap-2">
            <a href="?page=habit-form&id=<?php echo e($habitId); ?>" class="flex items-center gap-2 px-4 py-2.5 bg-black text-white rounded-xl font-bold text-sm hover:bg-gray-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                </svg>
                Edit
            </a>
            <button id="archive-toggle-btn" onclick="toggleHabitArchive()" class="flex items-center gap-2 px-4 py-2.5 border <?php echo (isset($habit['isActive']) && $habit['isActive'] === false) ? 'border-green-500 text-green-600 bg-green-50 hover:bg-green-100' : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50'; ?> rounded-xl font-bold text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?php if (isset($habit['isActive']) && $habit['isActive'] === false): ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    <?php else: ?>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                    <?php endif; ?>
                </svg>
                <span id="archive-toggle-text"><?php echo (isset($habit['isActive']) && $habit['isActive'] === false) ? 'Activate' : 'Archive'; ?></span>
            </button>
            <button onclick="confirmAction('Delete this habit and all its history?', async () => {
                const response = await api.delete('api/habits.php?id=<?php echo e($habitId); ?>');
                if (response.success) {
                    showToast('Habit deleted', 'success');
                    window.location.href = '?page=habits';
                }
            })" class="flex items-center gap-2 px-4 py-2.5 bg-red-50 border border-red-200 text-red-600 rounded-xl font-bold text-sm hover:bg-red-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete
            </button>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Total Days</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalDays; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Current Streak</p>
            <p class="text-3xl font-bold text-green-600 mt-1"><?php echo $streak; ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Total Time</p>
            <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo formatDuration($totalSeconds); ?></p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Best Streak</p>
            <p class="text-3xl font-bold text-purple-600 mt-1">
                <?php
                $bestStreak = 0;
                $currentRun = 0;
                $sortedDates = array_unique(array_column($habitCompletions, 'date'));
                sort($sortedDates);
                $check = new DateTime();
                foreach ($sortedDates as $date) {
                    $d = new DateTime($date);
                    $diff = $check->diff($d);
                    if ($diff->days <= 1 && $currentRun > $bestStreak) {
                        $bestStreak = $currentRun;
                    }
                    $currentRun++;
                    $check = $d->modify('-1 day');
                }
                echo max($bestStreak, $currentRun);
                ?>
            </p>
        </div>
    </div>

    <!-- Quick Action & Timer -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h2 class="font-bold text-gray-900 mb-4">Quick Action</h2>
        <div class="flex items-center gap-4">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox"
                       id="todayCompletion"
                       class="w-6 h-6 rounded-lg border-2 border-gray-300 text-black focus:ring-black cursor-pointer"
                       <?php echo $todayCompleted ? 'checked' : ''; ?>
                       onchange="toggleTodayCompletion()">
                <span class="font-medium text-gray-900">Mark as <?php echo $todayCompleted ? 'not completed' : 'completed'; ?> today</span>
            </label>
            <?php if (!empty($habit['targetDuration'])): ?>
                <div class="flex items-center gap-2 ml-6 px-4 py-2 bg-gray-50 rounded-lg">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm text-gray-600">Target: <?php echo $habit['targetDuration']; ?> min/day</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Timer Section -->
        <div class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">Timer:</span>
                <span id="habit-timer-display" class="text-2xl font-mono font-bold text-gray-900">00:00</span>
                <button onclick="toggleHabitTimerOnView()" id="habit-timer-btn" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition text-sm">
                    Start
                </button>
            </div>
        </div>
    </div>

    <!-- Activity Chart (Last 30 Days) -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        <h2 class="font-bold text-gray-900 mb-4">Last 30 Days Activity</h2>
        <div class="flex items-end gap-1 h-16">
            <?php foreach ($activityData as $date => $active): ?>
                <div class="flex-1 <?php echo $active ? 'bg-green-500' : 'bg-gray-200'; ?> rounded-t transition-all hover:opacity-80"
                     title="<?php echo date('M j', strtotime($date)); ?>: <?php echo $active ? 'Completed' : 'Missed'; ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="flex justify-between text-xs text-gray-400 mt-2">
            <span><?php echo date('M j', strtotime('-29 days')); ?></span>
            <span>Today</span>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Completion History -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Recent Completions</h2>
            <?php if (empty($habitCompletions)): ?>
                <p class="text-gray-500 text-sm">No completion history yet</p>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach (array_slice($habitCompletions, 0, 20) as $comp): ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Completed</p>
                                <p class="text-xs text-gray-500"><?php echo date('l, F j, Y', strtotime($comp['date'] ?? 'now')); ?></p>
                            </div>
                            <?php if (!empty($comp['duration'])): ?>
                                <span class="text-xs text-gray-500"><?php echo formatDuration($comp['duration']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($habitCompletions) > 20): ?>
                    <p class="text-xs text-gray-400 mt-3 text-center">+<?php echo count($habitCompletions) - 20; ?> more</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Timer Sessions -->
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h2 class="font-bold text-gray-900 mb-4">Timer Sessions</h2>
            <?php if (empty($habitTimerSessions)): ?>
                <p class="text-gray-500 text-sm">No timer sessions recorded yet</p>
                <p class="text-xs text-gray-400 mt-2">Start the timer from the habits page to track time spent</p>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach (array_slice($habitTimerSessions, 0, 20) as $session): ?>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Timer Session</p>
                                <p class="text-xs text-gray-500"><?php echo isset($session['createdAt']) ? date('M j, Y g:i A', strtotime($session['createdAt'])) : 'Unknown'; ?></p>
                            </div>
                            <span class="text-sm font-bold text-blue-600"><?php echo formatDuration($session['duration'] ?? 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($habitTimerSessions) > 20): ?>
                    <p class="text-xs text-gray-400 mt-3 text-center">+<?php echo count($habitTimerSessions) - 20; ?> more sessions</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Habit Details -->
    <div class="bg-white rounded-xl border border-gray-200 p-6 mt-6">
        <h2 class="font-bold text-gray-900 mb-4">Habit Details</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <?php if (!empty($habit['reminderTime'])): ?>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 bg-amber-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-bold">Daily Reminder</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo date('g:i A', strtotime($habit['reminderTime'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($habit['targetDuration'])): ?>
                <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-bold">Target Duration</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo $habit['targetDuration']; ?> minutes per day</p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Completion Rate</p>
                    <p class="text-sm font-medium text-gray-900">
                        <?php
                        $daysSinceCreated = max(1, (time() - strtotime($habit['createdAt'] ?? 'now')) / 86400);
                        $rate = min(100, round(($totalDays / $daysSinceCreated) * 100));
                        echo $rate . '%';
                        ?>
                        <span class="text-gray-400 text-xs">(last 30 days)</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase font-bold">Created</p>
                    <p class="text-sm font-medium text-gray-900"><?php echo isset($habit['createdAt']) ? date('F j, Y', strtotime($habit['createdAt'])) : 'Unknown'; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Habit Timer using HabitTimerManager
const habitId = '<?php echo e($habitId); ?>';
const targetMinutes = <?php echo (int)($habit['targetDuration'] ?? 0); ?>;

function formatTimerDisplay(seconds) {
    const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
    const secs = (seconds % 60).toString().padStart(2, '0');
    return `${mins}:${secs}`;
}

function formatDuration(seconds) {
    if (seconds < 60) return `${seconds} sec`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min`;
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${mins}m`;
}

function updateTimerButtonState(running) {
    const btn = document.getElementById('habit-timer-btn');
    if (running) {
        btn.textContent = 'Stop';
        btn.classList.remove('bg-black');
        btn.classList.add('bg-red-500', 'hover:bg-red-600');
    } else {
        btn.textContent = 'Start';
        btn.classList.add('bg-black');
        btn.classList.remove('bg-red-500', 'hover:bg-red-600');
    }
}

async function toggleHabitTimerOnView() {
    const timerState = HabitTimerManager.getState();
    if (timerState.running && timerState.habitId === habitId) {
        await HabitTimerManager.stop();
    } else {
        await HabitTimerManager.start(habitId, targetMinutes);
    }
}

async function toggleTodayCompletion() {
    const checkbox = document.getElementById('todayCompletion');
    const completed = checkbox.checked;

    // Calculate duration if there's a running timer
    let duration = null;
    const timerState = HabitTimerManager.getState();
    if (completed && timerState.running && timerState.habitId === habitId) {
        duration = timerState.elapsedSeconds;
        await HabitTimerManager.stop();
        updateTimerButtonState(false);
    }

    const response = await api.post('api/habits.php?action=complete', {
        habitId: habitId,
        date: new Date().toISOString().split('T')[0],
        status: completed ? 'complete' : 'missed',
        duration: duration,
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        if (completed && duration) {
            showToast(`Habit marked as completed! Timer: ${formatDuration(duration)}`, 'success');
        } else {
            showToast(completed ? 'Habit marked as completed!' : 'Habit unmarked', 'success');
        }
        checkbox.checked = completed;
    } else {
        checkbox.checked = !completed;
        showToast('Failed to update habit', 'error');
    }
}

// ============================================
// HabitTimerManager Event Listeners
// ============================================

HabitTimerManager.on('timer:tick', (state) => {
    if (state.habitId === habitId) {
        const display = document.getElementById('habit-timer-display');
        if (display) {
            display.textContent = formatTimerDisplay(state.elapsedSeconds);
        }
    }
});

HabitTimerManager.on('timer:started', (state) => {
    if (state.habitId === habitId) {
        updateTimerButtonState(true);
        showToast('Timer started', 'success');
    }
});

HabitTimerManager.on('timer:target-reached', (state) => {
    if (state.habitId === habitId) {
        const habitName = '<?php echo e($habit['name']); ?>';
        updateTimerButtonState(false);

        if (typeof App !== 'undefined' && App.notifications) {
            App.notifications.send(`Goal Reached: ${habitName}`, {
                body: `You've completed your goal of ${targetMinutes} minutes!`,
                requireInteraction: true
            });
        }
    }
});

HabitTimerManager.on('timer:stopped', (data) => {
    updateTimerButtonState(false);
    if (data && data.duration) {
        showToast(`Timer stopped: ${formatDuration(data.duration)}`, 'success');
    }
});

HabitTimerManager.on('timer:restored', (state) => {
    if (state.habitId === habitId) {
        const display = document.getElementById('habit-timer-display');
        if (display) {
            display.textContent = formatTimerDisplay(state.elapsedSeconds);
        }
        updateTimerButtonState(true);
    }
});

// Toggle habit archive status
async function toggleHabitArchive() {
    const habitName = '<?php echo e($habit['name']); ?>';
    const isCurrentlyActive = <?php echo (isset($habit['isActive']) && $habit['isActive'] === false) ? 'false' : 'true'; ?>;
    const action = isCurrentlyActive ? 'activate' : 'archive';

    confirmAction(`${action === 'archive' ? 'Archive' : 'Activate'} "${habitName}"?`, async () => {
        const response = await api.post('api/habits.php?action=toggle_active', {
            habitId: habitId,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(response.message || `Habit ${action}d`, 'success');
            // Reload to see updated state
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(response.error || `Failed to ${action} habit`, 'error');
        }
    });
}

// Initialize timer on page load
document.addEventListener('DOMContentLoaded', () => {
    HabitTimerManager.restoreFromStorage();
});
</script>

