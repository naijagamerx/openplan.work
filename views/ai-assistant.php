<?php
// AI Assistant View
try {
    $db = new Database(getMasterPassword(), Auth::userId());
    $config = $db->load('config');
    $projects = $db->load('projects');
    $modelsLoad = $db->safeLoad('models');
    $models = $modelsLoad['success'] ? $modelsLoad['data'] : [];
} catch (Exception $e) {
    // If decryption fails, use empty/default values
    $config = [];
    $projects = [];
    $models = [];
}

$hasGroqKey = !empty($config['groqApiKey']);
$hasOpenRouterKey = !empty($config['openrouterApiKey']);
$hasAnyKey = $hasGroqKey || $hasOpenRouterKey;

// Filter enabled models from database
$groqModels = array_filter($models['groq'] ?? [], fn($m) => $m['enabled'] ?? true);
$openRouterModels = array_filter($models['openrouter'] ?? [], fn($m) => $m['enabled'] ?? true);

// Only use static model lists as fallback if database is completely empty
// This prevents overriding user's configured models
if (empty($groqModels) && empty($openRouterModels)) {
    // Database is empty, use static lists as fallback
    $groqModels = array_map(fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(GroqAPI::getModels()), GroqAPI::getModels());
    $openRouterModels = array_map(fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(OpenRouterAPI::getModels()), OpenRouterAPI::getModels());
}
?>

<div class="space-y-6">
    <!-- PHP Requirements Warning -->
    <div id="php-requirements-warning" class="hidden">
        <!-- Populated by JavaScript -->
    </div>

    <?php if (!$hasAnyKey): ?>
        <!-- API Key Warning -->
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-6 h-6 text-yellow-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <p class="font-medium text-yellow-800">API Keys Required</p>
                <p class="text-sm text-yellow-700 mt-1">Please add your Groq or OpenRouter API key in <a href="?page=settings" class="underline">Settings</a> to use AI features.</p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- AI Tools Buttons -->
    <div class="flex flex-wrap gap-3 mb-4">
        <!-- Task Generator -->
        <button onclick="openTaskGenerator()" class="flex items-center gap-2 px-4 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg <?php echo !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Task Generator
        </button>
        
        <!-- PRD Generator -->
        <button onclick="openPRDGenerator()" class="flex items-center gap-2 px-4 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg <?php echo !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            PRD Generator
        </button>
        
        <!-- AI Verification -->
        <button onclick="runAIVerification()" class="flex items-center gap-2 px-4 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg <?php echo !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            AI Verification
        </button>
    </div>
    
    <!-- Chat Interface -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">AI Chat</h3>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="clearConversationHistory()" class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-semibold hover:bg-red-50 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Clear History
                    </button>
                    <button type="button" onclick="toggleConversationHistory()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        History
                    </button>
                </div>
            </div>

            <!-- Agent Mode Controls -->
            <div class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center gap-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="agent-mode" class="sr-only peer" checked>
                        <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-black rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                        <span class="ml-2 text-sm font-medium text-gray-700">Agent Mode</span>
                    </label>
                    <span class="text-xs text-gray-500" id="agent-mode-status">(AI can perform actions)</span>
                </div>

                <div class="flex items-center gap-2">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="auto-confirm" class="sr-only peer" checked disabled>
                        <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-black rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-black"></div>
                        <span class="ml-2 text-sm font-medium text-gray-700">Auto-Confirm</span>
                    </label>
                    <span class="text-xs text-gray-500">(Always on in current agent mode)</span>
                </div>
            </div>
        </div>

        <div class="flex">
            <!-- Conversation History Sidebar -->
            <div id="conversation-sidebar" class="hidden w-64 border-r border-gray-200 bg-gray-50 flex-shrink-0">
                <div class="p-3 border-b border-gray-200 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Conversations</span>
                    <button type="button" onclick="toggleConversationHistory()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div id="conversation-list" class="overflow-y-auto" style="max-height: 450px;">
                    <!-- Populated by JS -->
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="flex-1 flex flex-col min-h-0">
                <!-- AI Provider and Model Selection -->
                <div class="p-3 border-b border-gray-200 flex items-center gap-2 flex-wrap bg-gray-50 flex-shrink-0">
                    <select id="ai-provider" onchange="updateModelList(); updateConversationState()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <?php if ($hasGroqKey): ?><option value="groq">Groq</option><?php endif; ?>
                        <?php if ($hasOpenRouterKey): ?><option value="openrouter">OpenRouter</option><?php endif; ?>
                    </select>
                    <select id="ai-model" onchange="updateConversationState()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <!-- Populated by JS -->
                    </select>
                    <select id="ai-kb-folder" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm" title="Knowledge Base context">
                        <option value="">No Knowledge Base</option>
                    </select>
                    <button type="button" onclick="copyChatTranscript()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50">
                        Copy
                    </button>
                    <button type="button" onclick="exportChatMarkdown()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50">
                        Export MD
                    </button>
                    <button type="button" onclick="openChatSaveToKnowledgeBase()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50">
                        Save to KB
                    </button>
                    <button type="button" onclick="saveChatToNotes()" class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-semibold hover:bg-gray-800">
                        Save to Notes
                    </button>
                    <button type="button" onclick="startNewConversation()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50">
                        New Chat
                    </button>
                </div>

                <!-- Chat Messages -->
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 min-h-0" style="max-height: 500px; min-height: 300px;">
                    <div class="text-center text-gray-400 text-sm py-8">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        Start a conversation with the AI assistant
                    </div>
                </div>

                <!-- Input Form -->
                <div class="p-4 border-t border-gray-200 flex-shrink-0">
                    <form id="chat-form" class="flex gap-3">
                        <input type="text" id="chat-input" placeholder="Ask anything..."
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none"
                               <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
                        <button type="submit" id="send-message" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition"
                                <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const ALL_MODELS = <?php echo json_encode([
    'groq' => array_values($groqModels),
    'openrouter' => array_values($openRouterModels)
]); ?>;

