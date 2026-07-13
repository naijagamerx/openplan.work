import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/invoice.dart';
import '../repositories/invoices_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Invoices hub — pushed from the menu (NOT a primary tab), so a plain
/// Scaffold + AppBar with a back arrow. Mirrors mobile/views/invoices.php:
/// total-outstanding header, status filter pills, invoice rows, create FAB.
class InvoicesScreen extends ConsumerStatefulWidget {
  const InvoicesScreen({super.key});

  @override
  ConsumerState<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends ConsumerState<InvoicesScreen> {
  String? _statusFilter; // null = all

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final invoices = ref.watch(invoicesProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Invoices'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: p.ink,
        foregroundColor: p.onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/invoices/new');
          if (mounted) ref.invalidate(invoicesProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: p.onInk),
      ),
      body: invoices.when(
        loading: () => ListView(
          padding: const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 120),
          children: const [
            Skeleton(height: 110, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.document,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(invoicesProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.document,
                message: 'No invoices yet',
                actionLabel: 'New Invoice',
                onAction: () => context.push('/invoices/new'),
              ),
            );
          }

          final filtered = _statusFilter == null
              ? list
              : list.where((i) => i.status == _statusFilter).toList();

          final outstanding = list
              .where((i) => !i.isPaid)
              .fold<double>(0, (sum, i) => sum + i.total);

          return RefreshIndicator(
            onRefresh: () => ref.refresh(invoicesProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
              children: [
                FadeIn(
                  child: _OutstandingHeader(
                    amount: outstanding,
                    currency: list.first.currency,
                  ),
                ),
                const SizedBox(height: 16),
                _StatusPills(
                  selected: _statusFilter,
                  onSelected: (v) => setState(() => _statusFilter = v),
                  counts: _countByStatus(list),
                ),
                const SizedBox(height: 16),
                if (filtered.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No invoices match'),
                  )
                else
                  for (var i = 0; i < filtered.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 70).clamp(0, 420)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _InvoiceRow(invoice: filtered[i]),
                      ),
                    ),
              ],
            ),
          );
        },
      ),
    );
  }

  Map<String, int> _countByStatus(List<Invoice> list) {
    final counts = <String, int>{
      'draft': 0,
      'sent': 0,
      'paid': 0,
      'overdue': 0,
      'cancelled': 0,
    };
    for (final i in list) {
      counts.update(i.status, (v) => v + 1, ifAbsent: () => 1);
    }
    return counts;
  }
}

class _OutstandingHeader extends StatelessWidget {
  const _OutstandingHeader({required this.amount, required this.currency});
  final double amount;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fmt = NumberFormat.simpleCurrency(name: currency);
    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('TOTAL OUTSTANDING', style: AppType.label(context, color: p.textMuted, size: 11)),
          const SizedBox(height: 6),
          Text(
            fmt.format(amount),
            style: AppType.mono(size: 32, color: p.textPrimary),
          ),
        ],
      ),
    );
  }
}

class _StatusPills extends StatelessWidget {
  const _StatusPills({
    required this.selected,
    required this.onSelected,
    required this.counts,
  });

  final String? selected;
  final ValueChanged<String?> onSelected;
  final Map<String, int> counts;

  @override
  Widget build(BuildContext context) {
    final groups = <(String, String)>[
      ('all', 'All'),
      ('draft', 'Draft'),
      ('sent', 'Pending'),
      ('paid', 'Paid'),
      ('overdue', 'Overdue'),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final (value, label) in groups)
          _Pill(
            label: value == 'all' ? label : '$label ${counts[value] ?? 0}',
            selected: (selected ?? 'all') == value,
            accent: _accent(value),
            onTap: () => onSelected(value == 'all' ? null : value),
          ),
      ],
    );
  }

  Color? _accent(String value) {
    switch (value) {
      case 'paid':
        return Invoice.paidColor;
      case 'overdue':
        return Invoice.overdueColor;
      default:
        return null;
    }
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.selected,
    required this.onTap,
    this.accent,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fill = selected ? (accent ?? p.ink) : Colors.transparent;
    final fg = selected
        ? (accent != null ? Colors.white : p.onInk)
        : p.textPrimary;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: selected ? (accent ?? p.ink) : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 12,
            fontWeight: FontWeight.w700,
            letterSpacing: 0.5,
            color: fg,
          ),
        ),
      ),
    );
  }
}

class _InvoiceRow extends ConsumerWidget {
  const _InvoiceRow({required this.invoice});
  final Invoice invoice;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final fmt = NumberFormat.simpleCurrency(name: invoice.currency);
    final dueText = invoice.dueDate == null
        ? null
        : DateFormat('MMM j, y').format(invoice.dueDate!.toLocal());

    return SectionCard(
      child: InkWell(
        onTap: () async {
          await context.push('/invoices/${invoice.id}');
          if (context.mounted) ref.invalidate(invoicesProvider);
        },
        borderRadius: BorderRadius.circular(AppRadii.section),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    invoice.clientId.isEmpty ? 'Unknown Client' : invoice.clientId,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: p.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    invoice.invoiceNumber.toUpperCase(),
                    style: AppType.label(context, color: p.textFaint, size: 10),
                  ),
                  if (dueText != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      'DUE $dueText'.toUpperCase(),
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.5,
                        color: _dueColor(p),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  fmt.format(invoice.total),
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: p.textPrimary,
                  ),
                ),
                const SizedBox(height: 6),
                _StatusBadge(status: invoice.status),
                const SizedBox(height: 8),
                HeroIcon(HeroPaths.chevronRight, size: 16, color: p.textFaint),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Color _dueColor(AppPalette p) {
    if (invoice.status == 'overdue') return Invoice.overdueColor;
    return p.textFaint;
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final accent = Invoice.statusColor(status);
    final Color color;
    switch (status.toLowerCase()) {
      case 'paid':
        color = Invoice.paidColor;
      case 'overdue':
        color = Invoice.overdueColor;
      case 'sent':
        color = p.ink;
      case 'draft':
        color = p.textFaint;
      default:
        color = p.textFaint; // cancelled
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: accent == Colors.transparent ? Colors.transparent : accent,
        border: Border.all(
          color: accent == Colors.transparent ? p.border : accent,
        ),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        Invoice.statusLabel(status).toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 1,
          color: accent == Colors.transparent ? color : Colors.white,
        ),
      ),
    );
  }
}
