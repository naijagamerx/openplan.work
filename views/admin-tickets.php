<?php
/**
 * Admin → Support Tickets queue.
 */

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo '<div class="p-6"><h2 class="text-2xl font-bold text-gray-900 mb-2">Administrator access required</h2></div>';
    return;
}

require_once INCLUDES_PATH . '/TicketsAPI.php';
$api = new TicketsAPI();
$tickets = $api->getAll();

$usersDb = new Database(APP_NAME);
$users = $usersDb->load('users', true);
$userMap = [];
foreach ($users as $u) {
    $userMap[$u['id'] ?? ''] = $u;
}
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=dashboard" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Support Tickets</h2>
            <p class="text-gray-500 font-medium tracking-tight">Users awaiting your response are listed first.</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <?php if (empty($tickets)): ?>
            <div class="p-12 text-center">
                <p class="text-gray-500 text-sm">No tickets yet.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($tickets as $t):
                    $user = $userMap[$t['userId'] ?? ''] ?? null;
                    $userLabel = $user ? ($user['email'] ?? $user['name'] ?? 'Unknown') : 'Unknown user';
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
                                    From <?php echo e($userLabel); ?> &middot;
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
