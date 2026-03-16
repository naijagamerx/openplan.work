<?php
/**
 * Notes Page - Three-Pane Layout
 * Categories | Note List | Editor
 */

$pageTitle = 'Notes';

// Load AI configuration
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

// Fallback to static model lists if database is empty
if (empty($groqModels)) {
    $staticModels = GroqAPI::getModels();
    $first = true;
    $groqModels = [];
    foreach ($staticModels as $id => $name) {
        $groqModels[] = ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => $first];
        $first = false;
    }
}
if (empty($openRouterModels)) {
    $openRouterModels = array_map(fn($id, $name) => ['modelId' => $id, 'displayName' => $name, 'enabled' => true, 'isDefault' => false],
        array_keys(OpenRouterAPI::getModels()), OpenRouterAPI::getModels());
}
?>

<!-- Mobile Filters (Only visible on mobile) -->
<div class="lg:hidden px-6 py-4 space-y-4">
    <!-- Search -->
    <div class="relative">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        <input id="note-search-mobile"
               type="text"
               placeholder="Search notes..."
               onkeyup="searchNotes(this.value)"
               class="w-full bg-gray-50 border-none rounded-md py-2 pl-10 pr-4 text-sm focus:ring-1 focus:ring-black outline-none transition-all placeholder:text-gray-400">
    </div>

    <!-- Category Filters -->
    <div class="flex gap-2 overflow-x-auto no-scrollbar" id="mobile-category-filters">
        <button onclick="filterNotes('all')" class="category-btn-mobile whitespace-nowrap bg-black text-white px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider" data-category="all">All Notes</button>
        <button onclick="filterNotes('pinned')" class="category-btn-mobile whitespace-nowrap border border-gray-200 text-gray-500 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider" data-category="pinned">Pinned</button>
        <button onclick="filterNotes('favorite')" class="category-btn-mobile whitespace-nowrap border border-gray-200 text-gray-500 px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider" data-category="favorite">Favorites</button>
    </div>

    <!-- Tag Filters -->
    <div id="mobile-tag-filters" class="flex gap-2 overflow-x-auto no-scrollbar">
        <!-- Tags populated by JS -->
    </div>
</div>

<!-- Mobile Note List (< 1024px) -->
<div class="lg:hidden flex-1 overflow-y-auto no-scrollbar pb-32" id="mobile-note-list">
    <!-- Loading State -->
    <div id="notes-loading-mobile" class="flex items-center justify-center py-20">
        <div class="inline-flex items-center gap-3">
            <div class="w-8 h-8 border-4 border-black border-t-transparent rounded-full animate-spin"></div>
            <span class="text-gray-600">Loading notes...</span>
        </div>
    </div>

    <!-- Empty State -->
    <div id="notes-empty-mobile" class="hidden text-center py-20 px-4">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-900">No notes yet</h3>
        <p class="text-gray-500 text-sm mt-1">Create your first note to get started</p>
    </div>

    <!-- Notes will be rendered here -->
    <div id="notes-container-mobile"></div>
</div>

<!-- Desktop Three-Pane Container (>= 1024px) -->
<div class="hidden lg:flex h-[calc(100vh-64px)] overflow-hidden">
    <!-- Left Pane: Categories -->
    <section class="w-60 flex flex-col bg-white border-r border-gray-200 flex-shrink-0 notes-page-section h-full overflow-hidden">
        <!-- New Note Button (Desktop Only) -->
        <div class="p-4 hidden lg:block">
            <button onclick="createAndSelectNewNote()"
                    class="w-full flex items-center justify-center gap-2 bg-black hover:bg-gray-800 text-white rounded-lg h-11 transition-all active:scale-[0.98]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span class="text-sm font-bold">New Note</span>
            </button>
            <button onclick="showBulkImportModal()"
                    class="w-full mt-2 flex items-center justify-center gap-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-lg h-9 transition-all active:scale-[0.98]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
                <span class="text-xs font-medium">Import Notes</span>
            </button>
        </div>

        <!-- Categories List -->
        <div class="px-2 py-2 flex-1 overflow-y-auto hide-scrollbar">
            <div class="px-3 flex items-center justify-between mb-2">
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Folders</h3>
                <button type="button"
                        onclick="toggleNoteList()"
                        data-notes-list-toggle
                        aria-expanded="true"
                        class="p-1 rounded text-gray-400 hover:text-black hover:bg-gray-100 transition"
                        title="Toggle note list">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
            <div class="flex flex-col gap-1">
                <button onclick="filterNotes('all')"
                        class="category-btn flex items-center gap-3 px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition-colors"
                        data-category="all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    <span class="text-sm font-medium">All Notes</span>
                    <span class="ml-auto text-xs bg-gray-100 px-2 py-0.5 rounded-full" id="count-all">0</span>
                </button>
                <button onclick="filterNotes('pinned')"
                        class="category-btn flex items-center gap-3 px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition-colors"
                        data-category="pinned">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                    </svg>
                    <span class="text-sm font-medium">Pinned</span>
                    <span class="ml-auto text-xs bg-gray-100 px-2 py-0.5 rounded-full" id="count-pinned">0</span>
                </button>
                <button onclick="filterNotes('favorite')"
                        class="category-btn flex items-center gap-3 px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition-colors"
                        data-category="favorite">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                    </svg>
                    <span class="text-sm font-medium">Favorites</span>
                    <span class="ml-auto text-xs bg-gray-100 px-2 py-0.5 rounded-full" id="count-favorite">0</span>
                </button>

                <!-- Tag-based categories -->
                <div class="border-t border-gray-100 my-2"></div>
                <div class="px-3 flex items-center justify-between mb-2">
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Tags</h3>
                    <button onclick="showGlobalTagManager()" class="text-[10px] text-gray-400 hover:text-black transition" title="Manage all tags">
                        Manage
                    </button>
                </div>
                <div id="tag-categories" class="flex flex-col gap-1">
                    <!-- Tag categories will be loaded here -->
                </div>
            </div>
        </div>
    </section>

    <!-- Middle Pane: Note List -->
    <section id="notes-list-pane" class="w-80 flex flex-col bg-white border-r border-gray-200 flex-shrink-0 notes-page-section mobile-active h-full overflow-hidden">
        <!-- Search Header (Desktop Only - mobile has search in nav) -->
        <div class="p-4 border-b border-gray-200 hidden lg:block">
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input id="note-search"
                           type="text"
                           placeholder="Search notes..."
                           onkeyup="searchNotes(this.value)"
                           class="w-full bg-transparent border border-gray-200 rounded-lg pl-10 pr-4 py-2 text-sm focus:border-black focus:ring-1 focus:ring-black outline-none transition-all placeholder:text-gray-400">
                </div>
                <button type="button"
                        onclick="toggleNoteList()"
                        data-notes-list-toggle
                        aria-expanded="true"
                        class="inline-flex items-center gap-1.5 px-2.5 py-2 border border-gray-200 rounded-lg text-xs font-semibold text-gray-600 hover:text-black hover:border-gray-300 transition"
                        title="Toggle note list">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"></path>
                    </svg>
                    <span data-notes-list-toggle-label>Hide List</span>
                </button>
            </div>
        </div>

        <!-- Note List -->
        <div id="note-list" class="flex-1 overflow-y-auto hide-scrollbar">
            <!-- Loading State -->
            <div id="notes-loading" class="flex items-center justify-center py-20">
                <div class="inline-flex items-center gap-3">
                    <div class="w-8 h-8 border-4 border-black border-t-transparent rounded-full animate-spin"></div>
                    <span class="text-gray-600">Loading notes...</span>
                </div>
            </div>

            <!-- Empty State -->
            <div id="notes-empty" class="hidden text-center py-20 px-4">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">No notes yet</h3>
                <p class="text-gray-500 text-sm mt-1">Create your first note to get started</p>
            </div>

            <!-- Notes will be rendered here -->
            <div id="notes-container"></div>
        </div>
    </section>

    <!-- Right Pane: Editor -->
    <section id="notes-editor-pane" class="flex-1 flex flex-col bg-white notes-page-section h-full overflow-hidden">
        <!-- Empty State -->
        <div id="editor-empty" class="flex-1 flex items-center justify-center">
            <div class="text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Select a note</h3>
                <p class="text-gray-500 mt-2">Choose a note from the list or create a new one</p>
            </div>
        </div>

        <!-- Editor (hidden by default) -->
        <div id="editor-container" class="hidden flex flex-col flex-1 overflow-hidden">
            <!-- Mobile Editor Header (Last Edited) -->
            <div class="lg:hidden pt-4 px-6 bg-white">
                <span id="editor-last-edited-mobile" class="text-[10px] font-medium uppercase tracking-[0.2em] text-gray-400">Last edited -</span>
            </div>

            <!-- Editor Toolbar -->
            <div class="h-14 border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0 lg:flex hidden">
                <div class="flex items-center gap-1">
                    <button onclick="formatText('bold')" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors" title="Bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h8a4 4 0 100-8H6v8zm0 0h8a4 4 0 110 8H6v-8z"></path>
                        </svg>
                    </button>
                    <button onclick="formatText('italic')" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors" title="Italic">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </button>
                    <div class="w-px h-4 bg-gray-200 mx-1"></div>
                    <button onclick="insertChecklist()" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors" title="Checklist">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                    </button>
                    <button onclick="formatText('insertUnorderedList')" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors" title="List">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="w-px h-4 bg-gray-200 mx-1"></div>
                    <button onclick="openAIGenerateModal()" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors text-gray-600" title="AI Generate">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </button>
                    <button onclick="openAIEditModal()" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors text-gray-600" title="AI Edit (Ctrl+I)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </button>
                    <button onclick="openConvertToMarkdownModal()" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors text-gray-600" title="Convert to Knowledge Base">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button"
                            onclick="toggleNoteList()"
                            data-notes-list-toggle
                            aria-expanded="true"
                            class="inline-flex items-center gap-1.5 px-2 py-1 border border-gray-200 rounded-md text-[11px] font-semibold text-gray-600 hover:text-black hover:border-gray-300 transition"
                            title="Toggle note list">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"></path>
                        </svg>
                        <span data-notes-list-toggle-label>Hide List</span>
                    </button>
                    <button id="editor-pin-btn" onclick="togglePinCurrentNote()" class="p-1.5 rounded-md hover:bg-gray-100 transition-colors" title="Pin note">
                        <svg id="editor-pin-icon" class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                    </button>
                    <span id="editor-status" class="text-xs text-gray-400">Saved</span>
                    <button onclick="deleteSelectedNote()" class="p-1.5 rounded-md hover:bg-red-50 text-gray-400 hover:text-red-500 transition-colors" title="Delete">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto px-6 lg:px-12 py-8 lg:py-12 hide-scrollbar">
                <article class="max-w-2xl w-full mx-auto">
                    <!-- Title Input -->
                    <input id="editor-title"
                           type="text"
                           placeholder="Note title..."
                           class="w-full text-2xl lg:text-3xl font-black text-gray-900 border-none focus:ring-0 p-0 placeholder:text-gray-300 bg-transparent mb-4 uppercase tracking-tight"
                           oninput="triggerAutoSave()">

                    <!-- Meta Info (Desktop Only) -->
                    <div id="editor-meta" class="lg:flex hidden items-center gap-4 mb-6 text-gray-500 text-sm">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span id="editor-date">-</span>
                        </span>
                        <span class="flex items-center gap-2 flex-wrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <span id="editor-tags-list" class="flex items-center gap-1 flex-wrap"></span>
                            <button onclick="showTagManager()" class="text-xs text-gray-400 hover:text-black transition ml-1" title="Manage tags">
                                + Add Tag
                            </button>
                        </span>
                    </div>

                    <!-- Content Editor (contenteditable div) -->
                    <div id="editor-content"
                         contenteditable="true"
                         data-placeholder="Start writing..."
                         class="w-full text-base text-gray-700 border-none focus:ring-0 p-0 placeholder:text-gray-300 bg-transparent font-sans min-h-[600px] outline-none overflow-y-auto"
                         oninput="triggerAutoSave()"></div>
                </article>
            </div>

            <!-- Mobile Editor Toolbar -->
            <div class="lg:hidden bg-white border-t border-gray-200 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-5">
                    <button onclick="formatText('bold')" class="flex items-center justify-center text-black hover:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h8a4 4 0 100-8H6v8zm0 0h8a4 4 0 110 8H6v-8z"></path></svg>
                    </button>
                    <button onclick="formatText('italic')" class="flex items-center justify-center text-black hover:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </button>
                    <button onclick="insertChecklist()" class="flex items-center justify-center text-black hover:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    </button>
                    <button onclick="showMobilePane('notes')" class="flex items-center justify-center text-black hover:opacity-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                    </button>
                </div>
                <div class="flex items-center gap-4">
                    <div class="h-6 w-[1px] bg-gray-200"></div>
                    <button onclick="showMobilePane('notes')" class="text-xs font-black uppercase tracking-widest bg-black text-white px-3 py-1.5 rounded-sm">Done</button>
                </div>
            </div>
        </div>
    </section>
