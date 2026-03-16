<?php
require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

if (requestMethod() !== 'GET' && !Auth::isMcp()) {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());

// Verify database connection works
try {
    $db->load('habits', true);
} catch (Exception $e) {
    errorResponse('Database connection failed: Wrong master password', 401, ERROR_UNAUTHORIZED);
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

function normalizeWaterAmountMl($rawAmount, $glassSizeMl = 250) {
    $glassSize = (int)$glassSizeMl > 0 ? (int)$glassSizeMl : 250;
    $value = (float)$rawAmount;
    if ($value <= 0) {
        return $glassSize;
    }

    // Backward compatibility: legacy plans stored "glasses" (1,2,...) instead of ml.
    if ($value <= 20) {
        return (int) round($value * $glassSize);
    }

    return (int) round($value);
}

function amountMlToGlassUnits($amountMl, $glassSizeMl = 250) {
    $glassSize = (int)$glassSizeMl > 0 ? (int)$glassSizeMl : 250;
    $units = (float)$amountMl / $glassSize;
    return round($units, 2);
}

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $habits = $db->load('habits', true);
            foreach ($habits as $habit) {
                if ($habit['id'] === $id) {
                    successResponse($habit);
                }
            }
            errorResponse('Habit not found', 404, ERROR_NOT_FOUND);
        } elseif ($action === 'get_water_tracker') {
            $waterTracker = $db->load('water_tracker', true);
            $today = date('Y-m-d');

            $todayEntry = null;
            foreach ($waterTracker as $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayEntry = $entry;
                    break;
                }
            }

            if ($todayEntry) {
                if (!isset($todayEntry['intakeMl'])) {
                    $todayEntry['intakeMl'] = (int) round(((float)($todayEntry['glasses'] ?? 0)) * 250);
                }
                if (!isset($todayEntry['goalMl'])) {
                    $todayEntry['goalMl'] = (int) round(((float)($todayEntry['goal'] ?? 8)) * 250);
                }
                successResponse($todayEntry, 'Water tracker data retrieved');
            } else {
                $defaultGoal = isset($_GET['goal']) ? (int)$_GET['goal'] : 8;
                successResponse([
                    'date' => $today,
                    'glasses' => 0,
                    'goal' => $defaultGoal,
                    'intakeMl' => 0,
                    'goalMl' => $defaultGoal * 250,
                    'lastReminder' => null
                ], 'Water tracker data retrieved');
            }
        } elseif ($action === 'get_water_plan') {
            $planId = $_GET['planId'] ?? null;
            $allowLegacy = (isset($_GET['allow_legacy']) && $_GET['allow_legacy'] === '1');
            $waterPlans = $db->load('water_plans', true);
            $waterPlanHistory = $db->load('water_plan_history', true);
            $waterTracker = $db->load('water_tracker', true);

            // If planId is provided, look for it in all collections
            if ($planId) {
                // Check manual plans
                foreach ($waterPlans as $plan) {
                    if (isset($plan['id']) && $plan['id'] === $planId) {
                        successResponse($plan, 'Water plan retrieved');
                    }
                }
                // Check history plans
                foreach ($waterPlanHistory as $plan) {
                    if (isset($plan['id']) && $plan['id'] === $planId) {
                        successResponse($plan, 'Water plan retrieved from history');
                    }
                }
                // Check AI plans in tracker
                foreach ($waterTracker as $entry) {
                    if (isset($entry['id']) && $entry['id'] === $planId && isset($entry['schedule'])) {
                        successResponse($entry, 'AI water plan retrieved from tracker');
                    }
                }
                errorResponse('Water plan not found', 404, ERROR_NOT_FOUND);
            }

            // Default behavior: Get the active water plan (prioritize manual plans)
            // First, check for active manual plan (get the most recent one)
            $activeManualPlan = null;
            foreach ($waterPlans as $plan) {
                if (isset($plan['isActive']) && $plan['isActive'] === true && isset($plan['type']) && $plan['type'] === 'manual') {
                    if (!$activeManualPlan || (isset($plan['createdAt']) && $plan['createdAt'] > ($activeManualPlan['createdAt'] ?? ''))) {
                        $activeManualPlan = $plan;
                    }
                }
            }

            if ($activeManualPlan) {
                successResponse($activeManualPlan, 'Active manual water plan retrieved');
            }

            // Legacy fallback is opt-in only. By default, this endpoint returns active plans only.
            // This avoids stale reminders from old tracker schedules when no plan is active.
            if (!$allowLegacy) {
                errorResponse('No active water plan found', 404, ERROR_NOT_FOUND);
            }

            // Optional legacy fallback: only consider tracker plans from today.
            $today = date('Y-m-d');
            $aiPlans = array_values(array_filter($waterTracker, function($entry) use ($today) {
                if (!isset($entry['schedule']) || !is_array($entry['schedule'])) {
                    return false;
                }

                if (isset($entry['date']) && $entry['date'] === $today) {
                    return true;
                }

                if (isset($entry['createdAt'])) {
                    return substr((string)$entry['createdAt'], 0, 10) === $today;
                }

                return false;
            }));

            if (empty($aiPlans)) {
                errorResponse('No active water plan found', 404, ERROR_NOT_FOUND);
            }

            usort($aiPlans, function($a, $b) {
                $aTime = strtotime($a['updatedAt'] ?? $a['createdAt'] ?? '1970-01-01');
                $bTime = strtotime($b['updatedAt'] ?? $b['createdAt'] ?? '1970-01-01');
                return $bTime <=> $aTime;
            });

            successResponse($aiPlans[0], 'Legacy water plan retrieved');
        } elseif ($action === 'list_water_plan_history') {
            // List all water plans - includes manual plans and saved history
            $waterPlans = $db->load('water_plans', true);
            $waterPlanHistory = $db->load('water_plan_history', true);

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

            if (empty($allPlans)) {
                successResponse([], 'No water plans found');
            }

            // Sort by createdAt descending (most recent first)
            usort($allPlans, function($a, $b) {
                $aTime = strtotime($a['createdAt'] ?? 0);
                $bTime = strtotime($b['createdAt'] ?? 0);
                return $bTime - $aTime;
            });

            successResponse($allPlans, 'Water plan history retrieved');
        } else {
            $habits = $db->load('habits', true);
            $completions = $db->load('habit_completions', true);
            $timerSessions = $db->load('habit_timer_sessions', true);

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
                        // Allow today to be skipped if not yet completed
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

                $habitTimerSessions = array_filter($timerSessions, fn($s) => $s['habitId'] === $habit['id']);
                $totalDuration = array_sum(array_map(fn($s) => $s['duration'] ?? 0, $habitTimerSessions));
                $habits[$key]['totalMinutes'] = round($totalDuration / 60, 2);
                $habits[$key]['sessionCount'] = count($habitTimerSessions);

                $activeTimer = array_filter($habitTimerSessions, fn($s) => $s['status'] === 'running');
                $habits[$key]['activeTimer'] = !empty($activeTimer) ? array_values($activeTimer)[0] : null;

                // Add per-habit streak calculations for grid view
                $habits[$key]['currentStreak'] = $calculateHabitStreak($habit['id'], $completions, $today);
                $habits[$key]['longestStreak'] = $calculateLongestStreak($habit['id'], $completions);

                // Calculate last 7 days completion for sparkline
                $habits[$key]['weeklyProgress'] = [];
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $dayCompletions = array_filter($habitCompletions, fn($c) => $c['date'] === $date && $c['status'] === 'complete');
                    $habits[$key]['weeklyProgress'][] = count($dayCompletions) > 0 ? 100 : 0;
                }
            }

            // Calculate aggregated stats for grid view
            $stats = [
                'totalHabits' => count($habits),
                'activeStreaks' => count(array_filter($habits, fn($h) => ($h['currentStreak'] ?? 0) > 0)),
                'dailyCompletion' => count($habits) > 0 ? round((count(array_filter($habits, fn($h) => $h['todayCompleted'])) / count($habits)) * 100) : 0,
                'longestStreak' => max(array_column($habits, 'longestStreak') ?: [0])
            ];

            successResponse(array_values($habits));
        }
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? $body['action'] ?? 'add';

        if ($action === 'complete' && isset($body['habitId']) && isset($body['date'])) {
            $completions = $db->load('habit_completions', true);
            $timerSessions = $db->load('habit_timer_sessions', true);
            $habitId = (string)$body['habitId'];
            $date = substr((string)$body['date'], 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                errorResponse('Invalid date format', 400, ERROR_VALIDATION);
            }
            $status = strtolower((string)($body['status'] ?? 'complete'));
            if (!in_array($status, ['complete', 'incomplete'], true)) {
                $status = 'complete';
            }
            $providedDuration = isset($body['duration']) ? (int)$body['duration'] : null;

            // Calculate duration - use provided duration or check for running timer
            $timerDuration = $providedDuration;
            $runningSessionIndex = null;

            if ($timerDuration === null) {
                foreach ($timerSessions as $i => $session) {
                    if ($session['habitId'] === $habitId && $session['status'] === 'running') {
                        $startTime = strtotime($session['startTime']);
                        $timerDuration = time() - $startTime;
                        $runningSessionIndex = $i;
                        break;
                    }
                }
            }

            // Keep exactly one completion record per (habitId, date) to avoid duplicates.
            $existingRecord = null;
            $filteredCompletions = [];
            foreach ($completions as $comp) {
                $compHabitId = (string)($comp['habitId'] ?? '');
                $compDate = substr((string)($comp['date'] ?? ''), 0, 10);
                if ($compHabitId === $habitId && $compDate === $date) {
                    $existingRecord = $comp;
                    continue;
                }
                $filteredCompletions[] = $comp;
            }
            $completions = $filteredCompletions;

            $completionData = [
                'habitId' => $habitId,
                'date' => $date,
                'status' => $status,
                'completedAt' => $status === 'complete' ? date('c') : null,
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];

            // Include timer duration if there was a running timer
            if ($timerDuration > 0) {
                $completionData['duration'] = $timerDuration;

                // Stop the running timer session if exists
                if ($runningSessionIndex !== null) {
                    $timerSessions[$runningSessionIndex]['endTime'] = date('c');
                    $timerSessions[$runningSessionIndex]['duration'] = $timerDuration;
                    $timerSessions[$runningSessionIndex]['status'] = 'completed';
                    $timerSessions[$runningSessionIndex]['updatedAt'] = date('c');
                    $db->save('habit_timer_sessions', $timerSessions);
                }
            }

            $updatedCompletion = $completionData;
            if ($existingRecord) {
                $updatedCompletion = array_merge($existingRecord, [
                    'status' => $status,
                    'date' => $date,
                    'completedAt' => $status === 'complete' ? date('c') : null,
                    'updatedAt' => date('c')
                ]);
                if ($timerDuration > 0) {
                    $updatedCompletion['duration'] = $timerDuration;
                } elseif (!isset($updatedCompletion['duration'])) {
                    $updatedCompletion['duration'] = 0;
                }
                if (empty($updatedCompletion['createdAt'])) {
                    $updatedCompletion['createdAt'] = date('c');
                }
                if (empty($updatedCompletion['id'])) {
                    $updatedCompletion['id'] = $db->generateId();
                }
            } else {
                $updatedCompletion['id'] = $db->generateId();
            }
            $completions[] = $updatedCompletion;

            $db->save('habit_completions', $completions);
            successResponse($updatedCompletion, 'Habit updated' . ($timerDuration > 0 ? " with {$timerDuration}s timer" : ''));
        } elseif ($action === 'start_timer' && isset($body['habitId'])) {
            $timerSessions = $db->load('habit_timer_sessions', true);
            $habitId = $body['habitId'];

            $existingRunning = null;
            foreach ($timerSessions as $i => $session) {
                if ($session['habitId'] === $habitId && $session['status'] === 'running') {
                    $existingRunning = $i;
                    break;
                }
            }

            if ($existingRunning !== null) {
                successResponse($timerSessions[$existingRunning], 'Timer already running');
            }

            $newSession = [
                'id' => $db->generateId(),
                'habitId' => $habitId,
                'startTime' => date('c'),
                'endTime' => null,
                'duration' => 0,
                'status' => 'running',
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];

            $timerSessions[] = $newSession;
            $db->save('habit_timer_sessions', $timerSessions);
            successResponse($newSession, 'Timer started');
        } elseif ($action === 'stop_timer' && isset($body['sessionId'])) {
            $timerSessions = $db->load('habit_timer_sessions', true);
            $sessionId = $body['sessionId'];

            $sessionIndex = null;
            foreach ($timerSessions as $i => $session) {
                if ($session['id'] === $sessionId && $session['status'] === 'running') {
                    $sessionIndex = $i;
                    break;
                }
            }

            if ($sessionIndex === null) {
                errorResponse('Active timer not found', 404, ERROR_NOT_FOUND);
            }

            $startTime = strtotime($timerSessions[$sessionIndex]['startTime']);
            $endTime = time();
            $duration = $endTime - $startTime;

            $timerSessions[$sessionIndex]['endTime'] = date('c', $endTime);
            $timerSessions[$sessionIndex]['duration'] = $duration;
            $timerSessions[$sessionIndex]['status'] = 'completed';
            $timerSessions[$sessionIndex]['updatedAt'] = date('c');

            $db->save('habit_timer_sessions', $timerSessions);
            successResponse($timerSessions[$sessionIndex], 'Timer stopped');
        } elseif ($action === 'manual_log' && isset($body['habitId']) && isset($body['duration'])) {
            $timerSessions = $db->load('habit_timer_sessions', true);

            $newSession = [
                'id' => $db->generateId(),
                'habitId' => $body['habitId'],
                'startTime' => date('c'),
                'endTime' => date('c'),
                'duration' => (int)$body['duration'],
                'status' => 'manual',
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];

            $timerSessions[] = $newSession;
            $db->save('habit_timer_sessions', $timerSessions);
            successResponse($newSession, 'Manual time logged');
        } elseif ($action === 'timer_session' && isset($body['habitId']) && isset($body['duration'])) {
            // Save timer session from frontend
            $timerSessions = $db->load('habit_timer_sessions', true);

            $newSession = [
                'id' => $db->generateId(),
                'habitId' => $body['habitId'],
                'startTime' => date('c', strtotime('-'.$body['duration'].' seconds')),
                'endTime' => date('c'),
                'duration' => (int)$body['duration'],
                'status' => 'completed',
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];

            $timerSessions[] = $newSession;
            $db->save('habit_timer_sessions', $timerSessions);
            successResponse($newSession, 'Timer session saved');
        } elseif ($action === 'timer_stats' && isset($body['habitId'])) {
            $timerSessions = $db->load('habit_timer_sessions', true);
            $habitId = $body['habitId'];

            $habitSessions = array_filter($timerSessions, fn($s) => $s['habitId'] === $habitId);
            $totalDuration = array_sum(array_map(fn($s) => $s['duration'] ?? 0, $habitSessions));
            $avgDuration = count($habitSessions) > 0 ? $totalDuration / count($habitSessions) : 0;

            successResponse([
                'totalSessions' => count($habitSessions),
                'totalDuration' => $totalDuration,
                'averageDuration' => round($avgDuration, 2),
                'recentSessions' => array_slice(array_reverse(array_values($habitSessions)), 0, 10)
            ], 'Timer stats retrieved');
        } elseif ($action === 'add_water_glass') {
            $waterTracker = $db->load('water_tracker', true);
            $today = date('Y-m-d');

            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            $count = isset($body['count']) ? (float)$body['count'] : 1.0;
            if ($count <= 0) {
                $count = 1.0;
            }
            $source = $body['source'] ?? 'manual';
            $planId = $body['planId'] ?? null;
            $glassSizeMl = isset($body['glassSizeMl']) ? (int)$body['glassSizeMl'] : 250;
            if ($glassSizeMl <= 0) {
                $glassSizeMl = 250;
            }
            $amountMl = isset($body['amountMl']) ? (int)$body['amountMl'] : (int) round($count * $glassSizeMl);
            if ($amountMl <= 0) {
                $amountMl = (int) round($count * $glassSizeMl);
            }
            $goalMl = isset($body['goalMl']) ? (int)$body['goalMl'] : null;

            // Get glasses planned from active plan if planId provided
            $glassesPlanned = 0;
            if ($planId) {
                $waterPlans = $db->load('water_plans', true);
                foreach ($waterPlans as $plan) {
                    if (isset($plan['id']) && $plan['id'] === $planId) {
                        $glassesPlanned = count($plan['schedule'] ?? []);
                        $glassSizeMl = (int)($plan['glassSize'] ?? $glassSizeMl);
                        if ($glassSizeMl <= 0) {
                            $glassSizeMl = 250;
                        }
                        if ($goalMl === null && isset($plan['dailyGoal'])) {
                            $goalMl = (int)$plan['dailyGoal'];
                        }
                        break;
                    }
                }
            }

            if ($todayIndex !== null) {
                $waterTracker[$todayIndex]['glasses'] += $count;
                $waterTracker[$todayIndex]['intakeMl'] = (int)(($waterTracker[$todayIndex]['intakeMl'] ?? 0) + $amountMl);
                if ($planId) {
                    $waterTracker[$todayIndex]['planId'] = $planId;
                }
                if ($glassesPlanned > 0 && !isset($waterTracker[$todayIndex]['glassesPlanned'])) {
                    $waterTracker[$todayIndex]['glassesPlanned'] = $glassesPlanned;
                }
                if ($goalMl !== null && $goalMl > 0) {
                    $waterTracker[$todayIndex]['goalMl'] = $goalMl;
                } elseif (!isset($waterTracker[$todayIndex]['goalMl'])) {
                    $waterTracker[$todayIndex]['goalMl'] = (int) round(((float)($waterTracker[$todayIndex]['goal'] ?? 8)) * $glassSizeMl);
                }
                if (!isset($waterTracker[$todayIndex]['logEntries'])) {
                    $waterTracker[$todayIndex]['logEntries'] = [];
                }
                $waterTracker[$todayIndex]['logEntries'][] = [
                    'time' => date('c'),
                    'glasses' => $count,
                    'amountMl' => $amountMl,
                    'source' => $source
                ];
                if (!isset($waterTracker[$todayIndex]['missedReminders'])) {
                    $waterTracker[$todayIndex]['missedReminders'] = [];
                }
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'planId' => $planId,
                    'glasses' => $count,
                    'intakeMl' => $amountMl,
                    'glassesPlanned' => $glassesPlanned,
                    'goal' => isset($body['goal']) ? (int)$body['goal'] : 8,
                    'goalMl' => ($goalMl !== null && $goalMl > 0) ? $goalMl : ((isset($body['goal']) ? (int)$body['goal'] : 8) * $glassSizeMl),
                    'reminderInterval' => isset($body['reminderInterval']) ? (int)$body['reminderInterval'] : 60,
                    'lastReminder' => null,
                    'logEntries' => [[
                        'time' => date('c'),
                        'glasses' => $count,
                        'amountMl' => $amountMl,
                        'source' => $source
                    ]],
                    'missedReminders' => [],
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            $updatedEntry = $todayIndex !== null ? $waterTracker[$todayIndex] : end($waterTracker);
            successResponse($updatedEntry, 'Water intake added');
        } elseif ($action === 'set_water_goal') {
            $waterTracker = $db->load('water_tracker', true);
            $today = date('Y-m-d');

            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            $goal = (int)$body['goal'];

            if ($todayIndex !== null) {
                $waterTracker[$todayIndex]['goal'] = $goal;
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'glasses' => 0,
                    'goal' => $goal,
                    'reminderInterval' => 60,
                    'lastReminder' => null,
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            successResponse($waterTracker[$todayIndex] ?? end($waterTracker), 'Goal updated');
        } elseif ($action === 'set_water_reminder') {
            $waterTracker = $db->load('water_tracker', true);
            $today = date('Y-m-d');

            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            $interval = isset($body['reminderInterval']) ? (int)$body['reminderInterval'] : 60;

            if ($todayIndex !== null) {
                $waterTracker[$todayIndex]['reminderInterval'] = $interval;
                $waterTracker[$todayIndex]['lastReminder'] = null;
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'glasses' => 0,
                    'goal' => 8,
                    'reminderInterval' => $interval,
                    'lastReminder' => null,
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            successResponse($waterTracker[$todayIndex] ?? end($waterTracker), 'Reminder set');
        } elseif ($action === 'get_water_quote') {
            // Get water quote - check cached daily quote first
            $today = date('Y-m-d');

            // Try to load today's cached quote (generated by cron)
            $dailyQuote = $db->load('daily_water_quote', true);

            if ($dailyQuote && isset($dailyQuote['date']) && $dailyQuote['date'] === $today) {
                // Return cached daily quote
                successResponse([
                    'quote' => $dailyQuote['quote'],
                    'tip' => $dailyQuote['tip'],
                    'fact' => $dailyQuote['fact'],
                    'source' => $dailyQuote['source'] ?? 'cached'
                ], 'Quote loaded from cache');
            }

            // No cached quote for today, generate one now
            require_once __DIR__ . '/../includes/GroqAPI.php';
            require_once __DIR__ . '/../includes/OpenRouterAPI.php';

            $config = $db->load('config', true);
            $groqKey = $config['groqApiKey'] ?? '';
            $openrouterKey = $config['openrouterApiKey'] ?? '';
            $defaultModel = $config['defaultModel'] ?? 'groq';

            // Fallback quotes if no API key
            $fallbackQuotes = [
                ['quote' => 'Water is the driving force of all nature.', 'tip' => 'Keep a water bottle at your desk', 'fact' => 'Your body is 60% water'],
                ['quote' => 'Drink water for your brain.', 'tip' => 'Start your day with a glass of water', 'fact' => 'Your brain is 75% water'],
                ['quote' => 'Hydration is happiness.', 'tip' => 'Set hourly reminders to drink', 'fact' => 'Blood is 90% water'],
                ['quote' => 'Flow like water.', 'tip' => 'Drink before you feel thirsty', 'fact' => 'Water regulates body temperature'],
                ['quote' => 'Water is life.', 'tip' => 'Add lemon to your water for flavor', 'fact' => 'Muscles are 75% water']
            ];

            $quoteData = null;
            $source = 'fallback';

            // Try Groq first (default)
            if (!empty($groqKey) && $defaultModel === 'groq') {
                try {
                    $groq = new GroqAPI($groqKey);

                    // Get configured Groq model from database
                    $models = $db->load('models', true);
                    $groqModel = null;
                    if ($models && isset($models['groq']) && is_array($models['groq'])) {
                        // First, look for default model
                        foreach ($models['groq'] as $m) {
                            if (isset($m['isDefault']) && $m['isDefault'] && isset($m['modelId'])) {
                                $groqModel = $m['modelId'];
                                break;
                            }
                        }
                        // If no default, use first enabled model
                        if (!$groqModel) {
                            foreach ($models['groq'] as $m) {
                                if (isset($m['modelId']) && ($m['enabled'] ?? true)) {
                                    $groqModel = $m['modelId'];
                                    break;
                                }
                            }
                        }
                    }

                    // If no model configured, use fallback quotes
                    if (!$groqModel) {
                        throw new Exception('No Groq model configured. Please configure a model in Model Settings.');
                    }

                    $prompt = <<<PROMPT
Generate a short, inspiring hydration quote (under 100 characters).
Also include one practical tip for drinking more water (under 80 characters).
And one interesting water fact (under 80 characters).

Return ONLY valid JSON in this format (no markdown, no code blocks):
{
    "quote": "Short inspiring quote about water",
    "tip": "Practical tip for drinking water",
    "fact": "Interesting water fact"
}
PROMPT;

                    $response = $groq->chatCompletion([
                        ['role' => 'user', 'content' => $prompt]
                    ], $groqModel);

                    $content = $response['choices'][0]['message']['content'] ?? '';

                    // Extract JSON from response
                    preg_match('/\{[\s\S]*\}/', $content, $matches);

                    if (!empty($matches[0])) {
                        $quoteData = json_decode($matches[0], true);
                        if ($quoteData && !empty($quoteData['quote'])) {
                            $source = 'groq';
                        }
                    }
                } catch (Exception $e) {
                    // Continue to OpenRouter or fallback
                }
            }

            // Try OpenRouter as fallback
            if ($quoteData === null && !empty($openrouterKey)) {
                try {
                    $openrouter = new OpenRouterAPI($openrouterKey);

                    // Get configured OpenRouter model from database
                    $models = $db->load('models', true);
                    $openrouterModel = null;
                    if ($models && isset($models['openrouter']) && is_array($models['openrouter'])) {
                        // First, look for default model
                        foreach ($models['openrouter'] as $m) {
                            if (isset($m['isDefault']) && $m['isDefault'] && isset($m['modelId'])) {
                                $openrouterModel = $m['modelId'];
                                break;
                            }
                        }
                        // If no default, use first enabled model
                        if (!$openrouterModel) {
                            foreach ($models['openrouter'] as $m) {
                                if (isset($m['modelId']) && ($m['enabled'] ?? true)) {
                                    $openrouterModel = $m['modelId'];
                                    break;
                                }
                            }
                        }
                    }

                    // If no model configured, skip OpenRouter
                    if (!$openrouterModel) {
                        throw new Exception('No OpenRouter model configured. Please configure a model in Model Settings.');
                    }

                    $prompt = <<<PROMPT
Generate a short, inspiring hydration quote (under 100 characters).
Also include one practical tip for drinking more water (under 80 characters).
And one interesting water fact (under 80 characters).

Return ONLY valid JSON in this format (no markdown, no code blocks):
{
    "quote": "Short inspiring quote about water",
    "tip": "Practical tip for drinking water",
    "fact": "Interesting water fact"
}
PROMPT;

                    $response = $openrouter->chatCompletion([
                        ['role' => 'user', 'content' => $prompt]
                    ], $openrouterModel);

                    $content = $response['choices'][0]['message']['content'] ?? '';

                    // Extract JSON from response
                    preg_match('/\{[\s\S]*\}/', $content, $matches);

                    if (!empty($matches[0])) {
                        $quoteData = json_decode($matches[0], true);
                        if ($quoteData && !empty($quoteData['quote'])) {
                            $source = 'openrouter';
                        }
                    }
                } catch (Exception $e) {
                    // Continue to fallback
                }
            }

            // Fallback to random quote
            if ($quoteData === null) {
                $quoteData = $fallbackQuotes[array_rand($fallbackQuotes)];
                $source = 'fallback';
            }

            // Cache this quote for today
            $cachedQuote = [
                'date' => $today,
                'quote' => $quoteData['quote'],
                'tip' => $quoteData['tip'],
                'fact' => $quoteData['fact'],
                'source' => $source,
                'generatedAt' => date('c')
            ];
            $db->save('daily_water_quote', $cachedQuote);

            successResponse([
                'quote' => $quoteData['quote'],
                'tip' => $quoteData['tip'],
                'fact' => $quoteData['fact'],
                'source' => $source
            ], 'Quote generated successfully');
        } elseif ($action === 'complete_water_plan') {
            // Mark a plan item as completed
            $planId = $body['planId'] ?? null;
            $index = (int)($body['index'] ?? 0);

            if (!$planId) {
                errorResponse('Plan ID required', 400, ERROR_VALIDATION);
            }

            $waterTracker = $db->load('water_tracker', true);
            $planIndex = null;

            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['id']) && $entry['id'] === $planId) {
                    $planIndex = $i;
                    break;
                }
            }

            if ($planIndex === null) {
                errorResponse('Plan not found', 404, ERROR_NOT_FOUND);
            }

            if (!isset($waterTracker[$planIndex]['schedule'][$index])) {
                errorResponse('Plan item not found', 404, ERROR_NOT_FOUND);
            }

            // Mark as completed and update water tracker
            $waterTracker[$planIndex]['schedule'][$index]['completed'] = true;
            $waterTracker[$planIndex]['schedule'][$index]['completedAt'] = date('c');
            $waterTracker[$planIndex]['updatedAt'] = date('c');

            // Also update the daily water tracker
            $amount = $waterTracker[$planIndex]['schedule'][$index]['amount'] ?? 250;
            $glassesCount = $amount / 250; // Convert ml to glasses

            $today = date('Y-m-d');
            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            if ($todayIndex !== null) {
                $waterTracker[$todayIndex]['glasses'] += $glassesCount;
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'glasses' => $glassesCount,
                    'goal' => $waterTracker[$planIndex]['dailyGoal'] ?? 8,
                    'reminderInterval' => 60,
                    'lastReminder' => null,
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            successResponse($waterTracker[$planIndex], 'Plan item completed');
        } elseif ($action === 'clear_water_plan') {
            // Clear water plan (remove plan entries, keep daily tracker)
            $waterTracker = $db->load('water_tracker', true);

            // Keep only entries that are not plans (don't have 'schedule' key)
            $filtered = array_filter($waterTracker, fn($e) => !isset($e['schedule']));

            $db->save('water_tracker', array_values($filtered));
            successResponse(null, 'Water plan cleared');
        } elseif ($action === 'save_water_plan_history') {
            // Save current water plan to history
            $planId = $body['planId'] ?? null;

            if (!$planId) {
                errorResponse('Plan ID required', 400, ERROR_VALIDATION);
            }

            $waterTracker = $db->load('water_tracker', true);
            $plan = null;
            $planIndex = null;

            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['id']) && $entry['id'] === $planId) {
                    $plan = $entry;
                    $planIndex = $i;
                    break;
                }
            }

            if (!$plan) {
                errorResponse('Plan not found', 404, ERROR_NOT_FOUND);
            }

            // Create history entry
            $historyEntry = [
                'id' => $db->generateId(),
                'planId' => $planId,
                'dailyGoal' => $plan['dailyGoal'] ?? 0,
                'totalGlasses' => $plan['totalGlasses'] ?? 0,
                'schedule' => $plan['schedule'] ?? [],
                'tips' => $plan['tips'] ?? [],
                'hydrationFacts' => $plan['hydrationFacts'] ?? [],
                'userParams' => $plan['userParams'] ?? [],
                'generatedAt' => $plan['generatedAt'] ?? date('c'),
                'savedAt' => date('c'),
                'createdAt' => date('c')
            ];

            // Save to water_plan_history collection
            $waterPlanHistory = $db->load('water_plan_history', true);
            $waterPlanHistory[] = $historyEntry;
            $db->save('water_plan_history', $waterPlanHistory);

            successResponse($historyEntry, 'Water plan saved to history');
        } elseif ($action === 'load_water_plan_history') {
            // Load a specific water plan from history
            $historyId = $body['historyId'] ?? null;

            if (!$historyId) {
                errorResponse('History ID required', 400, ERROR_VALIDATION);
            }

            $waterPlanHistory = $db->load('water_plan_history', true);
            $historyEntry = null;

            foreach ($waterPlanHistory as $entry) {
                if (isset($entry['id']) && $entry['id'] === $historyId) {
                    $historyEntry = $entry;
                    break;
                }
            }

            if (!$historyEntry) {
                errorResponse('History entry not found', 404, ERROR_NOT_FOUND);
            }

            // Create new plan from history
            $newPlan = [
                'id' => $db->generateId(),
                'dailyGoal' => $historyEntry['dailyGoal'] ?? 0,
                'totalGlasses' => $historyEntry['totalGlasses'] ?? 0,
                'schedule' => array_map(function($item) {
                    return array_merge($item, ['completed' => false]);
                }, $historyEntry['schedule'] ?? []),
                'tips' => $historyEntry['tips'] ?? [],
                'hydrationFacts' => $historyEntry['hydrationFacts'] ?? [],
                'userParams' => $historyEntry['userParams'] ?? [],
                'generatedAt' => date('c'),
                'loadedFromHistory' => $historyEntry['id']
            ];

            // Save to water_tracker collection
            $waterTracker = $db->load('water_tracker', true);
            $waterTracker[] = $newPlan;
            $db->save('water_tracker', $waterTracker);

            successResponse($newPlan, 'Water plan loaded from history');
        } elseif ($action === 'delete_water_plan_history') {
            // Delete a specific water plan from history
            $historyId = $body['historyId'] ?? null;

            if (!$historyId) {
                errorResponse('History ID required', 400, ERROR_VALIDATION);
            }

            $waterPlanHistory = $db->load('water_plan_history', true);
            $originalCount = count($waterPlanHistory);

            // Filter out the deleted entry
            $waterPlanHistory = array_filter($waterPlanHistory, fn($e) => ($e['id'] ?? '') !== $historyId);

            if (count($waterPlanHistory) === $originalCount) {
                errorResponse('History entry not found', 404, ERROR_NOT_FOUND);
            }

            $db->save('water_plan_history', array_values($waterPlanHistory));
            successResponse(null, 'Water plan history deleted');
        } elseif ($action === 'create_manual_plan') {
            // Create manual water plan with time schedule
            $name = $body['name'] ?? 'My Water Plan';
            $dailyGoal = (int)($body['dailyGoal'] ?? 2000);
            $glassSize = (int)($body['glassSize'] ?? 250);
            $schedule = $body['schedule'] ?? [];

            if (empty($schedule)) {
                errorResponse('Schedule cannot be empty', 400, ERROR_VALIDATION);
            }

            $plan = [
                'id' => $db->generateId(),
                'type' => 'manual',
                'name' => $name,
                'dailyGoal' => $dailyGoal,
                'glassSize' => $glassSize,
                'isActive' => true,
                'schedule' => array_map(function($item) use ($db) {
                    return [
                        'id' => $db->generateId(),
                        'time' => $item['time'],
                        'amount' => normalizeWaterAmountMl($item['amount'] ?? 0, $item['glassSize'] ?? 250),
                        'completed' => false,
                        'completedAt' => null,
                        'missed' => false,
                        'lastNotifiedAt' => null
                    ];
                }, array_map(function($item) use ($glassSize) {
                    $item['glassSize'] = $glassSize;
                    return $item;
                }, $schedule)),
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];

            $waterPlans = $db->load('water_plans', true);

            // Deactivate all existing plans
            foreach ($waterPlans as &$p) {
                $p['isActive'] = false;
                $p['updatedAt'] = date('c');
            }

            $waterPlans[] = $plan;
            $db->save('water_plans', $waterPlans);

            successResponse($plan, 'Manual water plan created');
        } elseif ($action === 'update_manual_plan') {
            // Update existing manual plan
            $planId = $body['planId'] ?? null;

            if (!$planId) {
                errorResponse('Plan ID required', 400, ERROR_VALIDATION);
            }

            $waterPlans = $db->load('water_plans', true);
            $planIndex = null;

            foreach ($waterPlans as $i => $plan) {
                if (isset($plan['id']) && $plan['id'] === $planId) {
                    $planIndex = $i;
                    break;
                }
            }

            if ($planIndex === null) {
                errorResponse('Plan not found', 404, ERROR_NOT_FOUND);
            }

            // Update allowed fields
            $allowedFields = ['name', 'dailyGoal', 'glassSize', 'isActive', 'schedule'];
            foreach ($allowedFields as $field) {
                if (isset($body[$field])) {
                    if ($field === 'schedule') {
                        // Update schedule with IDs preserved
                        $currentGlassSize = (int)($body['glassSize'] ?? ($waterPlans[$planIndex]['glassSize'] ?? 250));
                        $waterPlans[$planIndex]['schedule'] = array_map(function($item) use ($db, $currentGlassSize) {
                            return array_merge($item, [
                                'id' => $item['id'] ?? $db->generateId(),
                                'amount' => normalizeWaterAmountMl($item['amount'] ?? 0, $currentGlassSize)
                            ]);
                        }, $body[$field]);
                    } else {
                        $waterPlans[$planIndex][$field] = $body[$field];
                    }
                }
            }

            $waterPlans[$planIndex]['updatedAt'] = date('c');
            $db->save('water_plans', $waterPlans);

            successResponse($waterPlans[$planIndex], 'Manual water plan updated');
        } elseif ($action === 'activate_plan') {
            // Set a plan as active for today
            $planId = $body['planId'] ?? null;

            if (!$planId) {
                errorResponse('Plan ID required', 400, ERROR_VALIDATION);
            }

            $waterPlans = $db->load('water_plans', true);
            $planIndex = null;

            // Deactivate all plans and find the target plan
            foreach ($waterPlans as $i => $plan) {
                if (isset($plan['id']) && $plan['id'] === $planId) {
                    $planIndex = $i;
                }
                $waterPlans[$i]['isActive'] = false;
            }

            if ($planIndex === null) {
                errorResponse('Plan not found', 404, ERROR_NOT_FOUND);
            }

            $waterPlans[$planIndex]['isActive'] = true;
            $waterPlans[$planIndex]['updatedAt'] = date('c');
            $db->save('water_plans', $waterPlans);

            // Reset schedule for the activated plan
            $waterPlans[$planIndex]['schedule'] = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'time' => $item['time'],
                    'amount' => $item['amount'],
                    'completed' => false,
                    'completedAt' => null,
                    'missed' => false,
                    'lastNotifiedAt' => null
                ];
            }, $waterPlans[$planIndex]['schedule']);

            $db->save('water_plans', $waterPlans);

            successResponse($waterPlans[$planIndex], 'Plan activated for today');
        } elseif ($action === 'delete_plan') {
            // Delete a water plan from either water_plans or water_plan_history
            $planId = $body['planId'] ?? null;
            $collection = $body['collection'] ?? 'water_plans';

            if (!$planId) {
                errorResponse('Plan ID required', 400, ERROR_VALIDATION);
            }

            if (!in_array($collection, ['water_plans', 'water_plan_history'])) {
                errorResponse('Invalid collection', 400, ERROR_VALIDATION);
            }

            if ($db->delete($collection, $planId)) {
                successResponse(null, 'Plan deleted');
            }

            errorResponse('Plan not found', 404, ERROR_NOT_FOUND);
        } elseif ($action === 'complete_reminder') {
            // Mark scheduled reminder as complete
            $planId = $body['planId'] ?? null;
            $scheduleItemId = $body['scheduleItemId'] ?? null;

            if (!$planId || !$scheduleItemId) {
                errorResponse('Plan ID and Schedule Item ID required', 400, ERROR_VALIDATION);
            }

            $waterPlans = $db->load('water_plans', true);
            $planIndex = null;
            $itemIndex = null;

            foreach ($waterPlans as $pi => $plan) {
                if (isset($plan['id']) && $plan['id'] === $planId) {
                    $planIndex = $pi;
                    foreach ($plan['schedule'] as $si => $item) {
                        if ($item['id'] === $scheduleItemId) {
                            $itemIndex = $si;
                            break;
                        }
                    }
                    break;
                }
            }

            if ($planIndex === null || $itemIndex === null) {
                errorResponse('Plan or item not found', 404, ERROR_NOT_FOUND);
            }

            // Mark as completed
            $waterPlans[$planIndex]['schedule'][$itemIndex]['completed'] = true;
            $waterPlans[$planIndex]['schedule'][$itemIndex]['completedAt'] = date('c');
            $waterPlans[$planIndex]['schedule'][$itemIndex]['missed'] = false;
            $waterPlans[$planIndex]['updatedAt'] = date('c');
            $db->save('water_plans', $waterPlans);

            // Add to daily tracker
            $glassSizeMl = (int)($waterPlans[$planIndex]['glassSize'] ?? 250);
            if ($glassSizeMl <= 0) {
                $glassSizeMl = 250;
            }
            $amount = normalizeWaterAmountMl($waterPlans[$planIndex]['schedule'][$itemIndex]['amount'] ?? 0, $glassSizeMl);
            $amountGlasses = amountMlToGlassUnits($amount, $glassSizeMl);
            $waterTracker = $db->load('water_tracker', true);
            $today = date('Y-m-d');
            $todayIndex = null;

            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            if ($todayIndex !== null) {
                $waterTracker[$todayIndex]['glasses'] += $amountGlasses;
                $waterTracker[$todayIndex]['intakeMl'] = (int)(($waterTracker[$todayIndex]['intakeMl'] ?? 0) + $amount);
                $waterTracker[$todayIndex]['planId'] = $planId;
                $waterTracker[$todayIndex]['goalMl'] = (int)($waterPlans[$planIndex]['dailyGoal'] ?? ($waterTracker[$todayIndex]['goalMl'] ?? 2000));
                if (!isset($waterTracker[$todayIndex]['logEntries'])) {
                    $waterTracker[$todayIndex]['logEntries'] = [];
                }
                $waterTracker[$todayIndex]['logEntries'][] = [
                    'time' => date('c'),
                    'glasses' => $amountGlasses,
                    'amountMl' => $amount,
                    'source' => 'reminder',
                    'scheduleItemId' => $scheduleItemId
                ];
                if (!isset($waterTracker[$todayIndex]['missedReminders'])) {
                    $waterTracker[$todayIndex]['missedReminders'] = [];
                }
                $waterTracker[$todayIndex]['missedReminders'] = array_values(array_filter(
                    $waterTracker[$todayIndex]['missedReminders'],
                    fn($entry) => ($entry['scheduleItemId'] ?? '') !== $scheduleItemId
                ));
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'planId' => $planId,
                    'glasses' => $amountGlasses,
                    'intakeMl' => $amount,
                    'glassesPlanned' => count($waterPlans[$planIndex]['schedule']),
                    'goal' => (int)($waterPlans[$planIndex]['dailyGoal'] / $waterPlans[$planIndex]['glassSize']),
                    'goalMl' => (int)($waterPlans[$planIndex]['dailyGoal'] ?? 2000),
                    'reminderInterval' => 60,
                    'lastReminder' => null,
                    'logEntries' => [[
                        'time' => date('c'),
                        'glasses' => $amountGlasses,
                        'amountMl' => $amount,
                        'source' => 'reminder',
                        'scheduleItemId' => $scheduleItemId
                    ]],
                    'missedReminders' => [],
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            successResponse($waterPlans[$planIndex]['schedule'][$itemIndex], 'Reminder completed');
        } elseif ($action === 'mark_reminder_missed') {
            // Mark reminder as missed (after grace period)
            $planId = $body['planId'] ?? null;
            $scheduleItemId = $body['scheduleItemId'] ?? null;

            if (!$planId || !$scheduleItemId) {
                errorResponse('Plan ID and Schedule Item ID required', 400, ERROR_VALIDATION);
            }

            $waterPlans = $db->load('water_plans', true);
            $planIndex = null;
            $itemIndex = null;
            $scheduleItem = null;

            foreach ($waterPlans as $pi => $plan) {
                if (isset($plan['id']) && $plan['id'] === $planId) {
                    $planIndex = $pi;
                    foreach ($plan['schedule'] as $si => $item) {
                        if ($item['id'] === $scheduleItemId) {
                            $itemIndex = $si;
                            $scheduleItem = $item;
                            break;
                        }
                    }
                    break;
                }
            }

            if ($planIndex === null || $itemIndex === null) {
                errorResponse('Plan or item not found', 404, ERROR_NOT_FOUND);
            }

            if (($waterPlans[$planIndex]['schedule'][$itemIndex]['missed'] ?? false) === true) {
                successResponse($waterPlans[$planIndex]['schedule'][$itemIndex], 'Reminder already marked missed');
            }

            // Mark as missed in plan
            $waterPlans[$planIndex]['schedule'][$itemIndex]['missed'] = true;
            $waterPlans[$planIndex]['schedule'][$itemIndex]['lastNotifiedAt'] = date('c');
            $waterPlans[$planIndex]['updatedAt'] = date('c');
            $db->save('water_plans', $waterPlans);

            // Add to daily tracker missed reminders
            $waterTracker = $db->load('water_tracker', true);
            $today = date('Y-m-d');
            $todayIndex = null;

            foreach ($waterTracker as $i => $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            $missedEntry = [
                'scheduleItemId' => $scheduleItemId,
                'time' => $scheduleItem['time'],
                'missedAt' => date('c')
            ];

            if ($todayIndex !== null) {
                if (!isset($waterTracker[$todayIndex]['missedReminders'])) {
                    $waterTracker[$todayIndex]['missedReminders'] = [];
                }
                $waterTracker[$todayIndex]['missedReminders'][] = $missedEntry;
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'planId' => $planId,
                    'glasses' => 0,
                    'glassesPlanned' => count($waterPlans[$planIndex]['schedule']),
                    'goal' => (int)($waterPlans[$planIndex]['dailyGoal'] / $waterPlans[$planIndex]['glassSize']),
                    'reminderInterval' => 60,
                    'lastReminder' => null,
                    'logEntries' => [],
                    'missedReminders' => [$missedEntry],
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            successResponse($missedEntry, 'Reminder marked as missed');
        } elseif ($action === 'get_daily_tracking') {
            // Get today's tracking data (planned vs actual)
            $waterTracker = $db->load('water_tracker', true);
            $waterPlans = $db->load('water_plans', true);
            $today = date('Y-m-d');

            // Find today's tracker entry
            $todayEntry = null;
            foreach ($waterTracker as $entry) {
                if (isset($entry['date']) && $entry['date'] === $today) {
                    $todayEntry = $entry;
                    break;
                }
            }

            // Find active plan (most recent)
            $activePlan = null;
            foreach ($waterPlans as $plan) {
                if (isset($plan['isActive']) && $plan['isActive'] === true) {
                    if (!$activePlan || (isset($plan['createdAt']) && $plan['createdAt'] > ($activePlan['createdAt'] ?? ''))) {
                        $activePlan = $plan;
                    }
                }
            }

            $response = [
                'date' => $today,
                'tracker' => $todayEntry,
                'activePlan' => $activePlan,
                'planned' => $activePlan ? count($activePlan['schedule']) : 0,
                'completed' => 0,
                'missed' => 0,
                'plannedMl' => 0,
                'completedMl' => 0,
                'goalMl' => (int)($activePlan['dailyGoal'] ?? 0)
            ];

            if ($activePlan) {
                $glassSizeMl = (int)($activePlan['glassSize'] ?? 250);
                if ($glassSizeMl <= 0) {
                    $glassSizeMl = 250;
                }
                foreach ($activePlan['schedule'] as $item) {
                    $itemMl = normalizeWaterAmountMl($item['amount'] ?? 0, $glassSizeMl);
                    $response['plannedMl'] += $itemMl;
                    if ($item['completed'] ?? false) {
                        $response['completed']++;
                        $response['completedMl'] += $itemMl;
                    }
                    if ($item['missed'] ?? false) {
                        $response['missed']++;
                    }
                }
            }

            $fallbackGlassSizeMl = ($activePlan && isset($activePlan['glassSize'])) ? (int)$activePlan['glassSize'] : 250;
            if ($fallbackGlassSizeMl <= 0) {
                $fallbackGlassSizeMl = 250;
            }

            if ($todayEntry) {
                $response['logged'] = $todayEntry['glasses'] ?? 0;
                $response['loggedMl'] = (int)($todayEntry['intakeMl'] ?? round(((float)($todayEntry['glasses'] ?? 0)) * $fallbackGlassSizeMl));
                $response['logEntries'] = $todayEntry['logEntries'] ?? [];
                $response['missedReminders'] = $todayEntry['missedReminders'] ?? [];
            } else {
                $response['logged'] = 0;
                $response['loggedMl'] = 0;
                $response['logEntries'] = [];
                $response['missedReminders'] = [];
            }

            successResponse($response, 'Daily tracking data retrieved');
        } elseif ($action === 'get_tracking_history') {
            // Get daily tracking history for date range
            $startDate = $body['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $body['endDate'] ?? date('Y-m-d');

            $waterTracker = $db->load('water_tracker', true);
            $waterPlans = $db->load('water_plans', true);

            // Filter entries by date range
            $history = [];
            foreach ($waterTracker as $entry) {
                if (!isset($entry['date'])) {
                    continue;
                }
                $entryDate = $entry['date'];
                if ($entryDate >= $startDate && $entryDate <= $endDate) {
                    $planned = $entry['glassesPlanned'] ?? 0;
                    $logged = $entry['glasses'] ?? 0;
                    $missedCount = count($entry['missedReminders'] ?? []);

                    // Find plan name if available
                    $planName = 'Unknown Plan';
                    if (isset($entry['planId'])) {
                        foreach ($waterPlans as $plan) {
                            if (isset($plan['id']) && $plan['id'] === $entry['planId']) {
                                $planName = $plan['name'] ?? 'Unnamed Plan';
                                break;
                            }
                        }
                    }

                    $history[] = [
                        'date' => $entryDate,
                        'planName' => $planName,
                        'planned' => $planned,
                        'logged' => $logged,
                        'percentage' => $planned > 0 ? round(($logged / $planned) * 100, 1) : 0,
                        'missed' => $missedCount,
                        'missedReminders' => $entry['missedReminders'] ?? [],
                        'logEntries' => $entry['logEntries'] ?? []
                    ];
                }
            }

            // Sort by date descending
            usort($history, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            successResponse($history, 'Tracking history retrieved');
        } elseif ($action === 'toggle_active' && isset($body['habitId'])) {
            $habits = $db->load('habits', true);
            $habitId = $body['habitId'];
            $habitFound = false;

            foreach ($habits as $key => $habit) {
                if ($habit['id'] === $habitId) {
                    $habitFound = true;
                    // Toggle isActive (defaults to true if not set)
                    $currentStatus = isset($habit['isActive']) ? $habit['isActive'] : true;
                    $habits[$key]['isActive'] = !$currentStatus;
                    $habits[$key]['updatedAt'] = date('c');
                    $db->save('habits', $habits);

                    $newStatus = $habits[$key]['isActive'];
                    successResponse(
                        ['isActive' => $newStatus, 'habit' => $habits[$key]],
                        $newStatus ? 'Habit activated' : 'Habit archived'
                    );
                    break;
                }
            }

            if (!$habitFound) {
                errorResponse('Habit not found', 404, ERROR_NOT_FOUND);
            }
        } else {
            if (empty($body['name'])) {
                errorResponse('Habit name is required', 400, ERROR_VALIDATION);
            }

            $habits = $db->load('habits', true);
            $newHabit = [
                'id' => $db->generateId(),
                'name' => $body['name'],
                'category' => $body['category'] ?? 'general',
                'frequency' => $body['frequency'] ?? 'daily',
                'reminderTime' => $body['reminderTime'] ?? null,
                'targetDuration' => (int)($body['targetDuration'] ?? 0),
                'isActive' => true, // Default to active
                'isAiGenerated' => $body['isAiGenerated'] ?? false,
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];

            $habits[] = $newHabit;
            $db->save('habits', $habits);
            successResponse($newHabit, 'Habit created');
        }
        break;
        
    case 'PUT':
        if (!$id) {
            errorResponse('Habit ID required', 400, ERROR_VALIDATION);
        }

        $body = getJsonBody();
        $habits = $db->load('habits', true);
        $habitFound = false;

        foreach ($habits as $key => $habit) {
            if ($habit['id'] === $id) {
                $habitFound = true;
                $allowedFields = ['name', 'category', 'frequency', 'reminderTime', 'targetDuration'];
                foreach ($allowedFields as $field) {
                    if (isset($body[$field])) {
                        $habits[$key][$field] = $body[$field];
                    }
                }
                $habits[$key]['updatedAt'] = date('c');
                $db->save('habits', $habits);
                successResponse($habits[$key], 'Habit updated');
            }
        }

        if (!$habitFound) {
            errorResponse('Habit not found', 404, ERROR_NOT_FOUND);
        }
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Habit ID required', 400, ERROR_VALIDATION);
        }

        $habits = $db->load('habits', true);
        $filtered = array_filter($habits, fn($h) => $h['id'] !== $id);

        if (count($filtered) === count($habits)) {
            errorResponse('Habit not found', 404, ERROR_NOT_FOUND);
        }
        
        $db->save('habits', array_values($filtered));
        
        $completions = $db->load('habit_completions', true);
        $filteredCompletions = array_filter($completions, fn($c) => $c['habitId'] !== $id);
        $db->save('habit_completions', array_values($filteredCompletions));
        
        successResponse(null, 'Habit deleted');
        break;
        
    default:
        errorResponse('Method not allowed', 405, ERROR_NOT_IMPLEMENTED);
}

