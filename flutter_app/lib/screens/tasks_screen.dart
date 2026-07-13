import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/task.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/tasks_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/priority_badge.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

class TasksScreen extends ConsumerStatefulWidget {
  const TasksScreen({super.key});

  @override
  ConsumerState<TasksScreen> createState() => _TasksScreenState();
}

class _TasksScreenState extends ConsumerState<TasksScreen> {
  String _query = '';
  String? _statusFilter; // null = all
  String? _priorityFilter;

  @override
  Widget build(BuildContext context) {
    final projects = ref.watch(projectsProvider);

    return AppScaffold(
      title: 'Tasks',
      current: 'Tasks',
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/tasks/new');
          if (mounted) ref.invalidate(projectsProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: AppPalette.of(context).onInk),
      ),
      body: projects.when(
        loading: () => ListView(
          padding:
              const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 24),
          children: const [
            Skeleton(height: 140, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.clipboard,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(projectsProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.clipboard,
                message: 'No projects yet',
                actionLabel: 'New Task',
                onAction: () => context.push('/tasks/new'),
              ),
            );
          }

          // Flatten + filter client-side (mirrors tasks.php search/filter).
          final allTasks = <_FlatTask>[];
          for (final proj in list) {
            for (final t in proj.tasks) {
              allTasks.add(_FlatTask(task: t, projectName: proj.name, projectId: proj.id));
            }
          }
          final filtered = allTasks.where((ft) {
            if (_query.isNotEmpty &&
                !ft.task.title.toLowerCase().contains(_query.toLowerCase())) {
              return false;
            }
            if (_statusFilter != null && ft.task.status != _statusFilter) return false;
            if (_priorityFilter != null && ft.task.priority != _priorityFilter) {
              return false;
            }
            return true;
          }).toList();
          // incomplete first, then priority weight, then due date
          filtered.sort((a, b) {
            if (a.task.done != b.task.done) return a.task.done ? 1 : -1;
            final pri = _priorityWeight(b.task.priority)
                .compareTo(_priorityWeight(a.task.priority));
            if (pri != 0) return pri;
            return 0;
          });

          // Group back by project for display.
          final byProject = <String, List<_FlatTask>>{};
          for (final ft in filtered) {
            byProject.putIfAbsent(ft.projectName, () => []).add(ft);
          }

          return RefreshIndicator(
            onRefresh: () => ref.refresh(projectsProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 24),
              children: [
                _SearchAndFilters(
                  query: _query,
                  onQuery: (v) => setState(() => _query = v),
                  statusFilter: _statusFilter,
                  priorityFilter: _priorityFilter,
                  onStatus: (v) => setState(() => _statusFilter = v),
                  onPriority: (v) => setState(() => _priorityFilter = v),
                ),
                const SizedBox(height: 12),
                if (filtered.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No tasks match'),
                  )
                else
                  for (var i = 0; i < byProject.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 70).clamp(0, 420)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ProjectCard(
                          name: byProject.keys.elementAt(i),
                          tasks: byProject.values.elementAt(i),
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

  int _priorityWeight(String p) {
    switch (p) {
      case 'urgent':
        return 4;
      case 'high':
        return 3;
      case 'medium':
        return 2;
      case 'low':
      default:
        return 1;
    }
  }
}

class _FlatTask {
  _FlatTask({required this.task, required this.projectName, required this.projectId});
  final Task task;
  final String projectName;
  final String projectId;
}

class _SearchAndFilters extends StatelessWidget {
  const _SearchAndFilters({
    required this.query,
    required this.onQuery,
    required this.statusFilter,
    required this.priorityFilter,
    required this.onStatus,
    required this.onPriority,
  });

  final String query;
  final ValueChanged<String> onQuery;
  final String? statusFilter;
  final String? priorityFilter;
  final ValueChanged<String?> onStatus;
  final ValueChanged<String?> onPriority;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          onChanged: onQuery,
          textCapitalization: TextCapitalization.sentences,
          decoration: InputDecoration(
            isDense: true,
            hintText: 'Search tasks…',
            hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
            prefixIcon: HeroIcon(HeroPaths.search, size: 18, color: p.textFaint),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.ink, width: 1.5)),
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 6,
          children: [
            _FilterChip(
                label: 'All',
                selected: statusFilter == null,
                onTap: () => onStatus(null)),
            _FilterChip(
                label: 'To Do',
                selected: statusFilter == 'todo',
                onTap: () => onStatus('todo')),
            _FilterChip(
                label: 'In Progress',
                selected: statusFilter == 'in_progress',
                onTap: () => onStatus('in_progress')),
            _FilterChip(
                label: 'Done',
                selected: statusFilter == 'done',
                onTap: () => onStatus('done')),
            const SizedBox(width: 6),
            _FilterChip(
                label: 'Urgent',
                selected: priorityFilter == 'urgent',
                color: priorityColor('urgent'),
                onTap: () => onPriority(
                    priorityFilter == 'urgent' ? null : 'urgent')),
            _FilterChip(
                label: 'High',
                selected: priorityFilter == 'high',
                color: priorityColor('high'),
                onTap: () =>
                    onPriority(priorityFilter == 'high' ? null : 'high')),
          ],
        ),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.color,
  });
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fill = selected ? (color ?? p.ink) : Colors.transparent;
    final fg = selected ? (color != null ? Colors.white : p.onInk) : p.textPrimary;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: selected ? (color ?? p.ink) : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(label,
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: fg)),
      ),
    );
  }
}

