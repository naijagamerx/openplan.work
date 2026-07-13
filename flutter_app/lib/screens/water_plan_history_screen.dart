import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/water_plan.dart';
import '../repositories/water_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';
import '../widgets/stat_card.dart';

/// List of past + manual plans with totals, Activate / Delete actions.
/// Mirrors mobile/views/water-plan-history.php.
class WaterPlanHistoryScreen extends ConsumerWidget {
  const WaterPlanHistoryScreen({super.key});

  void _refresh(WidgetRef ref) {
    ref.invalidate(waterHistoryProvider);
    ref.invalidate(waterPlanProvider);
    ref.invalidate(waterProvider);
  }

  Future<void> _activate(WidgetRef ref, WaterPlan plan) async {
    try {
      await ref.read(waterRepositoryProvider).activatePlan(plan.id);
      _refresh(ref);
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context).showSnackBar(const SnackBar(
            content: Text('Plan activated'),
            behavior: SnackBarBehavior.floating));
      }
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _delete(WidgetRef ref, WaterPlan plan) async {
    final ok = await confirmDialog(ref.context,
        title: 'Delete plan',
        message: 'Delete "${plan.name}"? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(waterRepositoryProvider).deletePlan(plan.id,
          collection: plan.source == 'history'
              ? 'water_plan_history'
              : 'water_plans');
      _refresh(ref);
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final history = ref.watch(waterHistoryProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Water Plan History'),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          await context.push('/water/new');
          _refresh(ref);
        },
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.button),
          side: BorderSide(color: AppPalette.of(context).ink),
        ),
        child: const HeroIcon(HeroPaths.plus, size: 22),
      ),
      body: history.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Row(children: [
              Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
              SizedBox(width: 12),
              Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
            ]),
            SizedBox(height: 16),
            Skeleton(height: 140, radius: AppRadii.card),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.card),
          ],
        ),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.archive,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: () => _refresh(ref),
          ),
        ),
        data: (plans) {
          if (plans.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.archive,
                message: 'No plans yet.',
                actionLabel: 'Create Plan',
                onAction: () async {
                  await context.push('/water/new');
                  _refresh(ref);
                },
              ),
            );
          }
          final activeCount = plans.where((p) => p.isActive).length;
          final reminderTotal =
              plans.fold(0, (s, p) => s + p.schedule.length);
          final range = _dateRange(plans);

          return RefreshIndicator(
            onRefresh: () async => _refresh(ref),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 8, AppSpacing.page, 120),
              children: [
                FadeIn(
                  child: Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          label: 'Total Plans',
                          iconPath: HeroPaths.archive,
                          valueInt: plans.length,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: StatCard(
                          label: 'Active',
                          iconPath: HeroPaths.check,
                          valueInt: activeCount,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                FadeIn(
                  delay: const Duration(milliseconds: 40),
                  child: SectionCard(
                    heading: 'Date Range',
                    child: Text(range,
                        style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: AppPalette.of(context).textPrimary)),
                  ),
                ),
                const SizedBox(height: AppSpacing.gap),
                for (var i = 0; i < plans.length; i++)
                  FadeIn(
                    delay: Duration(milliseconds: 80 + i * 40),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _HistoryCard(
                        plan: plans[i],
                        onActivate: plans[i].isActive
                            ? null
                            : () => _activate(ref, plans[i]),
                        onDelete: () => _delete(ref, plans[i]),
                      ),
                    ),
                  ),
                Center(
                  child: Text(
                    'SHOWING ${plans.length} PLAN(S) · $reminderTotal REMINDERS'
                        .toUpperCase(),
                    style: AppType.label(context),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  /// "MMM j, y" oldest → newest, or "—" when dates are missing.
  static String _dateRange(List<WaterPlan> plans) {
    final dates = plans
        .map((p) => p.createdAt)
        .whereType<DateTime>()
        .toList()
      ..sort((a, b) => a.compareTo(b));
    if (dates.isEmpty) return '—';
    final fmt = DateFormat('MMM j, y');
    if (dates.length == 1) return fmt.format(dates.first);
    return '${fmt.format(dates.first)} → ${fmt.format(dates.last)}';
  }
}

/// A single plan card in the history list.
class _HistoryCard extends StatelessWidget {
  const _HistoryCard({
    required this.plan,
    required this.onActivate,
    required this.onDelete,
  });

  final WaterPlan plan;
  final VoidCallback? onActivate; // null when already active
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final created = plan.createdAt;
    final totalSlots = plan.schedule.length;
    final completedSlots = plan.completedCount;
    final progress = totalSlots > 0 ? completedSlots / totalSlots : 0.0;
    final isManual = (plan.source ?? 'manual') == 'manual';
    final sourceLabel = isManual ? 'Manual' : 'History';
    final dateText = created != null
        ? DateFormat('MMM j, y · h:mm a').format(created)
        : '—';

    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: plan.isActive ? p.ink : p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(plan.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: p.textPrimary)),
                    const SizedBox(height: 4),
                    Text(dateText,
                        style: AppType.label(
                            context, color: p.textMuted, size: 11)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _StatusPill(active: plan.isActive),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(color: p.border.withValues(alpha: 0.5)),
                bottom: BorderSide(color: p.border.withValues(alpha: 0.5)),
              ),
            ),
            child: Row(
              children: [
                _Meta(label: 'Goal', value: '${plan.litersGoal.toStringAsFixed(2)}L'),
                _Meta(label: 'Reminders', value: '$totalSlots'),
                _Meta(label: 'Type', value: sourceLabel),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Row(
                  children: [
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadii.pill),
                        child: TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: progress.clamp(0, 1)),
                          duration: const Duration(milliseconds: 600),
                          builder: (_, v, __) =>
                              LinearProgressIndicator(
                            value: v,
                            minHeight: 6,
                            backgroundColor: p.border,
                            valueColor: AlwaysStoppedAnimation(p.ink),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Text('${(progress * 100).round()}%',
                        style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: p.textPrimary)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              if (onActivate != null) ...[
                _MiniButton(
                  label: 'Activate',
                  primary: true,
                  onTap: onActivate!,
                ),
                const SizedBox(width: 8),
              ],
              _MiniButton(
                label: 'Delete',
                destructive: true,
                onTap: onDelete,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, size: 10)),
          const SizedBox(height: 4),
          Text(value,
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: p.textPrimary)),
        ],
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.active});
  final bool active;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fill = active ? p.ink : Colors.transparent;
    final fg = active ? p.onInk : p.textMuted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: fill,
        border: Border.all(color: active ? p.ink : p.border),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(active ? 'ACTIVE' : 'INACTIVE',
          style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1,
              color: fg)),
    );
  }
}

class _MiniButton extends StatelessWidget {
  const _MiniButton({
    required this.label,
    required this.onTap,
    this.primary = false,
    this.destructive = false,
  });

  final String label;
  final VoidCallback onTap;
  final bool primary;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final borderColor = destructive ? const Color(0xFFDC2626) : p.ink;
    final fill = primary ? p.ink : Colors.transparent;
    final fg = destructive
        ? const Color(0xFFDC2626)
        : (primary ? p.onInk : p.textPrimary);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: borderColor),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Text(label.toUpperCase(),
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 1,
                color: fg)),
      ),
    );
  }
}
