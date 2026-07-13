import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/meeting.dart';
import '../models/task.dart';
import '../repositories/meetings_repository.dart';
import '../repositories/tasks_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Calendar + Meetings screen. Mirrors mobile/views/calendar.php.
/// Plain Scaffold (not a primary tab) — pushed from the menu hub.
class CalendarScreen extends ConsumerStatefulWidget {
  const CalendarScreen({super.key});

  @override
  ConsumerState<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends ConsumerState<CalendarScreen> {
  late DateTime _focusedMonth;
  DateTime? _selectedDay;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _focusedMonth = DateTime(now.year, now.month, 1);
    _selectedDay = DateTime(now.year, now.month, now.day);
  }

  void _goPrevMonth() {
    setState(() {
      _focusedMonth = DateTime(_focusedMonth.year, _focusedMonth.month - 1, 1);
      _selectedDay = null;
    });
  }

  void _goNextMonth() {
    setState(() {
      _focusedMonth = DateTime(_focusedMonth.year, _focusedMonth.month + 1, 1);
      _selectedDay = null;
    });
  }

  void _selectDay(DateTime day) {
    setState(() => _selectedDay = day);
  }

  void _jumpToToday() {
    final now = DateTime.now();
    setState(() {
      _focusedMonth = DateTime(now.year, now.month, 1);
      _selectedDay = DateTime(now.year, now.month, now.day);
    });
  }

