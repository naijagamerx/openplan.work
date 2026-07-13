import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/project.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/projects_repository.dart';
import '../repositories/tasks_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Create or edit a project — mirrors mobile/views/project-form.php.
/// Pass [projectId] to edit; omit to create.
class ProjectFormScreen extends ConsumerStatefulWidget {
  const ProjectFormScreen({super.key, this.projectId});

  final String? projectId;

  @override
  ConsumerState<ProjectFormScreen> createState() => _ProjectFormScreenState();
}

class _ProjectFormScreenState extends ConsumerState<ProjectFormScreen> {
  bool get _isEdit => widget.projectId != null;

  final _name = TextEditingController();
  final _client = TextEditingController();
  final _color = TextEditingController();
  final _description = TextEditingController();

  String _status = 'planning';
  bool _loading = false;
  bool _saving = false;

  static const _statusOptions = <String, String>{
    'planning': 'Planning',
    'active': 'Active',
    'in_progress': 'In Progress',
    'on_hold': 'On Hold',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
  };

  @override
  void initState() {
    super.initState();
    if (_isEdit) _loadForEdit();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final p = await ref.read(projectsCrudProvider).fetchProject(widget.projectId!);
      if (p != null && mounted) {
        _name.text = p.name;
        _client.text = (p.clientId ?? '').isEmpty
            ? (p.clientName ?? '')
            : p.clientId!;
        _color.text = p.color ?? '';
        _description.text = p.description;
        _status = Project.normalizeStatus(p.status);
        setState(() => _loading = false);
      } else if (mounted) {
        setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _client.dispose();
    _color.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Project name is required'),
          behavior: SnackBarBehavior.floating));
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(projectsCrudProvider);
    final color = _color.text.trim();
    final description = _description.text.trim();

    try {
      if (_isEdit) {
        await repo.update(
          widget.projectId!,
          name: name,
          description: description,
          status: _status,
          color: color,
        );
      } else {
        await repo.create(
          name: name,
          description: description,
          status: _status,
          color: color,
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

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Edit Project')),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 56, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 90, radius: AppRadii.button),
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
        title: Text(_isEdit ? 'Edit Project' : 'New Project'),
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
                    label: 'Project Name',
                    controller: _name,
                    hint: 'E.g. Q1 Brand Refresh',
                    textInputAction: TextInputAction.next,
                    maxLines: 2,
                  ),
                  const SizedBox(height: 16),
                  LabeledField(
                    label: 'Client (optional)',
                    controller: _client,
                    hint: 'Client name or id',
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 20),
                  ChipSelector<String>(
                    label: 'Status',
                    selected: _status,
                    onSelected: (v) => setState(() => _status = v),
                    options: _statusOptions,
                  ),
                  const SizedBox(height: 20),
                  _ColorField(
                    controller: _color,
                    onChanged: () => setState(() {}),
                  ),
                  const SizedBox(height: 20),
                  LabeledField(
                    label: 'Description',
                    controller: _description,
                    hint: 'Brief project overview…',
                    maxLines: 5,
                  ),
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

/// Hex color input with a live preview swatch + the app's default presets.
class _ColorField extends StatelessWidget {
  const _ColorField({required this.controller, required this.onChanged});

  final TextEditingController controller;
  final VoidCallback onChanged;

  static const _presets = [
    '#0A0A0A',
    '#6B7280',
    '#DC2626',
    '#EA580C',
    '#EAB308',
    '#16A34A',
    '#2563EB',
    '#7C3AED',
  ];

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final swatch = _parseColor(controller.text.trim()) ?? p.ink;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('COLOR (OPTIONAL)',
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 8),
        Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: swatch,
                border: Border.all(color: p.border),
                borderRadius: BorderRadius.circular(AppRadii.button),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: TextField(
                controller: controller,
                onChanged: (_) => onChanged(),
                textCapitalization: TextCapitalization.characters,
                decoration: InputDecoration(
                  isDense: true,
                  hintText: '#0A0A0A',
                  hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
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
                style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: p.textPrimary,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final hex in _presets)
              GestureDetector(
                onTap: () {
                  controller.text = hex;
                  onChanged();
                },
                behavior: HitTestBehavior.opaque,
                child: Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: _parseColor(hex) ?? p.ink,
                    border: Border.all(
                        color: controller.text.trim().toLowerCase() ==
                                hex.toLowerCase()
                            ? p.ink
                            : p.border,
                        width: controller.text.trim().toLowerCase() ==
                                hex.toLowerCase()
                            ? 2
                            : 1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
              ),
          ],
        ),
      ],
    );
  }
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
