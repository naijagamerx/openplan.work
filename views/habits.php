<?php
$db = new Database(getMasterPassword());
$habits = $db->load('habits');
$completions = $db->load('habit_completions');

$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

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

    $completedDates = array_filter(array_column($habitCompletions, 'date'), fn($d) => isset($d));
    $habits[$key]['completedDates'] = array_values($completedDates);
}

$habits = array_values($habits);
$categories = array_unique(array_column($habits, 'category'));
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Habit Tracker</h2>
            <p class="text-gray-500 font-medium tracking-tight">Build better daily routines</p>
        </div>
        <a href="?page=habit-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Habit
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Total Habits</p>
            <p class="text-4xl font-bold text-gray-900 mt-2"><?php echo count($habits); ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Completed Today</p>
            <p class="text-4xl font-bold text-green-600 mt-2"><?php echo count(array_filter($habits, fn($h) => $h['todayCompleted'])); ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Streak Days</p>
            <p class="text-4xl font-bold text-blue-600 mt-2"><?php echo calculateStreak($completions); ?></p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Today's Habits -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="p-5 border-b border-gray-200">
                <h3 class="font-bold text-gray-900">Today's Habits</h3>
                <p class="text-sm text-gray-500"><?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="p-5 space-y-3">
                <?php if (empty($habits)): ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-500 mt-3">No habits yet</p>
                        <a href="?page=habit-form" class="inline-block mt-3 text-sm font-medium text-black hover:underline">Create your first habit →</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($habits as $habit): ?>
                        <div class="habit-item flex items-center gap-3 p-4 rounded-xl border border-gray-100 hover:border-gray-200 transition group"
                             data-id="<?php echo e($habit['id']); ?>"
                             data-completed="<?php echo $habit['todayCompleted'] ? 'true' : 'false'; ?>">
                            <div class="flex-shrink-0">
                                <input type="checkbox"
                                       class="w-6 h-6 rounded-lg border-2 border-gray-200 text-black focus:ring-black cursor-pointer transition-all"
                                       <?php echo $habit['todayCompleted'] ? 'checked' : ''; ?>
                                       onchange="toggleHabit('<?php echo e($habit['id']); ?>', this.checked)">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-gray-900 text-sm <?php echo $habit['todayCompleted'] ? 'line-through text-gray-400' : ''; ?>">
                                    <?php echo e($habit['name']); ?>
                                </p>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                    <?php echo ucfirst($habit['category']); ?> • <?php echo $habit['completionCount']; ?> days
                                </p>
                            </div>
                            <?php if (!empty($habit['reminderTime'])): ?>
                                <div class="hidden sm:block text-xs font-bold text-gray-400">
                                    <?php echo date('g:i A', strtotime($habit['reminderTime'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="?page=habit-form&id=<?php echo e($habit['id']); ?>" class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-black hover:border-black transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </a>
                                <button onclick="deleteHabit('<?php echo e($habit['id']); ?>')" class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-100 transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calendar -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="p-5 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-bold text-gray-900">Progress Calendar</h3>
                <div class="flex items-center gap-2">
                    <button onclick="changeMonth(-1)" class="p-1 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                    </button>
                    <span id="calendar-month" class="text-sm font-bold text-gray-700 min-w-[120px] text-center"></span>
                    <button onclick="changeMonth(1)" class="p-1 hover:bg-gray-100 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>
            <div class="p-5">
                <div id="calendar-grid" class="grid grid-cols-7 gap-1"></div>
                <div class="mt-4 flex items-center gap-4 text-xs font-bold text-gray-500">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        <span>Completed</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-3 rounded-full bg-gray-200"></div>
                        <span>Missed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let habitsData = <?php echo json_encode($habits); ?>;
let currentDate = new Date(<?php echo $currentYear; ?>, <?php echo $currentMonth - 1; ?>, 1);

function calculateStreak(completions) {
    if (!completions || completions.length === 0) return 0;

    const dates = [...new Set(completions.map(c => c.date))].sort().reverse();
    let streak = 0;
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    for (const dateStr of dates) {
        const checkDate = new Date(dateStr);
        const diffDays = Math.floor((today - checkDate) / (1000 * 60 * 60 * 24));

        if (diffDays <= 1) {
            streak++;
            today.setDate(today.getDate() - 1);
        } else {
            break;
        }
    }

    return streak;
}

async function toggleHabit(habitId, completed) {
    const today = new Date().toISOString().split('T')[0];

    const response = await api.post('api/habits.php?action=complete', {
        habitId: habitId,
        date: today,
        status: completed ? 'complete' : 'missed',
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        showToast(completed ? 'Habit completed!' : 'Habit reopened', 'success');

        const habitItem = document.querySelector(`[data-id="${habitId}"]`);
        const habitName = habitItem.querySelector('p').textContent;

        habitsData = habitsData.map(h => {
            if (h.id === habitId) {
                h.todayCompleted = completed;
                if (completed) {
                    h.completedDates.push(today);
                } else {
                    h.completedDates = h.completedDates.filter(d => d !== today);
                }
            }
            return h;
        });

        habitItem.dataset.completed = completed;
        habitItem.querySelector('p').classList.toggle('line-through', completed);
        habitItem.querySelector('p').classList.toggle('text-gray-400', completed);

        renderCalendar();
    }
}

async function deleteHabit(habitId) {
    if (!confirm('Are you sure you want to delete this habit?')) return;

    const response = await api.delete('api/habits.php?id=' + habitId);

    if (response.success) {
        showToast('Habit deleted', 'success');
        habitsData = habitsData.filter(h => h.id !== habitId);
        document.querySelector(`[data-id="${habitId}"]`).remove();
        renderCalendar();
    }
}

function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    const monthLabel = document.getElementById('calendar-month');

    grid.innerHTML = '';

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                      'July', 'August', 'September', 'October', 'November', 'December'];
    monthLabel.textContent = `${monthNames[month]} ${year}`;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    dayHeaders.forEach(day => {
        const header = document.createElement('div');
        header.className = 'text-center text-[10px] font-bold text-gray-400 uppercase tracking-widest py-2';
        header.textContent = day;
        grid.appendChild(header);
    });

    for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'p-2';
        grid.appendChild(empty);
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayCell = document.createElement('div');

        let completedCount = 0;
        habitsData.forEach(habit => {
            if (habit.completedDates.includes(dateStr)) {
                completedCount++;
            }
        });

        const completionPercent = habitsData.length > 0 ? (completedCount / habitsData.length) * 100 : 0;
        const isToday = today.getDate() === day && today.getMonth() === month && today.getFullYear() === year;

        let bgColor = 'bg-gray-100';
        if (completionPercent === 100) bgColor = 'bg-green-500';
        else if (completionPercent >= 75) bgColor = 'bg-green-400';
        else if (completionPercent >= 50) bgColor = 'bg-green-300';
        else if (completionPercent >= 25) bgColor = 'bg-green-200';

        dayCell.className = `p-2 text-center rounded-lg text-sm font-bold cursor-pointer hover:scale-105 transition-all ${bgColor} ${isToday ? 'ring-2 ring-black' : ''}`;
        dayCell.textContent = day;
        dayCell.title = `${completedCount}/${habitsData.length} habits completed`;

        grid.appendChild(dayCell);
    }
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

document.addEventListener('DOMContentLoaded', () => {
    renderCalendar();
    initializeReminderCheck();
});
</script>

<?php
function calculateStreak($completions) {
    if (empty($completions)) return 0;

    $dates = array_unique(array_column($completions, 'date'));
    rsort($dates);

    $streak = 0;
    $checkDate = new DateTime();
    $checkDate->setTime(0, 0, 0);

    foreach ($dates as $dateStr) {
        $compDate = new DateTime($dateStr);
        $interval = $checkDate->diff($compDate);

        if ($interval->days <= 1) {
            $streak++;
            $checkDate->modify('-1 day');
        } else {
            break;
        }
    }

    return $streak;
}
?>
