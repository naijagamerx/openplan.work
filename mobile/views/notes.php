<?php
/**
 * Mobile Notes Page - LazyMan Tools
 *
 * Uses shared mobile header component for consistency.
 * Matches tasks/settings page structure.
 */

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once __DIR__ . '/../../includes/NotesAPI.php';

// Load AI configuration (same as PC)
try {
    $db = new Database(getMasterPassword(), Auth::userId());
    $config = $db->load('config');
    $modelsLoad = $db->safeLoad('models');
    $models = $modelsLoad['success'] ? $modelsLoad['data'] : [];
} catch (Exception $e) {
    $config = [];
    $models = [];
}

$hasGroqKey = !empty($config['groqApiKey']);
$hasOpenRouterKey = !empty($config['openrouterApiKey']);
$hasAnyKey = $hasGroqKey || $hasOpenRouterKey;

// Filter enabled models
$groqModels = array_filter($models['groq'] ?? [], fn($m) => $m['enabled']);
$openRouterModels = array_filter($models['openrouter'] ?? [], fn($m) => $m['enabled']);

$notesAPI = new NotesAPI($db, 'notes');

// Get all notes
$notes = $notesAPI->findAll() ?? [];

// Hide archived notes from the default mobile notes list.
$notes = array_values(array_filter($notes, function($note) {
    $tags = array_map('strtolower', $note['tags'] ?? []);
    return !in_array('archived', $tags, true);
}));

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$tagFilter = $_GET['tag'] ?? '';

// Filter notes
$filteredNotes = array_filter($notes, function($note) use ($filter, $tagFilter) {
    if ($filter === 'pinned' && empty($note['isPinned'])) return false;
    if ($filter === 'favorites' && empty($note['isFavorite'])) return false;
    if (!empty($tagFilter) && !in_array($tagFilter, $note['tags'] ?? [])) return false;
    return true;
});

// Sort by updated date
usort($filteredNotes, function($a, $b) {
    return strtotime($b['updatedAt'] ?? 0) - strtotime($a['updatedAt'] ?? 0);
});

// Get all unique tags
$allTags = $notesAPI->getAllTags();

// Count stats
$pinnedCount = count(array_filter($notes, fn($n) => !empty($n['isPinned'])));
$favoritesCount = count(array_filter($notes, fn($n) => !empty($n['isFavorite'])));

// Get site name
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Notes - <?= htmlspecialchars($siteName) ?></title>

