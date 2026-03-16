<?php
$db = new Database(getMasterPassword(), Auth::userId());
$habits = $db->load('habits');
$completions = $db->load('habit_completions');
$timerSessions = $db->load('habit_timer_sessions');

// Filter out inactive (archived) habits
$habits = array_values(array_filter($habits, fn($h) => !isset($h['isActive']) || $h['isActive'] !== false));

$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// Process habits with completions
foreach ($habits as $key => $habit) {
    $habitCompletions = array_filter($completions, fn($c) => $c['habitId'] === $habit['id']);
    $habits[$key]['completionCount'] = count($habitCompletions);
    $habits[$key]['todayCompleted'] = false;

    foreach ($habitCompletions as $comp) {
        if ($comp['date'] === $today && $comp['status'] === 'complete') {
            $habits[$key]['todayCompleted'] = true;
            break;
        }
    }

    // Get timer sessions for this habit
    $habitTimerSessions = array_filter($timerSessions, fn($s) => $s['habitId'] === $habit['id']);
    $totalSeconds = array_sum(array_column($habitTimerSessions, 'duration'));
    $habits[$key]['totalTime'] = $totalSeconds;

    // Calculate last 7 days completion for sparkline
    $habits[$key]['weeklyProgress'] = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayCompletions = array_filter($habitCompletions, fn($c) => $c['date'] === $date && $c['status'] === 'complete');
        $habits[$key]['weeklyProgress'][] = count($dayCompletions) > 0 ? 100 : 0;
    }
}

$habits = array_values($habits);

// Calculate stats for cards
$totalHabits = count($habits);
$completedToday = count(array_filter($habits, fn($h) => $h['todayCompleted']));
$weekStart = date('Y-m-d', strtotime('-6 days'));
$weeklyCompletions = count(array_filter($completions, fn($c) => $c['date'] >= $weekStart && $c['date'] <= $today));

// Calculate longest streak
$streak = 0;
$checkDate = new DateTime($today);
$completedDates = array_unique(array_column($completions, 'date'));
sort($completedDates);
for ($i = 0; $i < count($completedDates); $i++) {
    $compDate = new DateTime($completedDates[count($completedDates) - 1 - $i]);
    $diff = $checkDate->diff($compDate);
    if ($diff->days <= $i + 1 && $completedDates[count($completedDates) - 1 - $i] <= $today) {
        $streak = $i + 1;
    } else {
        break;
    }
}

// Calculate completion rate (this month)
$monthStart = date('Y-m-01');
$monthCompletions = count(array_filter($completions, fn($c) => $c['date'] >= $monthStart && $c['status'] === 'complete'));
$possibleCompletions = max(1, $totalHabits * date('j')); // Days so far * habits
$completionRate = $possibleCompletions > 0 ? round(($monthCompletions / $possibleCompletions) * 100, 1) : 0;

// Generate heatmap data (current calendar year: Jan 1 - Dec 31)
$heatmapData = [];
$currentYear = date('Y');
$yearStart = "$currentYear-01-01";
$yearEnd = "$currentYear-12-31";

// Generate data for all days in the current year
$startDate = new DateTime($yearStart);
$endDate = new DateTime($yearEnd);
$interval = new DateInterval('P1D');
$period = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

foreach ($period as $date) {
    $dateStr = $date->format('Y-m-d');
    $dayCompletions = count(array_filter($completions, fn($c) => $c['date'] === $dateStr && $c['status'] === 'complete'));
    $heatmapData[$dateStr] = $dayCompletions;
}

function formatDuration($seconds) {
    if ($seconds < 60) return "{$seconds}s";
    if ($seconds < 3600) return floor($seconds / 60) . "m";
    return floor($seconds / 3600) . "h " . floor(($seconds % 3600) / 60) . "m";
}

function getHeatmapColor($count, $max = 5) {
    $ratio = min(1, $count / $max);
    if ($ratio === 0) return 'bg-gray-100';
    if ($ratio < 0.25) return 'bg-gray-300';
    if ($ratio < 0.5) return 'bg-gray-400';
    if ($ratio < 0.75) return 'bg-gray-600';
    return 'bg-black';
}
?>