</div>
</div> <!-- End Desktop Container -->

<!-- Mobile Editor (Only visible on mobile, < 1024px) -->
<div id="mobile-editor" class="lg:hidden fixed inset-0 bg-white z-50 hidden flex flex-col">
    <!-- Mobile Editor Header -->
    <div class="px-6 py-4 flex items-center justify-between border-b border-gray-100">
        <div class="flex items-center gap-4">
            <button onclick="closeMobileEditor()" class="hover:opacity-60 transition-opacity">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            <h1 id="mobile-editor-title" class="text-lg font-bold tracking-tight truncate max-w-[200px]">Note</h1>
        </div>
        <button onclick="deleteSelectedNote()" class="hover:opacity-60 transition-opacity text-red-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
        </button>
    </div>

    <!-- Last Edited -->
    <div class="px-6 pt-4">
        <span id="mobile-editor-last-edited" class="text-[10px] font-medium uppercase tracking-[0.2em] text-gray-400">Last edited -</span>
    </div>

    <!-- Editor Content (Scrollable) -->
    <div class="flex-1 overflow-y-auto px-6 py-6 no-scrollbar">
        <input id="mobile-editor-title-input"
               type="text"
               placeholder="Note title..."
               class="w-full text-2xl font-black text-gray-900 border-none focus:ring-0 p-0 placeholder:text-gray-300 bg-transparent mb-4 uppercase tracking-tight font-sans"
               oninput="triggerAutoSave()">

        <!-- Tags display -->
        <div id="mobile-editor-tags" class="mb-4 flex items-center gap-2 flex-wrap text-sm">
            <span id="mobile-editor-tags-list" class="flex items-center gap-1 flex-wrap"></span>
            <button onclick="showTagManager()" class="text-xs text-gray-400 hover:text-black transition">+ Add Tag</button>
        </div>

        <div id="mobile-editor-content"
             contenteditable="true"
             data-placeholder="Start writing..."
             class="w-full text-base text-gray-700 border-none focus:ring-0 p-0 leading-relaxed placeholder:text-gray-300 bg-transparent font-sans no-scrollbar outline-none overflow-y-auto"
             style="min-height: calc(100vh - 300px);"
             oninput="triggerAutoSave()"></div>
    </div>

    <!-- Mobile Editor Toolbar -->
    <div class="bg-white border-t border-gray-200 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-5">
            <button onclick="formatText('bold')" class="flex items-center justify-center text-black hover:opacity-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 12h8a4 4 0 100-8H6v8zm0 0h8a4 4 0 110 8H6v-8z"></path>
                </svg>
            </button>
            <button onclick="formatText('italic')" class="flex items-center justify-center text-black hover:opacity-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
            </button>
            <button onclick="insertChecklist()" class="flex items-center justify-center text-black hover:opacity-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
            </button>
        </div>
        <div class="flex items-center gap-4">
            <span id="mobile-editor-status" class="text-xs text-gray-400">Saved</span>
        </div>
    </div>
</div>

<!-- Custom Scrollbar Styles -->

<!-- Custom Scrollbar Styles -->
<style>
    /* Hide all scrollbars while keeping scroll functionality */
    .custom-scrollbar,
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .custom-scrollbar::-webkit-scrollbar,
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .category-btn.active {
        background-color: #f3f4f6;
        color: #000;
    }

    .note-item.active {
        background-color: #f3f4f6;
    }

    .note-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background-color: #000;
    }

    .spinner:not(.hidden) {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top-color: white;
        border-radius: 50%;
    }

    /* Hide scrollbar while keeping scroll functionality */
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }

    /* Hide textarea scrollbar */
    #editor-content {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    #editor-content::-webkit-scrollbar {
        display: none;
    }

    /* Hide mobile editor textarea scrollbar */
    #mobile-editor-content {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    #mobile-editor-content::-webkit-scrollbar {
        display: none;
    }

    /* Placeholder for contenteditable divs */
    #editor-content:empty:before,
    #mobile-editor-content:empty:before {
        content: attr(data-placeholder);
        color: #d1d5db;
        pointer-events: none;
    }

    /* Note list collapse (desktop) */
    #notes-list-pane {
        transition: width 0.2s ease, flex-basis 0.2s ease, opacity 0.2s ease;
    }

    body.notes-list-collapsed #notes-list-pane {
        width: 0 !important;
        flex-basis: 0 !important;
        min-width: 0 !important;
        border-right: 0;
        overflow: hidden;
    }

    body.notes-list-collapsed #notes-list-pane > * {
        opacity: 0;
        pointer-events: none;
    }

    /* Note content typography + wrap */
    #editor-content,
    #mobile-editor-content {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.7;
    }

    #editor-content p,
    #mobile-editor-content p {
        margin: 0 0 0.9rem;
    }

    #editor-content ul,
    #editor-content ol,
    #mobile-editor-content ul,
    #mobile-editor-content ol {
        padding-left: 1.25rem;
        margin: 0 0 0.9rem;
    }

    #editor-content li,
    #mobile-editor-content li {
        margin: 0.25rem 0;
    }

    .note-preview {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* ==================== MOBILE SWIPEABLE CARDS ==================== */

    /* Note card container for swipeable actions */
    .note-card-container {
        position: relative;
        overflow: hidden;
        touch-action: pan-y;
    }

    .note-actions {
        position: absolute;
        right: 0;
        top: 0;
        height: 100%;
        display: flex;
        align-items: center;
        transform: translateX(100%);
        transition: transform 0.2s ease;
        z-index: 5;
    }

    /* Show actions on touch/hold */
    .note-card-container:active .note-actions,
    .note-card-container.touching .note-actions {
        transform: translateX(0);
    }

    .note-card-container:active .note-content,
    .note-card-container.touching .note-content {
        transform: translateX(-120px);
    }

    .note-content {
        transition: transform 0.2s ease;
        background: white;
        position: relative;
        z-index: 10;
    }

    /* No scrollbar class */
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
</style>

<script>
let notes = [];
let selectedNoteId = null;
let currentFilter = 'all';
let currentTag = null;
let autoSaveTimeout = null;
const NOTES_LIST_COLLAPSED_CLASS = 'notes-list-collapsed';
const NOTES_LIST_STATE_KEY = 'notes.list.collapsed';

/**
 * Check if session is still valid by testing API response
 * If session expired (401), redirect to login
 */
async function checkSessionValidity() {
    try {
        const response = await fetch('api/notes.php', {
            method: 'GET',
            credentials: 'include'
        });

        if (response.status === 401) {
            // Session expired, redirect to login
            window.location.href = '?page=login';
            return false;
        }
        return true;
    } catch (error) {
        console.error('Session check failed:', error);
        return true; // Allow to continue on network errors
    }
}

// Load notes on page load
document.addEventListener('DOMContentLoaded', function() {
    initNoteListToggle();
    loadNotes();
    loadTags();
});

function initNoteListToggle() {
    try {
        const saved = localStorage.getItem(NOTES_LIST_STATE_KEY);
        if (saved === '1') {
            document.body.classList.add(NOTES_LIST_COLLAPSED_CLASS);
        }
    } catch (error) {
        console.warn('Failed to read note list state:', error);
    }

    updateNoteListToggleButtons();
}

function updateNoteListToggleButtons() {
    const collapsed = document.body.classList.contains(NOTES_LIST_COLLAPSED_CLASS);
    document.querySelectorAll('[data-notes-list-toggle]').forEach(button => {
        const label = button.querySelector('[data-notes-list-toggle-label]');
        if (label) {
            label.textContent = collapsed ? 'Show List' : 'Hide List';
        }
        button.setAttribute('aria-expanded', (!collapsed).toString());
        button.setAttribute('aria-label', collapsed ? 'Show note list' : 'Hide note list');
    });
}

function toggleNoteList(forceState) {
    const collapsed = typeof forceState === 'boolean'
        ? forceState
        : !document.body.classList.contains(NOTES_LIST_COLLAPSED_CLASS);

    document.body.classList.toggle(NOTES_LIST_COLLAPSED_CLASS, collapsed);

    try {
        localStorage.setItem(NOTES_LIST_STATE_KEY, collapsed ? '1' : '0');
    } catch (error) {
        console.warn('Failed to save note list state:', error);
    }

    updateNoteListToggleButtons();
}

async function loadNotes() {
    try {
        const response = await api.get('api/notes.php');

        // Check for session timeout (401 or session error)
        if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
            console.warn('Session expired, redirecting to login');
            window.location.href = '?page=login';
            return;
        }

        if (response.success && Array.isArray(response.data)) {
            notes = response.data;
            renderNoteList();
            updateCounts();
        }
    } catch (error) {
        console.error('Failed to load notes:', error);
        showToast('Failed to load notes', 'error');
    }
}