<!-- Favicons -->
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
                    "background-dark": "#0a0a0a",
                },
                fontFamily: {
                    "display": ["Inter", "sans-serif"]
                },
            },
        },
    }
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-white text-black font-display antialiased;
        }
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .note-row {
        @apply border-b border-gray-100 py-4 px-6 cursor-pointer transition-colors;
    }
    .note-row:active {
        @apply bg-gray-50;
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
$title = 'Notes';
$leftAction = 'menu';
$rightAction = 'add';
$rightTarget = 'showCreateNoteOptions()';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<!-- Header Actions Bar (More Menu Button) -->
<div class="absolute top-3 right-16 flex items-center gap-2 z-30">
    <button onclick="toggleMoreMenu()" class="p-2 text-gray-600 hover:text-black transition touch-target">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
        </svg>
    </button>
</div>

<!-- More Actions Menu -->
<div id="moreMenu" class="fixed inset-0 bg-black/50 z-50 hidden" onclick="toggleMoreMenu()">
    <div class="absolute right-4 top-16 bg-white rounded-xl shadow-xl p-2 w-48" onclick="event.stopPropagation()">
        <button onclick="showImportModal(); toggleMoreMenu();" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 rounded-lg">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <span>Import Notes</span>
        </button>
        <button onclick="exportNotes(); toggleMoreMenu();" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 rounded-lg">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            <span>Export All</span>
        </button>
        <button onclick="toggleBulkSelect(); toggleMoreMenu();" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 rounded-lg">
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span>Bulk Select</span>
        </button>
        <?php if ($hasAnyKey): ?>
        <button onclick="showAIGenerateModal(); toggleMoreMenu();" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3 rounded-lg">
            <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
            <span>AI Generate</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Search and Filters -->
<div class="px-4 py-3 bg-white border-b border-gray-100">
    <div class="relative mb-3">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
            id="search-input"
            class="w-full bg-gray-50 border-none rounded-xl py-2.5 pl-10 pr-4 text-xs font-medium focus:ring-1 focus:ring-black placeholder:text-gray-400 transition-all"
            placeholder="Search notes..."
            type="text"
            onkeyup="searchNotes(this.value)"
        />
    </div>

    <div class="flex gap-4 overflow-x-auto no-scrollbar">
        <button onclick="setFilter('all')" class="filter-btn whitespace-nowrap text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 hover:underline underline-offset-4 touch-target" data-filter="all">
            All (<?= count($notes) ?>)
        </button>
        <button onclick="setFilter('pinned')" class="filter-btn whitespace-nowrap text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 hover:underline underline-offset-4 touch-target" data-filter="pinned">
            Pinned (<?= $pinnedCount ?>)
        </button>
        <button onclick="setFilter('favorites')" class="filter-btn whitespace-nowrap text-[10px] font-bold uppercase tracking-widest flex items-center gap-1 hover:underline underline-offset-4 touch-target" data-filter="favorites">
            Favorites (<?= $favoritesCount ?>)
        </button>
    </div>

    <?php if (!empty($allTags)): ?>
    <div class="flex gap-2 mt-3 overflow-x-auto no-scrollbar">
        <?php foreach ($allTags as $tag): ?>
            <button onclick="setTag('<?= htmlspecialchars($tag) ?>')" class="tag-btn whitespace-nowrap text-[10px] text-gray-400 hover:text-black transition-colors px-2 py-1 rounded-full bg-gray-50" data-tag="<?= htmlspecialchars($tag) ?>">
                #<?= htmlspecialchars($tag) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Notes List -->
<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <?php if (empty($filteredNotes)): ?>
        <!-- Empty State -->
        <div class="text-center py-16 px-6">
            <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-gray-500 text-sm">No notes found</p>
            <a href="?page=note-form" class="inline-block mt-4 text-xs font-bold uppercase tracking-widest bg-black text-white px-4 py-2 rounded-sm hover:opacity-90">
                Create Note
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($filteredNotes as $note): ?>
            <a href="?page=view-notes&id=<?= urlencode($note['id']) ?>" class="block border-b border-gray-100 py-4 px-6 hover:bg-gray-50 transition-colors">
                <div class="flex justify-between items-start mb-1">
                    <h3 class="text-sm font-bold uppercase tracking-tight pr-8 flex-1">
                        <?= htmlspecialchars($note['title'] ?? 'Untitled') ?>
                        <?php if (!empty($note['isPinned'])): ?>
                            <svg class="w-3 h-3 inline ml-1 text-black" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                            </svg>
                        <?php endif; ?>
                    </h3>
                    <span class="text-[10px] text-gray-400 font-medium flex-shrink-0">
                        <?= date('M d', strtotime($note['updatedAt'] ?? 'now')) ?>
                    </span>
                </div>
                <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-2">
                    <?= htmlspecialchars(substr(strip_tags($note['content'] ?? ''), 0, 120)) ?>
                    <?php if (strlen($note['content'] ?? '') > 120) echo '...'; ?>
                </p>
                <div class="flex gap-2">
                    <?php foreach (($note['tags'] ?? []) as $tag): ?>
                        <span class="text-[9px] uppercase tracking-widest text-gray-400">#<?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php include MOBILE_VIEW_PATH . '/partials/bottom-nav.php'; ?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>

<!-- Import Modal -->
<div id="importModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="closeImportModal()">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6" onclick="event.stopPropagation()">
        <h2 class="text-xl font-bold mb-4">Import Notes</h2>
        <p class="text-sm text-gray-500 mb-4">Upload JSON, CSV, TXT, or MD files. HTML tags will be stripped.</p>
        <input type="file" id="importFile" accept=".json,.csv,.txt,.md" multiple class="w-full mb-4 p-2 border border-gray-200 rounded-lg">
        <div id="importPreview" class="hidden mb-4 max-h-40 overflow-y-auto bg-gray-50 rounded-lg p-3 text-sm"></div>
        <div class="flex gap-2">
            <button onclick="closeImportModal()" class="flex-1 py-2 border border-gray-200 rounded-lg text-sm font-medium">Cancel</button>
            <button onclick="importNotes()" class="flex-1 py-2 bg-black text-white rounded-lg text-sm font-medium">Import</button>
        </div>
    </div>
</div>

<!-- AI Generate Modal -->
<div id="aiGenerateModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="closeAIGenerateModal()">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6" onclick="event.stopPropagation()">
        <h2 class="text-xl font-bold mb-4">AI Generate Note</h2>
        <textarea id="aiPrompt" placeholder="Describe what you want to write about..." class="w-full h-32 p-3 border border-gray-200 rounded-lg mb-4 resize-none text-sm"></textarea>
        <select id="aiModel" class="w-full p-2 border border-gray-200 rounded-lg mb-4 text-sm">
            <?php foreach ($groqModels as $model): ?>
                <option value="<?= htmlspecialchars($model['modelId']) ?>" data-provider="groq"><?= htmlspecialchars($model['displayName']) ?> (Groq)</option>
            <?php endforeach; ?>
            <?php foreach ($openRouterModels as $model): ?>
                <option value="<?= htmlspecialchars($model['modelId']) ?>" data-provider="openrouter"><?= htmlspecialchars($model['displayName']) ?> (OpenRouter)</option>
            <?php endforeach; ?>
        </select>
        <div class="flex gap-2">
            <button onclick="closeAIGenerateModal()" class="flex-1 py-2 border border-gray-200 rounded-lg text-sm font-medium">Cancel</button>
            <button onclick="generateNoteWithAI()" class="flex-1 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">Generate</button>
        </div>
    </div>
</div>

<!-- Create Note Options Modal -->
<div id="createNoteModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="closeCreateNoteOptions()">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6" onclick="event.stopPropagation()">
        <h2 class="text-xl font-bold mb-2">Create Note</h2>
        <p class="text-sm text-gray-500 mb-5">Choose how to create your note.</p>
        <div class="space-y-3">
            <button onclick="createManualNote()" class="w-full text-left px-4 py-3 border border-gray-200 rounded-xl hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                <span class="font-medium">New Note</span>
            </button>
            <?php if ($hasAnyKey): ?>
            <button onclick="createWithAI()" class="w-full text-left px-4 py-3 border border-purple-200 rounded-xl hover:bg-purple-50 flex items-center gap-3">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span class="font-medium text-purple-700">Generate with AI</span>
            </button>
            <?php endif; ?>
        </div>
        <button onclick="closeCreateNoteOptions()" class="w-full mt-5 py-3 border border-gray-200 rounded-xl text-sm font-medium">Cancel</button>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6 transform scale-95 opacity-0 transition-all duration-200" onclick="event.stopPropagation()">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h6m-7 8h8M6 12h9a4 4 0 118 0 4 4 0 01-4 4H6z"></path>
                </svg>
            </div>
            <h2 id="confirmTitle" class="text-xl font-bold text-red-600">Confirm Delete</h2>
        </div>
        <p id="confirmMessage" class="text-sm text-gray-600 mb-6">Are you sure you want to continue?</p>
        <div class="flex gap-3">
            <button id="confirmCancelBtn" onclick="closeConfirmModal()" class="flex-1 py-3 border border-gray-200 rounded-lg text-sm font-medium">Cancel</button>
            <button id="confirmOkBtn" onclick="" class="flex-1 py-3 bg-red-600 text-white rounded-lg text-sm font-medium">Delete</button>
        </div>
    </div>
</div>

<!-- Toast Notifications - Fixed centered position -->
<div id="toastContainer" class="fixed top-20 left-1/2 right-1/2 -translate-x-1/2 z-50 flex flex-col items-center gap-2 pointer-events-none px-4">
</div>

<!-- Swipe Action Panel -->
<div id="swipeAction" class="fixed right-3 bottom-24 bg-white border border-gray-200 rounded-xl shadow-xl p-2 hidden z-40">
    <div class="flex items-center gap-2">
        <button type="button" onclick="swipePinCurrent()" class="px-3 py-2 text-xs font-bold uppercase tracking-widest rounded-lg border border-gray-200 hover:bg-gray-50">
            Pin
        </button>
        <button type="button" onclick="swipeArchiveCurrent()" class="px-3 py-2 text-xs font-bold uppercase tracking-widest rounded-lg border border-gray-200 hover:bg-gray-50">
            Archive
        </button>
        <button type="button" onclick="swipeDeleteCurrent()" class="px-3 py-2 text-xs font-bold uppercase tracking-widest rounded-lg bg-red-600 text-white hover:bg-red-700">
            Delete
        </button>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="fixed bottom-20 left-0 right-0 bg-black text-white p-4 transform translate-y-full transition-transform z-40 flex items-center justify-between hidden">
    <div class="flex-1">
        <span id="bulkCount" class="text-sm font-medium">0 selected</span>
        <span id="bulkHint" class="text-xs text-gray-400 ml-2 hidden">Tap notes to select</span>
    </div>
    <div id="bulkButtons" class="flex gap-3 hidden">
        <button onclick="bulkDelete()" class="text-sm text-red-400 font-medium px-2 py-1">Delete</button>
        <button onclick="bulkExport()" class="text-sm text-white font-medium px-2 py-1">Export</button>
        <button onclick="toggleBulkSelect()" class="text-sm text-white font-medium px-2 py-1">Cancel</button>
    </div>
</div>

</div>

<script>
// Simple filter and search functionality
const allNotes = <?= json_encode(array_values($notes)) ?>;
let currentFilter = 'all';
let currentTag = '';

function setFilter(filter) {
    currentFilter = filter;
    currentTag = '';
    hideSwipeActions();
    updateFilterButtons();
    renderNotes();
}

function setTag(tag) {
    currentFilter = 'all';
    currentTag = tag;
    hideSwipeActions();
    renderNotes();
}

function searchNotes(query) {
    hideSwipeActions();
    renderNotes(query.toLowerCase());
}

function updateFilterButtons() {
    document.querySelectorAll('.filter-btn').forEach(btn => {
        if (btn.dataset.filter === currentFilter) {
            btn.classList.add('text-black');
            btn.classList.remove('text-gray-400');
        } else {
            btn.classList.remove('text-black');
            btn.classList.add('text-gray-400');
        }
    });
}

// More Menu
function toggleMoreMenu() {
    const menu = document.getElementById('moreMenu');
    menu.classList.toggle('hidden');
}

// Import/Export
function showImportModal() {
    document.getElementById('importModal').classList.remove('hidden');
}

function closeImportModal() {
    document.getElementById('importModal').classList.add('hidden');
}

// Helper to strip HTML tags
function stripHtmlTags(html) {
    return html.replace(/<[^>]*>/g, '');
}

// Parse CSV line
function parseCsvLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    result.push(current.trim());
    return result;
}

