<?php
// Habit History Page - Table view of all completed habit history
$db = new Database(getMasterPassword(), Auth::userId());
$habits = $db->load('habits');
$completions = $db->load('habit_completions');
$timerSessions = $db->load('habit_timer_sessions');

$today = date('Y-m-d');

// Build habit lookup array
$habitMap = [];
foreach ($habits as $habit) {
    $habitMap[$habit['id']] = $habit;
}

// Filter completions for "complete" status and sort by date descending
$completedHistory = array_filter($completions, fn($c) => $c['status'] === 'complete');
usort($completedHistory, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));

// Calculate statistics
$totalCompletions = count($completedHistory);
$uniqueHabitsCompleted = count(array_unique(array_column($completedHistory, 'habitId')));

// Date range
$dateRange = 'All time';
if (!empty($completedHistory)) {
    $dates = array_column($completedHistory, 'date');
    $minDate = min($dates);
    $maxDate = max($dates);
    $dateRange = date('M j, Y', strtotime($minDate)) . ' - ' . date('M j, Y', strtotime($maxDate));
}

// Group by date for table
$historyByDate = [];
foreach ($completedHistory as $comp) {
    $date = $comp['date'];
    if (!isset($historyByDate[$date])) {
        $historyByDate[$date] = [];
    }
    $historyByDate[$date][] = $comp;
}
krsort($historyByDate);

function formatDuration($seconds) {
    if ($seconds < 60) return "{$seconds}s";
    if ($seconds < 3600) return floor($seconds / 60) . "m";
    return floor($seconds / 3600) . "h " . floor(($seconds % 3600) / 60) . "m";
}
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Habit History</h2>
            <p class="text-gray-500 font-medium tracking-tight">All completed habit records</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=habits" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Habits
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Total Completions</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalCompletions; ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Unique Habits</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $uniqueHabitsCompleted; ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Date Range</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?php echo $dateRange; ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Completion Rate</p>
            <p class="text-3xl font-bold text-green-600 mt-1">
                <?php echo count($habits) > 0 ? round(($uniqueHabitsCompleted / count($habits)) * 100) : 0; ?>%
            </p>
        </div>
    </div>

    <!-- History Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <?php if (empty($completedHistory)): ?>
            <div class="p-10 text-center">
                <svg class="w-16 h-16 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 mt-4">No completed habits yet</h3>
                <p class="text-gray-500 mt-2">Complete some habits to see your history</p>
                <a href="?page=habits" class="inline-block mt-4 px-6 py-2 bg-black text-white rounded-lg font-bold hover:bg-gray-800 transition">Go to Habits →</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Date</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Habit</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Category</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Time Spent</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Status</th>
                            <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($historyByDate as $date => $dayCompletions): ?>
                            <?php foreach ($dayCompletions as $comp): ?>
                                <?php
                                $habit = $habitMap[$comp['habitId']] ?? null;
                                if (!$habit) continue;

                                // Get timer sessions for this date and habit
                                $dateTimerSessions = array_filter($timerSessions, fn($s) =>
                                    $s['habitId'] === $comp['habitId'] &&
                                    isset($s['date']) &&
                                    substr($s['date'], 0, 10) === $date
                                );
                                $totalSeconds = array_sum(array_column($dateTimerSessions, 'duration'));
                                ?>
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-4">
                                        <p class="text-sm font-bold text-gray-900">
                                            <?php echo date('M j, Y', strtotime($date)); ?>
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            <?php echo date('l', strtotime($date)); ?>
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-black text-white rounded-lg flex items-center justify-center font-bold text-sm">
                                                <?php echo strtoupper(substr($habit['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-bold text-gray-900"><?php echo e($habit['name']); ?></p>
                                                <p class="text-xs text-gray-400"><?php echo e($comp['id']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 bg-gray-100 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-600">
                                            <?php echo ucfirst($habit['category'] ?? 'general'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <span class="text-sm font-mono font-bold text-gray-700">
                                                <?php echo $totalSeconds > 0 ? formatDuration($totalSeconds) : '--'; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-black uppercase tracking-widest">
                                            Complete
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="?page=view-habit&id=<?php echo e($comp['habitId']); ?>"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-gray-600 hover:text-black hover:border-black transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    Showing <span class="font-bold"><?php echo count($completedHistory); ?></span> completion records
                    across <span class="font-bold"><?php echo count($historyByDate); ?></span> unique days
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

