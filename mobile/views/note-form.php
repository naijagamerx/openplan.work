<?php
/**
 * Mobile New Note Page - LazyMan Tools
 * Based on Google Stitch MCP design
 * Converted to PHP with Heroicons
 */

// Load NotesAPI if not already loaded
if (!class_exists('NotesAPI')) {
    require_once __DIR__ . '/../../includes/NotesAPI.php';
}

$db = new Database(getMasterPassword(), Auth::userId());
$notesAPI = new NotesAPI($db, 'notes');

// Get all unique tags for suggestions
$allTags = $notesAPI->getAllTags();

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
    <title>New Note - <?= htmlspecialchars($siteName) ?></title>

    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/favicons/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= APP_URL ?>/assets/favicons/favicon-16x16.png">
    <link rel="shortcut icon" href="<?= APP_URL ?>/assets/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= APP_URL ?>/assets/favicons/apple-touch-icon.png">

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
                @apply bg-gray-50 text-black font-display antialiased;
            }
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        textarea:focus, input:focus {
            outline: none;
            box-shadow: none;
        }
        .touch-target {
            min-height: 44px;
            min-width: 44px;
        }
        @supports (-webkit-touch-callout: none) {
            input, textarea, select {
                font-size: 16px !important;
            }
        }
        /* Auto-fit textarea for mobile */
        .auto-fit-textarea {
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }
    </style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden" style="height: 100vh; height: 100dvh;">

<?php
$title = 'New Note';
$leftAction = 'back';
$rightAction = 'menu'; // Three-dot menu for more options
$rightTarget = 'toggleMenu()';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<!-- Save button in content area (below header) -->
<div class="px-4 py-2 bg-white border-b border-gray-100 flex items-center gap-2">
    <button onclick="saveNote()" class="bg-black text-white px-5 py-2 text-[10px] font-bold uppercase tracking-widest rounded-sm hover:opacity-90 transition-opacity touch-target">
        Save
    </button>
</div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col p-6 overflow-y-auto no-scrollbar min-h-0">
        <!-- Title Input -->
        <div class="mb-4">
            <input
                id="note-title"
                class="w-full text-2xl font-black uppercase tracking-tight border-none p-0 placeholder-gray-200 focus:ring-0"
                placeholder="Untitled Note"
                type="text"
                autocomplete="off"
            />
        </div>

        <!-- Tags Section -->
        <div id="tags-container" class="flex flex-wrap gap-2 mb-8">
            <!-- Tags will be dynamically added here -->
            <button onclick="showTagPicker()" class="inline-flex items-center border border-dashed border-gray-300 px-2 py-0.5 rounded-full text-gray-400 hover:border-black hover:text-black transition-colors">
                <!-- Plus Heroicon -->
                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="text-[9px] font-bold uppercase tracking-wider">Add Tag</span>
            </button>
        </div>

        <!-- Content Textarea -->
        <div class="flex-1 min-h-0">
            <textarea
                id="note-content"
                class="w-full h-full min-h-[200px] sm:min-h-[300px] border-none p-0 text-sm leading-relaxed placeholder-gray-300 resize-none focus:ring-0 auto-fit-textarea"
                placeholder="Start writing..."
                oninput="updateWordCount()"></textarea>
        </div>
    </main>

    <!-- Bottom Toolbar -->
    <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3 z-40 flex-shrink-0">
        <div class="flex justify-between items-center max-w-full">
            <div class="flex items-center gap-1">
                <button onclick="insertBold()" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- Bold Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z"></path>
                    </svg>
                </button>
                <button onclick="insertItalic()" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- Italic Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4H6a2 2 0 00-2 2v12a2 2 0 002 2h4m6-16h4a2 2 0 012 2v12a2 2 0 01-2 2h-4m-6 0h6"></path>
                    </svg>
                </button>
                <button onclick="insertChecklist()" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- Checklist Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </button>
                <button onclick="insertList()" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- List Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <button onclick="insertLink()" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- Link Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </button>
                <button onclick="showTagPicker()" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- Tag Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                </button>
                <button onclick="toggleMusicPlayer()" id="music-btn" class="w-10 h-10 flex items-center justify-center rounded hover:bg-gray-50 text-gray-600 touch-target">
                    <!-- Music Heroicon -->
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                    </svg>
                </button>
            </div>
            <div class="flex items-center text-gray-300">
                <span id="word-count" class="text-[10px] font-medium uppercase tracking-tighter">0 words</span>
            </div>
        </div>
        <div class="mt-4 mb-2 flex justify-center">
            <div class="w-12 h-1 bg-gray-200 rounded-full"></div>
        </div>
    </div>

