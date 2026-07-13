import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../repositories/knowledge_base_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Knowledge-base file editor/viewer. Mirrors the PHP viewer section: name +
/// multiline monospace content, rendered-ish (the raw markdown in a readable
/// form), editable, with a sticky Save + Delete bar.
class KbFileScreen extends ConsumerStatefulWidget {
  const KbFileScreen({
    super.key,
    required this.folderId,
    this.fileId,
    this.initialName,
  });

  final String folderId;
  final String? fileId; // null → create mode

  /// Optional pre-fetched name so the new-file flow can seed the field
  /// (unused by the caller today but keeps the contract explicit).
  final String? initialName;

  bool get isEdit => fileId != null;

  @override
  ConsumerState<KbFileScreen> createState() => _KbFileScreenState();
}

class _KbFileScreenState extends ConsumerState<KbFileScreen> {
  final _name = TextEditingController();
  final _content = TextEditingController();
  bool _loading = false;
  bool _saving = false;
  bool _loaded = false; // distinguish "load failed" from "still loading"

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) {
      _load();
    } else {
      _loaded = true;
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _content.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final file =
          await ref.read(kbRepositoryProvider).fetchFile(widget.fileId!);
      if (mounted) {
        _name.text = file.name;
        _content.text = file.content;
        _loaded = true;
      }
    } catch (_) {
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Name is required'),
        behavior: SnackBarBehavior.floating,
      ));
      return;
    }
    if (!RegExp(r'\.(md|xml)$', caseSensitive: false).hasMatch(name)) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Name must end with .md or .xml'),
        behavior: SnackBarBehavior.floating,
      ));
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(kbRepositoryProvider);
    try {
      if (widget.isEdit) {
        await repo.updateFile(
          id: widget.fileId!,
          name: name,
          content: _content.text,
        );
      } else {
        await repo.createFile(
          folderId: widget.folderId,
          name: name,
          content: _content.text,
        );
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(widget.isEdit ? 'Saved' : 'Created'),
          behavior: SnackBarBehavior.floating,
        ));
        context.pop(true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _delete() async {
    if (!widget.isEdit) {
      context.pop();
      return;
    }
    final ok = await confirmDialog(context,
        title: 'Delete file',
        message: 'Delete "${_name.text}"? This cannot be undone.');
    if (!ok) return;

    setState(() => _saving = true);
    try {
      await ref.read(kbRepositoryProvider).deleteFile(widget.fileId!);
      if (mounted) context.pop(true);
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
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit File' : 'New File'),
      ),
      body: SafeArea(
        top: false,
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: _loading
                  ? ListView(
                      padding: const EdgeInsets.fromLTRB(
                          AppSpacing.page, 8, AppSpacing.page, 24),
                      children: const [
                        Skeleton(height: 56),
                        SizedBox(height: 20),
                        Skeleton(height: 220, radius: AppRadii.card),
                      ],
                    )
                  : !_loaded
                      ? Center(
                          child: EmptyState(
                            iconPath: HeroPaths.document,
                            message: 'File not found',
                            actionLabel: 'Retry',
                            onAction: _load,
                          ),
                        )
                      : ListView(
                          padding: const EdgeInsets.fromLTRB(
                              AppSpacing.page, 8, AppSpacing.page, 32),
                          children: [
                            FadeIn(
                              child: LabeledField(
                                label: 'File name',
                                controller: _name,
                                hint: 'e.g. notes.md',
                                textInputAction: TextInputAction.next,
                              ),
                            ),
                            const SizedBox(height: 20),
                            FadeIn(
                              delay: const Duration(milliseconds: 60),
                              child: _ContentField(controller: _content),
                            ),
                            if (widget.isEdit) ...[
                              const SizedBox(height: 24),
                              Text(
                                'CONTENT IS EDITABLE MARKDOWN. CHANGES SAVE ON TAP.',
                                style: AppType.label(
                                    context, color: p.textFaint, size: 10),
                              ),
                            ],
                          ],
                        ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: widget.isEdit ? _delete : () => context.pop(),
              cancelLabel: widget.isEdit ? 'Delete' : 'Cancel',
              saving: _saving,
              saveLabel: widget.isEdit ? 'Save' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}

/// Multiline content field with a monospace font — the editor body.
/// Replicates the PHP app's raw-markdown editor while staying readable.
class _ContentField extends StatelessWidget {
  const _ContentField({required this.controller});
  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('CONTENT',
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: p.canvas,
            border: Border.all(color: p.border),
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: TextField(
            controller: controller,
            maxLines: 20,
            minLines: 8,
            style: TextStyle(
              fontFamily: 'GeistMono',
              fontSize: 13,
              height: 1.5,
              color: p.textPrimary,
            ),
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(
              isDense: true,
              border: InputBorder.none,
              hintText: '# Heading\n\nWrite your markdown here…',
              hintStyle: TextStyle(
                fontFamily: 'GeistMono',
                fontSize: 13,
                color: p.textFaint,
              ),
              contentPadding: EdgeInsets.zero,
              counterText: '',
            ),
          ),
        ),
        if (controller.text.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              '${_bytes(controller.text)} B · edited ${_nowLabel()}',
              style: AppType.label(context, color: p.textFaint, size: 9),
            ),
          ),
      ],
    );
  }

  int _bytes(String s) => s.codeUnits.length;

  String _nowLabel() => DateFormat.Hm().format(DateTime.now());
}
