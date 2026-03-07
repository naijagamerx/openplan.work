<?php
require_once __DIR__ . '/../config.php';

if (!Auth::check()) {
    errorResponse('Unauthorized', 401);
}

if (requestMethod() !== 'GET') {
    $body = getJsonBody();
    $token = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($token)) {
        errorResponse('Invalid CSRF token', 403);
    }
}

$db = new Database(getMasterPassword());
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

switch (requestMethod()) {
    case 'GET':
        if ($id) {
            $habits = $db->load('habits');
            foreach ($habits as $habit) {
                if ($habit['id'] === $id) {
                    successResponse($habit);
                }
            }
            errorResponse('Habit not found', 404);
        } elseif ($action === 'get_water_tracker') {
            $waterTracker = $db->load('water_tracker');
            $today = date('Y-m-d');

            $todayEntry = null;
            foreach ($waterTracker as $entry) {
                if ($entry['date'] === $today) {
                    $todayEntry = $entry;
                    break;
                }
            }

            if ($todayEntry) {
                successResponse($todayEntry, 'Water tracker data retrieved');
            } else {
                $defaultGoal = isset($_GET['goal']) ? (int)$_GET['goal'] : 8;
                successResponse([
                    'date' => $today,
                    'glasses' => 0,
                    'goal' => $defaultGoal,
                    'lastReminder' => null
                ], 'Water tracker data retrieved');
            }
        } else {
            $habits = $db->load('habits');
            $completions = $db->load('habit_completions');
            $timerSessions = $db->load('habit_timer_sessions');

            $today = date('Y-m-d');

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
            }

            successResponse(array_values($habits));
        }
        break;
        
    case 'POST':
        $body = getJsonBody();
        $action = $_GET['action'] ?? 'add';

        if ($action === 'complete' && isset($body['habitId']) && isset($body['date'])) {
            $completions = $db->load('habit_completions');
            $habitId = $body['habitId'];
            $date = $body['date'];

            $existingIndex = null;
            foreach ($completions as $i => $comp) {
                if ($comp['habitId'] === $habitId && $comp['date'] === $date) {
                    $existingIndex = $i;
                    break;
                }
            }

            if ($existingIndex !== null) {
                $completions[$existingIndex]['status'] = $body['status'] ?? 'complete';
                $completions[$existingIndex]['completedAt'] = $body['status'] === 'complete' ? date('c') : null;
                $completions[$existingIndex]['updatedAt'] = date('c');
            } else {
                $completions[] = [
                    'id' => $db->generateId(),
                    'habitId' => $habitId,
                    'date' => $date,
                    'status' => $body['status'] ?? 'complete',
                    'completedAt' => date('c'),
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('habit_completions', $completions);
            successResponse($completions[$existingIndex] ?? end($completions), 'Habit updated');
        } elseif ($action === 'start_timer' && isset($body['habitId'])) {
            $timerSessions = $db->load('habit_timer_sessions');
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
            $timerSessions = $db->load('habit_timer_sessions');
            $sessionId = $body['sessionId'];

            $sessionIndex = null;
            foreach ($timerSessions as $i => $session) {
                if ($session['id'] === $sessionId && $session['status'] === 'running') {
                    $sessionIndex = $i;
                    break;
                }
            }

            if ($sessionIndex === null) {
                errorResponse('Active timer not found', 404);
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
            $timerSessions = $db->load('habit_timer_sessions');

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
        } elseif ($action === 'timer_stats' && isset($body['habitId'])) {
            $timerSessions = $db->load('habit_timer_sessions');
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
            $waterTracker = $db->load('water_tracker');
            $today = date('Y-m-d');

            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if ($entry['date'] === $today) {
                    $todayIndex = $i;
                    break;
                }
            }

            $count = isset($body['count']) ? (int)$body['count'] : 1;

            if ($todayIndex !== null) {
                $waterTracker[$todayIndex]['glasses'] += $count;
                $waterTracker[$todayIndex]['updatedAt'] = date('c');
            } else {
                $waterTracker[] = [
                    'id' => $db->generateId(),
                    'date' => $today,
                    'glasses' => $count,
                    'goal' => isset($body['goal']) ? (int)$body['goal'] : 8,
                    'reminderInterval' => isset($body['reminderInterval']) ? (int)$body['reminderInterval'] : 60,
                    'lastReminder' => null,
                    'createdAt' => date('c'),
                    'updatedAt' => date('c')
                ];
            }

            $db->save('water_tracker', $waterTracker);
            $updatedEntry = $todayIndex !== null ? $waterTracker[$todayIndex] : end($waterTracker);
            successResponse($updatedEntry, 'Glass added');
        } elseif ($action === 'set_water_goal') {
            $waterTracker = $db->load('water_tracker');
            $today = date('Y-m-d');

            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if ($entry['date'] === $today) {
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
            $waterTracker = $db->load('water_tracker');
            $today = date('Y-m-d');

            $todayIndex = null;
            foreach ($waterTracker as $i => $entry) {
                if ($entry['date'] === $today) {
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
        } else {
            if (empty($body['name'])) {
                errorResponse('Habit name is required');
            }

            $habits = $db->load('habits');
            $newHabit = [
                'id' => $db->generateId(),
                'name' => $body['name'],
                'category' => $body['category'] ?? 'general',
                'frequency' => $body['frequency'] ?? 'daily',
                'reminderTime' => $body['reminderTime'] ?? null,
                'targetDuration' => (int)($body['targetDuration'] ?? 0),
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
            errorResponse('Habit ID required');
        }

        $body = getJsonBody();
        $habits = $db->load('habits');
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
            errorResponse('Habit not found', 404);
        }
        break;
        
    case 'DELETE':
        if (!$id) {
            errorResponse('Habit ID required');
        }
        
        $habits = $db->load('habits');
        $filtered = array_filter($habits, fn($h) => $h['id'] !== $id);
        
        if (count($filtered) === count($habits)) {
            errorResponse('Habit not found', 404);
        }
        
        $db->save('habits', array_values($filtered));
        
        $completions = $db->load('habit_completions');
        $filteredCompletions = array_filter($completions, fn($c) => $c['habitId'] !== $id);
        $db->save('habit_completions', array_values($filteredCompletions));
        
        successResponse(null, 'Habit deleted');
        break;
        
    default:
        errorResponse('Method not allowed', 405);
}
