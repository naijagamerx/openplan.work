import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/invoice.dart';
import '../repositories/invoices_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Create or edit an invoice. Mirrors mobile/views/invoice-form.php.
/// Pass [invoiceId] to edit; omit (null) to create. The list routes to
/// `/invoices/:id` (edit) and the FAB to `/invoices/new` (create).
class InvoiceFormScreen extends ConsumerStatefulWidget {
  const InvoiceFormScreen({super.key, this.invoiceId});

  final String? invoiceId;
  bool get isEdit => invoiceId != null;

  @override
  ConsumerState<InvoiceFormScreen> createState() => _InvoiceFormScreenState();
}

class _InvoiceFormScreenState extends ConsumerState<InvoiceFormScreen> {
  final _client = TextEditingController();
  final _invoiceNumber = TextEditingController();
  final _taxRate = TextEditingController(text: '0');
  final _notes = TextEditingController();

  String _currency = 'USD';
  String _status = 'draft';
  DateTime? _dueDate;
  DateTime? _issueDate;

  /// Editable line-item rows. Each holds three controllers (description,
  /// quantity, unitPrice) plus the invoice id when editing an existing line.
  final List<_LineRow> _rows = [];

  bool _loading = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) {
      _loadForEdit();
    } else {
      _dueDate = DateTime.now().add(const Duration(days: 30));
      _issueDate = DateTime.now();
      _rows.add(_LineRow());
    }
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final inv = await ref.read(invoicesRepositoryProvider).fetch(widget.invoiceId!);
      if (inv != null && mounted) {
        _client.text = inv.clientId;
        _invoiceNumber.text = inv.invoiceNumber;
        _taxRate.text = inv.taxRate == 0 ? '0' : inv.taxRate.toStringAsFixed(2);
        _notes.text = inv.notes;
        _currency = inv.currency;
        _status = inv.status;
        _dueDate = inv.dueDate;
        _issueDate = inv.issueDate;
        _rows
          ..clear()
          ..addAll(inv.lineItems.map((li) => _LineRow.from(li)));
        if (_rows.isEmpty) _rows.add(_LineRow());
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _client.dispose();
    _invoiceNumber.dispose();
    _taxRate.dispose();
    _notes.dispose();
    for (final r in _rows) {
      r.dispose();
    }
    super.dispose();
  }

  double get _subtotal =>
      _rows.fold(0.0, (s, r) => s + (r.quantity * r.unitPrice));
  double get _taxRateValue => double.tryParse(_taxRate.text) ?? 0;
  double get _taxAmount => _subtotal * (_taxRateValue / 100);
  double get _total => _subtotal + _taxAmount;

  Future<void> _pickDate(DateTime? current, ValueChanged<DateTime> onPicked) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked != null) setState(() => onPicked(picked));
  }

  void _addRow() => setState(() => _rows.add(_LineRow()));

  void _removeRow(int i) {
    setState(() {
      _rows[i].dispose();
      _rows.removeAt(i);
      if (_rows.isEmpty) _rows.add(_LineRow());
    });
  }

  Future<void> _save() async {
    final client = _client.text.trim();
    if (client.isEmpty) {
      _toast('Client is required');
      return;
    }
    if (_dueDate == null) {
      _toast('Due date is required');
      return;
    }
    final items = _rows
        .map((r) => r.toLineItem())
        .where((li) => li.description.trim().isNotEmpty && li.quantity > 0)
        .toList();
    if (items.isEmpty) {
      _toast('Add at least one valid line item');
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(invoicesRepositoryProvider);
    final body = {
      'clientId': client,
      if (_invoiceNumber.text.trim().isNotEmpty)
        'invoiceNumber': _invoiceNumber.text.trim(),
      'lineItems': items.map((i) => i.toBody()).toList(),
      'subtotal': _subtotal,
      'taxRate': _taxRateValue,
      'taxAmount': _taxAmount,
      'total': _total,
      'currency': _currency,
      'status': _status,
      'dueDate': _fmt(_dueDate!),
      if (_issueDate != null) 'issueDate': _fmt(_issueDate!),
      if (_notes.text.trim().isNotEmpty) 'notes': _notes.text.trim(),
    };

    try {
      if (widget.isEdit) {
        await repo.update(widget.invoiceId!, body);
      } else {
        await repo.create(body);
      }
      ref.invalidate(invoicesProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) _toast('$e');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _toast(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      behavior: SnackBarBehavior.floating,
    ));
  }

  static String _fmt(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fmt = NumberFormat.simpleCurrency(name: _currency);

    if (_loading) {
      return Scaffold(
        appBar: AppBar(
          leading: IconButton(
            icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
            onPressed: () => context.pop(),
          ),
          title: const Text('Edit Invoice'),
        ),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 24),
          children: const [
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 120, radius: AppRadii.section),
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
        title: Text(widget.isEdit ? 'Edit Invoice' : 'New Invoice'),
      ),
      body: SafeArea(
        top: false,
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(AppSpacing.page, 8, AppSpacing.page, 24),
                children: [
                  LabeledField(
                    label: 'Client',
                    controller: _client,
                    hint: 'Client name',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'Invoice number',
                    controller: _invoiceNumber,
                    hint: widget.isEdit ? '' : 'Auto-generated on create',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Currency',
                    selected: _currency,
                    onSelected: (v) => setState(() => _currency = v),
                    options: const {
                      'USD': 'USD',
                      'EUR': 'EUR',
                      'GBP': 'GBP',
                      'ZAR': 'ZAR',
                    },
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Status',
                    selected: _status,
                    onSelected: (v) => setState(() => _status = v),
                    options: const {
                      'draft': 'Draft',
                      'sent': 'Sent',
                      'paid': 'Paid',
                      'overdue': 'Overdue',
                      'cancelled': 'Cancelled',
                    },
                  ),
                  const SizedBox(height: 20),
                  _DateRow(
                    label: 'Issue date',
                    value: _issueDate,
                    onPick: (d) => _pickDate(_issueDate, (v) => _issueDate = v),
                    onClear: () => setState(() => _issueDate = null),
                  ),
                  const SizedBox(height: 16),
                  _DateRow(
                    label: 'Due date',
                    value: _dueDate,
                    onPick: (d) => _pickDate(_dueDate, (v) => _dueDate = v),
                    onClear: () => setState(() => _dueDate = null),
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'Tax rate (%)',
                    controller: _taxRate,
                    hint: '0',
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    onChanged: (_) => setState(() {}),
                  ),
                  const SizedBox(height: 24),
                  // ── Line items ──────────────────────────────────────────
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('LINE ITEMS',
                          style: AppType.label(context, color: p.textMuted, size: 11)),
                      GestureDetector(
                        onTap: _addRow,
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          HeroIcon(HeroPaths.plus, size: 14, color: p.ink),
                          const SizedBox(width: 4),
                          Text('ADD', style: AppType.label(context, color: p.ink, size: 11)),
                        ]),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  for (var i = 0; i < _rows.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 40).clamp(0, 240)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _LineItemCard(
                          row: _rows[i],
                          currencyFmt: fmt,
                          onChanged: () => setState(() {}),
                          onRemove: () => _removeRow(i),
                        ),
                      ),
                    ),
                  const SizedBox(height: 8),
                  _TotalsCard(
                    subtotal: _subtotal,
                    taxRate: _taxRateValue,
                    taxAmount: _taxAmount,
                    total: _total,
                    fmt: fmt,
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Notes',
                    controller: _notes,
                    hint: 'Payment notes, terms, or comments…',
                    maxLines: 4,
                  ),
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
}

