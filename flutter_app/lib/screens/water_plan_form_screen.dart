import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/water_plan.dart';
import '../repositories/water_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Create or edit a manual water plan. Mirrors mobile/views/new-water-plan.php.
///
/// Fields: name, daily goal (L), drink size (ml), Quick Presets, dynamic
/// schedule rows {time, amount}. Includes an "AI Generate" action that opens a
/// body-metrics dialog (weight/activity/climate) and fills the form.
class WaterPlanFormScreen extends ConsumerStatefulWidget {
  const WaterPlanFormScreen({super.key, this.planId, this.aiMode = false});

  /// Non-null → edit an existing plan; null → create.
  final String? planId;

  /// When true, opens the AI-generate dialog immediately on mount.
  final bool aiMode;

  @override
  ConsumerState<WaterPlanFormScreen> createState() => _WaterPlanFormScreenState();
}

class _WaterPlanFormScreenState extends ConsumerState<WaterPlanFormScreen> {
  final _name = TextEditingController(text: 'My Water Plan');
  final _goalL = TextEditingController(text: '2.50'); // L
  final _glassMl = TextEditingController(text: '250'); // ml
  final _amounts = <TextEditingController>[];
  final _times = <String>[];

  bool _loading = false;
  bool _saving = false;
  bool _generating = false;