function updateModelList() {
    const provider = document.getElementById('ai-provider').value;
    const modelSelect = document.getElementById('ai-model');
    if (!modelSelect) return;
    const models = ALL_MODELS[provider] || [];
    
    modelSelect.innerHTML = models.map(m => 
        `<option value="${m.modelId}" ${m.isDefault ? 'selected' : ''}>${m.displayName}</option>`
    ).join('');
}

// Initial population
window.addEventListener('DOMContentLoaded', () => {
    updateModelList();
    loadKBFolders();
    checkPHPRequirements();
});

// Check PHP extensions requirements
async function checkPHPRequirements() {
    try {
        const response = await api.get('api/ai.php?action=check_requirements');
        if (response.success && response.data) {
            const { extensions, all_required_loaded, missing_required, php_version_ok } = response.data;

            if (!all_required_loaded || !php_version_ok) {
                showRequirementsWarning(extensions, missing_required, php_version_ok, response.data.php_version);
            }
        }
    } catch (error) {
        console.error('Failed to check PHP requirements:', error);
    }
}

function showRequirementsWarning(extensions, missing, phpVersionOk, currentPhpVersion) {
    const warningDiv = document.getElementById('php-requirements-warning');
    if (!warningDiv) return;

    let missingHtml = '';

    // Check PHP version
    if (!phpVersionOk) {
        missingHtml += `
            <div class="flex items-start gap-3 mb-3">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="font-medium text-red-800">PHP Version Issue</p>
                    <p class="text-sm text-red-700">Current version: ${currentPhpVersion}. Required: 8.0 or higher.</p>
                </div>
            </div>
        `;
    }

    // Check missing extensions
    if (missing.length > 0) {
        missing.forEach(ext => {
            const info = extensions[ext];
            const isCurl = ext === 'curl';
            missingHtml += `
                <div class="flex items-start gap-3 mb-3">
                    <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <div>
                        <p class="font-medium text-red-800">${ext.toUpperCase()} Extension Missing</p>
                        <p class="text-sm text-red-700">Required for: ${info.feature}</p>
                        <a href="docs/PHP_SETUP.md" target="_blank" class="text-xs text-red-600 underline hover:text-red-800">How to enable ${ext.toUpperCase()}</a>
                    </div>
                </div>
            `;

            // Disable chat input if cURL is missing
            if (isCurl) {
                const chatInput = document.getElementById('chat-input');
                const chatButton = document.querySelector('#chat-form button[type="submit"]');
                const providerSelect = document.getElementById('ai-provider');

                if (chatInput) {
                    chatInput.disabled = true;
                    chatInput.placeholder = `${ext.toUpperCase()} extension is required for AI features`;
                }
                if (chatButton) {
                    chatButton.disabled = true;
                    chatButton.textContent = 'Extension Missing';
                }
                if (providerSelect) {
                    providerSelect.disabled = true;
                }
            }
        });
    }

    // Show optional extensions that are missing
    const optionalMissing = Object.keys(extensions).filter(ext => !extensions[ext].loaded && !extensions[ext].required);
    if (optionalMissing.length > 0) {
        missingHtml += '<p class="text-xs text-yellow-700 mt-2">Optional extensions missing: ' + optionalMissing.map(e => e.toUpperCase()).join(', ') + '</p>';
    }

    warningDiv.innerHTML = `
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div class="flex-1">
                    <p class="font-medium text-red-800">PHP Extensions Required</p>
                    <p class="text-sm text-red-700 mb-3">Some PHP extensions required for AI features are not loaded.</p>
                    ${missingHtml}
                    <div class="mt-3 pt-3 border-t border-red-200">
                        <a href="docs/PHP_SETUP.md" target="_blank" class="text-sm text-red-700 underline hover:text-red-900 font-medium">
                            View PHP Setup Guide →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `;
    warningDiv.classList.remove('hidden');
}

let chatHistory = [];

// Agent Mode State
let currentConversationId = null;
let agentModeEnabled = true;
let autoConfirmEnabled = true;
let pendingActions = [];

// Task Generator
let generatedTasks = [];
let selectedProjectId = '';

