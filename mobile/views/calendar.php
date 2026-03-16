<?php
/**
 * Mobile Calendar Page - redesigned to match sample/mobile_clander.md UI/UX
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">\n        <h2>Session Error</h2>\n        <p>Your session has expired or the master password is not available.</p>\n        <p>Please <a href="?page=login">log in again</a>.</p>\n    </body></html>');
}

try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">\n        <h2>Database Error</h2>\n        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>\n        <p><a href="?page=dashboard">Return to dashboard</a></p>\n    </body></html>');
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$dayHeaders = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = (int)date('w', mktime(0, 0, 0, $month, 1, $year));

$projects = $db->load('projects') ?? [];
$tasksByDay = [];
$monthTasks = [];

foreach ($projects as $project) {
    foreach (($project['tasks'] ?? []) as $task) {
        $rawDueDate = (string)($task['dueDate'] ?? '');
        if ($rawDueDate === '') {
            continue;
        }

        $timestamp = strtotime($rawDueDate);
        if ($timestamp === false) {
            continue;
        }

        $taskYear = (int)date('Y', $timestamp);
        $taskMonth = (int)date('n', $timestamp);
        if ($taskYear !== $year || $taskMonth !== $month) {
            continue;
        }

        $day = (int)date('j', $timestamp);
        $hasExplicitTime = preg_match('/\d{2}:\d{2}/', $rawDueDate) === 1;
        $dueTime = $hasExplicitTime ? date('H:i', $timestamp) : '--:--';

        $item = [
            'id' => (string)($task['id'] ?? ''),
            'title' => (string)($task['title'] ?? 'Untitled Task'),
            'completed' => !empty($task['completedAt']) || (($task['status'] ?? '') === 'done'),
            'projectId' => (string)($project['id'] ?? ''),
            'projectName' => (string)($project['name'] ?? 'Inbox'),
            'dueTime' => $dueTime,
            'timestamp' => $timestamp
        ];

        if (!isset($tasksByDay[$day])) {
            $tasksByDay[$day] = [];
        }
        $tasksByDay[$day][] = $item;
        $monthTasks[] = $item;
    }
}

foreach ($tasksByDay as $day => $dayTasks) {
    usort($dayTasks, static function(array $a, array $b): int {
        if ($a['completed'] !== $b['completed']) {
            return $a['completed'] ? 1 : -1;
        }
        return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
    });
    $tasksByDay[$day] = $dayTasks;
}

$requestedDay = isset($_GET['day']) ? (int)$_GET['day'] : 0;
$selectedDay = 0;
if ($requestedDay >= 1 && $requestedDay <= $daysInMonth) {
    $selectedDay = $requestedDay;
} else {
    $todayYear = (int)date('Y');
    $todayMonth = (int)date('n');
    $todayDay = (int)date('j');

    if ($year === $todayYear && $month === $todayMonth) {
        $selectedDay = $todayDay;
    } elseif (!empty($tasksByDay)) {
        $taskDays = array_keys($tasksByDay);
        sort($taskDays);
        $selectedDay = (int)$taskDays[0];
    } else {
        $selectedDay = 1;
    }
}

if ($selectedDay < 1 || $selectedDay > $daysInMonth) {
    $selectedDay = 1;
}

$selectedTasks = $tasksByDay[$selectedDay] ?? [];
$monthTaskCount = count($monthTasks);
$selectedDateLabel = date('M j', mktime(0, 0, 0, $month, $selectedDay, $year));
$siteName = getSiteName() ?? 'LazyMan';

$calendarCells = [];
for ($i = 0; $i < $firstDayOfMonth; $i++) {
    $calendarCells[] = [
        'day' => null,
        'selected' => false,
        'hasTasks' => false,
        'taskCount' => 0
    ];
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $calendarCells[] = [
        'day' => $day,
        'selected' => $day === $selectedDay,
        'hasTasks' => !empty($tasksByDay[$day]),
        'taskCount' => count($tasksByDay[$day] ?? [])
    ];
}
while (count($calendarCells) % 7 !== 0) {
    $calendarCells[] = [
        'day' => null,
        'selected' => false,
        'hasTasks' => false,
        'taskCount' => 0
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Calendar - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: {
                    primary: '#000000',
                    'background-light': '#ffffff'
                },
                fontFamily: {
                    display: ['Inter', 'sans-serif']
                }
            }
        }
    };
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-white text-black font-display antialiased;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col overflow-hidden">

<?php
$title = 'Calendar';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'openCalendarTaskModal()';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<div class="px-4 py-4 flex items-center justify-between border-b border-gray-100 dark:border-zinc-800">
    <a href="?page=calendar&month=<?= $prevMonth ?>&year=<?= $prevYear ?>&day=1" class="p-1 hover:bg-gray-100 dark:hover:bg-zinc-900 rounded-full transition-colors" aria-label="Previous month">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5"/>
        </svg>
    </a>
    <h2 class="text-sm font-bold uppercase tracking-[0.2em]"><?= htmlspecialchars($monthNames[$month]) ?> <?= $year ?></h2>
    <div class="flex items-center gap-1">
        <button onclick="if (window.Mobile && Mobile.theme) { Mobile.theme.toggle(); }"
                data-theme-toggle
                class="p-2 rounded-full border border-gray-200 dark:border-zinc-700 hover:bg-gray-100 dark:hover:bg-zinc-900 transition-colors"
                aria-label="Switch theme">
            <svg class="w-4 h-4 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/>
            </svg>
            <svg class="w-4 h-4 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
            </svg>
        </button>
        <a href="?page=calendar&month=<?= $nextMonth ?>&year=<?= $nextYear ?>&day=1" class="p-1 hover:bg-gray-100 dark:hover:bg-zinc-900 rounded-full transition-colors" aria-label="Next month">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
        </a>
    </div>
</div>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <section class="px-4 pt-4 pb-5">
        <div class="calendar-grid text-center mb-4">
            <?php foreach ($dayHeaders as $header): ?>
                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest py-2"><?= htmlspecialchars($header) ?></div>
            <?php endforeach; ?>
        </div>

        <div class="calendar-grid border-t border-l border-gray-100 dark:border-zinc-800">
            <?php foreach ($calendarCells as $cell): ?>
                <?php if ($cell['day'] === null): ?>
                    <div class="aspect-square border-r border-b border-gray-100 dark:border-zinc-800 flex items-center justify-center"></div>
                <?php else: ?>
                    <a
                        href="?page=calendar&month=<?= $month ?>&year=<?= $year ?>&day=<?= $cell['day'] ?>"
                        class="aspect-square border-r border-b border-gray-100 dark:border-zinc-800 flex flex-col items-center justify-center relative transition-colors <?= $cell['selected'] ? 'bg-black text-white dark:bg-white dark:text-black' : 'hover:bg-gray-50 dark:hover:bg-zinc-900' ?>"
                    >
                        <span class="text-xs <?= $cell['selected'] ? 'font-bold' : 'font-medium' ?>"><?= $cell['day'] ?></span>
                        <?php if ($cell['hasTasks']): ?>
                            <div class="absolute bottom-2 flex items-center gap-1">
                                <span class="w-2 h-2 rounded-full bg-green-500 border border-white dark:border-zinc-900"></span>
                                <?php if (($cell['taskCount'] ?? 0) > 1): ?>
                                    <span class="text-[8px] font-bold leading-none text-green-600 dark:text-green-400"><?= (int)$cell['taskCount'] ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-[10px] font-bold uppercase tracking-widest text-green-600 dark:text-green-400">Green dot = day has task/event</p>
    </section>

    <section class="px-4 py-3 bg-gray-50 dark:bg-zinc-900 border-y border-gray-100 dark:border-zinc-800">
        <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-widest"><?= $monthTaskCount ?> task<?= $monthTaskCount === 1 ? '' : 's' ?> this month</span>
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Selected: <?= htmlspecialchars($selectedDateLabel) ?></span>
        </div>
    </section>

    <section class="px-4 py-5">
        <?php if (empty($selectedTasks)): ?>
            <div class="text-center py-8 border border-gray-100 dark:border-zinc-800">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400">No tasks on this day</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($selectedTasks as $task): ?>
                    <a href="?page=view-task&id=<?= urlencode($task['id']) ?>&projectId=<?= urlencode($task['projectId']) ?>" class="flex items-center gap-4 <?= $task['completed'] ? 'opacity-30' : 'group' ?>">
                        <div class="w-1 h-12 <?= $task['completed'] ? 'bg-gray-200 dark:bg-zinc-700' : 'bg-black dark:bg-white' ?>"></div>
                        <div class="flex-1 border-b border-gray-100 dark:border-zinc-800 pb-4">
                            <div class="flex justify-between items-start mb-1 gap-3">
                                <h3 class="text-sm font-bold uppercase tracking-tight leading-tight"><?= htmlspecialchars($task['title']) ?></h3>
                                <span class="text-[10px] font-black flex-shrink-0"><?= htmlspecialchars($task['dueTime']) ?></span>
                            </div>
                            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Project: <?= htmlspecialchars($task['projectName']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<nav class="absolute bottom-0 left-0 right-0 bg-white dark:bg-zinc-950 border-t border-gray-100 dark:border-zinc-800 px-8 py-6 flex justify-between items-center z-40">
    <a href="?page=dashboard" class="text-gray-300 dark:text-zinc-600 hover:text-black dark:hover:text-white transition-colors flex flex-col items-center gap-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
        </svg>
        <span class="text-[8px] font-black uppercase tracking-widest">Dash</span>
    </a>
    <a href="?page=calendar" class="text-black dark:text-white flex flex-col items-center gap-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V7.5m-18 0h18"/>
        </svg>
        <span class="text-[8px] font-black uppercase tracking-widest">Tasks</span>
    </a>
    <a href="?page=habits" class="text-gray-300 dark:text-zinc-600 hover:text-black dark:hover:text-white transition-colors flex flex-col items-center gap-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
        </svg>
        <span class="text-[8px] font-black uppercase tracking-widest">Habits</span>
    </a>
    <a href="?page=settings" class="text-gray-300 dark:text-zinc-600 hover:text-black dark:hover:text-white transition-colors flex flex-col items-center gap-1">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.5 6h3m-7.5 6h12m-9 6h6"/>
        </svg>
        <span class="text-[8px] font-black uppercase tracking-widest">Config</span>
    </a>
</nav>

<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 dark:bg-zinc-700 rounded-full z-50"></div>
</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();

    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
    const CALENDAR_SELECTED_DATE = <?= json_encode(sprintf('%04d-%02d-%02d', $year, $month, $selectedDay)) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
function openCalendarTaskModal() {
    if (!window.Mobile || !Mobile.ui || typeof Mobile.ui.openTaskModal !== 'function') {
        window.location.href = '?page=tasks';
        return;
    }

    Mobile.ui.openTaskModal();

    let attempts = 0;
    const timer = setInterval(() => {
        attempts++;
        const dueInput = document.querySelector('#mobile-task-form input[name="dueDate"]');
        if (dueInput) {
            dueInput.value = CALENDAR_SELECTED_DATE;
            clearInterval(timer);
        } else if (attempts >= 30) {
            clearInterval(timer);
        }
    }, 80);
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.Mobile && typeof Mobile.init === 'function') {
        Mobile.init();
    }
});
</script>
</body>
</html>
