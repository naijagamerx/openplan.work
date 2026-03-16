<?php
// View Task Page
try {
    $db = new Database(getMasterPassword(), Auth::userId());

    // Get task ID and project ID from URL
    $taskId = $_GET['id'] ?? null;
    $projectId = $_GET['projectId'] ?? null;

    // Error reporting
    $errors = [];
    if (!$taskId) $errors[] = 'Missing task ID parameter';
    if (!$projectId) $errors[] = 'Missing project ID parameter';

    if (!empty($errors)) {
        echo '<div class="p-6">';
        echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">';
        echo '<h3 class="font-bold mb-2">Error Loading Task</h3>';
        foreach ($errors as $error) {
            echo '<p class="text-sm">• ' . e($error) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        return;
    }

    // Find the task
    $projects = $db->load('projects');
    if (empty($projects)) {
        echo '<div class="p-6"><div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">No projects found in database</div></div>';
        return;
    }

    $task = null;
    $project = null;

    foreach ($projects as $p) {
        if ($p['id'] === $projectId) {
            $project = $p;
            foreach ($p['tasks'] ?? [] as $t) {
                if ($t['id'] === $taskId) {
                    $task = $t;
                    break;
                }
            }
            break;
        }
    }

    if (!$task) {
        echo '<div class="p-6">';
        echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">';
        echo '<h3 class="font-bold mb-2">Task Not Found</h3>';
        echo '<p class="text-sm">• Task ID: ' . e($taskId) . '</p>';
        echo '<p class="text-sm">• Project ID: ' . e($projectId) . '</p>';
        if ($project) {
            echo '<p class="text-sm">• Project found: ' . e($project['name'] ?? 'Unknown') . '</p>';
            echo '<p class="text-sm">• Tasks in project: ' . count($project['tasks'] ?? []) . '</p>';
        } else {
            echo '<p class="text-sm">• Project not found</p>';
        }
        echo '</div>';
        echo '</div>';
        return;
    }

    if (!$project) {
        echo '<div class="p-6"><div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Project not found</div></div>';
        return;
    }

    $task['status'] = normalizeTaskStatus($task['status'] ?? 'todo');

    // Calculate time tracking
    $estimatedMinutes = $task['estimatedMinutes'] ?? 0;
    $actualMinutes = 0;
    $timeEntries = $task['timeEntries'] ?? [];
    foreach ($timeEntries as $entry) {
        $actualMinutes += $entry['minutes'] ?? 0;
    }

    // Calculate subtask completion
    $subtasks = $task['subtasks'] ?? [];

    // Ensure all subtasks have IDs (for backward compatibility with old data)
    foreach ($subtasks as $index => &$subtask) {
        if (empty($subtask['id'])) {
            $subtask['id'] = 'subtask-' . $index;
        }
    }
    unset($subtask);

    $completedSubtasks = array_filter($subtasks, fn($s) => $s['completed'] ?? false);

} catch (Exception $e) {
    echo '<div class="p-6">';
    echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">';
    echo '<h3 class="font-bold mb-2">Error Loading Task</h3>';
    echo '<p class="text-sm font-mono bg-red-100 p-2 rounded mt-2">' . e($e->getMessage()) . '</p>';
    echo '</div>';
    echo '</div>';
    return;
}
?>

<div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            <a href="?page=tasks" class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-3 mb-1">
                    <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo priorityClass($task['priority'] ?? 'medium'); ?>">
                        <?php echo $task['priority'] ?? 'medium'; ?>
                    </span>
                    <span class="px-2 py-0.5 text-[10px] font-black uppercase tracking-widest rounded <?php echo statusClass($task['status']); ?>">
                        <?php echo str_replace('_', ' ', $task['status']); ?>
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900"><?php echo e($task['title']); ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="?page=task-form&id=<?php echo e($taskId); ?>&projectId=<?php echo e($projectId); ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl font-bold text-sm hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                </svg>
                Edit
            </a>
            <button onclick="confirmAction('Delete this task?', () => deleteTask('<?php echo e($taskId); ?>', '<?php echo e($projectId); ?>'))" class="flex items-center gap-2 px-4 py-2.5 bg-red-50 border border-red-200 text-red-600 rounded-xl font-bold text-sm hover:bg-red-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete
            </button>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="col-span-2 space-y-6">
            <!-- Description -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                    </svg>
                    Description
                </h3>
                <div class="prose prose-sm max-w-none text-gray-600">
                    <?php if (!empty($task['description'])): ?>
                        <p class="whitespace-pre-wrap"><?php echo e($task['description']); ?></p>
                    <?php else: ?>
                        <p class="text-gray-400 italic">No description provided</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Subtasks -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        Subtasks
                    </h3>
                    <span class="text-sm text-gray-500"><?php echo count($completedSubtasks); ?>/<?php echo count($subtasks); ?> completed</span>
                </div>
                <?php if (!empty($subtasks)): ?>
                    <div class="space-y-3">
                        <?php foreach ($subtasks as $index => $subtask):
                            // Calculate deadline badge for subtask
                            $subtaskBadge = '';
                            if (!($subtask['completed'] ?? false) && !empty($subtask['dueDate'])) {
                                $now = new DateTime();
                                $dueDate = new DateTime($subtask['dueDate']);
                                $diff = $dueDate->getTimestamp() - $now->getTimestamp();
                                $minutesDiff = floor($diff / 60);

                                if ($diff < 0) {
                                    $subtaskBadge = 'overdue';
                                } elseif ($minutesDiff <= 15) {
                                    $subtaskBadge = 'due-soon';
                                } elseif ($diff <= 3600) {
                                    $subtaskBadge = 'not-started';
                                }
                            }
                        ?>
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg" id="subtask-<?php echo e($subtask['id']); ?>" data-subtask-id="<?php echo e($subtask['id']); ?>" data-due-date="<?php echo e($subtask['dueDate'] ?? ''); ?>">
                                <input type="checkbox"
                                       class="w-5 h-5 rounded-lg border-2 border-gray-200 text-black focus:ring-black cursor-pointer subtask-checkbox"
                                       data-subtask-id="<?php echo e($subtask['id']); ?>"
                                       <?php echo ($subtask['completed'] ?? false) ? 'checked' : ''; ?>
                                       onchange="toggleSubtask('<?php echo e($taskId); ?>', '<?php echo e($projectId); ?>', '<?php echo e($subtask['id']); ?>', this.checked)">
                                <span class="flex-1 <?php echo ($subtask['completed'] ?? false) ? 'line-through text-gray-400' : 'text-gray-900'; ?> subtask-title">
                                    <?php echo e($subtask['title']); ?>
                                </span>

                                <!-- Deadline badge for subtask -->
                                <?php if ($subtaskBadge): ?>
                                    <span class="px-2 py-1 text-xs font-bold rounded-lg <?php
                                        echo match($subtaskBadge) {
                                            'overdue' => 'bg-red-100 text-red-700',
                                            'due-soon' => 'bg-orange-100 text-orange-700',
                                            'not-started' => 'bg-yellow-100 text-yellow-700',
                                            default => ''
                                        };
                                    ?>" id="badge-<?php echo e($subtask['id']); ?>">
                                        <?php echo match($subtaskBadge) {
                                            'overdue' => '⏰ OVERDUE',
                                            'due-soon' => '⚠️ DUE SOON',
                                            'not-started' => '📌 NOT STARTED',
                                            default => ''
                                        }; ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($subtask['estimatedMinutes'])): ?>
                                    <span class="text-xs text-gray-400"><?php echo formatMinutes($subtask['estimatedMinutes']); ?></span>
                                <?php endif; ?>
                                <!-- Timer button for subtask -->
                                <button onclick="toggleSubtaskTimer('<?php echo e($subtask['id']); ?>', '<?php echo e($taskId); ?>', '<?php echo e($projectId); ?>')"
                                        class="p-2 text-gray-400 hover:text-black hover:bg-gray-200 rounded-lg transition timer-btn"
                                        id="timer-btn-<?php echo e($subtask['id']); ?>"
                                        title="Start/Stop timer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                                <!-- Timer display (hidden by default) -->
                                <span class="text-xs font-mono text-gray-500 timer-display hidden" id="timer-display-<?php echo e($subtask['id']); ?>">00:00</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400 italic">No subtasks</p>
                <?php endif; ?>
            </div>

            <!-- Time Entries -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Time Tracking
                    </h3>
                    <button onclick="openLogTimeModal()" class="px-3 py-1.5 bg-black text-white text-xs font-bold rounded-lg hover:bg-gray-800 transition">
                        + Log Time
                    </button>
                </div>
                <?php if (!empty($timeEntries)): ?>
                    <div class="space-y-3">
                        <?php foreach ($timeEntries as $entry): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo e($entry['description'] ?? 'Time entry'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo formatDate($entry['date'] ?? date('Y-m-d')); ?></p>
                                </div>
                                <span class="font-bold text-gray-700"><?php echo formatMinutes($entry['minutes'] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between">
                        <span class="font-bold text-gray-600">Total Time</span>
                        <span class="font-bold text-gray-900"><?php echo formatMinutes($actualMinutes); ?></span>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-400 italic mb-3">No time entries yet</p>
                        <div class="text-left bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
                            <p class="font-bold text-gray-900 mb-2">How to track time:</p>
                            <ul class="space-y-1">
                                <li>• <strong>Timer:</strong> Click ⏱️ on subtasks while working</li>
                                <li>• <strong>Manual:</strong> Click "+ Log Time" to add past work</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log Time Modal -->
        <div id="log-time-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
            <div class="bg-white rounded-2xl p-6 w-full max-w-md mx-4">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Log Time</h3>
                    <button onclick="closeLogTimeModal()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <form onsubmit="submitLogTime(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Time Spent</label>
                            <div class="flex gap-3">
                                <input type="number" id="log-time-hours" min="0" max="24" value="0" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors" placeholder="Hours">
                                <input type="number" id="log-time-minutes" min="0" max="59" value="30" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors" placeholder="Minutes">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Description</label>
                            <input type="text" id="log-time-description" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors" placeholder="What did you work on?">
                        </div>
                        <button type="submit" class="w-full py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition">
                            Log Time
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-span-1 space-y-6">
            <!-- Project Info -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                    Project
                </h3>
                <a href="?page=projects" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="w-8 h-8 rounded-lg" style="background-color: <?php echo $project['color'] ?? '#3B82F6'; ?>"></div>
                    <span class="font-medium text-gray-900"><?php echo e($project['name']); ?></span>
                </a>
            </div>

            <!-- Due Date -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Due Date
                </h3>
                <?php if (!empty($task['dueDate'])): ?>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="font-medium text-gray-900"><?php echo formatDate($task['dueDate']); ?></p>
                        <p class="text-xs text-gray-500 mt-1"><?php echo timeAgo($task['dueDate']); ?></p>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400 italic">No due date</p>
                <?php endif; ?>
            </div>

            <!-- Time Estimate -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Time Estimate
                </h3>
                <div class="space-y-3">
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Estimated</span>
                        <span class="font-bold text-gray-900"><?php echo formatMinutes($estimatedMinutes); ?></span>
                    </div>
                    <div class="flex justify-between p-3 bg-gray-50 rounded-lg">
                        <span class="text-gray-600">Actual</span>
                        <span class="font-bold text-gray-900"><?php echo formatMinutes($actualMinutes); ?></span>
                    </div>
                    <?php if ($estimatedMinutes > 0): ?>
                        <div class="pt-2">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-500">Progress</span>
                                <span class="text-gray-600"><?php echo min(100, round($actualMinutes / $estimatedMinutes * 100)); ?>%</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full bg-black rounded-full transition-all" style="width: <?php echo min(100, $actualMinutes / $estimatedMinutes * 100); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dates -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h3 class="font-bold text-gray-900 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Details
                </h3>
                <div class="space-y-3 text-sm">
                    <?php if (!empty($task['recurrence']['enabled'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Recurs</span>
                            <span class="text-gray-900">
                                Every <?php echo (int)($task['recurrence']['interval'] ?? 1); ?>
                                <?php echo e($task['recurrence']['frequency'] ?? 'week'); ?>(s)
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Created</span>
                        <span class="text-gray-900"><?php echo formatDate($task['createdAt'] ?? date('Y-m-d')); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Updated</span>
                        <span class="text-gray-900"><?php echo formatDate($task['updatedAt'] ?? date('Y-m-d')); ?></span>
                    </div>
                    <?php if (isTaskDone($task['status'] ?? '') && !empty($task['completedAt'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Completed</span>
                            <span class="text-gray-900"><?php echo formatDate($task['completedAt']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Subtask timer tracking
const activeTimers = {};
let taskSubtasks = <?php echo json_encode($subtasks); ?>;

// Toggle subtask completion
async function toggleSubtask(taskId, projectId, subtaskId, completed) {
    try {
        // Get current task to get subtasks
        const response = await api.get(`api/tasks.php?id=${taskId}`);
        if (!response.success) {
            showToast('Failed to load task', 'error');
            return;
        }

        const task = response.data;
        const subtasks = task.subtasks || [];

        // Find subtask by ID or by index (for backward compatibility with old data)
        let subtaskIndex = -1;

        // First try to find by ID
        subtaskIndex = subtasks.findIndex(s => s.id === subtaskId);

        // If not found by ID, try to find by generated ID format (subtask-index)
        if (subtaskIndex === -1 && subtaskId.startsWith('subtask-')) {
            const index = parseInt(subtaskId.replace('subtask-', ''));
            if (!isNaN(index) && index < subtasks.length) {
                subtaskIndex = index;
            }
        }

        if (subtaskIndex === -1) {
            console.error('Subtask not found. ID:', subtaskId, 'Available subtasks:', subtasks);
            showToast('Subtask not found', 'error');
            return;
        }

        subtasks[subtaskIndex].completed = completed;

        // Update task with modified subtasks
        const updateResponse = await api.put(`api/tasks.php?id=${taskId}`, {
            subtasks: subtasks,
            csrf_token: CSRF_TOKEN
        });

        if (updateResponse.success) {
            // Update UI
            const row = document.getElementById(`subtask-${subtaskId}`);
            const title = row.querySelector('.subtask-title');
            const checkbox = row.querySelector('.subtask-checkbox');

            if (completed) {
                title.classList.add('line-through', 'text-gray-400');
                title.classList.remove('text-gray-900');
            } else {
                title.classList.remove('line-through', 'text-gray-400');
                title.classList.add('text-gray-900');
            }

            // Update counter
            updateSubtaskCounter();
            showToast('Subtask updated', 'success');
        } else {
            showToast(updateResponse.error || 'Failed to update subtask', 'error');
            // Revert checkbox
            checkbox.checked = !completed;
        }
    } catch (error) {
        console.error('Error updating subtask:', error);
        showToast('Failed to update subtask', 'error');
    }
}

// Update subtask counter
function updateSubtaskCounter() {
    const checkboxes = document.querySelectorAll('.subtask-checkbox');
    const total = checkboxes.length;
    const completed = Array.from(checkboxes).filter(cb => cb.checked).length;
    document.querySelector('.text-gray-500.text-sm').textContent = `${completed}/${total} completed`;
}

// Subtask timer functions with Phase 5 enhancements
async function toggleSubtaskTimer(subtaskId, taskId, projectId) {
    if (activeTimers[subtaskId]) {
        // Stop the timer
        stopSubtaskTimer(subtaskId, taskId);
    } else {
        // Start the timer
        startSubtaskTimer(subtaskId, taskId);
    }
}

function startSubtaskTimer(subtaskId, taskId) {
    const startTime = Date.now();

    // Request notification permission
    if (App.notifications.checkPermission() === 'default') {
        App.notifications.requestPermission();
    }

    // Get subtask's estimated time from taskSubtasks
    const subtask = taskSubtasks.find(s => s.id === subtaskId);
    const estimatedMinutes = subtask?.estimatedMinutes || 0;

    const display = document.getElementById(`timer-display-${subtaskId}`);
    const btn = document.getElementById(`timer-btn-${subtaskId}`);

    // Show timer display (Phase 5: only show when running)
    display.classList.remove('hidden');
    display.textContent = '00:00';

    // Update button to show stop icon
    btn.innerHTML = `<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
        <rect x="6" y="6" width="12" height="12" fill="currentColor"></rect>
    </svg>`;
    btn.classList.remove('text-gray-400');
    btn.classList.add('text-red-500');

    // Calculate target time in milliseconds (0 means no limit)
    const targetTimeMs = estimatedMinutes > 0 ? estimatedMinutes * 60 * 1000 : 0;

    // Store timer in localStorage for Phase 4 smart alerts
    const timerKey = `subtask_timer_${subtaskId}`;
    localStorage.setItem(timerKey, JSON.stringify({
        running: true,
        startTime: startTime,
        estimatedMinutes: estimatedMinutes
    }));

    // Store timer
    activeTimers[subtaskId] = {
        taskId: taskId,
        projectId: null,
        startTime: startTime,
        estimatedMinutes: estimatedMinutes,
        interval: setInterval(() => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const seconds = (elapsed % 60).toString().padStart(2, '0');
            display.textContent = `${minutes}:${seconds}`;

            // Auto-stop when reaching estimated time
            if (targetTimeMs > 0 && (Date.now() - startTime) >= targetTimeMs) {
                const subtaskTitle = subtask?.title || 'Subtask';
                stopSubtaskTimer(subtaskId, taskId);

                App.notifications.send(`Time Up: ${subtaskTitle}`, {
                    body: `Estimated time of ${estimatedMinutes} minutes reached.`,
                    requireInteraction: true
                });
                showToast(`Timer stopped at ${estimatedMinutes} minutes`, 'info');
            }
        }, 1000)
    };
}

function stopSubtaskTimer(subtaskId, taskId) {
    const timer = activeTimers[subtaskId];
    if (!timer) return;

    clearInterval(timer.interval);

    const elapsedSeconds = Math.floor((Date.now() - timer.startTime) / 1000);
    const elapsedMinutes = Math.round(elapsedSeconds / 60);

    // Log time entry
    if (elapsedMinutes >= 1) {
        logTimeEntry(taskId, elapsedMinutes, `Subtask time`);
    }

    // Reset UI - Phase 5: Hide timer display when stopped
    const display = document.getElementById(`timer-display-${subtaskId}`);
    const btn = document.getElementById(`timer-btn-${subtaskId}`);

    display.classList.add('hidden');
    btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>`;
    btn.classList.remove('text-red-500');
    btn.classList.add('text-gray-400');

    // Clear timer from localStorage (Phase 5)
    const timerKey = `subtask_timer_${subtaskId}`;
    localStorage.removeItem(timerKey);

    delete activeTimers[subtaskId];

    if (elapsedMinutes >= 1) {
        showToast(`Logged ${elapsedMinutes} minutes`, 'success');
        // Refresh page to show new time entry
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}

async function logTimeEntry(taskId, minutes, description) {
    try {
        const response = await api.put(`api/tasks.php?id=${taskId}`, {
            timeEntries: {
                date: new Date().toISOString(),
                minutes: minutes,
                description: description
            },
            addTimeEntry: true,
            csrf_token: CSRF_TOKEN
        });

        if (!response.success) {
            console.error('Failed to log time:', response.error);
        }
    } catch (error) {
        console.error('Error logging time:', error);
    }
}

async function deleteTask(taskId, projectId) {
    try {
        const response = await api.delete(`api/tasks.php?id=${taskId}&projectId=${projectId}`);
        if (response.success) {
            showToast('Task deleted', 'success');
            setTimeout(() => {
                window.location.href = '?page=tasks';
            }, 500);
        } else {
            showToast(response.error || 'Failed to delete task', 'error');
        }
    } catch (error) {
        showToast('Failed to delete task', 'error');
    }
}

// Log Time Modal
function openLogTimeModal() {
    document.getElementById('log-time-modal').classList.remove('hidden');
    document.getElementById('log-time-modal').classList.add('flex');
    document.getElementById('log-time-hours').value = 0;
    document.getElementById('log-time-minutes').value = 30;
    document.getElementById('log-time-description').value = '';
    document.getElementById('log-time-hours').focus();
}

function closeLogTimeModal() {
    document.getElementById('log-time-modal').classList.add('hidden');
    document.getElementById('log-time-modal').classList.remove('flex');
}

/**
 * Phase 5: Update sub-task deadline badges every 10 seconds
 * Similar to main task badge system
 */
function updateSubtaskDeadlineBadges() {
    const now = new Date();
    const subtaskElements = document.querySelectorAll('[data-subtask-id][data-due-date]');

    subtaskElements.forEach(element => {
        const dueDate = element.getAttribute('data-due-date');
        const subtaskId = element.getAttribute('data-subtask-id');

        if (!dueDate) return;

        const checkbox = element.querySelector('.subtask-checkbox');
        if (checkbox?.checked) return; // Skip completed subtasks

        const due = new Date(dueDate);
        const diff = due.getTime() - now.getTime();
        const minutesDiff = Math.floor(diff / 60000);

        let badgeType = null;
        if (diff < 0) {
            badgeType = 'overdue';
        } else if (minutesDiff <= 15) {
            badgeType = 'due-soon';
        } else if (diff <= 3600000) {
            badgeType = 'not-started';
        }

        const badgeElement = element.querySelector(`#badge-${subtaskId}`);

        if (badgeType && !badgeElement) {
            // Create badge if it doesn't exist
            const badge = document.createElement('span');
            badge.id = `badge-${subtaskId}`;
            badge.className = `px-2 py-1 text-xs font-bold rounded-lg ${
                badgeType === 'overdue' ? 'bg-red-100 text-red-700' :
                badgeType === 'due-soon' ? 'bg-orange-100 text-orange-700' :
                'bg-yellow-100 text-yellow-700'
            }`;
            badge.textContent = badgeType === 'overdue' ? '⏰ OVERDUE' :
                               badgeType === 'due-soon' ? '⚠️ DUE SOON' :
                               '📌 NOT STARTED';

            const estimatedSpan = element.querySelector('.text-xs.text-gray-400');
            if (estimatedSpan) {
                estimatedSpan.parentNode.insertBefore(badge, estimatedSpan);
            }
        } else if (!badgeType && badgeElement) {
            // Remove badge if no longer needed
            badgeElement.remove();
        } else if (badgeElement && badgeType) {
            // Update badge class if type changed
            const newClass = badgeType === 'overdue' ? 'bg-red-100 text-red-700' :
                           badgeType === 'due-soon' ? 'bg-orange-100 text-orange-700' :
                           'bg-yellow-100 text-yellow-700';
            badgeElement.className = `px-2 py-1 text-xs font-bold rounded-lg ${newClass}`;
        }
    });
}

// Initialize badge updates every 10 seconds
let subtaskBadgeInterval = setInterval(updateSubtaskDeadlineBadges, 10000);

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    if (subtaskBadgeInterval) {
        clearInterval(subtaskBadgeInterval);
    }
});

async function submitLogTime(event) {
    event.preventDefault();

    const hours = parseInt(document.getElementById('log-time-hours').value) || 0;
    const minutes = parseInt(document.getElementById('log-time-minutes').value) || 0;
    const description = document.getElementById('log-time-description').value.trim() || 'Manual time entry';

    const totalMinutes = hours * 60 + minutes;

    if (totalMinutes <= 0) {
        showToast('Please enter a valid time', 'error');
        return;
    }

    try {
        const response = await api.put(`api/tasks.php?id=<?php echo $taskId; ?>`, {
            timeEntries: {
                date: new Date().toISOString(),
                minutes: totalMinutes,
                description: description
            },
            addTimeEntry: true,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Logged ${hours}h ${minutes}m`, 'success');
            closeLogTimeModal();
            setTimeout(() => window.location.reload(), 500);
        } else {
            showToast(response.error || 'Failed to log time', 'error');
        }
    } catch (error) {
        showToast('Failed to log time', 'error');
    }
}

// Close modal on outside click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('log-time-modal');
    if (e.target === modal) {
        closeLogTimeModal();
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Update subtask counter on page load
    updateSubtaskCounter();
});
</script>

