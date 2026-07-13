import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/habit.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/habits_repository.dart';
import '../services/notifications_service.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/progress_ring.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

class HabitsScreen extends ConsumerStatefulWidget {
  const HabitsScreen({super.key});

  @override
  ConsumerState<HabitsScreen> createState() => _HabitsScreenState();
}

class _HabitsScreenState extends ConsumerState<HabitsScreen> {
  @override
  void initState() {
    super.initState();
    // Best-effort: refresh OS habit reminders whenever the habit list reloads.
    // Mirrors PHP mobile.js:658-780 (polls api/habits.php, fires OS notifs).
    ref.listenManual(habitsListProvider, (_, next) {
      next.whenData((list) =>
          ref.read(notificationsServiceProvider).scheduleHabitReminders(list));
    });
  }

  @override
  Widget build(BuildContext context) {
    final habits = ref.watch(habitsListProvider);

    return AppScaffold(
      title: 'Habits',
      current: 'Habits',
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/habits/new');
          if (context.mounted) ref.invalidate(habitsListProvider);
        },
        child: HeroIcon(HeroPaths.plus,
            size: 24, color: AppPalette.of(context).onInk),
      ),
      body: habits.when(
        loading: () => ListView(
          padding: const EdgeInsets.fromLTRB(AppSpacing.page, 20, AppSpacing.page, 24),
          children: const [
            Skeleton(height: 110, radius: AppRadii.section),
            SizedBox(height: AppSpacing.gap),
            Skeleton(height: 72, radius: AppRadii.card),
            SizedBox(height: 10),
            Skeleton(height: 72, radius: AppRadii.card),
            SizedBox(height: 10),
            Skeleton(height: 72, radius: AppRadii.card),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.fire,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(habitsListProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return const Center(
              child: EmptyState(
                  iconPath: HeroPaths.fire, message: 'No habits yet'),
            );
          }
          final done = list.where((h) => h.todayCompleted).length;
          final progress = list.isEmpty ? 0.0 : done / list.length;
          final bestStreak = list.fold<int>(0,
              (m, h) => h.bestStreak > m ? h.bestStreak : m);
          final totalDone = list.fold<int>(
              0, (m, h) => m + h.totalCompletions);
          return RefreshIndicator(
            onRefresh: () => ref.refresh(habitsListProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 20, AppSpacing.page, 24),
              children: [
                FadeIn(
                    child: _Summary(done: done, total: list.length, progress: progress)),
                const SizedBox(height: AppSpacing.gap),
                FadeIn(
                  delay: const Duration(milliseconds: 40),
                  child: _Momentum(
                    bestStreak: bestStreak,
                    totalCompletions: totalDone,
                    activeHabits: list.length,
                  ),
                ),
                const SizedBox(height: AppSpacing.gap),
                for (var i = 0; i < list.length; i++)
                  FadeIn(
                    delay: Duration(milliseconds: (i * 60).clamp(0, 360)),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _HabitRow(
                        habit: list[i],
                        onToggle: () => _toggle(list[i]),
                        onTap: () async {
                          await context.push('/habits/${list[i].id}');
                          if (context.mounted) ref.invalidate(habitsListProvider);
                        },
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _toggle(Habit h) async {
    try {
      await ref
          .read(habitsRepositoryProvider)
          .setComplete(h.id, !h.todayCompleted);
      ref.invalidate(habitsListProvider);
      ref.invalidate(dashboardProvider);
    } catch (_) {
      // surfaced on next refresh
    }
  }
}

class _Summary extends StatelessWidget {
  const _Summary({required this.done, required this.total, required this.progress});
  final int done;
  final int total;
  final double progress;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SectionCard(
      child: Row(
        children: [
          ProgressRing(
            progress: progress,
            size: 84,
            stroke: 9,
            trackColor: p.border,
            color: p.ink,
            center: Text('$done/$total',
                style: AppType.mono(
                    size: 17, weight: FontWeight.w600, color: p.textPrimary)),
          ),
          const SizedBox(width: 20),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('TODAY',
                    style:
                        AppType.label(context, color: p.textPrimary, size: 13)),
                const SizedBox(height: 6),
                Text('${(progress * 100).round()}% of habits complete',
                    style: TextStyle(fontSize: 13, color: p.textMuted)),
                const SizedBox(height: 2),
                Text('$total active habit${total == 1 ? '' : 's'}',
                    style: TextStyle(fontSize: 12, color: p.textFaint)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HabitRow extends StatelessWidget {
  const _HabitRow({required this.habit, required this.onToggle, this.onTap});
  final Habit habit;
  final VoidCallback onToggle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final done = habit.todayCompleted;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.card),
          // DESIGN.md §7: borders, not shadows.
        ),
        child: Row(
          children: [
            GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: onToggle,
              child: HeroIcon(
                done ? HeroPaths.checkCircle : HeroPaths.circle,
                size: 26,
                color: done ? p.ink : p.textFaint,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                habit.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 15,
                fontWeight: FontWeight.w600,
                color: done ? p.textFaint : p.textPrimary,
                decoration: done ? TextDecoration.lineThrough : null,
              ),
            ),
          ),
          if (habit.currentStreak > 0) ...[
            const SizedBox(width: 8),
            HeroIcon(HeroPaths.fire, size: 16, color: p.textMuted),
            const SizedBox(width: 4),
            Text('${habit.currentStreak}',
                style: AppType.mono(
                    size: 14, weight: FontWeight.w600, color: p.textMuted)),
          ],
        ],
        ),
      ),
    );
  }
}

/// Inverted (ink) momentum card — mirrors the black momentum card in
/// mobile/views/habits.php.
class _Momentum extends StatelessWidget {
  const _Momentum({
    required this.bestStreak,
    required this.totalCompletions,
    required this.activeHabits,
  });
  final int bestStreak;
  final int totalCompletions;
  final int activeHabits;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    Widget metric(String label, String value) => Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(value,
                  style: AppType.mono(
                      size: 22,
                      weight: FontWeight.w700,
                      color: p.onInk)),
              const SizedBox(height: 4),
              Text(label.toUpperCase(),
                  style: TextStyle(
                      fontFamily: 'Geist',
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1,
                      color: p.onInk.withValues(alpha: 0.7))),
            ],
          ),
        );
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.sectionPad),
      decoration: BoxDecoration(
        color: p.ink,
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: Row(
        children: [
          HeroIcon(HeroPaths.fire, size: 24, color: p.onInk),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('MOMENTUM',
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.5,
                        color: p.onInk.withValues(alpha: 0.7))),
                const SizedBox(height: 12),
                Row(
                  children: [
                    metric('Best', '${bestStreak}d'),
                    metric('Total', '$totalCompletions'),
                    metric('Active', '$activeHabits'),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
