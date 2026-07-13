import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/task.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/tasks_repository.dart';
import '../state/task_timer_controller.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/priority_badge.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';
import '../widgets/task_timer_pill.dart';

/// Task detail / view-task screen. Mirrors mobile/views/view-task.php.
class TaskDetailScreen extends ConsumerStatefulWidget {
  const TaskDetailScreen({super.key, required this.taskId});
  final String taskId;

  @override
  ConsumerState<TaskDetailScreen> createState() => _TaskDetailScreenState();
}

class _TaskDetailScreenState extends ConsumerState<TaskDetailScreen> {
  late final FutureProvider<({Task task, String projectId})?> _provider;
  // Captured app-level container so we can clear the active marker AFTER the
  // teardown frame — `ref` is invalid once this widget is being removed, and
  // writing a provider synchronously during deactivate() crashes Riverpod.
  ProviderContainer? _container;

  @override
  void initState() {
    super.initState();
    _provider = FutureProvider<({Task task, String projectId})?>(
        (ref) => ref.watch(tasksRepositoryProvider).fetchTask(widget.taskId));
    // Mark this task detail as the active screen so the global timer pill
    // hides itself here (the pill lives above the router and can't read the
    // route on its own).
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.read(activeTaskDetailProvider.notifier).state = widget.taskId;
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _container ??= ProviderScope.containerOf(context, listen: false);
  }

  @override
  void deactivate() {
    // Clear the active-detail marker when leaving this screen — but defer it out
    // of the build/teardown cycle (a synchronous provider write here throws
    // "Tried to modify a provider while the widget tree was building").
    final container = _container;
    final taskId = widget.taskId;
    if (container != null) {
      Future.microtask(() {
        try {
          if (container.read(activeTaskDetailProvider) == taskId) {
            container.read(activeTaskDetailProvider.notifier).state = null;
          }
        } catch (_) {
          // Container disposed during app teardown — nothing to clear.
        }
      });
    }
    super.deactivate();
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final task = ref.watch(_provider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Task'),
        actions: [
          IconButton(
            icon: const HeroIcon(HeroPaths.pencil, size: 20),
            onPressed: () async {
              await context.push('/tasks/${widget.taskId}/edit');
              if (mounted) ref.invalidate(_provider);
            },
          ),
        ],
      ),
      body: task.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 28, radius: AppRadii.card),
            SizedBox(height: 16),
            Skeleton(height: 180, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 120, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.tasks,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(_provider),
          ),
        ),
        data: (data) {
          if (data == null) {
            return const Center(
              child: EmptyState(
                  iconPath: HeroPaths.tasks, message: 'Task not found'),
            );
          }
          return _Body(
            task: data.task,
            projectId: data.projectId,
            onChanged: () => ref.invalidate(_provider),
          );
        },
      ),
    );
  }
}

class _Body extends ConsumerStatefulWidget {
  const _Body({required this.task, required this.projectId, required this.onChanged});
  final Task task;
  final String projectId;
  final VoidCallback onChanged;

  @override
  ConsumerState<_Body> createState() => _BodyState();
}

