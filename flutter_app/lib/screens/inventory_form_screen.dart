import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/inventory_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Create or edit an inventory product. Mirrors mobile product-form.
class InventoryFormScreen extends ConsumerStatefulWidget {
  const InventoryFormScreen({super.key, this.itemId});

  final String? itemId;
  bool get isEdit => itemId != null;

  @override
  ConsumerState<InventoryFormScreen> createState() =>
      _InventoryFormScreenState();
}

class _InventoryFormScreenState extends ConsumerState<InventoryFormScreen> {
  final _name = TextEditingController();
  final _sku = TextEditingController();
  final _stock = TextEditingController();
  final _minStock = TextEditingController();
  final _price = TextEditingController();
  final _cost = TextEditingController();
  final _category = TextEditingController();
  final _description = TextEditingController();
  bool _loading = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) _loadForEdit();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final item = await ref
          .read(inventoryRepositoryProvider)
          .fetch(widget.itemId!);
      if (item != null && mounted) {
        _name.text = item.name;
        _sku.text = item.sku;
        _stock.text = '${item.stock}';
        _minStock.text = '${item.minStock}';
        _price.text = item.price == 0 ? '' : _strip(item.price);
        _cost.text = item.cost == 0 ? '' : _strip(item.cost);
        _category.text = item.category;
        _description.text = item.description;
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _strip(double v) {
    final s = v.toStringAsFixed(2);
    // Trim trailing .00 for cleaner editing.
    return s.endsWith('.00') ? s.substring(0, s.length - 3) : s;
  }

  @override
  void dispose() {
    _name.dispose();
    _sku.dispose();
    _stock.dispose();
    _minStock.dispose();
    _price.dispose();
    _cost.dispose();
    _category.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Product name is required'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    final stock = int.tryParse(_stock.text) ?? 0;
    final minStock = int.tryParse(_minStock.text) ?? 5;
    final price = double.tryParse(_price.text) ?? 0;
    final cost = double.tryParse(_cost.text) ?? 0;

    setState(() => _saving = true);
    final repo = ref.read(inventoryRepositoryProvider);
    try {
      if (widget.isEdit) {
        await repo.update(
          id: widget.itemId!,
          name: name,
          sku: _sku.text.trim(),
          stock: stock,
          minStock: minStock,
          price: price,
          cost: cost,
          category: _category.text.trim(),
          description: _description.text.trim(),
        );
      } else {
        await repo.create(
          name: name,
          sku: _sku.text.trim(),
          stock: stock,
          minStock: minStock,
          price: price,
          cost: cost,
          category: _category.text.trim(),
          description: _description.text.trim(),
        );
      }
      ref.invalidate(inventoryProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(
          leading: IconButton(
            icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
            onPressed: () => context.pop(),
          ),
          title: const Text('Edit Product'),
        ),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.page, 8, AppSpacing.page, 24),
          children: const [
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 20),
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 20),
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 20),
            Skeleton(height: 56, radius: AppRadii.button),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Product' : 'New Product'),
      ),
      body: SafeArea(
        top: false,
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page, 8, AppSpacing.page, 24),
                children: [
                  LabeledField(
                    label: 'Product name',
                    controller: _name,
                    hint: 'e.g. A4 Paper Ream',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'SKU',
                    controller: _sku,
                    hint: 'Optional stock code',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: LabeledField(
                          label: 'Stock',
                          controller: _stock,
                          hint: '0',
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.next,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: LabeledField(
                          label: 'Min stock',
                          controller: _minStock,
                          hint: '5',
                          keyboardType: TextInputType.number,
                          textInputAction: TextInputAction.next,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: LabeledField(
                          label: 'Sell price',
                          controller: _price,
                          hint: '0.00',
                          keyboardType: const TextInputType.numberWithOptions(
                              decimal: true),
                          textInputAction: TextInputAction.next,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: LabeledField(
                          label: 'Unit cost',
                          controller: _cost,
                          hint: '0.00',
                          keyboardType: const TextInputType.numberWithOptions(
                              decimal: true),
                          textInputAction: TextInputAction.next,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Category',
                    controller: _category,
                    hint: 'e.g. Office Supplies',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Description',
                    controller: _description,
                    hint: 'Optional notes',
                    maxLines: 3,
                  ),
                ],
              ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: widget.isEdit ? 'Update' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}
