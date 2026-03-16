<?php
/**
 * Shared Pomodoro Music Manager
 */
if (!Auth::isAdmin()) {
    http_response_code(403);
    ?>
    <div class="max-w-2xl mx-auto py-16">
        <div class="bg-white border border-red-100 rounded-3xl shadow-sm p-8 text-center">
            <h2 class="text-2xl font-semibold text-gray-900 mb-3">Administrator access required</h2>
            <p class="text-gray-600">Only administrators can manage the shared music library for this installation.</p>
        </div>
    </div>
    <?php
    return;
}
?>

<div class="max-w-5xl">
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=settings" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Shared Music Library</h2>
            <p class="text-sm text-gray-500 mt-1">Manage Pomodoro audio that should be available to every workspace and included in clean app exports.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Upload Shared Track</h3>
            <p class="text-sm text-gray-500 mb-5">Accepted formats: MP3, WAV, and M4A. Files are stored in <code class="bg-gray-100 px-2 py-1 rounded text-xs">assets/media/pomodoro</code>.</p>

            <form id="shared-music-upload-form" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Track name</label>
                    <input type="text" id="shared-track-name" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-black focus:border-transparent" placeholder="Deep Focus Loop">
                </div>

                <div class="relative group">
                    <input type="file" id="shared-track-file" accept=".mp3,.wav,.m4a,audio/mpeg,audio/wav,audio/mp4,audio/x-m4a" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                    <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center group-hover:border-black transition-colors">
                        <p id="shared-track-file-label" class="text-sm font-bold text-gray-400 uppercase tracking-widest">Choose audio file</p>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-black text-white rounded-xl font-semibold hover:bg-gray-800 transition">Upload Shared Track</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Library Tracks</h3>
                    <p class="text-sm text-gray-500">Rename or delete tracks that should be visible to every user.</p>
                </div>
                <button type="button" onclick="loadSharedMusic()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition">
                    Refresh
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                            <th class="px-6 py-3">Track</th>
                            <th class="px-6 py-3">File</th>
                            <th class="px-6 py-3">Size</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="shared-music-list" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-gray-500">Loading shared music...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const fileInput = document.getElementById('shared-track-file');
    if (fileInput) {
        fileInput.addEventListener('change', () => {
            const label = document.getElementById('shared-track-file-label');
            const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            label.textContent = file ? file.name : 'Choose audio file';
        });
    }

    const form = document.getElementById('shared-music-upload-form');
    if (form) {
        form.addEventListener('submit', uploadSharedTrack);
    }

    loadSharedMusic();
});

async function loadSharedMusic() {
    const tbody = document.getElementById('shared-music-list');
    tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">Loading shared music...</td></tr>';

    try {
        const response = await api.get('api/pomodoro.php?action=shared_music_list');
        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Failed to load shared music');
        }

        const tracks = response.data || [];
        if (tracks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">No shared tracks uploaded yet.</td></tr>';
            return;
        }

        tbody.innerHTML = tracks.map((track) => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="font-medium text-gray-900">${escapeHtml(track.name || 'Untitled')}</div>
                    <div class="text-xs text-gray-500 mt-1">${track.uploadedAt ? new Date(track.uploadedAt).toLocaleString() : 'Unknown upload date'}</div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600 font-mono">${escapeHtml(track.filename || '')}</td>
                <td class="px-6 py-4 text-sm text-gray-600">${formatBytes(track.size || 0)}</td>
                <td class="px-6 py-4">
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" onclick='renameSharedTrack(${JSON.stringify(track.id)}, ${JSON.stringify(track.name || "")})' class="px-3 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition">Rename</button>
                        <button type="button" onclick='deleteSharedTrack(${JSON.stringify(track.id)}, ${JSON.stringify(track.name || track.filename || "")})' class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition">Delete</button>
                    </div>
                </td>
            </tr>
        `).join('');
    } catch (error) {
        console.error('Failed to load shared music:', error);
        tbody.innerHTML = `<tr><td colspan="4" class="px-6 py-10 text-center text-red-500">${escapeHtml(error.message || 'Failed to load shared music')}</td></tr>`;
    }
}

async function uploadSharedTrack(event) {
    event.preventDefault();

    const fileInput = document.getElementById('shared-track-file');
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
    if (!file) {
        showToast('Choose an MP3 or WAV file first.', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('name', document.getElementById('shared-track-name').value.trim());
    formData.append('csrf_token', CSRF_TOKEN);

    const submitButton = event.target.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Uploading...';

    try {
        const response = await fetch('api/pomodoro.php?action=shared_music_upload', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error?.message || data.message || 'Upload failed');
        }

        showToast('Shared track uploaded successfully.', 'success');
        event.target.reset();
        document.getElementById('shared-track-file-label').textContent = 'Choose audio file';
        loadSharedMusic();
    } catch (error) {
        showToast(error.message || 'Upload failed', 'error');
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
    }
}

function renameSharedTrack(id, currentName) {
    const nextName = window.prompt('Rename shared track', currentName);
    if (!nextName || nextName.trim() === '' || nextName.trim() === currentName) {
        return;
    }

    api.post('api/pomodoro.php?action=shared_music_rename', {
        id,
        name: nextName.trim(),
        csrf_token: CSRF_TOKEN
    }).then((response) => {
        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Rename failed');
        }
        showToast('Track renamed.', 'success');
        loadSharedMusic();
    }).catch((error) => {
        showToast(error.message || 'Rename failed', 'error');
    });
}

function deleteSharedTrack(id, label) {
    if (!window.confirm(`Delete "${label}" from the shared Pomodoro library?`)) {
        return;
    }

    api.post('api/pomodoro.php?action=shared_music_delete', {
        id,
        csrf_token: CSRF_TOKEN
    }).then((response) => {
        if (!response.success) {
            throw new Error(response.error?.message || response.message || 'Delete failed');
        }
        showToast('Track deleted.', 'success');
        loadSharedMusic();
    }).catch((error) => {
        showToast(error.message || 'Delete failed', 'error');
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatBytes(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) {
        return `${size} B`;
    }
    if (size < 1048576) {
        return `${(size / 1024).toFixed(1)} KB`;
    }
    return `${(size / 1048576).toFixed(1)} MB`;
}
</script>
