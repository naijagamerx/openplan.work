<?php
/**
 * Project Form View (Add/Edit)
 */
$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;
$project = null;

if ($id) {
    $projects = $db->load('projects');
    foreach ($projects as $p) {
        if ($p['id'] === $id) {
            $project = $p;
            break;
        }
    }
}

$clients = $db->load('clients');
$title = $id ? 'Edit Project' : 'New Project';
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=projects" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="project-form" class="p-8 space-y-6">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Project Name -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">Project Name</label>
                    <input type="text" name="name" value="<?php echo e($project['name'] ?? ''); ?>" required
                           placeholder="e.g. Website Redesign 2024"
                           class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors">
                </div>

                <!-- Client Selection -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">Client</label>
                    <select name="clientId" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="">Select a client...</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo e($client['id']); ?>" <?php echo ($project['clientId'] ?? '') === $client['id'] ? 'selected' : ''; ?>>
                                <?php echo e($client['name']); ?> (<?php echo e($client['company'] ?? 'Individual'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">Status</label>
                    <select name="status" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="planning" <?php echo ($project['status'] ?? '') === 'planning' ? 'selected' : ''; ?>>Planning</option>
                        <option value="in_progress" <?php echo ($project['status'] ?? 'in_progress') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="on_hold" <?php echo ($project['status'] ?? '') === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                        <option value="completed" <?php echo ($project['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <!-- Dates -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">Start Date</label>
                    <input type="date" name="startDate" value="<?php echo e($project['startDate'] ?? date('Y-m-d')); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">Due Date</label>
                    <input type="date" name="dueDate" value="<?php echo e($project['dueDate'] ?? ''); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-gray-50">
                </div>

                <!-- Budget -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">Budget</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-medium">$</span>
                        <input type="number" name="budget" value="<?php echo e($project['budget'] ?? ''); ?>" step="0.01"
                               placeholder="0.00"
                               class="w-full pl-8 pr-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors">
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-semibold text-gray-700 uppercase tracking-wide">Description</label>
                    <button type="button" onclick="generateDescriptionWithAI()" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 uppercase tracking-tighter">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16a1 1 0 01-1 1H6a1 1 0 110-2h1a1 1 0 011 1zM14.243 16.364a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM10 18a1 1 0 100-2v-1a1 1 0 100 2v1z"></path></svg>
                        AI Help
                    </button>
                </div>
                <textarea name="description" rows="6" 
                          placeholder="Describe the project scope, goals, and requirements..."
                          class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors min-h-[150px]"><?php echo e($project['description'] ?? ''); ?></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-4 pt-6 border-t border-gray-100">
                <a href="?page=projects" class="px-8 py-3 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">Cancel</a>
                <button type="submit" class="px-10 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg">
                    <?php echo $id ? 'Update Project' : 'Create Project'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('project-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const url = data.id ? 'api/projects.php?action=update&id=' + data.id : 'api/projects.php?action=add';
    
    // Using global api helper from app.js
    const response = await api.post(url, data);
    
    if (response.success) {
        showToast(data.id ? 'Project updated!' : 'Project created!', 'success');
        setTimeout(() => location.href = '?page=projects', 1000);
    } else {
        showToast(response.error || 'Failed to save project', 'error');
    }
});

async function generateDescriptionWithAI() {
    const nameInput = document.querySelector('input[name="name"]');
    const descArea = document.querySelector('textarea[name="description"]');
    
    if (!nameInput.value) {
        showToast('Please enter a project name first', 'info');
        return;
    }
    
    showToast('Generating description...', 'info');
    
    const response = await api.post('api/ai-generate.php?action=project', {
        idea: nameInput.value
    });
    
    if (response.success && response.data?.description) {
        descArea.value = response.data.description;
        showToast('AI description generated!', 'success');
    } else {
        showToast('AI generation failed', 'error');
    }
}
</script>
