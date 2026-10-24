<?php
/**
 * Meeting Form View — Create / Edit calendar meetings.
 * Uses main.php layout (full-width sidebar content area), matches task-form monochrome design.
 */

require_once INCLUDES_PATH . '/Database.php';
require_once INCLUDES_PATH . '/MeetingsAPI.php';

$pageTitle = 'Meeting';
$currentPage = 'calendar';

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$isEdit = $id !== '';

$db = new Database(getMasterPassword(), Auth::userId());
$meetingsAPI = new MeetingsAPI($db, 'meetings');

$meeting = null;
if ($isEdit) {
    $meeting = $meetingsAPI->find($id);
    if (!$meeting) {
        header('Location: ?page=calendar');
        exit;
    }
}

$today = date('Y-m-d');
$defaults = [
    'title' => $meeting['title'] ?? '',
    'description' => $meeting['description'] ?? '',
    'date' => $meeting['date'] ?? ($_GET['date'] ?? $today),
    'startTime' => $meeting['startTime'] ?? '',
    'endTime' => $meeting['endTime'] ?? '',
    'location' => $meeting['location'] ?? '',
    'attendees' => is_array($meeting['attendees'] ?? null) ? implode(', ', $meeting['attendees']) : ($meeting['attendees'] ?? ''),
    'projectId' => $meeting['projectId'] ?? '',
    'clientId' => $meeting['clientId'] ?? ''
];

$projects = $db->load('projects');
$clients = $db->load('clients');
$title = $isEdit ? 'Edit Meeting' : 'New Meeting';
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=calendar" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
            <p class="text-gray-500 font-medium tracking-tight">
                <?php echo $isEdit ? 'Update meeting details below.' : 'Schedule a new meeting on your calendar.'; ?>
            </p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="meeting-form" class="p-8 space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Title</label>
                    <input type="text" name="title" required value="<?php echo e($defaults['title']); ?>"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white"
                        placeholder="Team standup, Client kickoff, etc.">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Date</label>
                    <input type="date" name="date" required value="<?php echo e($defaults['date']); ?>"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Start</label>
                        <input type="time" name="startTime" value="<?php echo e($defaults['startTime']); ?>"
                            class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">End</label>
                        <input type="time" name="endTime" value="<?php echo e($defaults['endTime']); ?>"
                            class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Location</label>
                    <input type="text" name="location" value="<?php echo e($defaults['location']); ?>"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white"
                        placeholder="Room A / Zoom link / Physical address">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Attendees</label>
                    <input type="text" name="attendees" value="<?php echo e($defaults['attendees']); ?>"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white"
                        placeholder="comma-separated names or emails">
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Project</label>
                    <select name="projectId" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        <option value="">No project</option>
                        <?php foreach (($projects ?? []) as $p): ?>
                            <option value="<?php echo e($p['id']); ?>" <?php echo (($defaults['projectId'] ?? '') === $p['id']) ? 'selected' : ''; ?>>
                                <?php echo e($p['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Client</label>
                    <select name="clientId" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                        <option value="">No client</option>
                        <?php foreach (($clients ?? []) as $c): ?>
                            <option value="<?php echo e($c['id']); ?>" <?php echo (($defaults['clientId'] ?? '') === $c['id']) ? 'selected' : ''; ?>>
                                <?php echo e($c['name'] ?? $c['company'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Description</label>
                    <textarea name="description" rows="4"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white"
                        placeholder="Agenda, notes, expected outcomes..."><?php echo e($defaults['description']); ?></textarea>
                </div>
            </div>

            <div class="flex items-center justify-between pt-6 border-t-2 border-gray-100">
                <div>
                    <?php if ($isEdit): ?>
                        <button type="button" onclick="deleteMeeting()"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-bold text-gray-700 hover:text-red-600 uppercase tracking-widest transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Delete
                        </button>
                    <?php endif; ?>
                </div>
                <div class="flex gap-3">
                    <a href="?page=calendar" class="px-6 py-3 border-2 border-gray-200 rounded-xl text-sm font-bold uppercase tracking-widest text-gray-700 hover:border-black transition">Cancel</a>
                    <button type="submit" class="px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
                        <?php echo $isEdit ? 'Save Changes' : 'Create Meeting'; ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('meeting-form');
    if (!form) return;
    const isEdit = <?php echo $isEdit ? 'true' : 'false'; ?>;
    const meetingId = <?php echo json_encode($id); ?>;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(form);
        const payload = {
            title: (formData.get('title') || '').trim(),
            description: formData.get('description') || '',
            date: formData.get('date') || '',
            startTime: formData.get('startTime') || '',
            endTime: formData.get('endTime') || '',
            location: formData.get('location') || '',
            attendees: (formData.get('attendees') || '').split(',').map(s => s.trim()).filter(Boolean),
            color: '#000000',
            projectId: formData.get('projectId') || '',
            clientId: formData.get('clientId') || '',
            csrf_token: formData.get('csrf_token') || ''
        };

        if (!payload.title || !payload.date) {
            showToast('Title and date are required', 'error');
            return;
        }

        try {
            let response;
            if (isEdit) {
                response = await api.put('api/meetings.php?id=' + encodeURIComponent(meetingId), payload);
            } else {
                response = await api.post('api/meetings.php', payload);
            }
            if (response && response.success) {
                showToast(isEdit ? 'Meeting updated' : 'Meeting created', 'success');
                setTimeout(() => { window.location.href = '?page=calendar'; }, 400);
            } else {
                showToast((response && (response.error?.message || response.error)) || 'Failed to save', 'error');
            }
        } catch (err) {
            console.error('Meeting save failed:', err);
            showToast('Failed to save meeting', 'error');
        }
    });

    window.deleteMeeting = function() {
        confirmAction('Delete this meeting? This cannot be undone.', async () => {
            try {
                const response = await api.delete('api/meetings.php?id=' + encodeURIComponent(meetingId));
                if (response && response.success) {
                    showToast('Meeting deleted', 'success');
                    setTimeout(() => { window.location.href = '?page=calendar'; }, 400);
                } else {
                    showToast((response && (response.error?.message || response.error)) || 'Failed to delete', 'error');
                }
            } catch (err) {
                console.error('Meeting delete failed:', err);
                showToast('Failed to delete meeting', 'error');
            }
        });
    };
})();
</script>
