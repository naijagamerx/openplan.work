<?php
/**
 * Mobile AI Assistant Page
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
    $config = $db->load('config');
    $projects = $db->load('projects');
    $modelsLoad = $db->safeLoad('models');
    $models = $modelsLoad['success'] ? $modelsLoad['data'] : [];
} catch (Exception $e) {
    $config = [];
    $projects = [];
    $models = [];
}

$hasGroqKey = !empty($config['groqApiKey']);
$hasOpenRouterKey = !empty($config['openrouterApiKey']);
$hasAnyKey = $hasGroqKey || $hasOpenRouterKey;

$groqModels = array_values(array_filter($models['groq'] ?? [], static fn($m) => $m['enabled'] ?? true));
$openRouterModels = array_values(array_filter($models['openrouter'] ?? [], static fn($m) => $m['enabled'] ?? true));
if (empty($groqModels) && empty($openRouterModels)) {
    $groqModels = array_map(
        static fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(GroqAPI::getModels()),
        GroqAPI::getModels()
    );
    $openRouterModels = array_map(
        static fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(OpenRouterAPI::getModels()),
        OpenRouterAPI::getModels()
    );
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>AI Assistant - <?= htmlspecialchars($siteName) ?></title>

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
      colors: { primary: "#000000", accent: "#ffffff" },
      fontFamily: { display: ["Inter", "sans-serif"] }
    }
  }
}
</script>
<style type="text/tailwindcss">
  @layer base {
    body { @apply bg-white text-black font-display antialiased; }
  }
  .no-scrollbar::-webkit-scrollbar { display: none; }
  .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  .tool-card { @apply flex-shrink-0 w-28 h-28 p-3 border border-black flex flex-col justify-between; }
  .chat-message { @apply mb-3; }
  .chat-message.user { @apply flex justify-end; }
  .chat-message.assistant { @apply flex justify-start; }
  .chat-bubble { @apply max-w-[86%] border border-black px-3 py-2 text-sm whitespace-pre-wrap break-words; }
  .chat-message.user .chat-bubble { @apply bg-black text-white; }
  .chat-message.assistant .chat-bubble { @apply bg-white text-black; }
</style>
</head>
<body class="bg-gray-100 flex justify-center">
<div class="relative w-full max-w-[420px] h-screen bg-white shadow-2xl flex flex-col border-x border-black/5 overflow-hidden">

<?php
$title = 'AI Assistant';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<?php if (!$hasAnyKey): ?>
<div class="px-4 py-3 bg-yellow-50 border-b border-yellow-200">
  <p class="text-xs font-bold uppercase tracking-widest text-yellow-800">API keys are required in Settings.</p>
</div>
<?php endif; ?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-[250px]">
  <section class="mt-4 px-4">
    <div class="flex gap-3 overflow-x-auto no-scrollbar pb-2">
      <button onclick="openTaskGenerator()" class="tool-card bg-black text-white <?= !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$hasAnyKey ? 'disabled' : '' ?>>
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0"/></svg>
        <span class="text-[10px] font-black uppercase tracking-tight leading-tight">Task<br>Generator</span>
      </button>
      <button onclick="openPRDGenerator()" class="tool-card bg-white text-black <?= !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$hasAnyKey ? 'disabled' : '' ?>>
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span class="text-[10px] font-black uppercase tracking-tight leading-tight">PRD<br>Generator</span>
      </button>
      <button onclick="runAIVerification()" class="tool-card bg-white text-black <?= !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$hasAnyKey ? 'disabled' : '' ?>>
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span class="text-[10px] font-black uppercase tracking-tight leading-tight">AI<br>Verification</span>
      </button>
    </div>
  </section>

  <section class="mt-4 px-4 space-y-3">
    <div class="flex border border-black overflow-hidden">
      <div class="flex-1 flex items-center justify-between px-3 py-2 border-r border-black">
        <span class="text-[10px] font-bold uppercase tracking-widest">Agent Mode</span>
        <input id="agent-mode" type="checkbox" class="w-4 h-4 accent-black" checked>
      </div>
      <div class="flex-1 flex items-center justify-between px-3 py-2">
        <span class="text-[10px] font-bold uppercase tracking-widest">Auto-Confirm</span>
        <input id="auto-confirm" type="checkbox" class="w-4 h-4 accent-black" checked disabled>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <select id="ai-provider" class="w-full bg-white border border-black px-3 py-2 text-xs font-bold uppercase tracking-widest focus:ring-0 focus:outline-none" <?= !$hasAnyKey ? 'disabled' : '' ?>>
        <?php if ($hasGroqKey): ?><option value="groq">Groq</option><?php endif; ?>
        <?php if ($hasOpenRouterKey): ?><option value="openrouter">OpenRouter</option><?php endif; ?>
      </select>
      <select id="ai-model" class="w-full bg-white border border-black px-3 py-2 text-xs font-bold uppercase tracking-widest focus:ring-0 focus:outline-none" <?= !$hasAnyKey ? 'disabled' : '' ?>></select>
    </div>
    <select id="ai-kb-folder" class="w-full bg-white border border-black px-3 py-2 text-xs font-bold focus:ring-0 focus:outline-none" <?= !$hasAnyKey ? 'disabled' : '' ?>>
      <option value="">No Knowledge Base</option>
    </select>
  </section>

  <section class="mt-5 px-4">
    <button onclick="toggleConversationHistory()" class="w-full border border-black py-2 text-[10px] font-black uppercase tracking-widest hover:bg-gray-50">Conversation History</button>
  </section>

  <section class="mt-5 px-4">
    <div id="chat-messages" class="min-h-[260px] border-t border-black/10 pt-4">
      <div id="chat-empty-state" class="text-center space-y-3 opacity-40 py-16">
        <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h5m8 5l-3.4-2.2A8 8 0 104 12a8 8 0 0012.6 6.4L20 20z"/></svg>
        <p class="text-xs font-medium max-w-[220px] mx-auto">Start a conversation with the AI assistant.</p>
      </div>
    </div>
  </section>
</main>

<div class="absolute left-0 right-0 bottom-[74px] bg-white z-30">
  <div class="flex items-center justify-around border-y border-black py-1">
    <button onclick="copyChatTranscript()" class="p-2 hover:bg-gray-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2M8 16h8a2 2 0 002-2v-4a2 2 0 00-2-2H8a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg></button>
    <button onclick="exportChatMarkdown()" class="p-2 hover:bg-gray-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12l-4-4m4 4l4-4M4 20h16"/></svg></button>
    <button onclick="saveChatToKnowledgeBase()" class="p-2 hover:bg-gray-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg></button>
    <button onclick="saveChatToNotes()" class="p-2 hover:bg-gray-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button>
    <div class="h-5 w-[1px] bg-black/20"></div>
    <button onclick="startNewConversation()" class="p-2 hover:bg-gray-100"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></button>
  </div>
  <form id="chat-form" class="p-3 flex gap-2 items-end">
    <textarea id="chat-input" rows="1" placeholder="Type your prompt..." class="flex-1 min-h-[44px] max-h-28 border border-black p-3 text-sm focus:ring-0 focus:outline-none resize-none placeholder:text-gray-400" <?= !$hasAnyKey ? 'disabled' : '' ?>></textarea>
    <button id="send-message-btn" type="submit" class="w-11 h-11 bg-black text-white flex items-center justify-center <?= !$hasAnyKey ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$hasAnyKey ? 'disabled' : '' ?>>
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h13m0 0l-5-5m5 5l-5 5"/></svg>
    </button>
  </form>
</div>

<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<div id="conversation-drawer" class="fixed inset-0 bg-black/50 hidden z-50">
  <div class="absolute inset-y-0 right-0 w-full max-w-[420px] bg-white border-l border-black transform translate-x-full transition-transform duration-300" id="conversation-sheet">
    <div class="p-4 border-b border-black flex items-center justify-between">
      <h3 class="text-sm font-black uppercase tracking-widest">Conversations</h3>
      <button onclick="toggleConversationHistory()" class="p-2 border border-black"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <div class="p-3 border-b border-gray-100 flex gap-2">
      <button onclick="startNewConversation(); toggleConversationHistory();" class="flex-1 py-2 border border-black text-[10px] font-black uppercase tracking-widest">New Chat</button>
      <button onclick="clearConversationHistory()" class="flex-1 py-2 border border-black text-[10px] font-black uppercase tracking-widest">Clear All</button>
    </div>
    <div id="conversation-list" class="h-[calc(100%-112px)] overflow-y-auto no-scrollbar">
      <div class="p-4 text-sm text-gray-500">Loading...</div>
    </div>
  </div>
</div>

<div id="ai-modal" class="fixed inset-0 bg-black/50 hidden z-50 p-4 overflow-y-auto no-scrollbar">
  <div class="w-full max-w-[420px] mx-auto bg-white border border-black p-4 mt-8 mb-10">
    <div class="flex items-center justify-between mb-3">
      <h3 id="ai-modal-title" class="text-sm font-black uppercase tracking-widest">Modal</h3>
      <button onclick="closeModal()" class="p-1 border border-black"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
    </div>
    <div id="ai-modal-content"></div>
  </div>
</div>

<script>
  const APP_URL = '<?= htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') ?>';
  const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
  const HAS_AI_KEY = <?= $hasAnyKey ? 'true' : 'false' ?>;
  const ALL_MODELS = <?= json_encode(['groq' => $groqModels, 'openrouter' => $openRouterModels]) ?>;
  const PROJECTS = <?= json_encode(array_values($projects)) ?>;
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') { Mobile.init(); }

const KB_FOLDER_STORAGE_KEY = 'mobile.ai.kbFolder';
let chatHistory = [];
let currentConversationId = null;
let agentModeEnabled = true;
let selectedKbFolderId = '';
let kbFolders = [];
let isSending = false;
let generatedTasksBuffer = [];
let generatedPRDBuffer = '';
let generatedPRDIdea = '';

function showToast(message, type) {
  if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') Mobile.ui.showToast(message, type || 'info');
  else alert(message);
}
function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str == null ? '' : String(str); return d.innerHTML; }
function timeAgo(dateStr) { if (!dateStr) return '--'; const d = new Date(dateStr); const diff = Math.floor((Date.now() - d.getTime()) / 1000); if (diff < 60) return diff + 's ago'; if (diff < 3600) return Math.floor(diff / 60) + 'm ago'; if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'; return Math.floor(diff / 86400) + 'd ago'; }
function getProvider() { const el = document.getElementById('ai-provider'); return el ? el.value : 'groq'; }
function getModel() { const el = document.getElementById('ai-model'); return el ? el.value : ''; }

function updateModelList() {
  const provider = getProvider();
  const select = document.getElementById('ai-model');
  const models = Array.isArray(ALL_MODELS[provider]) ? ALL_MODELS[provider] : [];
  select.innerHTML = models.map((m, i) => `<option value="${escapeHtml(m.modelId || '')}" ${(m.isDefault || i === 0) ? 'selected' : ''}>${escapeHtml(m.displayName || m.modelId || 'Model')}</option>`).join('');
}
function openModal(title, html) { document.getElementById('ai-modal-title').textContent = title; document.getElementById('ai-modal-content').innerHTML = html; document.getElementById('ai-modal').classList.remove('hidden'); }
function closeModal() { document.getElementById('ai-modal').classList.add('hidden'); }

function addChatMessage(role, content, functionCalls, toolResults) {
  const container = document.getElementById('chat-messages');
  const empty = document.getElementById('chat-empty-state');
  if (empty) empty.remove();
  const wrapper = document.createElement('div');
  wrapper.className = 'chat-message ' + (role === 'user' ? 'user' : 'assistant');
  let bubble = '<div class="chat-bubble">';
  if (role !== 'user' && Array.isArray(functionCalls) && functionCalls.length) bubble += '<div class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-500">Running tools...</div>';
  bubble += content && String(content).trim() !== '' ? escapeHtml(content) : (role !== 'user' ? '<span class="text-xs text-gray-500">No textual response.</span>' : '');
  if (role !== 'user' && Array.isArray(toolResults) && toolResults.length) {
    bubble += '<div class="mt-2 pt-2 border-t border-gray-200 space-y-1">';
    toolResults.forEach((r) => { bubble += '<div class="text-[10px] uppercase tracking-wider">' + escapeHtml(r.summary || r.name || 'Tool result') + '</div>'; });
    bubble += '</div>';
  }
  bubble += '</div>';
  wrapper.innerHTML = bubble;
  container.appendChild(wrapper);
  container.scrollTop = container.scrollHeight;
}
function showTyping() { const c = document.getElementById('chat-messages'); const e = document.getElementById('chat-empty-state'); if (e) e.remove(); const div = document.createElement('div'); div.id='typing-indicator'; div.className='chat-message assistant'; div.innerHTML='<div class="chat-bubble"><span class="text-xs text-gray-500 uppercase tracking-widest">Thinking...</span></div>'; c.appendChild(div); c.scrollTop = c.scrollHeight; }
function hideTyping() { const t = document.getElementById('typing-indicator'); if (t) t.remove(); }
function renderWelcomeIfEmpty() { const c = document.getElementById('chat-messages'); if (!c.children.length) c.innerHTML = `<div id="chat-empty-state" class="text-center space-y-3 opacity-40 py-16"><svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8m-8 4h5m8 5l-3.4-2.2A8 8 0 104 12a8 8 0 0012.6 6.4L20 20z"/></svg><p class="text-xs font-medium max-w-[220px] mx-auto">Start a conversation with the AI assistant.</p></div>`; }

async function sendStandardMessage(message) {
  chatHistory.push({ role: 'user', content: message });
  showTyping();
  try {
    const response = await App.api.post('api/ai.php?action=chat', { messages: chatHistory, provider: getProvider(), model: getModel(), kbFolderId: selectedKbFolderId || '', csrf_token: CSRF_TOKEN });
    hideTyping();
    if (response.success && response.data && response.data.response) {
      chatHistory.push({ role: 'assistant', content: response.data.response });
      addChatMessage('assistant', response.data.response);
    } else addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
  } catch (error) { hideTyping(); addChatMessage('assistant', 'Error: ' + (error.message || 'Network error')); }
}

function normalizeAgentMessages(messages) {
  const rows = [];
  (messages || []).forEach((m) => {
    if (m.role !== 'user' && m.role !== 'assistant') return;
    rows.push({ role: m.role, content: m.content || '', functionCalls: m.functionCalls || [], toolResults: m.toolResults || [] });
  });
  return rows;
}
async function refreshConversation() {
  if (!currentConversationId) return;
  const response = await App.api.get('api/ai-agent.php?action=get_conversation&id=' + encodeURIComponent(currentConversationId));
  if (!response.success || !response.data || !response.data.conversation) return;
  const messages = normalizeAgentMessages(response.data.conversation.messages || []);
  const container = document.getElementById('chat-messages');
  container.innerHTML = '';
  messages.forEach((m) => addChatMessage(m.role, m.content, m.functionCalls, m.toolResults));
  chatHistory = messages.map((m) => ({ role: m.role, content: m.content }));
  renderWelcomeIfEmpty();
}

async function sendAgentMessage(message) {
  showTyping();
  try {
    const response = await App.api.post('api/ai-agent.php?action=chat', { message, conversationId: currentConversationId, autoConfirm: true, provider: getProvider(), model: getModel(), kbFolderId: selectedKbFolderId || null, csrf_token: CSRF_TOKEN });
    hideTyping();
    if (response.success && response.data) {
      currentConversationId = response.data.conversationId || currentConversationId;
      await refreshConversation();
      await loadConversations();
    } else addChatMessage('assistant', 'Error: ' + (response.error || 'Failed to get response'));
  } catch (error) { hideTyping(); addChatMessage('assistant', 'Error: ' + (error.message || 'Network error')); }
}

async function handleSendMessage(event) {
  event.preventDefault();
  if (!HAS_AI_KEY || isSending) return;
  const input = document.getElementById('chat-input');
  const message = (input.value || '').trim();
  if (!message) return;
  isSending = true;
  input.value = '';
  addChatMessage('user', message);
  if (agentModeEnabled) await sendAgentMessage(message);
  else await sendStandardMessage(message);
  isSending = false;
}

function startNewConversation() { currentConversationId = null; chatHistory = []; document.getElementById('chat-messages').innerHTML = ''; renderWelcomeIfEmpty(); loadConversations(); showToast('New conversation started.', 'info'); }

function toggleConversationHistory() {
  const backdrop = document.getElementById('conversation-drawer');
  const sheet = document.getElementById('conversation-sheet');
  if (backdrop.classList.contains('hidden')) { backdrop.classList.remove('hidden'); setTimeout(() => sheet.classList.remove('translate-x-full'), 10); loadConversations(); }
  else { sheet.classList.add('translate-x-full'); setTimeout(() => backdrop.classList.add('hidden'), 200); }
}
async function loadConversations() {
  const list = document.getElementById('conversation-list');
  list.innerHTML = '<div class="p-4 text-sm text-gray-500">Loading...</div>';
  try {
    const response = await App.api.get('api/ai-agent.php?action=list_conversations');
    const rows = response.success && response.data && Array.isArray(response.data.conversations) ? response.data.conversations : [];
    if (!rows.length) { list.innerHTML = '<div class="p-4 text-sm text-gray-500">No conversations yet.</div>'; return; }
    list.innerHTML = rows.map((r) => `<button class="w-full text-left p-3 border-b border-gray-100 hover:bg-gray-50 ${r.id === currentConversationId ? 'bg-gray-50' : ''}" onclick="openConversation('${r.id}')"><p class="text-xs font-black uppercase tracking-tight truncate">${escapeHtml(r.title || 'Conversation')}</p><p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">${Number(r.messageCount || 0)} msgs · ${timeAgo(r.updatedAt)}</p></button>`).join('');
  } catch (error) { list.innerHTML = '<div class="p-4 text-sm text-red-600">Failed to load conversations.</div>'; }
}
async function openConversation(id) { currentConversationId = id; await refreshConversation(); await loadConversations(); toggleConversationHistory(); }
async function clearConversationHistory() {
  if (!window.confirm('Clear all AI conversations?')) return;
  try {
    const response = await App.api.post('api/ai-agent.php?action=clear_all_conversations', { csrf_token: CSRF_TOKEN });
    if (response.success) { startNewConversation(); showToast('All conversations cleared.', 'success'); }
    else showToast(response.error || 'Failed to clear conversations', 'error');
  } catch (error) { showToast('Failed to clear conversations', 'error'); }
}

function setStoredKbFolder(id) { try { localStorage.setItem(KB_FOLDER_STORAGE_KEY, id || ''); } catch (e) {} }
function getStoredKbFolder() { try { return localStorage.getItem(KB_FOLDER_STORAGE_KEY) || ''; } catch (e) { return ''; } }
function renderKbSelect() {
  const select = document.getElementById('ai-kb-folder');
  if (!select) return;
  const options = [{ id: '', name: 'No Knowledge Base' }].concat(kbFolders.map((f) => ({ id: f.id, name: f.name })));
  select.innerHTML = options
    .map((option) => `<option value="${escapeHtml(option.id)}">${escapeHtml(option.name)}</option>`)
    .join('');
  select.value = selectedKbFolderId;
}
function handleKbFolderChange(event) {
  selectedKbFolderId = event.target?.value || '';
  setStoredKbFolder(selectedKbFolderId);
}
async function loadKbFolders() {
  try {
    const response = await App.api.get('api/knowledge-base.php?action=list_folders');
    kbFolders = response.success && response.data && Array.isArray(response.data.folders) ? response.data.folders : [];
  } catch (error) { kbFolders = []; }
  const stored = getStoredKbFolder();
  selectedKbFolderId = kbFolders.some((f) => f.id === stored) ? stored : '';
  renderKbSelect();
}

function getChatMarkdown() {
  if (!chatHistory.length) return '';
  const lines = ['# AI Chat Export', '', 'Generated: ' + new Date().toISOString(), ''];
  chatHistory.forEach((entry) => { lines.push('## ' + (entry.role === 'assistant' ? 'Assistant' : 'User')); lines.push(entry.content || ''); lines.push(''); });
  return lines.join('\n').trim() + '\n';
}
async function copyChatTranscript() { const t = getChatMarkdown(); if (!t) return showToast('No chat to copy yet.', 'info'); try { await navigator.clipboard.writeText(t); showToast('Copied to clipboard.', 'success'); } catch (e) { showToast('Copy failed.', 'error'); } }
function exportChatMarkdown() {
  const t = getChatMarkdown();
  if (!t) return showToast('No chat to export yet.', 'info');
  const d = new Date().toISOString().slice(0, 10);
  const blob = new Blob([t], { type: 'text/markdown' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'ai-chat-' + d + '.md';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
  showToast('Markdown exported.', 'success');
}
async function saveChatToNotes() {
  const t = getChatMarkdown();
  if (!t) return showToast('No chat to save yet.', 'info');
  try {
    const r = await App.api.post('api/notes.php', { title: 'AI Chat - ' + new Date().toISOString().slice(0, 10), content: t, csrf_token: CSRF_TOKEN });
    if (r.success) showToast('Saved to notes.', 'success');
    else showToast(r.error || 'Failed to save note', 'error');
  } catch (e) { showToast('Failed to save note.', 'error'); }
}
async function saveChatToKnowledgeBase() {
  const t = getChatMarkdown();
  if (!t) return showToast('No chat to save yet.', 'info');
  if (!selectedKbFolderId) return showToast('Select a KB folder first.', 'warning');
  let filename = window.prompt('Filename (.md):', 'ai-chat-' + new Date().toISOString().slice(0, 10) + '.md');
  if (filename === null) return;
  filename = String(filename || '').trim();
  if (!filename) filename = 'ai-chat-' + new Date().toISOString().slice(0, 10) + '.md';
  if (!filename.toLowerCase().endsWith('.md')) filename += '.md';
  try {
    const r = await App.api.post('api/notes.php?action=convert_to_markdown', { folderId: selectedKbFolderId, filename, content: t, format: 'markdown', csrf_token: CSRF_TOKEN });
    if (r.success) showToast('Saved to knowledge base.', 'success');
    else showToast(r.error || 'Failed to save to KB', 'error');
  } catch (e) { showToast('Failed to save to KB.', 'error'); }
}

function getProjectOptions() {
  const rows = ['<option value="">Select project (optional)</option>'];
  PROJECTS.forEach((p) => rows.push('<option value="' + escapeHtml(p.id) + '">' + escapeHtml(p.name || 'Project') + '</option>'));
  return rows.join('');
}
function openTaskGenerator() {
  openModal('Task Generator', `<form id="mobile-task-gen-form" class="space-y-3"><div><label class="block text-[10px] font-black uppercase tracking-widest mb-1">Project Description</label><textarea name="description" rows="4" class="w-full border border-black p-3 text-sm focus:ring-0 focus:outline-none" placeholder="Describe what you want to build..." required></textarea></div><div><label class="block text-[10px] font-black uppercase tracking-widest mb-1">Save To Project</label><select name="projectId" class="w-full border border-black p-2 text-sm focus:ring-0 focus:outline-none">${getProjectOptions()}</select></div><button type="submit" class="w-full py-3 bg-black text-white text-[10px] font-black uppercase tracking-widest">Generate Tasks</button><div id="mobile-task-gen-result" class="hidden border border-black p-3 text-sm"></div></form>`);
  const form = document.getElementById('mobile-task-gen-form');
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const resultBox = document.getElementById('mobile-task-gen-result');
    const fd = new FormData(form);
    const description = String(fd.get('description') || '').trim();
    const projectId = String(fd.get('projectId') || '');
    resultBox.classList.remove('hidden');
    resultBox.textContent = 'Generating tasks...';
    generatedTasksBuffer = [];
    try {
      const r = await App.api.post('api/ai.php?action=generate_tasks', { description, provider: getProvider(), model: getModel(), csrf_token: CSRF_TOKEN });
      if (!r.success || !r.data || !Array.isArray(r.data.tasks)) { resultBox.textContent = r.error || 'Failed to generate tasks.'; return; }
      generatedTasksBuffer = r.data.tasks;
      resultBox.innerHTML = '<p class="font-bold mb-2">Generated Tasks</p><ul class="space-y-1 text-xs">' + r.data.tasks.map((t, i) => '<li><strong>' + (i + 1) + '.</strong> ' + escapeHtml(t.title || 'Task') + '</li>').join('') + '</ul>' + (projectId ? '<button type="button" onclick="saveGeneratedTasksToProject(\'' + escapeHtml(projectId) + '\')" class="mt-3 w-full py-2 bg-black text-white text-[10px] font-black uppercase tracking-widest">Save To Project</button>' : '');
      showToast('Tasks generated.', 'success');
    } catch (e) { resultBox.textContent = 'Generation failed.'; }
  });
}
async function saveGeneratedTasksToProject(projectId) {
  if (!projectId || !generatedTasksBuffer.length) return showToast('No tasks to save.', 'warning');
  try {
    const r = await App.api.post('api/ai.php?action=import_tasks', { projectId, tasks: generatedTasksBuffer, csrf_token: CSRF_TOKEN });
    if (r.success) { showToast('Tasks saved to project.', 'success'); closeModal(); }
    else showToast(r.error || 'Failed to save tasks', 'error');
  } catch (e) { showToast('Failed to save tasks.', 'error'); }
}
function openPRDGenerator() {
  openModal('PRD Generator', `<form id="mobile-prd-form" class="space-y-3"><div><label class="block text-[10px] font-black uppercase tracking-widest mb-1">Product Idea</label><textarea name="idea" rows="4" class="w-full border border-black p-3 text-sm focus:ring-0 focus:outline-none" placeholder="Describe the product idea..." required></textarea></div><div><label class="block text-[10px] font-black uppercase tracking-widest mb-1">Save To Project</label><select name="projectId" class="w-full border border-black p-2 text-sm focus:ring-0 focus:outline-none">${getProjectOptions()}</select></div><button type="submit" class="w-full py-3 bg-black text-white text-[10px] font-black uppercase tracking-widest">Generate PRD</button><div id="mobile-prd-result" class="hidden border border-black p-3 text-sm max-h-52 overflow-y-auto no-scrollbar whitespace-pre-wrap"></div><button id="mobile-prd-save-btn" type="button" class="hidden w-full py-2 bg-black text-white text-[10px] font-black uppercase tracking-widest">Save PRD To Project</button></form>`);
  const form = document.getElementById('mobile-prd-form');
  const saveBtn = document.getElementById('mobile-prd-save-btn');
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(form);
    const idea = String(fd.get('idea') || '').trim();
    const projectId = String(fd.get('projectId') || '');
    const box = document.getElementById('mobile-prd-result');
    box.classList.remove('hidden');
    box.textContent = 'Generating PRD...';
    generatedPRDBuffer = '';
    generatedPRDIdea = idea;
    try {
      const r = await App.api.post('api/ai.php?action=generate_prd', { idea, provider: getProvider(), model: getModel(), csrf_token: CSRF_TOKEN });
      if (!r.success || !r.data || !r.data.prd) { box.textContent = r.error || 'Failed to generate PRD.'; saveBtn.classList.add('hidden'); return; }
      generatedPRDBuffer = r.data.prd;
      generatedPRDIdea = r.data.idea || idea;
      box.textContent = generatedPRDBuffer;
      if (projectId) { saveBtn.classList.remove('hidden'); saveBtn.onclick = () => saveGeneratedPRDToProject(projectId); } else saveBtn.classList.add('hidden');
      showToast('PRD generated.', 'success');
    } catch (e) { box.textContent = 'Failed to generate PRD.'; saveBtn.classList.add('hidden'); }
  });
}
async function saveGeneratedPRDToProject(projectId) {
  if (!projectId || !generatedPRDBuffer) return showToast('No PRD to save.', 'warning');
  try {
    const r = await App.api.post('api/ai.php?action=save_prd', { projectId, prd: generatedPRDBuffer, idea: generatedPRDIdea, csrf_token: CSRF_TOKEN });
    if (r.success) { showToast('PRD saved to project.', 'success'); closeModal(); }
    else showToast(r.error || 'Failed to save PRD', 'error');
  } catch (e) { showToast('Failed to save PRD.', 'error'); }
}
async function runAIVerification() {
  const provider = getProvider();
  const model = getModel();
  const action = provider === 'openrouter' ? 'test_openrouter' : 'test_groq';
  openModal('AI Verification', '<div class="text-sm text-gray-600">Running verification...</div>');
  try {
    const r = await App.api.post('api/ai-test.php?action=' + action, { model, csrf_token: CSRF_TOKEN });
    if (!r.success || !r.data) { document.getElementById('ai-modal-content').innerHTML = '<div class="text-sm text-red-600">Verification failed.</div>'; return; }
    const d = r.data;
    const ok = !!d.success || !!d.connected;
    document.getElementById('ai-modal-content').innerHTML = `<div class="border border-black p-3"><p class="text-[10px] font-black uppercase tracking-widest mb-2">${escapeHtml(provider)} Connection</p><p class="text-sm font-bold ${ok ? 'text-green-700' : 'text-red-600'}">${ok ? 'Connected' : 'Failed'}</p>${d.response ? '<pre class="mt-2 text-xs whitespace-pre-wrap">' + escapeHtml(d.response) + '</pre>' : ''}${d.error ? '<p class="mt-2 text-xs text-red-600">' + escapeHtml(d.error) + '</p>' : ''}</div>`;
  } catch (e) { document.getElementById('ai-modal-content').innerHTML = '<div class="text-sm text-red-600">Verification failed.</div>'; }
}

document.getElementById('chat-form').addEventListener('submit', handleSendMessage);
const autoConfirmToggle = document.getElementById('auto-confirm');
if (autoConfirmToggle) {
  autoConfirmToggle.checked = true;
}
document.getElementById('agent-mode').addEventListener('change', (e) => {
  agentModeEnabled = !!e.target.checked;
  if (autoConfirmToggle) {
    autoConfirmToggle.checked = true;
  }
});
document.getElementById('ai-provider').addEventListener('change', updateModelList);
document.getElementById('ai-kb-folder').addEventListener('change', handleKbFolderChange);
document.getElementById('conversation-drawer').addEventListener('click', function(event) { if (event.target === this) toggleConversationHistory(); });
document.getElementById('ai-modal').addEventListener('click', function(event) { if (event.target === this) closeModal(); });
updateModelList();
loadKbFolders();
renderWelcomeIfEmpty();
</script>
</body>
</html>

