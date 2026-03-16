<?php
/**
 * View Note Page
 * Full-page note viewer with markdown rendering and iOS Notes-style design
 */

$pageTitle = 'View Note';

$db = new Database(getMasterPassword(), Auth::userId());
$notesAPI = new NotesAPI($db, 'notes');

// Get note ID from URL
$noteId = $_GET['id'] ?? null;

if (!$noteId) {
    echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Note not found</div>';
    return;
}

// Find the note
$note = $notesAPI->find($noteId);

if (!$note) {
    echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">Note not found</div>';
    return;
}

// Format dates
$createdAt = date('F j, Y', strtotime($note['createdAt'] ?? 'now'));
$updatedAt = date('F j, Y g:i A', strtotime($note['updatedAt'] ?? 'now'));

// Check if in edit mode
$isEditMode = isset($_GET['edit']) && $_GET['edit'] === 'true';
?>

<style>
/* iOS Notes-style background with lined paper effect */
.notes-bg {
    background-color: #fcfcfa;
    background-image:
        linear-gradient(#e8e8e3 1px, transparent 1px);
    background-size: 100% 28px;
    min-height: 400px;
    border-radius: 8px;
}

/* Note content - proper word wrapping */
.note-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 28px;
    font-size: 16px;
    white-space: pre-wrap;
    word-wrap: break-word;
    word-break: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
}

/* Markdown styles matching iOS Notes */
.note-content h1 {
    font-size: 1.5em;
    font-weight: 700;
    margin: 0.5em 0;
    line-height: 1.3;
}

.note-content h2 {
    font-size: 1.25em;
    font-weight: 600;
    margin: 0.5em 0;
    line-height: 1.3;
}

.note-content h3 {
    font-size: 1.1em;
    font-weight: 600;
    margin: 0.5em 0;
    line-height: 1.3;
}

.note-content p {
    margin: 0.5em 0;
    line-height: 28px;
}

.note-content ul, .note-content ol {
    margin: 0.5em 0;
    padding-left: 1.5em;
}

.note-content li {
    margin: 0.25em 0;
    line-height: 28px;
}

.note-content code {
    background-color: rgba(0,0,0,0.05);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'SF Mono', Monaco, monospace;
    font-size: 0.9em;
    word-break: break-all;
    max-width: 100%;
    overflow-x: auto;
}

.note-content pre {
    background-color: rgba(0,0,0,0.03);
    padding: 12px;
    border-radius: 8px;
    overflow-x: auto;
    margin: 0.5em 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    max-width: 100%;
}

.note-content pre code {
    background: none;
    padding: 0;
    white-space: pre-wrap;
    word-break: break-word;
}

.note-content blockquote {
    border-left: 3px solid rgba(0,0,0,0.1);
    margin: 0.5em 0;
    padding-left: 1em;
    color: rgba(0,0,0,0.6);
}

.note-content a {
    color: #007aff;
    text-decoration: none;
    word-break: break-all;
}

.note-content hr {
    border: none;
    border-top: 1px solid rgba(0,0,0,0.1);
    margin: 1em 0;
}

.note-content img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}

.note-content table {
    width: 100%;
    border-collapse: collapse;
    max-width: 100%;
    overflow-x: auto;
}

.note-content th, .note-content td {
    border: 1px solid rgba(0,0,0,0.1);
    padding: 8px;
    text-align: left;
}

/* Edit mode styles */
.edit-textarea {
    width: 100%;
    min-height: 400px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 16px;
    line-height: 28px;
    padding: 12px;
    border: 2px solid #3b82f6;
    border-radius: 8px;
    background: #fcfcfa;
    resize: vertical;
    white-space: pre-wrap;
    word-wrap: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
}

.edit-input {
    width: 100%;
    font-size: 1.75em;
    font-weight: 700;
    padding: 8px 0;
    border: none;
    border-bottom: 2px solid #3b82f6;
    background: transparent;
    outline: none;
}
</style>

<!-- Note Content - Plain text only, no markdown, no auto-links -->
<div class="space-y-6">
    <!-- Header with actions -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
            <a href="?page=notes" class="flex items-center gap-2 text-gray-500 hover:text-gray-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span>Back</span>
            </a>
        </div>

        <div class="flex items-center gap-2" id="action-buttons">
            <button onclick="toggleEditMode()" class="flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit
            </button>
            <button onclick="deleteNote('<?php echo $noteId; ?>')" class="flex items-center gap-1.5 px-4 py-2 text-red-600 hover:bg-red-50 rounded-lg transition text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete
            </button>
        </div>
    </div>

    <!-- Note Header Info -->
    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400">
        <span>Created <?php echo $createdAt; ?></span>
        <?php if ($note['updatedAt'] !== $note['createdAt']): ?>
            <span>Edited <?php echo $updatedAt; ?></span>
        <?php endif; ?>

        <!-- Tags -->
        <?php if (!empty($note['tags']) && is_array($note['tags'])): ?>
            <div class="flex flex-wrap gap-1 ml-auto">
                <?php foreach ($note['tags'] as $tag): ?>
                    <span class="px-2 py-0.5 bg-gray-200/50 text-gray-500 text-xs rounded-full"><?php echo e($tag); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Status Badges -->
        <div class="flex items-center gap-2">
            <?php if ($note['isPinned']): ?>
                <span class="flex items-center gap-1 px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                    Pinned
                </span>
            <?php endif; ?>
            <?php if ($note['isFavorite']): ?>
                <span class="flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 text-xs rounded-full">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                    Favorite
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Mode -->
    <div id="view-mode" class="<?php echo $isEditMode ? 'hidden' : ''; ?>">
        <!-- Note Container with iOS Notes Style -->
        <div class="notes-bg p-6">
            <!-- Note Title -->
            <h1 id="note-title" class="text-3xl font-bold text-gray-900 mb-6 leading-tight">
                <?php echo e($note['title'] ?: 'Untitled'); ?>
            </h1>

            <!-- Note Content - Plain text only -->
            <div id="note-content" class="note-content text-gray-800 text-lg">
                <?php echo nl2br(htmlspecialchars($note['content'] ?? '', ENT_QUOTES, 'UTF-8'), false); ?>
            </div>
        </div>
    </div>

    <!-- Edit Mode -->
    <div id="edit-mode" class="<?php echo $isEditMode ? '' : 'hidden'; ?>">
        <div class="notes-bg p-6">
            <input type="text" id="edit-title" class="edit-input mb-6" value="<?php echo e($note['title'] ?: ''); ?>" placeholder="Note title...">
            <textarea id="edit-content" class="edit-textarea" rows="15" placeholder="Write your note here... (Markdown supported)"><?php echo e($note['content'] ?? ''); ?></textarea>
        </div>
        <div class="flex items-center gap-3 mt-4">
            <button onclick="saveNote()" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                Save Changes
            </button>
            <button onclick="cancelEdit()" class="px-6 py-2 text-gray-500 hover:bg-gray-100 rounded-lg transition">
                Cancel
            </button>
            <span id="save-status" class="text-sm text-gray-400 ml-auto"></span>
        </div>
    </div>
