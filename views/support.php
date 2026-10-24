<?php
/**
 * Support — user ticket list + new-ticket form.
 */

require_once INCLUDES_PATH . '/TicketsAPI.php';
$api = new TicketsAPI();
$tickets = $api->getByUser(Auth::userId());
?>

<div>
    <div class="flex items-center justify-between gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Support</h2>
            <p class="text-gray-500 font-medium tracking-tight">Open a ticket with the admin.</p>
        </div>
        <button type="button" id="new-ticket-btn" class="px-6 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            New Ticket
        </button>
    </div>

    <div id="ticket-form-card" class="hidden bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-6">
        <form id="ticket-form" class="p-8 space-y-5">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold">Open a new ticket</h3>
                <button type="button" id="ticket-form-close" class="text-sm text-gray-500 hover:text-black uppercase tracking-widest font-bold">Cancel</button>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Subject</label>
                <input type="text" name="subject" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="Brief summary">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Priority</label>
                <select name="priority" class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white">
                    <option value="low">Low</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">High</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Message</label>
                <textarea name="body" rows="6" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="Describe what you need..."></textarea>
            </div>

            <div class="flex justify-end pt-6 border-t-2 border-gray-100">
                <button type="submit" class="px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition">Submit Ticket</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($tickets)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm">No tickets yet.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($tickets as $t):
                    $status = $t['status'] ?? 'awaiting_admin';
                    $statusColor = match ($status) {
                        'closed' => 'text-gray-700 bg-gray-50 border-gray-200',
                        'awaiting_admin' => 'text-yellow-700 bg-yellow-50 border-yellow-200',
                        'awaiting_user' => 'text-blue-700 bg-blue-50 border-blue-200',
                        default => 'text-green-700 bg-green-50 border-green-200'
                    };
                    $msgCount = count($t['messages'] ?? []);
                ?>
                    <a href="?page=ticket-view&id=<?php echo e($t['id'] ?? ''); ?>" class="block p-6 hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <p class="font-bold text-lg"><?php echo e($t['subject'] ?? ''); ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php echo $msgCount; ?> message<?php echo $msgCount === 1 ? '' : 's'; ?>
                                    &middot; Updated <?php echo e(date('M j, Y g:i a', strtotime((string)($t['updatedAt'] ?? '')))); ?>
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest border px-2.5 py-1 rounded-full <?php echo $statusColor; ?>">
                                <?php echo e(str_replace('_', ' ', $status)); ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('ticket-form');
    const card = document.getElementById('ticket-form-card');
    const newBtn = document.getElementById('new-ticket-btn');
    const closeBtn = document.getElementById('ticket-form-close');

    newBtn.addEventListener('click', () => {
        card.classList.remove('hidden');
        card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    closeBtn.addEventListener('click', () => card.classList.add('hidden'));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const payload = {
            subject: (fd.get('subject') || '').trim(),
            body: (fd.get('body') || '').trim(),
            priority: fd.get('priority') || 'normal',
            csrf_token: fd.get('csrf_token') || ''
        };
        if (!payload.subject || !payload.body) {
            showToast('Subject and message are required', 'error');
            return;
        }
        try {
            const response = await api.post('api/tickets.php', payload);
            if (response && response.success) {
                showToast('Ticket submitted', 'success');
                setTimeout(() => { window.location.href = '?page=ticket-view&id=' + encodeURIComponent(response.data?.id || ''); }, 400);
            } else {
                showToast(response?.error?.message || 'Failed', 'error');
            }
        } catch (err) {
            showToast(err.response?.error?.message || 'Failed to submit', 'error');
        }
    });
})();
</script>
