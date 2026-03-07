<?php
$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;
$habit = null;

if ($id) {
    $habits = $db->load('habits');
    foreach ($habits as $h) {
        if ($h['id'] === $id) {
            $habit = $h;
            break;
        }
    }
}

$title = $id ? 'Edit Habit' : 'New Habit';
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=habits" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
            <p class="text-gray-500 font-medium tracking-tight">Build better daily routines</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="habit-form" class="p-8 space-y-8">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Habit Name -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Habit Name</label>
                    <input type="text" name="name" value="<?php echo e($habit['name'] ?? ''); ?>" required
                           placeholder="e.g., Morning meditation, Exercise, Read 30 mins"
                           class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-black outline-none transition-all text-lg font-medium">
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Category</label>
                    <select name="category" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-gray-50">
                        <option value="">Select a category...</option>
                        <option value="health" <?php echo ($habit['category'] ?? '') === 'health' ? 'selected' : ''; ?>>Health & Fitness</option>
                        <option value="productivity" <?php echo ($habit['category'] ?? '') === 'productivity' ? 'selected' : ''; ?>>Productivity</option>
                        <option value="mindfulness" <?php echo ($habit['category'] ?? '') === 'mindfulness' ? 'selected' : ''; ?>>Mindfulness</option>
                        <option value="learning" <?php echo ($habit['category'] ?? '') === 'learning' ? 'selected' : ''; ?>>Learning</option>
                        <option value="social" <?php echo ($habit['category'] ?? '') === 'social' ? 'selected' : ''; ?>>Social</option>
                        <option value="general" <?php echo ($habit['category'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                    </select>
                </div>

                <!-- Frequency -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Frequency</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="frequency" value="daily" class="peer hidden"
                                   <?php echo ($habit['frequency'] ?? 'daily') === 'daily' ? 'checked' : ''; ?>>
                            <div class="py-2 text-center text-xs font-bold uppercase tracking-tighter rounded-lg border-2 border-gray-100 peer-checked:bg-black peer-checked:text-white peer-checked:border-black transition-all">
                                Daily
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="frequency" value="once" class="peer hidden"
                                   <?php echo ($habit['frequency'] ?? '') === 'once' ? 'checked' : ''; ?>>
                            <div class="py-2 text-center text-xs font-bold uppercase tracking-tighter rounded-lg border-2 border-gray-100 peer-checked:bg-black peer-checked:text-white peer-checked:border-black transition-all">
                                Once
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Reminder Time -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Reminder Time</label>
                    <input type="time" name="reminderTime" value="<?php echo e($habit['reminderTime'] ?? ''); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-gray-50">
                </div>

                <!-- AI Generated Flag -->
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Source</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer">
                            <input type="radio" name="isAiGenerated" value="0" class="peer hidden"
                                   <?php echo !($habit['isAiGenerated'] ?? false) ? 'checked' : ''; ?>>
                            <div class="py-2 text-center text-xs font-bold uppercase tracking-tighter rounded-lg border-2 border-gray-100 peer-checked:bg-black peer-checked:text-white peer-checked:border-black transition-all">
                                Manual
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="isAiGenerated" value="1" class="peer hidden"
                                   <?php echo ($habit['isAiGenerated'] ?? false) ? 'checked' : ''; ?>>
                            <div class="py-2 text-center text-xs font-bold uppercase tracking-tighter rounded-lg border-2 border-gray-100 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 transition-all">
                                AI Suggested
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- AI Suggestions Section -->
            <div class="pt-8 border-t border-gray-100">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 tracking-tight">AI Suggestions</h3>
                    <button type="button" onclick="generateHabitSuggestions()" class="px-4 py-2 border-2 border-gray-100 rounded-xl text-xs font-black uppercase tracking-widest hover:border-black transition-all flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Generate Habits
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Your Goals</label>
                    <input type="text" id="habit-goals" placeholder="e.g., Improve fitness, learn new skills, be more productive"
                           class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-gray-50">
                </div>

                <div id="suggestions-container" class="space-y-3">
                    <div class="text-center py-8 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                        <p class="text-sm text-gray-400 font-medium">Enter your goals and click "Generate Habits"</p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-4 pt-8 border-t border-gray-100">
                <a href="?page=habits" class="px-8 py-3 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition">Cancel</a>
                <button type="submit" class="px-10 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg">
                    <?php echo $id ? 'Update Habit' : 'Create Habit'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
async function generateHabitSuggestions() {
    const goals = document.getElementById('habit-goals').value.trim();
    if (!goals) {
        showToast('Please enter your goals first', 'info');
        return;
    }

    showToast('AI is generating habits...', 'info');

    const modelsResponse = await api.get('api/models.php');
    const models = modelsResponse.data || {};
    const provider = 'groq';
    const groqModels = models?.groq || [];
    const defaultModel = groqModels.find(m => m.isDefault) || groqModels[0];
    const model = defaultModel?.modelId || 'llama-3.3-70b-versatile';

    const response = await api.post('api/ai.php?action=suggest_habits', {
        goals: goals,
        provider: provider,
        model: model,
        csrf_token: CSRF_TOKEN
    });

    if (Array.isArray(response.data)) {
        renderSuggestions(response.data);
        showToast('Habits generated!', 'success');
    } else {
        showToast('Failed to generate habits', 'error');
    }
}

function renderSuggestions(habits) {
    const container = document.getElementById('suggestions-container');
    container.innerHTML = '';

    habits.forEach((habit, index) => {
        const div = document.createElement('div');
        div.className = 'suggestion-item flex items-center gap-3 p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition cursor-pointer';
        div.innerHTML = `
            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold ${getCategoryColor(habit.category)}">
                ${habit.category.charAt(0).toUpperCase()}
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-900 text-sm">${habit.name}</p>
                <p class="text-xs text-gray-500 uppercase tracking-widest">${habit.category} • ${habit.reminderTime || 'No reminder'}</p>
            </div>
            <button type="button" onclick="useSuggestion(${index})" class="px-3 py-1 bg-black text-white text-xs font-bold uppercase rounded-lg hover:bg-gray-800 transition">
                Use This
            </button>
        `;
        container.appendChild(div);
    });

    window.suggestedHabits = habits;
}

function getCategoryColor(category) {
    const colors = {
        health: 'bg-green-500',
        productivity: 'bg-blue-500',
        mindfulness: 'bg-purple-500',
        learning: 'bg-yellow-500',
        social: 'bg-pink-500',
        general: 'bg-gray-500'
    };
    return colors[category] || colors.general;
}

function useSuggestion(index) {
    const habit = window.suggestedHabits[index];
    const form = document.getElementById('habit-form');

    form.querySelector('[name="name"]').value = habit.name;
    form.querySelector('[name="category"]').value = habit.category;
    form.querySelector('[name="frequency"]').value = habit.frequency;
    form.querySelector('[name="reminderTime"]').value = habit.reminderTime || '';
    form.querySelector('[name="isAiGenerated"][value="1"]').checked = true;

    showToast('Habit applied to form!', 'success');
}

document.getElementById('habit-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    data.isAiGenerated = data.isAiGenerated === '1';

    const url = data.id ? 'api/habits.php?action=update&id=' + data.id : 'api/habits.php?action=add';
    const response = await api.post(url, data);

    if (response.success) {
        showToast(data.id ? 'Habit updated!' : 'Habit created!', 'success');
        setTimeout(() => location.href = '?page=habits', 1000);
    } else {
        showToast(response.error || 'Failed to save habit', 'error');
    }
});
</script>
