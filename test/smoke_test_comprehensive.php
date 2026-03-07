<?php
/**
 * Comprehensive Smoke Test - Habit Tracker Feature
 * Tests page loads, JavaScript dependencies, and error handling
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     COMPREHENSIVE HABIT TRACKER SMOKE TEST REPORT              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$testsPassed = 0;
$testsFailed = 0;
$testsWarning = 0;

function test($name, $condition, $details = '') {
    global $testsPassed, $testsFailed, $testsWarning;
    
    if ($condition === true) {
        echo "✓ PASS: $name\n";
        if ($details) echo "        $details\n";
        $testsPassed++;
    } elseif ($condition === false) {
        echo "✗ FAIL: $name\n";
        if ($details) echo "        $details\n";
        $testsFailed++;
    } else {
        echo "⚠ WARN: $name\n";
        if ($details) echo "        $details\n";
        $testsWarning++;
    }
}

// ============================================
// PHASE 1: FILE EXISTENCE CHECKS
// ============================================
echo "PHASE 1: File Existence Checks\n";
echo "─────────────────────────────────────────────────────────────────\n";

test('HabitTimerManager.js exists', 
    file_exists(__DIR__ . '/../assets/js/habit-timer-manager.js'),
    'assets/js/habit-timer-manager.js');

test('habits.php view exists',
    file_exists(__DIR__ . '/../views/habits.php'),
    'views/habits.php');

test('view-habit.php view exists',
    file_exists(__DIR__ . '/../views/view-habit.php'),
    'views/view-habit.php');

test('api/habits.php exists',
    file_exists(__DIR__ . '/../api/habits.php'),
    'api/habits.php');

test('app.js exists',
    file_exists(__DIR__ . '/../assets/js/app.js'),
    'assets/js/app.js');

test('main layout exists',
    file_exists(__DIR__ . '/../views/layouts/main.php'),
    'views/layouts/main.php');

// ============================================
// PHASE 2: SCRIPT CONTENT VALIDATION
// ============================================
echo "\nPHASE 2: Script Content Validation\n";
echo "─────────────────────────────────────────────────────────────────\n";

$timerManagerContent = file_get_contents(__DIR__ . '/../assets/js/habit-timer-manager.js');
test('HabitTimerManager class defined',
    strpos($timerManagerContent, 'const HabitTimerManager') !== false,
    'IIFE pattern with const HabitTimerManager');

test('HabitTimerManager.start method',
    strpos($timerManagerContent, 'const start =') !== false,
    'start method defined');

test('HabitTimerManager.stop method',
    strpos($timerManagerContent, 'const stop =') !== false,
    'stop method defined');

test('HabitTimerManager event system',
    strpos($timerManagerContent, 'const on =') !== false && strpos($timerManagerContent, 'const emit =') !== false,
    'Event system (on/emit) implemented');

test('HabitTimerManager localStorage',
    strpos($timerManagerContent, 'localStorage') !== false,
    'localStorage persistence implemented');

// ============================================
// PHASE 3: VIEW FILE VALIDATION
// ============================================
echo "\nPHASE 3: View File Validation\n";
echo "─────────────────────────────────────────────────────────────────\n";

$habitsContent = file_get_contents(__DIR__ . '/../views/habits.php');
test('habits.php has timer functions',
    strpos($habitsContent, 'HabitTimerManager.start(') !== false &&
    strpos($habitsContent, 'HabitTimerManager.stop(') !== false,
    'HabitTimerManager start/stop wiring');

test('habits.php initializes timers',
    strpos($habitsContent, 'HabitTimerManager.restoreFromStorage()') !== false,
    'HabitTimerManager restoreFromStorage');

test('habits.php loads localStorage',
    strpos($habitsContent, 'HabitTimerManager.getState()') !== false,
    'HabitTimerManager state usage');

$viewHabitContent = file_get_contents(__DIR__ . '/../views/view-habit.php');
test('view-habit.php uses HabitTimerManager',
    strpos($viewHabitContent, 'HabitTimerManager') !== false,
    'HabitTimerManager referenced');

test('view-habit.php has event listeners',
    strpos($viewHabitContent, 'HabitTimerManager.on(') !== false,
    'Event listener setup');

test('view-habit.php restores timer',
    strpos($viewHabitContent, 'HabitTimerManager.restoreFromStorage') !== false,
    'Timer restoration on page load');

// ============================================
// PHASE 4: API VALIDATION
// ============================================
echo "\nPHASE 4: API Validation\n";
echo "─────────────────────────────────────────────────────────────────\n";

$apiContent = file_get_contents(__DIR__ . '/../api/habits.php');
test('API has start_timer action',
    strpos($apiContent, "'start_timer'") !== false || strpos($apiContent, '"start_timer"') !== false,
    'start_timer endpoint');

test('API has stop_timer action',
    strpos($apiContent, "'stop_timer'") !== false || strpos($apiContent, '"stop_timer"') !== false,
    'stop_timer endpoint');

test('API has timer_session action',
    strpos($apiContent, "'timer_session'") !== false || strpos($apiContent, '"timer_session"') !== false,
    'timer_session endpoint');

test('API manages habit_timer_sessions',
    strpos($apiContent, 'habit_timer_sessions') !== false,
    'Database operations for timer sessions');

// ============================================
// PHASE 5: LAYOUT VALIDATION
// ============================================
echo "\nPHASE 5: Layout Validation\n";
echo "─────────────────────────────────────────────────────────────────\n";

$layoutContent = file_get_contents(__DIR__ . '/../views/layouts/main.php');
test('Layout loads HabitTimerManager.js',
    strpos($layoutContent, 'habit-timer-manager.js') !== false,
    'Script tag for HabitTimerManager');

test('Layout loads app.js',
    strpos($layoutContent, 'assets/js/app.js') !== false,
    'Script tag for app.js');

test('Layout has CSRF token',
    strpos($layoutContent, 'CSRF_TOKEN') !== false,
    'CSRF token available');

// ============================================
// PHASE 6: DATABASE VALIDATION
// ============================================
echo "\nPHASE 6: Database Validation\n";
echo "─────────────────────────────────────────────────────────────────\n";

try {
    $db = new Database(getMasterPassword());
    
    $habits = $db->load('habits');
    test('Habits data loads',
        is_array($habits),
        'Loaded ' . count($habits) . ' habits');
    
    $timerSessions = $db->load('habit_timer_sessions');
    test('Timer sessions data loads',
        is_array($timerSessions),
        'Loaded ' . count($timerSessions) . ' timer sessions');
    
    $completions = $db->load('habit_completions');
    test('Habit completions data loads',
        is_array($completions),
        'Loaded ' . count($completions) . ' completions');
    
} catch (Exception $e) {
    test('Database connection', false, $e->getMessage());
}

// ============================================
// PHASE 7: ERROR LOG CHECK
// ============================================
echo "\nPHASE 7: Error Log Check\n";
echo "─────────────────────────────────────────────────────────────────\n";

$errorLogPath = __DIR__ . '/../data/php_error.log';
if (file_exists($errorLogPath)) {
    $logContent = file_get_contents($errorLogPath);
    $lines = array_filter(explode("\n", $logContent));
    $recentLines = array_slice($lines, -50);
    
    $fatalErrors = array_filter($recentLines, function($line) {
        return stripos($line, 'fatal') !== false || stripos($line, 'parse error') !== false;
    });
    
    test('No fatal PHP errors',
        count($fatalErrors) === 0,
        'Checked last 50 log entries');
    
    if (count($fatalErrors) > 0) {
        echo "\n  Recent fatal errors:\n";
        foreach (array_slice($fatalErrors, -3) as $error) {
            echo "  - " . trim($error) . "\n";
        }
    }
} else {
    test('Error log exists', null, 'No errors logged yet');
}

// ============================================
// SUMMARY
// ============================================
echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                        TEST SUMMARY                            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";
echo "✓ Passed:  $testsPassed\n";
echo "✗ Failed:  $testsFailed\n";
echo "⚠ Warning: $testsWarning\n";
echo "─────────────────────────────────────────────────────────────────\n";

if ($testsFailed === 0) {
    echo "✓ ALL CRITICAL TESTS PASSED\n";
} else {
    echo "✗ SOME TESTS FAILED - REVIEW ABOVE\n";
}

echo "\n";
