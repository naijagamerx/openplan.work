import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../repositories/inventory_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Inventory product detail with transaction history + edit/delete actions.
/// Mirrors mobile/views/inventory-history.php (detail + ledger combined).
class InventoryItemScreen extends ConsumerStatefulWidget {
  const InventoryItemScreen({super.key, required this.itemId});
  final String itemId;

  @override
  ConsumerState<InventoryItemScreen> createState() =>
      _InventoryItemScreenState();
}

class _InventoryItemScreenState extends ConsumerState<InventoryItemScreen> {
  late final FutureProvider<List<dynamic>> _provider;

  @override
  void initState() {
    super.initState();
    // Fetch the product and its history together so both reload together.
    _provider = FutureProvider<List<dynamic>>((ref) async {
      final repo = ref.watch(inventoryRepositoryProvider);
      final item = await repo.fetch(widget.itemId);
      final history =
          item == null ? <Map<String, dynamic>>[] : await repo.history(item.id);
      return [item, history];
    });
  }

  void _reload() => ref.invalidate(_provider);

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(_provider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Inventory'),
        actions: [
          IconButton(
            icon: const HeroIcon(HeroPaths.pencil, size: 20),
            onPressed: () async {
              await context.push('/inventory/${widget.itemId}/edit');
              if (mounted) _reload();
            },
          ),
        ],
      ),
      body: result.when(
        loading: () => _Loading(),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.cube,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: _reload,
          ),
        ),
        data: (data) {
          final item = data[0];
          final history = data[1] as List<Map<String, dynamic>>;
          if (item == null) {
            return const Center(
                child: EmptyState(
                    iconPath: HeroPaths.cube, message: 'Product not found'));
          }
          return _Body(
            data: data,
            history: history,
            onChanged: _reload,
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
      padding: const EdgeInsets.all(AppSpacing.page),
      children: const [
        Skeleton(height: 80, radius: AppRadii.card),
        SizedBox(height: 16),
        Row(children: [
          Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
          SizedBox(width: 12),
          Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
        ]),
        SizedBox(height: 16),
        Skeleton(height: 200, radius: AppRadii.section),
      ],
    );
  }
}

class _Body extends ConsumerStatefulWidget {
  const _Body({required this.data, required this.history, required this.onChanged});
  final List<dynamic> data;
  final List<Map<String, dynamic>> history;
  final VoidCallback onChanged;

  @override
  ConsumerState<_Body> createState() => _BodyState();
}

class _BodyState extends ConsumerState<_Body> {
  final _currency = NumberFormat.simpleCurrency();

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final item = widget.data[0];
    final stockColor =
        item.isLowStock ? const Color(0xFFDC2626) : p.textPrimary;

    // Aggregate history totals.
    int totalIn = 0;
    int totalOut = 0;
    double totalExpense = 0;
    for (final t in widget.history) {
      final qty = (t['quantity'] is num)
          ? (t['quantity'] as num).toInt()
          : int.tryParse('${t['quantity']}') ?? 0;
      if (t['type'] == 'in') {
        totalIn += qty;
        totalExpense += (t['totalCost'] is num)
            ? (t['totalCost'] as num).toDouble()
            : double.tryParse('${t['totalCost']}') ?? 0;
      } else if (t['type'] == 'out') {
        totalOut += qty;
      }
    }

