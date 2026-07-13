import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/habit.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/habits_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';
import '../widgets/stat_card.dart';

/// Habit detail screen. Mirrors mobile/views/view-habit.php.
class HabitDetailScreen extends ConsumerStatefulWidget {
  const HabitDetailScreen({super.key, required this.habitId});
  final String habitId;

  @override
  ConsumerState<HabitDetailScreen> createState() => _HabitDetailScreenState();
}

class _HabitDetailScreenState extends ConsumerState<HabitDetailScreen> {
  late final FutureProvider<Habit?> _provider;

  @override
  void initState() {
    super.initState();
    _provider = FutureProvider<Habit?>(
        (ref) => ref.watch(habitsRepositoryProvider).fetch(widget.habitId));
  }

  @override
  Widget build(BuildContext context) {
    final habit = ref.watch(_provider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Habit'),
        actions: [
          IconButton(
            icon: const HeroIcon(HeroPaths.pencil, size: 20),
            onPressed: () async {
              await context.push('/habits/${widget.habitId}/edit');
              if (mounted) ref.invalidate(_provider);
            },
          ),
        ],
      ),
      body: habit.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 90, radius: AppRadii.card),
            SizedBox(height: 16),
            Row(children: [
              Expanded(child: Skeleton(height: 90, radius: AppRadii.card)),
              SizedBox(width: 12),
              Expanded(child: Skeleton(height: 90, radius: AppRadii.card)),
            ]),
          ],
        ),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.fire,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(_provider),
          ),
        ),
        data: (h) {
          if (h == null) {
            return const Center(
                child: EmptyState(
                    iconPath: HeroPaths.fire, message: 'Habit not found'));
          }
          return _Body(habit: h, onChanged: () => ref.invalidate(_provider));
        },
      ),
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.habit, required this.onChanged});
  final Habit habit;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final today = DateTime.now();
    final monthHistory = habit.monthHistory;

    return RefreshIndicator(
      onRefresh: () async => onChanged(),
      child: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          FadeIn(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  children: [
                    _Tag(habit.category),
                    _Tag(habit.frequency),
                    if (!habit.active) const _Tag('Archived', muted: true),
                  ],
                ),
                const SizedBox(height: 12),
                Text(habit.name,
                    style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: p.textPrimary)),
                if (habit.createdAt != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                        'Since ${DateFormat.yMMMd().format(habit.createdAt!)}',
                        style: TextStyle(color: p.textFaint, fontSize: 12)),
                  ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 60),
            child: GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 1.4,
              children: [
                StatCard(
                  label: 'Current Streak',
                  iconPath: HeroPaths.fire,
                  valueText: '${habit.currentStreak}d',
                ),
                StatCard(
                  label: 'Best Streak',
                  iconPath: HeroPaths.chartBar,
                  valueText: '${habit.bestStreak}d',
                ),
                StatCard(
                  label: 'Completions',
                  iconPath: HeroPaths.check,
                  valueInt: habit.totalCompletions,
                ),
                StatCard(
                  label: 'This Month',
                  iconPath: HeroPaths.calendar,
                  valueText: '${monthHistory.length}d',
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: SectionCard(
              heading: 'This Month',
              child: _MonthGrid(
                year: today.year,
                month: today.month,
                history: monthHistory,
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 180),
            child: SectionCard(
              heading: 'Details',
              child: Column(
                children: [
                  _MetaRow(
                      label: 'Reminder',
                      value: habit.reminderTime ?? 'Not set'),
                  _MetaRow(
                      label: 'Target',
                      value: habit.targetDuration > 0
                          ? '${habit.targetDuration} min'
                          : 'No target'),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 220),
            child: Row(
              children: [
                Expanded(
                  child: _IconTile(
                    icon: habit.todayCompleted ? HeroPaths.xMark : HeroPaths.check,
                    label: habit.todayCompleted ? 'Undo Today' : 'Mark Today',
                    onTap: () => _toggleToday(ref),
                    primary: true,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _IconTile(
                    icon: HeroPaths.archive,
                    label: habit.active ? 'Archive' : 'Reactivate',
                    onTap: () => _toggleArchive(ref),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _IconTile(
                    icon: HeroPaths.trash,
                    label: 'Delete',
                    destructive: true,
                    onTap: () => _delete(ref),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _toggleToday(WidgetRef ref) async {
    try {
      await ref
          .read(habitsRepositoryProvider)
          .setComplete(habit.id, !habit.todayCompleted);
      ref.invalidate(habitsListProvider);
      ref.invalidate(dashboardProvider);
      onChanged();
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _toggleArchive(WidgetRef ref) async {
    try {
      await ref.read(habitsRepositoryProvider).setActive(habit.id, !habit.active);
      ref.invalidate(habitsListProvider);
      onChanged();
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context).showSnackBar(SnackBar(
            content: Text(habit.active ? 'Archived' : 'Reactivated'),
            behavior: SnackBarBehavior.floating));
      }
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _delete(WidgetRef ref) async {
    final ok = await confirmDialog(ref.context,
        title: 'Delete habit',
        message: 'Delete "${habit.name}"? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(habitsRepositoryProvider).delete(habit.id);
      ref.invalidate(habitsListProvider);
      ref.invalidate(dashboardProvider);
      if (ref.context.mounted) ref.context.pop();
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }
}

class _Tag extends StatelessWidget {
  const _Tag(this.label, {this.muted = false});
  final String label;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: muted ? p.border : p.ink,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(label.toUpperCase(),
          style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.5,
              color: muted ? p.textMuted : p.onInk)),
    );
  }
}

class _MonthGrid extends StatelessWidget {
  const _MonthGrid({required this.year, required this.month, required this.history});
  final int year;
  final int month;
  final Map<String, int> history;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final first = DateTime(year, month, 1);
    final daysInMonth = DateTime(year, month + 1, 0).day;
    final leadingSpaces = (first.weekday % 7); // Sun=0
    final today = DateTime.now();
    final isThisMonth = today.year == year && today.month == month;
    final fmt = DateFormat('yyyy-MM-dd');
    const weekLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 7,
            mainAxisSpacing: 4,
            crossAxisSpacing: 4,
            childAspectRatio: 1,
          ),
          itemCount: weekLabels.length + leadingSpaces + daysInMonth,
          itemBuilder: (_, i) {
            if (i < 7) {
              return Center(
                  child: Text(weekLabels[i],
                      style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: p.textFaint)));
            }
            final dayIndex = i - 7 - leadingSpaces + 1;
            if (dayIndex < 1) return const SizedBox.shrink();
            final date = DateTime(year, month, dayIndex);
            final key = fmt.format(date);
            final count = history[key] ?? 0;
            final isToday = isThisMonth && dayIndex == today.day;
            final isFuture =
                date.isAfter(DateTime(today.year, today.month, today.day));

            Color bg;
            Color fg;
            if (count > 0) {
              bg = p.ink;
              fg = p.onInk;
            } else if (isFuture) {
              bg = Colors.transparent;
              fg = p.textFaint;
            } else {
              bg = p.canvas;
              fg = p.textFaint;
            }
            return Container(
              decoration: BoxDecoration(
                color: bg,
                borderRadius: BorderRadius.circular(8),
                border: count == 0 && !isFuture
                    ? Border.all(color: p.border)
                    : null,
              ),
              alignment: Alignment.center,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Text('$dayIndex',
                      style: TextStyle(
                          fontSize: 11,
                          fontWeight:
                              isToday ? FontWeight.w900 : FontWeight.w600,
                          color: fg)),
                  if (isToday && count == 0)
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                          color: p.ink, shape: BoxShape.circle),
                    ),
                ],
              ),
            );
          },
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            _LegendDot(color: p.ink, label: 'Done'),
            const SizedBox(width: 16),
            _LegendDot(color: p.canvas, border: p.border, label: 'None'),
          ],
        ),
      ],
    );
  }
}

class _LegendDot extends StatelessWidget {
  const _LegendDot({required this.color, required this.label, this.border});
  final Color color;
  final Color? border;
  final String label;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
              border: border != null ? Border.all(color: border!) : null),
        ),
        const SizedBox(width: 6),
        Text(label, style: TextStyle(fontSize: 11, color: p.textFaint)),
      ],
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textMuted)),
          Text(value,
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: p.textPrimary)),
        ],
      ),
    );
  }
}

class _IconTile extends StatelessWidget {
  const _IconTile({
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
          border: Border.all(
              color: primary ? p.ink : p.border),
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