<!-- Music Player Modal -->
<div id="music-player-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-end justify-center">
    <div class="bg-white w-full max-w-[420px] p-4 border-t border-gray-200">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold uppercase tracking-widest">Music Player</h3>
            <button onclick="toggleMusicPlayer()" class="p-2 hover:bg-gray-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <button onclick="toggleMusicPlayback()" id="music-play-btn" class="flex items-center gap-2 px-4 py-2 bg-black text-white text-sm font-bold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span id="music-play-text">Play</span>
                </button>
                <input type="file" id="music-file-input" accept="audio/*" class="hidden" onchange="uploadMusic(this)">
                <button onclick="document.getElementById('music-file-input').click()" class="flex items-center gap-2 px-4 py-2 border border-black text-sm font-bold uppercase tracking-wider">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M8 12l4 4m0 0l4-4m-4 4V4"></path>
                    </svg>
                    Upload
                </button>
            </div>
            <div id="music-track-name" class="text-xs text-gray-500 text-center">No track selected</div>
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5L6 9H3v6h3l5 4V5zm4.54 3.46a5 5 0 010 7.08m2.83-9.9a9 9 0 010 12.72"></path>
                </svg>
                <input id="music-volume" class="flex-1 accent-black h-1 bg-gray-200 appearance-none cursor-pointer" type="range" min="0" max="1" step="0.05" value="0.6">
            </div>
        </div>
    </div>
</div>

<!-- Hidden audio element -->
<audio id="note-audio-player"></audio>

</div>

<!-- Tag Picker Modal -->
<div id="tag-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-end justify-center">
    <div class="bg-white w-full max-w-[420px] rounded-t-xl p-4 max-h-[70vh] overflow-y-auto">
        <h3 class="text-sm font-bold uppercase tracking-widest mb-4">Select Tags</h3>
        <div id="available-tags" class="flex flex-wrap gap-2 mb-4">
            <?php foreach ($allTags as $tag): ?>
                <button onclick="addTag('<?= htmlspecialchars($tag) ?>')" class="tag-option px-3 py-1.5 border border-gray-200 rounded-full text-[10px] font-bold uppercase tracking-wider hover:border-black transition-colors">
                    #<?= htmlspecialchars($tag) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-gray-100 pt-4">
            <input
                type="text"
                id="new-tag-input"
                placeholder="New tag name..."
                class="w-full border-b border-gray-200 py-2 text-sm focus:outline-none focus:border-black"
                onkeypress="if(event.key === 'Enter') createNewTag()"
            />
        </div>
        <button onclick="closeTagModal()" class="w-full mt-4 py-3 text-sm font-bold uppercase tracking-widest border-t border-gray-100">Close</button>
    </div>
</div>

<!-- Menu Modal -->
<div id="menu-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-end justify-center">
    <div class="bg-white w-full max-w-[420px] rounded-t-xl p-4">
        <button onclick="saveNote()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <span class="text-sm font-medium">Save Note</span>
        </button>
        <button onclick="clearForm()" class="w-full text-left px-4 py-3 hover:bg-gray-50 flex items-center gap-3">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            <span class="text-sm font-medium text-red-500">Clear & Start Over</span>
        </button>
        <button onclick="closeMenu()" class="w-full text-left px-4 py-3 hover:bg-gray-50 font-bold">Cancel</button>
    </div>
</div>

<!-- App Config -->
<script>
    const APP_URL = <?= json_encode(APP_URL) ?>;
    const NOTES_API_URL = `${APP_URL}/api/notes.php`;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const MOBILE_VERSION = true;
</script>

<script>

const existingTags = <?= json_encode($allTags ?? []) ?>;
let selectedTags = [];

window.updateWordCount = function() {
    const content = document.getElementById('note-content')?.value || '';
    const words = content.trim() ? content.trim().split(/\s+/).length : 0;
    const countEl = document.getElementById('word-count');
    if (countEl) countEl.textContent = words + ' words';
};

window.showTagPicker = function() {
    const modal = document.getElementById('tag-modal');
    if (modal) modal.classList.remove('hidden');
};

window.closeTagModal = function() {
    const modal = document.getElementById('tag-modal');
    if (modal) modal.classList.add('hidden');
};

window.addTag = function(tag) {
    if (!selectedTags.includes(tag)) {
        selectedTags.push(tag);
        window.renderTags();
    }
    window.closeTagModal();
};

window.removeTag = function(tag) {
    selectedTags = selectedTags.filter(t => t !== tag);
    window.renderTags();
};

window.renderTags = function() {
    const container = document.getElementById('tags-container');
    if (!container) return;

    const addButtonHTML = `
        <button onclick="window.showTagPicker()" class="inline-flex items-center border border-dashed border-gray-300 px-2 py-0.5 rounded-full text-gray-400 hover:border-black hover:text-black transition-colors">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span class="text-[9px] font-bold uppercase tracking-wider">Add Tag</span>
        </button>
    `;

    const tagsHTML = selectedTags.map(tag => `
        <div class="inline-flex items-center border border-black px-2 py-0.5 rounded-full">
            <span class="text-[9px] font-bold uppercase tracking-wider">#${tag}</span>
            <button onclick="window.removeTag('${tag}')" class="ml-1 flex items-center">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `).join('');

    container.innerHTML = tagsHTML + addButtonHTML;
};

window.createNewTag = function() {
    const input = document.getElementById('new-tag-input');
    if (!input) return;

    const tag = input.value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
    if (tag && !selectedTags.includes(tag)) {
        selectedTags.push(tag);
        window.renderTags();
    }
    input.value = '';
    window.closeTagModal();
};

