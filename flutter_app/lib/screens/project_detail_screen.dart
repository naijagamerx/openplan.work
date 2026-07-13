import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/project.dart';
import '../models/task.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/projects_repository.dart';
import '../repositories/tasks_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/priority_badge.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Project detail / view-project screen — mirrors mobile/views/view-project.php.
class ProjectDetailScreen extends ConsumerStatefulWidget {
  const ProjectDetailScreen({super.key, required this.projectId});
  final String projectId;

  @override
  ConsumerState<ProjectDetailScreen> createState() =>
      _ProjectDetailScreenState();
}

class _ProjectDetailScreenState extends ConsumerState<ProjectDetailScreen> {
  late final FutureProvider<Project?> _provider;

  @override
  void initState() {
    super.initState();
    _provider = FutureProvider<Project?>(
        (ref) => ref.watch(projectsCrudProvider).fetchProject(widget.projectId));
  }

  @override
  Widget build(BuildContext context) {
    final project = ref.watch(_provider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Project'),
        actions: [
          IconButton(
            icon: const HeroIcon(HeroPaths.pencil, size: 20),
            onPressed: () async {
              await context.push('/projects/${widget.projectId}/edit');
              if (mounted) ref.invalidate(_provider);
            },
          ),
        ],
      ),
      body: project.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 120, radius: AppRadii.section),
            SizedBox(height: 16),
            Skeleton(height: 100, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 160, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.folder,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(_provider),
          ),
        ),
        data: (p) {
          if (p == null) {
            return const Center(
              child: EmptyState(
                  iconPath: HeroPaths.folder, message: 'Project not found'),
            );
          }
          return _Body(
            project: p,
            onChanged: () => ref.invalidate(_provider),
          );
        },
      ),
    );
  }
}

class _Body extends ConsumerStatefulWidget {
  const _Body({required this.project, required this.onChanged});
  final Project project;
  final VoidCallback onChanged;

  @override
  ConsumerState<_Body> createState() => _BodyState();
}

class _BodyState extends ConsumerState<_Body> {
  late Project _project;
  bool _deleting = false;

  @override
  void initState() {
    super.initState();
    _project = widget.project;
  }

  @override
  void didUpdateWidget(covariant _Body old) {
    super.didUpdateWidget(old);
    if (old.project != widget.project) _project = widget.project;
  }