async function loadTags() {
    try {
        const response = await api.get('api/notes.php?action=tags');

        // Check for session timeout
        if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
            console.warn('Session expired, redirecting to login');
            window.location.href = '?page=login';
            return;
        }

        if (response.success && Array.isArray(response.data)) {
            renderTagCategories(response.data);
        }
    } catch (error) {
        console.error('Failed to load tags:', error);
        // Check if it's a session error
        if (error.status === 401) {
            window.location.href = '?page=login';
        }
    }
}

function renderTagCategories(tags) {
    const container = document.getElementById('tag-categories');
    const mobileContainer = document.getElementById('mobile-tag-filters');

    if (tags.length === 0) {
        if (container) container.innerHTML = '<p class="px-3 text-xs text-gray-400 italic">No tags yet</p>';
        if (mobileContainer) mobileContainer.innerHTML = '';
        return;
    }

    const tagHtml = tags.map(tag => `
        <button onclick="filterByTag('${tag}')"
                class="category-btn flex items-center gap-3 px-3 py-2 rounded-lg text-gray-500 hover:bg-gray-50 transition-colors"
                data-category="tag-${tag}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
            </svg>
            <span class="text-sm font-medium">${escapeHtml(tag)}</span>
        </button>
    `).join('');

    if (container) container.innerHTML = tagHtml;

    // Mobile tag filters styling
    if (mobileContainer) {
        mobileContainer.innerHTML = tags.map(tag => `
            <button onclick="filterByTag('${tag}')"
                    class="whitespace-nowrap text-[10px] ${currentTag === tag ? 'text-black font-bold' : 'text-gray-400'} hover:text-black transition-colors uppercase tracking-widest"
                    data-category="tag-${tag}">
                #${escapeHtml(tag)}
            </button>
        `).join('');
    }
}

function renderNoteList() {
    // Desktop containers
    const loading = document.getElementById('notes-loading');
    const empty = document.getElementById('notes-empty');
    const container = document.getElementById('notes-container');

    // Mobile containers
    const loadingMobile = document.getElementById('notes-loading-mobile');
    const emptyMobile = document.getElementById('notes-empty-mobile');
    const containerMobile = document.getElementById('notes-container-mobile');

    // Hide all loading states
    if (loading) loading.classList.add('hidden');
    if (loadingMobile) loadingMobile.classList.add('hidden');

    let filteredNotes = notes;

    // Apply filters
    if (currentTag) {
        filteredNotes = filteredNotes.filter(note =>
            note.tags && note.tags.some(t => t.toLowerCase() === currentTag.toLowerCase())
        );
    } else if (currentFilter === 'pinned') {
        filteredNotes = filteredNotes.filter(note => note.isPinned);
    } else if (currentFilter === 'favorite') {
        filteredNotes = filteredNotes.filter(note => note.isFavorite);
    }

    if (filteredNotes.length === 0) {
        if (empty) empty.classList.remove('hidden');
        if (emptyMobile) emptyMobile.classList.remove('hidden');
        if (container) container.innerHTML = '';
        if (containerMobile) containerMobile.innerHTML = '';
        return;
    }

    if (empty) empty.classList.add('hidden');
    if (emptyMobile) emptyMobile.classList.add('hidden');

    // Sort: pinned first, then by date (most recent first)
    filteredNotes.sort((a, b) => {
        if (a.isPinned && !b.isPinned) return -1;
        if (!a.isPinned && b.isPinned) return 1;
        return new Date(b.updatedAt || b.createdAt) - new Date(a.updatedAt || a.createdAt);
    });

    // Render to desktop container
    if (container) {
        container.innerHTML = filteredNotes.map(note => createNoteListItem(note)).join('');

        // Add click handlers for desktop
        container.querySelectorAll('.note-item').forEach(item => {
            item.addEventListener('click', () => {
                loadNoteIntoEditor(item.dataset.noteId);
            });
        });
    }

    // Render to mobile container
    if (containerMobile) {
        containerMobile.innerHTML = filteredNotes.map(note => createNoteListItem(note)).join('');

        // Add click handlers for mobile
        containerMobile.querySelectorAll('.note-card-container').forEach(item => {
            item.addEventListener('click', (e) => {
                // Don't trigger if clicking on action buttons
                if (e.target.closest('.note-actions')) return;

                // Add touching class for visual feedback
                item.classList.add('touching');
                setTimeout(() => item.classList.remove('touching'), 200);

                loadNoteIntoEditor(item.dataset.noteId);
            });
        });
    }
}