class _BodyState extends ConsumerState<_Body> {
  late Task _task;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _task = widget.task;
  }

  @override
  void didUpdateWidget(covariant _Body old) {
    super.didUpdateWidget(old);
    if (old.task != widget.task) _task = widget.task;
  }

  Future<void> _toggleDone() async {
    final newDone = !_task.done;
    setState(() {
      _task = _task.copyWith(status: newDone ? 'done' : 'todo');
      _busy = true;
    });
    try {
      await ref.read(tasksRepositoryProvider).toggleComplete(
            taskId: _task.id,
            projectId: widget.projectId,
            done: newDone,
          );
      ref.invalidate(projectsProvider);
      ref.invalidate(allTasksProvider);
      ref.invalidate(dashboardProvider);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed: $e'), behavior: SnackBarBehavior.floating));
        setState(() => _task = widget.task); // revert
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _addSubtask() async {
    final controller = TextEditingController();
    final title = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppPalette.of(ctx).surface,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.card),
            side: BorderSide(color: AppPalette.of(ctx).border)),
        title: const Text('Add subtask'),
        content: TextField(
          controller: controller,
          autofocus: true,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(hintText: 'Subtask title'),
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('CANCEL')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: const Text('ADD'),
          ),
        ],
      ),
    );
    if (title == null || title.isEmpty) return;
    try {
      await ref.read(tasksRepositoryProvider).addSubtask(
            taskId: _task.id,
            projectId: widget.projectId,
            title: title,
          );
      widget.onChanged();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _toggleSubtask(int index, bool val) async {
    try {
      await ref.read(tasksRepositoryProvider).setSubtaskComplete(
            taskId: _task.id,
            projectId: widget.projectId,
            index: index,
            completed: val,
          );
      widget.onChanged();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _logTime() async {
    final minsController = TextEditingController(text: '30');
    final descController = TextEditingController();
    final p = AppPalette.of(context);
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: p.surface,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.card),
            side: BorderSide(color: p.border)),
        title: const Text('Log time'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: minsController,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                  labelText: 'Minutes', border: OutlineInputBorder()),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: descController,
              textCapitalization: TextCapitalization.sentences,
              decoration: const InputDecoration(
                  labelText: 'Description (optional)',
                  border: OutlineInputBorder()),
            ),
          ],
        ),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('CANCEL')),
          TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('LOG')),
        ],
      ),
    );
    if (ok != true) return;
    final minutes = int.tryParse(minsController.text) ?? 0;
    if (minutes <= 0) return;
    try {
      await ref.read(tasksRepositoryProvider).logTime(
            taskId: _task.id,
            projectId: widget.projectId,
            minutes: minutes,
            description: descController.text.trim(),
          );
      widget.onChanged();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Time logged'), behavior: SnackBarBehavior.floating));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _delete() async {
    final ok = await confirmDialog(context,
        title: 'Delete task',
        message: 'Delete "${_task.title}"? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(tasksRepositoryProvider).deleteTask(
            taskId: _task.id,
            projectId: widget.projectId,
          );
      ref.invalidate(projectsProvider);
      ref.invalidate(allTasksProvider);
      ref.invalidate(dashboardProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return RefreshIndicator(
      onRefresh: () async => widget.onChanged(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          FadeIn(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [PriorityBadge(_task.priority)]),
                const SizedBox(height: 12),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        _task.title,
                        style: TextStyle(
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                          color: _task.done ? p.textFaint : p.textPrimary,
                          decoration:
                              _task.done ? TextDecoration.lineThrough : null,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    GestureDetector(
                      onTap: _busy ? null : _toggleDone,
                      child: HeroIcon(
                        _task.done ? HeroPaths.checkCircle : HeroPaths.circle,
                        size: 28,
                        color: _task.done ? p.ink : p.textFaint,
                      ),
                    ),
                  ],
                ),
                if (_task.projectName != null) ...[
                  const SizedBox(height: 8),
                  Text(_task.projectName!.toUpperCase(),
                      style: AppType.label(context, color: p.textMuted)),
                ],
              ],
            ),
          ),
          if (_task.hasDescription) ...[
            const SizedBox(height: AppSpacing.gap),
            FadeIn(
              delay: const Duration(milliseconds: 60),
              child: SectionCard(
                heading: 'Description',
                child: Text(_task.description,
                    style: TextStyle(fontSize: 14, height: 1.5, color: p.textPrimary)),
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: SectionCard(
              heading: 'Subtasks',
              trailing: GestureDetector(
                onTap: _addSubtask,
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  HeroIcon(HeroPaths.plus, size: 14, color: p.ink),
                  const SizedBox(width: 4),
                  Text('ADD', style: AppType.label(context, color: p.ink)),
                ]),
              ),
              child: _task.subtasks.isEmpty
                  ? Text('No subtasks',
                      style: TextStyle(color: p.textFaint, fontSize: 14))
                  : Column(
                      children: [
                        for (var i = 0; i < _task.subtasks.length; i++) ...[
                          if (i > 0) Divider(height: 1, color: p.border),
                          _SubtaskRow(
                            subtask: _task.subtasks[i],
                            onToggle: (val) => _toggleSubtask(i, val),
                          ),
                        ],
                      ],
                    ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 150),
            child: SectionCard(
              heading: 'Time Tracking',
              child: _TimeTracker(
                task: _task,
                projectId: widget.projectId,
                onChanged: widget.onChanged,
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
                  _MetaRow(label: 'Status', value: _statusLabel(_task.status)),
                  if (_task.dueDate != null)
                    _MetaRow(
                        label: 'Due',
                        value:
                            DateFormat.yMMMd().format(_task.dueDate!.toLocal())),
                  _MetaRow(
                      label: 'Estimated',
                      value: _fmtMins(_task.estimatedMinutes)),
                  _MetaRow(
                      label: 'Logged', value: _fmtMins(_task.actualMinutes)),
                  if (_task.createdAt != null)
                    _MetaRow(
                        label: 'Created',
                        value: DateFormat.yMMMd()
                            .format(_task.createdAt!.toLocal())),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 240),
            child: Column(
              children: [
                Row(children: [
                  Expanded(
                    child: _ActionTile(
                      icon: HeroPaths.clock,
                      label: 'Log time',
                      onTap: _logTime,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _ActionTile(
                      icon: HeroPaths.trash,
                      label: 'Delete',
                      destructive: true,
                      onTap: _delete,
                    ),
                  ),
                ]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Live stopwatch / countdown for a task. Reads the global [TaskTimerController];
/// Start binds the timer to this task, Pause/Resume toggle it, and Stop & Log
/// pipes the elapsed minutes through the existing `logTime()` API. Mirrors the
/// PHP `Mobile.viewTaskTimer` (view-task.php:697-896).
class _TimeTracker extends ConsumerStatefulWidget {
  const _TimeTracker({
    required this.task,
    required this.projectId,
    required this.onChanged,
  });
  final Task task;
  final String projectId;
  final VoidCallback onChanged;

  @override
  ConsumerState<_TimeTracker> createState() => _TimeTrackerState();
}

class _TimeTrackerState extends ConsumerState<_TimeTracker> {
  bool _stopping = false;

  Task get _task => widget.task;

  Future<void> _start() async {
    ref.read(taskTimerControllerProvider).start(
          taskId: _task.id,
          projectId: widget.projectId,
          title: _task.title,
          estimatedMinutes: _task.estimatedMinutes,
        );
  }

  Future<void> _stopAndLog() async {
    final controller = ref.read(taskTimerControllerProvider);
    final minutes = controller.stop();
    if (minutes <= 0) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Timer stopped — nothing to log yet'),
            behavior: SnackBarBehavior.floating));
      }
      return;
    }
    setState(() => _stopping = true);
    try {
      await ref.read(tasksRepositoryProvider).logTime(
            taskId: _task.id,
            projectId: widget.projectId,
            minutes: minutes,
            description: 'Tracked via timer',
          );
      widget.onChanged();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text('Logged $minutes min'),
            behavior: SnackBarBehavior.floating));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _stopping = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final controller = ref.watch(taskTimerControllerProvider);
    final runningHere =
        controller.isActive && controller.taskId == _task.id;
    final runningElsewhere =
        controller.isForDifferentTask(_task.id);

    // Stopwatch mode (no estimate) vs countdown (estimate > 0).
    final countdown = _task.estimatedMinutes > 0;
    final time = controller.isActive
        ? controller.timeLabel
        : (countdown ? _formatMins(_task.estimatedMinutes * 60) : '00:00');
    final modeLabel = runningHere ? controller.mode.label : 'Ready';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Time + mode row.
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              time,
              style: AppType.mono(
                size: 34,
                color: controller.mode == TaskTimerMode.overtime
                    ? const Color(0xFFDC2626)
                    : p.textPrimary,
              ),
            ),
            const SizedBox(width: 12),
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text(modeLabel.toUpperCase(),
                  style: AppType.label(context, color: p.textMuted)),
            ),
            const Spacer(),
            if (controller.mode == TaskTimerMode.overtime)
              Text('OVER',
                  style: AppType.label(context,
                      color: const Color(0xFFDC2626))),
          ],
        ),
        const SizedBox(height: 6),
        Text(
          countdown
              ? 'Countdown · est ${_fmtMins(_task.estimatedMinutes)}'
              : 'Stopwatch',
          style: TextStyle(fontSize: 12, color: p.textFaint),
        ),

        // Another task's timer is running — notice + link.
        if (runningElsewhere) ...[
          const SizedBox(height: 14),
          _OtherTaskNotice(taskId: controller.taskId!),
        ],

        const SizedBox(height: 14),
        // Controls.
        Row(
          children: [
            Expanded(
              child: _TimerButton(
                label: controller.mode == TaskTimerMode.paused
                    ? 'Resume'
                    : 'Start',
                iconPath: HeroPaths.play,
                primary: true,
                enabled: !runningElsewhere &&
                    !_stopping &&
                    !(runningHere && controller.isRunning),
                onPressed: () {
                  if (controller.mode == TaskTimerMode.paused) {
                    ref.read(taskTimerControllerProvider).resume();
                  } else {
                    _start();
                  }
                },
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _TimerButton(
                label: 'Pause',
                iconPath: HeroPaths.pause,
                primary: false,
                enabled: runningHere && controller.isRunning,
                onPressed: () =>
                    ref.read(taskTimerControllerProvider).pause(),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: _TimerButton(
                label: _stopping ? '…' : 'Stop & Log',
                iconPath: HeroPaths.check,
                primary: false,
                enabled: runningHere && !_stopping,
                onPressed: _stopAndLog,
              ),
            ),
          ],
        ),
      ],
    );
  }
}