async function importNotes() {
    const fileInput = document.getElementById('importFile');
    const files = fileInput.files;
    if (!files || files.length === 0) {
        alert('Please select a file');
        return;
    }
    
    let allNotes = [];
    let errors = [];
    
    for (const file of Array.from(files)) {
        try {
            const content = await file.text();
            const extension = file.name.toLowerCase().split('.').pop();
            
            if (extension === 'json') {
                const data = JSON.parse(content);
                if (Array.isArray(data)) {
                    data.forEach(item => {
                        allNotes.push({
                            title: item.title || item.Title || 'Untitled',
                            content: stripHtmlTags(item.content || item.Content || item.body || item.Body || ''),
                            tags: item.tags || item.Tags || [],
                            isPinned: item.isPinned || item.IsPinned || false,
                            isFavorite: item.isFavorite || item.IsFavorite || false
                        });
                    });
                } else {
                    errors.push(`${file.name}: JSON must be an array`);
                }
            } else if (extension === 'csv') {
                const lines = content.trim().split('\n');
                if (lines.length >= 2) {
                    const headers = parseCsvLine(lines[0]).map(h => h.toLowerCase().trim());
                    const titleIdx = headers.indexOf('title');
                    const contentIdx = headers.indexOf('content');
                    const tagsIdx = headers.indexOf('tags');
                    
                    for (let i = 1; i < lines.length; i++) {
                        const values = parseCsvLine(lines[i]);
                        allNotes.push({
                            title: titleIdx !== -1 ? values[titleIdx] : 'Untitled',
                            content: contentIdx !== -1 ? stripHtmlTags(values[contentIdx]) : '',
                            tags: tagsIdx !== -1 ? values[tagsIdx].split(',').map(t => t.trim()).filter(t => t) : [],
                            isPinned: false,
                            isFavorite: false
                        });
                    }
                }
            } else if (extension === 'txt' || extension === 'md') {
                const title = file.name.replace(/\.[^/.]+$/, '');
                allNotes.push({
                    title: title,
                    content: stripHtmlTags(content),
                    tags: [],
                    isPinned: false,
                    isFavorite: false
                });
            }
        } catch (err) {
            errors.push(`${file.name}: ${err.message}`);
        }
    }
    
    if (allNotes.length === 0) {
        alert('No valid notes found to import' + (errors.length ? '\nErrors:\n' + errors.join('\n') : ''));
        return;
    }
    
    // Send to API
    try {
        const response = await fetch(`${NOTES_API_URL}?action=bulk_import`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ notes: allNotes, csrf_token: CSRF_TOKEN })
        });
        const result = await response.json();
        if (result.success) {
            const created = result?.data?.created ?? 0;
            alert(`Imported ${created} notes` + (errors.length ? `\n\nErrors:\n${errors.join('\n')}` : ''));
            location.reload();
        } else {
            alert('Import failed: ' + (result.error || 'Unknown error'));
        }
    } catch (err) {
        alert('Import error: ' + err.message);
    }
}