  @override
  void initState() {
    super.initState();
    if (widget.planId != null) {
      _loadForEdit();
    } else {
      // Seed with one empty row (matches PHP's renderScheduleRow('', '0.25')).
      _addRow(time: '', amountL: '0.25');
    }
    if (widget.aiMode) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _showAiDialog());
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _goalL.dispose();
    _glassMl.dispose();
    for (final c in _amounts) {
      c.dispose();
    }
    super.dispose();
  }

  bool get _isEdit => widget.planId != null;

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final plan = await ref.read(waterRepositoryProvider).getPlan(widget.planId!);
      if (plan != null && mounted) {
        _name.text = plan.name;
        _goalL.text = (plan.dailyGoalMl / 1000).toStringAsFixed(2);
        _glassMl.text = plan.glassSizeMl.toString();
        _clearRows();
        for (final r in plan.schedule) {
          _addRow(
              time: r.time,
              amountL: (r.resolvedAmountMl(plan.glassSizeMl) / 1000)
                  .toStringAsFixed(2));
        }
        if (_amounts.isEmpty) _addRow(time: '', amountL: '0.25');
      }
    } catch (_) {
      // best-effort; user can still create from scratch
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _addRow({String time = '', String amountL = '0.25'}) {
    setState(() {
      _times.add(time);
      _amounts.add(TextEditingController(text: amountL));
    });
  }

  void _removeRow(int i) {
    setState(() {
      _amounts[i].dispose();
      _amounts.removeAt(i);
      _times.removeAt(i);
    });
  }

  void _clearRows() {
    for (final c in _amounts) {
      c.dispose();
    }
    _amounts.clear();
    _times.clear();
  }

  /// Quick Presets ported from new-water-plan.php PRESETS (amounts in L).
  void _applyPreset(String name) {
    const presets = <String, List<({String time, String amountL})>>{
      'fullDay': [
        (time: '07:00', amountL: '0.25'),
        (time: '09:00', amountL: '0.25'),
        (time: '11:00', amountL: '0.25'),
        (time: '13:00', amountL: '0.25'),
        (time: '15:00', amountL: '0.25'),
        (time: '17:00', amountL: '0.25'),
        (time: '19:00', amountL: '0.25'),
        (time: '21:00', amountL: '0.25'),
      ],
      'halfDay': [
        (time: '08:00', amountL: '0.25'),
        (time: '10:00', amountL: '0.25'),
        (time: '12:00', amountL: '0.25'),
        (time: '14:00', amountL: '0.25'),
      ],
      'workDay': [
        (time: '09:00', amountL: '0.25'),
        (time: '11:00', amountL: '0.25'),
        (time: '13:00', amountL: '0.25'),
        (time: '15:00', amountL: '0.25'),
        (time: '17:00', amountL: '0.25'),
        (time: '19:00', amountL: '0.25'),
      ],
    };
    final preset = presets[name];
    if (preset == null) return;
    setState(() {
      _clearRows();
      for (final p in preset) {
        _times.add(p.time);
        _amounts.add(TextEditingController(text: p.amountL));
      }
    });
  }

  int _litersToMl(String v, [int def = 0]) {
    final parsed = double.tryParse(v);
    if (parsed == null || parsed <= 0) return def;
    return (parsed * 1000).round();
  }

  Future<void> _pickTime(int i) async {
    final initial = _parseTimeOfDay(_times[i]) ?? TimeOfDay.now();
    final picked =
        await showTimePicker(context: context, initialTime: initial);
    if (picked != null) {
      setState(() {
        _times[i] =
            '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
      });
    }
  }

  static TimeOfDay? _parseTimeOfDay(String hhmm) {
    if (hhmm.isEmpty) return null;
    final parts = hhmm.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    return TimeOfDay(hour: h, minute: m);
  }

  Future<void> _showAiDialog() async {
    final result = await showDialog<_AiInputs>(
      context: context,
      builder: (_) => const _AiGenerateDialog(),
    );
    if (result == null) return;
    setState(() => _generating = true);
    try {
      final plan = await ref.read(waterRepositoryProvider).generatePlan(
            weight: result.weight,
            activity: result.activity,
            climate: result.climate,
            glassSizeMl: _litersToMl(_glassMl.text, 250),
            wakeTime: result.wakeTime,
            sleepTime: result.sleepTime,
          );
      if (mounted) _fillFromPlan(plan);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _generating = false);
    }
  }

  void _fillFromPlan(WaterPlan plan) {
    setState(() {
      _name.text = plan.name;
      _goalL.text = (plan.dailyGoalMl / 1000).toStringAsFixed(2);
      _glassMl.text = plan.glassSizeMl.toString();
      _clearRows();
      for (final r in plan.schedule) {
        _times.add(r.time);
        _amounts.add(TextEditingController(
            text: (r.resolvedAmountMl(plan.glassSizeMl) / 1000)
                .toStringAsFixed(2)));
      }
      if (_amounts.isEmpty) _addRow(time: '', amountL: '0.25');
    });
  }

  Future<void> _save() async {
    final name = _name.text.trim().isEmpty ? 'My Water Plan' : _name.text.trim();
    final dailyGoalMl = _litersToMl(_goalL.text, 2500);
    final glassSizeMl = _litersToMl(_glassMl.text, 250);

    final schedule = <WaterReminder>[];
    for (var i = 0; i < _times.length; i++) {
      final time = _times[i].trim();
      if (time.isEmpty) continue;
      final amountMl = _litersToMl(_amounts[i].text, glassSizeMl);
      schedule.add(WaterReminder(
        id: '',
        time: time,
        amountMl: amountMl,
      ));
    }

    if (schedule.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Please add at least one reminder.'),
          behavior: SnackBarBehavior.floating));
      return;
    }

    schedule.sort((a, b) => a.time.compareTo(b.time));

    setState(() => _saving = true);
    final repo = ref.read(waterRepositoryProvider);
    try {
      final plan = WaterPlan(
        id: widget.planId ?? '',
        name: name,
        dailyGoalMl: dailyGoalMl,
        glassSizeMl: glassSizeMl,
        isActive: true,
        schedule: schedule,
      );
      if (_isEdit) {
        await repo.updateManualPlan(plan);
      } else {
        await repo.createManualPlan(plan);
      }
      ref.invalidate(waterPlanProvider);
      ref.invalidate(waterProvider);
      ref.invalidate(waterHistoryProvider);
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
    final p = AppPalette.of(context);
    if (_loading) {
      return Scaffold(
        appBar: AppBar(
          leading: IconButton(
            icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
            onPressed: () => context.pop(),
          ),
          title: const Text('Edit Plan'),
        ),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 200, radius: AppRadii.section),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(_isEdit ? 'Edit Plan' : 'New Water Plan'),
        actions: [
          TextButton.icon(
            onPressed: _generating ? null : _showAiDialog,
            icon: HeroIcon(HeroPaths.sparkles,
                size: 16, color: _generating ? p.textFaint : p.ink),
            label: Text('AI',
                style: TextStyle(
                    color: _generating ? p.textFaint : p.ink,
                    fontWeight: FontWeight.w700)),
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
                    label: 'Plan name',
                    controller: _name,
                    hint: 'My Water Plan',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: LabeledField(
                          label: 'Daily goal (L)',
                          controller: _goalL,
                          hint: '2.50',
                          keyboardType:
                              const TextInputType.numberWithOptions(decimal: true),
                          textInputAction: TextInputAction.next,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: LabeledField(
                          label: 'Drink size (ml)',
                          controller: _glassMl,
                          hint: '250',
                          keyboardType: TextInputType.number,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  // Quick Presets
                  SectionCard(
                    heading: 'Quick Presets',
                    child: Row(
                      children: [
                        Expanded(
                          child: _PresetButton(
                              label: 'Full Day',
                              onTap: () => _applyPreset('fullDay')),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _PresetButton(
                              label: 'Half Day',
                              onTap: () => _applyPreset('halfDay')),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _PresetButton(
                              label: 'Work Day',
                              onTap: () => _applyPreset('workDay')),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: AppSpacing.gap),
                  // Schedule
                  SectionCard(
                    heading: 'Schedule',
                    child: _amounts.isEmpty
                        ? const EmptyState(
                            iconPath: HeroPaths.clock,
                            message: 'No reminders yet.')
                        : Column(
                            children: [
                              for (var i = 0; i < _amounts.length; i++)
                                _ScheduleRow(
                                  index: i,
                                  time: _times[i],
                                  amountController: _amounts[i],
                                  onPickTime: () => _pickTime(i),
                                  onRemove: () => _removeRow(i),
                                ),
                            ],
                          ),
                  ),
                  const SizedBox(height: 12),
                  Center(
                    child: TextButton.icon(
                      onPressed: () => _addRow(time: '', amountL: '0.25'),
                      icon: HeroIcon(HeroPaths.plus, size: 16, color: p.ink),
                      label: Text('ADD REMINDER',
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1)),
                    ),
                  ),
                ],
              ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: _isEdit ? 'Update' : 'Save',
            ),
          ],
        ),
      ),
    );
  }
}