function createNoteListItem(note) {
    const previewSource = stripHtml(note.content || '');
    const preview = previewSource.substring(0, 100);
    const date = formatDate(note.updatedAt || note.createdAt, 'MMM DD');
    const isActive = note.id === selectedNoteId;
    const isMobile = window.innerWidth < 1024;

    // Check if note is new (created within last hour)
    const ONE_HOUR = 60 * 60 * 1000;
    const isNew = new Date() - new Date(note.createdAt) < ONE_HOUR;

    if (isMobile) {
        // Swipeable card design for mobile
        return `
            <div class="note-card-container border-b border-gray-100" data-note-id="${note.id}">
                <div class="note-content p-6">
                    <div class="flex justify-between items-start mb-1">
                        <h2 class="text-sm font-bold uppercase tracking-tight pr-8">${escapeHtml(note.title || 'Untitled')}</h2>
                        <span class="text-[10px] text-gray-400 font-medium">${date}</span>
                    </div>
                    <p class="note-preview text-xs text-gray-500 line-clamp-2 leading-relaxed mb-3 break-words">${escapeHtml(preview)}</p>
                    <div class="flex gap-2 items-center">
                        ${isNew ? '<span class="inline-block w-2 h-2 bg-black rounded-full" title="New note"></span>' : ''}
                        ${(note.tags || []).map(t => `<span class="text-[9px] uppercase tracking-widest text-gray-400">#${escapeHtml(t)}</span>`).join('')}
                        ${note.isPinned ? '<span class="text-[9px] uppercase tracking-widest text-black font-bold">PINNED</span>' : ''}
                    </div>
                </div>
                <div class="note-actions">
                    <button class="w-[60px] h-full bg-gray-50 flex items-center justify-center border-l border-gray-100" onclick="event.stopPropagation(); togglePinNote('${note.id}')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                        </svg>
                    </button>
                    <button class="w-[60px] h-full bg-gray-50 flex items-center justify-center border-l border-gray-100 text-red-500" onclick="event.stopPropagation(); deleteNoteById('${note.id}')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    }

    // Desktop version with pin button
    return `
        <div class="note-item p-4 hover:bg-gray-50 border-b border-gray-200 cursor-pointer transition-colors ${isActive ? 'active' : ''} ${note.isPinned ? 'bg-yellow-50/30' : ''}"
             data-note-id="${note.id}">
            <div class="flex justify-between items-start mb-1">
                <div class="flex items-center gap-2 pr-2 overflow-hidden">
                    ${isNew ? '<span class="inline-block w-2 h-2 bg-black rounded-full flex-shrink-0" title="New note"></span>' : ''}
                    ${note.isPinned ? '<svg class="w-3 h-3 text-amber-500 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>' : ''}
                    <h4 class="font-bold text-sm text-gray-900 truncate">${escapeHtml(note.title || 'Untitled')}</h4>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button onclick="event.stopPropagation(); togglePinNote('${note.id}')" 
                            class="p-1 rounded hover:bg-gray-200 transition-colors" 
                            title="${note.isPinned ? 'Unpin note' : 'Pin note'}">
                        <svg class="w-3.5 h-3.5 ${note.isPinned ? 'text-amber-500 fill-current' : 'text-gray-400'}" 
                             fill="${note.isPinned ? 'currentColor' : 'none'}" 
                             stroke="currentColor" 
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                        </svg>
                    </button>
                    <span class="text-[10px] text-gray-500">${timeAgo(note.updatedAt || note.createdAt)}</span>
                </div>
            </div>
            <p class="note-preview text-xs text-gray-500 line-clamp-2 break-words">${escapeHtml(preview)}</p>
        </div>
    `;
}

function updateCounts() {
    document.getElementById('count-all').textContent = notes.length;
    document.getElementById('count-pinned').textContent = notes.filter(n => n.isPinned).length;
    document.getElementById('count-favorite').textContent = notes.filter(n => n.isFavorite).length;
}

function filterNotes(filter) {
    currentFilter = filter;
    currentTag = null;

    // Update desktop active state
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-category="${filter}"]`)?.classList.add('active');

    // Update mobile filter buttons
    document.querySelectorAll('.category-btn-mobile').forEach(btn => {
        btn.classList.remove('bg-black', 'text-white');
        btn.classList.add('border', 'border-gray-200', 'text-gray-500');
    });
    const activeMobileBtn = document.querySelector(`.category-btn-mobile[data-category="${filter}"]`);
    if (activeMobileBtn) {
        activeMobileBtn.classList.remove('border', 'border-gray-200', 'text-gray-500');
        activeMobileBtn.classList.add('bg-black', 'text-white');
    }

    renderNoteList();
}

function filterByTag(tag) {
    currentTag = currentTag === tag ? null : tag;
    currentFilter = 'all';

    // Update active state
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.classList.remove('active');
    });

    if (currentTag) {
        document.querySelector(`[data-category="tag-${tag}"]`)?.classList.add('active');
    } else {
        document.querySelector(`[data-category="all"]`)?.classList.add('active');
    }

    renderNoteList();
}

async function searchNotes(query) {
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(async () => {
        if (!query.trim()) {
            loadNotes();
            return;
        }

        try {
            const response = await api.get(`api/notes.php?action=search&query=${encodeURIComponent(query)}`);

            // Check for session timeout
            if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
                console.warn('Session expired during search, redirecting to login');
                window.location.href = '?page=login';
                return;
            }

            if (response.success) {
                notes = response.data;
                renderNoteList();
            }
        } catch (error) {
            console.error('Search failed:', error);
            // Check if it's a session error
            if (error.status === 401) {
                window.location.href = '?page=login';
            }
        }
    }, 300);
}

function loadNoteIntoEditor(noteId) {
    const note = notes.find(n => n.id === noteId);
    if (!note) return;

    selectedNoteId = noteId;

    const isMobile = window.innerWidth < 1024;

    if (isMobile) {
        // Mobile: Show mobile editor
        const mobileEditor = document.getElementById('mobile-editor');
        const mobileNoteList = document.getElementById('mobile-note-list');

        if (mobileEditor) mobileEditor.classList.remove('hidden');
        if (mobileNoteList) mobileNoteList.classList.add('hidden');

        // Populate mobile editor
        document.getElementById('mobile-editor-title').textContent = note.title || 'Untitled';
        document.getElementById('mobile-editor-title-input').value = note.title || '';
        // Use setEditorContent to properly normalize and escape content
        const mobileEditorContent = document.getElementById('mobile-editor-content');
        if (mobileEditorContent) {
            const html = normalizeEditorHtml(note.content || '');
            mobileEditorContent.innerHTML = html;
        }

        const mobileLastEdited = document.getElementById('mobile-editor-last-edited');
        if (mobileLastEdited) {
            mobileLastEdited.textContent = `Last edited ${timeAgo(note.updatedAt || note.createdAt)}`;
        }

        // Tags
        const tagsContainer = document.getElementById('mobile-editor-tags');
        if (tagsContainer) {
            if (note.tags && note.tags.length > 0) {
                tagsContainer.innerHTML = note.tags.map(t => `<span class="inline-flex items-center text-gray-600">#${escapeHtml(t)}</span>`).join(' ');
            } else {
                tagsContainer.textContent = 'No tags';
            }
        }

        // Update status
        document.getElementById('mobile-editor-status').textContent = 'Loaded';
    } else {
        // Desktop: Original behavior
        // Update active state in list
        document.querySelectorAll('.note-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-note-id="${noteId}"]`)?.classList.add('active');

        // Show editor, hide empty state
        document.getElementById('editor-empty').classList.add('hidden');
        document.getElementById('editor-container').classList.remove('hidden');

        // Populate editor
        document.getElementById('editor-title').value = note.title || '';
        // Use setEditorContent to properly normalize and escape content
        setEditorContent(note.content || '');

        const formattedDate = formatDate(note.updatedAt || note.createdAt);
        document.getElementById('editor-date').textContent = formattedDate;

        const desktopLastEdited = document.getElementById('editor-last-edited-mobile');
        if (desktopLastEdited) {
            desktopLastEdited.textContent = `Last edited ${timeAgo(note.updatedAt || note.createdAt)}`;
        }

        // Tags
        const desktopTagsContainer = document.getElementById('editor-tags-list');
        if (desktopTagsContainer) {
            if (note.tags && note.tags.length > 0) {
                desktopTagsContainer.innerHTML = note.tags.map(t => `<span class="inline-flex items-center">${escapeHtml(t)}</span>`).join(', ');
            } else {
                desktopTagsContainer.innerHTML = '<span class="text-gray-400">No tags</span>';
            }
        }

        // Update status
        document.getElementById('editor-status').textContent = 'Loaded';
        
        // Update pin button state
        updateEditorPinButton(note.isPinned);
    }
}

function updateEditorPinButton(isPinned) {
    const pinBtn = document.getElementById('editor-pin-btn');
    const pinIcon = document.getElementById('editor-pin-icon');
    if (pinBtn && pinIcon) {
        if (isPinned) {
            pinBtn.title = 'Unpin note';
            pinIcon.classList.remove('text-gray-400');
            pinIcon.classList.add('text-amber-500', 'fill-current');
        } else {
            pinBtn.title = 'Pin note';
            pinIcon.classList.remove('text-amber-500', 'fill-current');
            pinIcon.classList.add('text-gray-400');
        }
    }
}

async function togglePinCurrentNote() {
    if (!selectedNoteId) return;
    await togglePinNote(selectedNoteId);
}

function triggerAutoSave() {
    if (!selectedNoteId) return;

    document.getElementById('editor-status').textContent = 'Saving...';

    clearTimeout(autoSaveTimeout);
    autoSaveTimeout = setTimeout(async () => {
        await autoSaveNote();
    }, 1000); // 1 second debounce
}

async function autoSaveNote() {
    if (!selectedNoteId) return;

    const isMobile = window.innerWidth < 1024;

    // Get title and content from appropriate editor
    let title, content;

    if (isMobile) {
        title = document.getElementById('mobile-editor-title-input')?.value.trim();
        content = document.getElementById('mobile-editor-content')?.innerHTML.trim();
    } else {
        title = document.getElementById('editor-title').value.trim();
        content = document.getElementById('editor-content').innerHTML.trim();
    }

    try {
        const response = await api.put(`api/notes.php?id=${selectedNoteId}`, {
            title: title || 'Untitled',
            content,
            csrf_token: CSRF_TOKEN
        });

        // Check for session timeout
        if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
            console.warn('Session expired, redirecting to login');
            window.location.href = '?page=login';
            return;
        }

        if (response.success) {
            if (isMobile) {
                document.getElementById('mobile-editor-status').textContent = 'Saved';
                const mobileLastEdited = document.getElementById('mobile-editor-last-edited');
                if (mobileLastEdited) {
                    mobileLastEdited.textContent = `Last edited Just now`;
                }
            } else {
                document.getElementById('editor-status').textContent = 'Saved';
                const mobileLastEdited = document.getElementById('editor-last-edited-mobile');
                if (mobileLastEdited) {
                    mobileLastEdited.textContent = 'Last edited Just now';
                }
            }

            // Update local data
            const note = notes.find(n => n.id === selectedNoteId);
            if (note) {
                note.title = title || 'Untitled';
                note.content = content;
                note.updatedAt = new Date().toISOString();
            }

            // Re-render list item
            renderNoteList();
        } else {
            if (isMobile) {
                document.getElementById('mobile-editor-status').textContent = 'Error saving';
            } else {
                document.getElementById('editor-status').textContent = 'Error saving';
            }
        }
    } catch (error) {
        console.error('Failed to save note:', error);
        // Check if it's a session error
        if (error.status === 401) {
            window.location.href = '?page=login';
            return;
        }
        if (isMobile) {
            document.getElementById('mobile-editor-status').textContent = 'Error';
        } else {
            document.getElementById('editor-status').textContent = 'Error';
        }
    }
}

// Auto-resize textarea to fit content
function autoResizeTextarea() {
    const textarea = document.getElementById('editor-content');
    if (!textarea) return;

    // Reset height to auto to get the correct scrollHeight
    textarea.style.height = 'auto';

    // Set height to scrollHeight to fit all content
    textarea.style.height = textarea.scrollHeight + 'px';

    // Also resize on input
    textarea.removeEventListener('input', autoResizeTextarea);
    textarea.addEventListener('input', autoResizeTextarea);
}

async function createAndSelectNewNote() {
    try {
        const response = await api.post('api/notes.php', {
            title: 'New Note',
            content: '',
            tags: [],
            csrf_token: CSRF_TOKEN
        });

        // Check for session timeout
        if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
            console.warn('Session expired, redirecting to login');
            window.location.href = '?page=login';
            return;
        }

        if (response.success && response.data) {
            const newNote = response.data;
            // Reload notes from server to ensure proper sorting
            await loadNotes();
            loadNoteIntoEditor(newNote.id);
            showToast('Note created', 'success');

            // Focus title
            document.getElementById('editor-title').focus();
            document.getElementById('editor-title').select();
        } else {
            showToast('Failed to create note', 'error');
        }
    } catch (error) {
        console.error('Failed to create note:', error);
        // Check if it's a session error
        if (error.status === 401) {
            window.location.href = '?page=login';
            return;
        }
        showToast('Failed to create note', 'error');
    }
}

