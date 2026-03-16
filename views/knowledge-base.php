<?php
/**
 * Knowledge Base Page
 *
 * Folder-based knowledge base for organizing and viewing markdown and XML files.
 */

// Load page data
$currentPage = 'knowledge-base';
$pageTitle = 'Knowledge Base';
?>
<div class="kb-container flex h-screen bg-gray-50" id="kb-app">
    <!-- Sidebar: Folder Tree -->
    <aside class="kb-sidebar w-64 bg-white border-r border-gray-200 flex flex-col">
        <!-- Sidebar Header -->
        <div class="kb-sidebar-header p-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-gray-900">Folders</h2>
                <button id="kb-new-folder-btn" class="kb-new-folder-btn p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="New Folder">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Folder Tree -->
        <div class="kb-folder-tree flex-1 overflow-y-auto p-2" id="kb-folder-tree">
            <!-- Folders will be loaded here -->
            <div class="kb-loading text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                <p class="text-gray-500 mt-2">Loading folders...</p>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="kb-main flex-1 flex flex-col overflow-hidden">
        <!-- Toolbar -->
        <div class="kb-toolbar bg-white border-b border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <!-- Breadcrumbs -->
                <div class="kb-breadcrumbs flex items-center space-x-2 text-sm" id="kb-breadcrumbs">
                    <span class="text-gray-500">Select a folder to begin</span>
                </div>

                <!-- Actions -->
                <div class="kb-actions flex items-center space-x-2">
                    <button id="kb-upload-btn" class="kb-upload-btn inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Upload
                    </button>
                    <div class="relative">
                        <input type="text" id="kb-search-input" placeholder="Search..." class="kb-search-input w-64 pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900">
                        <svg class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Search Results Dropdown -->
            <div id="kb-search-results" class="kb-search-results hidden absolute top-16 right-4 w-96 bg-white border border-gray-200 rounded-lg shadow-lg max-h-80 overflow-y-auto z-10">
                <!-- Search results will appear here -->
            </div>
        </div>

        <!-- Content Area -->
        <div class="kb-content flex-1 overflow-y-auto p-6" id="kb-content">
            <!-- Default: Welcome message -->
            <div id="kb-welcome" class="kb-welcome text-center py-16">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Welcome to Knowledge Base</h3>
                <p class="text-gray-500 max-w-md mx-auto">Create folders to organize your markdown and XML files. Upload files to get started.</p>
                <button id="kb-get-started-btn" class="mt-6 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Create First Folder
                </button>
            </div>

            <!-- File List: Hidden by default -->
            <div id="kb-file-list" class="kb-file-list hidden">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900" id="kb-current-folder-name">Files</h3>
                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                        <span id="kb-file-count">0 files</span>
                    </div>
                </div>
                <div id="kb-files-grid" class="kb-files-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Files will be loaded here -->
                </div>
                <div id="kb-empty-folder" class="kb-empty-folder hidden text-center py-12">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-500">No files in this folder</p>
                    <button class="mt-4 kb-upload-empty-btn text-gray-900 hover:text-gray-700 text-sm font-medium">
                        Upload files to get started
                    </button>
                </div>
            </div>

            <!-- File Viewer: Hidden by default -->
            <div id="kb-file-viewer" class="kb-file-viewer hidden">
                <div class="flex items-center justify-between mb-4">
                    <button id="kb-back-btn" class="kb-back-btn inline-flex items-center text-sm text-gray-600 hover:text-gray-900 transition-colors">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to folder
                    </button>
                    <div class="flex items-center space-x-2">
                        <button id="kb-view-raw-btn" class="kb-view-raw-btn px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded hover:bg-gray-200 transition-colors">
                            View Raw
                        </button>
                        <button id="kb-view-rendered-btn" class="kb-view-rendered-btn hidden px-3 py-1.5 text-xs font-medium text-white bg-gray-900 rounded hover:bg-gray-800 transition-colors">
                            View Rendered
                        </button>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-semibold text-gray-900" id="kb-file-title">filename.md</h3>
                        <p class="text-sm text-gray-500 mt-1" id="kb-file-meta">2 KB • Last modified 2 hours ago</p>
                    </div>
                    <div id="kb-file-content" class="kb-file-content p-6 overflow-auto max-h-[600px]">
                        <!-- File content will be rendered here -->
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Hidden file input for upload -->
<input type="file" id="kb-file-input" class="hidden" accept=".md,.xml" multiple>