  Future<void> _newMeeting(DateTime date) async {
    await context.push('/meetings/new?date=${DateFormat('yyyy-MM-dd').format(date)}');
    if (mounted) ref.invalidate(meetingsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final projects = ref.watch(projectsProvider);
    final meetings = ref.watch(meetingsProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Calendar'),
        actions: [
          TextButton(
            onPressed: _jumpToToday,
            child: Text('TODAY',
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1,
                    color: AppPalette.of(context).ink)),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () => _newMeeting(_selectedDay ?? DateTime.now()),
        child: HeroIcon(HeroPaths.plus, size: 24, color: AppPalette.of(context).onInk),
      ),
      body: projects.when(
        loading: () => _Loading(),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.calendar,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(projectsProvider),
          ),
        ),
        data: (projectList) {
          // Compute tasks due per day (only within the focused month).
          final tasksByDay = _indexTasksByDay(projectList);
          return meetings.when(
            loading: () => _Loading(),
            error: (e, _) => Center(
              child: EmptyState(
                iconPath: HeroPaths.video,
                message: e.toString(),
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(meetingsProvider),
              ),
            ),
            data: (meetingList) {
              final meetingsByDay = _indexMeetingsByDay(meetingList);

              // Resolve the selected day (default to today if in this month).
              final today = DateTime.now();
              final DateTime selected = _selectedDay ??
                  (_focusedMonth.year == today.year &&
                          _focusedMonth.month == today.month
                      ? DateTime(today.year, today.month, today.day)
                      : _firstEventDay(tasksByDay, meetingsByDay) ??
                          DateTime(
                              _focusedMonth.year, _focusedMonth.month, 1));

              final dayTasks = tasksByDay[_dayKey(selected)] ?? const [];
              final dayMeetings = meetingsByDay[_dayKey(selected)] ?? const [];

              // Month totals for the summary strip.
              final monthTaskCount =
                  tasksByDay.values.fold<int>(0, (a, l) => a + l.length);
              final monthMeetingCount =
                  meetingsByDay.values.fold<int>(0, (a, l) => a + l.length);

              return RefreshIndicator(
                onRefresh: () async {
                  ref.invalidate(projectsProvider);
                  ref.invalidate(meetingsProvider);
                },
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(
                      AppSpacing.page, 8, AppSpacing.page, 120),
                  children: [
                    FadeIn(
                      child: _MonthNavigator(
                        month: _focusedMonth,
                        onPrev: _goPrevMonth,
                        onNext: _goNextMonth,
                      ),
                    ),
                    const SizedBox(height: 16),
                    FadeIn(
                      delay: const Duration(milliseconds: 60),
                      child: SectionCard(
                        child: _MonthGrid(
                          month: _focusedMonth,
                          selectedDay: selected,
                          tasksByDay: tasksByDay,
                          meetingsByDay: meetingsByDay,
                          onDaySelected: _selectDay,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    FadeIn(
                      delay: const Duration(milliseconds: 90),
                      child: _Legend(),
                    ),
                    const SizedBox(height: AppSpacing.gap),
                    FadeIn(
                      delay: const Duration(milliseconds: 120),
                      child: _SummaryStrip(
                        taskCount: monthTaskCount,
                        meetingCount: monthMeetingCount,
                        selectedLabel: DateFormat('MMM d').format(selected),
                      ),
                    ),
                    const SizedBox(height: AppSpacing.gap),
                    FadeIn(
                      delay: const Duration(milliseconds: 160),
                      child: _DayDetail(
                        date: selected,
                        meetings: dayMeetings,
                        tasks: dayTasks,
                        onAddMeeting: () => _newMeeting(selected),
                      ),
                    ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  /// Map "yyyy-MM-dd" → tasks whose dueDate falls on that day (all projects).
  /// Filters to the focused month for the summary count.
  Map<String, List<_CalTask>> _indexTasksByDay(List<dynamic> projectList) {
    final map = <String, List<_CalTask>>{};
    final fmt = DateFormat('yyyy-MM-dd');
    for (final proj in projectList) {
      for (final Task t in proj.tasks) {
        if (t.dueDate == null) continue;
        final due = t.dueDate!;
        if (due.year != _focusedMonth.year || due.month != _focusedMonth.month) {
          continue;
        }
        final key = fmt.format(due);
        map.putIfAbsent(key, () => [])
            .add(_CalTask(task: t, projectName: proj.name));
      }
    }
    // incomplete first, then due time (parity with calendar.php sort).
    for (final l in map.values) {
      l.sort((a, b) {
        if (a.task.done != b.task.done) return a.task.done ? 1 : -1;
        final ad = a.task.dueDate;
        final bd = b.task.dueDate;
        if (ad != null && bd != null) return ad.compareTo(bd);
        return 0;
      });
    }
    return map;
  }

  /// Map "yyyy-MM-dd" → meetings on that day (within the focused month).
  Map<String, List<Meeting>> _indexMeetingsByDay(List<Meeting> meetings) {
    final fmt = DateFormat('yyyy-MM-dd');
    final map = <String, List<Meeting>>{};
    for (final m in meetings) {
      final d = m.date;
      if (d.year != _focusedMonth.year || d.month != _focusedMonth.month) {
        continue;
      }
      map.putIfAbsent(fmt.format(d), () => []).add(m);
    }
    for (final l in map.values) {
      l.sort((a, b) =>
          (a.startTime ?? '').compareTo(b.startTime ?? ''));
    }
    return map;
  }

  DateTime? _firstEventDay(
      Map<String, List<_CalTask>> tasks, Map<String, List<Meeting>> meetings) {
    final keys = {...tasks.keys, ...meetings.keys}.toList()..sort();
    if (keys.isEmpty) return null;
    return DateTime.parse(keys.first);
  }

  String _dayKey(DateTime d) => DateFormat('yyyy-MM-dd').format(d);
}

class _Loading extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(AppSpacing.page),
      children: const [
        Skeleton(height: 56, radius: AppRadii.button),
        SizedBox(height: 16),
        Skeleton(height: 300, radius: AppRadii.section),
        SizedBox(height: AppSpacing.gap),
        Skeleton(height: 120, radius: AppRadii.section),
      ],
    );
  }
}

/// Month/year header with prev/next arrows.
class _MonthNavigator extends StatelessWidget {
  const _MonthNavigator({
    required this.month,
    required this.onPrev,
    required this.onNext,
  });

  final DateTime month;
  final VoidCallback onPrev;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        IconButton(
          icon: HeroIcon(HeroPaths.chevronLeft, size: 22, color: p.textPrimary),
          onPressed: onPrev,
          tooltip: 'Previous month',
        ),
        Text(
          DateFormat('MMMM y').format(month).toUpperCase(),
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 13,
            fontWeight: FontWeight.w700,
            letterSpacing: 2,
            color: p.textPrimary,
          ),
        ),
        IconButton(
          icon: HeroIcon(HeroPaths.chevronRight, size: 22, color: p.textPrimary),
          onPressed: onNext,
          tooltip: 'Next month',
        ),
      ],
    );
  }
}

/// Navigable month grid. Day cells show dots: green for tasks, purple/ink for meetings.
class _MonthGrid extends StatelessWidget {
  const _MonthGrid({
    required this.month,
    required this.selectedDay,
    required this.tasksByDay,
    required this.meetingsByDay,
    required this.onDaySelected,
  });

  final DateTime month;
  final DateTime selectedDay;
  final Map<String, List<_CalTask>> tasksByDay;
  final Map<String, List<Meeting>> meetingsByDay;
  final ValueChanged<DateTime> onDaySelected;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    const weekLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    final fmt = DateFormat('yyyy-MM-dd');

    final first = DateTime(month.year, month.month, 1);
    final daysInMonth = DateTime(month.year, month.month + 1, 0).day;
    final leading = first.weekday % 7; // Sunday=0
    final today = DateTime.now();
    final isThisMonth = today.year == month.year && today.month == month.month;

    return Column(
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
          itemCount: 7 + leading + daysInMonth,
          itemBuilder: (_, i) {
            if (i < 7) {
              return Center(
                child: Text(weekLabels[i],
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1,
                        color: p.textFaint)),
              );
            }
            final dayIndex = i - 7 - leading + 1;
            if (dayIndex < 1) return const SizedBox.shrink();
            final date = DateTime(month.year, month.month, dayIndex);
            final key = fmt.format(date);
            final hasTasks = (tasksByDay[key]?.isNotEmpty ?? false);
            final hasMeetings = (meetingsByDay[key]?.isNotEmpty ?? false);
            final isSelected = date.year == selectedDay.year &&
                date.month == selectedDay.month &&
                date.day == selectedDay.day;
            final isToday = isThisMonth && dayIndex == today.day;

            final Color bg = isSelected ? p.ink : Colors.transparent;
            final Color fg = isSelected
                ? p.onInk
                : (isToday ? p.ink : p.textPrimary);

            return GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () => onDaySelected(date),
              child: Container(
                decoration: BoxDecoration(
                  color: bg,
                  borderRadius: BorderRadius.circular(8),
                  border: isSelected
                      ? null
                      : Border.all(color: p.border, width: 1),
                ),
                alignment: Alignment.center,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    Text('$dayIndex',
                        style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 12,
                            fontWeight: isToday || isSelected
                                ? FontWeight.w800
                                : FontWeight.w500,
                            color: fg)),
                    if (hasTasks || hasMeetings)
                      Positioned(
                        bottom: 5,
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (hasTasks)
                              _Dot(
                                  color: isSelected
                                      ? p.onInk
                                      : const Color(0xFF16A34A)),
                            if (hasTasks && hasMeetings) const SizedBox(width: 3),
                            if (hasMeetings)
                              _Dot(
                                  color: isSelected
                                      ? p.onInk
                                      : const Color(0xFFA855F7)),
                          ],
                        ),
                      ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}

class _Dot extends StatelessWidget {
  const _Dot({required this.color});
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 5,
      height: 5,
      decoration: BoxDecoration(color: color, shape: BoxShape.circle),
    );
  }
}

class _Legend extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    Widget item(Color c, String label) => Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(color: c, shape: BoxShape.circle)),
            const SizedBox(width: 6),
            Text(label,
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1,
                    color: p.textFaint)),
          ],
        );
    return Row(
      children: [
        item(const Color(0xFF16A34A), 'TASKS'),
        const SizedBox(width: 16),
        item(const Color(0xFFA855F7), 'MEETINGS'),
      ],
    );
  }
}

