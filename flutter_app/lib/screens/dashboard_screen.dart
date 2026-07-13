import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../auth/auth_controller.dart';
import '../models/dashboard_stats.dart';
import '../models/task.dart';
import '../repositories/dashboard_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/count_up.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/inspiration_card.dart';
import '../widgets/pomodoro_card.dart';
import '../widgets/priority_badge.dart';
import '../widgets/progress_ring.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';
import '../widgets/water_section.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final stats = ref.watch(dashboardProvider);
    final name = ref.watch(userNameProvider);
    final p = AppPalette.of(context);

    return AppScaffold(
      title: 'Dashboard',
      current: 'Dashboard',
      actions: [
        IconButton(
          tooltip: 'Sign out',
          icon: HeroIcon(HeroPaths.logout, size: 22, color: p.textPrimary),
          onPressed: () async {
            await ref.read(authStateProvider.notifier).logout();
            if (context.mounted) context.go('/login');
          },
        ),
      ],
      body: stats.when(
        loading: () => const _Padded(child: DashboardSkeleton()),
        error: (e, _) => _Padded(
          child: EmptyState(
            iconPath: HeroPaths.clipboard,
            message: e is Exception ? e.toString() : 'Failed to load',
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(dashboardProvider),
          ),
        ),
        data: (s) => RefreshIndicator(
          onRefresh: () => ref.refresh(dashboardProvider.future),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
                AppSpacing.page, 20, AppSpacing.page, 24),
            children: [
              FadeIn(child: _OverviewHero(s: s, name: name)),
              const SizedBox(height: AppSpacing.gap),
              FadeIn(
                delay: const Duration(milliseconds: 110),
                child: _HabitsCard(s: s),
              ),
              const SizedBox(height: AppSpacing.gap),
              const FadeIn(
                delay: Duration(milliseconds: 170),
                child: WaterSection(),
              ),
              const SizedBox(height: AppSpacing.gap),
              const FadeIn(
                delay: Duration(milliseconds: 230),
                child: InspirationCard(),
              ),
              const SizedBox(height: AppSpacing.gap),
              const FadeIn(
                delay: Duration(milliseconds: 290),
                child: PomodoroCard(),
              ),
              const SizedBox(height: AppSpacing.gap),
              FadeIn(
                delay: const Duration(milliseconds: 350),
                child: _RecentTasks(s: s, onToggle: (t) => _markDone(ref, t)),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _markDone(WidgetRef ref, Task t) async {
    try {
      await ref.read(apiClientProvider).put(
        '/api/tasks.php',
        query: {'id': t.id, if (t.projectId != null) 'projectId': t.projectId!},
        body: {'status': 'done'},
      );
      ref.invalidate(dashboardProvider);
    } catch (_) {
      // surfaced on next refresh
    }
  }
}

class _Padded extends StatelessWidget {
  const _Padded({required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => Padding(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 20, AppSpacing.page, 24),
        child: child,
      );
}

/// Focal black overview card: greeting + giant pending count + inline metrics.
class _OverviewHero extends StatelessWidget {
  const _OverviewHero({required this.s, this.name});
  final DashboardStats s;
  final String? name;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final first = (name ?? '').trim().split(' ').first;
    final hour = DateTime.now().hour;
    final tod = hour < 12
        ? 'Good morning'
        : hour < 18
            ? 'Good afternoon'
            : 'Good evening';
    final greet = first.isEmpty ? tod : '$tod, $first';
    final date = DateFormat('EEE, MMM d').format(DateTime.now());

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: p.ink,
        borderRadius: BorderRadius.circular(AppRadii.section),
        boxShadow: [
          BoxShadow(
            color: p.ink.withValues(alpha: 0.22),
            blurRadius: 30,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  greet,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 15,
                    fontWeight: FontWeight.w500,
                    color: p.onInk.withValues(alpha: 0.85),
                  ),
                ),
              ),
              Text(
                date.toUpperCase(),
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.5,
                  color: p.onInk.withValues(alpha: 0.45),
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              CountUp(
                s.pendingTasks,
                style: AppType.mono(size: 60, color: p.onInk).copyWith(height: 1),
              ),
              const SizedBox(width: 14),
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Text(
                  'TASKS\nPENDING',
                  style: TextStyle(
                    fontSize: 11,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.5,
                    color: p.onInk.withValues(alpha: 0.55),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          Container(height: 1, color: p.onInk.withValues(alpha: 0.15)),
          const SizedBox(height: 16),
          Row(
            children: [
              _metric(p, '${s.completedTasks}', 'COMPLETED'),
              _vline(p),
              _metric(p, '${s.activeProjects}', 'PROJECTS'),
              _vline(p),
              _metric(p, '${s.tasksThisWeek}', 'THIS WEEK'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _metric(AppPalette p, String value, String label) => Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value,
                style: AppType.mono(
                    size: 20, weight: FontWeight.w600, color: p.onInk)),
            const SizedBox(height: 3),
            Text(label,
                style: TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                  color: p.onInk.withValues(alpha: 0.45),
                )),
          ],
        ),
      );

  Widget _vline(AppPalette p) => Container(
        width: 1,
        height: 30,
        margin: const EdgeInsets.symmetric(horizontal: 14),
        color: p.onInk.withValues(alpha: 0.15),
      );
}

/// Wide Habits card with animated progress.
class _HabitsCard extends StatelessWidget {
  const _HabitsCard({required this.s});
  final DashboardStats s;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SectionCard(
      child: Row(
        children: [
          ProgressRing(
            progress: s.habitsProgress,
            size: 80,
            stroke: 9,
            trackColor: p.border,
            color: p.ink,
            center: Text('${s.habitsToday}/${s.totalHabits}',
                style: AppType.mono(
                    size: 17, weight: FontWeight.w600, color: p.textPrimary)),
          ),
          const SizedBox(width: 20),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('HABITS TODAY',
                    style:
                        AppType.label(context, color: p.textPrimary, size: 13)),
                const SizedBox(height: 6),
                Text('${(s.habitsProgress * 100).round()}% complete today',
                    style: TextStyle(fontSize: 13, color: p.textMuted)),
                const SizedBox(height: 2),
                Text(
                    '${s.totalHabits} active habit${s.totalHabits == 1 ? '' : 's'}',
                    style: TextStyle(fontSize: 12, color: p.textFaint)),
              ],
            ),
          ),
          HeroIcon(HeroPaths.fire, size: 22, color: p.textFaint),
        ],
      ),
    );
  }
}