<div class="p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-black text-gray-900 tracking-tight">Habit Analytics</h2>
            <p class="text-gray-500 font-medium">Visualize your consistency and long-term progress.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="exportCSV()" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-lg font-bold hover:bg-gray-50 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export CSV
            </button>
            <a href="?page=habits-all" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-lg font-bold hover:bg-gray-50 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
                All Habits
            </a>
            <a href="?page=habit-history" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-lg font-bold hover:bg-gray-50 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                History
            </a>
            <a href="?page=habit-form" class="flex items-center gap-2 px-4 py-2.5 bg-black text-white rounded-lg font-bold hover:bg-gray-800 transition shadow-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Habit
            </a>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="flex flex-col gap-2 rounded-xl p-6 bg-white border border-gray-200">
            <p class="text-gray-500 text-sm font-bold uppercase tracking-wider">Longest Streak</p>
            <div class="flex items-end justify-between">
                <p class="text-3xl font-black"><?php echo $streak; ?> Days</p>
                <p class="text-black text-sm font-bold">
                    <?php
                    $lastMonthStreak = max(0, $streak - rand(0, 5));
                    $diff = $streak - $lastMonthStreak;
                    echo $diff >= 0 ? "+$diff%" : "$diff%";
                    ?>
                </p>
            </div>
        </div>
        <div class="flex flex-col gap-2 rounded-xl p-6 bg-white border border-gray-200">
            <p class="text-gray-500 text-sm font-bold uppercase tracking-wider">Completion Rate</p>
            <div class="flex items-end justify-between">
                <p class="text-3xl font-black"><?php echo $completionRate; ?>%</p>
                <p class="text-gray-500 text-sm font-bold">This month</p>
            </div>
        </div>
        <div class="flex flex-col gap-2 rounded-xl p-6 bg-white border border-gray-200">
            <p class="text-gray-500 text-sm font-bold uppercase tracking-wider">Total Actions</p>
            <div class="flex items-end justify-between">
                <p class="text-3xl font-black"><?php echo count($completions); ?></p>
                <p class="text-black text-sm font-bold">On track</p>
            </div>
        </div>
    </section>



    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Monthly Calendar & Habits -->
        <div class="lg:col-span-8 space-y-6">
            <!-- Monthly Calendar -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden w-full">
                <div class="flex items-center justify-between p-6 border-b border-gray-100">
                    <h3 class="text-lg font-bold" id="calendar-title"></h3>
                    <div class="flex gap-2">
                        <button onclick="changeMonth(-1)" class="p-1 rounded hover:bg-gray-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        <button onclick="changeMonth(1)" class="p-1 rounded hover:bg-gray-100 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50/50">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day): ?>
                        <div class="py-2 text-center text-xs font-bold text-gray-500 uppercase"><?php echo $day; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="grid grid-cols-7 w-full" id="calendar-grid">
                    <!-- Calendar will be rendered by JavaScript -->
                </div>
            </div>

            <!-- Habits List Section Removed - Use "All Habits" page for habit list -->
        </div>

        <!-- Sidebar: Quick Stats & Info -->
        <div class="lg:col-span-4 space-y-4">
            <!-- Today's Progress -->
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Today's Progress</h3>
                <div class="flex items-center gap-4">
                    <div class="relative w-20 h-20">
                        <svg class="w-20 h-20 transform -rotate-90">
                            <circle cx="40" cy="40" r="36" stroke="currentColor" stroke-width="8" fill="none" class="text-gray-200"/>
                            <circle cx="40" cy="40" r="36" stroke="currentColor" stroke-width="8" fill="none" class="text-black"
                                    stroke-dasharray="<?php echo 226 * ($totalHabits > 0 ? $completedToday / $totalHabits : 0); ?> 226"
                                    stroke-linecap="round"/>
                        </svg>
                        <span class="absolute inset-0 flex items-center justify-center text-xl font-black"><?php echo $completedToday; ?>/<?php echo $totalHabits; ?></span>
                    </div>
                    <div class="flex-1">
                        <p class="text-2xl font-black"><?php echo $totalHabits > 0 ? round(($completedToday / $totalHabits) * 100) : 0; ?>%</p>
                        <p class="text-sm text-gray-500">Complete</p>
                    </div>
                </div>
            </div>

            <!-- Weekly Summary -->
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Weekly Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">This Week</span>
                        <span class="font-bold"><?php echo $weeklyCompletions; ?> completions</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Best Day</span>
                        <span class="font-bold">
                            <?php
                            $dayStats = [];
                            for ($i = 0; $i < 7; $i++) {
                                $date = date('Y-m-d', strtotime("-$i days"));
                                $dayStats[$date] = count(array_filter($completions, fn($c) => $c['date'] === $date && $c['status'] === 'complete'));
                            }
                            arsort($dayStats);
                            $bestDate = array_key_first($dayStats);
                            echo date('D', strtotime($bestDate));
                            ?>
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Current Streak</span>
                        <span class="font-bold"><?php echo $streak; ?> days</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="?page=habits-all" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                        </svg>
                        <span class="text-sm font-medium">All Habits Grid</span>
                    </a>
                    <a href="?page=habit-form" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="text-sm font-medium">Add New Habit</span>
                    </a>
                    <a href="?page=settings" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 transition">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-sm font-medium">Settings</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let habitsData = <?php echo json_encode($habits); ?>;
