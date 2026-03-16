<?php
// All Habits Grid View - Stitch Design Integration
// Retrieved from Google Stitch MCP - Screen: a54dcd0f3fa44519b1ece915f050f2a6
$db = new Database(getMasterPassword(), Auth::userId());
$habits = $db->load('habits');
$completions = $db->load('habit_completions');
$timerSessions = $db->load('habit_timer_sessions');

$today = date('Y-m-d');

// Helper function to calculate per-habit streak
$calculateHabitStreak = function($habitId, $allCompletions, $today) {
    $habitCompletions = array_filter($allCompletions, fn($c) => $c['habitId'] === $habitId && $c['status'] === 'complete');
    $completedDates = array_unique(array_column($habitCompletions, 'date'));
    sort($completedDates);

    $streak = 0;
    $checkDate = new DateTime($today);
    for ($i = 0; $i < 365; $i++) {
        $checkDateStr = $checkDate->format('Y-m-d');
        $hasCompletion = in_array($checkDateStr, $completedDates);
        if ($hasCompletion) {
            $streak++;
            $checkDate->modify('-1 day');
        } else {
            if ($checkDateStr === $today) {
                $checkDate->modify('-1 day');
                continue;
            }
            break;
        }
    }
    return $streak;
};

// Helper function to calculate longest streak for a habit
$calculateLongestStreak = function($habitId, $allCompletions) {
    $habitCompletions = array_filter($allCompletions, fn($c) => $c['habitId'] === $habitId && $c['status'] === 'complete');
    $completedDates = array_unique(array_column($habitCompletions, 'date'));
    sort($completedDates);

    if (empty($completedDates)) {
        return 0;
    }

    $longestStreak = 1;
    $currentStreak = 1;

    for ($i = 1; $i < count($completedDates); $i++) {
        $prevDate = new DateTime($completedDates[$i - 1]);
        $currDate = new DateTime($completedDates[$i]);
        $diff = $currDate->diff($prevDate)->days;

        if ($diff === 1) {
            $currentStreak++;
        } else {
            $longestStreak = max($longestStreak, $currentStreak);
            $currentStreak = 1;
        }
    }

    return max($longestStreak, $currentStreak);
};

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

    // Calculate last 7 days completion for sparkline
    $habits[$key]['weeklyProgress'] = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayCompletions = array_filter($habitCompletions, fn($c) => $c['date'] === $date && $c['status'] === 'complete');
        $habits[$key]['weeklyProgress'][] = count($dayCompletions) > 0 ? 100 : 0;
    }

    // Add streak calculations
    $habits[$key]['currentStreak'] = $calculateHabitStreak($habit['id'], $completions, $today);
    $habits[$key]['longestStreak'] = $calculateLongestStreak($habit['id'], $completions);
}

$habits = array_values($habits);

// Filter out inactive habits (archived)
$habits = array_values(array_filter($habits, fn($h) => !isset($h['isActive']) || $h['isActive'] !== false));

// Calculate stats for cards
$totalHabits = count($habits);
$activeStreaks = count(array_filter($habits, fn($h) => ($h['currentStreak'] ?? 0) > 0));
$dailyCompletion = $totalHabits > 0 ? round((count(array_filter($habits, fn($h) => $h['todayCompleted'])) / $totalHabits) * 100) : 0;
$longestStreak = max(array_column($habits, 'longestStreak') ?: [0]);
?>

