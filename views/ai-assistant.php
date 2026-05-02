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

$hasGroqKey       = !empty($config['groqApiKey']);
$hasOpenRouterKey  = !empty($config['openrouterApiKey']);
$hasGeminiKey      = !empty($config['geminiApiKey']);
$ollamaUrl         = $config['ollamaUrl'] ?? 'http://localhost:11434';
$hasOllama         = !empty($models['ollama']); // Ollama available if models have been detected
$hasAnyKey         = $hasGroqKey || $hasOpenRouterKey || $hasGeminiKey || $hasOllama;

// Filter enabled models from database
$groqModels        = array_values(array_filter($models['groq'] ?? [],        fn($m) => $m['enabled'] ?? true));
$openRouterModels  = array_values(array_filter($models['openrouter'] ?? [],   fn($m) => $m['enabled'] ?? true));
$geminiModels      = array_values(array_filter($models['gemini'] ?? [],       fn($m) => $m['enabled'] ?? true));
$ollamaModels      = array_values(array_filter($models['ollama'] ?? [],       fn($m) => $m['enabled'] ?? true));

// Per-provider fallback: only use static list for providers that have NO DB models
if (empty($groqModels)) {
    $groqModels = array_map(fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(GroqAPI::getModels()), GroqAPI::getModels());
}
if (empty($openRouterModels)) {
    $openRouterModels = array_map(fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(OpenRouterAPI::getModels()), OpenRouterAPI::getModels());
}
if (empty($geminiModels) && $hasGeminiKey) {
    $geminiModels = array_map(fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(GeminiAPI::getModels()), GeminiAPI::getModels());
}
?>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fadeInUp 0.5s ease-out forwards;
}
/* Markdown rendered AI messages */
.ai-markdown { font-size:0.875rem; line-height:1.6; }
.ai-markdown p { margin:0.35em 0; }
.ai-markdown h1,.ai-markdown h2,.ai-markdown h3 { font-weight:600; margin:0.6em 0 0.25em; }
.ai-markdown ul,.ai-markdown ol { padding-left:1.4em; margin:0.35em 0; }
.ai-markdown li { margin:0.15em 0; }
.ai-markdown code { background:#f3f4f6; padding:0.1em 0.35em; border-radius:3px; font-size:0.82em; font-family:ui-monospace,monospace; }
.ai-markdown pre { background:#1e1e2e; color:#cdd6f4; padding:0.75em 1em; border-radius:6px; overflow-x:auto; margin:0.5em 0; }
.ai-markdown pre code { background:none; padding:0; color:inherit; font-size:0.82em; }
.ai-markdown table { border-collapse:collapse; width:100%; margin:0.5em 0; font-size:0.85em; }
.ai-markdown th,.ai-markdown td { border:1px solid #e5e7eb; padding:0.35em 0.65em; text-align:left; }
.ai-markdown th { background:#f9fafb; font-weight:600; }
.ai-markdown blockquote { border-left:3px solid #d1d5db; margin:0.4em 0; padding:0.25em 0.75em; color:#6b7280; }
.ai-markdown a { color:#2563eb; text-decoration:underline; }
.ai-markdown hr { border:none; border-top:1px solid #e5e7eb; margin:0.75em 0; }
</style>

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
    
    <!-- AI Tools Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
            AI Assistant
            <!-- Connection Status Icon -->
            <button onclick="runAIVerification()" id="ai-status-icon" class="p-1 rounded-full hover:bg-gray-100 transition" title="Check AI Connection">
                <div class="w-3 h-3 rounded-full bg-gray-300 ring-2 ring-white"></div>
            </button>
        </h2>

        <!-- Tools Dropdown -->
        <div class="relative inline-block text-left" id="ai-tools-dropdown">
            <button onclick="toggleToolsDropdown()" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-xl font-medium text-gray-700 hover:bg-gray-50 shadow-sm transition">
                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                </svg>
                AI Tools
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div id="ai-tools-menu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg ring-1 ring-black ring-opacity-5 z-50 transform origin-top-right transition-all duration-200">
                <div class="py-1">
                    <button onclick="openPRDGenerator()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Generate PRD
                    </button>
                    <button onclick="openModelSettingsSidebar()" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2 border-t border-gray-100">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Configure Models
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Off-Canvas Sidebar for Model Settings -->
    <div id="model-settings-sidebar" class="fixed inset-y-0 right-0 w-96 bg-white shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-[60] flex flex-col">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
            <h3 class="font-semibold text-gray-900">Configure AI Models</h3>
            <button onclick="closeModelSettingsSidebar()" class="text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="model-settings-sidebar-content" class="flex-1 overflow-y-auto p-4 space-y-6">
            <!-- Content populated by JS -->
            <div class="flex items-center justify-center h-full">
                <div class="spinner"></div>
            </div>
        </div>
    </div>

    <!-- Overlay for Sidebar -->
    <div id="model-settings-overlay" onclick="closeModelSettingsSidebar()" class="fixed inset-0 bg-black/30 z-[55] hidden transition-opacity duration-300 opacity-0"></div>

    <div class="bg-white rounded-xl border border-gray-200 flex flex-col" style="height: calc(100vh - 140px);">
        <div class="p-4 border-b border-gray-200 flex-shrink-0">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Chat Session</h3>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="startNewConversation()" class="px-3 py-1.5 bg-black text-white rounded-lg text-xs font-semibold hover:bg-gray-800 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        New Chat
                    </button>
                    <button type="button" onclick="clearConversationHistory()" class="px-3 py-1.5 border border-red-200 text-red-600 rounded-lg text-xs font-semibold hover:bg-red-50 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Clear
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

        <div class="flex flex-1 overflow-hidden">
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
                    <select id="ai-provider" onchange="updateModelList(); applyProviderConstraints(); updateConversationState()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <?php $configuredCount = ($hasGroqKey?1:0)+($hasOpenRouterKey?1:0)+($hasGeminiKey?1:0)+($hasOllama?1:0); ?>
                        <?php if ($configuredCount >= 2): ?><option value="smart">⚡ Smart (Auto-select)</option><?php endif; ?>
                        <option value="groq"><?= $hasGroqKey ? 'Groq' : 'Groq (key needed)' ?></option>
                        <option value="openrouter"><?= $hasOpenRouterKey ? 'OpenRouter' : 'OpenRouter (key needed)' ?></option>
                        <option value="gemini"><?= $hasGeminiKey ? 'Google Gemini' : 'Google Gemini (key needed)' ?></option>
                        <option value="ollama"><?= $hasOllama ? 'Ollama (Local)' : 'Ollama (not detected)' ?></option>
                    </select>
                    <select id="ai-model" onchange="updateConversationState()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <!-- Populated by JS -->
                    </select>
                    <select id="ai-kb-folder" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm" title="Knowledge Base context">
                        <option value="">No Knowledge Base</option>
                    </select>
                    <div class="relative">
                        <button type="button" onclick="toggleAssistantActionsMenu()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50">
                            Actions
                        </button>
                        <div id="assistant-actions-menu" class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-20">
                            <button type="button" onclick="copyChatTranscript(); closeAssistantActionsMenu();" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50">Copy</button>
                            <button type="button" onclick="exportChatMarkdown(); closeAssistantActionsMenu();" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50">Export MD</button>
                            <button type="button" onclick="openChatSaveToKnowledgeBase(); closeAssistantActionsMenu();" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50">Save to KB</button>
                            <button type="button" onclick="saveChatToNotes(); closeAssistantActionsMenu();" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50">Save to Notes</button>
                            <button type="button" onclick="startNewConversation(); closeAssistantActionsMenu();" class="block w-full text-left px-3 py-2 text-xs hover:bg-gray-50">New Chat</button>
                            <div class="border-t border-gray-100 my-1"></div>
                            <label class="flex items-center justify-between gap-2 px-3 py-2 text-xs hover:bg-gray-50 cursor-pointer">
                                <span>Show model dot</span>
                                <input type="checkbox" id="show-model-dot-toggle" class="w-3.5 h-3.5 accent-black" onchange="toggleProviderDot(this.checked)">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-4 min-h-0">
                    <div id="chat-empty-state" class="text-center text-gray-400 text-sm h-full flex flex-col justify-center items-center">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <p class="mb-4">Start a conversation with the AI assistant</p>
                        <div id="smart-start-suggestions" class="flex flex-wrap justify-center gap-2 max-w-lg mx-auto hidden transition-all duration-500 opacity-0">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <!-- Switch Provider Banner — shown when backend emits switch_provider_recommended -->
                <div id="switch-provider-banner" class="hidden mx-4 mb-2 p-3 rounded-lg border border-amber-300 bg-amber-50 text-sm text-amber-900 flex items-start gap-3 flex-shrink-0">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"></path>
                    </svg>
                    <div class="flex-1 min-w-0">
                        <div id="switch-provider-banner-text" class="font-medium"></div>
                        <div id="switch-provider-banner-actions" class="mt-2 flex flex-wrap gap-2"></div>
                    </div>
                    <button type="button" onclick="dismissSwitchProviderBanner()" class="text-amber-700 hover:text-amber-900" title="Dismiss">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
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
    'groq'        => $groqModels,
    'openrouter'  => $openRouterModels,
    'gemini'      => $geminiModels,
    'ollama'      => $ollamaModels
]); ?>;

function updateModelList() {
    const provider = document.getElementById('ai-provider').value;
    const modelSelect = document.getElementById('ai-model');
    if (!modelSelect) return;
    if (provider === 'smart') {
        modelSelect.innerHTML = `<option value="auto">Auto by provider</option>`;
        modelSelect.disabled = true;
        return;
    }
    modelSelect.disabled = false;
    const models = ALL_MODELS[provider] || [];
    modelSelect.innerHTML = `<option value="auto" class="font-bold">Auto (Best Available)</option>` +
        models.map(m => `<option value="${m.modelId}" ${m.isDefault ? 'selected' : ''}>${m.displayName}</option>`).join('');
}

// Per-provider dot colors for the inline reply indicator. Each provider gets a
// distinct hue so the user can glance and tell who answered without reading text.
window.PROVIDER_DOT_COLORS = {
    groq:       '#7c3aed', // violet
    openrouter: '#ea580c', // orange
    gemini:     '#2563eb', // blue
    ollama:     '#16a34a', // green
    smart:      '#0ea5e9'  // sky (rare — when smart isn't resolved yet)
};

// Toggle the "show provider dot" preference. Persists in localStorage and
// updates already-rendered messages so the change is immediate.
function toggleProviderDot(enabled) {
    localStorage.setItem('ai.showProviderDot', enabled ? '1' : '0');
    document.querySelectorAll('.provider-dot').forEach(el => {
        el.style.display = enabled ? '' : 'none';
    });
}

// Providers that cannot dispatch tool calls — must keep Agent Mode + Auto-Confirm
// + Knowledge Base off so the user is not misled into expecting tool actions.
const CHAT_ONLY_PROVIDERS = ['groq'];

function isChatOnlyProvider(provider) {
    return CHAT_ONLY_PROVIDERS.includes(String(provider || '').toLowerCase());
}

// Groq is a chat-only provider with its own model. Selection = sticky: only
// Groq is called, only Groq is billed. KB selector hidden (KB inflates context
// past Groq's 8K TPM) and Agent toggle locked off (Groq cannot tool-call). For
// KB or actions, the user should pick Smart Auto or OpenRouter/Gemini directly.
function applyProviderConstraints() {
    const providerSelect = document.getElementById('ai-provider');
    const provider = providerSelect ? providerSelect.value : '';
    const chatOnly = isChatOnlyProvider(provider);
    const agent = document.getElementById('agent-mode');
    const agentStatus = document.getElementById('agent-mode-status');
    const autoConf = document.getElementById('auto-confirm');
    const kb = document.getElementById('ai-kb-folder');

    if (agent) {
        if (chatOnly) {
            agent.checked = false;
            agent.disabled = true;
            agentModeEnabled = false;
            if (agentStatus) agentStatus.textContent = '(Groq is chat-only. For actions/KB pick Smart, OpenRouter, or Gemini.)';
        } else {
            agent.disabled = false;
            agentModeEnabled = !!agent.checked;
            if (agentStatus) agentStatus.textContent = agent.checked ? '(AI can perform actions)' : '(AI only responds)';
        }
    }

    if (autoConf) {
        if (chatOnly) autoConf.checked = false;
        autoConf.disabled = true;
    }

    if (kb) {
        if (chatOnly) {
            kb.value = '';
            kb.disabled = true;
            kb.classList.add('hidden');
            selectedKbFolderId = '';
        } else {
            kb.disabled = false;
            kb.classList.remove('hidden');
        }
    }
}

function closeAssistantActionsMenu() {
    document.getElementById('assistant-actions-menu')?.classList.add('hidden');
}

function toggleAssistantActionsMenu() {
    document.getElementById('assistant-actions-menu')?.classList.toggle('hidden');
}

function isComplexPrompt(prompt) {
    const text = String(prompt || '').toLowerCase();
    if (text.length > 220) return true;
    const keywords = ['complex', 'analyze', 'analysis', 'plan', 'strategy', 'architecture', 'debug', 'refactor', 'workflow', 'agentic', 'multi-step', 'tradeoff', 'prioritize', 'schedule'];
    return keywords.some((k) => text.includes(k));
}

// Ordered list of providers that are actually configured — used by smart mode
function getConfiguredProviders() {
    const configured = [];
    if (<?= $hasGroqKey ? 'true' : 'false' ?>) configured.push('groq');
    if (<?= $hasOpenRouterKey ? 'true' : 'false' ?>) configured.push('openrouter');
    if (<?= $hasGeminiKey ? 'true' : 'false' ?>) configured.push('gemini');
    if (<?= $hasOllama ? 'true' : 'false' ?>) configured.push('ollama');
    return configured;
}

function resolveProviderForMessage(message) {
    const selected = document.getElementById('ai-provider')?.value || 'groq';
    if (selected !== 'smart') return selected;
    const configured = getConfiguredProviders();
    if (!configured.length) return 'groq';
    // For complex prompts prefer a richer model provider if available
    if (isComplexPrompt(message)) {
        return configured.find(p => p === 'openrouter' || p === 'gemini') || configured[0];
    }
    return configured[0];
}

function resolveModelForProvider(provider, smartMode = false) {
    const isSmartMode = smartMode || document.getElementById('ai-provider')?.value === 'smart';
    if (!isSmartMode) {
        return document.getElementById('ai-model')?.value || undefined;
    }
    const models = ALL_MODELS[provider] || [];
    const preferred = models.find((m) => m.isDefault) || models[0];
    return preferred?.modelId || 'auto';
}

// Initial population
window.addEventListener('DOMContentLoaded', () => {
    updateModelList();
    applyProviderConstraints();
    loadKBFolders();
    checkPHPRequirements();

    // Sync the "show model dot" checkbox with the persisted preference (default ON).
    const dotToggle = document.getElementById('show-model-dot-toggle');
    if (dotToggle) {
        dotToggle.checked = localStorage.getItem('ai.showProviderDot') !== '0';
    }

    // Ctrl+Enter / Cmd+Enter to send message
    document.getElementById('chat-input')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            document.getElementById('chat-form')?.dispatchEvent(new Event('submit', { bubbles: true }));
        }
    });

    // Close dropdown on click outside
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('ai-tools-menu');
        const button = document.getElementById('ai-tools-dropdown');
        if (!dropdown.contains(e.target) && !button.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
        const actionsMenu = document.getElementById('assistant-actions-menu');
        const actionsBtn = e.target?.closest?.('button[onclick*="toggleAssistantActionsMenu"]');
        if (actionsMenu && !actionsMenu.contains(e.target) && !actionsBtn) {
            actionsMenu.classList.add('hidden');
        }
    });
});

function toggleToolsDropdown() {
    document.getElementById('ai-tools-menu').classList.toggle('hidden');
}

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

// Persistence: save the active conversation id to localStorage so refreshing
// the page restores the same chat instead of dropping the user back to an
// empty start state. Empty / null clears the slot.
const AI_CONVERSATION_STORAGE_KEY = 'ai.currentConversationId';
function _persistConvId() {
    try {
        if (currentConversationId) {
            localStorage.setItem(AI_CONVERSATION_STORAGE_KEY, String(currentConversationId));
        } else {
            localStorage.removeItem(AI_CONVERSATION_STORAGE_KEY);
        }
    } catch (_) { /* localStorage may be unavailable in private mode */ }
}
let agentModeEnabled = true;
let autoConfirmEnabled = true;
let pendingActions = [];
let eventCursor = 0;
let timelineEvents = [];

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

function renderMarkdown(content) {
    if (typeof marked === 'undefined' || typeof DOMPurify === 'undefined') {
        return '<pre class="whitespace-pre-wrap text-sm font-sans">' + escapeHtml(content) + '</pre>';
    }
    try {
        // Custom renderer: intercept mermaid code blocks
        const renderer = new marked.Renderer();
        renderer.code = function({ text, lang }) {
            if ((lang || '').toLowerCase().trim() === 'mermaid') {
                return `<div class="mermaid-block my-3 p-3 bg-white border border-gray-200 rounded-xl overflow-x-auto"><div class="mermaid">${escapeHtml(text)}</div></div>`;
            }
            // Default: let marked handle it
            const escaped = escapeHtml(text);
            const cls = lang ? ` class="language-${escapeHtml(lang)}"` : '';
            return `<pre><code${cls}>${escaped}</code></pre>`;
        };
        marked.use({ renderer, breaks: true, gfm: true });
        const html = marked.parse(content);
        // Allow div + class/id so mermaid containers survive sanitization
        return '<div class="ai-markdown">' + DOMPurify.sanitize(html, { ADD_TAGS: ['div'], ADD_ATTR: ['class', 'id'] }) + '</div>';
    } catch (e) {
        return '<pre class="whitespace-pre-wrap text-sm font-sans">' + escapeHtml(content) + '</pre>';
    }
}

function renderMermaidInEl(el) {
    if (typeof mermaid === 'undefined') return;
    const nodes = el.querySelectorAll('.mermaid');
    if (!nodes.length) return;
    mermaid.run({ nodes }).catch(() => {});
}

function copyMsgText(btn) {
    const text = btn.dataset.text;
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.innerHTML;
        btn.textContent = '✓ Copied';
        setTimeout(() => { btn.innerHTML = orig; }, 1500);
    }).catch(() => {
        showToast('Copy failed', 'error');
    });
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
            provider: resolveProviderForMessage(String(formData.get('idea') || '')),
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
    const providerSelection = document.getElementById('ai-provider').value;
    const provider = providerSelection === 'smart' ? (getConfiguredProviders()[0] || 'groq') : providerSelection;
    const model = providerSelection === 'smart'
        ? (ALL_MODELS[provider]?.find?.(m => m.isDefault)?.modelId || ALL_MODELS[provider]?.[0]?.modelId || 'auto')
        : document.getElementById('ai-model').value;
    
    // Animate icon
    const icon = document.getElementById('ai-status-icon');
    if (icon) {
        icon.innerHTML = '<div class="w-3 h-3 rounded-full bg-yellow-400 ring-2 ring-white animate-pulse"></div>';
    }
    
    let results = [];
    
    try {
        // Use the unified test_connection endpoint
        const response = await api.post('api/ai.php?action=test_connection', {
            provider: provider,
            model: model,
            csrf_token: CSRF_TOKEN
        });

        if (response.success && response.data.connected) {
             results.push({ 
                provider: provider.charAt(0).toUpperCase() + provider.slice(1), 
                model, 
                success: true,
                response: response.data.response,
                latency: '0.5', // Simulated/approximate since backend doesn't return it yet
                status_code: 200,
                timestamp: new Date().toLocaleTimeString()
            });
        } else {
            throw new Error(response.data?.error || response.error || 'Connection failed');
        }
    } catch (error) {
        results.push({
            provider: provider.charAt(0).toUpperCase() + provider.slice(1),
            model,
            success: false,
            error: error.message || 'Failed to connect',
            latency: 0,
            status_code: error.status || 500,
            timestamp: new Date().toLocaleTimeString()
        });
    }
    
    // Update icon based on result
    if (icon) {
        const allSuccess = results.every(r => r.success);
        icon.innerHTML = `<div class="w-3 h-3 rounded-full ${allSuccess ? 'bg-green-500' : 'bg-red-500'} ring-2 ring-white"></div>`;
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
async function sendAgentMessage(message) {
    showTypingIndicator(); // Keep showing typing indicator (with polling tailored details)
    timelineEvents = [];
    renderTimeline();

    if (!currentConversationId) {
        try {
            const startResponse = await api.post('api/ai-agent.php?action=start_conversation', {
                titleSeed: message,
                csrf_token: CSRF_TOKEN
            });
            if (startResponse.success && startResponse.data?.conversationId) {
                currentConversationId = startResponse.data.conversationId;
                _persistConvId();
                eventCursor = 0;
            }
        } catch (error) {
            console.warn('Failed to pre-start conversation, fallback to chat create.', error);
        }
    }

    if (currentConversationId && eventCursor === 0) {
        await syncEventCursorToLatest();
    }

    startPolling();        // Start real-time updates

    const isSmart = document.getElementById('ai-provider')?.value === 'smart';
    const firstProvider = resolveProviderForMessage(message);
    const providersToTry = isSmart
        ? [firstProvider, ...getConfiguredProviders().filter(p => p !== firstProvider)]
        : [firstProvider];

    for (let i = 0; i < providersToTry.length; i++) {
        const effectiveProvider = providersToTry[i];
        const effectiveModel = resolveModelForProvider(effectiveProvider, isSmart);

        if (i > 0) {
            const providerLabel = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
            showToast(`Retrying with ${providerLabel}...`, 'info');
        }

        try {
            const response = await api.post('api/ai-agent.php?action=chat', {
                message: message,
                conversationId: currentConversationId,
                autoConfirm: autoConfirmEnabled,
                provider: effectiveProvider,
                model: effectiveModel,
                kbFolderId: document.getElementById('ai-kb-folder')?.value || null,
                // Smart Auto pipeline signal — backend uses Groq to polish the
                // final user-facing summary when smartMode=true and the answering
                // provider was not Groq.
                smartMode: document.getElementById('ai-provider')?.value === 'smart',
                csrf_token: CSRF_TOKEN
            });

            stopPolling();
            hideTypingIndicator();

            if (response.success) {
                currentConversationId = response.data.conversationId;
                _persistConvId();
                await refreshConversation();
                loadConversations();
                return;
            } else {
                if (isSmart && i < providersToTry.length - 1) {
                    const label = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
                    showToast(`${label} unavailable, trying next...`, 'warning');
                    continue;
                }
                addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
                return;
            }
        } catch (error) {
            stopPolling();
            hideTypingIndicator();
            if (isSmart && i < providersToTry.length - 1) {
                const label = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
                showToast(`${label} failed, trying next...`, 'warning');
                // Re-show typing for retry
                showTypingIndicator();
                startPolling();
                continue;
            }
            const errorMessage = error.response?.error?.message || error.message || 'Failed to connect to AI service';
            addChatMessage('assistant', 'Error: ' + errorMessage);
            return;
        }
    }
}

// Estimate chars across a chat-history array. ~chars/4 ≈ tokens.
function estimateHistoryChars(history) {
    if (!Array.isArray(history)) return 0;
    let n = 0;
    for (const m of history) n += String(m?.content || '').length;
    return n;
}

// Hard cap on what we send to a chat-only provider (Groq). Groq's free-tier
// TPM is as low as 8000 tokens for some models (gpt-oss-120b), so we keep the
// outbound payload well under that. Drop oldest non-user turns first; always
// preserve at least the trailing user message.
function trimHistoryForChatOnly(history, maxChars) {
    if (!Array.isArray(history) || history.length === 0) return [];
    const out = [...history];
    let total = estimateHistoryChars(out);
    while (out.length > 1 && total > maxChars) {
        const removed = out.shift();
        total -= String(removed?.content || '').length;
    }
    // If the single remaining message is itself oversized, truncate its content.
    if (out.length === 1 && total > maxChars) {
        const last = out[0];
        const head = String(last?.content || '').slice(0, Math.max(0, maxChars - 200));
        out[0] = { ...last, content: head + '\n\n[truncated to fit chat-only model context]' };
    }
    return out;
}

// Heuristic — does the message imply a tool action (create/update/etc.)?
// Mirrored from AIAgent::looksLikeActionRequest so the frontend can route
// action-ish prompts away from Groq before round-tripping.
function looksLikeAction(message) {
    const t = String(message || '').toLowerCase();
    if (!t) return false;
    return /\b(create|add|make|new|build|update|edit|change|modify|rename|delete|remove|archive|cancel|complete|mark done|finish|close|send|email|invoice|bill|schedule|book|plan|log|record|track|export|import)\b/.test(t)
        || /start timer|stop timer|pomodoro/.test(t);
}

// Send message using regular Chat API
async function sendChatMessage(message) {
    showTypingIndicator();

    const userPicked = document.getElementById('ai-provider')?.value || 'groq';
    const isSmart = userPicked === 'smart';
    const configured = getConfiguredProviders();

    // Provider policy:
    // - Smart Auto = explicit opt-in to fallback. We try resolved-first, then
    //   the rest of the configured providers if it fails.
    // - Any explicit provider (groq, openrouter, gemini, ollama) = sticky.
    //   No silent fallback. If the user picked Groq, only Groq is called and
    //   only Groq is billed. If it fails, the user sees the error and decides.
    //   This avoids surprise charges on a different provider's API.
    let providersToTry;
    if (isSmart) {
        const firstProvider = resolveProviderForMessage(message);
        providersToTry = [firstProvider, ...configured.filter(p => p !== firstProvider)];
    } else {
        providersToTry = [userPicked];
    }

    for (let i = 0; i < providersToTry.length; i++) {
        const effectiveProvider = providersToTry[i];
        const effectiveModel = resolveModelForProvider(effectiveProvider, isSmart);

        // Surface provider + model in the typing indicator so the user sees what
        // is actually being called while the request is in flight (otherwise they
        // see only animated dots until the response lands).
        const detailEl = document.querySelector('#typing-indicator .typing-details');
        if (detailEl) {
            const label = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
            detailEl.textContent = `${label} · ${effectiveModel || 'auto'} · thinking…`;
        }

        if (i > 0) {
            const providerLabel = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
            showToast(`Retrying with ${providerLabel}...`, 'info');
        }

        // For chat-only providers, trim outbound history. Removed the previous
        // pre-flight refusal — it was firing on tiny prompts because chatHistory
        // accumulates prior assistant responses and even "hi" looked oversized
        // after one big turn. Trust backend TPM detection + auto-fallback below.
        const isChatOnly = isChatOnlyProvider(effectiveProvider);
        const outboundMessages = isChatOnly
            ? trimHistoryForChatOnly(chatHistory, 24000)
            : chatHistory;
        const hasNextProvider = i < providersToTry.length - 1;

        try {
            const response = await api.post('api/ai.php?action=chat', {
                messages: outboundMessages,
                provider: effectiveProvider,
                model: effectiveModel,
                // KB context is heavy → never send to chat-only providers. The
                // outer providersToTry already routed KB requests through a
                // non-chat-only provider when KB was selected.
                kbFolderId: isChatOnly ? '' : (document.getElementById('ai-kb-folder')?.value || ''),
                csrf_token: CSRF_TOKEN
            });

            hideTypingIndicator();

            // Smart Auto: structured switch hint = try next provider in chain.
            // Explicit pick: structured switch hint = show banner so user can
            // manually switch. We do NOT silently route to another billable API
            // when the user explicitly picked one provider.
            if (response && response.data && response.data.switchProviderRecommended) {
                if (isSmart && hasNextProvider) {
                    const nextProv = providersToTry[i + 1];
                    showToast(`${effectiveProvider} busy — trying ${nextProv}`, 'info');
                    showTypingIndicator();
                    continue;
                }
                showSwitchProviderBanner({
                    reason: response.data.reason || 'context_overflow',
                    suggestedProviders: response.data.suggestedProviders || []
                });
                addChatMessage('assistant', response.data.message || 'Provider hit a limit. Pick a different provider above to continue.');
                return;
            }

            if (response.success && response.data?.response) {
                chatHistory.push({ role: 'assistant', content: response.data.response });
                addChatMessage('assistant', response.data.response, [], [], {
                    provider: response.data.provider || effectiveProvider,
                    model: response.data.model || effectiveModel
                });
                return;
            } else {
                if (isSmart && hasNextProvider) {
                    const label = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
                    showToast(`${label} unavailable, trying next...`, 'warning');
                    showTypingIndicator();
                    continue;
                }
                addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
                return;
            }
        } catch (error) {
            const errMsg = error.response?.error?.message || error.message || 'Failed to connect to AI service';
            const isTpmError = isChatOnly && /request too large|tokens per minute|\bTPM\b/i.test(errMsg);
            if (isTpmError) {
                // Show banner so user can switch manually. No silent fallback —
                // prevents surprise charges on a non-picked provider.
                hideTypingIndicator();
                showSwitchProviderBanner({
                    reason: 'context_overflow',
                    suggestedProviders: getConfiguredProviders().filter(p => p !== 'groq' && p !== 'ollama')
                });
                addChatMessage('assistant', 'Groq hit its per-minute token limit. Wait ~60s for the window to clear, or pick OpenRouter / Gemini above.');
                return;
            }
            // Smart Auto cycles through providers on generic errors. Explicit
            // picks do NOT — they show the error so the user knows their choice
            // failed and can decide what to do.
            if (isSmart && hasNextProvider) {
                const label = effectiveProvider.charAt(0).toUpperCase() + effectiveProvider.slice(1);
                showToast(`${label} failed, trying next...`, 'warning');
                showTypingIndicator();
                continue;
            }
            hideTypingIndicator();
            addChatMessage('assistant', 'Error: ' + errMsg);
            return;
        }
    }
    hideTypingIndicator();
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

function addChatMessage(role, content, functionCalls = [], toolResults = [], meta = null) {
    const container = document.getElementById('chat-messages');
    const isUser = role === 'user';

    const placeholder = container.querySelector('#chat-empty-state');
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
        if (isUser) {
            html += `<pre class="whitespace-pre-wrap text-sm font-sans">${escapeHtml(content)}</pre>`;
        } else {
            html += renderMarkdown(content);
            // Provider/model indicator. By default a small colored dot per
            // provider — minimal visual noise but lets the user spot which AI
            // answered. Hover/title shows full "Provider · model". User can
            // disable via Actions menu → "Show model dot" toggle (preference
            // persists in localStorage).
            if (meta && (meta.provider || meta.model)) {
                const showDot = localStorage.getItem('ai.showProviderDot') !== '0';
                if (showDot) {
                    const provLabel = meta.provider ? meta.provider.charAt(0).toUpperCase() + meta.provider.slice(1) : 'AI';
                    const fullLabel = provLabel + (meta.model ? ' · ' + meta.model : '');
                    const dotColor = (window.PROVIDER_DOT_COLORS && window.PROVIDER_DOT_COLORS[String(meta.provider || '').toLowerCase()]) || '#6b7280';
                    html += `<span class="provider-dot inline-block w-2 h-2 rounded-full ml-2 align-middle" style="background:${dotColor};" title="${escapeHtml(fullLabel)}" data-provider="${escapeHtml(String(meta.provider || ''))}" data-model="${escapeHtml(String(meta.model || ''))}"></span>`;
                }
            }
            html += `<button onclick="copyMsgText(this)" data-text="${escapeHtml(content)}"
                class="mt-1 ml-2 text-xs text-gray-400 hover:text-gray-600 inline-flex items-center gap-1 transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>Copy</button>`;
        }
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
    renderMermaidInEl(div);
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
            stopPolling();
            hideTypingIndicator();
            // Clear UI
            document.getElementById('chat-messages').innerHTML = `
                <div id="chat-empty-state" class="text-center text-gray-400 text-sm h-full flex flex-col justify-center items-center">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <p class="mb-4">Start a conversation with the AI assistant</p>
                    <div id="smart-start-suggestions" class="flex flex-wrap justify-center gap-2 max-w-lg mx-auto hidden transition-all duration-500 opacity-0"></div>
                </div>
            `;
            chatHistory = [];
            currentConversationId = null;
            _persistConvId();
            eventCursor = 0;
            timelineEvents = [];

            // Reload conversation list
            loadConversations();
            loadSuggestions();

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

function renderTimeline() {
    const indicator = document.getElementById('typing-indicator');
    if (!indicator) return;
    const timeline = indicator.querySelector('.typing-timeline');
    if (!timeline) return;
    if (!timelineEvents.length) {
        timeline.innerHTML = '';
        return;
    }
    const lastItems = timelineEvents.slice(-8);
    timeline.innerHTML = lastItems.map((item) => {
        return `<div class="text-[11px] text-gray-600 leading-4">${escapeHtml(item)}</div>`;
    }).join('');
}

function applyConversationEvent(event) {
    const type = event?.type || '';
    const data = event?.data || {};
    let line = '';
    if (type === 'thinking_started') line = 'Thinking...';
    if (type === 'tool_started') line = `Running ${data.name || 'tool'}...`;
    if (type === 'tool_succeeded') line = `✓ ${data.summary || data.name || 'Tool completed'}`;
    if (type === 'tool_failed') line = `✕ ${data.name || 'tool'} failed: ${data.error || 'Unknown error'}`;
    if (type === 'token_retry') line = `Retrying with lower context/token budget...`;
    if (type === 'run_cancelled') line = 'Run cancelled.';
    if (type === 'run_error') line = `Error: ${data.error || 'Run failed'}`;
    if (type === 'summary_ready') line = 'Final summary ready.';
    if (type === 'switch_provider_recommended') {
        showSwitchProviderBanner(data);
        line = data.reason === 'context_overflow'
            ? 'Conversation hit chat-only context limit — see suggestion above.'
            : 'Conversation getting long for chat-only model — see suggestion above.';
    }

    if (line) {
        timelineEvents.push(line);
        updateLiveStatus(line);
        renderTimeline();
    }
}

// Render the sticky switch-provider banner above the input. Buttons render only
// for providers the backend reports as configured (suggestedProviders payload).
function showSwitchProviderBanner(data) {
    const banner = document.getElementById('switch-provider-banner');
    const text = document.getElementById('switch-provider-banner-text');
    const actions = document.getElementById('switch-provider-banner-actions');
    if (!banner || !text || !actions) return;

    const reason = data?.reason || 'context_near_limit';
    const headline = reason === 'context_overflow'
        ? 'This chat hit Groq\'s context limit.'
        : 'This conversation is getting long for Groq\'s chat window.';
    text.textContent = `${headline} Switch to OpenRouter or Google Gemini for longer context and tool actions.`;

    const suggested = Array.isArray(data?.suggestedProviders) && data.suggestedProviders.length
        ? data.suggestedProviders
        : ['openrouter', 'gemini'];
    const labelMap = { openrouter: 'Switch to OpenRouter', gemini: 'Switch to Gemini' };

    actions.innerHTML = '';
    suggested.forEach((p) => {
        const label = labelMap[p];
        if (!label) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'px-3 py-1.5 rounded-md bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700 transition';
        btn.textContent = label;
        btn.onclick = () => {
            const sel = document.getElementById('ai-provider');
            if (sel) {
                sel.value = p;
                updateModelList();
                applyProviderConstraints();
                if (typeof updateConversationState === 'function') updateConversationState();
            }
            dismissSwitchProviderBanner();
            document.getElementById('chat-input')?.focus();
        };
        actions.appendChild(btn);
    });

    banner.classList.remove('hidden');
}

function dismissSwitchProviderBanner() {
    document.getElementById('switch-provider-banner')?.classList.add('hidden');
}

async function pollConversationEvents() {
    if (!currentConversationId) return;
    try {
        const response = await api.get(`api/ai-agent.php?action=get_conversation_events&id=${encodeURIComponent(currentConversationId)}&cursor=${eventCursor}&limit=100`);
        if (!response.success || !response.data) return;
        const events = Array.isArray(response.data.events) ? response.data.events : [];
        events.forEach((event) => applyConversationEvent(event));
        eventCursor = Number(response.data.nextCursor || eventCursor || 0);
    } catch (error) {
        console.warn('Event polling error', error);
    }
}

async function syncEventCursorToLatest() {
    if (!currentConversationId) return;
    try {
        const response = await api.get(`api/ai-agent.php?action=get_conversation_events&id=${encodeURIComponent(currentConversationId)}&cursor=0&limit=1`);
        if (response.success && response.data) {
            eventCursor = Number(response.data.nextCursor || eventCursor || 0);
        }
    } catch (error) {
        console.warn('Event cursor sync error', error);
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

    let html = `<div class="max-w-[80%] px-4 py-2 rounded-xl bg-green-50 border border-green-200">`;
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
    const placeholder = container.querySelector('#chat-empty-state');
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
            <div class="typing-details text-xs text-gray-500 mt-2 font-mono"></div>
            <div class="typing-timeline mt-2 space-y-1"></div>
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
            _persistConvId();
            const conv = response.data.conversation;
            
            // Set message count for polling baseline
            lastMessageCount = (conv.messages || []).length;
            eventCursor = 0;
            timelineEvents = [];

            // Clear and reload messages
            const container = document.getElementById('chat-messages');
            container.innerHTML = '';

            conv.messages.forEach(msg => {
                if (msg.role === 'user') {
                    addChatMessage('user', msg.content);
                } else if (msg.role === 'assistant') {
                    // Skip tool-status messages saved by older builds. Tool
                    // progress is now surfaced through events, not the chat
                    // transcript, but old conversations still carry them.
                    const c = String(msg.content || '');
                    if (/^\s*Executing tool:\s/i.test(c)) return;
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
    stopPolling();
    hideTypingIndicator();
    currentConversationId = null;
    _persistConvId();
    chatHistory = [];
    lastMessageCount = 0; // Reset for polling
    eventCursor = 0;
    timelineEvents = [];
    const container = document.getElementById('chat-messages');
    container.innerHTML = `
        <div id="chat-empty-state" class="text-center text-gray-400 text-sm h-full flex flex-col justify-center items-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
            </svg>
            <p class="mb-4">Start a conversation with the AI assistant</p>
            <div id="smart-start-suggestions" class="flex flex-wrap justify-center gap-2 max-w-lg mx-auto hidden transition-all duration-500 opacity-0"></div>
        </div>
    `;
    loadConversations();
    loadSuggestions();
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

    // Poll every ~0.8s for snappier "thinking" updates while the agent works.
    // Faster than this risks flooding the rate limiter on long runs.
    pollInterval = setInterval(async () => {
        await pollConversationEvents();
        await refreshConversation();
    }, 800);
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
        sendBtn.innerHTML = 'Send';
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
                         const c = String(msg.content || '');
                         // Skip tool-status messages from older builds.
                         if (/^\s*Executing tool:\s/i.test(c)) return;
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

// Initial load of conversations
window.addEventListener('DOMContentLoaded', () => {
    // Restore the active conversation across page refresh — pull the last
    // conversationId from localStorage and re-load its messages so the user
    // doesn't lose context when reloading the tab.
    setTimeout(async () => {
        if (agentModeEnabled) {
            loadConversations();
        }
        loadSuggestions();
        try {
            const saved = localStorage.getItem(AI_CONVERSATION_STORAGE_KEY);
            if (saved && typeof loadConversation === 'function') {
                await loadConversation(saved);
            }
        } catch (_) { /* ignore */ }
    }, 1000);
});

// Smart Start Suggestions
async function loadSuggestions() {
    try {
        const response = await api.get('api/ai-agent.php?action=suggest_actions');
        if (response.success && response.data.suggestions) {
            renderSuggestions(response.data.suggestions);
        }
    } catch (e) {
        console.warn('Failed to load suggestions', e);
    }
}

function renderSuggestions(suggestions) {
    const container = document.getElementById('smart-start-suggestions');
    if (!container) return;
    
    if (suggestions.length === 0) return;
    
    container.innerHTML = suggestions.map(s => `
        <button onclick="useSuggestion('${escapeHtml(s.prompt)}')" 
                class="px-3 py-1.5 bg-white border border-gray-200 rounded-full text-xs font-medium text-gray-600 hover:border-black hover:text-black transition shadow-sm animate-fade-in-up">
            ✨ ${escapeHtml(s.label)}
        </button>
    `).join('');
    container.classList.remove('hidden');
    // Add small delay for fade in
    setTimeout(() => container.classList.remove('opacity-0'), 50);
}

function useSuggestion(prompt) {
    const input = document.getElementById('chat-input');
    if (!input) return;
    input.value = prompt;
    document.getElementById('chat-form').requestSubmit();
}

// =====================
// Model Settings Sidebar
// =====================

function openModelSettingsSidebar() {
    const sidebar = document.getElementById('model-settings-sidebar');
    const overlay = document.getElementById('model-settings-overlay');
    
    sidebar.classList.remove('translate-x-full');
    overlay.classList.remove('hidden');
    // slight delay to allow display:block to apply before opacity transition
    setTimeout(() => overlay.classList.remove('opacity-0'), 10);
    
    loadModelSettingsContent();
}

function closeModelSettingsSidebar() {
    const sidebar = document.getElementById('model-settings-sidebar');
    const overlay = document.getElementById('model-settings-overlay');
    
    sidebar.classList.add('translate-x-full');
    overlay.classList.add('opacity-0');
    setTimeout(() => overlay.classList.add('hidden'), 300);
}

async function loadModelSettingsContent() {
    const container = document.getElementById('model-settings-sidebar-content');
    container.innerHTML = '<div class="flex items-center justify-center h-full"><div class="spinner"></div></div>';

    try {
        const response = await api.get('api/models.php?action=list');
        if (response.success) {
            renderModelSettings(response.data);
        } else {
            container.innerHTML = '<p class="text-red-500 text-center mt-10">Failed to load models</p>';
        }
    } catch (error) {
        container.innerHTML = '<p class="text-red-500 text-center mt-10">Error loading models</p>';
    }
}

function renderModelSettings(models) {
    const container = document.getElementById('model-settings-sidebar-content');
    if (!container) return;

    let html = '<div class="space-y-6">';
    
    // Groq
    html += buildModelTable('Groq', 'groq', models.groq || []);
    
    // OpenRouter
    html += buildModelTable('OpenRouter', 'openrouter', models.openrouter || []);
    
    html += '</div>';
    
    container.innerHTML = html;
}

function buildModelTable(title, provider, models) {
    let html = `
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <h4 class="font-semibold text-gray-900">${title}</h4>
                <button onclick="openAddModelModal('${provider}')" class="px-3 py-1.5 bg-black text-white text-xs rounded-lg hover:bg-gray-800">
                    Add
                </button>
            </div>
            <div class="">
    `;
    
    if (models.length === 0) {
        html += `<div class="p-4 text-center text-gray-500 text-sm">No models configured</div>`;
    } else {
        html += `<div class="divide-y divide-gray-100">`;
        models.forEach(m => {
            const modelJson = JSON.stringify(m).replace(/"/g, '&quot;');
            html += `
                <div class="p-3 hover:bg-gray-50 transition group">
                    <div class="flex justify-between items-start mb-1">
                        <div class="font-medium text-gray-900 text-sm">${escapeHtml(m.displayName)}</div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="openEditModelModal(${modelJson}, '${provider}')" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Edit</button>
                            ${!m.isDefault ? `<button onclick="deleteModel('${m.id}')" class="text-red-600 hover:text-red-800 text-xs font-medium">Delete</button>` : ''}
                        </div>
                    </div>
                    <div class="text-xs font-mono text-gray-500 truncate mb-2">${escapeHtml(m.modelId)}</div>
                    <div class="flex items-center justify-between">
                        <span class="px-2 py-0.5 text-[10px] uppercase tracking-wider font-bold rounded-full ${m.enabled ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500'}">
                            ${m.enabled ? 'Active' : 'Disabled'}
                        </span>
                        ${m.isDefault ? '<span class="text-[10px] font-bold text-blue-600">Default</span>' : ''}
                    </div>
                </div>
            `;
        });
        html += `</div>`;
    }
    
    html += `</div></div>`;
    return html;
}

function openAddModelModal(provider) {
    // Close parent modal first? No, we can stack or replace.
    // Ideally we replace content or stack. The modal system supports one modal usually.
    // Let's replace content.
    
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Add ${provider} Model</h3>
            <form id="add-model-form" class="space-y-4">
                <input type="hidden" name="provider" value="${provider}">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                    <input type="text" name="displayName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model ID</label>
                    <input type="text" name="modelId" required placeholder="e.g., llama-3.3-70b-versatile" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono">
                    <p class="text-xs text-gray-500 mt-1">Use the provider model identifier. API keys are managed in Settings.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="enabled" checked id="model-enabled" class="rounded border-gray-300">
                    <label for="model-enabled" class="ml-2 text-sm text-gray-600">Enabled</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg">Add Model</button>
                </div>
            </form>
        </div>
    `);
    
    document.getElementById('add-model-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.enabled = !!data.enabled;

        try {
            const result = await api.post('api/models.php?action=add', data);
            if (result.success) {
                showToast(result.message || 'Model added successfully', 'success');
                closeModal();
                loadModelSettingsContent(); // Refresh sidebar
            } else {
                showToast(result.error?.message || 'Failed to add model', 'error');
            }
        } catch (error) {
            console.error('Add model error:', error);
            showToast('Failed to add model', 'error');
        }
    });
}

function openEditModelModal(model, provider) {
    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Edit Model</h3>
            <form id="edit-model-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                    <input type="text" name="displayName" value="${escapeHtml(model.displayName)}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Model ID</label>
                    <input type="text" name="modelId" value="${escapeHtml(model.modelId)}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono">
                    <p class="text-xs text-gray-500 mt-1">Use the provider model identifier. API keys are managed in Settings.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg">${escapeHtml(model.description)}</textarea>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="enabled" ${model.enabled ? 'checked' : ''} id="edit-model-enabled" class="rounded border-gray-300">
                    <label for="edit-model-enabled" class="ml-2 text-sm text-gray-600">Enabled</label>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg">Update Model</button>
                </div>
            </form>
        </div>
    `);
    
    document.getElementById('edit-model-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        data.enabled = !!data.enabled;

        try {
            const result = await api.post(`api/models.php?action=update&id=${model.id}`, data);
            if (result.success) {
                showToast(result.message || 'Model updated successfully', 'success');
                closeModal();
                loadModelSettingsContent(); // Refresh sidebar
            } else {
                showToast(result.error?.message || 'Failed to update model', 'error');
            }
        } catch (error) {
            console.error('Update model error:', error);
            showToast('Failed to update model', 'error');
        }
    });
}

async function deleteModel(id) {
    if (!confirm('Are you sure you want to delete this model?')) return;
    try {
        const result = await api.delete(`api/models.php?action=delete&id=${id}`);
        if (result.success) {
            showToast(result.message || 'Model deleted successfully', 'success');
            loadModelSettingsContent(); // Refresh sidebar
        } else {
            showToast(result.error?.message || 'Failed to delete model', 'error');
        }
    } catch (error) {
        console.error('Delete model error:', error);
        showToast('Failed to delete model', 'error');
    }
}
</script>
