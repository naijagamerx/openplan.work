<?php
/**
 * Mobile Habit Form Page
 *
 * Create/edit habits with a mobile-first form while keeping
 * shared mobile header/footer consistency.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$masterPassword = getMasterPassword();
if (empty($masterPassword)) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Session Error</h2>
        <p>Your session has expired or the master password is not available.</p>
        <p>Please <a href="?page=login">log in again</a>.</p>
    </body></html>');
}

try {
    $db = new Database($masterPassword, Auth::userId());
} catch (Exception $e) {
    die('<html><body style="font-family: sans-serif; padding: 20px; text-align: center;">
        <h2>Database Error</h2>
        <p>Failed to load data: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><a href="?page=habits">Back to Habits</a></p>
    </body></html>');
}

$habitId = trim((string)($_GET['id'] ?? ''));
$habit = null;
$habits = $db->load('habits') ?? [];

if ($habitId !== '') {
    foreach ($habits as $item) {
        if (($item['id'] ?? '') === $habitId) {
            $habit = $item;
            break;
        }
    }
}

$isEdit = is_array($habit);
$pageTitle = $isEdit ? 'Edit Habit' : 'New Habit';

$config = $db->load('config') ?? [];
$hasGroqKey = !empty($config['groqApiKey']);
$hasOpenRouterKey = !empty($config['openrouterApiKey']);

$models = [];
if (method_exists($db, 'safeLoad')) {
    $modelsLoad = $db->safeLoad('models');
    if (($modelsLoad['success'] ?? false) && is_array($modelsLoad['data'] ?? null)) {
        $models = $modelsLoad['data'];
    }
}

$findDefaultModel = function(array $providerModels): ?string {
    foreach ($providerModels as $model) {
        if (($model['enabled'] ?? false) && ($model['isDefault'] ?? false) && !empty($model['modelId'])) {
            return $model['modelId'];
        }
    }

    foreach ($providerModels as $model) {
        if (($model['enabled'] ?? false) && !empty($model['modelId'])) {
            return $model['modelId'];
        }
    }

    return null;
};

$defaultGroqModel = $findDefaultModel($models['groq'] ?? []);
$defaultOpenRouterModel = $findDefaultModel($models['openrouter'] ?? []);

$defaultAiProvider = '';
$defaultAiModel = '';
if ($hasGroqKey && $defaultGroqModel) {
    $defaultAiProvider = 'groq';
    $defaultAiModel = $defaultGroqModel;
} elseif ($hasOpenRouterKey && $defaultOpenRouterModel) {
    $defaultAiProvider = 'openrouter';
    $defaultAiModel = $defaultOpenRouterModel;
}

$hasAiSetup = $defaultAiProvider !== '' && $defaultAiModel !== '';
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($siteName) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png"/>
<link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png"/>
<link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico"/>
<link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png"/>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    "primary": "#000000",
                    "background-light": "#ffffff",
                    "background-dark": "#0a0a0a"
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                }
            }
        }
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-white text-black font-display antialiased;
        }
        input, select, textarea {
            @apply border border-gray-200 rounded-none focus:border-black focus:ring-0 text-sm py-3 px-4 w-full transition-colors;
        }
        label {
            @apply text-[10px] font-bold uppercase tracking-widest text-gray-500 mb-1 block;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .touch-target {
        min-height: 44px;
        min-width: 44px;
    }
    .form-section {
        @apply border-b border-gray-100 py-6;
    }
    .form-section:last-child {
        border-bottom: 0;
    }
    @supports (-webkit-touch-callout: none) {
        input, textarea, select {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col overflow-hidden">

<?php
$title = $pageTitle;
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-[240px]">
    <div class="px-6 pt-3 pb-1 border-b border-gray-100">
        <p class="text-[10px] text-gray-400">Build better daily routines</p>
    </div>

    <form id="habit-form" class="px-6">
        <input type="hidden" name="id" value="<?= htmlspecialchars($habit['id'] ?? '') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="isAiGenerated" id="is-ai-generated" value="<?= !empty($habit['isAiGenerated']) ? '1' : '0' ?>">

        <div class="form-section">
            <div class="space-y-5">
                <div>
                    <label for="habit-name">Habit Name</label>
                    <input
                        type="text"
                        name="name"
                        id="habit-name"
                        value="<?= htmlspecialchars($habit['name'] ?? '') ?>"
                        placeholder="e.g. Morning Meditation"
                        required
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="habit-category">Category</label>
                        <?php $habitCategory = strtolower((string)($habit['category'] ?? 'general')); ?>
                        <select name="category" id="habit-category" class="bg-white">
                            <option value="health" <?= $habitCategory === 'health' ? 'selected' : '' ?>>Health & Fitness</option>
                            <option value="productivity" <?= $habitCategory === 'productivity' ? 'selected' : '' ?>>Productivity</option>
                            <option value="mindfulness" <?= $habitCategory === 'mindfulness' ? 'selected' : '' ?>>Mindfulness</option>
                            <option value="learning" <?= $habitCategory === 'learning' ? 'selected' : '' ?>>Learning</option>
                            <option value="social" <?= $habitCategory === 'social' ? 'selected' : '' ?>>Social</option>
                            <option value="general" <?= $habitCategory === 'general' ? 'selected' : '' ?>>General</option>
                        </select>
                    </div>

                    <div>
                        <label for="habit-frequency">Frequency</label>
                        <?php $habitFrequency = strtolower((string)($habit['frequency'] ?? 'daily')); ?>
                        <select name="frequency" id="habit-frequency" class="bg-white">
                            <option value="daily" <?= $habitFrequency === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="once" <?= $habitFrequency === 'once' ? 'selected' : '' ?>>Once</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="space-y-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="habit-reminder">Reminder Time</label>
                        <input
                            type="time"
                            name="reminderTime"
                            id="habit-reminder"
                            value="<?= htmlspecialchars((string)($habit['reminderTime'] ?? '')) ?>"
                        />
                    </div>
                    <div>
                        <label for="habit-duration">Target Duration</label>
                        <div class="relative">
                            <input
                                type="number"
                                name="targetDuration"
                                id="habit-duration"
                                min="0"
                                value="<?= htmlspecialchars((string)($habit['targetDuration'] ?? '0')) ?>"
                                placeholder="15"
                                class="pr-12"
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-bold text-gray-400">MIN</span>
                        </div>
                    </div>
                </div>
                <p class="text-[10px] text-gray-400 italic">How many minutes you want to spend on this habit</p>
            </div>
        </div>

        <div class="form-section">
            <label>Source</label>
            <div class="flex border border-gray-200 p-1">
                <button
                    id="source-manual-btn"
                    onclick="setSource('manual')"
                    class="flex-1 py-2 text-[10px] font-bold uppercase tracking-widest bg-black text-white"
                    type="button"
                >
                    Manual
                </button>
                <button
                    id="source-ai-btn"
                    onclick="setSource('ai')"
                    class="flex-1 py-2 text-[10px] font-bold uppercase tracking-widest text-gray-400"
                    type="button"
                >
                    AI Suggested
                </button>
            </div>
        </div>

        <div class="form-section bg-gray-50 -mx-6 px-6">
            <div class="flex items-center justify-between mb-4">
                <label class="mb-0">AI Habit Generator</label>
                <?php if (!$hasAiSetup): ?>
                    <span class="text-[9px] text-gray-400 uppercase tracking-widest">Not Configured</span>
                <?php endif; ?>
            </div>
            <div class="space-y-4">
                <textarea
                    id="habit-goals"
                    class="resize-none"
                    rows="3"
                    placeholder="Enter your goals and click 'Generate Habits'..."
                ></textarea>
                <button
                    type="button"
                    onclick="generateHabitSuggestions()"
                    class="w-full bg-black text-white py-3.5 text-[10px] font-black uppercase tracking-[0.2em] flex items-center justify-center gap-2 active:scale-[0.98] transition-transform touch-target"
                >
                    Generate Habits
                </button>
                <div id="habit-suggestions" class="space-y-2"></div>
            </div>
        </div>
    </form>
</main>

<div class="fixed left-1/2 -translate-x-1/2 bottom-[84px] w-full max-w-[420px] bg-white border-t border-gray-100 p-4 z-30">
    <div class="flex gap-4">
        <a href="?page=habits" class="flex-1 py-4 text-center text-[11px] font-bold uppercase tracking-widest text-gray-400 border border-transparent active:text-black touch-target">
            Cancel
        </a>
        <button form="habit-form" type="submit" class="flex-[2] bg-black text-white py-4 text-[11px] font-black uppercase tracking-[0.2em] active:scale-[0.98] transition-transform touch-target">
            <?= $isEdit ? 'Update Habit' : 'Create Habit' ?>
        </button>
    </div>
</div>

<?php
$activePage = 'habits';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
    const EDIT_HABIT_ID = <?= json_encode($habit['id'] ?? '') ?>;
    const DEFAULT_AI_PROVIDER = <?= json_encode($defaultAiProvider) ?>;
    const DEFAULT_AI_MODEL = <?= json_encode($defaultAiModel) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
let suggestedHabits = [];

function notify(message, type) {
    if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
        Mobile.ui.showToast(message, type || 'info');
    } else {
        alert(message);
    }
}

function getErrorMessage(error, fallback) {
    const response = error && error.response ? error.response : null;
    if (response && response.error) {
        if (typeof response.error === 'string') {
            return response.error;
        }
        if (typeof response.error.message === 'string') {
            return response.error.message;
        }
    }
    if (error && typeof error.message === 'string') {
        return error.message;
    }
    return fallback;
}

function applySuggestion(index) {
    const habit = suggestedHabits[index];
    if (!habit) {
        return;
    }

    const nameEl = document.getElementById('habit-name');
    const categoryEl = document.getElementById('habit-category');
    const reminderEl = document.getElementById('habit-reminder');
    const durationEl = document.getElementById('habit-duration');
    const aiFlagEl = document.getElementById('is-ai-generated');
    const frequencyEl = document.getElementById('habit-frequency');

    if (nameEl) nameEl.value = habit.name || '';
    if (categoryEl && habit.category) categoryEl.value = habit.category;
    if (reminderEl) reminderEl.value = habit.reminderTime || '';
    if (durationEl && habit.targetDuration) durationEl.value = parseInt(habit.targetDuration, 10) || 0;
    if (frequencyEl) frequencyEl.value = habit.frequency || 'daily';
    if (aiFlagEl) aiFlagEl.value = '1';
    setSource('ai');

    notify('Suggestion applied.', 'success');
}

function setSource(source) {
    const aiFlagEl = document.getElementById('is-ai-generated');
    const manualBtn = document.getElementById('source-manual-btn');
    const aiBtn = document.getElementById('source-ai-btn');

    const isAi = source === 'ai';
    if (aiFlagEl) {
        aiFlagEl.value = isAi ? '1' : '0';
    }

    if (manualBtn && aiBtn) {
        if (isAi) {
            manualBtn.className = 'flex-1 py-2 text-[10px] font-bold uppercase tracking-widest text-gray-400';
            aiBtn.className = 'flex-1 py-2 text-[10px] font-bold uppercase tracking-widest bg-black text-white';
        } else {
            manualBtn.className = 'flex-1 py-2 text-[10px] font-bold uppercase tracking-widest bg-black text-white';
            aiBtn.className = 'flex-1 py-2 text-[10px] font-bold uppercase tracking-widest text-gray-400';
        }
    }
}

function renderSuggestions() {
    const container = document.getElementById('habit-suggestions');
    if (!container) {
        return;
    }

    if (!Array.isArray(suggestedHabits) || suggestedHabits.length === 0) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = suggestedHabits.map(function(habit, index) {
        const category = (habit.category || 'general').toString().toUpperCase();
        const name = (habit.name || 'Untitled Habit').toString();
        return `
            <div class="border border-gray-200 rounded-xl p-3 bg-white flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wider truncate">${name}</p>
                    <p class="text-[10px] text-gray-400 uppercase tracking-widest mt-1">${category}</p>
                </div>
                <button type="button" onclick="applySuggestion(${index})" class="px-3 py-2 border border-gray-200 rounded-lg text-[10px] font-bold uppercase tracking-widest hover:border-black touch-target">
                    Use
                </button>
            </div>
        `;
    }).join('');
}

async function generateHabitSuggestions() {
    const goalsEl = document.getElementById('habit-goals');
    const goals = goalsEl ? goalsEl.value.trim() : '';

    if (!goals) {
        notify('Enter your goals first.', 'info');
        return;
    }
    if (!DEFAULT_AI_PROVIDER || !DEFAULT_AI_MODEL) {
        notify('AI model is not configured in settings.', 'error');
        return;
    }

    try {
        notify('Generating suggestions...', 'info');
        const response = await App.api.post('api/ai.php?action=suggest_habits', {
            goals: goals,
            provider: DEFAULT_AI_PROVIDER,
            model: DEFAULT_AI_MODEL,
            csrf_token: CSRF_TOKEN
        });

        const data = Array.isArray(response.data) ? response.data : [];
        suggestedHabits = data;
        renderSuggestions();

        if (data.length === 0) {
            notify('No suggestions returned.', 'warning');
        } else {
            notify('Suggestions ready.', 'success');
        }
    } catch (error) {
        notify(getErrorMessage(error, 'Failed to generate suggestions.'), 'error');
    }
}

async function submitHabitForm(event) {
    event.preventDefault();

    const form = document.getElementById('habit-form');
    if (!form) {
        return;
    }

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    payload.name = (payload.name || '').trim();
    payload.category = payload.category || 'general';
    payload.frequency = payload.frequency || 'daily';
    payload.reminderTime = payload.reminderTime || '';
    payload.targetDuration = parseInt(payload.targetDuration || '0', 10) || 0;
    payload.isAiGenerated = payload.isAiGenerated === '1';
    payload.csrf_token = CSRF_TOKEN;

    if (!payload.name) {
        notify('Habit name is required.', 'error');
        return;
    }

    try {
        let response;
        if (EDIT_HABIT_ID) {
            response = await App.api.put('api/habits.php?id=' + encodeURIComponent(EDIT_HABIT_ID), payload);
        } else {
            response = await App.api.post('api/habits.php?action=add', payload);
        }

        if (response && response.success) {
            const successMessage = EDIT_HABIT_ID ? 'Habit updated.' : 'Habit created.';
            if (window.Mobile && Mobile.ui && typeof Mobile.ui.queueToast === 'function') {
                Mobile.ui.queueToast(successMessage, 'success');
            } else {
                notify(successMessage, 'success');
            }
            setTimeout(function() {
                window.location.href = '?page=habits';
            }, 250);
            return;
        }

        notify('Failed to save habit.', 'error');
    } catch (error) {
        notify(getErrorMessage(error, 'Failed to save habit.'), 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('habit-form');
    if (form) {
        form.addEventListener('submit', submitHabitForm);
    }

    setSource((document.getElementById('is-ai-generated')?.value === '1') ? 'ai' : 'manual');
});
</script>
</body>
</html>
