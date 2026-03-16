<?php
/**
 * Note Form Page - LazyMan Tools
 * Based on Google Stitch MCP design
 * Converted to PHP with Heroicons
 */

// Ensure user is authenticated
if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/NotesAPI.php';

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
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>New Note - <?= htmlspecialchars($siteName) ?></title>
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
    </style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden">

    <!-- Header -->
    <header class="px-4 py-3 flex justify-between items-center bg-white sticky top-0 z-30 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <button onclick="history.back()" class="p-2 -ml-2 hover:bg-gray-50 rounded-full transition-colors touch-target">
                <!-- Arrow Back Heroicon -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </button>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="saveNote()" class="bg-black text-white px-5 py-2 text-[10px] font-bold uppercase tracking-widest rounded-sm hover:opacity-90 transition-opacity">
                Save
            </button>
            <button onclick="toggleMenu()" class="p-2 hover:bg-gray-50 rounded-full transition-colors touch-target">
                <!-- More Vert Heroicon -->
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                </svg>
            </button>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col p-6 overflow-y-auto no-scrollbar">
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
        <div class="flex-1">
            <textarea
                id="note-content"
                class="w-full h-full min-h-[400px] border-none p-0 text-sm leading-relaxed placeholder-gray-300 resize-none focus:ring-0"
                placeholder="Start writing..."
                oninput="updateWordCount()"></textarea>
        </div>
    </main>

    <!-- Bottom Toolbar -->
    <div class="sticky bottom-0 bg-white border-t border-gray-100 px-4 py-3 z-40">
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
            </div>
            <div class="flex items-center text-gray-300">
                <span id="word-count" class="text-[10px] font-medium uppercase tracking-tighter">0 words</span>
            </div>
        </div>
        <div class="mt-4 mb-2 flex justify-center">
            <div class="w-12 h-1 bg-gray-200 rounded-full"></div>
        </div>
    </div>

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

<script>
const existingTags = <?= json_encode($allTags) ?>;
let selectedTags = [];

function updateWordCount() {
    const content = document.getElementById('note-content').value;
    const words = content.trim() ? content.trim().split(/\s+/).length : 0;
    document.getElementById('word-count').textContent = words + ' words';
}

function showTagPicker() {
    document.getElementById('tag-modal').classList.remove('hidden');
}

function closeTagModal() {
    document.getElementById('tag-modal').classList.add('hidden');
}

function addTag(tag) {
    if (!selectedTags.includes(tag)) {
        selectedTags.push(tag);
        renderTags();
    }
    closeTagModal();
}

function removeTag(tag) {
    selectedTags = selectedTags.filter(t => t !== tag);
    renderTags();
}

function renderTags() {
    const container = document.getElementById('tags-container');
    const addButtonHTML = `
        <button onclick="showTagPicker()" class="inline-flex items-center border border-dashed border-gray-300 px-2 py-0.5 rounded-full text-gray-400 hover:border-black hover:text-black transition-colors">
            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span class="text-[9px] font-bold uppercase tracking-wider">Add Tag</span>
        </button>
    `;

    const tagsHTML = selectedTags.map(tag => `
        <div class="inline-flex items-center border border-black px-2 py-0.5 rounded-full">
            <span class="text-[9px] font-bold uppercase tracking-wider">#${tag}</span>
            <button onclick="removeTag('${tag}')" class="ml-1 flex items-center">
                <!-- X Close Heroicon -->
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `).join('');

    container.innerHTML = tagsHTML + addButtonHTML;
}

function createNewTag() {
    const input = document.getElementById('new-tag-input');
    const tag = input.value.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
    if (tag && !selectedTags.includes(tag)) {
        selectedTags.push(tag);
        renderTags();
    }
    input.value = '';
    closeTagModal();
}

function insertFormat(before, after) {
    const textarea = document.getElementById('note-content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selected = text.substring(start, end);

    textarea.value = text.substring(0, start) + before + selected + after + text.substring(end);
    textarea.focus();
    textarea.setSelectionRange(start + before.length, end + before.length);
}

function insertBold() {
    insertFormat('**', '**');
}

function insertItalic() {
    insertFormat('*', '*');
}

function insertChecklist() {
    const textarea = document.getElementById('note-content');
    const start = textarea.selectionStart;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + '\n- [ ] ' + text.substring(start);
    textarea.focus();
}

function insertList() {
    const textarea = document.getElementById('note-content');
    const start = textarea.selectionStart;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + '\n- ' + text.substring(start);
    textarea.focus();
}

function insertLink() {
    const url = prompt('Enter URL:');
    if (url) {
        insertFormat('[', '](' + url + ')');
    }
}

function toggleMenu() {
    document.getElementById('menu-modal').classList.toggle('hidden');
}

function closeMenu() {
    document.getElementById('menu-modal').classList.add('hidden');
}

async function saveNote() {
    const title = document.getElementById('note-title').value;
    const content = document.getElementById('note-content').value;

    try {
        const response = await fetch('./api/notes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'create',
                title: title || 'Untitled Note',
                content: content,
                tags: selectedTags,
                csrf_token: '<?= CSRF_TOKEN ?>'
            })
        });

        const result = await response.json();
        if (result.success && result.data?.id) {
            window.location.href = './index.php?page=view-notes&id=' + result.data.id;
        } else {
            alert('Failed to save: ' + (result.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error saving note:', error);
        alert('Failed to save note');
    }
}

function clearForm() {
    if (confirm('Clear all content and start over?')) {
        document.getElementById('note-title').value = '';
        document.getElementById('note-content').value = '';
        selectedTags = [];
        renderTags();
        updateWordCount();
        closeMenu();
    }
}

// Close modals on backdrop click
document.getElementById('tag-modal').addEventListener('click', (e) => {
    if (e.target.id === 'tag-modal') closeTagModal();
});

document.getElementById('menu-modal').addEventListener('click', (e) => {
    if (e.target.id === 'menu-modal') closeMenu();
});
</script>
</body>
</html>

