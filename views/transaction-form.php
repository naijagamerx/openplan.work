<?php
/**
 * Transaction Form View (Add/Edit)
 */
$db = new Database(getMasterPassword());
$id = $_GET['id'] ?? null;
$transaction = null;

if ($id) {
    $finance = $db->load('finance');
    foreach ($finance as $t) {
        if ($t['id'] === $id) {
            $transaction = $t;
            break;
        }
    }
}

$title = $id ? 'Edit Transaction' : 'New Transaction';
$categories = ['General', 'Sales', 'Services', 'Subscription', 'Marketing', 'Hardware', 'Software', 'Rent', 'Utilities', 'Taxes', 'Other'];
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=finance" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="transaction-form" class="p-8 space-y-8">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Type Selection -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-400 mb-4 uppercase tracking-[0.2em]">Transaction Type</label>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="income" class="peer hidden" 
                                   <?php echo ($transaction['type'] ?? 'income') === 'income' ? 'checked' : ''; ?>>
                            <div class="py-4 text-center rounded-2xl border-2 border-gray-50 font-bold peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:text-green-700 transition-all">
                                INCOME
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="type" value="expense" class="peer hidden" 
                                   <?php echo ($transaction['type'] ?? 'income') === 'expense' ? 'checked' : ''; ?>>
                            <div class="py-4 text-center rounded-2xl border-2 border-gray-50 font-bold peer-checked:bg-red-50 peer-checked:border-red-500 peer-checked:text-red-700 transition-all">
                                EXPENSE
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Description</label>
                    <input type="text" name="description" value="<?php echo e($transaction['description'] ?? ''); ?>" required
                           placeholder="What was this for?"
                           class="w-full px-5 py-4 border-2 border-gray-50 rounded-2xl focus:border-black outline-none transition-all text-lg font-bold">
                </div>

                <!-- Amount -->
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Amount</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                        <input type="number" name="amount" value="<?php echo e($transaction['amount'] ?? ''); ?>" step="0.01" required
                               placeholder="0.00"
                               class="w-full pl-8 pr-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                    </div>
                </div>

                <!-- Date -->
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Date</label>
                    <input type="date" name="date" value="<?php echo e($transaction['date'] ?? date('Y-m-d')); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Category</label>
                    <select name="category" required class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors appearance-none bg-white">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>" <?php echo ($transaction['category'] ?? 'General') === $cat ? 'selected' : ''; ?>>
                                <?php echo e($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Notes (Optional)</label>
                <textarea name="notes" rows="4" 
                          placeholder="Additional details, tax references, etc..."
                          class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-all min-h-[100px] font-medium"><?php echo e($transaction['notes'] ?? ''); ?></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-4 pt-8 border-t border-gray-100">
                <a href="?page=finance" class="px-8 py-3 text-gray-400 font-bold rounded-xl hover:text-black transition uppercase tracking-widest text-[10px]">Cancel</a>
                <button type="submit" class="px-10 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg uppercase tracking-widest text-[10px]">
                    <?php echo $id ? 'Update Transaction' : 'Record Transaction'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('transaction-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const url = data.id ? 'api/finance.php?action=update&id=' + data.id : 'api/finance.php?action=add';
    const response = await api.post(url, data);
    
    if (response.success) {
        showToast(data.id ? 'Transaction updated!' : 'Transaction recorded!', 'success');
        setTimeout(() => location.href = '?page=finance', 1000);
    } else {
        showToast(response.error || 'Failed to save transaction', 'error');
    }
});
</script>