/// Small notice shown when a *different* task's timer is running, with a link
/// to that task (mirrors the PHP floatingTimer redirect).
class _OtherTaskNotice extends StatelessWidget {
  const _OtherTaskNotice({required this.taskId});
  final String taskId;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () => context.push('/tasks/$taskId'),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: p.canvas,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          children: [
            HeroIcon(HeroPaths.clock, size: 16, color: p.ink),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                'Another timer is running. Tap to view.',
                style: TextStyle(fontSize: 12, color: p.textMuted),
              ),
            ),
            HeroIcon(HeroPaths.chevronRight, size: 14, color: p.ink),
          ],
        ),
      ),
    );
  }
}

/// A compact bordered/ink button for the timer controls.
class _TimerButton extends StatelessWidget {
  const _TimerButton({
    required this.label,
    required this.iconPath,
    required this.primary,
    required this.enabled,
    required this.onPressed,
  });
  final String label;
  final String iconPath;
  final bool primary;
  final bool enabled;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = primary ? p.onInk : p.textPrimary;
    return Opacity(
      opacity: enabled ? 1 : 0.4,
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: enabled ? onPressed : null,
        child: Container(
          constraints: const BoxConstraints(minHeight: 40),
          padding: const EdgeInsets.symmetric(vertical: 9),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: primary ? p.ink : Colors.transparent,
            border: primary ? null : Border.all(color: p.border),
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              HeroIcon(iconPath, size: 13, color: color),
              const SizedBox(width: 6),
              Text(label.toUpperCase(),
                  style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.8,
                    color: color,
                  )),
            ],
          ),
        ),
      ),
    );
  }
}

