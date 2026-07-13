import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/task.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/tasks_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';

/// Create or edit a task. Mirrors mobile/views/task-form.php.
/// Pass [taskId] to edit; omit to create (requires [projectId] for create).
class TaskFormScreen extends ConsumerStatefulWidget {
  const TaskFormScreen({super.key, this.taskId, this.projectId});

  final String? taskId;
  final String? projectId;

  @override
  ConsumerState<TaskFormScreen> createState() => _TaskFormScreenState();
}

class _TaskFormScreenState extends ConsumerState<TaskFormScreen> {
  bool get _isEdit => widget.taskId != null;

  final _title = TextEditingController();
  final _description = TextEditingController();
  final _estimated = TextEditingController();
  final List<TextEditingController> _subControllers = [];

  String _status = 'todo';
  String _priority = 'medium';
  String? _projectId;
  DateTime? _dueDate;
  bool _loading = false;
  bool _saving = false;
  String? _editProjectId;

  @override
  void initState() {
    super.initState();
    _projectId = widget.projectId;
    if (_isEdit) {
      _loadForEdit();
    }
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final data = await ref
          .read(tasksRepositoryProvider)
          .fetchTask(widget.taskId!);
      if (data != null && mounted) {
        final t = data.task;
        _editProjectId = data.projectId;
        _projectId = data.projectId;
        _title.text = t.title;
        _description.text = t.description;
        _estimated.text =
            t.estimatedMinutes > 0 ? t.estimatedMinutes.toString() : '';
        _status = t.status;
        _priority = t.priority;
        _dueDate = t.dueDate;
        for (final s in t.subtasks) {
          _subControllers.add(TextEditingController(text: s.title));
        }
        setState(() => _loading = false);
      } else if (mounted) {
        setState(() => _loading = false);
      }
    } catch (e) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _estimated.dispose();
    for (final c in _subControllers) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    final title = _title.text.trim();
    if (title.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Title is required'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    if (!_isEdit && (_projectId == null || _projectId!.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Select a project'),
          behavior: SnackBarBehavior.floating));
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(tasksRepositoryProvider);
    final estimated = int.tryParse(_estimated.text) ?? 0;
    final subs = <Subtask>[];
    for (final c in _subControllers) {
      final t = c.text.trim();
      if (t.isNotEmpty) subs.add(Subtask(id: '', title: t));
    }

    try {
      if (_isEdit) {
        await repo.updateTask(
          taskId: widget.taskId!,
          projectId: _editProjectId ?? '',
          title: title,
          description: _description.text,
          status: _status,
          priority: _priority,
          dueDate: _dueDate,
          estimatedMinutes: estimated,
        );
      } else {
        await repo.createTask(
          projectId: _projectId!,
          title: title,
          description: _description.text,
          status: _status,
          priority: _priority,
          dueDate: _dueDate,
          estimatedMinutes: estimated,
          subtasks: subs,
        );
      }
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
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _pickDueDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _dueDate ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime(2100),
    );
    if (picked != null) setState(() => _dueDate = picked);
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Edit Task')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    final projects = ref.watch(projectsProvider).valueOrNull ?? const [];

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(_isEdit ? 'Edit Task' : 'New Task'),
      ),
      body: SafeArea(
        top: false,
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page, 8, AppSpacing.page, 24),
                children: [
                  if (!_isEdit && projects.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: LabeledDropdown<String>(
                        label: 'Project',
                        value: _projectId,
                        items: [
                          for (final proj in projects)
                            DropdownMenuItem(
                              value: proj.id,
                              child: Text(proj.name,
                                  overflow: TextOverflow.ellipsis),
                            ),
                        ],
                        onChanged: (v) => setState(() => _projectId = v),
                      ),
                    )
                  else if (!_isEdit) ...[
                    Padding(
                      padding: const EdgeInsets.only(bottom: 16),
                      child: Text('Create a project first.',
                          style: TextStyle(color: p.textFaint, fontSize: 14)),
                    ),
                  ],
                  LabeledField(
                    label: 'Title',
                    controller: _title,
                    hint: 'Task title',
                    maxLines: 2,
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'Description',
                    controller: _description,
                    hint: 'Optional details',
                    maxLines: 4,
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Status',
                    selected: _status,
                    onSelected: (v) => setState(() => _status = v),
                    options: const {
                      'backlog': 'Backlog',
                      'todo': 'To Do',
                      'in_progress': 'In Progress',
                      'review': 'Review',
                      'done': 'Done',
                    },
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Priority',
                    selected: _priority,
                    onSelected: (v) => setState(() => _priority = v),
                    colorFor: (v) => priorityColor(v),
                    options: const {
                      'low': 'Low',
                      'medium': 'Medium',
                      'high': 'High',
                      'urgent': 'Urgent',
                    },
                  ),
                  const SizedBox(height: 20),
                  // Due date row
                  _DateRow(
                    label: 'Due date',
                    value: _dueDate,
                    onPick: _pickDueDate,
                    onClear: () => setState(() => _dueDate = null),
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'Estimated minutes',
                    controller: _estimated,
                    hint: '0',
                    keyboardType: TextInputType.number,
                  ),
                  if (!_isEdit) ...[
                    const SizedBox(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text('SUBTASKS',
                            style: AppType.label(
                                context, color: p.textMuted, size: 11)),
                        GestureDetector(
                          onTap: () => setState(
                              () => _subControllers.add(TextEditingController())),
                          child: Row(mainAxisSize: MainAxisSize.min, children: [
                            HeroIcon(HeroPaths.plus, size: 14, color: p.ink),
                            const SizedBox(width: 4),
                            Text('ADD',
                                style: AppType.label(
                                    context, color: p.ink, size: 11)),
                          ]),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    for (var i = 0; i < _subControllers.length; i++)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: TextField(
                                controller: _subControllers[i],
                                textCapitalization:
                                    TextCapitalization.sentences,
                                decoration: InputDecoration(
                                  isDense: true,
                                  hintText: 'Subtask ${i + 1}',
                                  border: UnderlineInputBorder(
                                      borderSide: BorderSide(color: p.border)),
                                  enabledBorder: UnderlineInputBorder(
                                      borderSide: BorderSide(color: p.border)),
                                  focusedBorder: UnderlineInputBorder(
                                      borderSide:
                                          BorderSide(color: p.ink, width: 1.5)),
                                ),
                              ),
                            ),
                            IconButton(
                              icon: HeroIcon(HeroPaths.xMark,
                                  size: 16, color: p.textFaint),
                              onPressed: () => setState(() {
                                _subControllers[i].dispose();
                                _subControllers.removeAt(i);
                              }),
                            ),
                          ],
                        ),
                      ),
                  ],
                ],
              ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: _isEdit ? 'Update' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}

class _DateRow extends StatelessWidget {
  const _DateRow({
    required this.label,
    required this.value,
    required this.onPick,
    required this.onClear,
  });
  final String label;
  final DateTime? value;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 8),
        GestureDetector(
          onTap: onPick,
          behavior: HitTestBehavior.opaque,
          child: Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              border: Border.all(color: p.border),
              borderRadius: BorderRadius.circular(AppRadii.button),
            ),
            child: Row(
              children: [
                HeroIcon(HeroPaths.calendar, size: 18, color: p.textMuted),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    value == null
                        ? 'No date'
                        : '${value!.day}/${value!.month}/${value!.year}',
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: value == null ? p.textFaint : p.textPrimary),
                  ),
                ),
                if (value != null)
                  GestureDetector(
                    onTap: onClear,
                    child: HeroIcon(HeroPaths.xMark,
                        size: 16, color: p.textFaint),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