/// A single editable line-item row: three text controllers + a remove handle.
class _LineRow {
  _LineRow()
      : description = TextEditingController(),
        qtyCtrl = TextEditingController(text: '1'),
        priceCtrl = TextEditingController(text: '0');

  _LineRow.from(LineItem li)
      : description = TextEditingController(text: li.description),
        qtyCtrl = TextEditingController(text: _trimNum(li.quantity)),
        priceCtrl = TextEditingController(text: _trimNum(li.unitPrice));

  final TextEditingController description;
  final TextEditingController qtyCtrl;
  final TextEditingController priceCtrl;

  double get quantity => double.tryParse(qtyCtrl.text) ?? 0;
  double get unitPrice => double.tryParse(priceCtrl.text) ?? 0;
  double get total => quantity * unitPrice;

  LineItem toLineItem() => LineItem(
        description: description.text,
        quantity: quantity,
        unitPrice: unitPrice,
      );

  void dispose() {
    description.dispose();
    qtyCtrl.dispose();
    priceCtrl.dispose();
  }

  static String _trimNum(double v) =>
      v == v.roundToDouble() ? v.toInt().toString() : v.toString();
}

class _LineItemCard extends StatelessWidget {
  const _LineItemCard({
    required this.row,
    required this.currencyFmt,
    required this.onChanged,
    required this.onRemove,
  });