async function deleteSelectedNote() {
    if (!selectedNoteId) return;

    confirmAction('Are you sure you want to delete this note?', async () => {
        try {
            const response = await api.delete(`api/notes.php?id=${selectedNoteId}`);

            // Check for session timeout
            if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
                console.warn('Session expired, redirecting to login');
                window.location.href = '?page=login';
                return;
            }

            if (response.success) {
                notes = notes.filter(n => n.id !== selectedNoteId);
                selectedNoteId = null;

                const isMobile = window.innerWidth < 1024;

                if (isMobile) {
                    // Close mobile editor and show note list
                    closeMobileEditor();
                } else {
                    // Hide desktop editor, show empty state
                    document.getElementById('editor-container').classList.add('hidden');
                    document.getElementById('editor-empty').classList.remove('hidden');
                }

                updateCounts();
                renderNoteList();
                showToast('Note deleted', 'success');
            }
        } catch (error) {
            console.error('Failed to delete note:', error);
            // Check if it's a session error
            if (error.status === 401) {
                window.location.href = '?page=login';
                return;
            }
            showToast('Failed to delete note', 'error');
        }
    });
}

// Editor formatting functions
function formatText(command) {
    document.execCommand(command, false, null);
    document.getElementById('editor-content').focus();
}

function insertChecklist() {
    const editor = document.getElementById('editor-content');
    const selection = window.getSelection();

    // Insert checklist HTML at cursor position
    const checklistHtml = '<div><input type="checkbox"> Checklist item</div>';

    if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        range.deleteContents();

        const div = document.createElement('div');
        div.innerHTML = checklistHtml;
        range.insertNode(div);

        // Move cursor after the inserted element
        range.setStartAfter(div);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    } else {
        // No cursor position, append to end
        editor.innerHTML += '<br>' + checklistHtml;
    }

    editor.focus();
    triggerAutoSave();
}

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function stripHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.innerHTML = str;
    return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
}

function normalizeEditorHtml(content) {
    if (!content) return '';
    const hasHtml = /<\/?[a-z][\s\S]*>/i.test(content);
    if (hasHtml) {
        return content;
    }
    return escapeHtml(content).replace(/\n/g, '<br>');
}

function setEditorContent(content, options = {}) {
    const editor = document.getElementById('editor-content');
    if (!editor) return;
    const append = !!options.append;
    const html = normalizeEditorHtml(content);

    if (append && editor.innerHTML.trim() !== '') {
        editor.innerHTML = `${editor.innerHTML}<br><br>${html}`;
    } else {
        editor.innerHTML = html;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        createAndSelectNewNote();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
        e.preventDefault();
        openAIEditModal();
    }
    if (e.key === 'Escape' && selectedNoteId) {
        selectedNoteId = null;
        document.getElementById('editor-container').classList.add('hidden');
        document.getElementById('editor-empty').classList.remove('hidden');
        document.querySelectorAll('.note-item').forEach(item => {
            item.classList.remove('active');
        });
    }
});

// ==================== AI NOTE GENERATION ====================

const ALL_MODELS = <?php echo json_encode([
    'groq' => array_values($groqModels),
    'openrouter' => array_values($openRouterModels)
]); ?>;

let kbFolders = [];
let currentAIEditContent = ''; // Store current AI edit result

// Load knowledge base folders
async function loadKBFolders() {
    try {
        const response = await api.get('api/knowledge-base.php?action=list_folders');
        if (response.success && Array.isArray(response.data.folders)) {
            kbFolders = response.data.folders;
        }
    } catch (error) {
        console.error('Failed to load KB folders:', error);
    }
}

// Open AI Generate modal
function openAIGenerateModal() {
    if (!<?php echo $hasAnyKey ? 'true' : 'false'; ?>) {
        showToast('Please configure API keys in Settings', 'error');
        return;
    }

    // Ensure a note exists or create one
    if (!selectedNoteId) {
        createAndSelectNewNote();
        // Wait for note to be created
        setTimeout(() => openAIGenerateModalInternal(), 500);
        return;
    }

    openAIGenerateModalInternal();
}

function openAIGenerateModalInternal() {
    const providerOptions = <?php echo $hasGroqKey ? "'<option value=\"groq\">Groq</option>'" : "''"; ?>
        + <?php echo $hasOpenRouterKey ? "'<option value=\"openrouter\">OpenRouter</option>'" : "''"; ?>;

    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">AI Note Generation</h3>
            <form id="ai-note-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">What would you like the AI to write about?</label>
                    <textarea name="prompt" rows="4" required
                              placeholder="E.g., Write a summary of project milestones, create a meeting agenda, explain a concept..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">AI Provider</label>
                        <select name="provider" id="ai-provider-notes" onchange="updateNoteModelList()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            ${providerOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                        <select name="model" id="ai-model-notes" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <!-- Populated by JS -->
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="ai-append" name="append" class="rounded border-gray-300">
                    <label for="ai-append" class="text-sm text-gray-700">Append to existing content (don't replace)</label>
                </div>
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 flex items-center gap-2">
                        <span>Generate</span>
                        <div class="spinner hidden" id="ai-gen-spinner"></div>
                    </button>
                </div>
            </form>
        </div>
    `);

    // Initialize model list
    updateNoteModelList();

    // Handle form submission
    document.getElementById('ai-note-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const spinner = document.getElementById('ai-gen-spinner');
        spinner.classList.remove('hidden');

        const formData = new FormData(e.target);
        const data = {
            prompt: formData.get('prompt'),
            provider: formData.get('provider'),
            model: formData.get('model'),
            append: document.getElementById('ai-append').checked,
            noteId: selectedNoteId,
            csrf_token: CSRF_TOKEN
        };

        const response = await api.post('api/ai.php?action=generate_note_content', data);
        spinner.classList.add('hidden');

        if (response.success && response.data?.content) {
            // Update editor content
            setEditorContent(response.data.content, { append: data.append });
            triggerAutoSave();
            closeModal();
            showToast('Content generated!', 'success');
        } else {
            showToast(response.error || 'Generation failed', 'error');
        }
    });
}

function updateNoteModelList() {
    const provider = document.getElementById('ai-provider-notes')?.value;
    const modelSelect = document.getElementById('ai-model-notes');
    if (!modelSelect || !provider) return;

    const models = ALL_MODELS[provider] || [];
    modelSelect.innerHTML = models.map(m =>
        `<option value="${m.modelId}" ${m.isDefault ? 'selected' : ''}>${m.displayName}</option>`
    ).join('');
}

// ==================== MARKDOWN CONVERSION ====================

function openConvertToMarkdownModal() {
    if (!selectedNoteId) {
        showToast('Please select a note first', 'error');
        return;
    }

    const note = notes.find(n => n.id === selectedNoteId);
    if (!note) return;

    // Load folders first
    loadKBFolders().then(() => {
        showConvertModal(note);
    });
}

function showConvertModal(note) {
    if (kbFolders.length === 0) {
        openModal(`
            <div class="p-6 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No Folders Found</h3>
                <p class="text-gray-500 mb-4">Please create a folder in the Knowledge Base first.</p>
                <a href="?page=knowledge-base" class="inline-block px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800">
                    Go to Knowledge Base
                </a>
            </div>
        `);
        return;
    }

    const defaultFilename = (note.title || 'note').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '.md';

    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Convert to Knowledge Base</h3>
            <form id="convert-md-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Destination Folder</label>
                    <select name="folderId" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select a folder...</option>
                        ${kbFolders.map(folder => renderFolderOption(folder, 0)).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="format" value="markdown" checked class="text-black border-gray-300 focus:ring-black">
                            <span class="text-sm text-gray-700">Markdown (.md)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="format" value="xml" class="text-black border-gray-300 focus:ring-black">
                            <span class="text-sm text-gray-700">XML (.xml)</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Filename</label>
                    <input type="text" name="filename" id="convert-filename" value="${defaultFilename}" required
                           pattern="[a-zA-Z0-9-]+\\.(md|xml)"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Must end with .md or .xml and contain only letters, numbers, and hyphens</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 max-h-40 overflow-y-auto">
                    <p class="text-xs font-medium text-gray-700 mb-2">Preview (<span id="preview-format">Markdown</span>):</p>
                    <pre class="text-xs text-gray-600 whitespace-pre-wrap">${escapeHtml((note.content || '').substring(0, 500))}${(note.content || '').length > 500 ? '\n...' : ''}</pre>
                </div>
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 flex items-center gap-2">
                        <span>Convert & Save</span>
                        <div class="spinner hidden" id="convert-spinner"></div>
                    </button>
                </div>
            </form>
        </div>
    `);

    // Handle format change to update filename extension
    const formatRadios = document.querySelectorAll('input[name="format"]');
    const filenameInput = document.getElementById('convert-filename');
    const previewFormat = document.getElementById('preview-format');

    formatRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            const currentFilename = filenameInput.value;
            const baseName = currentFilename.replace(/\.(md|xml)$/, '');
            const newFormat = radio.value;
            const newExtension = newFormat === 'markdown' ? '.md' : '.xml';
            filenameInput.value = baseName + newExtension;
            previewFormat.textContent = newFormat === 'markdown' ? 'Markdown' : 'XML';
        });
    });

    document.getElementById('convert-md-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const spinner = document.getElementById('convert-spinner');
        spinner.classList.remove('hidden');

        const formData = new FormData(e.target);
        const folderId = formData.get('folderId');
        let filename = formData.get('filename');
        const format = formData.get('format');

        // Check for duplicate filenames
        try {
            const filesResponse = await api.get(`api/knowledge-base.php?action=list_files&folderId=${folderId}`);
            if (filesResponse.success && filesResponse.data?.files) {
                const existingFiles = filesResponse.data.files;
                filename = handleDuplicateFilename(filename, existingFiles, format);
            }
        } catch (error) {
            console.error('Failed to check for duplicate filenames:', error);
        }

        // Generate content based on format
        let content;
        if (format === 'xml') {
            content = generateXMLContent(note);
        } else {
            content = `# ${note.title || 'Note'}\n\n${note.content || ''}`;
        }

        const data = {
            folderId: folderId,
            filename: filename,
            content: content,
            title: note.title || '',
            format: format,
            csrf_token: CSRF_TOKEN
        };

        const response = await api.post('api/notes.php?action=convert_to_markdown', data);
        spinner.classList.add('hidden');

        if (response.success) {
            closeModal();
            showToast(`Note converted as ${filename}!`, 'success');
            setTimeout(() => {
                if (confirm('View in Knowledge Base?')) {
                    window.location.href = '?page=knowledge-base';
                }
            }, 500);
        } else {
            showToast(response.error || 'Conversion failed', 'error');
        }
    });
}

