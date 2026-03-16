<?php
/**
 * Mobile View Note Page - Using Stitch Design
 */

// Load NotesAPI if not already loaded
if (!class_exists('NotesAPI')) {
    require_once __DIR__ . '/../../includes/NotesAPI.php';
}

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

// Fallback timeAgo function if not already defined
if (!function_exists('timeAgo')) {
    function timeAgo(string $datetime): string {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff / 60) . ' min ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 604800) return floor($diff / 86400) . ' days ago';

        return date('M d', $time);
    }
}

$db = new Database(getMasterPassword(), Auth::userId());
$notesAPI = new NotesAPI($db, 'notes');

$noteId = $_GET['id'] ?? '';

if (empty($noteId)) {
    header('Location: ?page=notes');
    exit;
}

// Get note
$note = $notesAPI->find($noteId);

if (!$note) {
    header('Location: ?page=notes');
    exit;
}

// Plain text display - no markdown parsing, no auto-links
function displayPlainText($text) {
    // Just escape HTML and preserve line breaks
    return nl2br(htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'), false);
}

$isEditing = ($_GET['edit'] ?? 'false') === 'true';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <!-- DEBUG: Mobile view-notes.php -->
    <title><?= htmlspecialchars($note['title'] ?? 'Untitled') ?> - Notes [MOBILE]</title>

    <!-- Favicons -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        textarea:focus {
            outline: none;
        }
        .markdown-content h1 {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            letter-spacing: -0.025em;
            max-width: 100%;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .markdown-content h2 {
            font-size: 1.125rem;
            font-weight: 700;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }
        .markdown-content p {
            font-size: 1rem;
            line-height: 1.625;
            margin-bottom: 1rem;
            color: #292524;
        }
        .markdown-content ul {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        /* Prevent overflow and ensure content fits mobile screen */
        .markdown-content {
            max-width: 100%;
            overflow-x: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .markdown-content p {
            max-width: 100%;
            overflow-wrap: break-word;
            word-break: break-word;
        }
        /* Ensure long URLs and text wrap properly */
        .overflow-wrap-anywhere {
            overflow-wrap: anywhere;
        }
        @supports (-webkit-touch-callout: none) {
            input, textarea, select {
                font-size: 16px !important;
            }
        }
        .checklist-row {
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
            margin-bottom: 0.625rem;
        }
        .checklist-row input[type="checkbox"] {
            width: 1rem;
            height: 1rem;
            margin-top: 0.125rem;
            flex-shrink: 0;
        }
        .checklist-row.completed .checklist-label {
            text-decoration: line-through;
            color: #9ca3af;
        }
        .plain-line {
            margin-bottom: 0.625rem;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body class="bg-gray-100 flex justify-center overflow-x-hidden">
    <div class="relative w-full max-w-[420px] bg-white shadow-2xl mx-auto flex flex-col border-x border-gray-100 overflow-hidden" style="height: 100dvh;">

        <div class="sticky top-0 z-40 px-4 py-3 flex items-center">
            <button onclick="history.back()" class="p-2 -ml-2 touch-target">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            </button>
        </div>

        <!-- Last Edited Badge -->
        <div class="px-6 py-2 border-b border-gray-50">
            <span class="text-[10px] font-medium uppercase tracking-[0.2em] text-gray-400">
                Last edited <?= timeAgo($note['updatedAt'] ?? 'now') ?>
            </span>
        </div>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto no-scrollbar" style="padding-bottom: calc(92px + env(safe-area-inset-bottom));">
            <?php if ($isEditing): ?>
                <!-- Edit Mode -->
                <form id="editForm" class="p-6 min-h-full flex flex-col">
                    <input
                        type="text"
                        name="title"
                        value="<?= htmlspecialchars($note['title'] ?? '') ?>"
                        placeholder="Note title..."
                        class="text-2xl font-black mb-4 tracking-tight w-full max-w-full min-w-0 border-none focus:ring-0 p-0"
                    >
                    <textarea
                        name="content"
                        placeholder="Start typing..."
                        id="content-editor"
                        class="w-full flex-1 min-h-[240px] resize-none border-none focus:ring-0 p-0 text-base leading-relaxed text-neutral-800"
                    ><?= htmlspecialchars($note['content'] ?? '') ?></textarea>
                </form>
            <?php else: ?>
                <!-- View Mode - Plain text only -->
                <div id="note-view-container" class="p-6 markdown-content">
                    <?= !empty($note['title']) ? '<h1 class="overflow-wrap-anywhere">' . htmlspecialchars($note['title']) . '</h1>' : '' ?>
                    <div id="note-content-view"></div>
                    <p id="note-empty-hint" class="text-gray-400 italic <?= empty(trim($note['content'] ?? '')) ? '' : 'hidden' ?>">Tap the note to start writing...</p>
                </div>
            <?php endif; ?>
        </main>

        <!-- Bottom Toolbar -->
        <div class="fixed left-1/2 -translate-x-1/2 bottom-0 w-full max-w-[420px] bg-white border-t border-gray-200 px-4 py-3 flex items-center justify-between gap-3 z-40" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <?php if ($isEditing): ?>
                <!-- Editing Tools -->
                <div class="flex items-center gap-4 min-w-0 flex-1 overflow-x-auto no-scrollbar pr-2">
                    <button type="button" onclick="toggleMenu()" class="flex items-center justify-center text-black hover:opacity-50" aria-label="More">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                        </svg>
                    </button>
                    <button type="button" onclick="insertFormat('**', '**')" class="flex items-center justify-center text-black hover:opacity-50">
                        <!-- Bold Icon - Heroicon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                        </svg>
                    </button>
                    <button type="button" onclick="insertFormat('*', '*')" class="flex items-center justify-center text-black hover:opacity-50">
                        <!-- Italic Icon - Heroicon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4H6a2 2 0 00-2 2v12a2 2 0 002 2h4m6-16h4a2 2 0 012 2v12a2 2 0 01-2 2h-4m-6 0h6"></path>
                        </svg>
                    </button>
                    <button type="button" onclick="insertCheckbox()" class="flex items-center justify-center text-black hover:opacity-50">
                        <!-- Checklist Icon - Heroicon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </button>
                    <button type="button" onclick="insertList('bullet')" class="flex items-center justify-center text-black hover:opacity-50">
                        <!-- Bullet List Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <button type="button" onclick="insertList('numbered')" class="flex items-center justify-center text-black hover:opacity-50">
                        <!-- Numbered List Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h12M7 12h12M7 17h12M3 7h.01M3 12h.01M3 17h.01"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <span id="autosave-status" class="text-[10px] uppercase tracking-widest text-gray-400 max-w-[120px] truncate"></span>
                    <div class="h-6 w-[1px] bg-gray-200"></div>
                    <button type="button" onclick="saveNote()" class="text-[11px] font-black uppercase tracking-widest bg-black text-white px-2.5 py-1.5 rounded-sm hover:opacity-90 whitespace-nowrap">
                        Done
                    </button>
                </div>
            <?php else: ?>
                <!-- View Mode Tools -->
                <div class="flex items-center gap-4 min-w-0 flex-1 overflow-x-auto no-scrollbar pr-2">
                    <button type="button" onclick="toggleMenu()" class="flex items-center justify-center text-black hover:opacity-50" aria-label="More">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                        </svg>
                    </button>
                    <button type="button" onclick="toggleEdit()" class="flex items-center justify-center text-black hover:opacity-50">
                        <!-- Edit Icon - Heroicon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                    </button>
                    <?php if ($hasAnyKey): ?>
                    <button type="button" onclick="showAIEditModal()" class="flex items-center justify-center text-purple-600 hover:opacity-50" title="AI Edit">
                        <!-- AI Icon -->
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <div class="h-6 w-[1px] bg-gray-200"></div>
                    <button type="button" onclick="window.location.href='./index.php?page=notes'" class="text-[11px] font-black uppercase tracking-widest bg-black text-white px-2.5 py-1.5 rounded-sm hover:opacity-90 whitespace-nowrap">
                        Done
                    </button>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Menu Modal -->
    <div id="menuModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-end justify-center">
        <div class="bg-white w-full max-w-[420px] rounded-t-xl p-4">
            <button onclick="togglePin(); closeMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                </svg>
                <span><?= !empty($note['isPinned']) ? 'Unpin' : 'Pin' ?> Note</span>
            </button>
            <button onclick="toggleFavorite(); closeMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                </svg>
                <span><?= !empty($note['isFavorite']) ? 'Remove from' : 'Add to' ?> Favorites</span>
            </button>
            <button onclick="showTagManagerModal(); closeMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                <span>Manage Tags</span>
            </button>
            <button onclick="exportNote()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                <span>Export Note</span>
            </button>
            <button onclick="convertToKnowledgeBase(); closeMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Convert to Knowledge Base</span>
            </button>
            <button onclick="shareNote()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                </svg>
                <span>Share</span>
            </button>
            <button onclick="deleteNote()" class="w-full text-left px-4 py-3 hover:bg-red-50 text-red-500 flex items-center gap-3">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                <span>Delete</span>
            </button>
            <button onclick="closeMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 font-bold">Cancel</button>
        </div>
    </div>

    <!-- Tag Manager Modal -->
    <div id="tagManagerModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="closeTagManagerModal()">
        <div class="bg-white rounded-2xl w-full max-w-sm p-6" onclick="event.stopPropagation()">
            <h2 class="text-xl font-bold mb-4">Manage Tags</h2>
            <div id="currentTags" class="flex flex-wrap gap-2 mb-4">
                <?php foreach (($note['tags'] ?? []) as $tag): ?>
                    <span class="inline-flex items-center gap-1 bg-gray-100 px-2 py-1 rounded-full text-xs">
                        <?= htmlspecialchars($tag) ?>
                        <button onclick="removeTag('<?= htmlspecialchars($tag) ?>')" class="text-gray-400 hover:text-red-500">×</button>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
                <input type="text" id="newTagInput" placeholder="Add new tag..." class="flex-1 p-2 border border-gray-200 rounded-lg text-sm">
                <button onclick="addTag()" class="px-4 py-2 bg-black text-white rounded-lg text-sm font-medium">Add</button>
            </div>
            <button onclick="closeTagManagerModal()" class="w-full mt-4 py-2 border border-gray-200 rounded-lg text-sm font-medium">Done</button>
        </div>
    </div>

    <!-- AI Edit Modal -->
    <div id="aiEditModal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center p-4" onclick="closeAIEditModal()">
        <div class="bg-white rounded-2xl w-full max-w-sm p-6" onclick="event.stopPropagation()">
            <h2 class="text-xl font-bold mb-4">AI Edit</h2>
            <p class="text-sm text-gray-500 mb-3">What would you like to do with this note?</p>
            <textarea id="aiEditPrompt" placeholder="e.g., Make it more formal, Summarize, Add bullet points..." class="w-full h-24 p-3 border border-gray-200 rounded-lg mb-4 resize-none text-sm"></textarea>
            <select id="aiEditModel" class="w-full p-2 border border-gray-200 rounded-lg mb-4 text-sm">
                <?php foreach ($groqModels as $model): ?>
                    <option value="<?= htmlspecialchars($model['modelId']) ?>" data-provider="groq"><?= htmlspecialchars($model['displayName']) ?> (Groq)</option>
                <?php endforeach; ?>
                <?php foreach ($openRouterModels as $model): ?>
                    <option value="<?= htmlspecialchars($model['modelId']) ?>" data-provider="openrouter"><?= htmlspecialchars($model['displayName']) ?> (OpenRouter)</option>
                <?php endforeach; ?>
            </select>
            <div class="flex gap-2">
                <button onclick="closeAIEditModal()" class="flex-1 py-2 border border-gray-200 rounded-lg text-sm font-medium">Cancel</button>
                <button onclick="applyAIEdit()" class="flex-1 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">Apply</button>
            </div>
        </div>
    </div>

    <!-- App Config -->
    <script>
        const APP_URL = <?= json_encode(APP_URL) ?>;
        const NOTES_API_URL = `${APP_URL}/api/notes.php`;
        const KB_API_URL = `${APP_URL}/api/knowledge-base.php`;
        const AI_API_URL = `${APP_URL}/api/ai.php`;
        const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
        const MOBILE_VERSION = true;
    </script>
    <script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>

    <script>
        const noteId = '<?= htmlspecialchars($note['id'] ?? '') ?>';
        let isEditing = <?= $isEditing ? 'true' : 'false' ?>;
        let currentNoteTitle = <?= json_encode($note['title'] ?? 'Untitled') ?>;
        let currentNoteContent = <?= json_encode($note['content'] ?? '') ?>;
        let autoSaveTimer = null;
        let saveInFlight = false;
        let lastSavedSignature = JSON.stringify({
            title: currentNoteTitle || 'Untitled',
            content: currentNoteContent || ''
        });

        function getErrorMessage(result, fallback = 'Request failed') {
            if (!result) return fallback;
            if (typeof result.error === 'string') return result.error;
            if (typeof result.error === 'object' && result.error?.message) return result.error.message;
            return fallback;
        }

        async function requestJson(url, options = {}) {
            const headers = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            };
            const response = await fetch(url, { ...options, headers });
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const rawText = (await response.text()).slice(0, 160);
                throw new Error(`Server returned non-JSON response: ${rawText}`);
            }
            const result = await response.json();
            return { response, result };
        }

        function setSaveStatus(text = '', tone = 'muted') {
            const statusEl = document.getElementById('autosave-status');
            if (!statusEl) return;
            statusEl.textContent = text;
            statusEl.className = 'text-[10px] uppercase tracking-widest';
            if (tone === 'error') {
                statusEl.classList.add('text-red-500');
                return;
            }
            if (tone === 'success') {
                statusEl.classList.add('text-green-500');
                return;
            }
            statusEl.classList.add('text-gray-400');
        }

        function escapeHtml(value) {
            return (value ?? '')
                .toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderChecklistContent() {
            const container = document.getElementById('note-content-view');
            const emptyHint = document.getElementById('note-empty-hint');
            if (!container) return;

            if (!(currentNoteContent || '').trim()) {
                container.innerHTML = '';
                if (emptyHint) emptyHint.classList.remove('hidden');
                return;
            }
            if (emptyHint) emptyHint.classList.add('hidden');

            const lines = currentNoteContent.split(/\r?\n/);
            container.innerHTML = lines.map((line, index) => {
                const match = line.match(/^\s*-\s*\[([ xX])\]\s+(.*)$/);
                if (match) {
                    const checked = match[1].toLowerCase() === 'x';
                    return `
                        <label class="checklist-row ${checked ? 'completed' : ''}">
                            <input type="checkbox" data-line-index="${index}" ${checked ? 'checked' : ''}>
                            <span class="checklist-label">${escapeHtml(match[2])}</span>
                        </label>
                    `;
                }
                if (!line.trim()) {
                    return '<div class="plain-line">&nbsp;</div>';
                }
                return `<p class="plain-line">${escapeHtml(line)}</p>`;
            }).join('');
        }

        async function updateNote(title, content, { silent = false } = {}) {
            const payload = {
                title: (title || 'Untitled').toString(),
                content: (content || '').toString(),
                csrf_token: CSRF_TOKEN
            };

            try {
                const { response, result } = await requestJson(
                    `${NOTES_API_URL}?action=update&id=${encodeURIComponent(noteId)}`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify(payload)
                    }
                );
                if (!response.ok || !result.success) {
                    const message = getErrorMessage(result, 'Failed to save note');
                    if (!silent) alert('Failed to save: ' + message);
                    setSaveStatus('Save failed', 'error');
                    return false;
                }

                currentNoteTitle = payload.title;
                currentNoteContent = payload.content;
                lastSavedSignature = JSON.stringify({ title: currentNoteTitle, content: currentNoteContent });
                setSaveStatus('Saved', 'success');
                setTimeout(() => setSaveStatus(''), 1200);
                renderChecklistContent();
                return true;
            } catch (error) {
                console.error('Error saving note:', error);
                setSaveStatus('Save failed', 'error');
                if (!silent) alert('Failed to save note: ' + error.message);
                return false;
            }
        }

        function scheduleAutoSave() {
            if (!isEditing) return;
            const form = document.getElementById('editForm');
            if (!form) return;

            const formData = new FormData(form);
            const title = (formData.get('title') || 'Untitled').toString();
            const content = (formData.get('content') || '').toString();
            const nextSignature = JSON.stringify({ title, content });

            if (nextSignature === lastSavedSignature) return;
            if (autoSaveTimer) clearTimeout(autoSaveTimer);

            setSaveStatus('Typing...');
            autoSaveTimer = setTimeout(async () => {
                if (saveInFlight) return;
                saveInFlight = true;
                setSaveStatus('Auto-saving...');
                await updateNote(title, content, { silent: true });
                saveInFlight = false;
            }, 1200);
        }

        function bindAutoSave() {
            const titleInput = document.querySelector('#editForm input[name="title"]');
            const contentInput = document.querySelector('#editForm textarea[name="content"]');
            if (titleInput) titleInput.addEventListener('input', scheduleAutoSave);
            if (contentInput) contentInput.addEventListener('input', scheduleAutoSave);
            if (titleInput) titleInput.addEventListener('blur', () => flushAutoSave({ silent: true }));
            if (contentInput) contentInput.addEventListener('blur', () => flushAutoSave({ silent: true }));
        }

        async function flushAutoSave({ silent = true } = {}) {
            if (!isEditing) return true;
            const form = document.getElementById('editForm');
            if (!form) return true;

            const formData = new FormData(form);
            const title = (formData.get('title') || 'Untitled').toString();
            const content = (formData.get('content') || '').toString();
            const nextSignature = JSON.stringify({ title, content });
            if (nextSignature === lastSavedSignature) return true;

            if (autoSaveTimer) {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = null;
            }
            if (saveInFlight) return false;

            saveInFlight = true;
            setSaveStatus('Saving...');
            const saved = await updateNote(title, content, { silent });
            saveInFlight = false;
            return saved;
        }

        async function toggleChecklistItem(lineIndex, checked) {
            const lines = currentNoteContent.split(/\r?\n/);
            lines[lineIndex] = (lines[lineIndex] || '').replace(/^(\s*-\s*\[)([ xX])(\]\s+)/, `$1${checked ? 'x' : ' '}$3`);
            const updatedContent = lines.join('\n');
            setSaveStatus('Saving checklist...');
            const saved = await updateNote(currentNoteTitle || 'Untitled', updatedContent, { silent: true });
            if (!saved) {
                renderChecklistContent();
                alert('Failed to update checklist item');
            }
        }

        window.toggleEdit = function() {
            if (!noteId) {
                alert('Error: Cannot toggle edit - missing note ID');
                return;
            }
            isEditing = !isEditing;
            window.location.href = `?page=view-notes&id=${encodeURIComponent(noteId)}&edit=${isEditing ? 'true' : 'false'}`;
        };

        window.toggleMenu = function() {
            const modal = document.getElementById('menuModal');
            if (modal) modal.classList.toggle('hidden');
        };

        window.closeMenu = function() {
            const modal = document.getElementById('menuModal');
            if (modal) modal.classList.add('hidden');
        };

        window.saveNote = async function() {
            const form = document.getElementById('editForm');
            if (!form) {
                alert('Edit form not found');
                return;
            }
            const formData = new FormData(form);
            const title = (formData.get('title') || 'Untitled').toString();
            const content = (formData.get('content') || '').toString();
            setSaveStatus('Saving...');
            const saved = await updateNote(title, content);
            if (saved) {
                window.toggleEdit();
            }
        };

        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.message, e.filename, e.lineno);
        });

        document.addEventListener('change', async (event) => {
            const checkbox = event.target.closest('#note-content-view input[type=\"checkbox\"][data-line-index]');
            if (!checkbox) return;
            const lineIndex = parseInt(checkbox.dataset.lineIndex, 10);
            if (Number.isNaN(lineIndex)) return;
            await toggleChecklistItem(lineIndex, checkbox.checked);
        });
    </script>

    <script>
        async function togglePin() {
            try {
                const { response, result } = await requestJson(
                    `${NOTES_API_URL}?action=pin&id=${encodeURIComponent(noteId)}`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ csrf_token: CSRF_TOKEN })
                    }
                );
                if (response.ok && result.success) {
                    location.reload();
                    return;
                }
                alert('Failed to toggle pin: ' + getErrorMessage(result, 'Unknown error'));
            } catch (error) {
                console.error('Error toggling pin:', error);
            }
        }

        async function toggleFavorite() {
            try {
                const { response, result } = await requestJson(
                    `${NOTES_API_URL}?action=favorite&id=${encodeURIComponent(noteId)}`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ csrf_token: CSRF_TOKEN })
                    }
                );
                if (response.ok && result.success) {
                    location.reload();
                    return;
                }
                alert('Failed to toggle favorite: ' + getErrorMessage(result, 'Unknown error'));
            } catch (error) {
                console.error('Error toggling favorite:', error);
            }
        }

        async function deleteNote() {
            if (!confirm('Delete this note?')) return;
            closeMenu();
            try {
                const { response, result } = await requestJson(
                    `${NOTES_API_URL}?id=${encodeURIComponent(noteId)}`,
                    {
                        method: 'DELETE',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN }
                    }
                );
                if (response.ok && result.success) {
                    location.href = '?page=notes';
                    return;
                }
                alert('Failed to delete note: ' + getErrorMessage(result, 'Unknown error'));
            } catch (error) {
                console.error('Error deleting note:', error);
            }
        }

        function showTagManagerModal() {
            document.getElementById('tagManagerModal').classList.remove('hidden');
        }

        function closeTagManagerModal() {
            document.getElementById('tagManagerModal').classList.add('hidden');
        }

        async function addTag() {
            const input = document.getElementById('newTagInput');
            const tag = input.value.trim();
            if (!tag) return;

            try {
                const { response, result } = await requestJson(
                    `${NOTES_API_URL}?action=add_tag&id=${encodeURIComponent(noteId)}`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ tag, csrf_token: CSRF_TOKEN })
                    }
                );
                if (response.ok && result.success) {
                    input.value = '';
                    location.reload();
                    return;
                }
                alert('Failed to add tag: ' + getErrorMessage(result, 'Unknown error'));
            } catch (error) {
                console.error('Error adding tag:', error);
            }
        }

        async function removeTag(tag) {
            try {
                const { response, result } = await requestJson(
                    `${NOTES_API_URL}?action=remove_tag&id=${encodeURIComponent(noteId)}`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ tag, csrf_token: CSRF_TOKEN })
                    }
                );
                if (response.ok && result.success) {
                    location.reload();
                    return;
                }
                alert('Failed to remove tag: ' + getErrorMessage(result, 'Unknown error'));
            } catch (error) {
                console.error('Error removing tag:', error);
            }
        }

        function exportNote() {
            const noteData = {
                title: currentNoteTitle || 'Untitled',
                content: currentNoteContent || '',
                tags: <?= json_encode($note['tags'] ?? []) ?>,
                createdAt: '<?= $note['createdAt'] ?? '' ?>',
                updatedAt: '<?= $note['updatedAt'] ?? '' ?>'
            };
            const dataStr = JSON.stringify(noteData, null, 2);
            const blob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `note-${noteId}.json`;
            a.click();
            URL.revokeObjectURL(url);
        }

        async function convertToKnowledgeBase() {
            if (!confirm('Convert this note to a Knowledge Base article?')) return;

            try {
                const { response, result } = await requestJson(
                    `${KB_API_URL}?action=create_from_note`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ noteId, csrf_token: CSRF_TOKEN })
                    }
                );
                if (response.ok && result.success) {
                    alert('Converted to Knowledge Base!');
                    window.location.href = `?page=knowledge-base&device=desktop&id=${result.articleId}`;
                    return;
                }
                alert('Conversion failed: ' + getErrorMessage(result, 'Unknown error'));
            } catch (error) {
                console.error('Error converting:', error);
                alert('Conversion failed');
            }
        }

        function showAIEditModal() {
            document.getElementById('aiEditModal').classList.remove('hidden');
        }

        function closeAIEditModal() {
            document.getElementById('aiEditModal').classList.add('hidden');
        }

        async function applyAIEdit() {
            const prompt = document.getElementById('aiEditPrompt').value.trim();
            const modelSelect = document.getElementById('aiEditModel');
            if (!modelSelect || !modelSelect.value) {
                alert('Please select an AI model');
                return;
            }

            const selectedOption = modelSelect.options[modelSelect.selectedIndex];
            const provider = selectedOption?.dataset?.provider || 'groq';
            const model = modelSelect.value;
            const btn = document.querySelector('#aiEditModal button:last-child');
            btn.textContent = 'Processing...';
            btn.disabled = true;

            try {
                const { response, result } = await requestJson(
                    `${AI_API_URL}?action=edit_note`,
                    {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({
                            noteId,
                            operation: 'improve',
                            prompt,
                            provider,
                            model,
                            csrf_token: CSRF_TOKEN
                        })
                    }
                );

                if (!response.ok || !result.success) {
                    alert('Edit failed: ' + getErrorMessage(result, 'Unknown error'));
                    return;
                }

                const editedContent = result?.data?.content ?? '';
                if (!editedContent) {
                    alert('AI edit did not return content');
                    return;
                }

                const editTitleInput = document.querySelector('#editForm input[name="title"]');
                const titleToSave = editTitleInput ? editTitleInput.value : currentNoteTitle;
                const saved = await updateNote(titleToSave || 'Untitled', editedContent);
                if (saved) {
                    location.reload();
                }
            } catch (err) {
                alert('Error: ' + err.message);
            } finally {
                btn.textContent = 'Apply';
                btn.disabled = false;
            }
        }

        function shareNote() {
            if (navigator.share) {
                navigator.share({
                    title: currentNoteTitle || 'Note',
                    text: (currentNoteContent || '').slice(0, 200),
                    url: window.location.href
                });
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Link copied!');
            }
            closeMenu();
        }

        function insertFormat(before, after) {
            const textarea = document.getElementById('content-editor');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            const selected = text.substring(start, end);

            textarea.value = text.substring(0, start) + before + selected + after + text.substring(end);
            textarea.focus();
            textarea.setSelectionRange(start + before.length, end + before.length);
            scheduleAutoSave();
        }

        function insertCheckbox() {
            const textarea = document.getElementById('content-editor');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const text = textarea.value;

            textarea.value = text.substring(0, start) + '\n- [ ] ' + text.substring(start);
            textarea.focus();
            scheduleAutoSave();
        }

        function insertList(type) {
            const textarea = document.getElementById('content-editor');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const text = textarea.value;
            const prefix = type === 'numbered' ? '\n1. ' : '\n- ';

            textarea.value = text.substring(0, start) + prefix + text.substring(start);
            textarea.focus();
            scheduleAutoSave();
        }

        document.getElementById('menuModal').addEventListener('click', (e) => {
            if (e.target.id === 'menuModal') {
                closeMenu();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            renderChecklistContent();
            bindAutoSave();
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) flushAutoSave({ silent: true });
            });
            window.addEventListener('pagehide', () => {
                flushAutoSave({ silent: true });
            });
            const viewContainer = document.getElementById('note-view-container');
            if (viewContainer && !isEditing) {
                viewContainer.addEventListener('click', (e) => {
                    if (e.target.closest('input[type="checkbox"]')) return;
                    if (e.target.closest('button,a')) return;
                    window.toggleEdit();
                });
            }
            if (window.Mobile && typeof Mobile.init === 'function') {
                Mobile.init();
            }
        });
    </script>
</body>
</html>