    return RefreshIndicator(
      onRefresh: () async => widget.onChanged(),
      child: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          // Header
          FadeIn(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    if (item.category.isNotEmpty) _Tag(item.category),
                    _Tag(item.isLowStock ? 'Low Stock' : 'In Stock',
                        muted: !item.isLowStock,
                        alert: item.isLowStock),
                  ],
                ),
                const SizedBox(height: 12),
                Text(item.name,
                    style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: p.textPrimary)),
                const SizedBox(height: 4),
                Text('SKU: ${item.sku.isEmpty ? "—" : item.sku}',
                    style: TextStyle(
                        fontFamily: 'GeistMono',
                        fontSize: 12,
                        color: p.textFaint)),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Stat grid
          FadeIn(
            delay: const Duration(milliseconds: 60),
            child: GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.5,
              children: [
                _MiniStat(
                    label: 'In Stock', value: '${item.stock}', valueColor: stockColor),
                _MiniStat(
                    label: 'Reorder At', value: '${item.minStock}'),
                _MiniStat(
                    label: 'Sell Price',
                    value: _currency.format(item.price)),
                _MiniStat(
                    label: 'Unit Cost', value: _currency.format(item.cost)),
                _MiniStat(
                    label: 'Inventory Value',
                    value: _currency.format(item.value)),
                _MiniStat(
                  label: 'Potential Profit',
                  value: _currency.format(item.potentialProfit),
                  valueColor: item.potentialProfit >= 0
                      ? const Color(0xFF16A34A)
                      : const Color(0xFFDC2626),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Description (if any)
          if (item.description.trim().isNotEmpty) ...[
            FadeIn(
              delay: const Duration(milliseconds: 100),
              child: SectionCard(
                heading: 'Description',
                child: Text(item.description,
                    style: TextStyle(
                        fontSize: 14, height: 1.5, color: p.textMuted)),
              ),
            ),
            const SizedBox(height: AppSpacing.gap),
          ],

          // History totals
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: Row(
              children: [
                Expanded(
                    child: _HistoryStat(
                        label: 'In', value: '$totalIn', color: const Color(0xFF16A34A))),
                const SizedBox(width: 8),
                Expanded(
                    child: _HistoryStat(
                        label: 'Out', value: '$totalOut', color: const Color(0xFFDC2626))),
                const SizedBox(width: 8),
                Expanded(
                    child: _HistoryStat(
                        label: 'Expense', value: _currency.format(totalExpense))),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Transaction ledger
          FadeIn(
            delay: const Duration(milliseconds: 160),
            child: SectionCard(
              heading: 'History',
              trailing: Text('${widget.history.length}',
                  style: AppType.label(context, color: p.textFaint)),
              child: widget.history.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Center(
                        child: Text('No transactions yet',
                            style: TextStyle(
                                fontSize: 13, color: p.textFaint)),
                      ),
                    )
                  : Column(
                      children: [
                        for (var i = 0; i < widget.history.length; i++) ...[
                            if (i > 0) Divider(height: 1, color: p.border),
                            _HistoryRow(entry: widget.history[i]),
                          ],
                      ],
                    ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Actions
          FadeIn(
            delay: const Duration(milliseconds: 200),
            child: Row(
              children: [
                Expanded(
                  child: _ActionTile(
                    icon: HeroPaths.plus,
                    label: 'Stock In',
                    onTap: () => _adjust(ref, 'in'),
                    primary: true,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionTile(
                    icon: HeroPaths.arrowTrendDown,
                    label: 'Stock Out',
                    onTap: () => _adjust(ref, 'out'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionTile(
                    icon: HeroPaths.trash,
                    label: 'Delete',
                    destructive: true,
                    onTap: _delete,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _adjust(WidgetRef ref, String direction) async {
    await showDialog<void>(
      context: context,
      builder: (_) => _AdjustDialog(item: widget.data[0], direction: direction),
    );
    if (mounted) {
      widget.onChanged();
      ref.invalidate(inventoryProvider);
    }
  }

  Future<void> _delete() async {
    final item = widget.data[0];
    final ok = await confirmDialog(context,
        title: 'Delete product',
        message: 'Delete "${item.name}"? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(inventoryRepositoryProvider).delete(item.id);
      ref.invalidate(inventoryProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }
}

class _Tag extends StatelessWidget {
  const _Tag(this.label, {this.muted = false, this.alert = false});
  final String label;
  final bool muted;
  final bool alert;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final Color bg;
    final Color fg;
    if (alert) {
      bg = const Color(0xFFDC2626);
      fg = Colors.white;
    } else if (muted) {
      bg = p.border;
      fg = p.textMuted;
    } else {
      bg = p.ink;
      fg = p.onInk;
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(label.toUpperCase(),
          style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.5,
              color: fg)),
    );
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat(
      {required this.label, required this.value, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textFaint, size: 9),
              maxLines: 1,
              overflow: TextOverflow.ellipsis),
          const SizedBox(height: 6),
          Text(value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: valueColor ?? p.textPrimary)),
        ],
      ),
    );
  }
}

class _HistoryStat extends StatelessWidget {
  const _HistoryStat(
      {required this.label, required this.value, this.color});
  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textFaint, size: 9)),
          const SizedBox(height: 4),
          Text(value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: color ?? p.textPrimary)),
        ],
      ),
    );
  }
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({required this.entry});
  final Map<String, dynamic> entry;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final type = (entry['type'] ?? '').toString();
    final isIn = type == 'in';
    final qty = (entry['quantity'] is num)
        ? (entry['quantity'] as num).toInt()
        : int.tryParse('${entry['quantity']}') ?? 0;
    final totalCost = (entry['totalCost'] is num)
        ? (entry['totalCost'] as num).toDouble()
        : double.tryParse('${entry['totalCost']}') ?? 0;
    final note = (entry['note'] ?? '').toString();
    final created = entry['createdAt']?.toString() ?? '';

    DateTime? dt;
    if (created.isNotEmpty) dt = DateTime.tryParse(created);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(isIn ? 'IN' : 'OUT',
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.5,
                        color: isIn
                            ? const Color(0xFF16A34A)
                            : const Color(0xFFDC2626))),
                if (dt != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      DateFormat.yMMMd().add_jm().format(dt),
                      style: TextStyle(fontSize: 11, color: p.textFaint),
                    ),
                  ),
                if (totalCost > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      NumberFormat.simpleCurrency().format(totalCost),
                      style: TextStyle(
                          fontFamily: 'GeistMono',
                          fontSize: 11,
                          color: p.textFaint),
                    ),
                  ),
                if (note.trim().isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(note,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 11, color: p.textMuted)),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text('${isIn ? "+" : "−"}$qty',
              style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: isIn
                      ? const Color(0xFF16A34A)
                      : const Color(0xFFDC2626))),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.destructive = false,
    this.primary = false,
  });
  final String icon;
  final String label;
  final VoidCallback onTap;
  final bool destructive;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = destructive
        ? const Color(0xFFDC2626)
        : (primary ? p.onInk : p.textPrimary);
    final fill = primary ? p.ink : p.surface;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: primary ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.card),
        ),
        child: Column(
          children: [
            HeroIcon(icon, size: 20, color: color),
            const SizedBox(height: 6),
            Text(label.toUpperCase(),
                style: AppType.label(context, color: color, size: 10),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}

/// Stock adjustment dialog (mirrors the list screen's, kept local to detail).
class _AdjustDialog extends ConsumerStatefulWidget {
  const _AdjustDialog({required this.item, required this.direction});
  final dynamic item;
  final String direction;

  @override
  ConsumerState<_AdjustDialog> createState() => _AdjustDialogState();
}

class _AdjustDialogState extends ConsumerState<_AdjustDialog> {
  final _qty = TextEditingController(text: '1');
  final _cost = TextEditingController();
  final _note = TextEditingController();
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    if ((widget.item.cost as double) > 0) {
      _cost.text = (widget.item.cost as double).toStringAsFixed(2);
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
            widget.item.id as String,
            widget.direction,
            qty,
            unitCost: unitCost,
            note: _note.text.trim(),
          );
      if (mounted) Navigator.pop(context);
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
            Text(widget.item.name as String,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: p.textMuted)),
            const SizedBox(height: 14),
            _Field(label: 'Quantity', controller: _qty, hint: '1', digits: true),
            const SizedBox(height: 12),
            _Field(
                label: 'Unit Cost',
                controller: _cost,
                hint: '0.00',
                digits: true,
                decimal: true),
            const SizedBox(height: 12),
            _Field(label: 'Note', controller: _note, hint: 'Optional note'),
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

class _Field extends StatelessWidget {
  const _Field({
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