/// A single schedule row: time picker + amount (L) + remove.
class _ScheduleRow extends StatelessWidget {
  const _ScheduleRow({
    required this.index,
    required this.time,
    required this.amountController,
    required this.onPickTime,
    required this.onRemove,
  });

  final int index;
  final String time;
  final TextEditingController amountController;
  final VoidCallback onPickTime;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          // Time picker button
          Expanded(
            flex: 2,
            child: GestureDetector(
              onTap: onPickTime,
              behavior: HitTestBehavior.opaque,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                decoration: BoxDecoration(
                  border: Border.all(color: p.border),
                  borderRadius: BorderRadius.circular(AppRadii.button),
                ),
                child: Row(
                  children: [
                    HeroIcon(HeroPaths.clock, size: 16, color: p.textMuted),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        time.isEmpty ? '--:--' : time,
                        style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color:
                                time.isEmpty ? p.textFaint : p.textPrimary),
                      ),
                    ),
                    HeroIcon(HeroPaths.chevronDown, size: 14, color: p.textFaint),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),
          // Amount (L)
          Expanded(
            flex: 2,
            child: TextField(
              controller: amountController,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              style: TextStyle(
                  fontSize: 14, fontWeight: FontWeight.w500, color: p.textPrimary),
              decoration: InputDecoration(
                isDense: true,
                hintText: '0.25',
                hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border)),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border)),
                focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.ink, width: 1.5)),
                suffixText: 'L',
                suffixStyle: TextStyle(color: p.textFaint, fontSize: 12),
              ),
            ),
          ),
          const SizedBox(width: 8),
          // Remove
          GestureDetector(
            onTap: onRemove,
            behavior: HitTestBehavior.opaque,
            child: const Padding(
              padding: EdgeInsets.all(6),
              child: HeroIcon(HeroPaths.trash,
                  size: 18, color: Color(0xFFDC2626)),
            ),
          ),
        ],
      ),
    );
  }
}

/// Bordered preset button (Full Day / Half Day / Work Day).
class _PresetButton extends StatelessWidget {
  const _PresetButton({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          border: Border.all(color: p.ink),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        alignment: Alignment.center,
        child: Text(label.toUpperCase(),
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 1,
                color: p.textPrimary)),
      ),
    );
  }
}

