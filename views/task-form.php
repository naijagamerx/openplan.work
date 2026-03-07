<?php
/**
 * Task Form View (Add/Edit)
 */
$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;
$projectId = $_GET['project_id'] ?? null;
$task = null;
$project = null;

$projects = $db->load('projects');

if ($id) {
    foreach ($projects as $idx => $p) {
        if (isset($p['tasks'])) {
            foreach ($p['tasks'] as $t) {
                if ($t['id'] === $id) {
                    $task = $t;
                    $projectId = $p['id'];
                    $project = $p;
                    break 2;
                }
            }
        }
    }
}

if ($projectId && !$project) {
    foreach ($projects as $p) {
        if ($p['id'] === $projectId) {
            $project = $p;
            break;
        }
    }
}

$title = $id ? 'Edit Task' : 'New Task';
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=tasks<?php echo $projectId ? '&project=' . $projectId : ''; ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
            <?php if ($project): ?>
                <p class="text-gray-500 font-medium tracking-tight">Project: <?php echo e($project['name']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="task-form" class="p-8 space-y-8">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Task Title -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Task Title</label>
                    <input type="text" name="title" value="<?php echo e($task['title'] ?? ''); ?>" required
                           placeholder="What needs to be done?"
                           class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-black outline-none transition-all text-lg font-medium">
                </div>

                <!-- Project Selection -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Project</label>
                    <select name="projectId" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo e($p['id']); ?>" <?php echo ($projectId === $p['id']) ? 'selected' : ''; ?>>
                                <?php echo e($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Priority</label>
                    <div class="grid grid-cols-4 gap-2">
                        <?php foreach (['low', 'medium', 'high', 'urgent'] as $prio): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="priority" value="<?php echo $prio; ?>" class="peer hidden" 
                                       <?php echo ($task['priority'] ?? 'medium') === $prio ? 'checked' : ''; ?>>
                                <div class="py-2 text-center text-xs font-bold uppercase tracking-tighter rounded-lg border-2 border-gray-100 peer-checked:bg-black peer-checked:text-white peer-checked:border-black transition-all">
                                    <?php echo ucfirst($prio); ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Due Date -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Due Date</label>
                    <input type="date" name="dueDate" value="<?php echo e($task['dueDate'] ?? ''); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-gray-50">
                </div>

                <!-- Estimate -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Estimate (Minutes)</label>
                    <div class="relative">
                        <input type="number" name="estimatedMinutes" value="<?php echo e($task['estimatedMinutes'] ?? '60'); ?>"
                               class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-gray-50">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-bold">MINS</span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Description</label>
                    <button type="button" onclick="improveDescriptionWithAI()" class="text-xs font-black text-blue-600 hover:text-blue-800 flex items-center gap-1 uppercase tracking-tighter">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        AI Improve
                    </button>
                </div>
                <textarea name="description" rows="4" 
                          placeholder="Add more details about this task..."
                          class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-all min-h-[100px]"><?php echo e($task['description'] ?? ''); ?></textarea>
            </div>

            <!-- Subtasks Section -->
            <div class="pt-8 border-t border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 tracking-tight">Subtasks</h3>
                    <button type="button" onclick="generateSubtasksWithAI()" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-black uppercase tracking-widest hover:border-black transition-all flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        AI Breakdown
                    </button>
                </div>
                
                <div id="subtasks-container" class="space-y-3">
                    <?php 
                    $subtasks = $task['subtasks'] ?? [];
                    if (empty($subtasks)): ?>
                        <div id="no-subtasks" class="text-center py-8 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                            <p class="text-sm text-gray-400 font-medium">No subtasks added yet</p>
                        </div>
                    <?php else: foreach ($subtasks as $st): ?>
                        <div class="subtask-row flex items-center gap-3 p-3 bg-gray-50 rounded-xl group">
                            <input type="text" name="subtask_titles[]" value="<?php echo e($st['title']); ?>" 
                                   class="flex-1 bg-transparent border-none focus:ring-0 text-sm font-medium">
                            <input type="number" name="subtask_minutes[]" value="<?php echo e($st['estimatedMinutes'] ?? 0); ?>" 
                                   class="w-20 bg-white border border-gray-200 rounded-lg px-2 py-1 text-xs text-right">
                            <button type="button" onclick="this.closest('.subtask-row').remove()" class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                
                <button type="button" onclick="addSubtaskRow()" class="mt-4 flex items-center gap-2 text-sm font-black text-gray-400 hover:text-black transition-colors uppercase tracking-widest">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Add Subtask manually
                </button>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-4 pt-8 border-t border-gray-100">
                <a href="?page=tasks<?php echo $projectId ? '&project=' . $projectId : ''; ?>" class="px-8 py-3 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">Cancel</a>
                <button type="submit" class="px-10 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg">
                    <?php echo $id ? 'Update Task' : 'Create Task'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function addSubtaskRow(title = '', mins = 0) {
    const container = document.getElementById('subtasks-container');
    const noSubtasks = document.getElementById('no-subtasks');
    if (noSubtasks) noSubtasks.remove();
    
    const div = document.createElement('div');
    div.className = 'subtask-row flex items-center gap-3 p-3 bg-gray-50 rounded-xl group animate-fade-in text-sm font-medium';
    div.innerHTML = `
        <input type="text" name="subtask_titles[]" value="${title}" placeholder="Subtask name..." required
               class="flex-1 bg-transparent border-none focus:ring-0">
        <input type="number" name="subtask_minutes[]" value="${mins}" placeholder="Time"
               class="w-20 bg-white border border-gray-200 rounded-lg px-2 py-1 text-xs text-right">
        <button type="button" onclick="this.closest('.subtask-row').remove()" class="text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </button>
    `;
    container.appendChild(div);
}

document.getElementById('task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Manual subtask handling
    const titles = formData.getAll('subtask_titles[]');
    const minutes = formData.getAll('subtask_minutes[]');
    data.subtasks = titles.map((title, i) => ({
        title,
        estimatedMinutes: parseInt(minutes[i] || 0)
    }));
    
    const url = data.id ? 'api/tasks.php?action=update&id=' + data.id : 'api/tasks.php?action=add';
    const response = await api.post(url, data);
    
    if (response.success) {
        showToast(data.id ? 'Task updated!' : 'Task created!', 'success');
        setTimeout(() => location.href = '?page=tasks&project=' + data.projectId, 1000);
    } else {
        showToast(response.error || 'Failed to save task', 'error');
    }
});

async function improveDescriptionWithAI() {
    const titleInput = document.querySelector('input[name="title"]');
    const descArea = document.querySelector('textarea[name="description"]');
    
    if (!titleInput.value) {
        showToast('Please enter a task title first', 'info');
        return;
    }
    
    showToast('AI is thinking...', 'info');
    
    const response = await api.post('api/ai-generate.php?action=subtasks', {
        title: titleInput.value,
        description: descArea.value
    });
    
    if (response.success && response.data) {
        // AI response for subtasks can be used to improve description too
        descArea.value += "\n\nKey focuses:\n- " + (response.data.map(st => st.title).join('\n- '));
        showToast('Description improved!', 'success');
    }
}

async function generateSubtasksWithAI() {
    const titleInput = document.querySelector('input[name="title"]');
    const descArea = document.querySelector('textarea[name="description"]');
    
    if (!titleInput.value) {
        showToast('Please enter a task title first', 'info');
        return;
    }
    
    showToast('Breaking down task...', 'info');
    
    const response = await api.post('api/ai-generate.php?action=subtasks', {
        title: titleInput.value,
        description: descArea.value
    });
    
    if (response.success && Array.isArray(response.data)) {
        response.data.forEach(st => {
            addSubtaskRow(st.title, st.estimatedMinutes || 0);
        });
        showToast('Subtasks generated!', 'success');
    } else {
        showToast('AI failed to generate subtasks', 'error');
    }
}
</script>
