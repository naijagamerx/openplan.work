import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/dashboard_repository.dart';
import '../repositories/habits_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';

/// Create or edit a habit. Mirrors mobile/views/habit-form.php.
class HabitFormScreen extends ConsumerStatefulWidget {
  const HabitFormScreen({super.key, this.habitId});

  final String? habitId;
  bool get isEdit => habitId != null;

  @override
  ConsumerState<HabitFormScreen> createState() => _HabitFormScreenState();
}

class _HabitFormScreenState extends ConsumerState<HabitFormScreen> {
  final _name = TextEditingController();
  String _category = 'general';
  String _frequency = 'daily';
  TimeOfDay? _reminder;
  final _target = TextEditingController();
  bool _loading = false;
  bool _saving = false;
  bool _suggesting = false;

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) _loadForEdit();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final h = await ref.read(habitsRepositoryProvider).fetch(widget.habitId!);
      if (h != null && mounted) {
        _name.text = h.name;
        _category = h.category;
        _frequency = h.frequency;
        _target.text =
            h.targetDuration > 0 ? h.targetDuration.toString() : '';
        if (h.reminderTime != null && h.reminderTime!.isNotEmpty) {
          final parts = h.reminderTime!.split(':');
          if (parts.length >= 2) {
            _reminder = TimeOfDay(
                hour: int.tryParse(parts[0]) ?? 0,
                minute: int.tryParse(parts[1]) ?? 0);
          }
        }
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _target.dispose();
    super.dispose();
  }

  Future<void> _pickReminder() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _reminder ?? TimeOfDay.now(),
    );
    if (picked != null) setState(() => _reminder = picked);
  }

  Future<void> _aiSuggest() async {
    setState(() => _suggesting = true);
    try {
      final data = await ref
          .read(habitsRepositoryProvider)
          .aiSuggest(category: _category);
      // The backend returns suggestions; offer the first as a name.
      final list = (data is List)
          ? data
          : (data is Map ? (data['habits'] ?? data['suggestions'] ?? []) : []);
      if (list is List && list.isNotEmpty) {
        final first = list.first;
        final name = first is Map
            ? (first['name'] ?? first['title'] ?? '').toString()
            : first.toString();
        if (name.isNotEmpty && mounted) {
          setState(() => _name.text = name);
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
              content: Text('Suggested: $name'),
              behavior: SnackBarBehavior.floating));
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('No suggestions returned'),
            behavior: SnackBarBehavior.floating));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _suggesting = false);
    }
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Name is required'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    final reminder = _reminder != null
        ? '${_reminder!.hour.toString().padLeft(2, '0')}:${_reminder!.minute.toString().padLeft(2, '0')}'
        : null;
    final target = int.tryParse(_target.text) ?? 0;

    setState(() => _saving = true);
    final repo = ref.read(habitsRepositoryProvider);
    try {
      if (widget.isEdit) {
        await repo.update(
          id: widget.habitId!,
          name: name,
          category: _category,
          frequency: _frequency,
          reminderTime: reminder,
          targetDuration: target,
        );
      } else {
        await repo.create(
          name: name,
          category: _category,
          frequency: _frequency,
          reminderTime: reminder,
          targetDuration: target,
        );
      }
      ref.invalidate(habitsListProvider);
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

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Edit Habit')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }
    final p = AppPalette.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Habit' : 'New Habit'),
        actions: [
          TextButton.icon(
            onPressed: _suggesting ? null : _aiSuggest,
            icon: HeroIcon(HeroPaths.sparkles,
                size: 16, color: _suggesting ? p.textFaint : p.ink),
            label: Text(
              'AI',
              style: TextStyle(
                  color: _suggesting ? p.textFaint : p.ink,
                  fontWeight: FontWeight.w700),
            ),
          ),
        ],
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
                  LabeledField(
                    label: 'Habit name',
                    controller: _name,
                    hint: 'e.g. Read for 30 minutes',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Category',
                    selected: _category,
                    onSelected: (v) => setState(() => _category = v),
                    options: const {
                      'general': 'General',
                      'health': 'Health',
                      'productivity': 'Productivity',
                      'mindfulness': 'Mindfulness',
                      'learning': 'Learning',
                      'fitness': 'Fitness',
                    },
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Frequency',
                    selected: _frequency,
                    onSelected: (v) => setState(() => _frequency = v),
                    options: const {
                      'daily': 'Daily',
                      'weekdays': 'Weekdays',
                      'weekly': 'Weekly',
                    },
                  ),
                  const SizedBox(height: 20),
                  // Reminder time row
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('REMINDER TIME',
                          style: AppType.label(
                              context, color: p.textMuted, size: 11)),
                      const SizedBox(height: 8),
                      GestureDetector(
                        onTap: _pickReminder,
                        behavior: HitTestBehavior.opaque,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(
                            border: Border.all(color: p.border),
                            borderRadius:
                                BorderRadius.circular(AppRadii.button),
                          ),
                          child: Row(
                            children: [
                              HeroIcon(HeroPaths.clock,
                                  size: 18, color: p.textMuted),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  _reminder == null
                                      ? 'No reminder'
                                      : _reminder!.format(context),
                                  style: TextStyle(
                                      fontSize: 15,
                                      fontWeight: FontWeight.w500,
                                      color: _reminder == null
                                          ? p.textFaint
                                          : p.textPrimary),
                                ),
                              ),
                              if (_reminder != null)
                                GestureDetector(
                                  onTap: () =>
                                      setState(() => _reminder = null),
                                  child: HeroIcon(HeroPaths.xMark,
                                      size: 16, color: p.textFaint),
                                ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'Target duration (minutes)',
                    controller: _target,
                    hint: '0',
                    keyboardType: TextInputType.number,
                  ),
                ],
              ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: widget.isEdit ? 'Update' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}