class _RecentTasks extends StatelessWidget {
  const _RecentTasks({required this.s, required this.onToggle});
  final DashboardStats s;
  final void Function(Task) onToggle;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SectionCard(
      heading: 'Recent Tasks',
      trailing: GestureDetector(
        onTap: () => context.go('/tasks'),
        child: Text('VIEW ALL',
            style: AppType.label(context, color: p.textFaint)),
      ),
      child: s.recentTasks.isEmpty
          ? const EmptyState(
              iconPath: HeroPaths.checkCircle, message: 'No pending tasks')
          : Column(
              children: [
                for (var i = 0; i < s.recentTasks.length; i++) ...[
                  if (i > 0) Divider(height: 1, color: p.border),
                  _TaskRow(
                    task: s.recentTasks[i],
                    onToggle: () => onToggle(s.recentTasks[i]),
                  ),
                ],
              ],
            ),
    );
  }
}

class _TaskRow extends StatelessWidget {
  const _TaskRow({required this.task, required this.onToggle});
  final Task task;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: onToggle,
            child: Padding(
              padding: const EdgeInsets.only(right: 12),
              child: HeroIcon(HeroPaths.circle, size: 20, color: p.textFaint),
            ),
          ),
          Expanded(
            child: Text(
              task.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: p.textPrimary),
            ),
          ),
          const SizedBox(width: 8),
          PriorityBadge(task.priority),
        ],
      ),
    );
  }
}