<!-- All Habits Grid View - Stitch Design -->
<div class="max-w-7xl mx-auto p-10 flex flex-col gap-10">
    <!-- Header -->
    <header class="flex flex-col gap-6">
        <div class="flex items-end justify-between">
            <div>
                <h2 class="text-5xl font-black text-black tracking-tighter">All Habits</h2>
                <p class="text-slate-400 text-sm mt-2 font-medium tracking-tight uppercase tracking-widest">High-productivity routine management</p>
            </div>
            <div class="flex gap-3">
                <button onclick="quickAddHabit()" class="flex items-center justify-center h-12 px-6 border border-black bg-white text-black text-xs font-black uppercase tracking-widest hover:bg-slate-50 transition-colors">
                    Quick Add
                </button>
                <a href="?page=habit-form" class="flex items-center justify-center h-12 px-6 bg-black text-white text-xs font-black uppercase tracking-widest hover:opacity-90 transition-opacity">
                    + New Habit
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <section class="grid grid-cols-4 gap-4">
            <div class="bg-white p-6 border border-black">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Total Habits</p>
                <h3 class="text-2xl font-black tracking-tight"><?php echo $totalHabits; ?></h3>
            </div>
            <div class="bg-white p-6 border border-black">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Active Streaks</p>
                <h3 class="text-2xl font-black tracking-tight"><?php echo $activeStreaks; ?></h3>
            </div>
            <div class="bg-white p-6 border border-black">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Daily Completion</p>
                <h3 class="text-2xl font-black tracking-tight"><?php echo $dailyCompletion; ?>%</h3>
            </div>
            <div class="bg-black p-6 border border-black">
                <p class="text-[10px] text-slate-400 font-black uppercase tracking-[0.2em] mb-1">Longest Streak</p>
                <h3 class="text-2xl font-black tracking-tight text-white"><?php echo $longestStreak; ?> Days</h3>
            </div>
        </section>
    </header>

    <!-- Habits Grid -->
    <?php if (empty($habits)): ?>
        <div class="bg-white border border-black p-20 text-center">
            <svg class="w-20 h-20 text-slate-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="text-2xl font-black text-slate-700 mt-6">No habits yet</h3>
            <p class="text-slate-500 mt-2">Start building your routine today</p>
            <a href="?page=habit-form" class="inline-block mt-6 px-8 py-3 bg-black text-white text-sm font-black uppercase tracking-widest hover:opacity-90 transition-opacity">
                Create Your First Habit
            </a>
        </div>
    <?php else: ?>
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-20">
            <?php foreach ($habits as $habit): ?>
                <div class="bg-white border border-black p-8 flex flex-col gap-6 hover:shadow-[8px_8px_0px_0px_rgba(0,0,0,1)] transition-all">
                    <div class="flex justify-between items-start">
                        <div class="flex flex-col">
                            <h4 class="text-xl font-black tracking-tight"><?php echo e($habit['name']); ?></h4>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                Category: <?php echo ucfirst($habit['category'] ?? 'General'); ?>
                                <?php if (!empty($habit['targetDuration'])): ?>
                                    • <?php echo $habit['targetDuration']; ?> min target
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-xs font-black uppercase tracking-widest text-slate-400">Current Streak</span>
                            <span class="text-3xl font-black"><?php echo $habit['currentStreak'] ?? 0; ?></span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 pt-4 border-t border-slate-100">
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Last 7 Days</p>
                        <div class="flex gap-2">
                            <?php foreach ($habit['weeklyProgress'] as $progress): ?>
                                <div class="sparkline-dot size-1.5 border border-black <?php echo $progress > 0 ? 'bg-black' : 'bg-white'; ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2">
                        <a href="?page=habit-form&id=<?php echo e($habit['id']); ?>"
                           class="flex-1 h-10 border border-black bg-white text-center font-black text-[11px] uppercase tracking-widest hover:bg-gray-100 transition-all flex items-center justify-center gap-1"
                           title="Edit habit">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            Edit
                        </a>
                        <button onclick="toggleHabitArchive('<?php echo e($habit['id']); ?>', '<?php echo e($habit['name']); ?>')"
                                class="flex-1 h-10 border border-black bg-white text-center font-black text-[11px] uppercase tracking-widest hover:bg-gray-100 transition-all flex items-center justify-center"
                                title="Archive this habit">
                            Archive
                        </button>
                        <a href="?page=view-habit&id=<?php echo e($habit['id']); ?>"
                           class="flex-1 h-10 border border-black bg-black text-white text-center font-black text-[11px] uppercase tracking-widest hover:opacity-90 transition-all flex items-center justify-center">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

<script>
function quickAddHabit() {
    window.location.href = '?page=habit-form';
}

async function toggleHabitArchive(habitId, habitName) {
    confirmAction(`Archive "${habitName}"? It will no longer appear in your habit list.`, async () => {
        const response = await api.post('api/habits.php?action=toggle_active', {
            habitId: habitId,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(response.message || 'Habit archived', 'success');
            // Reload the page to see the updated list
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(response.error || 'Failed to archive habit', 'error');
        }
    });
}
</script>

<style>
.sparkline-dot {
    width: 0.375rem;
    height: 0.375rem;
    border-radius: 9999px;
}
</style>

