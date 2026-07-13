import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/water_plan.dart';
import '../repositories/water_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/mono_button.dart';
import '../widgets/progress_ring.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';
import '../widgets/stat_card.dart';

/// Main water plan overview. Mirrors mobile/views/water-plan.php.
///
/// Shows the active plan (today's progress + reminder schedule with live
/// Scheduled/Due/Missed/Completed status) or an empty state offering plan
/// creation, AI generation, and a link to history.
class WaterPlanScreen extends ConsumerStatefulWidget {
  const WaterPlanScreen({super.key});

  @override
  ConsumerState<WaterPlanScreen> createState() => _WaterPlanScreenState();
}

class _WaterPlanScreenState extends ConsumerState<WaterPlanScreen> {
  /// Re-evaluates reminder statuses every minute (matches PHP's 60s poll) so
  /// Due→Missed transitions and the auto-mark-missed call fire on time.
  Timer? _tick;
  final _pendingMiss = <String>{};

  @override
  void initState() {
    super.initState();
    _tick = Timer.periodic(const Duration(seconds: 60), (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _tick?.cancel();
    super.dispose();
  }

  void _refresh() {
    ref.invalidate(waterPlanProvider);
    ref.invalidate(waterProvider);
  }

  @override
  Widget build(BuildContext context) {
    final planAsync = ref.watch(waterPlanProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Water Plan'),
        actions: [
          IconButton(
            tooltip: 'History',
            icon: const HeroIcon(HeroPaths.archive, size: 20),
            onPressed: () => context.push('/water/history'),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () async {
          await context.push('/water/new');
          _refresh();
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
      body: planAsync.when(
        loading: () => const _Loading(),
        error: (_, __) => _NoPlan(onCreated: _refresh),
        data: (plan) {
          if (plan == null) return _NoPlan(onCreated: _refresh);
          return _ActivePlan(
            plan: plan,
            pendingMiss: _pendingMiss,
            onChanged: _refresh,
          );
        },
      ),
    );
  }
}

/// Skeleton placeholder shaped like the plan overview.
class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.page),
      children: const [
        Skeleton(height: 120, radius: AppRadii.section),
        SizedBox(height: 16),
        Row(children: [
          Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
          SizedBox(width: 12),
          Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
          SizedBox(width: 12),
          Expanded(child: Skeleton(height: 80, radius: AppRadii.card)),
        ]),
        SizedBox(height: 16),
        Skeleton(height: 260, radius: AppRadii.section),
      ],
    );
  }
}

/// Empty state when no plan is active. Offers manual creation, AI generation,
/// and a link to history (mirrors water-plan.php's no-plan section).
class _NoPlan extends StatelessWidget {
  const _NoPlan({required this.onCreated});
  final VoidCallback onCreated;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(AppSpacing.page),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            HeroIcon(HeroPaths.droplet, size: 48, color: p.textFaint, strokeWidth: 1.5),
            const SizedBox(height: 16),
            Text('No Active Plan',
                style: AppType.label(context, color: p.textMuted, size: 11)),
            const SizedBox(height: 6),
            Text('Create your hydration schedule',
                textAlign: TextAlign.center,
                style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: p.textPrimary)),
            const SizedBox(height: 8),
            Text(
              'Track water reminders in liters and log each drink on time.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 14, color: p.textMuted),
            ),
            const SizedBox(height: 24),
            MonoButton('Create Plan',
                onPressed: () async {
                  await context.push('/water/new');
                  onCreated();
                }),
            const SizedBox(height: 10),
            MonoButton('Generate AI Plan',
                primary: false,
                onPressed: () async {
                  await context.push('/water/ai');
                  onCreated();
                }),
            const SizedBox(height: 10),
            TextButton(
              onPressed: () => context.push('/water/history'),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  HeroIcon(HeroPaths.archive, size: 16, color: p.textMuted),
                  const SizedBox(width: 6),
                  Text('View History',
                      style: TextStyle(
                          color: p.textMuted,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Active plan body: progress hero card, stat row, reminder schedule.
class _ActivePlan extends ConsumerWidget {
  const _ActivePlan({
    required this.plan,
    required this.pendingMiss,
    required this.onChanged,
  });

  final WaterPlan plan;
  final Set<String> pendingMiss; // schedule item ids being auto-marked missed
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Auto-mark reminders past the 10-min grace window as missed (PHP parity).
    _autoMarkMissed(ref);

    return RefreshIndicator(
      onRefresh: () async => onChanged(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          FadeIn(child: _HeroCard(plan: plan)),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 60),
            child: Row(
              children: [
                Expanded(
                  child: StatCard(
                    label: 'Planned',
                    iconPath: HeroPaths.droplet,
                    valueText: '${plan.litersPlanned.toStringAsFixed(2)} L',
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: StatCard(
                    label: 'Completed',
                    iconPath: HeroPaths.check,
                    valueText: '${plan.litersCompleted.toStringAsFixed(2)} L',
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: StatCard(
                    label: 'Missed',
                    iconPath: HeroPaths.xMark,
                    valueInt: plan.missedCount,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: SectionCard(
              heading: 'Today Schedule',
              child: plan.schedule.isEmpty
                  ? const EmptyState(
                      iconPath: HeroPaths.clock,
                      message: 'No reminders configured yet.',
                    )
                  : Column(
                      children: [
                        for (final r in plan.schedule)
                          _ReminderRow(
                            reminder: r,
                            glassSizeMl: plan.glassSizeMl,
                            planCreatedAt: plan.createdAt,
                            planId: plan.id,
                            onChanged: onChanged,
                          ),
                      ],
                    ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 180),
            child: Row(
              children: [
                Expanded(
                  child: _NavTile(
                    icon: HeroPaths.pencil,
                    label: 'Edit Plan',
                    primary: true,
                    onTap: () async {
                      await context.push('/water/new?planId=${plan.id}');
                      onChanged();
                    },
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _NavTile(
                    icon: HeroPaths.archive,
                    label: 'History',
                    onTap: () => context.push('/water/history'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Port of PHP refreshWaterStatuses(): for any reminder not completed/missed
  /// whose time is past the 10-min grace window, call mark_reminder_missed.
  Future<void> _autoMarkMissed(WidgetRef ref) async {
    final now = DateTime.now();
    const grace = Duration(minutes: 10);
    for (final r in plan.schedule) {
      if (r.completed || r.missed) continue;
      if (r.time.isEmpty) continue;
      if (pendingMiss.contains(r.id)) continue;

      final due = _todayAt(r.time);
      if (due == null) continue;

      // Reminders scheduled before the plan was created today are N/A — skip.
      final created = plan.createdAt;
      if (created != null &&
          due.year == created.year &&
          due.month == created.month &&
          due.day == created.day &&
          due.isBefore(created)) {
        continue;
      }

      if (now.difference(due) > grace) {
        pendingMiss.add(r.id);
        try {
          await ref.read(waterRepositoryProvider).markMissed(plan.id, r.id);
          if (ref.context.mounted) onChanged();
        } catch (_) {
          // best-effort; next refresh reflects server truth
        } finally {
          pendingMiss.remove(r.id);
        }
      }
    }
  }

  static DateTime? _todayAt(String hhmm) {
    final parts = hhmm.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, h, m);
  }
}

/// The plan header: progress ring + name + liters/goal chips.
class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.plan});
  final WaterPlan plan;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final progress = plan.progress;
    return Container(
      padding: const EdgeInsets.all(AppSpacing.sectionPad),
      decoration: BoxDecoration(
        color: p.ink,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('CURRENT PLAN',
                        style: AppType.label(context, color: p.onInk.withValues(alpha: 0.7), size: 11)),
                    const SizedBox(height: 6),
                    Text(plan.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            color: p.onInk)),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              ProgressRing(
                progress: progress,
                size: 64,
                stroke: 6,
                color: p.onInk,
                trackColor: p.onInk.withValues(alpha: 0.2),
                center: Text('${(progress * 100).round()}%',
                    style: AppType.mono(size: 14, color: p.onInk)),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _HeroChip(
                  '${plan.litersCompleted.toStringAsFixed(2)} / ${plan.litersGoal.toStringAsFixed(2)} L'),
              _HeroChip('${plan.schedule.length} reminders'),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroChip extends StatelessWidget {
  const _HeroChip(this.label);
  final String label;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        border: Border.all(color: p.onInk.withValues(alpha: 0.4)),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(label,
          style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 1,
              color: p.onInk)),
    );
  }
}

/// A single reminder row: time, amount, live status badge, tap-to-complete.
class _ReminderRow extends StatelessWidget {
  const _ReminderRow({
    required this.reminder,
    required this.glassSizeMl,
    required this.planCreatedAt,
    required this.planId,
    required this.onChanged,
  });

  final WaterReminder reminder;
  final int glassSizeMl;
  final DateTime? planCreatedAt;
  final String planId;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final status = _resolveStatus();
    final liters = (reminder.resolvedAmountMl(glassSizeMl) / 1000)
        .toStringAsFixed(2);
    final canComplete = status == _ReminderStatus.due ||
        status == _ReminderStatus.scheduled ||
        status == _ReminderStatus.missed;

    return Consumer(builder: (context, ref, _) {
      final p = AppPalette.of(context);
      return Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          border: Border(
              top: BorderSide(color: p.border.withValues(alpha: 0.5))),
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(reminder.time.isEmpty ? '--:--' : reminder.time,
                      style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: p.textPrimary)),
                  const SizedBox(height: 2),
                  Text('$liters L',
                      style: TextStyle(fontSize: 12, color: p.textMuted)),
                ],
              ),
            ),
            _StatusBadge(status: status),
            if (canComplete) ...[
              const SizedBox(width: 8),
              _CompleteButton(
                onTap: () => _complete(ref),
              ),
            ],
          ],
        ),
      );
    });
  }

  Future<void> _complete(WidgetRef ref) async {
    try {
      await ref.read(waterRepositoryProvider).completeReminder(planId, reminder.id);
      ref.invalidate(waterPlanProvider);
      ref.invalidate(waterProvider);
      onChanged();
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  /// Resolve the live status, mirroring PHP refreshWaterStatuses().
  _ReminderStatus _resolveStatus() {
    if (reminder.completed) return _ReminderStatus.completed;
    if (reminder.missed) return _ReminderStatus.missed;
    if (reminder.time.isEmpty) return _ReminderStatus.scheduled;

    final due = _todayAt(reminder.time);
    if (due == null) return _ReminderStatus.scheduled;

    // N/A if the reminder time is before the plan was created today.
    final created = planCreatedAt;
    if (created != null &&
        due.year == created.year &&
        due.month == created.month &&
        due.day == created.day &&
        due.isBefore(created)) {
      return _ReminderStatus.na;
    }

    final now = DateTime.now();
    if (now.isAfter(due.add(const Duration(minutes: 10)))) {
      return _ReminderStatus.missed;
    }
    if (!now.isBefore(due)) return _ReminderStatus.due;
    return _ReminderStatus.scheduled;
  }

  static DateTime? _todayAt(String hhmm) {
    final parts = hhmm.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, h, m);
  }
}

enum _ReminderStatus { scheduled, due, missed, completed, na }

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final _ReminderStatus status;

  @override
  Widget build(BuildContext context) {
    final (label, color) = switch (status) {
      _ReminderStatus.completed => ('COMPLETED', const Color(0xFF16A34A)),
      _ReminderStatus.missed => ('MISSED', const Color(0xFFDC2626)),
      _ReminderStatus.due => ('DUE', AppPalette.of(context).textPrimary),
      _ReminderStatus.na => ('N/A', AppPalette.of(context).textFaint),
      _ReminderStatus.scheduled => ('SCHEDULED', const Color(0xFF2563EB)),
    };
    return Text(label,
        style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: 1,
            color: color));
  }
}

/// Small circular check button to complete a reminder.
class _CompleteButton extends StatelessWidget {
  const _CompleteButton({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 32,
        height: 32,
        decoration: BoxDecoration(
          color: p.ink,
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        alignment: Alignment.center,
        child: HeroIcon(HeroPaths.check, size: 16, color: p.onInk),
      ),
    );
  }
}

/// Bordered action tile (Edit Plan / History).
class _NavTile extends StatelessWidget {
  const _NavTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.primary = false,
  });

  final String icon;
  final String label;
  final VoidCallback onTap;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = primary ? p.onInk : p.textPrimary;
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
                style: AppType.label(context, color: color, size: 10)),
          ],
        ),
      ),
    );
  }
}