function openTaskGenerator() {
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Generate Task Breakdown</h3>
            <form id="task-gen-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Description</label>
                    <textarea name="description" rows="5" required
                              placeholder="Describe your project in detail. For example: Build an e-commerce website with product catalog, shopping cart, checkout, and user authentication..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Save to Project (Optional)</label>
                    <select name="projectId" id="task-project-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo e($p['id']); ?>"><?php echo e($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 flex items-center gap-2">
                        <span>Generate</span>
                        <div class="spinner hidden" id="task-gen-spinner"></div>
                    </button>
                </div>
            </form>
            <div id="task-gen-result" class="mt-4 hidden"></div>
        </div>
    `);

    document.getElementById('task-gen-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const spinner = document.getElementById('task-gen-spinner');
        const result = document.getElementById('task-gen-result');
        const projectSelect = document.getElementById('task-project-select');
        spinner.classList.remove('hidden');

        const formData = new FormData(e.target);
        const data = {
            description: formData.get('description'),
            projectId: projectSelect.value,
            provider: document.getElementById('ai-provider')?.value || 'groq',
            csrf_token: CSRF_TOKEN
        };

        selectedProjectId = projectSelect.value;

        const response = await api.post('api/ai.php?action=generate_tasks', data);
        spinner.classList.add('hidden');

        if (response.success && response.data?.tasks) {
            generatedTasks = response.data.tasks;
            let html = '<div class="bg-gray-50 rounded-lg p-4 max-h-60 overflow-y-auto"><h4 class="font-medium mb-2">Generated Tasks:</h4><ul class="space-y-2">';
            response.data.tasks.forEach(task => {
                html += '<li class="text-sm"><strong>' + task.title + '</strong> (' + task.priority + ', ~' + task.estimatedMinutes + 'min)</li>';
            });
            html += '</ul></div>';

            // Add save button if project is selected
            if (selectedProjectId) {
                html += '<button onclick="saveTasksToProject()" class="mt-3 w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2">';
                html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                html += 'Save to Project</button>';
            } else {
                html += '<p class="mt-3 text-sm text-gray-500">Select a project above and generate again to save.</p>';
            }

            result.innerHTML = html;
            result.classList.remove('hidden');

            showToast('Tasks generated!', 'success');
        } else {
            showToast(response.error || 'Generation failed', 'error');
        }
    });
}

async function saveTasksToProject() {
    if (!selectedProjectId || generatedTasks.length === 0) {
        showToast('No project or tasks to save', 'error');
        return;
    }

    const response = await api.post('api/ai.php?action=import_tasks', {
        projectId: selectedProjectId,
        tasks: generatedTasks,
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        showToast('Tasks saved to project!', 'success');
        closeModal();
        // Redirect to project page
        window.location.href = '?page=projects';
    } else {
        showToast(response.error || 'Failed to save tasks', 'error');
    }
}

// PRD Generator
let generatedPRD = '';
let generatedPRDIdea = '';
let selectedPRDProjectId = '';
let selectedPRDKBFolderId = '';

// Knowledge Base selection for AI chat
const KB_FOLDER_STORAGE_KEY = 'ai.kb.folder';
let kbFolders = [];
let kbFoldersLoaded = false;
let selectedKbFolderId = '';

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function getStoredKbFolderId() {
    try {
        return localStorage.getItem(KB_FOLDER_STORAGE_KEY) || '';
    } catch (error) {
        return '';
    }
}

function setStoredKbFolderId(folderId) {
    try {
        localStorage.setItem(KB_FOLDER_STORAGE_KEY, folderId || '');
    } catch (error) {
        console.warn('Failed to store KB folder preference:', error);
    }
}

async function loadKBFolders() {
    try {
        const response = await api.get('api/knowledge-base.php?action=list_folders');
        if (response.success && Array.isArray(response.data?.folders)) {
            kbFolders = response.data.folders;
        } else {
            kbFolders = [];
        }
        kbFoldersLoaded = true;
        renderKbFolderSelect();
    } catch (error) {
        console.error('Failed to load KB folders:', error);
        kbFolders = [];
        kbFoldersLoaded = false;
        renderKbFolderSelect();
    }
}

async function ensureKBFoldersLoaded() {
    if (kbFoldersLoaded) return;
    await loadKBFolders();
}

function buildFolderPath(folderId, folderMap) {
    const parts = [];
    let current = folderMap.get(folderId);
    while (current) {
        parts.unshift(current.name);
        current = current.parentId ? folderMap.get(current.parentId) : null;
    }
    return parts.join(' / ');
}

function getKbFolderOptionsHtml(selectedId, placeholderLabel = 'No Knowledge Base') {
    if (!Array.isArray(kbFolders) || kbFolders.length === 0) {
        return `<option value="">No folders found</option>`;
    }

    const folderMap = new Map(kbFolders.map(folder => [folder.id, folder]));
    const sorted = kbFolders
        .map(folder => ({
            ...folder,
            path: buildFolderPath(folder.id, folderMap)
        }))
        .sort((a, b) => a.path.localeCompare(b.path));

    let html = `<option value="">${escapeHtml(placeholderLabel)}</option>`;
    html += sorted.map(folder => {
        const selected = folder.id === selectedId ? 'selected' : '';
        return `<option value="${folder.id}" ${selected}>${escapeHtml(folder.path)}</option>`;
    }).join('');

    return html;
}

function renderKbFolderSelect() {
    const select = document.getElementById('ai-kb-folder');
    if (!select) return;

    if (!selectedKbFolderId) {
        selectedKbFolderId = getStoredKbFolderId();
    }

    select.innerHTML = getKbFolderOptionsHtml(selectedKbFolderId, 'No Knowledge Base');
    select.value = selectedKbFolderId || '';
    select.onchange = () => {
        selectedKbFolderId = select.value;
        setStoredKbFolderId(selectedKbFolderId);
    };
}

function getDefaultPRDFilename() {
    const base = (generatedPRDIdea || 'prd')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    const safeBase = base || 'prd';
    return `${safeBase}.md`;
}

function getDefaultChatFilename() {
    const dateStamp = new Date().toISOString().slice(0, 10);
    return `ai-chat-${dateStamp}.md`;
}

function encodeBase64(str) {
    if (!str) return '';
    if (typeof TextEncoder !== 'undefined') {
        const bytes = new TextEncoder().encode(str);
        let binary = '';
        bytes.forEach(b => binary += String.fromCharCode(b));
        return btoa(binary);
    }
    return btoa(unescape(encodeURIComponent(str)));
}

async function openPRDGenerator() {
    await ensureKBFoldersLoaded();
    const kbSelectDisabled = kbFolders.length === 0 ? 'disabled' : '';
    const defaultKbFolderId = selectedPRDKBFolderId || getStoredKbFolderId();
    const kbOptionsHtml = getKbFolderOptionsHtml(defaultKbFolderId, 'Select a folder...');
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Generate PRD</h3>
            <form id="prd-gen-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project Idea</label>
                    <textarea name="idea" rows="4" required
                              placeholder="Describe your project idea..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Save to Project (Optional)</label>
                    <select name="projectId" id="prd-project-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?php echo e($p['id']); ?>"><?php echo e($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Save to Knowledge Base (Optional)</label>
                    <select id="prd-kb-folder" class="w-full px-3 py-2 border border-gray-300 rounded-lg" ${kbSelectDisabled}>
                        ${kbOptionsHtml}
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Choose a folder to save the PRD as a Markdown file after generation.</p>
                </div>
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800">Generate PRD</button>
                </div>
            </form>
        </div>
    `);

    document.getElementById('prd-gen-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        showToast('Generating PRD...', 'info');

        const formData = new FormData(e.target);
        const projectSelect = document.getElementById('prd-project-select');
        const kbSelect = document.getElementById('prd-kb-folder');
        selectedPRDProjectId = projectSelect.value;
        selectedPRDKBFolderId = kbSelect?.value || defaultKbFolderId || '';

        const response = await api.post('api/ai.php?action=generate_prd', {
            idea: formData.get('idea'),
            projectId: selectedPRDProjectId,
            provider: document.getElementById('ai-provider')?.value || 'groq',
            csrf_token: CSRF_TOKEN
        });

        if (response.success && response.data?.prd) {
            generatedPRD = response.data.prd;
            generatedPRDIdea = response.data.idea || formData.get('idea');

            const kbResultOptions = getKbFolderOptionsHtml(selectedPRDKBFolderId, 'Select a folder...');
            let html = '<div class="p-6 space-y-4">';
            html += '<div>';
            html += '<h3 class="font-semibold mb-2">Generated PRD</h3>';
            html += '<div class="prose prose-sm max-h-64 overflow-y-auto"><pre class="whitespace-pre-wrap text-sm">' + generatedPRD + '</pre></div>';
            html += '</div>';

            html += '<div class="space-y-2">';
            html += '<h4 class="text-sm font-semibold text-gray-700">Save Options</h4>';

            if (selectedPRDProjectId) {
                html += '<button onclick="savePRDToProject()" class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2">';
                html += '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                html += 'Save to Project</button>';
            } else {
                html += '<p class="text-xs text-gray-500">Select a project above and generate again to save to a project.</p>';
            }

            if (kbFolders.length > 0) {
                html += '<div class="grid grid-cols-1 gap-2 pt-2">';
                html += '<select id="prd-kb-folder-result" class="w-full px-3 py-2 border border-gray-300 rounded-lg">';
                html += kbResultOptions;
                html += '</select>';
                html += `<input id="prd-kb-filename" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm" value="${getDefaultPRDFilename()}" placeholder="prd.md">`;
                html += '<button onclick="savePRDToKnowledgeBase()" class="w-full py-2 bg-black text-white rounded-lg hover:bg-gray-800">Save to Knowledge Base</button>';
                html += '</div>';
            } else {
                html += '<p class="text-xs text-gray-500">No knowledge base folders yet. Create one in Knowledge Base first.</p>';
            }

            html += '</div>';
            html += '</div>';

            closeModal();
            openModal(html);
            showToast('PRD generated!', 'success');
        } else {
            showToast(response.error || 'Generation failed', 'error');
        }
    });
}