</div>

<script>
// Global variables
let noteId = '<?php echo $noteId; ?>';
let originalTitle = <?php echo json_encode($note['title'] ?? ''); ?>;
let originalContent = <?php echo json_encode($note['content'] ?? ''); ?>;
let saveTimeout = null;

// Note content is displayed as plain text only - no markdown, no auto-links

// Alias for toggleEditMode - some pages call it toggleEdit
function toggleEdit() {
    toggleEditMode();
}

function toggleEditMode() {
    const viewMode = document.getElementById('view-mode');
    const editMode = document.getElementById('edit-mode');

    if (editMode.classList.contains('hidden')) {
        viewMode.classList.add('hidden');
        editMode.classList.remove('hidden');
        document.getElementById('edit-title').focus();
    } else {
        cancelEdit();
    }
}

function cancelEdit() {
    document.getElementById('edit-title').value = originalTitle;
    document.getElementById('edit-content').value = originalContent;
    document.getElementById('edit-mode').classList.add('hidden');
    document.getElementById('view-mode').classList.remove('hidden');
    document.getElementById('save-status').textContent = '';
}

// Placeholder for toggleMenu function
function toggleMenu() {
    // Menu functionality - can be implemented as needed
}

async function saveNote() {
    const title = document.getElementById('edit-title').value.trim();
    const content = document.getElementById('edit-content').value;

    const statusEl = document.getElementById('save-status');
    statusEl.textContent = 'Saving...';

    try {
        const response = await api.put(`api/notes.php?id=${noteId}`, {
            title: title || 'Untitled',
            content: content
        });

        if (response.success) {
            statusEl.textContent = 'Saved!';
            statusEl.className = 'text-sm text-green-500 ml-auto';

            originalTitle = title || 'Untitled';
            originalContent = content;

            document.getElementById('note-title').textContent = originalTitle;
            document.getElementById('note-content').textContent = originalContent;
            renderMarkdown();

            setTimeout(() => {
                document.getElementById('edit-mode').classList.add('hidden');
                document.getElementById('view-mode').classList.remove('hidden');
                statusEl.textContent = '';
            }, 500);
        } else {
            statusEl.textContent = 'Error: ' + (response.error || 'Failed to save');
            statusEl.className = 'text-sm text-red-500 ml-auto';
        }
    } catch (error) {
        console.error('Save failed:', error);
        statusEl.textContent = 'Error saving note';
        statusEl.className = 'text-sm text-red-500 ml-auto';
    }
}

// Auto-save on typing (debounced)
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('edit-title');
    const contentInput = document.getElementById('edit-content');

    if (titleInput) titleInput.addEventListener('input', autoSave);
    if (contentInput) contentInput.addEventListener('input', autoSave);
});

function autoSave() {
    if (saveTimeout) clearTimeout(saveTimeout);

    const statusEl = document.getElementById('save-status');
    statusEl.textContent = 'Auto-saving...';

    saveTimeout = setTimeout(async () => {
        const title = document.getElementById('edit-title').value.trim();
        const content = document.getElementById('edit-content').value;

        try {
            const response = await api.put(`api/notes.php?id=${noteId}`, {
                title: title || 'Untitled',
                content: content
            });

            if (response.success) {
                originalTitle = title || 'Untitled';
                originalContent = content;
                statusEl.textContent = 'Auto-saved';
                setTimeout(() => statusEl.textContent = '', 2000);
            }
        } catch (error) {
            console.error('Auto-save failed:', error);
        }
    }, 2000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (!document.getElementById('edit-mode').classList.contains('hidden')) {
            cancelEdit();
        }
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        if (!document.getElementById('edit-mode').classList.contains('hidden')) {
            saveNote();
        }
    }
});

async function deleteNote(id) {
    if (!confirm('Are you sure you want to delete this note?')) return;

    try {
        const response = await api.delete(`api/notes.php?id=${id}`);
        if (response.success) {
            window.location.href = '?page=notes';
        } else {
            alert('Failed to delete note: ' + (response.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Delete failed:', error);
        alert('Failed to delete note');
    }
}
</script>