class _ProjectCard extends StatelessWidget {
  const _ProjectCard({required this.name, required this.tasks});
  final String name;
  final List<_FlatTask> tasks;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SectionCard(
      heading: name,
      trailing:
          Text('${tasks.length} TASKS', style: AppType.label(context)),
      child: Column(
        children: [
          for (var i = 0; i < tasks.length; i++) ...[
            if (i > 0) Divider(height: 1, color: p.border),
            _TaskRow(ft: tasks[i]),
          ],
        ],
      ),
    );
  }
}

class _TaskRow extends ConsumerWidget {
  const _TaskRow({required this.ft});
  final _FlatTask ft;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final task = ft.task;
    return Dismissible(
      key: ValueKey(task.id),
      // swipe right = complete, swipe left = open detail (no destructive swipe-delete here
      // to avoid accidental data loss; deletion lives in the detail screen).
      direction: DismissDirection.startToEnd,
      background: Container(
        alignment: Alignment.centerLeft,
        padding: const EdgeInsets.only(left: 20),
        color: p.ink,
        child: HeroIcon(task.done ? HeroPaths.xMark : HeroPaths.check,
            color: p.onInk, size: 22),
      ),
      confirmDismiss: (_) async {
        try {
          await ref.read(tasksRepositoryProvider).toggleComplete(
                taskId: task.id,
                projectId: ft.projectId,
                done: !task.done,
              );
          ref.invalidate(projectsProvider);
          ref.invalidate(dashboardProvider);
        } catch (e) {
          if (context.mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text('$e')));
          }
        }
        return false; // never actually dismiss
      },
      child: InkWell(
        onTap: () async {
          await context.push('/tasks/${task.id}');
          if (context.mounted) ref.invalidate(projectsProvider);
        },
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Row(
            children: [
              HeroIcon(
                task.done ? HeroPaths.checkCircle : HeroPaths.circle,
                size: 20,
                color: task.done ? p.textPrimary : p.textFaint,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      task.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: task.done ? p.textFaint : p.textPrimary,
                        decoration:
                            task.done ? TextDecoration.lineThrough : null,
                      ),
                    ),
                    if (task.dueDate != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(
                          _dueLabel(task.dueDate!),
                          style: TextStyle(
                              fontSize: 11, color: _dueColor(task.dueDate!, p)),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              PriorityBadge(task.priority),
              const SizedBox(width: 6),
              HeroIcon(HeroPaths.chevronRight, size: 16, color: p.textFaint),
            ],
          ),
        ),
      ),
    );
  }

  String _dueLabel(DateTime d) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final due = DateTime(d.year, d.month, d.day);
    final diff = due.difference(today).inDays;
    if (diff < 0) return 'Overdue';
    if (diff == 0) return 'Due today';
    if (diff == 1) return 'Tomorrow';
    if (diff <= 7) return 'In $diff days';
    return '${d.day}/${d.month}';
  }

  Color _dueColor(DateTime d, AppPalette p) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final due = DateTime(d.year, d.month, d.day);
    if (due.isBefore(today)) return const Color(0xFFDC2626);
    return p.textFaint;
  }
}
