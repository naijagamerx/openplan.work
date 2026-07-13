import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../repositories/meetings_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Create or edit a calendar meeting. Mirrors mobile/views/meeting-form.php.
/// `meetingId` non-null ⇒ edit mode (loads the existing meeting).
/// `initialDate` (or the `date` query param) pre-fills the date in create mode.
class MeetingFormScreen extends ConsumerStatefulWidget {
  const MeetingFormScreen({super.key, this.meetingId, this.initialDate});

  final String? meetingId;
  final DateTime? initialDate;

  bool get isEdit => meetingId != null;

  @override
  ConsumerState<MeetingFormScreen> createState() => _MeetingFormScreenState();
}

class _MeetingFormScreenState extends ConsumerState<MeetingFormScreen> {
  static const _swatches = <String>[
    '#A855F7', // purple (default)
    '#0A0A0A', // ink
    '#DC2626', // red
    '#EA580C', // orange
    '#EAB308', // yellow
    '#16A34A', // green
    '#3B82F6', // blue
    '#EC4899', // pink
  ];

  final _title = TextEditingController();
  final _location = TextEditingController();
  final _attendees = TextEditingController();
  final _notes = TextEditingController();

  DateTime _date = DateTime.now();
  TimeOfDay? _startTime;
  TimeOfDay? _endTime;
  String _color = '#A855F7';