async function savePRDToProject() {
    if (!selectedPRDProjectId || !generatedPRD) {
        showToast('No project or PRD to save', 'error');
        return;
    }

    const response = await api.post('api/ai.php?action=save_prd', {
        projectId: selectedPRDProjectId,
        prd: generatedPRD,
        idea: generatedPRDIdea,
        csrf_token: CSRF_TOKEN
    });

    if (response.success) {
        showToast('PRD saved to project!', 'success');
        closeModal();
        window.location.href = '?page=projects';
    } else {
        showToast(response.error || 'Failed to save PRD', 'error');
    }
}

async function savePRDToKnowledgeBase() {
    if (!generatedPRD) {
        showToast('No PRD to save', 'error');
        return;
    }

    const folderSelect = document.getElementById('prd-kb-folder-result');
    const filenameInput = document.getElementById('prd-kb-filename');
    const folderId = folderSelect?.value || selectedPRDKBFolderId;

    if (!folderId) {
        showToast('Select a knowledge base folder first', 'error');
        return;
    }

    let filename = filenameInput?.value.trim() || getDefaultPRDFilename();
    if (!filename.toLowerCase().endsWith('.md')) {
        filename += '.md';
    }

    try {
        const response = await api.post('api/knowledge-base.php?action=upload_file', {
            folderId: folderId,
            name: filename,
            content: encodeBase64(generatedPRD),
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            const savedName = response.data?.file?.name || filename;
            showToast(`PRD saved as ${savedName}`, 'success');
            closeModal();
        } else {
            showToast(response.error || 'Failed to save PRD to Knowledge Base', 'error');
        }
    } catch (error) {
        console.error('Failed to save PRD to KB:', error);
        showToast('Failed to save PRD to Knowledge Base', 'error');
    }
}



async function openChatSaveToKnowledgeBase() {
    const content = await getChatMarkdown();
    if (!content) {
        showToast('No chat to save yet', 'info');
        return;
    }

    await ensureKBFoldersLoaded();

    if (!Array.isArray(kbFolders) || kbFolders.length === 0) {
        showToast('No knowledge base folders yet', 'error');
        return;
    }

    const defaultFolderId = selectedKbFolderId || getStoredKbFolderId();
    const optionsHtml = getKbFolderOptionsHtml(defaultFolderId, 'Select a folder...');

    openModal(`
        <div class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Save Chat to Knowledge Base</h3>
                <p class="text-sm text-gray-500">Choose a folder and filename for the chat export.</p>
            </div>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Folder</label>
                <select id="chat-kb-folder" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    ${optionsHtml}
                </select>
            </div>
            <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Filename</label>
                <input id="chat-kb-filename" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm" value="${getDefaultChatFilename()}" placeholder="ai-chat.md">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="button" id="chat-kb-save-btn" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800">Save</button>
            </div>
        </div>
    `);

    document.getElementById('chat-kb-save-btn')?.addEventListener('click', saveChatToKnowledgeBase);
}

async function saveChatToKnowledgeBase() {
    const content = await getChatMarkdown();
    if (!content) {
        showToast('No chat to save yet', 'info');
        return;
    }

    const folderSelect = document.getElementById('chat-kb-folder');
    const filenameInput = document.getElementById('chat-kb-filename');
    const folderId = folderSelect?.value || selectedKbFolderId || '';

    if (!folderId) {
        showToast('Select a knowledge base folder first', 'error');
        return;
    }

    let filename = filenameInput?.value.trim() || getDefaultChatFilename();
    if (!filename.toLowerCase().endsWith('.md')) {
        filename += '.md';
    }

    selectedKbFolderId = folderId;
    setStoredKbFolderId(folderId);

    try {
        const response = await api.post('api/knowledge-base.php?action=upload_file', {
            folderId: folderId,
            name: filename,
            content: encodeBase64(content),
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            const savedName = response.data?.file?.name || filename;
            showToast(`Chat saved as ${savedName}`, 'success');
            closeModal();
        } else {
            showToast(response.error || 'Failed to save chat', 'error');
        }
    } catch (error) {
        console.error('Failed to save chat to KB:', error);
        showToast('Failed to save chat', 'error');
    }
}

// AI Verification
async function runAIVerification() {
    const provider = document.getElementById('ai-provider').value;
    const model = document.getElementById('ai-model').value;
    
    let results = [];
    
    if (provider === 'groq') {
        try {
            const response = await api.post('api/ai-test.php?action=test_groq', {
                model: model,
                csrf_token: CSRF_TOKEN
            });
            results.push({ provider: 'Groq', model, ...response.data });
        } catch (error) {
            results.push({
                provider: 'Groq',
                model,
                success: false,
                error: error.response?.error?.message || error.message || 'Failed to connect',
                latency: 0,
                status_code: error.status
            });
        }
    } else if (provider === 'openrouter') {
        try {
            const response = await api.post('api/ai-test.php?action=test_openrouter', {
                model: model,
                csrf_token: CSRF_TOKEN
            });
            results.push({ provider: 'OpenRouter', model, ...response.data });
        } catch (error) {
            results.push({
                provider: 'OpenRouter',
                model,
                success: false,
                error: error.response?.error?.message || error.message || 'Failed to connect',
                latency: 0,
                status_code: error.status
            });
        }
    }
    
    displayVerificationResults(results);
}

function displayVerificationResults(results) {
    let html = '<div class="space-y-4">';
    
    results.forEach(result => {
        if (result.success) {
            html += `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold text-green-800">${result.provider} Connection Successful</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Model:</span>
                            <span class="font-medium">${result.model}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Latency:</span>
                            <span class="font-medium ${result.within_threshold ? 'text-green-600' : 'text-yellow-600'}">${result.latency}s</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status Code:</span>
                            <span class="font-medium">${result.status_code}</span>
                        </div>
                        <div class="mt-2 pt-2 border-t border-green-200">
                            <span class="text-gray-600">Response:</span>
                            <p class="mt-1 bg-white p-2 rounded text-gray-800">${result.response}</p>
                        </div>
                        <div class="mt-2 pt-2 border-t border-green-200 text-xs text-gray-500">
                            <div>Timestamp: ${result.timestamp}</div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-red-600" parameter="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold text-red-800">${result.provider} Connection Failed</span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Error:</span>
                            <span class="font-medium text-red-700">${result.error}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Latency:</span>
                            <span class="font-medium">${result.latency}s</span>
                        </div>
                        ${result.status_code ? `
                        <div class="flex justify-between">
                            <span class="text-gray-600">Status Code:</span>
                            <span class="font-medium">${result.status_code}</span>
                        </div>
                        ` : ''}
                        <div class="mt-2 pt-2 border-t border-red-200 text-xs text-gray-500">
                            <div>Timestamp: ${result.timestamp}</div>
                        </div>
                    </div>
                </div>
            `;
        }
    });
    
    html += '</div>';
    
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">AI Verification Results</h3>
            ${html}
        </div>
    `);
}

// Agent mode state listeners
document.getElementById('agent-mode')?.addEventListener('change', (e) => {
    agentModeEnabled = e.target.checked;
    const statusLabel = document.getElementById('agent-mode-status');
    statusLabel.textContent = agentModeEnabled ? '(AI can perform actions)' : '(AI only responds)';
});

document.getElementById('auto-confirm')?.addEventListener('change', (e) => {
    autoConfirmEnabled = e.target.checked;
});

// Chat
document.getElementById('chat-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    if (!message) return;

    // Add user message
    addChatMessage('user', message);
    input.value = '';

    chatHistory.push({ role: 'user', content: message });

    // Choose API based on agent mode
    if (agentModeEnabled) {
        await sendAgentMessage(message);
    } else {
        await sendChatMessage(message);
    }
});

// Send message using Agent API
// Send message using Agent API
async function sendAgentMessage(message) {
    showTypingIndicator(); // Keep showing typing indicator (with polling tailored details)
    startPolling();        // Start real-time updates

    try {
        const response = await api.post('api/ai-agent.php?action=chat', {
            message: message,
            conversationId: currentConversationId,
            autoConfirm: autoConfirmEnabled,
            provider: document.getElementById('ai-provider')?.value || 'groq',
            model: document.getElementById('ai-model')?.value || undefined,
            kbFolderId: document.getElementById('ai-kb-folder')?.value || null,
            csrf_token: CSRF_TOKEN
        });

        stopPolling();
        hideTypingIndicator();

        if (response.success) {
            currentConversationId = response.data.conversationId;
            // Regular success - polling likely handled most updates, ensure final state.
            await refreshConversation();
            loadConversations(); // Update list sidebar
        } else {
            addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
        }
    } catch (error) {
        stopPolling();
        hideTypingIndicator();
        const errorMessage = error.response?.error?.message || error.message || 'Failed to connect to AI service';
        addChatMessage('assistant', 'Error: ' + errorMessage);
    }
}

// Send message using regular Chat API
async function sendChatMessage(message) {
    showTypingIndicator();

    try {
        const response = await api.post('api/ai.php?action=chat', {
            messages: chatHistory,
            provider: document.getElementById('ai-provider')?.value || 'groq',
            model: document.getElementById('ai-model')?.value || undefined,
            kbFolderId: document.getElementById('ai-kb-folder')?.value || '',
            csrf_token: CSRF_TOKEN
        });

        hideTypingIndicator();

        if (response.success && response.data?.response) {
            chatHistory.push({ role: 'assistant', content: response.data.response });
            addChatMessage('assistant', response.data.response);
        } else {
            addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
        }
    } catch (error) {
        hideTypingIndicator();
        const errorMessage = error.response?.error?.message || error.message || 'Failed to connect to AI service';
        addChatMessage('assistant', 'Error: ' + errorMessage);
    }
}

// Show agent confirmation modal
function showAgentConfirmation(action) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-2xl">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-gray-900">Confirm Action</h3>
                </div>
                <p class="text-gray-700 mb-6">${escapeHtml(action.question)}</p>
                <div class="flex gap-3 justify-end">
                    <button id="deny-btn-${action.id}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button id="confirm-btn-${action.id}" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        document.getElementById(`confirm-btn-${action.id}`).onclick = async () => {
            modal.remove();
            await confirmAction(action.id, true);
            resolve();
        };
        document.getElementById(`deny-btn-${action.id}`).onclick = async () => {
            modal.remove();
            await confirmAction(action.id, false);
            resolve();
        };
    });
}

// Handle action confirmation
async function confirmAction(actionId, approved) {
    if (!currentConversationId) return;

    showTypingIndicator();

    try {
        const response = await api.post('api/ai-agent.php?action=confirm_action', {
            conversationId: currentConversationId,
            actionId: actionId,
            approved: approved,
            csrf_token: CSRF_TOKEN
        });

        hideTypingIndicator();

        if (response.success) {
            if (approved) {
                addFunctionResult({
                    name: response.data.actionName,
                    result: response.data.result,
                    summary: response.data.summary
                });
            } else {
                addChatMessage('assistant', 'Action cancelled.', [], []);
            }
        }
    } catch (error) {
        hideTypingIndicator();
        console.error('Failed to confirm action:', error);
    }
}

function addChatMessage(role, content, functionCalls = [], toolResults = []) {
    const container = document.getElementById('chat-messages');
    const isUser = role === 'user';

    const placeholder = container.querySelector('.text-center');
    if (placeholder) placeholder.remove();

    const div = document.createElement('div');
    div.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');

    let html = `<div class="max-w-[80%] px-4 py-2 rounded-xl ${isUser ? 'bg-black text-white' : 'bg-gray-100 text-gray-900'}">`;

    // Show function calls (for assistant messages) - simplified display
    if (!isUser && functionCalls.length > 0) {
        html += '<div class="mb-3 p-2 bg-gray-50 rounded-lg border border-gray-200">';
        html += '<div class="text-xs text-gray-600 font-medium">⏳ Working on ' + functionCalls.length + ' action' + (functionCalls.length > 1 ? 's' : '') + '...</div>';
        html += '</div>';
    }

    // Clean content of XML-like tool calls if present
    // Aggressively strip everything from <tool_call> or <tool_code> to the end
    content = content.replace(/<tool_call>[\s\S]*/g, '')
                     .replace(/<tool_code>[\s\S]*/g, '')
                     .trim();

    // Main content
    if (content) {
        html += `<pre class="whitespace-pre-wrap text-sm font-sans">${escapeHtml(content)}</pre>`;
    }

    // Show tool results
    if (!isUser && toolResults.length > 0) {
        html += '<div class="mt-3 p-2 bg-green-50 rounded-lg border border-green-200">';
        html += '<div class="text-xs text-green-700 font-medium mb-1">✓ Results:</div>';
        toolResults.forEach(result => {
            if (result.error) {
                html += `<div class="text-sm text-red-800">❌ ${escapeHtml(result.name)}: ${escapeHtml(result.error)}</div>`;
            } else {
                html += `<div class="text-sm text-green-800">✓ ${escapeHtml(result.summary || result.name)}</div>`;
            }
        });
        html += '</div>';
    }

    html += '</div>';
    div.innerHTML = html;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Clear ALL conversation history
async function clearConversationHistory() {
    if (!confirm('Are you sure you want to clear ALL chat conversations? This cannot be undone.')) return;

    try {
        // Call API to delete all conversations
        const response = await api.post('api/ai-agent.php?action=clear_all_conversations', {
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            // Clear UI
            document.getElementById('chat-messages').innerHTML = `
                <div class="text-center text-gray-400 text-sm py-8">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    Start a conversation with the AI assistant
                </div>
            `;
            chatHistory = [];
            currentConversationId = null;

            // Reload conversation list
            loadConversations();

            showToast('All conversations cleared', 'success');
        } else {
            showToast(response.error || 'Failed to clear conversations', 'error');
        }
    } catch (e) {
        console.error('Failed to clear conversations', e);
        showToast('Failed to clear conversations: ' + (e.message || 'Unknown error'), 'error');
    }
}

// Live Status Update
function updateLiveStatus(status) {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) {
        // Check if we need to add the details text element
        let details = indicator.querySelector('.typing-details');
        if (!details) {
            const bubble = indicator.querySelector('.bg-gray-100');
            if (bubble) {
                details = document.createElement('div');
                details.className = 'typing-details text-xs text-gray-500 mt-2 font-mono';
                bubble.appendChild(details);
            }
        }
        if (details) details.textContent = status;
    }
}

// Override showTypingIndicator to support status
const originalShowTypingIndicator = showTypingIndicator;
showTypingIndicator = function() {
    originalShowTypingIndicator();
    updateLiveStatus('AI is thinking...');
};

// Add a standalone function result
function addFunctionResult(result) {
    const container = document.getElementById('chat-messages');

    const div = document.createElement('div');
    div.className = 'flex justify-start';

    let html = '<div class="max-w-[80%] px-4 py-2 rounded-xl bg-green-50 border border-green-200">';
    if (result.error) {
        html += `<div class="text-sm text-red-800">❌ ${escapeHtml(result.name)}: ${escapeHtml(result.error)}</div>`;
    } else {
        html += `<div class="text-sm text-green-800">✓ ${escapeHtml(result.summary || result.name)}</div>`;
    }
    html += '</div>';

    div.innerHTML = html;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Show typing indicator
function showTypingIndicator() {
    const container = document.getElementById('chat-messages');
    const placeholder = container.querySelector('.text-center');
    if (placeholder) placeholder.remove();

    const div = document.createElement('div');
    div.id = 'typing-indicator';
    div.className = 'flex justify-start';
    div.innerHTML = `
        <div class="bg-gray-100 rounded-xl px-4 py-3">
            <div class="flex space-x-2">
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
            </div>
        </div>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}

// Hide typing indicator
function hideTypingIndicator() {
    const indicator = document.getElementById('typing-indicator');
    if (indicator) indicator.remove();
}

async function getChatMarkdown() {
    let entries = chatHistory;

    // In agent mode, the server conversation is the source of truth.
    if (currentConversationId) {
        try {
            const response = await api.get(`api/ai-agent.php?action=get_conversation&id=${currentConversationId}`);
            if (response.success && response.data?.conversation?.messages) {
                entries = response.data.conversation.messages.map(msg => ({
                    role: msg.role,
                    content: msg.content || ''
                }));
            }
        } catch (error) {
            console.warn('Failed to load latest conversation for export, using local cache.', error);
        }
    }

    if (!entries.length) return '';

    const timestamp = new Date().toISOString();
    const lines = [
        '# AI Chat Export',
        '',
        `Generated: ${timestamp}`,
        ''
    ];

    entries.forEach(entry => {
        const label = entry.role === 'assistant' ? 'Assistant' : 'User';
        lines.push(`## ${label}`);
        lines.push(entry.content || '');
        lines.push('');
    });

    return lines.join('\n').trim() + '\n';
}

async function copyChatTranscript() {
    const content = await getChatMarkdown();
    if (!content) {
        showToast('No chat to copy yet', 'info');
        return;
    }
    try {
        await navigator.clipboard.writeText(content);
        showToast('Chat copied to clipboard', 'success');
    } catch (error) {
        console.error('Clipboard copy failed:', error);
        showToast('Copy failed', 'error');
    }
}

async function exportChatMarkdown() {
    const content = await getChatMarkdown();
    if (!content) {
        showToast('No chat to export yet', 'info');
        return;
    }
    const dateStamp = new Date().toISOString().slice(0, 10);
    const filename = `ai-chat-${dateStamp}.md`;
    const blob = new Blob([content], { type: 'text/markdown' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    showToast('Markdown exported', 'success');
}

async function saveChatToNotes() {
    const content = await getChatMarkdown();
    if (!content) {
        showToast('No chat to save yet', 'info');
        return;
    }
    const title = `AI Chat - ${new Date().toISOString().slice(0, 10)}`;
    try {
        const response = await api.post('api/notes.php', {
            title,
            content,
            csrf_token: CSRF_TOKEN
        });
        if (response.success) {
            showToast('Chat saved to notes', 'success');
        } else {
            showToast(response.error || 'Failed to save chat', 'error');
        }
    } catch (error) {
        console.error('Failed to save chat to notes:', error);
        showToast('Failed to save chat', 'error');
    }
}

// =====================
// Agent Mode Functions
// =====================

// Toggle conversation history sidebar
function toggleConversationHistory() {
    const sidebar = document.getElementById('conversation-sidebar');
    sidebar.classList.toggle('hidden');
    if (!sidebar.classList.contains('hidden')) {
        loadConversations();
    }
}

// Load conversations list
async function loadConversations() {
    try {
        const response = await api.get('api/ai-agent.php?action=list_conversations');
        if (response.success && response.data?.conversations) {
            renderConversationList(response.data.conversations);
        }
    } catch (error) {
        console.error('Failed to load conversations:', error);
    }
}

// Render conversation list
function renderConversationList(conversations) {
    const container = document.getElementById('conversation-list');
    if (!container) return;

    if (conversations.length === 0) {
        container.innerHTML = '<div class="p-3 text-sm text-gray-500 text-center">No conversations yet</div>';
        return;
    }

    container.innerHTML = conversations.map(conv => `
        <div class="conversation-item p-3 border-b border-gray-200 cursor-pointer hover:bg-gray-100 ${conv.id === currentConversationId ? 'bg-blue-50' : ''}"
             onclick="loadConversation('${conv.id}')">
            <div class="text-sm font-medium text-gray-900 truncate">${escapeHtml(conv.title)}</div>
            <div class="text-xs text-gray-500 mt-1">
                ${conv.messageCount || 0} messages · ${timeAgo(conv.updatedAt)}
            </div>
        </div>
    `).join('');
}

// Load a specific conversation
async function loadConversation(conversationId) {
    try {
        const response = await api.get(`api/ai-agent.php?action=get_conversation&id=${conversationId}`);
        if (response.success && response.data?.conversation) {
            currentConversationId = conversationId;
            const conv = response.data.conversation;
            
            // Set message count for polling baseline
            lastMessageCount = (conv.messages || []).length;

            // Clear and reload messages
            const container = document.getElementById('chat-messages');
            container.innerHTML = '';

            conv.messages.forEach(msg => {
                if (msg.role === 'user') {
                    addChatMessage('user', msg.content);
                } else if (msg.role === 'assistant') {
                    addChatMessage('assistant', msg.content, msg.functionCalls || [], msg.toolResults || []);
                }
            });

            // Highlight active conversation
            document.querySelectorAll('.conversation-item').forEach(item => {
                item.classList.remove('bg-blue-50');
            });
            const activeItem = document.querySelector(`.conversation-item[onclick*="${conversationId}"]`);
            if (activeItem) activeItem.classList.add('bg-blue-50');

            // Close sidebar on mobile
            if (window.innerWidth < 768) {
                document.getElementById('conversation-sidebar').classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Failed to load conversation:', error);
        showToast('Failed to load conversation', 'error');
    }
}

// Start a new conversation
function startNewConversation() {
    currentConversationId = null;
    chatHistory = [];
    lastMessageCount = 0; // Reset for polling
    const container = document.getElementById('chat-messages');
    container.innerHTML = `
        <div class="text-center text-gray-400 text-sm py-8">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            Start a conversation with the AI assistant
        </div>
    `;
    loadConversations();
    showToast('Started new conversation', 'info');
}

// Update conversation state (called when provider/model changes)
function updateConversationState() {
    // Reload current conversation if active
    if (currentConversationId) {
        loadConversation(currentConversationId);
    }
}

// Polling variables
let pollInterval = null;
let lastMessageCount = 0;
let isPolling = false;

async function cancelCurrentAgentRun() {
    if (!currentConversationId) {
        stopPolling();
        hideTypingIndicator();
        return;
    }

    try {
        await api.post('api/ai-agent.php?action=cancel', {
            conversationId: currentConversationId,
            csrf_token: CSRF_TOKEN
        });
    } catch (error) {
        console.error('Failed to cancel agent run:', error);
    } finally {
        stopPolling();
        hideTypingIndicator();
        showToast('Stopping assistant...', 'info');
        await refreshConversation();
    }
}

// Start polling for updates
function startPolling() {
    if (isPolling) return;
    isPolling = true;
    
    // Change Send button to Stop
    const sendBtn = document.getElementById('send-message');
    if (sendBtn) {
        sendBtn.innerHTML = '<i class="fas fa-stop"></i>';
        sendBtn.classList.add('bg-red-600', 'hover:bg-red-700');
        sendBtn.classList.remove('bg-black', 'hover:bg-gray-800');
        sendBtn.onclick = (e) => {
            e.preventDefault();
            cancelCurrentAgentRun();
        };
    }

    // Poll every 3 seconds
    pollInterval = setInterval(refreshConversation, 3000);
}

// Stop polling for updates
function stopPolling() {
    isPolling = false;
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }

    // Revert Send button
    const sendBtn = document.getElementById('send-message');
    if (sendBtn) {
        sendBtn.innerHTML = '<svg class="w-5 h-5 transform rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>';
        sendBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
        sendBtn.classList.add('bg-black', 'hover:bg-gray-800');
        // Restore original handler (assumed form submission handles it)
        sendBtn.onclick = null; 
    }
}

// Smart refresh of conversation logic
async function refreshConversation() {
    if (!currentConversationId) return;

    try {
        const response = await api.get(`api/ai-agent.php?action=get_conversation&id=${currentConversationId}`);
        if (response.success && response.data?.conversation) {
            const conv = response.data.conversation;
            const messages = conv.messages || [];

            // If new messages found
            if (messages.length > lastMessageCount) {
                // Determine start index
                const newMsgs = messages.slice(lastMessageCount);
                
                newMsgs.forEach(msg => {
                    if (msg.role === 'user') {
                        // Skip users own last message? No, showing it is fine
                        // But usually we already appended it locally. 
                        // To avoid duplicates, we check content?
                        // For simplicity, we only append ASSISTANT messages or SYSTEM messages
                        // Because User msg is added immediately by handleSubmit
                    }
                     
                    if (msg.role === 'assistant') {
                         addChatMessage('assistant', msg.content, msg.functionCalls || [], msg.toolResults || []);
                    }
                });
                
                lastMessageCount = messages.length;
            }
        }
    } catch (e) {
        console.error("Polling error:", e);
    }
}

// ... existing code ...

// Initial load of conversations
window.addEventListener('DOMContentLoaded', () => {
    // After initial setup, load conversations
    setTimeout(() => {
        if (agentModeEnabled) {
            loadConversations();
        }
    }, 1000);
});
</script>

