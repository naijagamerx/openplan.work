import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/inventory_item.dart';
import '../repositories/inventory_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Inventory product list. Mirrors mobile/views/inventory.php.
/// Plain Scaffold (not a primary tab) — pushed from the Menu hub.
class InventoryScreen extends ConsumerStatefulWidget {
  const InventoryScreen({super.key});

  @override
  ConsumerState<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends ConsumerState<InventoryScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final inventory = ref.watch(inventoryProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Inventory'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/inventory/new');
          if (mounted) ref.invalidate(inventoryProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: AppPalette.of(context).onInk),
      ),
      body: inventory.when(
        loading: () => _Loading(),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.cube,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(inventoryProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.cube,
                message: 'No products yet',
                actionLabel: 'Add Product',
                onAction: () async {
                  await context.push('/inventory/new');
                  if (mounted) ref.invalidate(inventoryProvider);
                },
              ),
            );
          }

          final filtered = list.where((item) {
            if (_query.isEmpty) return true;
            final q = _query.toLowerCase();
            return item.name.toLowerCase().contains(q) ||
                item.sku.toLowerCase().contains(q);
          }).toList();

          final lowStockCount = list.where((i) => i.isLowStock).length;
          final totalValue =
              list.fold<double>(0, (sum, i) => sum + i.value);
          final totalProfit =
              list.fold<double>(0, (sum, i) => sum + i.potentialProfit);

          return RefreshIndicator(
            onRefresh: () => ref.refresh(inventoryProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 120),
              children: [
                if (lowStockCount > 0) ...[
                  FadeIn(child: _LowStockBanner(count: lowStockCount)),
                  const SizedBox(height: 12),
                ],
                _SearchField(
                  query: _query,
                  onChanged: (v) => setState(() => _query = v),
                ),
                const SizedBox(height: 12),
                if (filtered.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No products match'),
                  )
                else
                  for (var i = 0; i < filtered.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 50).clamp(0, 350)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ProductCard(item: filtered[i]),
                      ),
                    ),
                const SizedBox(height: AppSpacing.gap),
                FadeIn(
                  child: _SummaryCard(
                    totalValue: totalValue,
                    totalProfit: totalProfit,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _Loading extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ListView(
      padding:
          const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
      children: const [
        Skeleton(height: 48, radius: AppRadii.button),
        SizedBox(height: 12),
        Skeleton(height: 150, radius: AppRadii.section),
        SizedBox(height: 12),
        Skeleton(height: 150, radius: AppRadii.section),
        SizedBox(height: 12),
        Skeleton(height: 150, radius: AppRadii.section),
      ],
    );
  }
}

class _LowStockBanner extends StatelessWidget {
  const _LowStockBanner({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Row(
        children: [
          HeroIcon(HeroPaths.bell, size: 18, color: p.ink),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'LOW STOCK ALERTS',
              style: AppType.label(context, color: p.textPrimary, size: 11),
            ),
          ),
          Text('$count',
              style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: p.ink)),
        ],
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.query, required this.onChanged});
  final String query;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return TextField(
      onChanged: onChanged,
      textCapitalization: TextCapitalization.sentences,
      decoration: InputDecoration(
        isDense: true,
        hintText: 'Search products or SKU',
        hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
        prefixIcon: HeroIcon(HeroPaths.search, size: 18, color: p.textFaint),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.border)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.border)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.ink, width: 1.5)),
      ),
    );
  }
}

class _ProductCard extends ConsumerWidget {
  const _ProductCard({required this.item});
  final InventoryItem item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final currency = NumberFormat.simpleCurrency();
    final stockColor =
        item.isLowStock ? const Color(0xFFDC2626) : p.textPrimary;

    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InkWell(
            onTap: () async {
              await context.push('/inventory/${item.id}');
              if (context.mounted) ref.invalidate(inventoryProvider);
            },
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(item.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: p.textPrimary)),
                      const SizedBox(height: 2),
                      Text(
                        'SKU: ${item.sku.isEmpty ? "NO-SKU" : item.sku}',
                        style: TextStyle(
                            fontFamily: 'GeistMono',
                            fontSize: 11,
                            color: p.textFaint),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                HeroIcon(HeroPaths.chevronRight, size: 16, color: p.textFaint),
              ],
            ),
          ),
          const SizedBox(height: 12),
          // Stat grid: Stock / Price / Value / Profit
          Container(
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(color: p.border),
                bottom: BorderSide(color: p.border),
              ),
            ),
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Row(
              children: [
                Expanded(
                  child: _Stat(
                      label: 'Stock',
                      value: '${item.stock}',
                      valueColor: stockColor),
                ),
                Expanded(
                  child: _Stat(
                      label: 'Price', value: currency.format(item.price)),
                ),
                Expanded(
                  child: _Stat(
                      label: 'Value', value: currency.format(item.value)),
                ),
                Expanded(
                  child: _Stat(
                    label: 'Profit',
                    value: currency.format(item.potentialProfit),
                    valueColor: item.potentialProfit >= 0
                        ? const Color(0xFF16A34A)
                        : const Color(0xFFDC2626),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _QuickButton(
                  label: 'Stock In',
                  icon: HeroPaths.plus,
                  onTap: () => _showAdjustDialog(context, ref, 'in'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _QuickButton(
                  label: 'Stock Out',
                  icon: HeroPaths.arrowTrendDown,
                  onTap: () => _showAdjustDialog(context, ref, 'out'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _showAdjustDialog(
      BuildContext context, WidgetRef ref, String direction) async {
    await showDialog<void>(
      context: context,
      builder: (_) => _StockAdjustDialog(
        item: item,
        direction: direction,
      ),
    );
    if (context.mounted) ref.invalidate(inventoryProvider);
  }
}

class _Stat extends StatelessWidget {
  const _Stat(
      {required this.label, required this.value, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textFaint, size: 9)),
        const SizedBox(height: 2),
        Text(value,
            style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: valueColor ?? p.textPrimary)),
      ],
    );
  }
}

class _QuickButton extends StatelessWidget {
  const _QuickButton({
    required this.label,
    required this.icon,
    required this.onTap,
  });
  final String label;
  final String icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          border: Border.all(color: p.ink),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            HeroIcon(icon, size: 14, color: p.ink),
            const SizedBox(width: 6),
            Text(label.toUpperCase(),
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1,
                    color: p.ink)),
          ],
        ),
      ),
    );
  }
}

