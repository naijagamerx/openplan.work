<?php
/**
 * Water Plan History Page
 * Table view of all water plans (manual + saved history)
 */

$db = new Database(getMasterPassword(), Auth::userId());
$waterPlans = $db->load('water_plans');
$waterPlanHistory = $db->load('water_plan_history');

// Auto-close expired plans (plans from previous days that are still active)
$autoClosedPlans = [];
$now = new DateTime();

function checkAndCloseExpiredPlan($plan, $now) {
    if (!isset($plan['isActive']) || !$plan['isActive']) {
        return [false, $plan];
    }

    // Check if plan was created on a different day
    $planDate = isset($plan['createdAt']) ? new DateTime($plan['createdAt']) : $now;
    $isSameDay = $planDate->format('Y-m-d') === $now->format('Y-m-d');

    if (!$isSameDay) {
        // Plan is from a previous day - auto-close it
        $plan['isActive'] = false;
        $plan['autoClosedAt'] = $now->format('c');
        $plan['closeReason'] = 'Plan expired - end of day reached';

        // Calculate final statistics
        $schedule = $plan['schedule'] ?? [];
        $totalGlasses = count($schedule);
        $completedCount = count(array_filter($schedule, fn($s) => ($s['completed'] ?? false)));
        $missedCount = count(array_filter($schedule, fn($s) => ($s['missed'] ?? false) && !($s['completed'] ?? false)));

        $plan['finalStats'] = [
            'totalGlasses' => $totalGlasses,
            'completed' => $completedCount,
            'missed' => $missedCount,
            'completionRate' => $totalGlasses > 0 ? round(($completedCount / $totalGlasses) * 100) : 0,
            'closedDate' => $now->format('Y-m-d H:i:s')
        ];

        return [true, $plan];
    }

    return [false, $plan];
}

// Process manual plans
if (is_array($waterPlans)) {
    foreach ($waterPlans as $key => $plan) {
        list($wasClosed, $plan) = checkAndCloseExpiredPlan($plan, $now);
        if ($wasClosed) {
            $waterPlans[$key] = $plan;
            $autoClosedPlans[] = $plan;
        }
    }
    // Save updated plans back to database
    if (!empty($autoClosedPlans)) {
        $db->save('water_plans', $waterPlans);
    }
}

// Combine both collections
$allPlans = [];

// Add manual plans
if (is_array($waterPlans)) {
    foreach ($waterPlans as $plan) {
        $plan['source'] = 'manual';
        $allPlans[] = $plan;
    }
}

// Add saved history plans
if (is_array($waterPlanHistory)) {
    foreach ($waterPlanHistory as $plan) {
        $plan['source'] = 'history';
        $allPlans[] = $plan;
    }
}

// Sort by createdAt descending (most recent first)
usort($allPlans, function($a, $b) {
    $aTime = strtotime($a['createdAt'] ?? 0);
    $bTime = strtotime($b['createdAt'] ?? 0);
    return $bTime - $aTime;
});

// Calculate statistics
$totalPlans = count($allPlans);
$activePlans = count(array_filter($allPlans, fn($p) => isset($p['isActive']) && $p['isActive'] === true));
$totalGlassesPlanned = array_sum(array_map(fn($p) => count($p['schedule'] ?? 0), $allPlans));

// Date range
$dateRange = 'No plans';
if (!empty($allPlans)) {
    $dates = array_map(fn($p) => date('Y-m-d', strtotime($p['createdAt'] ?? 'now')), $allPlans);
    $minDate = min($dates);
    $maxDate = max($dates);
    $dateRange = date('M j, Y', strtotime($minDate)) . ' - ' . date('M j, Y', strtotime($maxDate));
}

// Get today's tracking for daily stats
$waterTracker = $db->load('water_tracker');
$today = date('Y-m-d');
$todayEntry = null;
foreach ($waterTracker as $entry) {
    if (isset($entry['date']) && $entry['date'] === $today) {
        $todayEntry = $entry;
        break;
    }
}

$todayGlasses = $todayEntry['glasses'] ?? 0;
$todayMissed = count($todayEntry['missedReminders'] ?? []);
?>