<!-- Rename Modal -->
<div id="kb-rename-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Rename</h3>
            <input type="hidden" id="kb-rename-type" value="">
            <input type="hidden" id="kb-rename-id" value="">
            <input type="text" id="kb-rename-new-name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900" placeholder="Enter new name">
        </div>
        <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-3">
            <button onclick="closeRenameModal()" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</button>
            <button onclick="submitKbRename()" class="px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800">Rename</button>
        </div>
    </div>
</div>

<style>
/* Markdown rendering styles */
.kb-file-content.markdown h1 { font-size: 2em; font-weight: bold; margin: 0.67em 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.3em; }
.kb-file-content.markdown h2 { font-size: 1.5em; font-weight: bold; margin: 0.83em 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.3em; }
.kb-file-content.markdown h3 { font-size: 1.17em; font-weight: bold; margin: 1em 0; }
.kb-file-content.markdown h4 { font-size: 1em; font-weight: bold; margin: 1.33em 0; }
.kb-file-content.markdown p { margin: 1em 0; line-height: 1.6; }
.kb-file-content.markdown ul, .kb-file-content.markdown ol { margin: 1em 0; padding-left: 2em; }
.kb-file-content.markdown li { margin: 0.5em 0; }
.kb-file-content.markdown code { background: #f3f4f6; padding: 0.2em 0.4em; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 0.9em; }
.kb-file-content.markdown pre { background: #1f2937; color: #f9fafb; padding: 1em; border-radius: 6px; overflow-x: auto; margin: 1em 0; }
.kb-file-content.markdown pre code { background: none; padding: 0; color: inherit; }
.kb-file-content.markdown blockquote { border-left: 4px solid #d1d5db; padding-left: 1em; margin: 1em 0; color: #6b7280; }
.kb-file-content.markdown a { color: #111827; text-decoration: underline; }
.kb-file-content.markdown hr { border: none; border-top: 1px solid #e5e7eb; margin: 2em 0; }
.kb-file-content.markdown table { border-collapse: collapse; width: 100%; margin: 1em 0; }
.kb-file-content.markdown th, .kb-file-content.markdown td { border: 1px solid #e5e7eb; padding: 0.5em 1em; text-align: left; }
.kb-file-content.markdown th { background: #f9fafb; font-weight: bold; }
.kb-file-content.markdown img { max-width: 100%; height: auto; margin: 1em 0; }

/* XML syntax highlighting */
.kb-file-content.xml { font-family: 'Courier New', monospace; font-size: 0.9em; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word; }
.kb-file-content.xml .xml-tag { color: #22863a; }
.kb-file-content.xml .xml-attr-name { color: #6f42c1; }
.kb-file-content.xml .xml-attr-value { color: #032f62; }
.kb-file-content.xml .xml-comment { color: #6a737d; font-style: italic; }
.kb-file-content.xml .xml-cdata { color: #032f62; }

/* Folder tree styles */
.kb-folder-item { cursor: pointer; user-select: none; }
.kb-folder-item:hover .kb-folder-name { color: #111827; }
.kb-folder-item.active { background: #f3f4f6; border-radius: 6px; }
.kb-folder-item.active .kb-folder-name { color: #111827; font-weight: 500; }
.kb-folder-children { margin-left: 1rem; }

/* File card styles */
.kb-file-card { cursor: pointer; transition: all 0.2s; }
.kb-file-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
.kb-file-card .file-icon-md { color: #0891b2; }
.kb-file-card .file-icon-xml { color: #ea580c; }

/* Drag and drop */
.kb-drag-over { background: #f3f4f6 !important; border: 2px dashed #6b7280 !important; }
</style>

<script>
(function() {
    'use strict';

    // State
    const state = {
        folders: [],
        files: [],
        currentFolderId: null,
        currentFile: null,
        treeExpanded: JSON.parse(localStorage.getItem('kbTreeExpanded') || '{}'),
        rawView: false
    };

    // DOM Elements
    const elements = {
        folderTree: document.getElementById('kb-folder-tree'),
        content: document.getElementById('kb-content'),
        breadcrumbs: document.getElementById('kb-breadcrumbs'),
        welcome: document.getElementById('kb-welcome'),
        fileList: document.getElementById('kb-file-list'),
        fileViewer: document.getElementById('kb-file-viewer'),
        filesGrid: document.getElementById('kb-files-grid'),
        emptyFolder: document.getElementById('kb-empty-folder'),
        fileContent: document.getElementById('kb-file-content'),
        fileTitle: document.getElementById('kb-file-title'),
        fileMeta: document.getElementById('kb-file-meta'),
        searchInput: document.getElementById('kb-search-input'),
        searchResults: document.getElementById('kb-search-results'),
        fileInput: document.getElementById('kb-file-input')
    };

    // ============================================================================
    // API CALLS
    // ============================================================================

    async function apiCall(action, method = 'GET', data = null, queryParams = {}) {
        const url = new URL(`${APP_URL}/api/knowledge-base.php`);
        url.searchParams.append('action', action);
        Object.entries(queryParams).forEach(([k, v]) => url.searchParams.append(k, v));

        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify({...data, csrf_token: CSRF_TOKEN});
        } else if (data && method === 'DELETE') {
            options.body = JSON.stringify({...data, csrf_token: CSRF_TOKEN});
        }

        try {
            const response = await fetch(url.toString(), options);

            // Check if response is OK before parsing JSON
            if (!response.ok) {
                console.error('API call failed:', response.status, response.statusText);
                return { success: false, error: `Server error: ${response.status} ${response.statusText}` };
            }

            // Check if response is JSON before parsing
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON response:', text.substring(0, 200));
                return { success: false, error: 'Server returned non-JSON response. Check server logs.' };
            }

            const result = await response.json();
            return result;
        } catch (error) {
            console.error('API call failed:', error);
            return { success: false, error: error.message };
        }
    }

    // ============================================================================
    // FOLDER TREE
    // ============================================================================

    async function loadFolders() {
        const result = await apiCall('list_folders');
        if (result.success) {
            state.folders = result.data.folders || [];
            renderFolderTree();
        } else {
            showToast('Failed to load folders', 'error');
        }
    }

    function renderFolderTree() {
        if (state.folders.length === 0) {
            elements.folderTree.innerHTML = `
                <div class="text-center py-8">
                    <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                    <p class="text-gray-500 text-sm">No folders yet</p>
                    <button class="mt-2 text-gray-900 hover:text-gray-700 text-sm font-medium" onclick="showCreateFolderModal()">
                        Create your first folder
                    </button>
                </div>
            `;
            return;
        }

        const rootFolders = state.folders.filter(f => !f.parentId);
        elements.folderTree.innerHTML = rootFolders.map(folder => renderFolder(folder)).join('');
    }

    function renderFolder(folder) {
        const isExpanded = state.treeExpanded[folder.id];
        const children = state.folders.filter(f => f.parentId === folder.id);
        const isActive = state.currentFolderId === folder.id;

        let html = `
            <div class="kb-folder-item mb-1" data-folder-id="${folder.id}">
                <div class="flex items-center px-3 py-2 rounded-lg group ${isActive ? 'active' : ''}" onclick="handleFolderClick('${folder.id}')">
                    ${children.length > 0 ? `
                        <svg class="w-4 h-4 mr-2 text-gray-400 transition-transform ${isExpanded ? 'rotate-90' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    ` : '<span class="w-4 h-4 mr-2"></span>'}
                    <svg class="w-4 h-4 mr-2 ${isExpanded ? 'text-gray-900' : 'text-gray-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${isExpanded ? `
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"></path>
                        ` : `
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        `}
                    </svg>
                    <span class="kb-folder-name text-sm flex-1 truncate">${escapeHtml(folder.name)}</span>
                    <span class="text-xs text-gray-400 mr-2">${folder.fileCount || 0}</span>
                    <button class="text-gray-400 hover:text-black p-1 rounded hover:bg-gray-100 transition-colors opacity-0 group-hover:opacity-100" onclick="event.stopPropagation(); renameKbFolder('${folder.id}', '${escapeHtml(folder.name).replace(/'/g, "\\'")}')" title="Rename folder">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </button>
                    <button class="text-gray-400 hover:text-red-600 p-1 rounded hover:bg-gray-100 transition-colors" onclick="event.stopPropagation(); confirmDeleteFolder('${folder.id}', '${escapeHtml(folder.name).replace(/'/g, "\\'")}')" title="Delete folder">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
        `;

        if (children.length > 0 && isExpanded) {
            html += `<div class="kb-folder-children">${children.map(f => renderFolder(f)).join('')}</div>`;
        }

        html += `</div>`;
        return html;
    }

    function handleFolderClick(folderId) {
        const folder = state.folders.find(f => f.id === folderId);
        if (!folder) return;

        // Toggle expansion if has children
        const children = state.folders.filter(f => f.parentId === folderId);
        if (children.length > 0) {
            state.treeExpanded[folderId] = !state.treeExpanded[folderId];
            localStorage.setItem('kbTreeExpanded', JSON.stringify(state.treeExpanded));
            renderFolderTree();
        }

        // Select folder
        state.currentFolderId = folderId;
        state.currentFile = null;
        renderFolderTree();
        loadFiles(folderId);
    }

    // ============================================================================
    // FILES
    // ============================================================================

    async function loadFiles(folderId) {
        const result = await apiCall('list_files', 'GET', null, { folderId });
        if (result.success) {
            state.files = result.data.files || [];
            showFileList();
        } else {
            showToast('Failed to load files', 'error');
        }
    }

    function showFileList() {
        elements.welcome.classList.add('hidden');
        elements.fileViewer.classList.add('hidden');
        elements.fileList.classList.remove('hidden');

        const folder = state.folders.find(f => f.id === state.currentFolderId);
        document.getElementById('kb-current-folder-name').textContent = folder?.name || 'Files';
        document.getElementById('kb-file-count').textContent = `${state.files.length} file${state.files.length !== 1 ? 's' : ''}`;

        updateBreadcrumbs();

        if (state.files.length === 0) {
            elements.filesGrid.classList.add('hidden');
            elements.emptyFolder.classList.remove('hidden');
        } else {
            elements.emptyFolder.classList.add('hidden');
            elements.filesGrid.classList.remove('hidden');
            elements.filesGrid.innerHTML = state.files.map(file => renderFileCard(file)).join('');
        }
    }

    function renderFileCard(file) {
        const iconColor = file.type === 'markdown' ? 'file-icon-md' : 'file-icon-xml';
        const iconSvg = file.type === 'markdown' ? `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        ` : `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
        `;

        const size = formatFileSize(file.size);
        const modified = timeAgo(file.updatedAt);

        return `
            <div class="kb-file-card bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-all cursor-pointer" onclick="viewFile('${file.id}')">
                <div class="flex items-start justify-between mb-3">
                    <svg class="w-8 h-8 ${iconColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${iconSvg}
                    </svg>
                    <div class="relative flex items-center gap-1">
                        <button class="kb-file-rename-btn p-1 text-gray-400 hover:text-black hover:bg-gray-100 rounded" onclick="event.stopPropagation(); renameKbFile('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')" title="Rename file">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button class="kb-file-delete-btn p-1 text-gray-400 hover:text-red-600 hover:bg-gray-100 rounded" onclick="event.stopPropagation(); confirmDeleteFile('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')" title="Delete file">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <h4 class="font-medium text-gray-900 truncate mb-1">${escapeHtml(file.name)}</h4>
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>${size}</span>
                    <span>${modified}</span>
                </div>
            </div>
        `;
    }

    async function viewFile(fileId) {
        const result = await apiCall('get_file', 'GET', null, { id: fileId });
        if (result.success) {
            state.currentFile = result.data.file;
            showFileViewer();
        } else {
            showToast('Failed to load file', 'error');
        }
    }

    function showFileViewer() {
        elements.welcome.classList.add('hidden');
        elements.fileList.classList.add('hidden');
        elements.fileViewer.classList.remove('hidden');

        const file = state.currentFile;
        elements.fileTitle.textContent = file.name;
        elements.fileMeta.textContent = `${formatFileSize(file.size)} • Last modified ${timeAgo(file.updatedAt)}`;

        // Reset view mode
        state.rawView = false;
        updateViewButtons();

        renderFileContent();
    }

    function renderFileContent() {
        const file = state.currentFile;
        const content = decodeBase64Utf8(file.content);

        if (state.rawView) {
            // Raw view
            elements.fileContent.className = 'kb-file-content p-6 overflow-auto max-h-[600px] bg-gray-50';
            elements.fileContent.innerHTML = `<pre class="text-sm">${escapeHtml(content)}</pre>`;
        } else {
            if (file.type === 'markdown') {
                renderMarkdown(content);
            } else {
                renderXML(content);
            }
        }
    }

    /**
     * Decode base64 content with proper UTF-8 handling
     * atob() returns binary string, but for UTF-8 we need proper decoding
     */
    function decodeBase64Utf8(base64String) {
        // Decode base64 to binary string
        const binaryString = atob(base64String);

        // Convert binary string to bytes
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }

        // Decode bytes as UTF-8
        return new TextDecoder('utf-8').decode(bytes);
    }

    async function renderMarkdown(markdown) {
        elements.fileContent.className = 'kb-file-content markdown p-6';

        // First escape HTML special characters to prevent rendering issues
        let html = markdown
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // Then apply markdown transformations (order matters!)
        html = html
            // Code blocks first (must be before other transformations)
            .replace(/```([\s\S]*?)```/gim, '<pre><code>$1</code></pre>')
            // Inline code
            .replace(/`([^`]+)`/gim, '<code>$1</code>')
            // Headers
            .replace(/^### (.*$)/gim, '<h3>$1</h3>')
            .replace(/^## (.*$)/gim, '<h2>$1</h2>')
            .replace(/^# (.*$)/gim, '<h1>$1</h1>')
            // Bold
            .replace(/\*\*([^*]+)\*\*/gim, '<strong>$1</strong>')
            // Italic
            .replace(/\*([^*]+)\*/gim, '<em>$1</em>')
            // Links
            .replace(/\[([^\]]+)\]\(([^)]+)\)/gim, '<a href="$2" target="_blank">$1</a>')
            // Unordered lists
            .replace(/^\* (.*$)/gim, '<ul><li>$1</li></ul>')
            .replace(/<\/ul>\n<ul>/gim, '')
            // Ordered lists
            .replace(/^\d+\. (.*$)/gim, '<ol><li>$1</li></ol>')
            .replace(/<\/ol>\n<ol>/gim, '')
            // Horizontal rules
            .replace(/^---$/gim, '<hr>')
            // Line breaks and paragraphs
            .replace(/\n\n/gim, '</p><p>')
            .replace(/\n/gim, '<br>');

        elements.fileContent.innerHTML = `<div>${html}</div>`;
    }

    function renderXML(xml) {
        elements.fileContent.className = 'kb-file-content xml p-6';
        const highlighted = highlightXML(xml);
        elements.fileContent.innerHTML = highlighted;
    }

    function highlightXML(xml) {
        return xml
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/(&lt;!--[\s\S]*?--&gt;)/g, '<span class="xml-comment">$1</span>')
            .replace(/(&lt;\/?)([\w-]+)/g, '$1<span class="xml-tag">$2</span>')
            .replace(/([\w-]+)=(&quot;[^&]*&quot;)/g, '<span class="xml-attr-name">$1</span>=$2')
            .replace(/(&quot;[^&]*&quot;)/g, '<span class="xml-attr-value">$1</span>');
    }

    function toggleViewMode() {
        state.rawView = !state.rawView;
        updateViewButtons();
        renderFileContent();
    }

    function updateViewButtons() {
        const rawBtn = document.getElementById('kb-view-raw-btn');
        const renderedBtn = document.getElementById('kb-view-rendered-btn');

        if (state.rawView) {
            rawBtn.classList.add('hidden');
            renderedBtn.classList.remove('hidden');
        } else {
            rawBtn.classList.remove('hidden');
            renderedBtn.classList.add('hidden');
        }
    }

    function backToFileList() {
        state.currentFile = null;
        showFileList();
    }

    // ============================================================================
    // CREATE FOLDER
    // ============================================================================

    function showCreateFolderModal() {
        const modalHtml = `
            <div id="kb-modal-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Create New Folder</h3>
                        <input type="text" id="kb-folder-name-input" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-gray-900" placeholder="Folder name (letters, numbers, hyphens, underscores only)" maxlength="100">
                        <p class="text-xs text-gray-500 mt-2">URL-safe characters only: a-z, 0-9, hyphens, underscores</p>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-end space-x-3">
                        <button onclick="closeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900">Cancel</button>
                        <button onclick="createFolder()" class="px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800">Create</button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        setTimeout(() => document.getElementById('kb-folder-name-input').focus(), 100);
    }

    async function createFolder() {
        const input = document.getElementById('kb-folder-name-input');
        const name = input.value.trim();

        if (!name) {
            showToast('Please enter a folder name', 'error');
            return;
        }

        if (!/^[a-zA-Z0-9_-]+$/.test(name)) {
            showToast('Folder name must contain only letters, numbers, hyphens, and underscores', 'error');
            return;
        }

        const result = await apiCall('create_folder', 'POST', { name, parentId: state.currentFolderId || null });
        if (result.success) {
            showToast('Folder created', 'success');
            closeModal();
            loadFolders();
        } else {
            showToast(result.error || 'Failed to create folder', 'error');
        }
    }

    // ============================================================================
    // FILE UPLOAD
    // ============================================================================

    function handleFileUpload(files) {
        if (!files || files.length === 0) return;

        if (!state.currentFolderId) {
            showToast('Please select a folder first', 'error');
            return;
        }

        uploadFiles(Array.from(files));
    }

    async function uploadFiles(files) {
        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('folderId', state.currentFolderId);
            formData.append('csrf_token', CSRF_TOKEN);

            try {
                const response = await fetch(`${APP_URL}/api/knowledge-base.php?action=upload_file`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showToast(`Uploaded ${file.name}`, 'success');
                } else {
                    showToast(`Failed to upload ${file.name}: ${result.error}`, 'error');
                }
            } catch (error) {
                showToast(`Failed to upload ${file.name}`, 'error');
            }
        }

        // Refresh files
        if (state.currentFolderId) {
            loadFiles(state.currentFolderId);
            loadFolders(); // Update file counts
        }
    }

    // ============================================================================
    // DELETE OPERATIONS
    // ============================================================================

    function showDeleteConfirmModal(title, message, onConfirm) {
        const modalHtml = `
            <div id="kb-delete-modal-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                    <div class="p-6">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-center text-gray-900 mb-2">${title}</h3>
                        <p class="text-sm text-center text-gray-500">${message}</p>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 rounded-b-lg flex justify-center space-x-3">
                        <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button id="kb-confirm-delete-btn" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        document.getElementById('kb-confirm-delete-btn').onclick = () => {
            onConfirm();
            closeDeleteModal();
        };
    }

    function closeDeleteModal() {
        const overlay = document.getElementById('kb-delete-modal-overlay');
        if (overlay) overlay.remove();
    }

    // --- Folder Deletion ---

    function confirmDeleteFolder(folderId, folderName) {
        showDeleteConfirmModal(
            'Delete Folder',
            `Are you sure you want to delete the folder "${folderName}"? This action cannot be undone.`,
            () => deleteFolder(folderId)
        );
    }

    async function deleteFolder(folderId) {
        const result = await apiCall('delete_folder', 'DELETE', { id: folderId });
        if (result.success) {
            showToast('Folder deleted', 'success');

            // If deleting current folder, go back to root
            if (state.currentFolderId === folderId) {
                state.currentFolderId = null;
                showWelcome();
            }

            loadFolders();
        } else {
            showToast(result.error || 'Failed to delete folder', 'error');
        }
    }

    function showWelcome() {
        elements.fileList.classList.add('hidden');
        elements.fileViewer.classList.add('hidden');
        elements.welcome.classList.remove('hidden');
        elements.breadcrumbs.innerHTML = '<span class="text-gray-500">Select a folder to begin</span>';
    }

    // --- File Deletion ---

    function confirmDeleteFile(fileId, fileName) {
        showDeleteConfirmModal(
            'Delete File',
            `Are you sure you want to delete the file "${fileName}"? This action cannot be undone.`,
            () => deleteFile(fileId)
        );
    }

    async function deleteFile(fileId) {
        const result = await apiCall('delete_file', 'DELETE', { id: fileId });
        if (result.success) {
            showToast('File deleted', 'success');

            // If deleting current viewed file, go back to list
            if (state.currentFile && state.currentFile.id === fileId) {
                backToFileList();
            }

            // Refresh file list and folders (to update counts)
            if (state.currentFolderId) {
                loadFiles(state.currentFolderId);
                loadFolders();
            }
        } else {
            showToast(result.error || 'Failed to delete file', 'error');
        }
    }

    // ============================================================================
    // RENAME OPERATIONS
    // ============================================================================

    function renameKbFile(fileId, currentName) {
        document.getElementById('kb-rename-type').value = 'file';
        document.getElementById('kb-rename-id').value = fileId;
        document.getElementById('kb-rename-new-name').value = currentName;
        document.getElementById('kb-rename-modal').classList.remove('hidden');
        setTimeout(() => document.getElementById('kb-rename-new-name').focus(), 100);
    }

    function renameKbFolder(folderId, currentName) {
        document.getElementById('kb-rename-type').value = 'folder';
        document.getElementById('kb-rename-id').value = folderId;
        document.getElementById('kb-rename-new-name').value = currentName;
        document.getElementById('kb-rename-modal').classList.remove('hidden');
        setTimeout(() => document.getElementById('kb-rename-new-name').focus(), 100);
    }

    function closeRenameModal() {
        document.getElementById('kb-rename-modal').classList.add('hidden');
    }

    async function submitKbRename() {
        const type = document.getElementById('kb-rename-type').value;
        const id = document.getElementById('kb-rename-id').value;
        const newName = document.getElementById('kb-rename-new-name').value.trim();

        if (!newName) {
            showToast('Name cannot be empty', 'error');
            return;
        }

        const action = type === 'file' ? 'update_file' : 'update_folder';
        const result = await apiCall(action, 'PUT', { id, name: newName });

        if (result.success) {
            showToast(`${type === 'file' ? 'File' : 'Folder'} renamed successfully`, 'success');
            closeRenameModal();
            // Refresh
            loadFolders();
            if (state.currentFolderId) {
                loadFiles(state.currentFolderId);
            }
        } else {
            showToast(result.error || 'Rename failed', 'error');
        }
    }

    // ============================================================================
    // SEARCH
    // ============================================================================

    let searchTimeout;
    elements.searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => handleSearch(e.target.value), 300);
    });

    async function handleSearch(query) {
        if (!query.trim()) {
            elements.searchResults.classList.add('hidden');
            return;
        }

        const result = await apiCall('search', 'GET', null, { q: query });
        if (result.success && result.data.results.length > 0) {
            renderSearchResults(result.data.results);
        } else {
            elements.searchResults.classList.add('hidden');
        }
    }

    function renderSearchResults(results) {
        elements.searchResults.innerHTML = results.map(item => `
            <div class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0" onclick="navigateToResult('${item.type}', '${item.id}')">
                <div class="flex items-center">
                    <svg class="w-4 h-4 mr-3 ${item.type === 'folder' ? 'text-gray-900' : 'text-gray-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${item.type === 'folder' ? `
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                        ` : `
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        `}
                    </svg>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">${escapeHtml(item.name)}</p>
                        <p class="text-xs text-gray-500 truncate">${escapeHtml(item.path)}</p>
                    </div>
                </div>
            </div>
        `).join('');
        elements.searchResults.classList.remove('hidden');
    }

    function navigateToResult(type, id) {
        elements.searchResults.classList.add('hidden');
        elements.searchInput.value = '';

        if (type === 'folder') {
            state.currentFolderId = id;
            state.currentFile = null;
            renderFolderTree();
            loadFiles(id);
        } else {
            viewFile(id);
        }
    }

    // ============================================================================
    // BREADCRUMBS
    // ============================================================================

    function updateBreadcrumbs() {
        if (!state.currentFolderId) {
            elements.breadcrumbs.innerHTML = '<span class="text-gray-500">Select a folder to begin</span>';
            return;
        }

        const path = buildFolderPath(state.currentFolderId);
        const crumbs = path.map((folder, index) => {
            const isLast = index === path.length - 1;
            return isLast
                ? `<span class="text-gray-900 font-medium">${escapeHtml(folder.name)}</span>`
                : `<button class="text-gray-900 hover:text-gray-700" onclick="navigateToFolder('${folder.id}')">${escapeHtml(folder.name)}</button>`;
        });

        elements.breadcrumbs.innerHTML = crumbs.join('<svg class="w-4 h-4 text-gray-400 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>');
    }

    function buildFolderPath(folderId) {
        const path = [];
        let current = state.folders.find(f => f.id === folderId);

        while (current) {
            path.unshift(current);
            current = state.folders.find(f => f.id === current.parentId);
        }

        return path;
    }

    function navigateToFolder(folderId) {
        state.currentFolderId = folderId;
        state.currentFile = null;
        renderFolderTree();
        loadFiles(folderId);
    }

    // ============================================================================
    // DRAG AND DROP
    // ============================================================================

    function initDragDrop() {
        const container = document.getElementById('kb-app');

        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            container.classList.add('kb-drag-over');
        });

        container.addEventListener('dragleave', (e) => {
            if (e.target === container) {
                container.classList.remove('kb-drag-over');
            }
        });

        container.addEventListener('drop', (e) => {
            e.preventDefault();
            container.classList.remove('kb-drag-over');
            handleFileUpload(e.dataTransfer.files);
        });
    }

    // ============================================================================
    // UTILITIES
    // ============================================================================

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function timeAgo(dateString) {
        const date = new Date(dateString);
        const seconds = Math.floor((new Date() - date) / 1000);

        const intervals = {
            year: 31536000,
            month: 2592000,
            week: 604800,
            day: 86400,
            hour: 3600,
            minute: 60
        };

        for (const [unit, secondsInUnit] of Object.entries(intervals)) {
            const interval = Math.floor(seconds / secondsInUnit);
            if (interval >= 1) {
                return `${interval} ${unit}${interval > 1 ? 's' : ''} ago`;
            }
        }

        return 'Just now';
    }

    function closeModal() {
        const overlay = document.getElementById('kb-modal-overlay');
        if (overlay) overlay.remove();
    }

    // ============================================================================
    // EVENT LISTENERS
    // ============================================================================

    // New folder button
    document.getElementById('kb-new-folder-btn').addEventListener('click', showCreateFolderModal);
    document.getElementById('kb-get-started-btn').addEventListener('click', showCreateFolderModal);

    // Upload buttons
    document.getElementById('kb-upload-btn').addEventListener('click', () => elements.fileInput.click());
    document.querySelector('.kb-upload-empty-btn')?.addEventListener('click', () => elements.fileInput.click());
    elements.fileInput.addEventListener('change', (e) => handleFileUpload(e.target.files));

    // Back button
    document.getElementById('kb-back-btn').addEventListener('click', backToFileList);

    // View mode buttons
    document.getElementById('kb-view-raw-btn').addEventListener('click', toggleViewMode);
    document.getElementById('kb-view-rendered-btn').addEventListener('click', toggleViewMode);

    // Rename modal - Enter key support
    document.getElementById('kb-rename-new-name').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            submitKbRename();
        }
    });

    // Close search results when clicking outside
    document.addEventListener('click', (e) => {
        if (!elements.searchInput.contains(e.target) && !elements.searchResults.contains(e.target)) {
            elements.searchResults.classList.add('hidden');
        }
    });

    // ============================================================================
    // INITIALIZATION
    // ============================================================================

    function init() {
        loadFolders();
        initDragDrop();
    }

    // Expose functions globally for inline onclick handlers
    window.handleFolderClick = handleFolderClick;
    window.viewFile = viewFile;
    window.showCreateFolderModal = showCreateFolderModal;
    window.createFolder = createFolder;
    window.closeModal = closeModal;
    window.navigateToFolder = navigateToFolder;
    window.navigateToResult = navigateToResult;
    window.toggleViewMode = toggleViewMode;
    window.confirmDeleteFolder = confirmDeleteFolder;
    window.confirmDeleteFile = confirmDeleteFile;
    window.closeDeleteModal = closeDeleteModal;
    window.renameKbFile = renameKbFile;
    window.renameKbFolder = renameKbFolder;
    window.closeRenameModal = closeRenameModal;
    window.submitKbRename = submitKbRename;

    init();
})();
</script>
