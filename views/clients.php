<?php
// Clients View (CRM)
$db = new Database(getMasterPassword(), Auth::userId());
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
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick="viewClient('<?php echo e($client['id']); ?>')" 
                                            class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-blue-600 hover:border-blue-100 transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
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

    <!-- Client View Modal -->
    <div id="client-view-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-black text-white rounded-2xl flex items-center justify-center font-black text-xl shadow-sm" id="view-client-avatar">
                        A
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900" id="view-client-name">Client Name</h3>
                        <p class="text-sm text-gray-500" id="view-client-company">Company Name</p>
                    </div>
                </div>
                <button onclick="closeClientView()" class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-6">
                <!-- Contact Information -->
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Contact Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            <div>
                                <p class="text-[10px] font-black uppercase text-gray-400">Email</p>
                                <p class="text-sm font-medium text-gray-900" id="view-client-email">email@example.com</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            <div>
                                <p class="text-[10px] font-black uppercase text-gray-400">Phone</p>
                                <p class="text-sm font-medium text-gray-900" id="view-client-phone">+1 234 567 890</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Details -->
                <div>
                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl" id="view-website-container">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                            <div>
                                <p class="text-[10px] font-black uppercase text-gray-400">Website</p>
                                <p class="text-sm font-medium text-gray-900" id="view-client-website">example.com</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl" id="view-address-container">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <div>
                                <p class="text-[10px] font-black uppercase text-gray-400">Address</p>
                                <p class="text-sm font-medium text-gray-900" id="view-client-address">123 Street, City</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div id="view-notes-container">
                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Notes</h4>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-sm text-gray-700" id="view-client-notes">No notes available</p>
                    </div>
                </div>

                <!-- Linked Projects & Invoices -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Linked Projects</h4>
                        </div>
                        <p class="text-2xl font-bold text-gray-900" id="view-projects-count">0</p>
                        <p class="text-xs text-gray-500" id="view-projects-list">No projects linked</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Invoice Summary</h4>
                        </div>
                        <p class="text-2xl font-bold text-gray-900" id="view-revenue">$0.00</p>
                        <p class="text-xs text-gray-500" id="view-invoices-count">0 invoices</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4 border-t border-gray-100">
                    <a href="?page=client-form&id=" id="view-edit-link" class="flex-1 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition text-center text-[10px] uppercase tracking-widest">
                        Edit Client
                    </a>
                    <button onclick="closeClientView()" class="flex-1 py-3 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition text-[10px] uppercase tracking-widest">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const clientsData = <?php echo json_encode($clients); ?>;
const projectsData = <?php echo json_encode($projects); ?>;
const invoicesData = <?php echo json_encode($invoices); ?>;

async function deleteClient(id) {
    confirmAction('Are you sure you want to delete this client?', async () => {
        const response = await api.delete(`api/clients.php?id=${id}`);
        if (response.success) {
            showToast('Client deleted', 'success');
            location.reload();
        }
    });
}

function viewClient(id) {
    const client = clientsData.find(c => c.id === id);
    if (!client) return;

    document.getElementById('view-client-avatar').textContent = (client.name || 'A').charAt(0).toUpperCase();
    document.getElementById('view-client-name').textContent = client.name || 'Unknown';
    document.getElementById('view-client-company').textContent = client.company || 'No company';
    document.getElementById('view-client-email').textContent = client.email || 'No email';
    document.getElementById('view-client-phone').textContent = client.phone || 'No phone';
    document.getElementById('view-client-website').textContent = client.website || 'No website';
    document.getElementById('view-client-address').textContent = client.address || 'No address';
    document.getElementById('view-client-notes').textContent = client.notes || 'No notes available';

    const clientProjects = projectsData.filter(p => (p.clientId || '') === id);
    const clientInvoices = invoicesData.filter(i => (i.clientId || '') === id);
    const paidRevenue = clientInvoices.filter(i => (i.status || '') === 'paid').reduce((sum, i) => sum + (i.total || 0), 0);

    document.getElementById('view-projects-count').textContent = clientProjects.length;
    document.getElementById('view-projects-list').textContent = clientProjects.length > 0 
        ? clientProjects.map(p => p.name).slice(0, 3).join(', ') + (clientProjects.length > 3 ? '...' : '')
        : 'No projects linked';

    document.getElementById('view-revenue').textContent = formatCurrency(paidRevenue);
    document.getElementById('view-invoices-count').textContent = `${clientInvoices.length} invoice${clientInvoices.length !== 1 ? 's' : ''}`;

    document.getElementById('view-edit-link').href = `?page=client-form&id=${id}`;

    document.getElementById('client-view-modal').classList.remove('hidden');
    document.getElementById('client-view-modal').classList.add('flex');
}

function closeClientView() {
    document.getElementById('client-view-modal').classList.add('hidden');
    document.getElementById('client-view-modal').classList.remove('flex');
}
</script>

