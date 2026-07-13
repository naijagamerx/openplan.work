import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/finance_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Create or edit a finance transaction. Mirrors mobile/views/transaction-form.php.
/// Pass [transactionId] to edit; omit to create.
class TransactionFormScreen extends ConsumerStatefulWidget {
  const TransactionFormScreen({super.key, this.transactionId});

  final String? transactionId;
  bool get isEdit => transactionId != null;

  @override
  ConsumerState<TransactionFormScreen> createState() =>
      _TransactionFormScreenState();
}

class _TransactionFormScreenState extends ConsumerState<TransactionFormScreen> {
  final _amount = TextEditingController();
  final _category = TextEditingController();
  final _description = TextEditingController();

  String _type = 'expense'; // income|expense
  DateTime? _date;
  bool _loading = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _date = DateTime.now();
    if (widget.isEdit) _loadForEdit();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final t = await ref
          .read(financeRepositoryProvider)
          .fetch(widget.transactionId!);
      if (t != null && mounted) {
        _type = t.type;
        _amount.text = t.amount == 0 ? '' : _formatAmount(t.amount);
        _category.text = t.category;
        _description.text = t.description;
        _date = t.date;
        setState(() {});
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _formatAmount(double v) {
    // Trim trailing zeros so the amount field stays clean for editing.
    final s = v.toStringAsFixed(2);
    return s.contains('.') ? s.replaceFirst(RegExp(r'\.?0+$'), '') : s;
  }

  @override
  void dispose() {
    _amount.dispose();
    _category.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _save() async {
    final amount = double.tryParse(_amount.text);
    if (amount == null || amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Enter a valid amount'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    final category = _category.text.trim().isEmpty
        ? 'General'
        : _category.text.trim();
    final description = _description.text.trim();
    if (description.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Description is required'),
          behavior: SnackBarBehavior.floating));
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(financeRepositoryProvider);
    try {
      if (widget.isEdit) {
        await repo.update(
          id: widget.transactionId!,
          type: _type,
          amount: amount,
          date: _date,
          category: category,
          description: description,
        );
      } else {
        await repo.create(
          type: _type,
          amount: amount,
          date: _date ?? DateTime.now(),
          category: category,
          description: description,
        );
      }
      ref.invalidate(financeProvider);
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
          title: const Text('Edit Transaction'),
        ),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 80, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 56, radius: AppRadii.button),
          ],
        ),
      );
    }

    // Semantic tint for the type chips (green for income, red for expense).
    Color typeColor(String t) =>
        t == 'income' ? const Color(0xFF16A34A) : const Color(0xFFDC2626);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Transaction' : 'New Transaction'),
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
                  ChipSelector<String>(
                    label: 'Type',
                    selected: _type,
                    onSelected: (v) => setState(() => _type = v),
                    colorFor: typeColor,
                    options: const {
                      'income': 'Income',
                      'expense': 'Expense',
                    },
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Amount',
                    controller: _amount,
                    hint: '0.00',
                    keyboardType:
                        const TextInputType.numberWithOptions(decimal: true),
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  _DateRow(
                    label: 'Date',
                    value: _date,
                    onPick: _pickDate,
                    onClear: () => setState(() => _date = null),
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Category',
                    controller: _category,
                    hint: 'e.g. Sales',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Description',
                    controller: _description,
                    hint: 'Transaction description',
                    maxLines: 3,
                  ),
                  if (widget.isEdit) ...[
                    const SizedBox(height: AppSpacing.gap),
                    _DeleteTile(
                      onTap: _delete,
                    ),
                  ],
                ],
              ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: widget.isEdit ? 'Save' : 'Create',
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _delete() async {
    final ok = await confirmDialog(context,
        title: 'Delete transaction',
        message: 'Delete this transaction? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(financeRepositoryProvider).delete(widget.transactionId!);
      ref.invalidate(financeProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }
}

/// Date picker row — mirrors the _DateRow in task_form_screen.dart.
class _DateRow extends StatelessWidget {
  const _DateRow({
    required this.label,
    required this.value,
    required this.onPick,
    required this.onClear,
  });
  final String label;
  final DateTime? value;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 8),
        GestureDetector(
          onTap: onPick,
          behavior: HitTestBehavior.opaque,
          child: Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              border: Border.all(color: p.border),
              borderRadius: BorderRadius.circular(AppRadii.button),
            ),
            child: Row(
              children: [
                HeroIcon(HeroPaths.calendar, size: 18, color: p.textMuted),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    value == null
                        ? 'No date'
                        : '${value!.day}/${value!.month}/${value!.year}',
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: value == null ? p.textFaint : p.textPrimary),
                  ),
                ),
                if (value != null)
                  GestureDetector(
                    onTap: onClear,
                    child: HeroIcon(HeroPaths.xMark,
                        size: 16, color: p.textFaint),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _DeleteTile extends StatelessWidget {
  const _DeleteTile({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: const Color(0xFFDC2626)),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const HeroIcon(HeroPaths.trash, size: 16, color: Color(0xFFDC2626)),
            const SizedBox(width: 8),
            Text('DELETE TRANSACTION',
                style: AppType.label(
                    context, color: const Color(0xFFDC2626), size: 11)),
          ],
        ),
      ),
    );
  }
}