/// Inputs collected by the AI-generate dialog.
class _AiInputs {
  const _AiInputs({
    required this.weight,
    required this.activity,
    required this.climate,
    required this.wakeTime,
    required this.sleepTime,
  });
  final double weight;
  final String activity;
  final String climate;
  final String wakeTime;
  final String sleepTime;
}

/// Body-metrics dialog for AI plan generation (weight/activity/climate/wake/sleep).
class _AiGenerateDialog extends StatefulWidget {
  const _AiGenerateDialog();

  @override
  State<_AiGenerateDialog> createState() => _AiGenerateDialogState();
}

class _AiGenerateDialogState extends State<_AiGenerateDialog> {
  final _weight = TextEditingController(text: '70');
  String _activity = 'moderate';
  String _climate = 'temperate';
  TimeOfDay _wake = const TimeOfDay(hour: 7, minute: 0);
  TimeOfDay _sleep = const TimeOfDay(hour: 23, minute: 0);

  String _fmt(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  @override
  void dispose() {
    _weight.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: p.border),
      ),
      titlePadding: const EdgeInsets.fromLTRB(20, 20, 20, 0),
      contentPadding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      actionsPadding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      title: Row(
        children: [
          HeroIcon(HeroPaths.sparkles, size: 20, color: p.ink),
          const SizedBox(width: 8),
          Text('Generate AI Plan',
              style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: p.textPrimary)),
        ],
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('WEIGHT (KG)',
                style: AppType.label(context, color: p.textMuted, size: 11)),
            const SizedBox(height: 4),
            TextField(
              controller: _weight,
              keyboardType: TextInputType.number,
              style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w500,
                  color: p.textPrimary),
              decoration: InputDecoration(
                isDense: true,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border)),
              ),
            ),
            const SizedBox(height: 16),
            ChipSelector<String>(
              label: 'Activity',
              selected: _activity,
              onSelected: (v) => setState(() => _activity = v),
              options: const {
                'sedentary': 'Sedentary',
                'moderate': 'Moderate',
                'active': 'Active',
                'athlete': 'Athlete',
              },
            ),
            const SizedBox(height: 16),
            ChipSelector<String>(
              label: 'Climate',
              selected: _climate,
              onSelected: (v) => setState(() => _climate = v),
              options: const {
                'cold': 'Cold',
                'temperate': 'Temperate',
                'hot': 'Hot',
                'humid': 'Humid',
              },
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: _DialogTimeTile(
                    label: 'Wake',
                    value: _fmt(_wake),
                    onTap: () async {
                      final picked = await showTimePicker(
                          context: context, initialTime: _wake);
                      if (picked != null) setState(() => _wake = picked);
                    },
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _DialogTimeTile(
                    label: 'Sleep',
                    value: _fmt(_sleep),
                    onTap: () async {
                      final picked = await showTimePicker(
                          context: context, initialTime: _sleep);
                      if (picked != null) setState(() => _sleep = picked);
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text('CANCEL',
              style: TextStyle(
                  color: p.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
        TextButton(
          onPressed: () {
            final w = double.tryParse(_weight.text);
            if (w == null || w <= 0) {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                  content: Text('Enter a valid weight'),
                  behavior: SnackBarBehavior.floating));
              return;
            }
            Navigator.pop(
              context,
              _AiInputs(
                weight: w,
                activity: _activity,
                climate: _climate,
                wakeTime: _fmt(_wake),
                sleepTime: _fmt(_sleep),
              ),
            );
          },
          child: Text('GENERATE',
              style: TextStyle(
                  color: p.ink,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
      ],
    );
  }
}

/// Compact labeled time tile used inside the AI dialog.
class _DialogTimeTile extends StatelessWidget {
  const _DialogTimeTile({
    required this.label,
    required this.value,
    required this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textMuted, size: 11)),
          const SizedBox(height: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            decoration: BoxDecoration(
              border: Border.all(color: p.border),
              borderRadius: BorderRadius.circular(AppRadii.button),
            ),
            child: Row(
              children: [
                HeroIcon(HeroPaths.clock, size: 14, color: p.textMuted),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(value,
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: p.textPrimary)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