<div class="p-6">
    <!-- Auto-Close Notification -->
    <?php if (!empty($autoClosedPlans)): ?>
        <div class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="font-bold text-amber-800">Plan(s) Auto-Closed</h3>
                    <p class="text-sm text-amber-700 mt-1">
                        <?php echo count($autoClosedPlans); ?> plan(s) from previous days were automatically closed.
                        <a href="#closed-plans" class="underline hover:text-amber-900">View details below</a>.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Water Plan History</h2>
            <p class="text-gray-500 font-medium tracking-tight">All your water plans</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=water-plan" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create Plan
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Total Plans</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $totalPlans; ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Active Plans</p>
            <p class="text-3xl font-bold text-blue-600 mt-1"><?php echo $activePlans; ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Date Range</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?php echo $dateRange; ?></p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200">
            <p class="text-sm text-gray-500 uppercase tracking-widest font-bold">Today's Progress</p>
            <p class="text-3xl font-bold text-gray-900 mt-1"><?php echo $todayGlasses; ?> <span class="text-lg font-normal text-gray-500">/ <?php echo $todayEntry['glassesPlanned'] ?? 0; ?> glasses</span></p>
        </div>
    </div>

    <!-- Plans Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <?php if (empty($allPlans)): ?>
            <div class="p-10 text-center">
                <svg class="w-16 h-16 text-gray-300 mx-auto fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 mt-4">No water plans yet</h3>
                <p class="text-gray-500 mt-2">Create your first water plan to start tracking hydration</p>
                <a href="?page=water-plan" class="inline-block mt-4 px-6 py-2 bg-black text-white rounded-lg font-bold hover:bg-gray-800 transition">Create Plan →</a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Plan Name</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Daily Goal</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Glasses</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Type</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Created</th>
                            <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($allPlans as $plan): ?>
                            <?php
                            $schedule = $plan['schedule'] ?? [];
                            $totalGlasses = count($schedule);
                            $dailyGoal = $plan['dailyGoal'] ?? 0;
                            $createdDate = date('M j, Y', strtotime($plan['createdAt'] ?? 'now'));
                            $createdTime = date('g:i A', strtotime($plan['createdAt'] ?? 'now'));

                            $isActive = isset($plan['isActive']) && $plan['isActive'] === true;
                            $isManual = ($plan['source'] ?? 'ai') === 'manual';
                            $typeLabel = $isManual ? 'Manual' : 'AI';
                            $typeClass = $isManual ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700';

                            // Calculate completion for this plan
                            $completedCount = count(array_filter($schedule, fn($s) => ($s['completed'] ?? false)));
                            $percent = $totalGlasses > 0 ? round(($completedCount / $totalGlasses) * 100) : 0;
                            ?>
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-black text-white rounded-lg flex items-center justify-center font-bold text-sm">
                                            <?php echo strtoupper(substr($plan['name'] ?? 'Water Plan', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <p class="font-bold text-gray-900"><?php echo e($plan['name'] ?? 'Unnamed Plan'); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo $plan['id'] ?? ''; ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-900 font-medium"><?php echo number_format($dailyGoal); ?>ml</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-gray-900 font-medium"><?php echo $totalGlasses; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 <?php echo $typeClass; ?> rounded-full text-[10px] font-bold uppercase tracking-widest">
                                        <?php echo $typeLabel; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($isActive): ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-[10px] font-bold uppercase tracking-widest">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-west">
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900"><?php echo $createdDate; ?></p>
                                        <p class="text-xs text-gray-400"><?php echo $createdTime; ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Progress Badge -->
                                        <span class="px-2 py-1 <?php echo $percent >= 100 ? 'bg-green-100 text-green-700' : ($percent >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'); ?> rounded-full text-xs font-medium">
                                            <?php echo $percent; ?>%
                                        </span>
                                        <!-- View Details -->
                                        <a href="?page=water-plan-details&planId=<?php echo e($plan['id']); ?>"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs font-bold text-gray-600 hover:text-black hover:border-black transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </a>
                                        <!-- Delete Button -->
                                        <button onclick="deleteWaterPlan('<?php echo e($plan['id']); ?>', '<?php echo e($plan['source'] ?? 'manual'); ?>')"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-50 border border-red-200 rounded-lg text-xs font-bold text-red-600 hover:bg-red-100 hover:border-red-300 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete
                                        </button>
                                        <?php if (!$isActive): ?>
                                            <!-- Activate (if manual plan) -->
                                            <?php if ($isManual): ?>
                                            <button onclick="activatePlan('<?php echo $plan['id']; ?>')"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-50 border border-green-200 rounded-lg text-xs font-bold text-green-700 hover:bg-green-100 hover:border-green-300 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197 2.132a1 1 0 000-1.664 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Activate
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary Footer -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    Showing <span class="font-bold"><?php echo count($allPlans); ?></span> water plan(s)
                    with <span class="font-bold"><?php echo $totalGlassesPlanned; ?></span> total glasses scheduled
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
async function activatePlan(planId) {
    confirmAction('Activate this water plan? This will set it as your active plan for today.', async () => {
        try {
            const response = await api.post('api/habits.php?action=activate_plan', {
                planId: planId,
                csrf_token: CSRF_TOKEN
            });

            if (response.success) {
                showToast('Plan activated successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.error || 'Failed to activate plan', 'error');
            }
        } catch (error) {
            if (error.status === 401 || error.message?.includes('401')) {
                window.location.href = '?page=login&reason=session_expired';
                return;
            }
            showToast('Failed to activate plan', 'error');
        }
    });
}

async function deleteWaterPlan(planId, source) {
    const collection = source === 'manual' ? 'water_plans' : 'water_plan_history';

    confirmAction('Are you sure you want to delete this water plan? This cannot be undone.', async () => {
        try {
            const response = await api.post('api/habits.php?action=delete_plan', {
                planId: planId,
                collection: collection,
                csrf_token: CSRF_TOKEN
            });

            if (response.success) {
                showToast('Water plan deleted successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(response.error || 'Failed to delete plan', 'error');
            }
        } catch (error) {
            if (error.status === 401 || error.message?.includes('401')) {
                window.location.href = '?page=login&reason=session_expired';
                return;
            }
            showToast('Failed to delete plan', 'error');
        }
    });
}
</script>