  Future<void> _moveTask(Task task, String nextStatus) async {
    if (task.status == nextStatus) return;
    // Optimistically relocate the task into the new status column.
    setState(() {
      _project = _project.copyWith(
        tasks: _project.tasks
            .map((t) => t.id == task.id ? t.copyWith(status: nextStatus) : t)
            .toList(),
      );
    });
    try {
      await ref.read(tasksRepositoryProvider).updateTask(
            taskId: task.id,
            projectId: _project.id,
            status: nextStatus,
          );
      ref.invalidate(projectsProvider);
      ref.invalidate(allTasksProvider);
      ref.invalidate(dashboardProvider);
      // Reload the single project to pick up server-side counts/normalisation.
      widget.onChanged();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$e'), behavior: SnackBarBehavior.floating));
        setState(() => _project = widget.project); // revert
      }
    }
  }

  Future<void> _delete() async {
    final ok = await confirmDialog(context,
        title: 'Delete project',
        message:
            'Delete "${_project.name}" and all its tasks? This cannot be undone.');
    if (!ok) return;
    setState(() => _deleting = true);
    try {
      await ref.read(projectsCrudProvider).delete(_project.id);
      ref.invalidate(projectsProvider);
      ref.invalidate(allTasksProvider);
      ref.invalidate(dashboardProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _deleting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final avatarColor = _parseColor(_project.color) ?? p.ink;
    final progressPct = (_project.progress * 100).round();
    final byStatus = _groupTasks(_project.tasks);

    return RefreshIndicator(
      onRefresh: () async => widget.onChanged(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          // Header
          FadeIn(
            child: SectionCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _Avatar(
                          initial: _project.initial, color: avatarColor),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _project.name,
                              style: TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.3,
                                color: p.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 6,
                              crossAxisAlignment: WrapCrossAlignment.center,
                              children: [
                                _StatusBadge(status: _project.status),
                                if (_project.clientName != null &&
                                    _project.clientName!.isNotEmpty)
                                  Text(
                                    _project.clientName!.toUpperCase(),
                                    style: AppType.label(
                                        context, color: p.textMuted),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  if (_project.hasDescription) ...[
                    const SizedBox(height: 12),
                    Text(
                      _project.description,
                      style: TextStyle(
                          fontSize: 14, height: 1.5, color: p.textPrimary),
                    ),
                  ],
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: _HeaderButton(
                          label: 'Edit',
                          icon: HeroPaths.pencil,
                          onTap: () async {
                            await context.push(
                                '/projects/${_project.id}/edit');
                            if (mounted) widget.onChanged();
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _HeaderButton(
                          label: 'Add Task',
                          icon: HeroPaths.plus,
                          primary: true,
                          onTap: () async {
                            await context.push(
                                '/tasks/new?projectId=${_project.id}');
                            if (mounted) widget.onChanged();
                          },
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: _HeaderButton(
                          label: 'Delete',
                          icon: HeroPaths.trash,
                          destructive: true,
                          onTap: _deleting ? null : _delete,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),

          // Stats
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 60),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                        child: _StatCard(
                            label: 'Total', value: _project.taskCount)),
                    const SizedBox(width: 8),
                    Expanded(
                        child: _StatCard(
                            label: 'Done',
                            value: _project.completedCount)),
                    const SizedBox(width: 8),
                    Expanded(
                        child: _StatCard(
                            label: 'Pending',
                            value: _project.pendingCount)),
                    const SizedBox(width: 8),
                    Expanded(
                        child: _StatCard(
                            label: 'Progress', value: '$progressPct%')),
                  ],
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(AppSpacing.cardPad),
                  decoration: BoxDecoration(
                    color: p.surface,
                    border: Border.all(color: p.border),
                    borderRadius: BorderRadius.circular(AppRadii.card),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('OVERALL PROGRESS',
                              style: AppType.label(context,
                                  color: p.textMuted, size: 11)),
                          Text('$progressPct%',
                              style: AppType.label(context,
                                  color: p.textPrimary, size: 11)),
                        ],
                      ),
                      const SizedBox(height: 10),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(AppRadii.pill),
                        child: LinearProgressIndicator(
                          value: _project.progress,
                          minHeight: 8,
                          backgroundColor: p.border,
                          valueColor:
                              AlwaysStoppedAnimation<Color>(p.ink),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          // Task board
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(left: 4, bottom: 10),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('TASK BOARD',
                          style: AppType.label(context,
                              color: p.textPrimary, size: 13)),
                      Text('TAP STATUS TO MOVE',
                          style: AppType.label(context,
                              color: p.textMuted, size: 9)),
                    ],
                  ),
                ),
                for (final entry in byStatus)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _StatusColumn(
                      status: entry.status,
                      tasks: entry.tasks,
                      onMove: _moveTask,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// Group tasks by status, preserving the canonical column order.
  List<_Column> _groupTasks(List<Task> tasks) {
    const order = [
      'backlog',
      'todo',
      'in_progress',
      'review',
      'done',
    ];
    final map = <String, List<Task>>{
      for (final s in order) s: [],
    };
    for (final t in tasks) {
      final key = map.containsKey(t.status) ? t.status : 'todo';
      map[key]!.add(t);
    }
    return [for (final s in order) _Column(status: s, tasks: map[s]!)];
  }
}

class _Column {
  const _Column({required this.status, required this.tasks});
  final String status;
  final List<Task> tasks;
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.initial, required this.color});
  final String initial;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      height: 48,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Text(
        initial,
        style: const TextStyle(
          fontFamily: 'Geist',
          fontSize: 18,
          fontWeight: FontWeight.w800,
          color: Colors.white,
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
    final key = Project.normalizeStatus(status);
    final filled = key == 'completed' || key == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: filled ? p.ink : Colors.transparent,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        Project.statusLabel(status).toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.6,
          color: filled ? p.onInk : p.textPrimary,
        ),
      ),
    );
  }
}

class _HeaderButton extends StatelessWidget {
  const _HeaderButton({
    required this.label,
    required this.icon,
    required this.onTap,
    this.primary = false,
    this.destructive = false,
  });

  final String label;
  final String icon;
  final VoidCallback? onTap;
  final bool primary;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = destructive ? const Color(0xFFDC2626) : p.textPrimary;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        height: AppSpacing.touchTarget,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: primary ? p.ink : Colors.transparent,
          border: Border.all(
              color: destructive ? const Color(0xFFDC2626) : p.ink),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Text(
          label.toUpperCase(),
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 10,
            fontWeight: FontWeight.w800,
            letterSpacing: 1,
            color: primary ? p.onInk : color,
          ),
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});
  final String label;
  final Object value; // int (counts) or String ('45%')

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textMuted, size: 9)),
          const SizedBox(height: 6),
          Text('$value',
              style: AppType.mono(size: 20, color: p.textPrimary)),
        ],
      ),
    );
  }
}

