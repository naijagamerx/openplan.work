<?php
/**
 * Ticket view — single ticket thread + reply form.
 * Both user (owner) and admin can read/reply.
 */

require_once INCLUDES_PATH . '/TicketsAPI.php';
$api = new TicketsAPI();
$id = (string)($_GET['id'] ?? '');
$ticket = $id !== '' ? $api->find($id) : null;
$isOwner = $ticket && ($ticket['userId'] ?? '') === Auth::userId();
$isAdmin = Auth::isAdmin();
if (!$ticket || (!$isOwner && !$isAdmin)) {
    header('Location: ?page=support');
    exit;
}
$status = $ticket['status'] ?? 'awaiting_admin';
$statusColor = match ($status) {
    'closed' => 'text-gray-700 bg-gray-50 border-gray-200',
    'awaiting_admin' => 'text-yellow-700 bg-yellow-50 border-yellow-200',
    'awaiting_user' => 'text-blue-700 bg-blue-50 border-blue-200',
    default => 'text-green-700 bg-green-50 border-green-200'
};
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="<?php echo $isAdmin ? '?page=admin-tickets' : '?page=support'; ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-1">
                <h2 class="text-3xl font-bold text-gray-900"><?php echo e($ticket['subject'] ?? ''); ?></h2>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest border px-2.5 py-1 rounded-full <?php echo $statusColor; ?>">
                    <?php echo e(str_replace('_', ' ', $status)); ?>
                </span>
            </div>
            <p class="text-gray-500 text-sm">Opened <?php echo e(date('M j, Y g:i a', strtotime((string)($ticket['createdAt'] ?? '')))); ?></p>
        </div>
    </div>

    <div class="space-y-4 mb-6">
        <?php foreach ($ticket['messages'] ?? [] as $msg):
            $isAdminMsg = (($msg['authorRole'] ?? '') === 'admin');
        ?>
            <div class="flex <?php echo $isAdminMsg ? 'justify-end' : ''; ?>">
                <div class="max-w-2xl <?php echo $isAdminMsg ? 'bg-black text-white' : 'bg-white border border-gray-200'; ?> rounded-2xl p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <p class="text-[10px] font-bold uppercase tracking-widest <?php echo $isAdminMsg ? 'text-white/60' : 'text-gray-500'; ?>">
                            <?php echo $isAdminMsg ? 'Admin' : 'You'; ?>
                        </p>
                        <p class="text-[10px] <?php echo $isAdminMsg ? 'text-white/60' : 'text-gray-400'; ?>">
                            <?php echo e(date('M j, g:i a', strtotime((string)($msg['createdAt'] ?? '')))); ?>
                        </p>
                    </div>
                    <p class="text-sm whitespace-pre-line"><?php echo e($msg['body'] ?? ''); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($status !== 'closed'): ?>
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <form id="reply-form" class="p-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                <div>
                    <label class="block text-sm font-bold text-gray-500 mb-2 uppercase tracking-widest">Reply</label>
                    <textarea name="body" rows="4" required class="w-full px-4 py-3 border-2 border-gray-100 rounded-xl focus:border-black outline-none transition-colors bg-white" placeholder="Write your reply..."></textarea>
                </div>
                <div class="flex items-center justify-between">
                    <button type="button" id="close-ticket-btn" class="text-xs font-bold text-gray-500 hover:text-red-600 uppercase tracking-widest">Close ticket</button>
                    <button type="submit" class="px-8 py-3 bg-black text-white rounded-xl text-sm font-bold uppercase tracking-widest hover:bg-gray-800 transition">Send Reply</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-gray-50 border border-gray-200 rounded-2xl p-6 text-center text-sm text-gray-500">
            This ticket is closed.
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const ticketId = <?php echo json_encode($id); ?>;
    const form = document.getElementById('reply-form');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(form);
            const payload = {
                body: (fd.get('body') || '').trim(),
                csrf_token: fd.get('csrf_token') || ''
            };
            if (!payload.body) {
                showToast('Reply body required', 'error');
                return;
            }
            try {
                const response = await api.post('api/tickets.php?id=' + encodeURIComponent(ticketId) + '&action=reply', payload);
                if (response && response.success) {
                    showToast('Reply sent', 'success');
                    setTimeout(() => location.reload(), 300);
                } else {
                    showToast(response?.error?.message || 'Failed', 'error');
                }
            } catch (err) {
                showToast(err.response?.error?.message || 'Failed to send', 'error');
            }
        });
    }

    const closeBtn = document.getElementById('close-ticket-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            confirmAction('Close this ticket?', async () => {
                try {
                    const response = await api.post('api/tickets.php?id=' + encodeURIComponent(ticketId) + '&action=close', { csrf_token: CSRF_TOKEN });
                    if (response && response.success) {
                        showToast('Ticket closed', 'success');
                        setTimeout(() => location.reload(), 300);
                    } else {
                        showToast(response?.error?.message || 'Failed', 'error');
                    }
                } catch (err) {
                    showToast(err.response?.error?.message || 'Failed to close', 'error');
                }
            });
        });
    }
})();
</script>