class _StockAdjustDialog extends ConsumerStatefulWidget {
  const _StockAdjustDialog({required this.item, required this.direction});
  final InventoryItem item;
  final String direction; // 'in' | 'out'

  @override
  ConsumerState<_StockAdjustDialog> createState() =>
      _StockAdjustDialogState();
}

class _StockAdjustDialogState extends ConsumerState<_StockAdjustDialog> {
  final _qty = TextEditingController(text: '1');
  final _cost = TextEditingController();
  final _note = TextEditingController();
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    // Pre-fill unit cost from the product's cost price (matches PHP app).
    if (widget.item.cost > 0) {
      _cost.text = widget.item.cost.toStringAsFixed(2);
    }
  }

  @override
  void dispose() {
    _qty.dispose();
    _cost.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final qty = int.tryParse(_qty.text) ?? 0;
    if (qty <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Enter a valid quantity'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    final unitCost = double.tryParse(_cost.text);
    setState(() => _saving = true);
    try {
      await ref.read(inventoryRepositoryProvider).adjustStock(
            widget.item.id,
            widget.direction,
            qty,
            unitCost: unitCost,
            note: _note.text.trim(),
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(widget.direction == 'in'
                ? 'Stock updated (+$qty)'
                : 'Stock updated (−$qty)'),
            behavior: SnackBarBehavior.floating));
        Navigator.pop(context);
      }
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
    final p = AppPalette.of(context);
    final isIn = widget.direction == 'in';
    return AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: p.border),
      ),
      titlePadding: const EdgeInsets.fromLTRB(20, 20, 12, 0),
      contentPadding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
      title: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(isIn ? 'Stock In' : 'Stock Out',
              style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: p.textPrimary)),
          IconButton(
            visualDensity: VisualDensity.compact,
            onPressed: () => Navigator.pop(context),
            icon: HeroIcon(HeroPaths.xMark, size: 18, color: p.textFaint),
          ),
        ],
      ),
      content: SizedBox(
        width: double.maxFinite,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.item.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: p.textMuted)),
            const SizedBox(height: 14),
            _DialogField(
              label: 'Quantity',
              controller: _qty,
              hint: '1',
              digits: true,
            ),
            const SizedBox(height: 12),
            _DialogField(
              label: 'Unit Cost',
              controller: _cost,
              hint: '0.00',
              digits: true,
              decimal: true,
            ),
            const SizedBox(height: 12),
            _DialogField(
              label: 'Note',
              controller: _note,
              hint: 'Optional note',
            ),
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      actions: [
        Row(
          children: [
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: _saving ? null : () => Navigator.pop(context),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    border: Border.all(color: p.border),
                    borderRadius: BorderRadius.circular(AppRadii.button),
                  ),
                  alignment: Alignment.center,
                  child: Text('CANCEL',
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1,
                          color: p.textPrimary)),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: _saving ? null : _submit,
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: p.ink,
                    borderRadius: BorderRadius.circular(AppRadii.button),
                  ),
                  alignment: Alignment.center,
                  child: Text(_saving ? 'SAVING…' : 'SAVE',
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1,
                          color: p.onInk)),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _DialogField extends StatelessWidget {
  const _DialogField({
    required this.label,
    required this.controller,
    this.hint,
    this.digits = false,
    this.decimal = false,
  });
  final String label;
  final TextEditingController controller;
  final String? hint;
  final bool digits;
  final bool decimal;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    TextInputType kb;
    if (digits) {
      kb = decimal
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.number;
    } else {
      kb = TextInputType.text;
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 10)),
        const SizedBox(height: 4),
        TextField(
          controller: controller,
          keyboardType: kb,
          style: TextStyle(
              fontSize: 14, fontWeight: FontWeight.w500, color: p.textPrimary),
          decoration: InputDecoration(
            isDense: true,
            hintText: hint,
            hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.ink, width: 1.5)),
          ),
        ),
      ],
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.totalValue, required this.totalProfit});
  final double totalValue;
  final double totalProfit;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final currency = NumberFormat.simpleCurrency();
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: p.ink,
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('INVENTORY SUMMARY',
              style: AppType.label(context, color: p.onInk.withValues(alpha: 0.7), size: 10)),
          const SizedBox(height: 16),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('TOTAL VALUE',
                  style: TextStyle(
                      fontFamily: 'Geist',
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.5,
                      color: p.onInk.withValues(alpha: 0.7))),
              Text(currency.format(totalValue),
                  style: TextStyle(
                      fontFamily: 'GeistMono',
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      color: p.onInk)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('POTENTIAL PROFIT',
                  style: TextStyle(
                      fontFamily: 'Geist',
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.5,
                      color: p.onInk.withValues(alpha: 0.7))),
              Text(currency.format(totalProfit),
                  style: TextStyle(
                      fontFamily: 'GeistMono',
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: p.onInk)),
            ],
          ),
        ],
      ),
    );
  }
}