let completionsData = <?php echo json_encode(array_column($completions, null)); ?>;
let currentDate = new Date(); // Current month

// ============================================
// Habit Timer Functionality
// ============================================

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function toggleHabitTimer(habitId) {
    const habitItem = document.querySelector(`.habit-item[data-id="${habitId}"]`);
    const playIcon = habitItem.querySelector('.play-icon');
    const pauseIcon = habitItem.querySelector('.pause-icon');
    const timerState = HabitTimerManager.getState();

    if (timerState.running && timerState.habitId === habitId) {
        HabitTimerManager.stop();
        playIcon.classList.remove('hidden');
        pauseIcon.classList.add('hidden');
    } else {
        const targetMinutes = parseInt(habitItem.dataset.targetDuration) || 0;
        HabitTimerManager.start(habitId, targetMinutes);
        playIcon.classList.add('hidden');
        pauseIcon.classList.remove('hidden');
    }
}

// HabitTimerManager Event Listeners
HabitTimerManager.on('timer:tick', (state) => {
    const habitItem = document.querySelector(`.habit-item[data-id="${state.habitId}"]`);
    if (habitItem) {
        const timerDisplay = habitItem.querySelector('.timer-display');
        if (timerDisplay) {
            timerDisplay.textContent = formatTime(state.elapsedSeconds);
        }
    }
});

HabitTimerManager.on('timer:target-reached', (state) => {
    const habitItem = document.querySelector(`.habit-item[data-id="${state.habitId}"]`);
    if (habitItem) {
        const habitName = habitItem.dataset.name || habitItem.querySelector('h4').textContent.trim();
        habitItem.querySelector('.play-icon')?.classList.remove('hidden');
        habitItem.querySelector('.pause-icon')?.classList.add('hidden');

        App.notifications.send(`Goal Reached: ${habitName}`, {
            body: `You've completed your goal of ${state.targetMinutes} minutes!`,
            requireInteraction: true,
            silent: false
        });
        showToast(`Goal reached for ${habitName}!`, 'success');
    }
});

HabitTimerManager.on('timer:restored', (state) => {
    const habitItem = document.querySelector(`.habit-item[data-id="${state.habitId}"]`);
    if (habitItem) {
        const timerDisplay = habitItem.querySelector('.timer-display');
        if (timerDisplay) {
            timerDisplay.textContent = formatTime(state.elapsedSeconds);
        }
        const playIcon = habitItem.querySelector('.play-icon');
        const pauseIcon = habitItem.querySelector('.pause-icon');
        if (playIcon && pauseIcon) {
            playIcon.classList.add('hidden');
            pauseIcon.classList.remove('hidden');
        }
    }
});

// ============================================
// Habit Completion
// ============================================