String _formatMins(int seconds) {
  final m = (seconds.abs() ~/ 60);
  final s = (seconds.abs() % 60);
  return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
}

class _SubtaskRow extends StatelessWidget {
  const _SubtaskRow({required this.subtask, required this.onToggle});
  final Subtask subtask;
  final ValueChanged<bool> onToggle;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          GestureDetector(
            behavior: HitTestBehavior.opaque,
            onTap: () => onToggle(!subtask.completed),
            child: HeroIcon(
              subtask.completed ? HeroPaths.checkCircle : HeroPaths.circle,
              size: 20,
              color: subtask.completed ? p.ink : p.textFaint,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              subtask.title,
              style: TextStyle(
                fontSize: 14,
                color: subtask.completed ? p.textFaint : p.textPrimary,
                decoration:
                    subtask.completed ? TextDecoration.lineThrough : null,
              ),
            ),
          ),
          if (subtask.estimatedMinutes > 0)
            Text('${subtask.estimatedMinutes}m',
                style: AppType.mono(size: 12, color: p.textFaint)),
        ],
      ),
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

class _ActionTile extends StatelessWidget {
  const _ActionTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.destructive = false,
  });
  final String icon;
  final String label;
  final VoidCallback onTap;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = destructive ? const Color(0xFFDC2626) : p.textPrimary;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.card),
        ),
        child: Column(
          children: [
            HeroIcon(icon, size: 20, color: color),
            const SizedBox(height: 6),
            Text(label.toUpperCase(),
                style: AppType.label(context, color: color, size: 11)),
          ],
        ),
      ),
    );
  }
}

String _statusLabel(String status) {
  switch (status) {
    case 'backlog':
      return 'Backlog';
    case 'todo':
      return 'To Do';
    case 'in_progress':
      return 'In Progress';
    case 'review':
      return 'Review';
    case 'done':
      return 'Done';
    default:
      return status;
  }
}

String _fmtMins(int mins) {
  if (mins <= 0) return '0m';
  if (mins < 60) return '${mins}m';
  final h = mins ~/ 60;
  final m = mins % 60;
  return m == 0 ? '${h}h' : '${h}h ${m}m';
}
