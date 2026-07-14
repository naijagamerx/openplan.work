import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/advanced_invoice.dart';
import '../repositories/advanced_invoices_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_bottom_nav.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Advanced-invoices list. Mirrors mobile/views/advanced-invoices.php:
/// total-outstanding header, Unpaid/Paid/Draft filter pills, customer rows.
/// View-only (the web bounces editing to the desktop form), so no create FAB.
class AdvancedInvoicesScreen extends ConsumerStatefulWidget {
  const AdvancedInvoicesScreen({super.key});

  @override
  ConsumerState<AdvancedInvoicesScreen> createState() =>
      _AdvancedInvoicesScreenState();
}

class _AdvancedInvoicesScreenState
    extends ConsumerState<AdvancedInvoicesScreen> {
  String? _statusFilter; // null = all

  @override
  Widget build(BuildContext context) {
    final invoices = ref.watch(advancedInvoicesProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Advanced Invoices'),
      ),
      bottomNavigationBar: const AppNavBar(),
      body: invoices.when(
        loading: () => ListView(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.page, 16, AppSpacing.page, 120),
          children: const [
            Skeleton(height: 110, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 120, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 120, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.document,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(advancedInvoicesProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return const Center(
              child: EmptyState(
                iconPath: HeroPaths.document,
                message: 'No advanced invoices yet',
              ),
            );
          }

          final filtered = _statusFilter == null
              ? list
              : list.where((i) => i.status == _statusFilter).toList();
          final outstanding = list
              .where((i) => !i.isPaid)
              .fold<double>(0, (s, i) => s + i.totalDue);

          return RefreshIndicator(
            onRefresh: () => ref.refresh(advancedInvoicesProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 120),
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
                        iconPath: HeroPaths.search,
                        message: 'None match this filter'),
                  )
                else
                  for (var i = 0; i < filtered.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 70).clamp(0, 420)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _AdvRow(invoice: filtered[i]),
                      ),
                    ),
              ],
            ),
          );
        },
      ),
    );
  }

  Map<String, int> _countByStatus(List<AdvancedInvoice> list) {
    final counts = <String, int>{'unpaid': 0, 'paid': 0, 'draft': 0};
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
    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('TOTAL OUTSTANDING',
              style: AppType.label(context, color: p.textMuted, size: 11)),
          const SizedBox(height: 6),
          Text(
            _money(amount, currency),
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
      ('unpaid', 'Unpaid'),
      ('paid', 'Paid'),
      ('draft', 'Draft'),
    ];
    final p = AppPalette.of(context);
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final (value, label) in groups)
          GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => onSelected(value == 'all' ? null : value),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 120),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
              decoration: BoxDecoration(
                color: (selected ?? 'all') == value ? p.ink : Colors.transparent,
                border: Border.all(
                    color:
                        (selected ?? 'all') == value ? p.ink : p.border),
                borderRadius: BorderRadius.circular(AppRadii.pill),
              ),
              child: Text(
                value == 'all' ? label : '$label ${counts[value] ?? 0}',
                style: TextStyle(
                  fontFamily: 'Geist',
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                  color: (selected ?? 'all') == value ? p.onInk : p.textPrimary,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _AdvRow extends ConsumerWidget {
  const _AdvRow({required this.invoice});
  final AdvancedInvoice invoice;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final dateText = invoice.invoiceDate != null
        ? DateFormat('MMM d, y').format(invoice.invoiceDate!.toLocal())
        : null;

    return SectionCard(
      child: InkWell(
        onTap: () => context.push('/advanced-invoices/${invoice.id}'),
        borderRadius: BorderRadius.circular(AppRadii.section),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    invoice.customer.name.isEmpty
                        ? 'No customer'
                        : invoice.customer.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: p.textPrimary),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    invoice.invoiceNumber.isEmpty
                        ? 'DRAFT'
                        : invoice.invoiceNumber.toUpperCase(),
                    style: AppType.label(context, color: p.textFaint, size: 10),
                  ),
                  if (dateText != null) ...[
                    const SizedBox(height: 4),
                    Text(dateText.toUpperCase(),
                        style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1.5,
                            color: p.textFaint)),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  _money(invoice.totalDue, invoice.currency),
                  style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: p.textPrimary),
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
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final lower = status.toLowerCase();
    final Color accent;
    switch (lower) {
      case 'paid':
        accent = AdvancedInvoice.paidColor;
      case 'overdue':
        accent = AdvancedInvoice.overdueColor;
      default:
        accent = Colors.transparent; // unpaid/draft → outline
    }
    final filled = accent != Colors.transparent;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: filled ? accent : Colors.transparent,
        border: Border.all(color: filled ? accent : p.border),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        AdvancedInvoice.statusLabel(status).toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 1,
          color: filled ? Colors.white : p.textMuted,
        ),
      ),
    );
  }
}

/// Shared currency formatter (falls back to locale default for unknown codes).
String _money(double v, String currency) {
  try {
    return NumberFormat.simpleCurrency(name: currency).format(v);
  } catch (_) {
    return NumberFormat.simpleCurrency().format(v);
  }
}
