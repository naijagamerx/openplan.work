<?php
/**
 * Calendar View - Task Calendar with Month Navigation
 */

$pageTitle = 'Calendar';
$currentPage = 'calendar';

// Get current month/year from URL or use current date
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Validate month range
if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2020 || $year > 2030) {
    $year = (int)date('Y');
}

// Navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear = $year - 1;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear = $year + 1;
}

// Month names
$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Day names
$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Get days in month and start day
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = (int)date('w', mktime(0, 0, 0, $month, 1, $year));

// Get tasks with due dates in this month
$db = new Database(getMasterPassword(), Auth::userId());
$projects = $db->load('projects');

// Collect all tasks with due dates
$tasksByDate = [];
foreach ($projects as $project) {
    if (!empty($project['tasks'])) {
        foreach ($project['tasks'] as $task) {
            if (!empty($task['dueDate'])) {
                $taskDate = substr($task['dueDate'], 0, 10); // YYYY-MM-DD
                $taskYear = (int)substr($taskDate, 0, 4);
                $taskMonth = (int)substr($taskDate, 5, 2);

                if ($taskYear === $year && $taskMonth === $month) {
                    $day = (int)substr($taskDate, 8, 2);
                    if (!isset($tasksByDate[$day])) {
                        $tasksByDate[$day] = [];
                    }
                    $tasksByDate[$day][] = [
                        'id' => $task['id'],
                        'title' => $task['title'],
                        'status' => normalizeTaskStatus($task['status'] ?? 'todo'),
                        'priority' => $task['priority'],
                        'projectName' => $project['name'],
                        'projectColor' => $project['color'] ?? '#3b82f6'
                    ];
                }
            }
        }
    }
}

// Get today's date for highlighting
$today = date('Y-m-d');
$todayDay = (int)date('j');
$todayMonth = (int)date('m');
$todayYear = (int)date('Y');

$isTodayMonth = ($todayMonth === $month && $todayYear === $year);
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Calendar</h1>
            <p class="text-gray-500 mt-1">View tasks by due date</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="?page=calendar&month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>"
               class="p-2 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>

            <h2 class="text-xl font-semibold text-gray-900 min-w-[200px] text-center">
                <?php echo $monthNames[$month] . ' ' . $year; ?>
            </h2>

            <a href="?page=calendar&month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>"
               class="p-2 hover:bg-gray-100 rounded-lg transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <div class="flex gap-2">
            <a href="?page=calendar"
               class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition text-sm font-medium">
                Today
            </a>
        </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4 mb-6 no-print">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-green-500"></span>
            <span class="text-sm text-gray-600">Done</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-blue-500"></span>
            <span class="text-sm text-gray-600">In Progress</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
            <span class="text-sm text-gray-600">Todo</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-red-500"></span>
            <span class="text-sm text-gray-600">Overdue</span>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <!-- Day Headers -->
        <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
            <?php foreach ($dayNames as $day): ?>
                <div class="px-4 py-3 text-center text-sm font-medium text-gray-600">
                    <?php echo $day; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Calendar Days -->
        <div class="grid grid-cols-7">
            <?php
            // Empty cells before first day
            for ($i = 0; $i < $firstDayOfMonth; $i++) {
                echo '<div class="min-h-[100px] bg-gray-50 border-b border-r border-gray-100"></div>';
            }

            // Days of the month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $isToday = $isTodayMonth && ($day === $todayDay);
                $dayTasks = $tasksByDate[$day] ?? [];
                $hasTasks = !empty($dayTasks);

                $cellClass = 'min-h-[100px] p-2 border-b border-r border-gray-100';
                if ($isToday) {
                    $cellClass .= ' bg-blue-50';
                } else {
                    $cellClass .= ' bg-white';
                }

                echo '<div class="' . $cellClass . '">';
                echo '<div class="flex items-center justify-between mb-1">';
                echo '<span class="text-sm font-medium ' . ($isToday ? 'text-blue-600' : 'text-gray-700') . '">';
                echo $day;
                if ($isToday) {
                    echo ' <span class="text-xs bg-blue-600 text-white px-1.5 py-0.5 rounded ml-1">Today</span>';
                }
                echo '</span>';
                if ($hasTasks) {
                    echo '<span class="text-xs text-gray-400">' . count($dayTasks) . ' task' . (count($dayTasks) > 1 ? 's' : '') . '</span>';
                }
                echo '</div>';

                // Task pills
                if ($hasTasks) {
                    echo '<div class="space-y-1">';
                    foreach (array_slice($dayTasks, 0, 3) as $task) {
                        $taskStatus = normalizeTaskStatus($task['status'] ?? 'todo');
                        $statusColor = match($taskStatus) {
                            'done' => 'bg-green-100 text-green-800 border-green-200',
                            'in_progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                            'review' => 'bg-purple-100 text-purple-800 border-purple-200',
                            'backlog' => 'bg-gray-100 text-gray-800 border-gray-200',
                            default => 'bg-yellow-100 text-yellow-800 border-yellow-200'
                        };

                        // Priority indicator
                        $priorityDot = '';
                        if ($task['priority'] === 'urgent') {
                            $priorityDot = '<span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1"></span>';
                        } elseif ($task['priority'] === 'high') {
                            $priorityDot = '<span class="w-1.5 h-1.5 rounded-full bg-orange-500 mr-1"></span>';
                        }

                        $taskDate = $taskYear . '-' . str_pad($taskMonth, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $isOverdue = $taskDate < $today && $taskStatus !== 'done';

                        if ($isOverdue) {
                            $statusColor = 'bg-red-100 text-red-800 border-red-200';
                        }

                        echo '<div class="text-xs px-2 py-1 rounded border ' . $statusColor . ' truncate cursor-pointer hover:opacity-80 transition"
                                onclick="viewTask(\'' . $task['id'] . '\', \'' . htmlspecialchars($task['title']) . '\')"
                                title="' . htmlspecialchars($task['title']) . ' (' . htmlspecialchars($task['projectName']) . ')">';
                        echo $priorityDot . htmlspecialchars(substr($task['title'], 0, 20)) . (strlen($task['title']) > 20 ? '...' : '');
                        echo '</div>';
                    }

                    if (count($dayTasks) > 3) {
                        echo '<div class="text-xs text-gray-500 pl-2">+' . (count($dayTasks) - 3) . ' more</div>';
                    }
                    echo '</div>';
                }

                echo '</div>';
            }

            // Empty cells after last day
            $remainingCells = 7 - (($firstDayOfMonth + $daysInMonth) % 7);
            if ($remainingCells < 7) {
                for ($i = 0; $i < $remainingCells; $i++) {
                    echo '<div class="min-h-[100px] bg-gray-50 border-b border-r border-gray-100"></div>';
                }
            }
            ?>
        </div>
    </div>

    <!-- Task Detail Modal -->
    <div id="task-modal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl max-w-md w-full mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900" id="task-modal-title">Task Details</h3>
                <button onclick="closeTaskModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div id="task-modal-content"></div>
        </div>
    </div>
