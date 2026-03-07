<?php
// Clients View (CRM)
$db = new Database(getMasterPassword());
$clients = $db->load('clients');
$projects = $db->load('projects');
$invoices = $db->load('invoices');
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <p class="text-gray-500 font-bold uppercase tracking-widest text-[10px]"><?php echo count($clients); ?> clients in database</p>
        <a href="?page=client-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Client
        </a>
    </div>
    
    <!-- Clients List -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <?php if (empty($clients)): ?>
            <div class="p-20 text-center">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">No clients yet</h3>
                <p class="text-gray-500 mt-2">Start by adding your first client</p>
                <a href="?page=client-form" class="mt-6 inline-block px-6 py-2 bg-black text-white rounded-lg font-medium hover:bg-gray-800 transition">Add Client →</a>
            </div>
        <?php else: ?>
            <table class="w-full text-left">
                <thead class="bg-gray-50/50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Client Info</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Active Projects</th>
                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Total Revenue</th>
                        <th class="px-6 py-4 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($clients as $client): 
                        $clientProjects = array_filter($projects, fn($p) => ($p['clientId'] ?? '') === $client['id']);
                        $clientInvoices = array_filter($invoices, fn($i) => ($i['clientId'] ?? '') === $client['id']);
                        $revenue = array_sum(array_map(fn($i) => ($i['status'] ?? '') === 'paid' ? ($i['total'] ?? 0) : 0, $clientInvoices));
                    ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center font-black text-lg shadow-sm">
                                        <?php echo strtoupper(substr($client['name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-bold text-gray-900"><?php echo e($client['name']); ?></p>
                                        <p class="text-xs text-gray-400 font-medium"><?php echo e($client['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <span class="px-3 py-1 bg-gray-100 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-600">
                                    <?php echo count($clientProjects); ?> Projects
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                <p class="text-sm font-black text-gray-900"><?php echo formatCurrency($revenue); ?></p>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="?page=client-form&id=<?php echo e($client['id']); ?>" 
                                       class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-black hover:border-black transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </a>
                                    <button onclick="deleteClient('<?php echo e($client['id']); ?>')" 
                                            class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-100 transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
async function deleteClient(id) {
    confirmAction('Are you sure you want to delete this client?', async () => {
        const response = await api.delete(`api/clients.php?id=${id}`);
        if (response.success) {
            showToast('Client deleted', 'success');
            location.reload();
        }
    });
}
</script>