/// Month totals + selected-date strip (parity with calendar.php summary bar).
class _SummaryStrip extends StatelessWidget {
  const _SummaryStrip({
    required this.taskCount,
    required this.meetingCount,
    required this.selectedLabel,
  });

  final int taskCount;
  final int meetingCount;
  final String selectedLabel;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: p.canvas,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            '$taskCount TASK${taskCount == 1 ? '' : 'S'} · '
            '$meetingCount MEETING${meetingCount == 1 ? '' : 'S'}',
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.5,
                color: p.textPrimary),
          ),
          Text(
            'SELECTED: $selectedLabel'.toUpperCase(),
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 10,
                fontWeight: FontWeight.w700,
                letterSpacing: 1.5,
                color: p.textFaint),
          ),
        ],
      ),
    );
  }
}

/// The selected day's meetings + tasks detail.
class _DayDetail extends StatelessWidget {
  const _DayDetail({
    required this.date,
    required this.meetings,
    required this.tasks,
    required this.onAddMeeting,
  });

  final DateTime date;
  final List<Meeting> meetings;
  final List<_CalTask> tasks;
  final VoidCallback onAddMeeting;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (meetings.isNotEmpty) ...[
          _SectionLabel(
            label: 'MEETINGS',
            color: const Color(0xFFA855F7),
            actionLabel: 'Add',
            onAction: onAddMeeting,
          ),
          const SizedBox(height: 10),
          for (final m in meetings) ...[
            _MeetingRow(meeting: m),
            const SizedBox(height: 4),
          ],
          const SizedBox(height: 8),
        ],
        _SectionLabel(
          label: 'TASKS',
          color: const Color(0xFF16A34A),
          actionLabel: meetings.isEmpty ? 'New Meeting' : null,
          onAction: onAddMeeting,
        ),
        const SizedBox(height: 10),
        if (tasks.isEmpty)
          const EmptyState(
              iconPath: HeroPaths.clipboard, message: 'No tasks on this day')
        else
          for (final t in tasks) ...[
            _TaskRow(task: t),
            const SizedBox(height: 4),
          ],
      ],
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel({
    required this.label,
    required this.color,
    this.actionLabel,
    this.onAction,
  });

  final String label;
  final Color color;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
            const SizedBox(width: 8),
            Text(label,
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.5,
                    color: p.textPrimary)),
          ],
        ),
        if (actionLabel != null && onAction != null)
          GestureDetector(
            onTap: onAction,
            behavior: HitTestBehavior.opaque,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                HeroIcon(HeroPaths.plus, size: 13, color: p.ink),
                const SizedBox(width: 3),
                Text(actionLabel!.toUpperCase(),
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.5,
                        color: p.ink)),
              ],
            ),
          ),
      ],
    );
  }
}

