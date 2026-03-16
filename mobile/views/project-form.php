<?php
/**
 * Mobile Project Form (Create/Edit)
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
        <p><a href="?page=projects">Back to projects</a></p>
    </body></html>');
}

$siteName = getSiteName() ?? 'LazyMan';
$projectId = trim((string)($_GET['id'] ?? ''));
$projects = $db->load('projects') ?? [];
$clients = $db->load('clients') ?? [];

$project = null;
if ($projectId !== '') {
    foreach ($projects as $candidate) {
        if ((string)($candidate['id'] ?? '') === $projectId) {
            $project = $candidate;
            break;
        }
    }
}

$isEdit = is_array($project);
$pageTitle = $isEdit ? 'Edit Project' : 'New Project';

$field = static function (string $key, string $default = '') use ($project): string {
    if (!$project) {
        return $default;
    }
    $value = $project[$key] ?? $default;
    if (!is_scalar($value)) {
        return $default;
    }
    return (string)$value;
};

$status = $field('status', 'planning');
$allowedStatuses = ['planning', 'in_progress', 'on_hold', 'completed'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'planning';
}
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
            @apply bg-zinc-50 dark:bg-zinc-950 text-black dark:text-white font-display antialiased;
        }
        input, select, textarea {
            @apply w-full bg-white dark:bg-zinc-900 border border-black dark:border-white rounded-none px-4 py-3 text-sm focus:ring-1 focus:ring-black dark:focus:ring-white focus:border-black dark:focus:border-white outline-none transition-all placeholder:text-zinc-400 dark:placeholder:text-zinc-500 font-medium;
        }
        label {
            @apply block text-[10px] font-black uppercase tracking-[0.2em] mb-2 text-black dark:text-white;
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
    .status-toggle input:checked + div {
        @apply bg-black dark:bg-white text-white dark:text-black;
    }
    @supports (-webkit-touch-callout: none) {
        input, textarea, select {
            font-size: 16px !important;
        }
    }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col border-x border-zinc-100 dark:border-zinc-800 overflow-hidden">
<?php
$title = $pageTitle;
$leftAction = 'back';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar px-6 pb-36 text-zinc-900 dark:text-zinc-100">
    <form id="project-form" class="space-y-6">
        <input type="hidden" name="id" value="<?= htmlspecialchars($projectId) ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

        <div>
            <label for="project-name">Project Name</label>
            <input id="project-name" name="name" placeholder="E.g. Q1 Brand Refresh" type="text" required value="<?= htmlspecialchars($field('name')) ?>"/>
        </div>

        <div>
            <label for="project-client">Client</label>
            <div class="relative">
                <select class="appearance-none" id="project-client" name="clientId">
                    <option value="">Select a client</option>
                    <?php foreach ($clients as $client): ?>
                        <?php
                        $clientId = (string)($client['id'] ?? '');
                        $clientName = (string)($client['name'] ?? 'Unnamed');
                        $company = trim((string)($client['company'] ?? ''));
                        $selected = $clientId === $field('clientId') ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($clientId) ?>" <?= $selected ?>>
                            <?= htmlspecialchars($company !== '' ? ($clientName . ' (' . $company . ')') : $clientName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <svg class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none w-4 h-4 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </div>

        <div>
            <label>Status</label>
            <div class="grid grid-cols-2 gap-2">
                <label class="status-toggle cursor-pointer m-0">
                    <input class="hidden" name="status" type="radio" value="planning" <?= $status === 'planning' ? 'checked' : '' ?>/>
                    <div class="border border-black dark:border-white p-3 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">Planning</div>
                </label>
                <label class="status-toggle cursor-pointer m-0">
                    <input class="hidden" name="status" type="radio" value="in_progress" <?= $status === 'in_progress' ? 'checked' : '' ?>/>
                    <div class="border border-black dark:border-white p-3 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">In Progress</div>
                </label>
                <label class="status-toggle cursor-pointer m-0">
                    <input class="hidden" name="status" type="radio" value="on_hold" <?= $status === 'on_hold' ? 'checked' : '' ?>/>
                    <div class="border border-black dark:border-white p-3 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">On Hold</div>
                </label>
                <label class="status-toggle cursor-pointer m-0">
                    <input class="hidden" name="status" type="radio" value="completed" <?= $status === 'completed' ? 'checked' : '' ?>/>
                    <div class="border border-black dark:border-white p-3 text-center text-[10px] font-bold uppercase tracking-widest transition-colors">Completed</div>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="start-date">Start Date</label>
                <input class="uppercase text-[10px]" id="start-date" name="startDate" type="date" value="<?= htmlspecialchars($field('startDate', date('Y-m-d'))) ?>"/>
            </div>
            <div>
                <label for="due-date">Due Date</label>
                <input class="uppercase text-[10px]" id="due-date" name="dueDate" type="date" value="<?= htmlspecialchars($field('dueDate')) ?>"/>
            </div>
        </div>

        <div>
            <label for="project-budget">Budget ($)</label>
            <input id="project-budget" name="budget" placeholder="0.00" type="number" step="0.01" value="<?= htmlspecialchars($field('budget')) ?>"/>
        </div>

        <div class="relative">
            <div class="flex justify-between items-end mb-2">
                <label class="mb-0" for="project-description">Description</label>
                <button id="ai-project-description-btn" type="button" onclick="generateProjectDescription()" class="bg-black dark:bg-white text-white dark:text-black px-3 py-1 flex items-center gap-1 hover:opacity-90 transition-opacity touch-target">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    <span class="text-[9px] font-bold uppercase tracking-widest">AI Help</span>
                </button>
            </div>
            <textarea id="project-description" name="description" placeholder="Brief project overview..." rows="5"><?= htmlspecialchars($field('description')) ?></textarea>
        </div>
    </form>
</main>

<footer class="absolute bottom-0 left-0 right-0 bg-white dark:bg-zinc-950 border-t border-zinc-100 dark:border-zinc-800 p-6 z-30">
    <div class="flex flex-col gap-3">
        <button id="save-project-btn" type="button" onclick="saveProject()" class="w-full py-4 bg-black dark:bg-white text-white dark:text-black text-[11px] font-black uppercase tracking-[0.3em] hover:opacity-90 transition-opacity touch-target">
            <?= $isEdit ? 'Save Project' : 'Create Project' ?>
        </button>
        <a href="?page=projects" class="w-full py-3 text-center text-zinc-400 dark:text-zinc-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-black dark:hover:text-white transition-colors touch-target">
            Cancel
        </a>
    </div>
    <div class="mt-4 mx-auto w-32 h-1 bg-zinc-100 dark:bg-zinc-800 rounded-full"></div>
</footer>

</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*?)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const EDIT_PROJECT_ID = <?= json_encode($projectId) ?>;
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
function getErrorMessage(error, fallback) {
    if (error && error.response && error.response.error) {
        if (typeof error.response.error === 'string') {
            return error.response.error;
        }
        if (typeof error.response.error.message === 'string') {
            return error.response.error.message;
        }
    }
    if (error && typeof error.message === 'string' && error.message) {
        return error.message;
    }
    return fallback;
}

async function generateProjectDescription() {
    const nameInput = document.getElementById('project-name');
    const descriptionInput = document.getElementById('project-description');
    const aiButton = document.getElementById('ai-project-description-btn');
    const idea = (nameInput?.value || '').trim();

    if (idea === '') {
        Mobile.ui.showToast('Enter a project name first.', 'warning');
        return;
    }

    aiButton.disabled = true;
    const originalText = aiButton.innerHTML;
    aiButton.innerHTML = '<span class="text-[9px] font-bold uppercase tracking-widest">Generating...</span>';

    try {
        const response = await App.api.post('api/ai-generate.php?action=project', {
            idea: idea,
            csrf_token: CSRF_TOKEN
        });
        const generated = (response.data && response.data.description) ? String(response.data.description) : '';
        if (generated.trim() === '') {
            throw new Error('No description returned');
        }
        descriptionInput.value = generated;
        Mobile.ui.showToast('AI description generated.', 'success');
    } catch (error) {
        Mobile.ui.showToast(getErrorMessage(error, 'AI generation failed.'), 'error');
    } finally {
        aiButton.disabled = false;
        aiButton.innerHTML = originalText;
    }
}

async function saveProject() {
    const form = document.getElementById('project-form');
    const saveButton = document.getElementById('save-project-btn');
    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    payload.name = (payload.name || '').trim();
    payload.description = (payload.description || '').trim();
    payload.clientId = (payload.clientId || '').trim();
    payload.status = (payload.status || 'planning').trim();
    payload.startDate = (payload.startDate || '').trim();
    payload.dueDate = (payload.dueDate || '').trim();
    payload.budget = (payload.budget || '').trim();
    payload.csrf_token = CSRF_TOKEN;

    if (payload.name === '') {
        Mobile.ui.showToast('Project name is required.', 'error');
        return;
    }

    saveButton.disabled = true;
    const originalLabel = saveButton.textContent;
    saveButton.textContent = IS_EDIT ? 'SAVING...' : 'CREATING...';

    try {
        let response;
        if (IS_EDIT && EDIT_PROJECT_ID) {
            response = await App.api.put('api/projects.php?id=' + encodeURIComponent(EDIT_PROJECT_ID), payload);
        } else {
            response = await App.api.post('api/projects.php?action=add', payload);
        }

        if (!response.success || !response.data) {
            throw new Error('Failed to save project');
        }

        const destinationId = (response.data.id || EDIT_PROJECT_ID || '').toString();
        Mobile.ui.showToast(IS_EDIT ? 'Project updated.' : 'Project created.', 'success');

        setTimeout(() => {
            if (destinationId) {
                window.location.href = '?page=view-project&id=' + encodeURIComponent(destinationId);
            } else {
                window.location.href = '?page=projects';
            }
        }, 180);
    } catch (error) {
        Mobile.ui.showToast(getErrorMessage(error, 'Failed to save project.'), 'error');
        saveButton.disabled = false;
        saveButton.textContent = originalLabel;
    }
}

if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>
