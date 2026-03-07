<?php
// Finance View
$db = new Database(getMasterPassword());
$invoices = $db->load('invoices');
$finance = $db->load('finance');

// Calculate stats
$totalRevenue = array_sum(array_map(fn($i) => $i['status'] === 'paid' ? $i['total'] : 0, $invoices));
$totalExpenses = array_sum(array_map(fn($e) => $e['amount'] ?? 0, $finance));
$profit = $totalRevenue - $totalExpenses;

// Monthly data for chart
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyData[$month] = ['income' => 0, 'expenses' => 0];
}

foreach ($invoices as $invoice) {
    if ($invoice['status'] === 'paid') {
        $month = date('Y-m', strtotime($invoice['createdAt'] ?? 'now'));
        if (isset($monthlyData[$month])) {
            $monthlyData[$month]['income'] += $invoice['total'];
        }
    }
}
?>

<div class="space-y-6">
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border-2 border-green-50 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Total Revenue</p>
                    <p class="text-3xl font-black text-green-600"><?php echo formatCurrency($totalRevenue); ?></p>
                </div>
                <div class="w-12 h-12 bg-green-50 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border-2 border-red-50 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Total Expenses</p>
                    <p class="text-3xl font-black text-red-600"><?php echo formatCurrency($totalExpenses); ?></p>
                </div>
                <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path></svg>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border-2 border-gray-50 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-1">Net Profit</p>
                    <p class="text-3xl font-black <?php echo $profit >= 0 ? 'text-gray-900' : 'text-red-600'; ?>">
                        <?php echo formatCurrency($profit); ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-gray-50 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart -->
    <div class="bg-white rounded-2xl border border-gray-200 p-8 shadow-sm">
        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400 mb-8">Financial Pulse (6 Months)</h3>
        <div class="h-64">
            <canvas id="financeChart"></canvas>
        </div>
    </div>
    
    <!-- Expenses Section -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Transactions</h3>
            <a href="?page=transaction-form" class="flex items-center gap-2 px-4 py-2 bg-black text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-gray-800 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Record Transaction
            </a>
        </div>
        <div class="divide-y divide-gray-50">
            <?php if (empty($finance)): ?>
                <div class="p-12 text-center">
                    <p class="text-gray-400 font-medium">No transactions recorded yet.</p>
                </div>
            <?php else: 
                // Group by date or just sort
                usort($finance, fn($a, $b) => strcmp($b['date'] ?? $b['createdAt'], $a['date'] ?? $a['createdAt']));
                foreach (array_slice($finance, 0, 20) as $expense): 
                    $isIncome = ($expense['type'] ?? 'expense') === 'income';
            ?>
                <div class="flex items-center justify-between p-6 hover:bg-gray-50 transition-colors group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black <?php echo $isIncome ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'; ?>">
                            <?php echo $isIncome ? '+' : '-'; ?>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900"><?php echo e($expense['description'] ?? 'Transaction'); ?></p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400"><?php echo e($expense['category'] ?? 'General'); ?></span>
                                <span class="w-1 h-1 bg-gray-200 rounded-full"></span>
                                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400"><?php echo formatDate($expense['date'] ?? $expense['createdAt']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <p class="text-sm font-black <?php echo $isIncome ? 'text-green-600' : 'text-red-600'; ?>">
                            <?php echo $isIncome ? '+' : '-'; ?><?php echo formatCurrency($expense['amount'] ?? 0); ?>
                        </p>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="?page=transaction-form&id=<?php echo e($expense['id']); ?>" 
                               class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-black hover:border-black transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                            <button onclick="deleteTransaction('<?php echo e($expense['id']); ?>')" 
                                    class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-red-600 hover:border-red-100 transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
// Initialize Chart
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('financeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_values(array_map(fn($m) => date('M', strtotime($m . "-01")), array_keys($monthlyData)))); ?>,
            datasets: [{
                label: 'Income',
                data: <?php echo json_encode(array_column($monthlyData, 'income')); ?>,
                backgroundColor: '#000',
                borderRadius: 8,
                barThickness: 32
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#000',
                    titleFont: { weight: 'bold' },
                    bodyFont: { weight: 'bold' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { color: '#f3f4f6', drawBorder: false },
                    ticks: { font: { weight: 'bold', size: 10 }, color: '#9ca3af' }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: 'bold', size: 10 }, color: '#9ca3af' }
                }
            }
        }
    });
});

async function deleteTransaction(id) {
    confirmAction('Remove this transaction record?', async () => {
        const response = await api.delete(`api/finance.php?id=${id}`);
        if (response.success) {
            showToast('Record deleted', 'success');
            location.reload();
        } else {
            showToast('Delete failed', 'error');
        }
    });
}
</script>
