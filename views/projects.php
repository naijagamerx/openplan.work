<?php
// Projects View
$db = new Database(getMasterPassword(), Auth::userId());
$projects = $db->load('projects');
$clients = $db->load('clients');

$statusLabels = [
    'planning' => 'Planning',
    'active' => 'Active',
    'in_progress' => 'In Progress',
    'review' => 'Review',
    'completed' => 'Completed',
    'on_hold' => 'On Hold',
    'cancelled' => 'Cancelled'
];
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <p class="text-gray-500"><?php echo count($projects); ?> projects</p>
        <a href="?page=project-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Project
        </a>
    </div>
    
    <!-- Projects Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (empty($projects)): ?>
            <div class="col-span-full text-center py-20 bg-white rounded-2xl border-2 border-dashed border-gray-200">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">No projects yet</h3>
                <p class="text-gray-500 mt-2">Start by creating your first project</p>
                <a href="?page=project-form" class="mt-6 inline-block px-6 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">Create Project →</a>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $project): 
                $tasks = $project['tasks'] ?? [];
                $totalTasks = count($tasks);
                $completedTasks = count(array_filter($tasks, fn($t) => isTaskDone($t['status'] ?? '')));
                $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-xl transition-all group relative">
                    <div class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
                        <button onclick="deleteProject('<?php echo e($project['id']); ?>')" class="p-1.5 bg-gray-100 rounded-lg text-gray-500 hover:text-red-600 hover:bg-red-50 transition" title="Delete Project">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                        <a href="?page=view-project&id=<?php echo e($project['id']); ?>" class="p-1.5 bg-gray-100 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition" title="View Project">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </a>
                    </div>
                    <div class="flex items-start justify-between pr-10">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl font-black shadow-inner"
                                 style="background-color: <?php echo e($project['color'] ?? '#000'); ?>">
                                <?php echo strtoupper(substr($project['name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 group-hover:text-black transition-colors"><?php echo e($project['name']); ?></h3>
                                <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded shadow-sm <?php echo statusClass($project['status'] ?? 'active'); ?>">
                                    <?php echo $statusLabels[$project['status'] ?? 'active'] ?? 'Active'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($project['description'])): ?>
                        <p class="text-sm text-gray-500 mt-4 line-clamp-2 leading-relaxed"><?php echo e($project['description']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Progress -->
                    <div class="mt-6">
                        <div class="flex items-center justify-between text-xs font-bold mb-2">
                            <span class="text-gray-400 uppercase tracking-widest">Progress</span>
                            <span class="text-gray-900"><?php echo $progress; ?>%</span>
                        </div>
                        <div class="h-3 bg-gray-100 rounded-full overflow-hidden shadow-inner p-0.5">
                            <div class="h-full bg-black rounded-full transition-all duration-1000" style="width: <?php echo $progress; ?>%"></div>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="flex items-center gap-4 mt-6 text-xs font-bold text-gray-400 uppercase tracking-tighter">
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            <?php echo $totalTasks; ?> Tasks
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <?php echo $completedTasks; ?> Done
                        </span>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex gap-3 mt-6 pt-6 border-t border-gray-100">
                        <a href="?page=tasks&project=<?php echo e($project['id']); ?>" 
                           class="flex-1 text-center py-2.5 bg-gray-50 text-gray-900 rounded-xl text-xs font-bold hover:bg-gray-100 transition tracking-wide uppercase">
                            Tasks
                        </a>
                        <a href="?page=project-form&id=<?php echo e($project['id']); ?>"
                           class="px-5 py-2.5 border-2 border-gray-50 rounded-xl text-xs font-bold hover:bg-gray-50 transition tracking-wide uppercase">
                            Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Debug: dump project data to console
<?php foreach ($projects as $project): ?>
<?php endforeach; ?>

async function deleteProject(id) {
    if (!id) {
        showToast('Error: Project ID is missing', 'error');
        return;
    }
    confirmAction('Are you sure you want to delete this project? This will also delete all tasks.', async () => {
        const response = await api.delete(`api/projects.php?id=${id}`);
        if (response.success) {
            showToast('Project deleted', 'success');
            location.reload();
        }
    });
}
</script>

