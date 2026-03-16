<?php
/**
 * Mobile Knowledge Base Page
 *
 * Mobile-first implementation aligned to sample/mobileknowledgebase.md
 * and function parity with desktop knowledge base core features.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title>Knowledge Base - <?= htmlspecialchars($siteName) ?></title>

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
                primary: "#000000",
                "background-light": "#ffffff"
            },
            fontFamily: {
                display: ["Inter", "sans-serif"]
            }
        }
    }
}
</script>
<style type="text/tailwindcss">
    @layer base {
        body {
            @apply bg-gray-50 text-black font-display antialiased;
        }
    }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .mono-card { @apply border border-black bg-white p-4; }
    .file-row:last-child { border-bottom: none; }
    .kb-prose h1 { @apply text-2xl font-black mt-3 mb-2; }
    .kb-prose h2 { @apply text-xl font-black mt-3 mb-2; }
    .kb-prose h3 { @apply text-lg font-bold mt-3 mb-2; }
    .kb-prose p { @apply text-sm leading-6 my-2; }
    .kb-prose ul { @apply list-disc pl-5 my-2 text-sm; }
    .kb-prose ol { @apply list-decimal pl-5 my-2 text-sm; }
    .kb-prose code { @apply bg-gray-100 px-1 py-0.5 text-xs font-mono; }
    .kb-prose pre { @apply bg-gray-900 text-gray-100 p-3 overflow-x-auto text-xs my-2; }
    .kb-prose blockquote { @apply border-l-4 border-gray-300 pl-3 text-gray-600 my-2; }
</style>
</head>
<body class="flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white shadow-2xl flex flex-col border-x border-gray-100 overflow-hidden">

<?php
$title = 'Knowledge Base';
$leftAction = 'menu';
$rightAction = 'none';
include MOBILE_VIEW_PATH . '/partials/header-mobile.php';
?>

<main class="flex-1 overflow-y-auto no-scrollbar pb-32">
    <!-- Overview Section -->
    <section id="kb-overview-section">
        <div class="px-4 pt-2 pb-4 bg-white sticky top-0 z-10 border-b border-gray-100">
            <h1 class="text-3xl font-black tracking-tighter uppercase leading-none mb-4">Knowledge Base</h1>
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input id="kb-search-input" type="text" placeholder="Search folders and files..." class="w-full border border-black rounded-none py-2.5 pl-9 pr-3 text-sm focus:ring-0 focus:border-black placeholder:text-gray-300">
            </div>
            <div id="kb-search-results" class="hidden mt-2 border border-black bg-white max-h-52 overflow-y-auto no-scrollbar"></div>
        </div>

        <section class="px-4 mt-4 mb-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Directories</h2>
                <button onclick="openCreateFolderDialog()" class="text-[10px] font-black uppercase tracking-widest border-b border-black">New Folder</button>
            </div>
            <div id="kb-folder-list" class="space-y-2">
                <div class="mono-card text-sm text-gray-500">Loading folders...</div>
            </div>
        </section>

        <section class="px-4 mb-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Recent Files</h2>
                <span class="text-[10px] font-bold text-black border-b border-black">Latest</span>
            </div>
            <div class="border-t border-gray-100" id="kb-recent-files">
                <div class="file-row py-4 text-sm text-gray-500 border-b border-gray-100">Loading files...</div>
            </div>
        </section>
    </section>

    <!-- Folder Section -->
    <section id="kb-folder-section" class="hidden">
        <div class="px-4 py-4 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2 min-w-0">
                    <button onclick="showOverview()" class="p-1 border border-black hover:bg-black hover:text-white transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <h2 id="kb-folder-title" class="text-lg font-bold tracking-tight truncate">Folder</h2>
                </div>
                <span id="kb-folder-count" class="text-[10px] font-bold uppercase tracking-widest text-gray-400">0 Files</span>
            </div>
            <p id="kb-folder-path" class="text-[10px] text-gray-400 uppercase tracking-widest ml-8">Root</p>
        </div>

        <div class="p-4 space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <button onclick="triggerFileUpload()" class="w-full bg-black text-white py-3 flex items-center justify-center gap-2 hover:bg-neutral-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <span class="text-[10px] font-black uppercase tracking-widest">Upload</span>
                </button>
                <button onclick="openCreateFolderDialog(true)" class="w-full border border-black text-black py-3 flex items-center justify-center gap-2 hover:bg-gray-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <span class="text-[10px] font-black uppercase tracking-widest">Subfolder</span>
                </button>
            </div>

            <div class="space-y-px bg-black border border-black" id="kb-folder-files"></div>
        </div>
    </section>

    <!-- Viewer Section -->
    <section id="kb-viewer-section" class="hidden px-4 py-4">
        <div class="flex items-center justify-between mb-3">
            <button onclick="backToFolder()" class="inline-flex items-center text-xs font-bold uppercase tracking-widest border-b border-black">
                Back
            </button>
            <div class="flex items-center gap-2">
                <button id="kb-view-rendered-btn" onclick="setRawMode(false)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest border border-black bg-black text-white">Rendered</button>
                <button id="kb-view-raw-btn" onclick="setRawMode(true)" class="px-2 py-1 text-[10px] font-black uppercase tracking-widest border border-black">Raw</button>
            </div>
        </div>
        <div class="mono-card">
            <div class="border-b border-gray-100 pb-2 mb-3">
                <h3 id="kb-viewer-title" class="text-sm font-black uppercase tracking-tight">File</h3>
                <p id="kb-viewer-meta" class="text-[10px] text-gray-400 uppercase tracking-widest mt-1">--</p>
            </div>
            <div id="kb-viewer-content" class="text-sm text-gray-800"></div>
        </div>
    </section>
</main>

<button onclick="openCreateFolderDialog()" class="fixed bottom-28 right-[calc(50%-180px)] w-12 h-12 bg-black text-white rounded-full flex items-center justify-center shadow-2xl active:scale-95 transition-transform z-40">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
</button>

<?php
$activePage = 'settings';
include MOBILE_VIEW_PATH . '/partials/bottom-nav.php';
?>
<?php include MOBILE_VIEW_PATH . '/partials/offcanvas-menu.php'; ?>
<div class="absolute bottom-2 left-1/2 -translate-x-1/2 w-12 h-1 bg-gray-200 rounded-full z-40"></div>

</div>

<input id="kb-file-input" type="file" accept=".md,.xml" class="hidden">

<script>
    const APP_URL = '<?= htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') ?>';
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
</script>
<script src="<?= MOBILE_JS_URL ?>/mobile.js"></script>
<script>
if (window.Mobile && typeof Mobile.init === 'function') {
    Mobile.init();
}

const KB = {
    folders: [],
    filesByFolder: {},
    currentFolderId: null,
    currentFile: null,
    rawMode: false
};

function toast(message, type) {
    if (window.Mobile && Mobile.ui && typeof Mobile.ui.showToast === 'function') {
        Mobile.ui.showToast(message, type || 'info');
    } else {
        alert(message);
    }
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatBytes(bytes) {
    const size = Number(bytes || 0);
    if (size < 1024) return size + ' B';
    if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
    return (size / (1024 * 1024)).toFixed(1) + ' MB';
}

function timeAgo(isoDate) {
    if (!isoDate) return '--';
    const now = new Date();
    const past = new Date(isoDate);
    const diff = Math.floor((now - past) / 1000);
    if (diff < 60) return diff + 's ago';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

async function kbApi(action, method, payload, query) {
    const url = new URL(APP_URL + '/api/knowledge-base.php');
    url.searchParams.set('action', action);
    if (query && typeof query === 'object') {
        Object.keys(query).forEach((key) => {
            if (query[key] !== undefined && query[key] !== null) {
                url.searchParams.set(key, String(query[key]));
            }
        });
    }

    try {
        const options = {
            method: method || 'GET',
            headers: {}
        };

        if (payload && !(payload instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(Object.assign({}, payload, { csrf_token: CSRF_TOKEN }));
        } else if (payload instanceof FormData) {
            payload.append('csrf_token', CSRF_TOKEN);
            options.body = payload;
        }

        const response = await fetch(url.toString(), options);
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const raw = await response.text().catch(() => '');
            const preview = String(raw).replace(/\s+/g, ' ').trim().slice(0, 180);
            return { success: false, error: `Non-JSON response (${response.status})`, details: preview };
        }

        const result = await response.json();
        if (!response.ok && !result.success) {
            return { success: false, error: result.error || ('HTTP ' + response.status) };
        }
        return result;
    } catch (error) {
        return { success: false, error: error.message || 'Request failed' };
    }
}

async function loadFolders() {
    const result = await kbApi('list_folders', 'GET');
    if (!result.success) {
        toast(result.error || 'Failed to load folders', 'error');
        return;
    }

    KB.folders = Array.isArray(result.data && result.data.folders) ? result.data.folders : [];
    renderFolderList(KB.folders);
    await loadAllFiles();
    renderRecentFiles();
}

async function loadAllFiles() {
    KB.filesByFolder = {};
    const jobs = KB.folders.map((folder) => kbApi('list_files', 'GET', null, { folderId: folder.id }));
    const results = await Promise.all(jobs);
    KB.folders.forEach((folder, index) => {
        const res = results[index];
        KB.filesByFolder[folder.id] = (res && res.success && res.data && Array.isArray(res.data.files)) ? res.data.files : [];
    });
}

function renderFolderList(folders) {
    const container = document.getElementById('kb-folder-list');
    if (!folders.length) {
        container.innerHTML = '<div class="mono-card text-sm text-gray-500">No folders yet. Create your first folder.</div>';
        return;
    }

    container.innerHTML = folders.map((folder) => {
        return `
            <div class="group flex items-center justify-between p-4 border border-black bg-white hover:bg-black hover:text-white transition-colors">
                <button class="flex items-center gap-3 min-w-0 flex-1 text-left" onclick="openFolder('${folder.id}')">
                    <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="text-xs font-black uppercase tracking-widest truncate">${escapeHtml(folder.name)}</p>
                        <p class="text-[10px] opacity-70 uppercase tracking-widest">${folder.fileCount || 0} items</p>
                    </div>
                </button>
                <div class="flex items-center gap-1 pl-2">
                    <button onclick="renameFolder('${folder.id}', '${escapeHtml(folder.name).replace(/'/g, "\\'")}')" class="p-1 border border-current hover:bg-white hover:text-black transition-colors" title="Rename">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button onclick="deleteFolder('${folder.id}', '${escapeHtml(folder.name).replace(/'/g, "\\'")}')" class="p-1 border border-current hover:bg-white hover:text-red-600 transition-colors" title="Delete">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function getAllFiles() {
    return Object.values(KB.filesByFolder).flat();
}

function renderRecentFiles(files) {
    const list = files || getAllFiles();
    const container = document.getElementById('kb-recent-files');
    const sorted = list
        .slice()
        .sort((a, b) => new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0))
        .slice(0, 10);

    if (!sorted.length) {
        container.innerHTML = '<div class="file-row py-4 text-sm text-gray-500 border-b border-gray-100">No files uploaded yet.</div>';
        return;
    }

    container.innerHTML = sorted.map((file) => {
        return `
            <button class="file-row w-full text-left flex items-center justify-between py-4 border-b border-gray-100 hover:bg-gray-50 px-1 transition-colors"
                    onclick="openFile('${file.id}')">
                <div class="flex flex-col min-w-0">
                    <span class="text-xs font-bold uppercase tracking-tight truncate">${escapeHtml(file.name)}</span>
                    <span class="text-[9px] text-gray-400 font-mono mt-0.5 uppercase">${file.type || ''}</span>
                </div>
                <span class="text-[10px] font-mono text-gray-400">${timeAgo(file.updatedAt || file.createdAt)}</span>
            </button>
        `;
    }).join('');
}

function showOverview() {
    KB.currentFolderId = null;
    KB.currentFile = null;
    document.getElementById('kb-overview-section').classList.remove('hidden');
    document.getElementById('kb-folder-section').classList.add('hidden');
    document.getElementById('kb-viewer-section').classList.add('hidden');
}

function openFolder(folderId) {
    KB.currentFolderId = folderId;
    KB.currentFile = null;

    const folder = KB.folders.find((f) => f.id === folderId);
    const files = KB.filesByFolder[folderId] || [];

    document.getElementById('kb-overview-section').classList.add('hidden');
    document.getElementById('kb-viewer-section').classList.add('hidden');
    document.getElementById('kb-folder-section').classList.remove('hidden');

    document.getElementById('kb-folder-title').textContent = folder ? folder.name : 'Folder';
    document.getElementById('kb-folder-count').textContent = files.length + ' File' + (files.length === 1 ? '' : 's');
    document.getElementById('kb-folder-path').textContent = 'Root / ' + (folder ? folder.name : 'Folder');

    renderFolderFiles(files);
}

function renderFolderFiles(files) {
    const container = document.getElementById('kb-folder-files');
    if (!files.length) {
        container.innerHTML = '<div class="bg-white p-4 text-sm text-gray-500">No files in this folder.</div>';
        return;
    }

    container.innerHTML = files.map((file) => {
        const iconPath = file.type === 'xml'
            ? 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'
            : 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
        return `
            <div class="bg-white p-3 flex items-center gap-3">
                <div class="w-9 h-9 border border-black flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${iconPath}"/>
                    </svg>
                </div>
                <button class="flex-1 min-w-0 text-left" onclick="openFile('${file.id}')">
                    <h3 class="text-xs font-bold truncate">${escapeHtml(file.name)}</h3>
                    <p class="text-[9px] text-gray-400 uppercase tracking-tighter mt-1">${formatBytes(file.size)} · ${timeAgo(file.updatedAt || file.createdAt)}</p>
                </button>
                <div class="flex items-center gap-1">
                    <button onclick="renameFile('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')" class="p-1 border border-black hover:bg-gray-100 transition-colors" title="Rename">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>
                    <button onclick="deleteFile('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')" class="p-1 border border-black hover:bg-gray-100 transition-colors" title="Delete">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function decodeBase64(content) {
    try {
        return decodeURIComponent(
            Array.prototype.map.call(atob(content || ''), function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join('')
        );
    } catch (error) {
        return atob(content || '');
    }
}

async function openFile(fileId) {
    const result = await kbApi('get_file', 'GET', null, { id: fileId });
    if (!result.success) {
        toast(result.error || 'Failed to load file', 'error');
        return;
    }

    KB.currentFile = result.data && result.data.file ? result.data.file : null;
    KB.rawMode = false;

    if (!KB.currentFile) {
        toast('File not found', 'error');
        return;
    }

    document.getElementById('kb-overview-section').classList.add('hidden');
    document.getElementById('kb-folder-section').classList.add('hidden');
    document.getElementById('kb-viewer-section').classList.remove('hidden');

    document.getElementById('kb-viewer-title').textContent = KB.currentFile.name || 'File';
    document.getElementById('kb-viewer-meta').textContent = formatBytes(KB.currentFile.size) + ' · ' + timeAgo(KB.currentFile.updatedAt || KB.currentFile.createdAt);
    renderCurrentFile();
    updateViewButtons();
}

function updateViewButtons() {
    const renderedBtn = document.getElementById('kb-view-rendered-btn');
    const rawBtn = document.getElementById('kb-view-raw-btn');
    if (!KB.rawMode) {
        renderedBtn.classList.add('bg-black', 'text-white');
        rawBtn.classList.remove('bg-black', 'text-white');
    } else {
        rawBtn.classList.add('bg-black', 'text-white');
        renderedBtn.classList.remove('bg-black', 'text-white');
    }
}

function setRawMode(enabled) {
    KB.rawMode = !!enabled;
    updateViewButtons();
    renderCurrentFile();
}

function renderCurrentFile() {
    const target = document.getElementById('kb-viewer-content');
    if (!KB.currentFile) {
        target.innerHTML = '<p class="text-sm text-gray-500">No file selected.</p>';
        return;
    }

    const decoded = decodeBase64(KB.currentFile.content || '');
    if (KB.rawMode) {
        target.innerHTML = '<pre class="text-xs bg-gray-900 text-gray-100 p-3 overflow-x-auto">' + escapeHtml(decoded) + '</pre>';
        return;
    }

    if ((KB.currentFile.type || '').toLowerCase() === 'markdown') {
        target.innerHTML = renderMarkdown(decoded);
    } else {
        target.innerHTML = '<pre class="text-xs bg-gray-50 p-3 overflow-x-auto border border-gray-100">' + escapeHtml(decoded) + '</pre>';
    }
}

function renderMarkdown(text) {
    let html = escapeHtml(text || '');
    html = html.replace(/^### (.*)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.*)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.*)$/gm, '<h1>$1</h1>');
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/\n\n/g, '</p><p>');
    html = '<p>' + html + '</p>';
    html = html.replace(/<p><h([1-3])>/g, '<h$1>').replace(/<\/h([1-3])><\/p>/g, '</h$1>');
    return '<div class="kb-prose">' + html + '</div>';
}

function backToFolder() {
    if (KB.currentFolderId) {
        openFolder(KB.currentFolderId);
    } else {
        showOverview();
    }
}

function sanitizeFolderName(name) {
    return String(name || '').trim().replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_-]/g, '');
}

async function openCreateFolderDialog(useCurrentParent) {
    const raw = window.prompt('Folder name (letters/numbers/_/- only):');
    if (raw === null) return;
    const name = sanitizeFolderName(raw);
    if (!name) {
        toast('Invalid folder name.', 'warning');
        return;
    }
    const payload = { name: name };
    if (useCurrentParent && KB.currentFolderId) {
        payload.parentId = KB.currentFolderId;
    }
    const result = await kbApi('create_folder', 'POST', payload);
    if (!result.success) {
        toast(result.error || 'Failed to create folder', 'error');
        return;
    }
    toast('Folder created.', 'success');
    await loadFolders();
}

async function renameFolder(folderId, currentName) {
    const raw = window.prompt('Rename folder:', currentName || '');
    if (raw === null) return;
    const name = sanitizeFolderName(raw);
    if (!name) {
        toast('Invalid folder name.', 'warning');
        return;
    }
    const result = await kbApi('update_folder', 'PUT', { id: folderId, name: name });
    if (!result.success) {
        toast(result.error || 'Failed to rename folder', 'error');
        return;
    }
    toast('Folder renamed.', 'success');
    await loadFolders();
}

async function deleteFolder(folderId, folderName) {
    const message = 'Delete folder "' + folderName + '"?';
    const proceed = window.confirm(message);
    if (!proceed) return;

    const result = await kbApi('delete_folder', 'DELETE', { id: folderId });
    if (!result.success) {
        toast(result.error || 'Failed to delete folder', 'error');
        return;
    }
    toast('Folder deleted.', 'success');
    if (KB.currentFolderId === folderId) {
        showOverview();
    }
    await loadFolders();
}

function triggerFileUpload() {
    if (!KB.currentFolderId) {
        toast('Open a folder first.', 'warning');
        return;
    }
    const input = document.getElementById('kb-file-input');
    input.value = '';
    input.click();
}

async function handleFileUpload(event) {
    const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
    if (!file || !KB.currentFolderId) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('folderId', KB.currentFolderId);

    const result = await kbApi('upload_file', 'POST', formData);
    if (!result.success) {
        toast(result.error || 'Upload failed', 'error');
        return;
    }

    toast('File uploaded.', 'success');
    await loadFolders();
    if (KB.currentFolderId) {
        openFolder(KB.currentFolderId);
    }
}

async function renameFile(fileId, currentName) {
    const raw = window.prompt('Rename file (keep .md or .xml):', currentName || '');
    if (raw === null) return;
    const name = String(raw || '').trim();
    if (!name || !/\.(md|xml)$/i.test(name)) {
        toast('File must end with .md or .xml', 'warning');
        return;
    }
    const result = await kbApi('update_file', 'PUT', { id: fileId, name: name });
    if (!result.success) {
        toast(result.error || 'Failed to rename file', 'error');
        return;
    }
    toast('File renamed.', 'success');
    await loadFolders();
    if (KB.currentFolderId) openFolder(KB.currentFolderId);
}

async function deleteFile(fileId, fileName) {
    const proceed = window.confirm('Delete file "' + fileName + '"?');
    if (!proceed) return;
    const result = await kbApi('delete_file', 'DELETE', { id: fileId });
    if (!result.success) {
        toast(result.error || 'Failed to delete file', 'error');
        return;
    }
    toast('File deleted.', 'success');
    await loadFolders();
    if (KB.currentFolderId) openFolder(KB.currentFolderId);
}

async function runSearch(query) {
    const q = String(query || '').trim();
    const box = document.getElementById('kb-search-results');
    if (!q) {
        box.classList.add('hidden');
        box.innerHTML = '';
        renderFolderList(KB.folders);
        renderRecentFiles();
        return;
    }

    const result = await kbApi('search', 'GET', null, { q: q });
    if (!result.success) {
        box.classList.remove('hidden');
        box.innerHTML = '<div class="p-3 text-sm text-red-600">Search failed.</div>';
        return;
    }

    const rows = Array.isArray(result.data && result.data.results) ? result.data.results : [];
    box.classList.remove('hidden');

    if (!rows.length) {
        box.innerHTML = '<div class="p-3 text-sm text-gray-500">No results found.</div>';
        return;
    }

    box.innerHTML = rows.map((row) => {
        return `
            <button class="w-full text-left p-3 border-b border-gray-100 hover:bg-gray-50"
                    onclick="handleSearchSelection('${row.type}', '${row.id}')">
                <p class="text-xs font-black uppercase tracking-widest">${escapeHtml(row.name)}</p>
                <p class="text-[10px] text-gray-500 uppercase tracking-widest mt-1">${escapeHtml(row.path || '')}</p>
            </button>
        `;
    }).join('');
}

function handleSearchSelection(type, id) {
    const box = document.getElementById('kb-search-results');
    box.classList.add('hidden');
    box.innerHTML = '';
    if (type === 'folder') {
        openFolder(id);
    } else {
        openFile(id);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('kb-file-input').addEventListener('change', handleFileUpload);
    document.getElementById('kb-search-input').addEventListener('input', function(event) {
        runSearch(event.target.value);
    });
    loadFolders();
});
</script>
</body>
</html>
