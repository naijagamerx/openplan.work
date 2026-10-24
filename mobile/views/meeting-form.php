<?php
/**
 * Mobile Meeting Form — create / edit calendar meetings.
 */

if (!Auth::check()) {
    header('Location: ?page=login');
    exit;
}

require_once INCLUDES_PATH . '/MeetingsAPI.php';

$masterPassword = getMasterPassword();
$db = new Database($masterPassword, Auth::userId());
$meetingsAPI = new MeetingsAPI($db, 'meetings');

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$isEdit = $id !== '';

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
    'color' => $meeting['color'] ?? '#a855f7'
];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$siteName = getSiteName() ?? 'LazyMan';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport"/>
<title><?= $isEdit ? 'Edit Meeting' : 'New Meeting' ?> - <?= htmlspecialchars($siteName) ?></title>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: { extend: { fontFamily: { display: ['Inter', 'sans-serif'] }, colors: { primary: '#000000' } } }
    };
</script>
<style type="text/tailwindcss">
    @layer base { body { @apply bg-white text-black font-display antialiased; } }
    .mobile-input { @apply w-full border border-gray-300 dark:border-zinc-700 rounded-lg px-3 py-3 text-sm bg-white dark:bg-zinc-900 focus:outline-none focus:ring-2 focus:ring-black dark:focus:ring-white; }
    .mobile-label { @apply block text-[10px] font-black uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400 mb-1; }
</style>
</head>
<body class="bg-gray-100 dark:bg-zinc-950 flex justify-center">
<div class="relative w-full max-w-[420px] min-h-screen bg-white dark:bg-zinc-950 shadow-2xl flex flex-col">

<header class="px-4 py-4 flex items-center justify-between border-b border-gray-100 dark:border-zinc-800">
    <a href="?page=calendar" class="p-1 hover:bg-gray-100 dark:hover:bg-zinc-900 rounded-full" aria-label="Back">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
    </a>
    <h1 class="text-sm font-bold uppercase tracking-[0.2em]"><?= $isEdit ? 'Edit Meeting' : 'New Meeting' ?></h1>
    <div class="w-7"></div>
</header>

<main class="flex-1 overflow-y-auto px-4 py-5 pb-32">
    <form id="meeting-form" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>"><?php endif; ?>

        <div>
            <label class="mobile-label" for="m-title">Title</label>
            <input id="m-title" name="title" type="text" required value="<?= htmlspecialchars($defaults['title']) ?>" class="mobile-input" placeholder="Team standup">
        </div>

        <div>
            <label class="mobile-label" for="m-date">Date</label>
            <input id="m-date" name="date" type="date" required value="<?= htmlspecialchars($defaults['date']) ?>" class="mobile-input">
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mobile-label" for="m-start">Start</label>
                <input id="m-start" name="startTime" type="time" value="<?= htmlspecialchars($defaults['startTime']) ?>" class="mobile-input">
            </div>
            <div>
                <label class="mobile-label" for="m-end">End</label>
                <input id="m-end" name="endTime" type="time" value="<?= htmlspecialchars($defaults['endTime']) ?>" class="mobile-input">
            </div>
        </div>

        <div>
            <label class="mobile-label" for="m-location">Location</label>
            <input id="m-location" name="location" type="text" value="<?= htmlspecialchars($defaults['location']) ?>" class="mobile-input" placeholder="Room A / Zoom / Address">
        </div>

        <div>
            <label class="mobile-label" for="m-attendees">Attendees</label>
            <input id="m-attendees" name="attendees" type="text" value="<?= htmlspecialchars($defaults['attendees']) ?>" class="mobile-input" placeholder="comma-separated">
        </div>

        <div>
            <label class="mobile-label" for="m-color">Color</label>
            <input id="m-color" name="color" type="color" value="<?= htmlspecialchars($defaults['color']) ?>" class="mobile-input h-14">
        </div>

        <div>
            <label class="mobile-label" for="m-description">Description</label>
            <textarea id="m-description" name="description" rows="4" class="mobile-input" placeholder="Agenda, notes..."><?= htmlspecialchars($defaults['description']) ?></textarea>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="flex-1 bg-black text-white h-12 rounded-full font-black text-xs uppercase tracking-[0.3em] hover:opacity-90 transition">
                <?= $isEdit ? 'Save' : 'Create' ?>
            </button>
            <a href="?page=calendar" class="flex-1 border border-gray-300 dark:border-zinc-700 h-12 rounded-full font-bold text-xs uppercase tracking-[0.3em] flex items-center justify-center">Cancel</a>
        </div>

        <?php if ($isEdit): ?>
            <button type="button" id="meeting-delete" class="w-full text-red-600 dark:text-red-400 text-xs font-bold uppercase tracking-widest pt-4">Delete meeting</button>
        <?php endif; ?>
    </form>
</main>

</div>

<script>
    (function() {
        const path = window.location.pathname;
        const baseMatch = path.match(/^(\/[^\/]*)?\//);
        window.BASE_PATH = baseMatch ? baseMatch[1] || '' : '';
    })();
    const APP_URL = window.location.origin + window.BASE_PATH;
    const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';
    const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
    const MEETING_ID = <?= json_encode($id) ?>;
</script>
<script>
    function showMsg(msg, type) {
        if (window.Mobile && Mobile.ui && Mobile.ui.showToast) {
            Mobile.ui.showToast(msg, type || 'info');
        } else {
            alert(msg);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('meeting-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const payload = {
                title: (fd.get('title') || '').trim(),
                description: fd.get('description') || '',
                date: fd.get('date') || '',
                startTime: fd.get('startTime') || '',
                endTime: fd.get('endTime') || '',
                location: fd.get('location') || '',
                attendees: (fd.get('attendees') || '').split(',').map(s => s.trim()).filter(Boolean),
                color: fd.get('color') || '#a855f7',
                csrf_token: CSRF_TOKEN
            };
            if (!payload.title || !payload.date) {
                showMsg('Title and date are required', 'error');
                return;
            }
            try {
                const url = IS_EDIT
                    ? `${APP_URL}/api/meetings.php?id=${encodeURIComponent(MEETING_ID)}`
                    : `${APP_URL}/api/meetings.php`;
                const method = IS_EDIT ? 'PUT' : 'POST';
                const response = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (result.success) {
                    showMsg(IS_EDIT ? 'Meeting updated' : 'Meeting created', 'success');
                    setTimeout(() => { window.location.href = '?page=calendar'; }, 400);
                } else {
                    showMsg(result.error?.message || result.error || 'Failed to save', 'error');
                }
            } catch (err) {
                console.error('save meeting failed:', err);
                showMsg('Failed to save meeting', 'error');
            }
        });

        const delBtn = document.getElementById('meeting-delete');
        if (delBtn) {
            delBtn.addEventListener('click', async () => {
                if (!confirm('Delete this meeting?')) return;
                try {
                    const response = await fetch(`${APP_URL}/api/meetings.php?id=${encodeURIComponent(MEETING_ID)}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-Token': CSRF_TOKEN }
                    });
                    const result = await response.json();
                    if (result.success) {
                        showMsg('Meeting deleted', 'success');
                        setTimeout(() => { window.location.href = '?page=calendar'; }, 400);
                    } else {
                        showMsg(result.error?.message || result.error || 'Failed to delete', 'error');
                    }
                } catch (err) {
                    console.error('delete meeting failed:', err);
                    showMsg('Failed to delete meeting', 'error');
                }
            });
        }
    });
</script>
</body>
</html>