class _StatusColumn extends StatelessWidget {
  const _StatusColumn({
    required this.status,
    required this.tasks,
    required this.onMove,
  });

  final String status;
  final List<Task> tasks;
  final void Function(Task task, String nextStatus) onMove;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _taskStatusLabel(status).toUpperCase(),
                style: AppType.label(context,
                    color: p.textPrimary, size: 11),
              ),
              Container(
                width: 24,
                height: 24,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: Colors.transparent,
                  border: Border.all(color: p.border),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '${tasks.length}',
                  style: TextStyle(
                    fontFamily: 'GeistMono',
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    color: p.textPrimary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (tasks.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Text('No tasks',
                  style: AppType.label(context, color: p.textFaint, size: 9)),
            )
          else
            for (final t in tasks)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _TaskCard(task: t, onMove: onMove),
              ),
        ],
      ),
    );
  }
}

class _TaskCard extends ConsumerWidget {
  const _TaskCard({required this.task, required this.onMove});
  final Task task;
  final void Function(Task task, String nextStatus) onMove;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              PriorityBadge(task.priority),
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () async {
                  await context.push('/tasks/${task.id}');
                },
                child: HeroIcon(HeroPaths.chevronRight,
                    size: 16, color: p.textFaint),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            task.title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              height: 1.25,
              color: p.textPrimary,
            ),
          ),
          if (task.dueDate != null) ...[
            const SizedBox(height: 6),
            Text(
              'DUE ${_shortDate(task.dueDate!)}',
              style: AppType.label(context, color: p.textFaint, size: 9),
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              Text('STATUS',
                  style: AppType.label(context, color: p.textFaint, size: 8)),
              const SizedBox(width: 8),
              Expanded(
                child: _StatusDropdown(
                  value: task.status,
                  onChanged: (next) => onMove(task, next),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StatusDropdown extends StatelessWidget {
  const _StatusDropdown({required this.value, required this.onChanged});
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: DropdownButton<String>(
        value: value,
        isExpanded: true,
        underline: const SizedBox(),
        icon: HeroIcon(HeroPaths.chevronDown, size: 16, color: p.textPrimary),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 11,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.6,
          color: p.textPrimary,
        ),
        dropdownColor: p.surface,
        items: const [
          DropdownMenuItem(value: 'backlog', child: Text('Backlog')),
          DropdownMenuItem(value: 'todo', child: Text('To Do')),
          DropdownMenuItem(value: 'in_progress', child: Text('In Progress')),
          DropdownMenuItem(value: 'review', child: Text('Review')),
          DropdownMenuItem(value: 'done', child: Text('Done')),
        ],
        onChanged: (v) {
          if (v != null) onChanged(v);
        },
      ),
    );
  }
}

String _taskStatusLabel(String status) {
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

String _shortDate(DateTime d) {
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec'
  ];
  final local = d.toLocal();
  return '${months[local.month - 1]} ${local.day}';
}

/// Parse a hex color string (#RGB / #RRGGBB), returning null if invalid.
Color? _parseColor(String? hex) {
  if (hex == null || hex.isEmpty) return null;
  var h = hex.replaceFirst('#', '');
  if (h.length == 3) {
    h = h.split('').map((c) => '$c$c').join();
  }
  if (h.length != 6) return null;
  final value = int.tryParse('FF$h', radix: 16);
  if (value == null) return null;
  return Color(value);
}