/// Colored bar + title + time + location. Tappable → edit the meeting.
class _MeetingRow extends ConsumerWidget {
  const _MeetingRow({required this.meeting});
  final Meeting meeting;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final barColor = _parseHex(meeting.color) ?? const Color(0xFFA855F7);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () async {
        await context.push('/meetings/${meeting.id}/edit');
        if (context.mounted) ref.invalidate(meetingsProvider);
      },
      child: Row(
        children: [
          Container(width: 3, height: 44, color: barColor),
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              padding: const EdgeInsets.only(bottom: 10),
              decoration: BoxDecoration(
                  border:
                      Border(bottom: BorderSide(color: p.border, width: 1))),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          meeting.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: p.textPrimary),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(meeting.timeLabel,
                          style: TextStyle(
                              fontFamily: 'GeistMono',
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: p.textMuted)),
                    ],
                  ),
                  if (meeting.hasLocation)
                    Padding(
                      padding: const EdgeInsets.only(top: 3),
                      child: Text(
                        '@ ${meeting.location}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1,
                            color: p.textFaint),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Task bar + title + project. Tappable → /tasks/:id.
class _TaskRow extends StatelessWidget {
  const _TaskRow({required this.task});
  final _CalTask task;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final t = task.task;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => context.push('/tasks/${t.id}'),
      child: Row(
        children: [
          Container(
              width: 3,
              height: 44,
              color: t.done ? p.border : p.ink),
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              padding: const EdgeInsets.only(bottom: 10),
              decoration: BoxDecoration(
                  border:
                      Border(bottom: BorderSide(color: p.border, width: 1))),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          t.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              decoration:
                                  t.done ? TextDecoration.lineThrough : null,
                              color: t.done ? p.textFaint : p.textPrimary),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                          t.dueDate != null &&
                                  _hasExplicitTime(t.dueDate!)
                              ? DateFormat('HH:mm').format(t.dueDate!)
                              : '--:--',
                          style: TextStyle(
                              fontFamily: 'GeistMono',
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: p.textMuted)),
                    ],
                  ),
                  Padding(
                    padding: const EdgeInsets.only(top: 3),
                    child: Text(
                      'PROJECT: ${task.projectName}'.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1,
                          color: p.textFaint),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  bool _hasExplicitTime(DateTime d) =>
      d.hour != 0 || d.minute != 0 || d.second != 0;
}

/// Parse a "#rrggbb" hex string into a Color.
Color? _parseHex(String? hex) {
  if (hex == null || hex.isEmpty) return null;
  var h = hex.trim();
  if (h.startsWith('#')) h = h.substring(1);
  if (h.length == 3) {
    h = h.split('').map((c) => '$c$c').join();
  }
  if (h.length != 6) return null;
  final value = int.tryParse(h, radix: 16);
  if (value == null) return null;
  return Color(0xFF000000 | value);
}

/// A task plus its project name, for calendar indexing/display.
class _CalTask {
  const _CalTask({required this.task, required this.projectName});
  final Task task;
  final String projectName;
}