window.insertBold = function() {
    window.insertFormat('**', '**');
};

window.insertItalic = function() {
    window.insertFormat('*', '*');
};

window.insertChecklist = function() {
    const textarea = document.getElementById('note-content');
    if (!textarea) return;
    const start = textarea.selectionStart;
    textarea.value = textarea.value.substring(0, start) + '\n- [ ] ' + textarea.value.substring(start);
    textarea.focus();
};

window.insertList = function() {
    const textarea = document.getElementById('note-content');
    if (!textarea) return;
    const start = textarea.selectionStart;
    textarea.value = textarea.value.substring(0, start) + '\n- ' + textarea.value.substring(start);
    textarea.focus();
};

window.insertLink = function() {
    const url = prompt('Enter URL:');
    if (url) {
        window.insertFormat('[', '](' + url + ')');
    }
};

window.insertFormat = function(before, after) {
    const textarea = document.getElementById('note-content');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selected = text.substring(start, end);

    textarea.value = text.substring(0, start) + before + selected + after + text.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + before.length, end + before.length);
};

window.toggleMenu = function() {
    const modal = document.getElementById('menu-modal');
    if (modal) modal.classList.toggle('hidden');
};

window.closeMenu = function() {
    const modal = document.getElementById('menu-modal');
    if (modal) modal.classList.add('hidden');
};

window.saveNote = async function() {

    const titleInput = document.getElementById('note-title');
    const contentInput = document.getElementById('note-content');

    if (!titleInput || !contentInput) {
        alert('Error: Form elements not found');
        return;
    }

    const title = titleInput.value;
    const content = contentInput.value;


    try {
        const response = await fetch(NOTES_API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                action: 'create',
                title: title || 'Untitled Note',
                content: content,
                tags: selectedTags,
                csrf_token: CSRF_TOKEN
            })
        });

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const rawText = (await response.text()).slice(0, 120);
            throw new Error('Server returned non-JSON response while saving note: ' + rawText);
        }

        const result = await response.json();

        if (response.ok && result.success && result.data?.id) {
            window.location.href = './index.php?page=view-notes&id=' + result.data.id;
        } else {
            const message = (typeof result.error === 'object' && result.error?.message)
                ? result.error.message
                : (result.error || 'Unknown error');
            alert('Failed to save: ' + message);
        }
    } catch (error) {
        console.error('Error saving note:', error);
        alert('Failed to save note: ' + error.message);
    }
};

window.clearForm = function() {
    if (confirm('Clear all content and start over?')) {
        const titleInput = document.getElementById('note-title');
        const contentInput = document.getElementById('note-content');
        if (titleInput) titleInput.value = '';
        if (contentInput) contentInput.value = '';
        selectedTags = [];
        window.renderTags();
        window.updateWordCount();
        window.closeMenu();
    }
};

// Global error handler
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.message, e.filename, e.lineno);
});

// Close modals on backdrop click
document.getElementById('tag-modal').addEventListener('click', (e) => {
    if (e.target.id === 'tag-modal') closeTagModal();
});

document.getElementById('menu-modal').addEventListener('click', (e) => {
    if (e.target.id === 'menu-modal') closeMenu();
});

// Music Player Functions
let audioPlayer = document.getElementById('note-audio-player');
let isPlaying = false;
let currentTrack = null;

window.toggleMusicPlayer = function() {
    const modal = document.getElementById('music-player-modal');
    if (modal) {
        modal.classList.toggle('hidden');
    }
};

window.toggleMusicPlayback = function() {
    if (!audioPlayer) {
        audioPlayer = document.getElementById('note-audio-player');
    }

    if (isPlaying) {
        audioPlayer.pause();
        isPlaying = false;
        document.getElementById('music-play-text').textContent = 'Play';
    } else {
        if (currentTrack) {
            audioPlayer.play().catch(e => console.error('Playback failed:', e));
            isPlaying = true;
            document.getElementById('music-play-text').textContent = 'Pause';
        }
    }
};

window.uploadMusic = function(input) {
    const file = input.files[0];
    if (!file) return;

    const url = URL.createObjectURL(file);
    if (!audioPlayer) {
        audioPlayer = document.getElementById('note-audio-player');
    }

    audioPlayer.src = url;
    currentTrack = file.name;
    document.getElementById('music-track-name').textContent = file.name;

    // Auto-play on upload
    audioPlayer.play().catch(e => console.error('Playback failed:', e));
    isPlaying = true;
    document.getElementById('music-play-text').textContent = 'Pause';
};

// Volume control
document.getElementById('music-volume')?.addEventListener('input', function() {
    if (audioPlayer) {
        audioPlayer.volume = this.value;
    }
});

// Close music modal when clicking outside
document.getElementById('music-player-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'music-player-modal') toggleMusicPlayer();
});

// Handle audio ended
document.getElementById('note-audio-player')?.addEventListener('ended', function() {
    isPlaying = false;
    document.getElementById('music-play-text').textContent = 'Play';
});
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}
</script>
</body>
</html>

