<?php
// AI Assistant View
$db = new Database(getMasterPassword());
$config = $db->load('config');
$projects = $db->load('projects');
$models = $db->load('models');

$hasGroqKey = !empty($config['groqApiKey']);
$hasOpenRouterKey = !empty($config['openrouterApiKey']);
$hasAnyKey = $hasGroqKey || $hasOpenRouterKey;

// Filter enabled models
$groqModels = array_filter($models['groq'] ?? [], fn($m) => $m['enabled']);
$openRouterModels = array_filter($models['openrouter'] ?? [], fn($m) => $m['enabled']);
?>

<div class="space-y-6">
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
    
    <!-- AI Tools Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Task Generator -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Task Generator</h3>
                    <p class="text-sm text-gray-500">Generate task breakdown from project description</p>
                </div>
            </div>
            <button onclick="openTaskGenerator()" class="w-full py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition" <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
                Generate Tasks
            </button>
        </div>
        
        <!-- PRD Generator -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">PRD Generator</h3>
                    <p class="text-sm text-gray-500">Create product requirements document</p>
                </div>
            </div>
            <button onclick="openPRDGenerator()" class="w-full py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition" <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
                Generate PRD
            </button>
        </div>
    </div>
    
    <!-- Chat Interface -->
    <div class="bg-white rounded-xl border border-gray-200">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">AI Chat</h3>
            <div class="flex items-center gap-2">
                <select id="ai-provider" onchange="updateModelList()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <?php if ($hasGroqKey): ?><option value="groq">Groq</option><?php endif; ?>
                    <?php if ($hasOpenRouterKey): ?><option value="openrouter">OpenRouter</option><?php endif; ?>
                </select>
                <select id="ai-model" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <!-- Populated by JS -->
                </select>
            </div>
        </div>
        
        <div id="chat-messages" class="h-96 overflow-y-auto p-4 space-y-4">
            <div class="text-center text-gray-400 text-sm py-8">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                Start a conversation with the AI assistant
            </div>
        </div>
        
        <div class="p-4 border-t border-gray-200">
            <form id="chat-form" class="flex gap-3">
                <input type="text" id="chat-input" placeholder="Ask anything..." 
                       class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none"
                       <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
                <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition"
                        <?php echo !$hasAnyKey ? 'disabled' : ''; ?>>
                    Send
                </button>
            </form>
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
window.addEventListener('DOMContentLoaded', updateModelList);

let chatHistory = [];

// Task Generator
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Import to Project (Optional)</label>
                    <select name="projectId" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Don't import - just show results</option>
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
        spinner.classList.remove('hidden');
        
        const formData = new FormData(e.target);
        const data = {
            description: formData.get('description'),
            provider: document.getElementById('ai-provider')?.value || 'groq',
            csrf_token: CSRF_TOKEN
        };
        
        const response = await api.post('api/ai.php?action=generate_tasks', data);
        spinner.classList.add('hidden');
        
        if (response.success && response.data?.tasks) {
            let html = '<div class="bg-gray-50 rounded-lg p-4 max-h-60 overflow-y-auto"><h4 class="font-medium mb-2">Generated Tasks:</h4><ul class="space-y-2">';
            response.data.tasks.forEach(task => {
                html += '<li class="text-sm"><strong>' + task.title + '</strong> (' + task.priority + ', ~' + task.estimatedMinutes + 'min)</li>';
            });
            html += '</ul></div>';
            result.innerHTML = html;
            result.classList.remove('hidden');
            
            showToast('Tasks generated!', 'success');
        } else {
            showToast(response.error || 'Generation failed', 'error');
        }
    });
}

// PRD Generator
function openPRDGenerator() {
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
        const response = await api.post('api/ai.php?action=generate_prd', {
            idea: formData.get('idea'),
            provider: document.getElementById('ai-provider')?.value || 'groq',
            csrf_token: CSRF_TOKEN
        });
        
        if (response.success && response.data?.prd) {
            closeModal();
            openModal('<div class="p-6"><h3 class="font-semibold mb-4">Generated PRD</h3><div class="prose prose-sm max-h-96 overflow-y-auto"><pre class="whitespace-pre-wrap text-sm">' + response.data.prd + '</pre></div></div>');
            showToast('PRD generated!', 'success');
        } else {
            showToast(response.error || 'Generation failed', 'error');
        }
    });
}

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
    
    // Get AI response
    const response = await api.post('api/ai.php?action=chat', {
        messages: chatHistory,
        provider: document.getElementById('ai-provider')?.value || 'groq',
        model: document.getElementById('ai-model')?.value || '',
        csrf_token: CSRF_TOKEN
    });
    
    if (response.success && response.data?.response) {
        chatHistory.push({ role: 'assistant', content: response.data.response });
        addChatMessage('assistant', response.data.response);
    } else {
        addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
    }
});

function addChatMessage(role, content) {
    const container = document.getElementById('chat-messages');
    const isUser = role === 'user';
    
    const placeholder = container.querySelector('.text-center');
    if (placeholder) placeholder.remove();
    
    const div = document.createElement('div');
    div.className = 'flex ' + (isUser ? 'justify-end' : 'justify-start');
    div.innerHTML = `
        <div class="max-w-[80%] px-4 py-2 rounded-xl ${isUser ? 'bg-black text-white' : 'bg-gray-100 text-gray-900'}">
            <pre class="whitespace-pre-wrap text-sm font-sans">${content}</pre>
        </div>
    `;
    container.appendChild(div);
    container.scrollTop = container.scrollHeight;
}
</script>
