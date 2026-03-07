<?php
// Inventory View
$db = new Database(getMasterPassword());
$inventory = $db->load('inventory');
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400"><?php echo count($inventory); ?> products available</p>
        <a href="?page=product-form" class="flex items-center gap-2 px-6 py-3 bg-black text-white rounded-xl font-bold hover:bg-gray-800 transition shadow-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Product
        </a>
    </div>
    
    <!-- Products Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php if (empty($inventory)): ?>
            <div class="col-span-full text-center py-20 bg-white rounded-2xl border border-gray-100">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900">Inventory Empty</h3>
                <p class="text-gray-500 mt-2">Start by adding your first product from the button above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($inventory as $product): 
                $stock = $product['stock'] ?? $product['quantity'] ?? 0;
                $minStock = $product['minStock'] ?? $product['reorderPoint'] ?? 5;
                $lowStock = $stock <= $minStock;
                $price = $product['price'] ?? $product['unitPrice'] ?? 0;
            ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-xl transition-all group flex flex-col <?php echo $lowStock ? 'ring-2 ring-red-100' : ''; ?>">
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400 group-hover:bg-black group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <a href="?page=product-form&id=<?php echo e($product['id']); ?>" 
                               class="p-2 bg-white border border-gray-100 rounded-lg text-gray-400 hover:text-black hover:border-black transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                        </div>
                    </div>

                    <h3 class="font-bold text-gray-900 group-hover:text-black transition-colors"><?php echo e($product['name']); ?></h3>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-6"><?php echo e($product['sku'] ?? 'NO SKU'); ?></p>
                    
                    <div class="mt-auto grid grid-cols-2 gap-4 pt-6 border-t border-gray-50">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Stock</p>
                            <p class="text-xl font-bold <?php echo $lowStock ? 'text-red-500' : 'text-gray-900'; ?>">
                                <?php echo $stock; ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Price</p>
                            <p class="text-xl font-bold text-gray-900">
                                <?php echo formatCurrency($price); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-2">
                        <button onclick="adjustStock('<?php echo e($product['id']); ?>', 1)" 
                                class="flex-1 py-3 bg-gray-50 text-gray-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-all">
                            In
                        </button>
                        <button onclick="adjustStock('<?php echo e($product['id']); ?>', -1)" 
                                class="flex-1 py-3 bg-gray-50 text-gray-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-black hover:text-white transition-all">
                            Out
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
async function adjustStock(id, direction) {
    const qty = prompt(`Enter quantity to ${direction > 0 ? 'add' : 'remove'}:`);
    if (!qty || isNaN(qty)) return;
    
    const response = await api.post('api/inventory.php?action=adjust', {
        id: id,
        adjustment: parseInt(qty) * direction,
        csrf_token: CSRF_TOKEN
    });
    
    if (response.success) {
        showToast('Stock updated!', 'success');
        location.reload();
    } else {
        showToast(response.error || 'Update failed', 'error');
    }
}
</script>