function exportNotes() {
    const dataStr = JSON.stringify(allNotes, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `notes-export-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
}

// AI Generate
function showAIGenerateModal() {
    document.getElementById('aiGenerateModal').classList.remove('hidden');
}

function closeAIGenerateModal() {
    document.getElementById('aiGenerateModal').classList.add('hidden');
}

function showCreateNoteOptions() {
    const modal = document.getElementById('createNoteModal');
    if (modal) modal.classList.remove('hidden');
}

function closeCreateNoteOptions() {
    const modal = document.getElementById('createNoteModal');
    if (modal) modal.classList.add('hidden');
}

function createManualNote() {
    closeCreateNoteOptions();
    window.location.href = '?page=note-form';
}

function createWithAI() {
    closeCreateNoteOptions();
    showAIGenerateModal();
}

async function generateNoteWithAI() {
    const prompt = document.getElementById('aiPrompt').value;
    const modelSelect = document.getElementById('aiModel');
    const model = modelSelect?.value || '';
    const selectedOption = modelSelect?.options?.[modelSelect.selectedIndex];
    const provider = selectedOption?.dataset?.provider || 'groq';
    if (!prompt.trim()) {
        alert('Please enter a prompt');
        return;
    }
    
    const btn = document.querySelector('#aiGenerateModal button:last-child');
    btn.textContent = 'Generating...';
    btn.disabled = true;
    
    try {
        const response = await fetch(`${AI_API_URL}?action=generate_note_content`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ prompt, provider, model, csrf_token: CSRF_TOKEN })
        });
        const result = await response.json();
        if (!result.success) {
            alert('Generation failed: ' + (result.error || 'Unknown error'));
            return;
        }

        const generatedContent = result?.data?.content || '';
        if (!generatedContent.trim()) {
            alert('AI did not return any note content');
            return;
        }

        const title = prompt.trim().slice(0, 80) || 'AI Note';
        const createResponse = await fetch(NOTES_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({
                title,
                content: generatedContent,
                tags: ['ai-generated'],
                csrf_token: CSRF_TOKEN
            })
        });
        const createResult = await createResponse.json();
        if (createResult.success && createResult.data?.id) {
            window.location.href = `?page=view-notes&id=${createResult.data.id}`;
        } else {
            alert('Failed to save generated note');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.textContent = 'Generate';
        btn.disabled = false;
    }
}

// Bulk Select
let selectedNotes = new Set();
let isBulkSelectMode = false;

function toggleBulkSelect() {
    isBulkSelectMode = !isBulkSelectMode;
    selectedNotes.clear();
    hideSwipeActions();
    updateBulkActionsBar();
    renderNotes();
}

function toggleNoteSelection(noteId) {
    if (selectedNotes.has(noteId)) {
        selectedNotes.delete(noteId);
    } else {
        selectedNotes.add(noteId);
    }
    updateBulkActionsBar();
    renderNotes();
}

function updateBulkActionsBar() {
    const bar = document.getElementById('bulkActionsBar');
    const count = document.getElementById('bulkCount');
    const hint = document.getElementById('bulkHint');
    const buttons = document.getElementById('bulkButtons');
    if (!bar || !count || !hint || !buttons) return;

    if (isBulkSelectMode) {
        bar.classList.remove('hidden');
        requestAnimationFrame(() => bar.classList.remove('translate-y-full'));
        count.textContent = `${selectedNotes.size} selected`;
        if (hint) hint.classList.remove('hidden');
        if (buttons) buttons.classList.remove('hidden');
    } else {
        bar.classList.add('translate-y-full');
        setTimeout(() => {
            if (!isBulkSelectMode) {
                bar.classList.add('hidden');
            }
        }, 180);
        if (hint) hint.classList.add('hidden');
        if (buttons) buttons.classList.add('hidden');
    }
}

async function bulkDelete() {
    if (selectedNotes.size === 0) {
        showToast('No notes selected', 'warning');
        return;
    }

    // Show confirmation modal
    const count = selectedNotes.size;
    showConfirmModal(
        `Delete ${count} note${count > 1 ? 's' : ''}?`,
        'This action cannot be undone.',
        async () => {
            try {
                const response = await fetch(`${NOTES_API_URL}?action=bulk_delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        ids: Array.from(selectedNotes),
                        csrf_token: CSRF_TOKEN
                    })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(`Deleted ${result.data.deleted} note${result.data.deleted > 1 ? 's' : ''}`, 'success');
                    selectedNotes.clear();
                    toggleBulkSelect();
                } else {
                    showToast('Some notes could not be deleted', 'error');
                }
            } catch (error) {
                console.error('Error deleting notes:', error);
                showToast('Failed to delete notes', 'error');
            }
        }
    );
}

