import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/transaction.dart';
import '../repositories/finance_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Finance overview. Mirrors mobile/views/finance.php: revenue / expenses / net
/// stat cards, a 6-month net pulse line, filter pills and the transaction list.
class FinanceScreen extends ConsumerStatefulWidget {
  const FinanceScreen({super.key});

  @override
  ConsumerState<FinanceScreen> createState() => _FinanceScreenState();
}

class _FinanceScreenState extends ConsumerState<FinanceScreen> {
  /// null = all (mirrors finance.php ?filter=all|income|expense).
  String? _filter;

  @override
  Widget build(BuildContext context) {
    final finance = ref.watch(financeProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Finance'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/finance/new');
          if (mounted) ref.invalidate(financeProvider);
        },
        child: HeroIcon(HeroPaths.plus,
            size: 24, color: AppPalette.of(context).onInk),
      ),
      body: finance.when(
        loading: () => ListView(
          padding:
              const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
          children: const [
            Skeleton(height: 96, radius: AppRadii.card),
            SizedBox(height: 12),
            Skeleton(height: 96, radius: AppRadii.card),
            SizedBox(height: 12),
            Skeleton(height: 96, radius: AppRadii.card),
            SizedBox(height: AppSpacing.gap),
            Skeleton(height: 140, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.banknotes,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(financeProvider),
            ),
          ),
        ),
        data: (list) => RefreshIndicator(
          onRefresh: () => ref.refresh(financeProvider.future),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
                AppSpacing.page, 12, AppSpacing.page, 120),
            children: [
              FadeIn(
                child: _StatCards(transactions: list),
              ),
              const SizedBox(height: AppSpacing.gap),
              FadeIn(
                delay: const Duration(milliseconds: 60),
                child: _PulseChart(transactions: list),
              ),
              const SizedBox(height: AppSpacing.gap),
              FadeIn(
                delay: const Duration(milliseconds: 120),
                child: _FilterPills(
                  filter: _filter,
                  onChanged: (v) => setState(() => _filter = v),
                ),
              ),
              const SizedBox(height: 12),
              _TransactionList(transactions: _applyFilter(list, _filter)),
            ],
          ),
        ),
      ),
    );
  }

  List<FinanceTransaction> _applyFilter(
      List<FinanceTransaction> all, String? filter) {
    if (filter == null) return all;
    return all.where((t) => t.type == filter).toList();
  }
}

// ---------------------------------------------------------------------------
// Stat cards: Total Revenue (ink), Total Expenses, Net Profit (red if < 0).
// ---------------------------------------------------------------------------

class _StatCards extends StatelessWidget {
  const _StatCards({required this.transactions});
  final List<FinanceTransaction> transactions;

  @override
  Widget build(BuildContext context) {
    final currency = NumberFormat.simpleCurrency();
    final income = transactions
        .where((t) => t.isIncome)
        .fold(0.0, (s, t) => s + t.amount);
    final expenses = transactions
        .where((t) => !t.isIncome)
        .fold(0.0, (s, t) => s + t.amount);
    final net = income - expenses;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _StatCard(
          label: 'Total Revenue',
          value: currency.format(income),
          primary: true,
        ),
        const SizedBox(height: 12),
        _StatCard(
          label: 'Total Expenses',
          value: currency.format(expenses),
        ),
        const SizedBox(height: 12),
        _StatCard(
          label: 'Net Profit',
          value: currency.format(net),
          negative: net < 0,
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({
    required this.label,
    required this.value,
    this.primary = false,
    this.negative = false,
  });

  final String label;
  final String value;
  final bool primary; // ink fill (mirrors finance.php's filled revenue card)
  final bool negative; // red value (net < 0)

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fill = primary ? p.ink : p.surface;
    final fg = primary ? p.onInk : p.textPrimary;
    final muted = primary ? p.onInk.withValues(alpha: 0.6) : p.textFaint;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: fill,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: muted, size: 10)),
          const SizedBox(height: 6),
          Text(value,
              style: AppType.mono(
                size: 28,
                color: negative ? const Color(0xFFDC2626) : fg,
              )),
        ],
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// 6-month net pulse line (CustomPainter). Monochrome ink polyline, scaled to fit.
// ---------------------------------------------------------------------------

class _PulseChart extends StatelessWidget {
  const _PulseChart({required this.transactions});
  final List<FinanceTransaction> transactions;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final now = DateTime.now();

    // Build the last 6 month buckets (oldest → newest). Months with no data = 0.
    final months = <DateTime>[];
    for (var i = 5; i >= 0; i--) {
      months.add(DateTime(now.year, now.month - i));
    }
    final netByMonth = List<double>.filled(6, 0);
    for (final t in transactions) {
      for (var i = 0; i < months.length; i++) {
        if (t.date.year == months[i].year && t.date.month == months[i].month) {
          netByMonth[i] += t.isIncome ? t.amount : -t.amount;
        }
      }
    }

    final monthLabel = DateFormat('MMM');
    final firstLabel = monthLabel.format(months.first);
    final lastLabel = monthLabel.format(months.last);

    return SectionCard(
      heading: 'Financial Pulse (6 Months)',
      trailing: Text('$firstLabel - $lastLabel',
          style: AppType.label(context, color: p.textFaint, size: 10)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 120,
            child: CustomPaint(
              size: Size.infinite,
              painter: _PulsePainter(
                values: netByMonth,
                color: p.ink,
                gridColor: p.border,
              ),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              for (final m in months)
                Text(monthLabel.format(m),
                    style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        color: p.textFaint)),
            ],
          ),
        ],
      ),
    );
  }
}