  bool _loading = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) {
      _loadForEdit();
    } else {
      final query = GoRouterState.of(context).uri.queryParameters['date'];
      final initial = widget.initialDate ??
          (query != null && query.length >= 10
              ? DateTime.tryParse('${query}T00:00:00')
              : null);
      _date = initial ?? DateTime.now();
    }
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final m = await ref.read(meetingsRepositoryProvider).fetch(widget.meetingId!);
      if (m != null && mounted) {
        _title.text = m.title;
        _location.text = m.location ?? '';
        _attendees.text = m.attendees.join(', ');
        _notes.text = m.notes;
        _date = m.date;
        _startTime = _parseTime(m.startTime);
        _endTime = _parseTime(m.endTime);
        if (m.color != null && m.color!.isNotEmpty) _color = m.color!;
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  TimeOfDay? _parseTime(String? s) {
    if (s == null || s.trim().isEmpty) return null;
    final parts = s.trim().split(':');
    if (parts.length < 2) return null;
    return TimeOfDay(
        hour: int.tryParse(parts[0]) ?? 0,
        minute: int.tryParse(parts[1]) ?? 0);
  }

  String _fmtTime(TimeOfDay t) =>
      '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}';

  @override
  void dispose() {
    _title.dispose();
    _location.dispose();
    _attendees.dispose();
    _notes.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _pickStart() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _startTime ?? _endTime ?? TimeOfDay.now(),
    );
    if (picked != null) setState(() => _startTime = picked);
  }

  Future<void> _pickEnd() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _endTime ?? _startTime ?? TimeOfDay.now(),
    );
    if (picked != null) setState(() => _endTime = picked);
  }

  Future<void> _save() async {
    final title = _title.text.trim();
    if (title.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Title and date are required'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    setState(() => _saving = true);
    final repo = ref.read(meetingsRepositoryProvider);
    try {
      if (widget.isEdit) {
        await repo.update(
          id: widget.meetingId!,
          title: title,
          date: _date,
          startTime: _startTime != null ? _fmtTime(_startTime!) : null,
          endTime: _endTime != null ? _fmtTime(_endTime!) : null,
          location: _location.text.trim(),
          color: _color,
          attendees: _splitAttendees(_attendees.text),
          notes: _notes.text.trim(),
        );
      } else {
        await repo.create(
          title: title,
          date: _date,
          startTime: _startTime != null ? _fmtTime(_startTime!) : null,
          endTime: _endTime != null ? _fmtTime(_endTime!) : null,
          location: _location.text.trim(),
          color: _color,
          attendees: _splitAttendees(_attendees.text),
          notes: _notes.text.trim(),
        );
      }
      ref.invalidate(meetingsProvider);
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

  List<String> _splitAttendees(String raw) => raw
      .split(',')
      .map((s) => s.trim())
      .where((s) => s.isNotEmpty)
      .toList();

  Future<void> _delete() async {
    final ok = await confirmDialog(context,
        title: 'Delete meeting',
        message: 'Delete "${_title.text.trim().isEmpty ? 'this meeting' : _title.text.trim()}"? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(meetingsRepositoryProvider).delete(widget.meetingId!);
      ref.invalidate(meetingsProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(
          leading: IconButton(
            icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
            onPressed: () => context.pop(),
          ),
          title: const Text('Edit Meeting'),
        ),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 56, radius: AppRadii.button),
          ],
        ),
      );
    }
    final p = AppPalette.of(context);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Meeting' : 'New Meeting'),
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
                    label: 'Title',
                    controller: _title,
                    hint: 'Team standup',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  // Date row
                  _PickerRow(
                    label: 'Date',
                    icon: HeroPaths.calendar,
                    value: DateFormat('MMM d, y').format(_date),
                    onTap: _pickDate,
                  ),
                  const SizedBox(height: 20),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: _PickerRow(
                          label: 'Start',
                          icon: HeroPaths.clock,
                          value: _startTime?.format(context),
                          hint: '—',
                          onTap: _pickStart,
                          onClear: _startTime == null
                              ? null
                              : () => setState(() => _startTime = null),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _PickerRow(
                          label: 'End',
                          icon: HeroPaths.clock,
                          value: _endTime?.format(context),
                          hint: '—',
                          onTap: _pickEnd,
                          onClear: _endTime == null
                              ? null
                              : () => setState(() => _endTime = null),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Location',
                    controller: _location,
                    hint: 'Room A / Zoom / Address',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  // Color picker swatches
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('COLOR',
                          style: AppType.label(
                              context, color: p.textMuted, size: 11)),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          for (final hex in _swatches)
                            _ColorSwatch(
                              hex: hex,
                              selected: _color.toUpperCase() ==
                                  hex.toUpperCase(),
                              onTap: () => setState(() => _color = hex),
                            ),
                        ],
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Attendees',
                    controller: _attendees,
                    hint: 'comma-separated',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Notes',
                    controller: _notes,
                    hint: 'Agenda, notes…',
                    maxLines: 4,
                  ),
                  if (widget.isEdit) ...[
                    const SizedBox(height: 24),
                    Center(
                      child: GestureDetector(
                        onTap: _delete,
                        behavior: HitTestBehavior.opaque,
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            HeroIcon(HeroPaths.trash,
                                size: 15, color: Color(0xFFDC2626)),
                            SizedBox(width: 6),
                            Text('DELETE MEETING',
                                style: TextStyle(
                                    fontFamily: 'Geist',
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 1.5,
                                    color: Color(0xFFDC2626))),
                          ],
                        ),
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
              saveLabel: widget.isEdit ? 'Update' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}

/// A bordered picker row (date/time) with an uppercase label, leading icon,
/// optional clear button.
class _PickerRow extends StatelessWidget {
  const _PickerRow({
    required this.label,
    required this.icon,
    required this.value,
    required this.onTap,
    this.hint,
    this.onClear,
  });

  final String label;
  final String icon;
  final String? value;
  final String? hint;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final empty = value == null || value!.isEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 8),
        GestureDetector(
          onTap: onTap,
          behavior: HitTestBehavior.opaque,
          child: Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
            decoration: BoxDecoration(
              border: Border.all(color: p.border),
              borderRadius: BorderRadius.circular(AppRadii.button),
            ),
            child: Row(
              children: [
                HeroIcon(icon, size: 18, color: p.textMuted),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    empty ? (hint ?? 'Select') : value!,
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: empty ? p.textFaint : p.textPrimary),
                  ),
                ),
                if (onClear != null)
                  GestureDetector(
                    onTap: onClear,
                    child: HeroIcon(HeroPaths.xMark, size: 16, color: p.textFaint),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _ColorSwatch extends StatelessWidget {
  const _ColorSwatch({
    required this.hex,
    required this.selected,
    required this.onTap,
  });

  final String hex;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: _color,
          shape: BoxShape.circle,
          border: Border.all(
            color: selected ? p.ink : p.border,
            width: selected ? 3 : 1,
          ),
        ),
        alignment: Alignment.center,
        child: selected
            ? HeroIcon(HeroPaths.check, size: 16, color: _onColor)
            : null,
      ),
    );
  }

  Color get _color {
    var h = hex;
    if (h.startsWith('#')) h = h.substring(1);
    if (h.length == 3) {
      h = h.split('').map((c) => '$c$c').join();
    }
    final v = int.tryParse(h, radix: 16) ?? 0xA855F7;
    return Color(0xFF000000 | v);
  }

  Color get _onColor {
    // Approximate luminance to pick readable check color.
    final c = _color;
    final argb = c.toARGB32();
    final r = (argb >> 16) & 0xFF;
    final g = (argb >> 8) & 0xFF;
    final b = argb & 0xFF;
    final luminance = 0.299 * r + 0.587 * g + 0.114 * b;
    return luminance > 140 ? const Color(0xFF0A0A0A) : Colors.white;
  }
}