</div>

<script>
// View task details
function viewTask(taskId, taskTitle) {
    const modal = document.getElementById('task-modal');
    const titleEl = document.getElementById('task-modal-title');
    const contentEl = document.getElementById('task-modal-content');

    titleEl.textContent = taskTitle;
    contentEl.innerHTML = '<p class="text-gray-600">Loading task details...</p>';
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // Find task in projects
    let taskData = null;
    let projectName = '';

    api.get('api/projects.php').then(response => {
        const projects = response.data || [];

        for (const project of projects) {
            if (project.tasks) {
                const task = project.tasks.find(t => t.id === taskId);
                if (task) {
                    taskData = task;
                    projectName = project.name;
                    break;
                }
            }
        }

        if (taskData) {
            const statusLabels = {
                'backlog': 'Backlog',
                'todo': 'To Do',
                'in_progress': 'In Progress',
                'review': 'Review',
                'done': 'Done'
            };

            const priorityLabels = {
                'low': 'Low',
                'medium': 'Medium',
                'high': 'High',
                'urgent': 'Urgent'
            };

            contentEl.innerHTML = `
                <div class="space-y-4">
                    <div>
                        <label class="text-xs text-gray-500 uppercase">Status</label>
                        <p class="text-sm font-medium capitalize">${statusLabels[taskData.status] || taskData.status}</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase">Priority</label>
                        <p class="text-sm font-medium capitalize">${priorityLabels[taskData.priority] || taskData.priority}</p>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase">Project</label>
                        <p class="text-sm font-medium">${projectName}</p>
                    </div>
                    ${taskData.dueDate ? `
                    <div>
                        <label class="text-xs text-gray-500 uppercase">Due Date</label>
                        <p class="text-sm font-medium">${App.format.date(taskData.dueDate)}</p>
                    </div>
                    ` : ''}
                    ${taskData.description ? `
                    <div>
                        <label class="text-xs text-gray-500 uppercase">Description</label>
                        <p class="text-sm text-gray-600">${taskData.description}</p>
                    </div>
                    ` : ''}
                    <div class="flex gap-2 pt-4">
                        <a href="?page=tasks&action=edit&id=${taskId}" class="flex-1 px-4 py-2 bg-black text-white rounded-lg text-center text-sm hover:bg-gray-800 transition">
                            Edit Task
                        </a>
                        <button onclick="closeTaskModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                            Close
                        </button>
                    </div>
                </div>
            `;
        } else {
            contentEl.innerHTML = '<p class="text-red-500">Task not found</p>';
        }
    }).catch(error => {
        contentEl.innerHTML = '<p class="text-red-500">Failed to load task</p>';
    });
}

function closeTaskModal() {
    const modal = document.getElementById('task-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Close modal on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeTaskModal();
    }
});

// Close modal on backdrop click
document.getElementById('task-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'task-modal') {
        closeTaskModal();
    }
});
</script>