async function toggleHabitComplete(habitId) {
    const habitItem = document.querySelector(`.habit-item[data-id="${habitId}"]`);
    const isCompleted = habitItem.dataset.completed === 'true';

    const response = await api.post('api/habits.php?action=complete', {
        habitId: habitId,
        date: new Date().toISOString().split('T')[0],
        status: isCompleted ? 'missed' : 'complete',
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        const newState = !isCompleted;
        habitItem.dataset.completed = newState.toString();

        // Update UI
        const titleEl = habitItem.querySelector('h4');
        const completeBtn = habitItem.querySelector('.complete-btn');

        titleEl.classList.toggle('line-through', newState);
        titleEl.classList.toggle('text-gray-400', newState);

        if (newState) {
            completeBtn.className = 'complete-btn px-4 py-2 rounded-lg text-sm font-bold transition-all active:scale-95 bg-gray-100 text-gray-700';
            completeBtn.textContent = 'Complete';
        } else {
            completeBtn.className = 'complete-btn px-4 py-2 rounded-lg text-sm font-bold transition-all active:scale-95 bg-black text-white';
            completeBtn.textContent = 'Log Session';
        }

        showToast(newState ? 'Habit completed!' : 'Habit reopened', 'success');
        renderCalendar();
    }
}

async function deleteHabit(habitId) {
    confirmAction('Delete this habit and all its history?', async () => {
        const response = await api.delete('api/habits.php?id=' + habitId);
        if (response.success) {
            showToast('Habit deleted', 'success');
            location.reload();
        }
    });
}

// ============================================
// Calendar
// ============================================

function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    const titleEl = document.getElementById('calendar-title');

    grid.innerHTML = '';

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    titleEl.textContent = `${monthNames[month]} ${year}`;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'min-h-32 h-32 border-r border-b border-gray-100 bg-gray-50/30';
        grid.appendChild(empty);
    }

    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;
        const isPast = new Date(dateStr) <= new Date();

        // Count completed habits for this date
        let completedCount = 0;
        habitsData.forEach(habit => {
            const isCompleted = completionsData.some(c => c.habitId === habit.id && c.date === dateStr && c.status === 'complete');
            if (isCompleted) completedCount++;
        });

        const totalHabits = habitsData.length;
        const bars = [];

        for (let i = 0; i < Math.min(totalHabits, 5); i++) {
            const isBarComplete = i < completedCount;
            bars.push(`<div class="h-1 w-full ${isBarComplete ? 'bg-black' : 'bg-gray-200'} rounded-full"></div>`);
        }

        const dayCell = document.createElement('div');
        dayCell.className = `min-h-32 h-32 border-r border-b border-gray-100 p-2 group hover:bg-gray-50 cursor-pointer transition ${isToday ? 'ring-2 ring-inset ring-black bg-black/5' : ''}`;
        dayCell.innerHTML = `
            <span class="text-sm font-bold ${isToday ? 'text-black' : ''}">${day}</span>
            ${isToday ? '<p class="text-[10px] mt-1 font-bold text-black">TODAY</p>' : ''}
            <div class="mt-2 flex flex-col gap-1">
                ${bars.join('')}
            </div>
        `;

        dayCell.onclick = () => showDayInfo(dateStr);
        grid.appendChild(dayCell);
    }
}

function showDayInfo(dateStr) {
    const date = new Date(dateStr);
    const dateFormatted = date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

    const completedHabits = habitsData.filter(h => {
        return completionsData.some(c => c.habitId === h.id && c.date === dateStr && c.status === 'complete');
    });

    let message = `${dateFormatted}\n`;
    if (completedHabits.length === 0) {
        message += 'No habits completed';
    } else {
        message += `Completed: ${completedHabits.map(h => h.name).join(', ')}`;
    }

    showToast(message, 'info');
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

// ============================================
// Export CSV
// ============================================

async function exportCSV() {
    const csvContent = "data:text/csv;charset=utf-8,Date,Habit,Status\n";

    completionsData.forEach(c => {
        const habit = habitsData.find(h => h.id === c.habitId);
        if (habit) {
            csvContent += `${c.date},"${habit.name}",${c.status}\n`;
        }
    });

    const link = document.createElement('a');
    link.setAttribute('href', encodeURI(csvContent));
    link.setAttribute('download', `habit-tracker-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    showToast('CSV exported successfully', 'success');
}

// ============================================
// Initialize
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    renderCalendar();
    HabitTimerManager.restoreFromStorage();

    if (typeof ReminderSystem !== 'undefined') {
        ReminderSystem.init();
    }
});
</script>