/**
 * Handle duplicate filenames by auto-renaming with counter
 * @param {string} filename - The proposed filename
 * @param {Array} existingFiles - Array of existing file objects
 * @param {string} format - 'markdown' or 'xml'
 * @returns {string} - The final filename (possibly renamed)
 */
function handleDuplicateFilename(filename, existingFiles, format) {
    const extension = format === 'markdown' ? '.md' : '.xml';
    const baseName = filename.replace(/\.(md|xml)$/, '');

    // Check if exact match exists (case-insensitive)
    const exists = existingFiles.some(file =>
        file.name.toLowerCase() === filename.toLowerCase()
    );

    if (!exists) {
        return filename;
    }

    // Find next available counter
    let counter = 1;
    let newFilename;
    do {
        newFilename = `${baseName}-${counter}${extension}`;
        const stillExists = existingFiles.some(file =>
            file.name.toLowerCase() === newFilename.toLowerCase()
        );
        if (!stillExists) {
            return newFilename;
        }
        counter++;
    } while (counter < 1000);

    return `${baseName}-${Date.now()}${extension}`;
}

/**
 * Generate XML content from note object
 * @param {Object} note - The note object
 * @returns {string} - XML formatted content
 */
function generateXMLContent(note) {
    const escapeXml = (str) => {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    };

    const title = escapeXml(note.title || 'Untitled Note');
    const content = escapeXml(note.content || '');
    const tags = (note.tags || []).map(tag => `<tag>${escapeXml(tag)}</tag>`).join('\n    ');
    const createdAt = note.createdAt || new Date().toISOString();
    const updatedAt = note.updatedAt || new Date().toISOString();

    // Build XML declaration using String.fromCharCode to avoid PHP short tag issues
    const xmlDecl = String.fromCharCode(60) + '?xml version="1.0" encoding="UTF-8"?>';
    return xmlDecl + `
<note>
  <title>${title}</title>
  <content>${content}</content>
  <tags>
    ${tags || ''}
  </tags>
  <metadata>
    <createdAt>${createdAt}</createdAt>
    <updatedAt>${updatedAt}</updatedAt>
  </metadata>
</note>`;
}

// ==================== AI EDIT FUNCTIONS ====================

/**
 * Open AI Edit modal for editing existing notes
 */