class _PulsePainter extends CustomPainter {
  _PulsePainter({
    required this.values,
    required this.color,
    required this.gridColor,
  });

  final List<double> values;
  final Color color;
  final Color gridColor;

  @override
  void paint(Canvas canvas, Size size) {
    final n = values.length;
    if (n < 2) return;

    final minVal = values.reduce((a, b) => a < b ? a : b);
    final maxVal = values.reduce((a, b) => a > b ? a : b);
    final range = (maxVal - minVal).abs() < 0.0001 ? 1.0 : (maxVal - minVal);

    final w = size.width;
    final h = size.height;

    // Subtle vertical month separators (mirrors the finance.php grid).
    final gridPaint = Paint()
      ..color = gridColor
      ..strokeWidth = 1;
    for (var i = 1; i < n; i++) {
      final x = w * i / n;
      canvas.drawLine(Offset(x, 0), Offset(x, h), gridPaint);
    }

    // Polyline of the 6 net values, padded 10% top/bottom so it never clips.
    final padTop = h * 0.1;
    final padBottom = h * 0.1;
    final usable = h - padTop - padBottom;

    final points = <Offset>[];
    for (var i = 0; i < n; i++) {
      final x = (n == 1) ? 0.0 : w * i / (n - 1);
      final normalized = (values[i] - minVal) / range;
      final y = padTop + usable - (normalized * usable);
      points.add(Offset(x, y));
    }

    final linePaint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(Path()..addPolygon(points, false), linePaint);

    // Ink dots at each vertex.
    final dotPaint = Paint()..color = color;
    for (final pt in points) {
      canvas.drawCircle(pt, 2.5, dotPaint);
    }
  }

  @override
  bool shouldRepaint(covariant _PulsePainter old) =>
      old.color != color ||
      old.gridColor != gridColor ||
      !_listEquals(old.values, values);

  static bool _listEquals(List<double> a, List<double> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if ((a[i] - b[i]).abs() > 0.0001) return false;
    }
    return true;
  }
}

// ---------------------------------------------------------------------------
// Filter pills: All / Income / Expense.
// ---------------------------------------------------------------------------

class _FilterPills extends StatelessWidget {
  const _FilterPills({required this.filter, required this.onChanged});

  final String? filter;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 6,
      children: [
        _Pill(
            label: 'All',
            selected: filter == null,
            onTap: () => onChanged(null)),
        _Pill(
            label: 'Income',
            selected: filter == 'income',
            onTap: () => onChanged('income')),
        _Pill(
            label: 'Expense',
            selected: filter == 'expense',
            onTap: () => onChanged('expense')),
      ],
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? p.ink : Colors.transparent,
          border: Border.all(color: selected ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(label.toUpperCase(),
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 12,
                fontWeight: FontWeight.w700,
                letterSpacing: 1,
                color: selected ? p.onInk : p.textPrimary)),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Transaction list.
// ---------------------------------------------------------------------------

class _TransactionList extends StatelessWidget {
  const _TransactionList({required this.transactions});
  final List<FinanceTransaction> transactions;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    if (transactions.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 24),
        child: EmptyState(
            iconPath: HeroPaths.banknotes,
            message: 'No transactions for this filter.'),
      );
    }

    // newest first (mirrors finance.php sort).
    final sorted = [...transactions]
      ..sort((a, b) => b.date.compareTo(a.date));

    return SectionCard(
      heading: 'Transactions',
      trailing: Text('${sorted.length} ITEMS', style: AppType.label(context)),
      child: Column(
        children: [
          for (var i = 0; i < sorted.length; i++) ...[
            if (i > 0) Divider(height: 1, color: p.border),
            _TransactionRow(transaction: sorted[i]),
          ],
        ],
      ),
    );
  }
}

class _TransactionRow extends StatelessWidget {
  const _TransactionRow({required this.transaction});
  final FinanceTransaction transaction;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final currency = NumberFormat.simpleCurrency();
    final income = transaction.isIncome;
    final amountColor =
        income ? const Color(0xFF16A34A) : const Color(0xFFDC2626);
    final dateLabel = DateFormat('MMM j').format(transaction.date);
    final desc = transaction.description.trim().isNotEmpty
        ? transaction.description
        : 'Untitled';

    return InkWell(
      onTap: () async {
        await context.push('/finance/${transaction.id}');
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            // Category glyph (income arrow-up / expense arrow-down tint).
            Container(
              width: 34,
              height: 34,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: p.canvas,
                border: Border.all(color: p.border),
                borderRadius: BorderRadius.circular(8),
              ),
              child: HeroIcon(
                income ? HeroPaths.arrowTrendUp : HeroPaths.arrowTrendDown,
                size: 18,
                color: amountColor,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(desc.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.4,
                          color: p.textPrimary)),
                  const SizedBox(height: 2),
                  Text(
                    '${transaction.category.isNotEmpty ? transaction.category.toUpperCase() : 'UNCATEGORIZED'} · $dateLabel',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1,
                        color: p.textFaint),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(
              '${income ? '+' : '-'}${currency.format(transaction.amount)}',
              style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: amountColor),
            ),
          ],
        ),
      ),
    );
  }
}