function bulkExport() {
    const selected = allNotes.filter(n => selectedNotes.has(n.id));
    const dataStr = JSON.stringify(selected, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `notes-bulk-export-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);
}

function renderNotes(searchQuery = '') {
    let filtered = allNotes;

    if (currentFilter === 'pinned') {
        filtered = filtered.filter(n => n.isPinned);
    } else if (currentFilter === 'favorites') {
        filtered = filtered.filter(n => n.isFavorite);
    }

    if (currentTag) {
        filtered = filtered.filter(n => (n.tags || []).includes(currentTag));
    }

    if (searchQuery) {
        filtered = filtered.filter(n => {
            const title = (n.title || '').toLowerCase();
            const content = (n.content || '').toLowerCase();
            return title.includes(searchQuery) || content.includes(searchQuery);
        });
    }

    filtered.sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt));

    const container = document.querySelector('main');
    if (filtered.length === 0) {
        container.innerHTML = `
            <div class="text-center py-16 px-6">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500 text-sm">No notes found</p>
            </div>
        `;
        return;
    }

    container.innerHTML = filtered.map(note => {
        const isSelected = selectedNotes.has(note.id);
        if (isBulkSelectMode) {
            return `
                <div data-note-id="${note.id}"
                   ontouchstart="handleTouchStart(event, '${note.id}')"
                   ontouchmove="handleTouchMove(event)"
                   ontouchend="handleTouchEnd()"
                   onclick="toggleNoteSelection('${note.id}')"
                   class="block border-b border-gray-100 py-4 px-6 hover:bg-gray-50 transition-colors cursor-pointer ${isSelected ? 'bg-gray-100' : ''}"
                   style="touch-action: pan-y; will-change: transform;">
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded border-2 ${isSelected ? 'bg-black border-black' : 'border-gray-300'} flex items-center justify-center">
                            ${isSelected ? '<svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>' : ''}
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-bold uppercase tracking-tight">${note.title || 'Untitled'}</h3>
                            <p class="text-xs text-gray-500 line-clamp-1">${(note.content || '').substring(0, 60).replace(/<[^>]*>/g, '')}</p>
                        </div>
                    </div>
                </div>
            `;
        }
        return `
            <a href="?page=view-notes&id=${encodeURIComponent(note.id)}"
               data-note-id="${note.id}"
               ontouchstart="handleTouchStart(event, '${note.id}')"
               ontouchmove="handleTouchMove(event)"
               ontouchend="handleTouchEnd()"
               onclick="return handleNoteTap(event, '${note.id}')"
               class="block border-b border-gray-100 py-4 px-6 hover:bg-gray-50 transition-colors"
               style="touch-action: pan-y; will-change: transform;">
                <div class="flex justify-between items-start mb-1">
                    <h3 class="text-sm font-bold uppercase tracking-tight pr-8 flex-1">
                        ${note.title || 'Untitled'}
                        ${note.isPinned ? '<svg class="w-3 h-3 inline ml-1 text-black" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>' : ''}
                    </h3>
                    <span class="text-[10px] text-gray-400 font-medium flex-shrink-0">${new Date(note.updatedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</span>
                </div>
                <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed mb-2">
                    ${(note.content || '').substring(0, 120).replace(/<[^>]*>/g, '')}${(note.content || '').length > 120 ? '...' : ''}
                </p>
                <div class="flex gap-2">
                    ${(note.tags || []).map(t => `<span class="text-[9px] uppercase tracking-widest text-gray-400">#${t}</span>`).join('')}
                </div>
            </a>
        `;
    }).join('');
}

// ==================== NEW UI/UX FUNCTIONS ====================

// Toast Notification System
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    const colors = {
        success: 'bg-green-600',
        error: 'bg-red-600',
        warning: 'bg-orange-500',
        info: 'bg-blue-600'
    };
    const icons = {
        success: 'M5 13l4 4L19 7',
        error: 'M6 18L18 6M6 6l0 0',
        warning: 'M12 9v2m0 4h6m-6 4h6',
        info: 'M13 16h-1'
    };

    toast.className = `${colors[type]} text-white px-4 py-3 rounded-xl shadow-lg flex items-center gap-3 transform translate-x-full transition-all duration-300 pointer-events-auto min-h-[48px]`;
    toast.innerHTML = `
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${icons[type]}"></path>
        </svg>
        <span class="text-sm font-medium">${message}</span>
    `;

    container.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-x-full');
    });

    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Confirmation Modal System
let confirmCallback = null;

function showConfirmModal(title, message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const okBtn = document.getElementById('confirmOkBtn');
    const cancelBtn = document.getElementById('confirmCancelBtn');
    const content = modal.querySelector('div div');

    if (!modal || !titleEl || !messageEl || !okBtn) return;

    titleEl.textContent = title;
    messageEl.textContent = message;
    confirmCallback = onConfirm;

    // Setup OK button
    okBtn.onclick = () => {
        if (confirmCallback) confirmCallback();
        closeConfirmModal();
    };

    // Setup Cancel button
    cancelBtn.onclick = closeConfirmModal;

    // Show modal with animation
    modal.classList.remove('hidden');
    content.classList.remove('scale-95', 'opacity-0');
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    const content = modal?.querySelector('div div');

    modal?.classList.add('hidden');
    content?.classList.add('scale-95', 'opacity-0');
    confirmCallback = null;
}

// Improved Export with Toast
function exportNotes() {
    const dataStr = JSON.stringify(allNotes, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `notes-export-${new Date().toISOString().split('T')[0]}.json`;
    a.click();
    URL.revokeObjectURL(url);

    // Show success toast
    showToast(`${allNotes.length} notes exported`, 'success');
}

// Swipe Gesture Handling
let touchStartX = 0;
let touchStartY = 0;
let currentSwipeNoteId = null;
let isSwiping = false;
let touchDeltaX = 0;
let activeSwipeNoteId = null;
let justSwipedAt = 0;

function handleTouchStart(e, noteId) {
    if (isBulkSelectMode) return;
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
    currentSwipeNoteId = noteId;
    isSwiping = false;
    touchDeltaX = 0;
}

function handleTouchMove(e) {
    if (!currentSwipeNoteId) return;

    const touchX = e.touches[0].clientX;
    const touchY = e.touches[0].clientY;
    const diffX = touchX - touchStartX;
    const diffY = touchY - touchStartY;
    touchDeltaX = diffX;

    if (Math.abs(diffX) > Math.abs(diffY) && diffX < -12) {
        isSwiping = true;
        const noteEl = document.querySelector(`[data-note-id="${currentSwipeNoteId}"]`);
        const translateX = Math.max(diffX, -140);

        if (noteEl) {
            noteEl.style.transform = `translateX(${translateX}px)`;
        }
        e.preventDefault();
    }
}

function handleTouchEnd() {
    if (!currentSwipeNoteId) return;

    const noteEl = document.querySelector(`[data-note-id="${currentSwipeNoteId}"]`);
    if (!isSwiping || !noteEl) {
        currentSwipeNoteId = null;
        isSwiping = false;
        touchDeltaX = 0;
        return;
    }

    if (touchDeltaX <= -70) {
        showSwipeActions(currentSwipeNoteId);
        justSwipedAt = Date.now();
    } else {
        noteEl.style.transform = '';
        noteEl.style.transition = 'transform 0.2s ease-out';
        setTimeout(() => {
            noteEl.style.transition = '';
        }, 200);
    }

    currentSwipeNoteId = null;
    isSwiping = false;
    touchDeltaX = 0;
}

function showSwipeActions(noteId) {
    if (activeSwipeNoteId && activeSwipeNoteId !== noteId) {
        const prevEl = document.querySelector(`[data-note-id="${activeSwipeNoteId}"]`);
        if (prevEl) prevEl.style.transform = '';
    }

    const noteEl = document.querySelector(`[data-note-id="${noteId}"]`);
    const swipeAction = document.getElementById('swipeAction');
    if (noteEl) {
        noteEl.style.transform = 'translateX(-140px)';
        noteEl.style.transition = 'transform 0.2s ease-out';
        setTimeout(() => {
            noteEl.style.transition = '';
        }, 200);
    }

    if (swipeAction) {
        swipeAction.setAttribute('data-note-id', noteId);
        swipeAction.classList.remove('hidden');
    }

    activeSwipeNoteId = noteId;
}

function hideSwipeActions() {
    const swipeAction = document.getElementById('swipeAction');
    if (swipeAction) {
        swipeAction.classList.add('hidden');
        swipeAction.removeAttribute('data-note-id');
    }
    if (activeSwipeNoteId) {
        const activeEl = document.querySelector(`[data-note-id="${activeSwipeNoteId}"]`);
        if (activeEl) {
            activeEl.style.transform = '';
        }
    }
    activeSwipeNoteId = null;
}

function handleNoteTap(event, noteId) {
    if (Date.now() - justSwipedAt < 350) {
        event.preventDefault();
        return false;
    }
    if (activeSwipeNoteId === noteId) {
        event.preventDefault();
        hideSwipeActions();
        return false;
    }
    if (activeSwipeNoteId && activeSwipeNoteId !== noteId) {
        event.preventDefault();
        hideSwipeActions();
        return false;
    }
    return true;
}

function swipeDeleteCurrent() {
    const swipeAction = document.getElementById('swipeAction');
    const noteId = swipeAction?.getAttribute('data-note-id');
    if (!noteId) return;
    performSwipeDelete(noteId);
}

function swipePinCurrent() {
    const swipeAction = document.getElementById('swipeAction');
    const noteId = swipeAction?.getAttribute('data-note-id');
    if (!noteId) return;
    togglePinFromList(noteId);
}

function swipeArchiveCurrent() {
    const swipeAction = document.getElementById('swipeAction');
    const noteId = swipeAction?.getAttribute('data-note-id');
    if (!noteId) return;
    archiveFromList(noteId);
}

async function togglePinFromList(noteId) {
    hideSwipeActions();
    try {
        const response = await fetch(`${NOTES_API_URL}?action=pin&id=${encodeURIComponent(noteId)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });
        const result = await response.json();
        if (result.success) {
            const note = allNotes.find(n => n.id === noteId);
            if (note) note.isPinned = !note.isPinned;
            renderNotes((document.getElementById('search-input')?.value || '').toLowerCase());
            showToast('Pin updated', 'success');
        } else {
            showToast('Failed to update pin', 'error');
        }
    } catch (error) {
        console.error('Error pinning note:', error);
        showToast('Failed to update pin', 'error');
    }
}

async function archiveFromList(noteId) {
    hideSwipeActions();
    try {
        const response = await fetch(`${NOTES_API_URL}?action=add_tag&id=${encodeURIComponent(noteId)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ tag: 'archived', csrf_token: CSRF_TOKEN })
        });
        const result = await response.json();
        if (result.success) {
            const index = allNotes.findIndex(n => n.id === noteId);
            if (index > -1) {
                allNotes.splice(index, 1);
            }
            renderNotes((document.getElementById('search-input')?.value || '').toLowerCase());
            showToast('Note archived', 'success');
        } else {
            showToast('Failed to archive note', 'error');
        }
    } catch (error) {
        console.error('Error archiving note:', error);
        showToast('Failed to archive note', 'error');
    }
}

async function performSwipeDelete(noteId) {
    hideSwipeActions();

    showConfirmModal(
        'Delete Note?',
        'This note will be permanently deleted.',
        async () => {
            try {
                const response = await fetch(`${NOTES_API_URL}?id=${encodeURIComponent(noteId)}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                });
                const result = await response.json();
                if (result.success) {
                    showToast('Note deleted', 'success');
                    // Remove note from list
                    const index = allNotes.findIndex(n => n.id === noteId);
                    if (index > -1) {
                        allNotes.splice(index, 1);
                    }
                    renderNotes();
                } else {
                    showToast('Failed to delete', 'error');
                }
            } catch (error) {
                console.error('Error deleting note:', error);
                showToast('Delete failed', 'error');
            }
        }
    );
}
</script>

<!-- App Config -->
<script>
    const APP_URL = <?= json_encode(APP_URL) ?>;
    const NOTES_API_URL = `${APP_URL}/api/notes.php`;
    const AI_API_URL = `${APP_URL}/api/ai.php`;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
</script>

<!-- Mobile JS -->
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

<!-- Initialize Mobile -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (window.Mobile && typeof Mobile.init === 'function') {
        Mobile.init();
    }
    updateBulkActionsBar();

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-note-id]') && !event.target.closest('#swipeAction')) {
            hideSwipeActions();
        }
    });
});
</script>
</body>
</html>