  final _LineRow row;
  final NumberFormat currencyFmt;
  final VoidCallback onChanged;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          TextField(
            controller: row.description,
            textCapitalization: TextCapitalization.sentences,
            textInputAction: TextInputAction.next,
            onChanged: (_) => onChanged(),
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500, color: p.textPrimary),
            decoration: InputDecoration(
              isDense: true,
              hintText: 'Description',
              hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
              contentPadding: const EdgeInsets.only(top: 8, bottom: 8),
              border: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
              enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
              focusedBorder: UnderlineInputBorder(
                  borderSide: BorderSide(color: p.ink, width: 1.5)),
            ),
          ),
          const SizedBox(height: 8),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: TextField(
                  controller: row.qtyCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  onChanged: (_) => onChanged(),
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500, color: p.textPrimary),
                  decoration: InputDecoration(
                    isDense: true,
                    hintText: 'Qty',
                    hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
                    contentPadding: const EdgeInsets.only(top: 8, bottom: 8),
                    border: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
                    enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
                    focusedBorder: UnderlineInputBorder(
                        borderSide: BorderSide(color: p.ink, width: 1.5)),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: row.priceCtrl,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  onChanged: (_) => onChanged(),
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w500, color: p.textPrimary),
                  decoration: InputDecoration(
                    isDense: true,
                    hintText: 'Unit price',
                    hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
                    contentPadding: const EdgeInsets.only(top: 8, bottom: 8),
                    border: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
                    enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
                    focusedBorder: UnderlineInputBorder(
                        borderSide: BorderSide(color: p.ink, width: 1.5)),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('LINE TOTAL',
                  style: AppType.label(context, color: p.textFaint, size: 10)),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    currencyFmt.format(row.total),
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: p.textPrimary,
                    ),
                  ),
                  const SizedBox(width: 10),
                  GestureDetector(
                    onTap: onRemove,
                    behavior: HitTestBehavior.opaque,
                    child: HeroIcon(HeroPaths.trash, size: 16, color: p.textFaint),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TotalsCard extends StatelessWidget {
  const _TotalsCard({
    required this.subtotal,
    required this.taxRate,
    required this.taxAmount,
    required this.total,
    required this.fmt,
  });

  final double subtotal;
  final double taxRate;
  final double taxAmount;
  final double total;
  final NumberFormat fmt;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    Widget line(String label, String value, {bool emphasize = false}) => Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label.toUpperCase(),
                  style: AppType.label(
                      context,
                      color: emphasize ? p.textPrimary : p.textMuted,
                      size: emphasize ? 12 : 10)),
              Text(
                value,
                style: TextStyle(
                  fontFamily: emphasize ? 'GeistMono' : 'Geist',
                  fontSize: emphasize ? 18 : 13,
                  fontWeight: FontWeight.w800,
                  color: p.textPrimary,
                ),
              ),
            ],
          ),
        );

    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.canvas,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Column(
        children: [
          line('Subtotal', fmt.format(subtotal)),
          line('Tax (${_trimNum(taxRate)}%)', fmt.format(taxAmount)),
          Divider(height: 1, color: p.border),
          const SizedBox(height: 8),
          line('Total', fmt.format(total), emphasize: true),
        ],
      ),
    );
  }

  static String _trimNum(double v) =>
      v == v.roundToDouble() ? v.toInt().toString() : v.toStringAsFixed(2);
}

class _DateRow extends StatelessWidget {
  const _DateRow({
    required this.label,
    required this.value,
    required this.onPick,
    required this.onClear,
  });

  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onPick;
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
          onTap: () => onPick(value ?? DateTime.now()),
          behavior: HitTestBehavior.opaque,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
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
                        : DateFormat('MMM j, y').format(value!.toLocal()),
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: value == null ? p.textFaint : p.textPrimary),
                  ),
                ),
                if (value != null)
                  GestureDetector(
                    onTap: onClear,
                    child: HeroIcon(HeroPaths.xMark, size: 16, color: p.textFaint),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