function openAIEditModal() {
    if (!selectedNoteId) {
        showToast('Please select a note first', 'error');
        return;
    }

    if (!<?php echo $hasAnyKey ? 'true' : 'false'; ?>) {
        showToast('Please configure API keys in Settings', 'error');
        return;
    }

    const note = notes.find(n => n.id === selectedNoteId);
    if (!note) return;

    const providerOptions = <?php echo $hasGroqKey ? "'<option value=\"groq\">Groq</option>'" : "''"; ?>
        + <?php echo $hasOpenRouterKey ? "'<option value=\"openrouter\">OpenRouter</option>'" : "''"; ?>;

    const previewText = (note.content || '').substring(0, 200);
    const notePreview = previewText + ((note.content || '').length > 200 ? '...' : '');

    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">AI Note Editing</h3>
            <form id="ai-edit-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Operation</label>
                    <div class="space-y-2">
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="operation" value="rewrite" class="mt-1 text-black border-gray-300 focus:ring-black">
                            <div>
                                <span class="font-medium text-gray-900">Rewrite</span>
                                <p class="text-xs text-gray-500 mt-1">Rewrite your note for clarity, structure, and professional tone</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="operation" value="improve" class="mt-1 text-black border-gray-300 focus:ring-black">
                            <div>
                                <span class="font-medium text-gray-900">Improve</span>
                                <p class="text-xs text-gray-500 mt-1">Improve grammar, spelling, and readability</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="operation" value="expand" class="mt-1 text-black border-gray-300 focus:ring-black">
                            <div>
                                <span class="font-medium text-gray-900">Expand</span>
                                <p class="text-xs text-gray-500 mt-1">Expand with more details, examples, and context (30-50% more content)</p>
                            </div>
                        </label>
                        <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="operation" value="summarize" class="mt-1 text-black border-gray-300 focus:ring-black">
                            <div>
                                <span class="font-medium text-gray-900">Summarize</span>
                                <p class="text-xs text-gray-500 mt-1">Create a concise bullet-point summary (20-30% of original)</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">AI Provider</label>
                        <select name="provider" id="ai-edit-provider" onchange="updateEditModelList()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            ${providerOptions}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
                        <select name="model" id="ai-edit-model" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <!-- Populated by JS -->
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Custom Instructions <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea name="prompt" rows="3"
                              placeholder="E.g., 'Change the name to John', 'Make it more formal', 'Add more details about the project'..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-black focus:border-transparent outline-none text-sm"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Tell the AI exactly what to change or how to modify your note</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                    <p class="text-xs font-medium text-gray-700 mb-1">Current Note Preview:</p>
                    <p class="text-xs text-gray-600 italic">${escapeHtml(notePreview)}</p>
                </div>
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="ai-edit-process-btn" disabled class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800 flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span>Process</span>
                        <div class="spinner hidden" id="ai-edit-spinner"></div>
                    </button>
                </div>
            </form>
        </div>
    `);

    // Initialize model list
    updateEditModelList();

    // Enable/disable process button based on operation selection
    const operationRadios = document.querySelectorAll('input[name="operation"]');
    const processBtn = document.getElementById('ai-edit-process-btn');

    operationRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            processBtn.disabled = false;
        });
    });

    // Handle form submission
    document.getElementById('ai-edit-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const spinner = document.getElementById('ai-edit-spinner');
        spinner.classList.remove('hidden');
        processBtn.disabled = true;

        const formData = new FormData(e.target);
        const prompt = formData.get('prompt')?.trim();
        const data = {
            noteId: selectedNoteId,
            operation: formData.get('operation'),
            provider: formData.get('provider'),
            model: formData.get('model'),
            prompt: prompt,
            csrf_token: CSRF_TOKEN
        };

        try {
            const response = await api.post('api/ai.php?action=edit_note', data);
            spinner.classList.add('hidden');

            if (response.success && response.data) {
                closeModal();
                showAIEditResult(response.data, note);
            } else {
                showToast(response.error || 'AI edit failed', 'error');
                processBtn.disabled = false;
            }
        } catch (error) {
            spinner.classList.add('hidden');
            showToast('Failed to connect to AI service', 'error');
            processBtn.disabled = false;
        }
    });
}

/**
 * Update AI Edit model list based on selected provider
 */
function updateEditModelList() {
    const provider = document.getElementById('ai-edit-provider')?.value;
    const modelSelect = document.getElementById('ai-edit-model');
    if (!modelSelect || !provider) return;

    const models = ALL_MODELS[provider] || [];
    modelSelect.innerHTML = models.map(m =>
        `<option value="${m.modelId}" ${m.isDefault ? 'selected' : ''}>${m.displayName}</option>`
    ).join('');
}

/**
 * Show AI Edit result preview modal
 */
function showAIEditResult(resultData, originalNote) {
    const originalWordCount = (originalNote.content || '').split(/\s+/).filter(w => w.length > 0).length;
    const editedWordCount = (resultData.content || '').split(/\s+/).filter(w => w.length > 0).length;

    const wordCountDiff = editedWordCount - originalWordCount;
    const percentageChange = originalWordCount > 0
        ? Math.round((wordCountDiff / originalWordCount) * 100)
        : 0;

    const changeColor = wordCountDiff > 0 ? 'text-green-600' : (wordCountDiff < 0 ? 'text-orange-600' : 'text-gray-600');
    const changeText = wordCountDiff > 0 ? `+${wordCountDiff}` : wordCountDiff;
    const percentageText = percentageChange > 0 ? `+${percentageChange}%` : `${percentageChange}%`;

    const previewText = (resultData.content || '').substring(0, 1000);
    const showMore = (resultData.content || '').length > 1000;
    
    // Store content in global variable to avoid inline quoting issues
    currentAIEditContent = resultData.content || '';

    openModal(`
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">AI Edit Result</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <span class="text-sm text-gray-600">Word Count:</span>
                        <span class="ml-2 font-medium text-gray-900">${originalWordCount} → ${editedWordCount}</span>
                    </div>
                    <div class="${changeColor} font-medium">
                        ${changeText} (${percentageText})
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-3 max-h-60 overflow-y-auto">
                    <p class="text-xs font-medium text-gray-700 mb-2">Preview:</p>
                    <pre class="text-xs text-gray-600 whitespace-pre-wrap">${escapeHtml(previewText)}${showMore ? '\n...' : ''}</pre>
                </div>
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="button" onclick="applyAIEdit()" class="px-4 py-2 bg-black text-white rounded-lg hover:bg-gray-800">Apply Changes</button>
                </div>
            </div>
        </div>
    `);
}

/**
 * Apply AI Edit changes to the note
 */
function applyAIEdit() {
    if (currentAIEditContent) {
        setEditorContent(currentAIEditContent);

        triggerAutoSave();
        closeModal();
        showToast('Note edited successfully!', 'success');
        
        // Clear content
        currentAIEditContent = '';
    }
}

function renderFolderOption(folder, level) {
    const indent = level * 16;
    const children = kbFolders.filter(f => f.parentId === folder.id);

    return `
        <option value="${folder.id}">${' '.repeat(level)}${escapeHtml(folder.name)}</option>
        ${children.map(child => renderFolderOption(child, level + 1)).join('')}
    `;
}

// ==================== MOBILE NOTE ACTIONS ====================

async function togglePinNote(noteId) {
    const note = notes.find(n => n.id === noteId);
    if (!note) return;

    try {
        const response = await api.put(`api/notes.php?id=${noteId}`, {
            isPinned: !note.isPinned,
            csrf_token: CSRF_TOKEN
        });

        // Check for session timeout
        if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
            console.warn('Session expired, redirecting to login');
            window.location.href = '?page=login';
            return;
        }

        if (response.success) {
            note.isPinned = !note.isPinned;
            renderNoteList();
            showToast(note.isPinned ? 'Note pinned' : 'Note unpinned', 'success');
        }
    } catch (error) {
        console.error('Failed to toggle pin:', error);
        // Check if it's a session error
        if (error.status === 401) {
            window.location.href = '?page=login';
            return;
        }
        showToast('Failed to update note', 'error');
    }
}

async function deleteNoteById(noteId) {
    confirmAction('Are you sure you want to delete this note?', async () => {
        try {
            const response = await api.delete(`api/notes.php?id=${noteId}`);

            // Check for session timeout
            if (!response || response.status === 401 || (response.message && response.message.includes('session'))) {
                console.warn('Session expired, redirecting to login');
                window.location.href = '?page=login';
                return;
            }

            if (response.success) {
                notes = notes.filter(n => n.id !== noteId);
                if (selectedNoteId === noteId) {
                    selectedNoteId = null;
                }
                renderNoteList();
                updateCounts();
                showToast('Note deleted', 'success');
            }
        } catch (error) {
            console.error('Failed to delete note:', error);
            // Check if it's a session error
            if (error.status === 401) {
                window.location.href = '?page=login';
                return;
            }
            showToast('Failed to delete note', 'error');
        }
    });
}

// Close mobile editor and return to note list
function closeMobileEditor() {
    const mobileEditor = document.getElementById('mobile-editor');
    const mobileNoteList = document.getElementById('mobile-note-list');

    if (mobileEditor) mobileEditor.classList.add('hidden');
    if (mobileNoteList) mobileNoteList.classList.remove('hidden');
}

// Sync mobile editor content with desktop editor
function syncMobileEditors() {
    const isMobile = window.innerWidth < 1024;

    if (!isMobile) {
        // Sync from desktop to mobile
        const desktopTitle = document.getElementById('editor-title')?.value;
        const desktopContent = document.getElementById('editor-content')?.innerHTML;

        const mobileTitleInput = document.getElementById('mobile-editor-title-input');
        const mobileContent = document.getElementById('mobile-editor-content');

        if (mobileTitleInput) mobileTitleInput.value = desktopTitle || '';
        if (mobileContent) mobileContent.innerHTML = desktopContent || '';
    } else {
        // Sync from mobile to desktop
        const mobileTitle = document.getElementById('mobile-editor-title-input')?.value;
        const mobileContent = document.getElementById('mobile-editor-content')?.innerHTML;

        const desktopTitle = document.getElementById('editor-title');
        const desktopContent = document.getElementById('editor-content');

        if (desktopTitle) desktopTitle.value = mobileTitle || '';
        if (desktopContent) desktopContent.innerHTML = mobileContent || '';
    }
}

// ============================================
// TAG MANAGEMENT FUNCTIONS
// ============================================

let currentNoteTags = [];

function renderNoteTags(tags) {
    currentNoteTags = tags || [];
    const desktopList = document.getElementById('editor-tags-list');
    const mobileList = document.getElementById('mobile-editor-tags-list');

    const html = tags.length === 0 ? '' : tags.map(tag => `
        <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">
            ${tag}
            <button onclick="removeTagFromCurrentNote('${tag}')" class="hover:text-red-500 transition" title="Remove tag">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </span>
    `).join('');

    if (desktopList) desktopList.innerHTML = html;
    if (mobileList) mobileList.innerHTML = html;
}

function showTagManager() {
    if (!selectedNoteId) {
        showToast('Please save the note first', 'warning');
        return;
    }

    const tagsHtml = currentNoteTags.map(tag => `
        <span class="inline-flex items-center gap-1 px-3 py-1 bg-gray-100 text-gray-700 text-sm rounded-full">
            ${tag}
            <button onclick="removeTagFromCurrentNote('${tag}'); showTagManager();" class="hover:text-red-500 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </span>
    `).join('');

    const content = `
        <div class="p-6 max-w-md">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Manage Tags</h3>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Current Tags</label>
                <div id="tag-manager-current" class="flex flex-wrap gap-2">
                    ${tagsHtml || '<span class="text-sm text-gray-400 italic">No tags</span>'}
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Add New Tag</label>
                <div class="flex gap-2">
                    <input type="text" id="new-tag-input" placeholder="Enter tag name..."
                           class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-black"
                           onkeypress="if(event.key==='Enter'){addTagToCurrentNote(document.getElementById('new-tag-input').value); showTagManager();}">
                    <button onclick="addTagToCurrentNote(document.getElementById('new-tag-input').value); showTagManager();"
                            class="px-4 py-2 bg-black text-white text-sm rounded-lg hover:bg-gray-800 transition">
                        Add
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-1">Press Enter or click Add</p>
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-gray-100">
                <button onclick="showGlobalTagManager()" class="text-sm text-gray-500 hover:text-black transition">
                    Manage All Tags
                </button>
                <button onclick="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-black transition">
                    Done
                </button>
            </div>
        </div>
    `;

    openModal(content);
    setTimeout(() => document.getElementById('new-tag-input')?.focus(), 100);
}

async function addTagToCurrentNote(tag) {
    if (!tag || !tag.trim()) return;
    if (!selectedNoteId) {
        showToast('Please save the note first', 'warning');
        return;
    }

    try {
        const response = await api.post('api/notes.php?action=add_tag&id=' + selectedNoteId, {
            tag: tag.trim(),
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            currentNoteTags = response.data.tags || [];
            renderNoteTags(currentNoteTags);
            loadTags(); // Refresh tag list in sidebar
            showToast('Tag added', 'success');
            document.getElementById('new-tag-input').value = '';
        }
    } catch (error) {
        showToast('Failed to add tag', 'error');
    }
}

async function removeTagFromCurrentNote(tag) {
    if (!selectedNoteId) return;

    try {
        const response = await api.post('api/notes.php?action=remove_tag&id=' + selectedNoteId, {
            tag: tag,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            currentNoteTags = response.data.tags || [];
            renderNoteTags(currentNoteTags);
            loadTags(); // Refresh tag list in sidebar
            showToast('Tag removed', 'success');
        }
    } catch (error) {
        showToast('Failed to remove tag', 'error');
    }
}

// ============================================
// GLOBAL TAG MANAGER
// ============================================

async function showGlobalTagManager() {
    try {
        const response = await api.get('api/notes.php?action=tag_stats');
        
        if (!response.success) {
            showToast('Failed to load tag statistics', 'error');
            return;
        }

        const payload = Array.isArray(response.data) ? response.data : [];

        // Defensive fallback: if API ever returns notes instead of stats, derive stats client-side.
        const stats = payload.length > 0 && typeof payload[0] === 'object' && !Object.prototype.hasOwnProperty.call(payload[0], 'tag')
            ? (() => {
                const counts = new Map();
                payload.forEach(note => {
                    const tags = Array.isArray(note?.tags) ? note.tags : [];
                    tags.forEach(rawTag => {
                        const normalized = typeof rawTag === 'string' ? rawTag.trim().toLowerCase() : '';
                        if (!normalized) return;
                        counts.set(normalized, (counts.get(normalized) || 0) + 1);
                    });
                });
                return [...counts.entries()]
                    .map(([tag, count]) => ({ tag, count }))
                    .sort((a, b) => a.tag.localeCompare(b.tag));
            })()
            : payload;

        const validStats = stats
            .map(stat => {
                const rawTag = typeof stat?.tag === 'string' ? stat.tag : '';
                const tag = rawTag.trim();
                const count = Number(stat?.count ?? 0);
                return {
                    tag,
                    count: Number.isFinite(count) ? count : 0
                };
            })
            .filter(stat => stat.tag.length > 0);

        const tagsHtml = validStats.length === 0
            ? '<p class="text-sm text-gray-400 italic">No tags found</p>'
            : validStats.map(stat => `
                <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-gray-700">${escapeHtml(stat.tag)}</span>
                        <span class="text-xs text-gray-400">${stat.count} note${stat.count !== 1 ? 's' : ''}</span>
                    </div>
                    <button onclick='confirmDeleteTagGlobal(${JSON.stringify(stat.tag)})' class="text-gray-400 hover:text-red-500 transition" title="Delete tag from all notes">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            `).join('');

        const content = `
            <div class="p-6 max-w-md">
                <h3 class="text-lg font-bold text-gray-900 mb-2">All Tags (${validStats.length})</h3>
                <p class="text-sm text-gray-500 mb-4">Deleting a tag removes it from all notes.</p>

                <div class="max-h-80 overflow-y-auto space-y-2 mb-4">
                    ${tagsHtml}
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-100">
                    <button onclick="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-black transition">
                        Close
                    </button>
                </div>
            </div>
        `;

        openModal(content);
    } catch (error) {
        console.error('Tag manager error:', error);
        showToast('Failed to load tag manager', 'error');
    }
}

function confirmDeleteTagGlobal(tag) {
    const normalizedTag = typeof tag === 'string' ? tag.trim() : '';
    if (!normalizedTag) {
        showToast('Invalid tag name', 'error');
        return;
    }

    const escapedTag = escapeHtml(normalizedTag);
    const deleteTagPayload = JSON.stringify(normalizedTag);
    
    const content = `
        <div class="p-6 max-w-sm text-center">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Tag</h3>
            <p class="text-sm text-gray-500 mb-4">Are you sure you want to delete the tag "${escapedTag}" from all notes? This cannot be undone.</p>
            <div class="flex justify-center gap-3">
                <button onclick="closeModal(); showGlobalTagManager();" class="px-4 py-2 text-sm text-gray-600 hover:text-black transition">
                    Cancel
                </button>
                <button onclick='deleteTagGlobal(${deleteTagPayload})' class="px-4 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600 transition">
                    Delete
                </button>
            </div>
        </div>
    `;
    openModal(content);
}

async function deleteTagGlobal(tag) {
    const normalizedTag = typeof tag === 'string' ? tag.trim() : '';
    if (!normalizedTag) {
        showToast('Invalid tag name', 'error');
        return;
    }

    try {
        const response = await api.post('api/notes.php?action=delete_tag_global', {
            tag: normalizedTag,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            showToast(`Tag '${normalizedTag}' deleted from ${response.data.affected} notes`, 'success');
            loadTags(); // Refresh sidebar
            if (selectedNoteId) {
                // Refresh current note's tags
                const noteResponse = await api.get('api/notes.php?id=' + selectedNoteId);
                if (noteResponse.success) {
                    currentNoteTags = noteResponse.data.tags || [];
                    renderNoteTags(currentNoteTags);
                }
            }
            closeModal();
            setTimeout(showGlobalTagManager, 300);
        }
    } catch (error) {
        showToast('Failed to delete tag', 'error');
    }
}

// ============================================
// BULK IMPORT FUNCTIONS
// ============================================

let pendingImportNotes = [];

function showBulkImportModal() {
    pendingImportNotes = [];
    const content = `
        <div class="p-6 max-w-lg">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Import Notes</h3>
            <p class="text-sm text-gray-500 mb-4">Upload a JSON or CSV file containing notes.</p>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Files</label>
                <input type="file" id="import-file-input" accept=".json,.csv,.txt,.md" multiple
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
                       onchange="handleBulkImportFiles(this.files)">
                <p class="text-xs text-gray-400 mt-1">Supported formats: JSON, CSV, TXT, MD (select multiple files)</p>
            </div>

            <div id="import-preview" class="hidden mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
                <div id="import-preview-content" class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3 bg-gray-50"></div>
            </div>

            <div id="import-errors" class="hidden mb-4">
                <label class="block text-sm font-medium text-red-600 mb-2">Errors</label>
                <div id="import-errors-content" class="max-h-32 overflow-y-auto text-sm text-red-500 bg-red-50 rounded-lg p-3"></div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button onclick="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-black transition">
                    Cancel
                </button>
                <button id="import-confirm-btn" onclick="confirmBulkImport()" disabled
                        class="px-4 py-2 text-sm bg-black text-white rounded-lg hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    Import Notes
                </button>
            </div>
        </div>
    `;
    openModal(content);
}

async function handleBulkImportFiles(files) {
    if (!files || files.length === 0) return;

    const previewDiv = document.getElementById('import-preview');
    const previewContent = document.getElementById('import-preview-content');
    const errorsDiv = document.getElementById('import-errors');
    const errorsContent = document.getElementById('import-errors-content');
    const confirmBtn = document.getElementById('import-confirm-btn');

    pendingImportNotes = [];
    confirmBtn.disabled = true;

    let allNotes = [];
    let allErrors = [];

    try {
        for (const file of Array.from(files)) {
            try {
                const content = await file.text();
                const extension = file.name.toLowerCase().split('.').pop();

                if (extension === 'json') {
                    const result = parseNotesJson(content);
                    allNotes = allNotes.concat(result.notes);
                    allErrors = allErrors.concat(result.errors.map(e => `${file.name}: ${e}`));
                } else if (extension === 'csv') {
                    const result = parseNotesCsv(content);
                    allNotes = allNotes.concat(result.notes);
                    allErrors = allErrors.concat(result.errors.map(e => `${file.name}: ${e}`));
                } else if (extension === 'txt' || extension === 'md') {
                    // Single text/markdown file - import as one note
                    // Strip HTML tags to ensure content is plain text only
                    const title = file.name.replace(/\.[^/.]+$/, ''); // Remove extension
                    const plainTextContent = content.replace(/<[^>]*>/g, ''); // Remove HTML tags
                    allNotes.push({
                        title: title,
                        content: plainTextContent,
                        tags: [],
                        isPinned: false,
                        isFavorite: false
                    });
                } else {
                    allErrors.push(`${file.name}: Unsupported file format`);
                }
            } catch (fileError) {
                allErrors.push(`${file.name}: ${fileError.message}`);
            }
        }

        pendingImportNotes = allNotes;

        // Show preview
        if (allNotes.length > 0) {
            previewDiv.classList.remove('hidden');
            previewContent.innerHTML = `
                <div class="text-sm text-gray-600 mb-2">${files.length} file(s) selected, ${allNotes.length} note(s) found</div>
                ${allNotes.map((note, i) => `
                    <div class="py-1 ${i > 0 ? 'border-t border-gray-200' : ''}">
                        <span class="font-medium">${note.title || 'Untitled'}</span>
                        ${note.tags?.length ? `<span class="text-xs text-gray-400 ml-2">[${note.tags.join(', ')}]</span>` : ''}
                    </div>
                `).join('')}`;
            confirmBtn.disabled = false;
        } else {
            previewDiv.classList.add('hidden');
        }

        // Show errors
        if (allErrors.length > 0) {
            errorsDiv.classList.remove('hidden');
            errorsContent.innerHTML = allErrors.map(e => `<div>${e}</div>`).join('');
        } else {
            errorsDiv.classList.add('hidden');
        }

    } catch (error) {
        previewDiv.classList.add('hidden');
        errorsDiv.classList.remove('hidden');
        errorsContent.innerHTML = `<div>${error.message}</div>`;
    }
}

function parseNotesJson(content) {
    const result = { notes: [], errors: [] };

    try {
        const data = JSON.parse(content);

        if (!Array.isArray(data)) {
            result.errors.push('JSON must be an array of notes');
            return result;
        }

        data.forEach((item, index) => {
            if (typeof item !== 'object' || item === null) {
                result.errors.push(`Row ${index + 1}: Invalid note format`);
                return;
            }

            // Strip HTML tags from content to ensure plain text only
            const rawContent = item.content || item.Content || item.body || item.Body || '';
            const plainTextContent = typeof rawContent === 'string' ? rawContent.replace(/<[^>]*>/g, '') : rawContent;
            
            const note = {
                title: item.title || item.Title || '',
                content: plainTextContent,
                tags: parseTags(item.tags || item.Tags || []),
                isPinned: item.isPinned || item.IsPinned || item.pinned || false,
                isFavorite: item.isFavorite || item.IsFavorite || item.favorite || false
            };

            result.notes.push(note);
        });
    } catch (e) {
        result.errors.push('Invalid JSON: ' + e.message);
    }

    return result;
}

function parseNotesCsv(content) {
    const result = { notes: [], errors: [] };

    const lines = content.trim().split('\n');
    if (lines.length < 2) {
        result.errors.push('CSV must have a header row and at least one data row');
        return result;
    }

    // Parse header
    const headers = parseCsvLine(lines[0]).map(h => h.toLowerCase().trim());

    const titleIdx = headers.indexOf('title');
    const contentIdx = headers.indexOf('content');
    const tagsIdx = headers.indexOf('tags');
    const pinnedIdx = headers.indexOf('ispinned') !== -1 ? headers.indexOf('ispinned') : headers.indexOf('pinned');
    const favoriteIdx = headers.indexOf('isfavorite') !== -1 ? headers.indexOf('isfavorite') : headers.indexOf('favorite');

    if (titleIdx === -1) {
        result.errors.push('CSV must have a "title" column');
        return result;
    }

    for (let i = 1; i < lines.length; i++) {
        const values = parseCsvLine(lines[i]);

        // Strip HTML tags from content to ensure plain text only
        const rawContent = contentIdx !== -1 ? values[contentIdx] : '';
        const plainTextContent = rawContent.replace(/<[^>]*>/g, '');
        
        const note = {
            title: values[titleIdx] || '',
            content: plainTextContent,
            tags: tagsIdx !== -1 ? parseTags(values[tagsIdx]) : [],
            isPinned: pinnedIdx !== -1 ? values[pinnedIdx].toLowerCase() === 'true' : false,
            isFavorite: favoriteIdx !== -1 ? values[favoriteIdx].toLowerCase() === 'true' : false
        };

        result.notes.push(note);
    }

    return result;
}

function parseCsvLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            if (inQuotes && line[i + 1] === '"') {
                current += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
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

function parseTags(tags) {
    if (Array.isArray(tags)) {
        return tags.filter(t => t && t.trim());
    }
    if (typeof tags === 'string') {
        return tags.split(',').map(t => t.trim()).filter(t => t);
    }
    return [];
}

async function confirmBulkImport() {
    if (pendingImportNotes.length === 0) return;

    const confirmBtn = document.getElementById('import-confirm-btn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Importing...';

    try {
        const response = await api.post('api/notes.php?action=bulk_import', {
            notes: pendingImportNotes,
            csrf_token: CSRF_TOKEN
        });

        if (response.success) {
            const { created, errors } = response.data;
            let message = `Imported ${created} notes`;
            if (errors.length > 0) {
                message += ` (${errors.length} errors)`;
            }
            showToast(message, errors.length > 0 ? 'warning' : 'success');

            closeModal();
            loadNotes();
            loadTags();
        }
    } catch (error) {
        showToast('Failed to import notes', 'error');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Import Notes';
    }
}
</script>

