<?php
/**
 * Product Form View (Add/Edit)
 */
$db = new Database(getMasterPassword(), Auth::userId());
$id = $_GET['id'] ?? null;
$product = null;

if ($id) {
    $products = $db->load('inventory');
    foreach ($products as $p) {
        if ($p['id'] === $id) {
            $product = $p;
            break;
        }
    }
}

$title = $id ? 'Edit Product' : 'New Product';
?>

<div>
    <div class="flex items-center gap-4 mb-8">
        <a href="?page=inventory" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        <h2 class="text-3xl font-bold text-gray-900"><?php echo e($title); ?></h2>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="product-form" class="p-8 space-y-8">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Product Name -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Product Name</label>
                    <input type="text" name="name" value="<?php echo e($product['name'] ?? ''); ?>" required
                           placeholder="e.g. Ultra Widget X1"
                           class="w-full px-5 py-4 border-2 border-gray-50 rounded-2xl focus:border-black outline-none transition-all text-lg font-bold">
                </div>

                <!-- SKU / Category -->
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">SKU / Code</label>
                    <input type="text" name="sku" value="<?php echo e($product['sku'] ?? ''); ?>"
                           placeholder="WID-001"
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Category</label>
                    <select name="category" class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium appearance-none bg-gray-50">
                        <option value="General">General</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Raw Materials">Raw Materials</option>
                        <option value="Packaging">Packaging</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Groceries">Groceries</option>
                    </select>
                </div>

                <!-- Pricing -->
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Unit Price</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                        <input type="number" name="unitPrice" value="<?php echo e($product['unitPrice'] ?? ''); ?>" step="0.01" required
                               placeholder="0.00"
                               class="w-full pl-8 pr-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Cost Price</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                        <input type="number" name="costPrice" value="<?php echo e($product['costPrice'] ?? ''); ?>" step="0.01"
                               placeholder="0.00"
                               class="w-full pl-8 pr-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                    </div>
                    <p class="mt-1 text-[10px] text-gray-400 font-bold uppercase tracking-tight">Internal cost for profit calculation.</p>
                </div>

                <!-- Stock Management -->
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Current Stock</label>
                    <input type="number" name="quantity" value="<?php echo e($product['quantity'] ?? '0'); ?>" required
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Minimum Stock Level</label>
                    <input type="number" name="reorderPoint" value="<?php echo e($product['reorderPoint'] ?? '5'); ?>"
                           class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-colors font-medium">
                    <p class="mt-1 text-[10px] text-gray-400 font-bold uppercase tracking-tight">System will alert you when stock falls below this.</p>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-sm font-bold text-gray-400 mb-2 uppercase tracking-widest">Description</label>
                <textarea name="description" rows="4" 
                          placeholder="Technical specs, features, or notes..."
                          class="w-full px-4 py-3 border-2 border-gray-50 rounded-xl focus:border-black outline-none transition-all min-h-[100px] font-medium"><?php echo e($product['description'] ?? ''); ?></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-4 pt-8 border-t border-gray-100">
                <a href="?page=inventory" class="px-8 py-3 text-gray-400 font-bold rounded-xl hover:text-black transition uppercase tracking-widest text-[10px]">Cancel</a>
                <button type="submit" class="px-10 py-3 bg-black text-white font-bold rounded-xl hover:bg-gray-800 transition shadow-lg uppercase tracking-widest text-[10px]">
                    <?php echo $id ? 'Update Product' : 'Create Product'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('product-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    const url = data.id ? 'api/inventory.php?action=update&id=' + data.id : 'api/inventory.php?action=add';
    const response = await api.post(url, data);
    
    if (response.success) {
        showToast(data.id ? 'Product updated!' : 'Product created!', 'success');
        setTimeout(() => location.href = '?page=inventory', 1000);
    } else {
        showToast(response.error || 'Failed to save product', 'error');
    }
});
</script>

