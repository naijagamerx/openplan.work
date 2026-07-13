import 'dart:async';

import 'package:file_selector/file_selector.dart';
import 'package:flutter/material.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:markdown/markdown.dart' as md;
import 'package:share_plus/share_plus.dart';

import '../models/note.dart';
import '../repositories/notes_repository.dart';
import '../theme/app_tokens.dart';
import '../utils/note_blocks.dart';
import '../widgets/hero_icon.dart';
import '../widgets/notes_ai_sheet.dart';
import '../widgets/skeleton.dart';

/// Full-screen note editor with rich blocks (tables, checklists, images),
/// auto-save (debounced), share-to-WhatsApp, move-to-folder (tags), and
/// import-from-recent-note.
class NoteFormScreen extends ConsumerStatefulWidget {
  const NoteFormScreen({super.key, this.noteId});

  final String? noteId;
  bool get isEdit => noteId != null;

  @override
  ConsumerState<NoteFormScreen> createState() => _NoteFormScreenState();
}

class _NoteFormScreenState extends ConsumerState<NoteFormScreen> {
  final _title = TextEditingController();
  final _content = TextEditingController();
  final _tags = TextEditingController();
  String? _color;
  bool _isPinned = false;
  bool _isFavorite = false;
  bool _loading = false;
  bool _toolsOpen = false;
  bool _dirty = false;
  bool _preview = false; // read-only rendered view (markdown incl. tables)

  // Auto-save state.
  Timer? _debounce;
  bool _autoSaving = false;
  DateTime? _lastSavedAt;
  String? _createdId; // for new notes, the id assigned on first autosave

  @override
  void initState() {
    super.initState();
    _title.addListener(_onChange);
    _content.addListener(_onChange);
    _tags.addListener(_onChange);
    if (widget.isEdit) _loadForEdit();
  }

  void _onChange() {
    if (!_dirty) setState(() => _dirty = true);
    // Debounced auto-save.
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 1200), _autoSave);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _title.dispose();
    _content.dispose();
    _tags.dispose();
    super.dispose();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final n = await ref.read(notesRepositoryProvider).fetch(widget.noteId!);
      if (n != null && mounted) {
        _title.text = n.title;
        _content.text = n.content;
        _tags.text = n.tags.join(', ');
        // Normalize '' → null so the tint logic and picker treat "no colour"
        // consistently (the server stores '' when a colour is cleared).
        _color = (n.color != null && n.color!.isNotEmpty) ? n.color : null;
        _isPinned = n.isPinned;
        _isFavorite = n.isFavorite;
        _dirty = false;
        _lastSavedAt = n.updatedAt ?? n.createdAt;
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  List<String> _parseTags() => _tags.text
      .split(',')
      .map((t) => t.trim())
      .where((t) => t.isNotEmpty)
      .toList();

  // ── Notes AI chat ──────────────────────────────────────────────────────
  Future<void> _openNotesAi() async {
    // Flush any pending edit first so the AI reads the current note content
    // server-side (notes_chat loads the note by id).
    if (_dirty) {
      _debounce?.cancel();
      await _autoSave();
    }
    if (!mounted) return;
    await showNotesAiSheet(
      context,
      // After the flush above, a new note has a real _createdId, so the AI gets
      // this note's content as context even before it's been reopened.
      noteId: widget.noteId ?? _createdId,
      onApply: _applyAiText,
    );
  }

  void _applyAiText(String text, NotesAiApply mode) {
    final base = _content.text;
    // Wrap in setState so the rebuild refreshes BOTH the editable TextField and
    // the read-only Markdown preview (the preview reads _content.text at build
    // and would otherwise show stale content after a Replace/Insert/Append).
    setState(() {
      switch (mode) {
        case NotesAiApply.replace:
          _content.value = TextEditingValue(
            text: text,
            selection: TextSelection.collapsed(offset: text.length),
          );
        case NotesAiApply.append:
          final joined = base.isEmpty ? text : '$base\n\n$text';
          _content.value = TextEditingValue(
            text: joined,
            selection: TextSelection.collapsed(offset: joined.length),
          );
        case NotesAiApply.insert:
          final sel = _content.selection;
          final start = sel.isValid && sel.start >= 0 ? sel.start : base.length;
          final end = sel.isValid && sel.end >= 0 ? sel.end : base.length;
          final joined = base.replaceRange(start, end, text);
          _content.value = TextEditingValue(
            text: joined,
            selection: TextSelection.collapsed(offset: start + text.length),
          );
      }
      _preview = false; // drop to the editor so the applied text is visible
    });
    // Arm dirty + debounced autosave for the programmatic change.
    _onChange();
    // Setting _content.text fires _onChange → dirty + debounced autosave.
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Added to note'),
        behavior: SnackBarBehavior.floating,
        duration: Duration(seconds: 1),
      ),
    );
  }

  // ── Auto-save (debounced) ──────────────────────────────────────────────
  Future<void> _autoSave() async {
    final title = _title.text.trim();
    // Don't autosave an empty note.
    if (title.isEmpty && _content.text.trim().isEmpty) return;
    final id = widget.noteId ?? _createdId;
    final repo = ref.read(notesRepositoryProvider);

    setState(() => _autoSaving = true);
    try {
      if (id != null) {
        await repo.update(
          id: id,
          title: title.isEmpty ? 'Untitled' : title,
          content: _content.text,
          tags: _parseTags(),
          color: _color ?? '', // '' clears server-side (null would be dropped)
          isPinned: _isPinned,
          isFavorite: _isFavorite,
        );
      } else {
        final created = await repo.create(
          title: title.isEmpty ? 'Untitled' : title,
          content: _content.text,
          tags: _parseTags(),
          color: _color ?? '', // '' clears server-side (null would be dropped)
          isPinned: _isPinned,
          isFavorite: _isFavorite,
        );
        // Capture the REAL server id so every later autosave UPDATEs this note
        // instead of PUTting to a bogus id (which silently dropped edits — incl.
        // AI Insert/Replace/Append — on a freshly-created note).
        _createdId = created.id;
      }
      ref.invalidate(notesProvider);
      if (mounted) {
        setState(() {
          _dirty = false;
          _lastSavedAt = DateTime.now();
        });
      }
    } catch (_) {
      // Silently retry on next change; surfaced via "Unsaved" indicator.
    } finally {
      if (mounted) setState(() => _autoSaving = false);
    }
  }

  Future<bool> _confirmExit() async {
    // Flush any pending autosave before exiting.
    _debounce?.cancel();
    if (_dirty) await _autoSave();
    return true;
  }

  Future<void> _delete() async {
    final id = widget.noteId ?? _createdId;
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: Theme.of(ctx).colorScheme.surface,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Delete note'),
        content: const Text('This note will be permanently deleted.'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('CANCEL')),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('DELETE',
                style: TextStyle(color: Color(0xFFDC2626))),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(notesRepositoryProvider).delete(id);
      ref.invalidate(notesProvider);
      if (mounted) context.pop();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  // ── Rich content inserters ─────────────────────────────────────────────
  void _insertTable() {
    setState(() {
      _content.text = insertTable(_content.text, columns: 3, rows: 2);
      _content.selection =
          TextSelection.collapsed(offset: _content.text.length);
    });
  }

  void _insertChecklist() {
    setState(() {
      _content.text = insertChecklist(_content.text, items: 3);
      _content.selection =
          TextSelection.collapsed(offset: _content.text.length);
    });
  }

  Future<void> _insertImage() async {
    const imgGroup = XTypeGroup(
      label: 'Image',
      extensions: ['png', 'jpg', 'jpeg', 'gif', 'webp'],
      mimeTypes: ['image/*'],
    );
    final xFile = await openFile(acceptedTypeGroups: const [imgGroup]);
    if (xFile == null) return;
    final bytes = await xFile.readAsBytes();
    final mime = xFile.mimeType ?? 'image/png';
    setState(() {
      _content.text = insertImage(_content.text, bytes, mime);
      _content.selection =
          TextSelection.collapsed(offset: _content.text.length);
      _dirty = true;
    });
    _onChange();
  }

  Future<void> _importRecent() async {
    final all = await ref.read(notesProvider.future);
    if (!mounted) return;
    final others = widget.noteId != null
        ? all.where((n) => n.id != widget.noteId).toList()
        : all;
    if (others.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('No other notes to import'),
          behavior: SnackBarBehavior.floating));
      return;
    }
    final picked = await showModalBottomSheet<Note>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        final p = AppPalette.of(ctx);
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Text('INSERT FROM RECENT',
                        style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1,
                            color: p.textMuted)),
                  ],
                ),
              ),
              Divider(height: 1, color: p.border),
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: others.length,
                  itemBuilder: (_, i) {
                    final n = others[i];
                    return ListTile(
                      title: Text(n.title.isEmpty ? 'Untitled' : n.title,
                          maxLines: 1, overflow: TextOverflow.ellipsis),
                      subtitle: Text(n.preview,
                          maxLines: 1, overflow: TextOverflow.ellipsis),
                      onTap: () => Navigator.pop(ctx, n),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
    if (picked == null) return;
    setState(() {
      _content.text = appendNote(_content.text, picked.title, picked.content);
      _content.selection =
          TextSelection.collapsed(offset: _content.text.length);
      _dirty = true;
    });
    _onChange();
  }

  // ── Share to WhatsApp ──────────────────────────────────────────────────
  Future<void> _shareWhatsApp() async {
    final title = _title.text.trim();
    final body = _content.text.trim();
    if (title.isEmpty && body.isEmpty) return;
    final text = StringBuffer();
    if (title.isNotEmpty) text.writeln('*$title*');
    text.writeln();
    text.write(body);
    // WhatsApp accepts text via the system share sheet; SharePlus routes to
    // WhatsApp if installed, else the generic share target.
    await SharePlus.instance.share(ShareParams(text: text.toString(), subject: title));
  }

  // ── Move to folder (tags act as folders) ───────────────────────────────
  Future<void> _moveToFolder() async {
    final all = await ref.read(notesProvider.future);
    if (!mounted) return;
    // Folders = distinct tags across all notes, plus a "New folder" option.
    final folders = <String>{};
    for (final n in all) {
      folders.addAll(n.tags.where((t) => t.toLowerCase() != 'archived'));
    }
    final sorted = folders.toList()..sort();
    final result = await showDialog<_FolderChoice>(
      context: context,
      builder: (ctx) => _MoveFolderDialog(existing: sorted),
    );
    if (result == null) return;
    // A "move" replaces the tag set with the chosen folder tag.
    final newTags = <String>[];
    if (result.isNew) {
      if (result.name.trim().isNotEmpty) newTags.add(result.name.trim());
    } else {
      newTags.add(result.name);
    }
    // The chosen folder becomes the sole tag (a "move").
    setState(() {
      _tags.text = newTags.join(', ');
      _dirty = true;
    });
    _onChange();
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Moved to "${result.name}"'),
          behavior: SnackBarBehavior.floating));
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    // The note colour is a list-card indicator only — the editor keeps the plain
    // canvas background (no full-screen tint).

    if (_loading) {
      return Scaffold(
        backgroundColor: p.canvas,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          leading: IconButton(
            icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
            onPressed: () => context.pop(),
          ),
        ),
        body: ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 22, radius: AppRadii.button),
            SizedBox(height: 16),
            Skeleton(height: 120, radius: AppRadii.button),
          ],
        ),
      );
    }

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final nav = GoRouter.of(context);
        final shouldExit = await _confirmExit();
        if (!mounted) return;
        if (shouldExit) nav.pop();
      },
      child: Scaffold(
        backgroundColor: p.canvas,
        resizeToAvoidBottomInset: true,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          elevation: 0,
          scrolledUnderElevation: 0,
          leading: IconButton(
            icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
            onPressed: () async {
              final nav = GoRouter.of(context);
              final shouldExit = await _confirmExit();
              if (!mounted) return;
              if (shouldExit) nav.pop();
            },
          ),
          actions: [
            IconButton(
              tooltip: 'Notes AI',
              icon: const HeroIcon(HeroPaths.sparkles, size: 20),
              onPressed: _openNotesAi,
            ),
            IconButton(
              tooltip: _preview ? 'Edit' : 'Preview',
              icon: HeroIcon(
                  _preview ? HeroPaths.pencil : HeroPaths.document,
                  size: 20),
              onPressed: () => setState(() => _preview = !_preview),
            ),
            IconButton(
              icon: const HeroIcon(HeroPaths.dots, size: 20),
              onPressed: () => _showActionsSheet(),
            ),
            if (widget.isEdit || _createdId != null)
              IconButton(
                icon: const HeroIcon(HeroPaths.trash, size: 20),
                onPressed: _delete,
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
                      AppSpacing.page, 4, AppSpacing.page, 8),
                  children: [
                    TextField(
                      controller: _title,
                      textInputAction: TextInputAction.next,
                      textCapitalization: TextCapitalization.sentences,
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        height: 1.25,
                        color: p.textPrimary,
                      ),
                      decoration: InputDecoration(
                        isDense: true,
                        hintText: 'Title',
                        hintStyle: TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.w800,
                            color: p.textFaint.withValues(alpha: 0.5)),
                        border: InputBorder.none,
                        enabledBorder: InputBorder.none,
                        focusedBorder: InputBorder.none,
                        contentPadding: EdgeInsets.zero,
                      ),
                    ),
                    const SizedBox(height: 10),
                    if (_preview)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: _content.text.trim().isEmpty
                            ? Text('Nothing to preview',
                                style: TextStyle(color: p.textFaint))
                            : MarkdownBody(
                                data: _content.text,
                                selectable: true,
                                extensionSet: md.ExtensionSet.gitHubWeb,
                                styleSheet: MarkdownStyleSheet(
                                  p: TextStyle(
                                      fontSize: 17,
                                      height: 1.5,
                                      color: p.textPrimary),
                                  tableBorder:
                                      TableBorder.all(color: p.border),
                                  tableHead: TextStyle(
                                      fontWeight: FontWeight.w700,
                                      color: p.textPrimary),
                                  tableCellsPadding:
                                      const EdgeInsets.symmetric(
                                          horizontal: 8, vertical: 6),
                                ),
                              ),
                      )
                    else
                      TextField(
                        controller: _content,
                        minLines: 1,
                        // null → grows to full content height so the outer
                        // ListView owns all scrolling (a finite cap made the
                        // field scroll internally and fight the list on long notes).
                        maxLines: null,
                        keyboardType: TextInputType.multiline,
                        textCapitalization: TextCapitalization.sentences,
                        style: TextStyle(
                          fontSize: 17,
                          height: 1.5,
                          fontFamily: 'Geist', // Apple Notes-style clean sans (was mono)
                          color: p.textPrimary,
                        ),
                        decoration: InputDecoration(
                          isDense: true,
                          hintText: 'Note',
                          hintStyle: TextStyle(
                              fontSize: 17,
                              height: 1.5,
                              color: p.textFaint.withValues(alpha: 0.5)),
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          contentPadding: EdgeInsets.zero,
                        ),
                      ),
                    const SizedBox(height: 80),
                  ],
                ),
              ),
              _Toolbar(
                toolsOpen: _toolsOpen,
                onToggleTools: () =>
                    setState(() => _toolsOpen = !_toolsOpen),
                autoSaving: _autoSaving,
                dirty: _dirty,
                lastSavedAt: _lastSavedAt,
                color: _color,
                onColorChanged: (hex) {
                  setState(() {
                    if (hex.isEmpty) {
                      _color = null;
                    } else {
                      _color = _color == hex ? null : hex;
                    }
                  });
                  _onChange(); // mark dirty + arm the debounced autosave
                },
                tagsController: _tags,
                isPinned: _isPinned,
                onPinnedChanged: (v) {
                  setState(() => _isPinned = v);
                  _onChange();
                },
                isFavorite: _isFavorite,
                onFavoriteChanged: (v) {
                  setState(() => _isFavorite = v);
                  _onChange();
                },
                onInsertTable: _insertTable,
                onInsertChecklist: _insertChecklist,
                onInsertImage: _insertImage,
                onImportRecent: _importRecent,
                onMoveFolder: _moveToFolder,
                onShare: _shareWhatsApp,
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showActionsSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        final p = AppPalette.of(ctx);
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text('ACTIONS',
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 12,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1,
                        color: p.textMuted)),
              ),
              Divider(height: 1, color: p.border),
              ListTile(
                  leading: const HeroIcon(HeroPaths.chat, size: 20),
                  title: const Text('Share to WhatsApp'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _shareWhatsApp();
                  }),
              ListTile(
                  leading: const HeroIcon(HeroPaths.folder, size: 20),
                  title: const Text('Move to folder'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _moveToFolder();
                  }),
              ListTile(
                  leading: const HeroIcon(HeroPaths.document, size: 20),
                  title: const Text('Insert from recent note'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _importRecent();
                  }),
              ListTile(
                  leading: const HeroIcon(HeroPaths.archive, size: 20),
                  title: const Text('Move to trash'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _delete();
                  }),
            ],
          ),
        );
      },
    );
  }
}

class _FolderChoice {
  const _FolderChoice(this.name, {required this.isNew});
  final String name;
  final bool isNew;
}

class _MoveFolderDialog extends StatefulWidget {
  const _MoveFolderDialog({required this.existing});
  final List<String> existing;

  @override
  State<_MoveFolderDialog> createState() => _MoveFolderDialogState();
}

class _MoveFolderDialogState extends State<_MoveFolderDialog> {
  final _newCtrl = TextEditingController();

  @override
  void dispose() {
    _newCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      title: const Text('Move to folder'),
      content: SizedBox(
        width: double.maxFinite,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (widget.existing.isNotEmpty) ...[
                Text('EXISTING FOLDERS',
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1,
                        color: p.textFaint)),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final f in widget.existing)
                      GestureDetector(
                        onTap: () =>
                            Navigator.pop(context, _FolderChoice(f, isNew: false)),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: p.canvas,
                            border: Border.all(color: p.border),
                            borderRadius: BorderRadius.circular(AppRadii.pill),
                          ),
                          child: Text('#$f',
                              style: TextStyle(
                                  fontFamily: 'Geist',
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: p.textPrimary)),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 16),
                Divider(height: 1, color: p.border),
                const SizedBox(height: 16),
              ],
              Text('NEW FOLDER',
                  style: TextStyle(
                      fontFamily: 'Geist',
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1,
                      color: p.textFaint)),
              const SizedBox(height: 6),
              TextField(
                controller: _newCtrl,
                textCapitalization: TextCapitalization.none,
                decoration: InputDecoration(
                  isDense: true,
                  hintText: 'folder name',
                  border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(AppRadii.button),
                      borderSide: BorderSide(color: p.border)),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('CANCEL')),
        TextButton(
          onPressed: () {
            final name = _newCtrl.text.trim();
            if (name.isEmpty) return;
            Navigator.pop(context, _FolderChoice(name, isNew: true));
          },
          child: const Text('MOVE'),
        ),
      ],
    );
  }
}

// ── Toolbar ───────────────────────────────────────────────────────────────

class _Toolbar extends StatelessWidget {
  const _Toolbar({
    required this.toolsOpen,
    required this.onToggleTools,
    required this.autoSaving,
    required this.dirty,
    required this.lastSavedAt,
    required this.color,
    required this.onColorChanged,
    required this.tagsController,
    required this.isPinned,
    required this.onPinnedChanged,
    required this.isFavorite,
    required this.onFavoriteChanged,
    required this.onInsertTable,
    required this.onInsertChecklist,
    required this.onInsertImage,
    required this.onImportRecent,
    required this.onMoveFolder,
    required this.onShare,
  });

  final bool toolsOpen;
  final VoidCallback onToggleTools;
  final bool autoSaving;
  final bool dirty;
  final DateTime? lastSavedAt;
  final String? color;
  final ValueChanged<String> onColorChanged;
  final TextEditingController tagsController;
  final bool isPinned;
  final ValueChanged<bool> onPinnedChanged;
  final bool isFavorite;
  final ValueChanged<bool> onFavoriteChanged;
  final VoidCallback onInsertTable;
  final VoidCallback onInsertChecklist;
  final VoidCallback onInsertImage;
  final VoidCallback onImportRecent;
  final VoidCallback onMoveFolder;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SafeArea(
      top: false,
      child: Container(
        decoration: BoxDecoration(
          color: p.surface,
          border: Border(top: BorderSide(color: p.border)),
        ),
        child: AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          alignment: Alignment.topCenter,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Compact bar.
              Padding(
                padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.page, vertical: 8),
                child: Row(
                  children: [
                    _FormatToggle(open: toolsOpen, onTap: onToggleTools),
                    const SizedBox(width: 8),
                    _CircleBtn(
                        icon: HeroPaths.bookmark,
                        active: isPinned,
                        onTap: () => onPinnedChanged(!isPinned)),
                    const SizedBox(width: 8),
                    _CircleBtn(
                        icon: HeroPaths.star,
                        active: isFavorite,
                        onTap: () => onFavoriteChanged(!isFavorite)),
                    const Spacer(),
                    _SaveStatus(autoSaving: autoSaving, dirty: dirty, lastSavedAt: lastSavedAt),
                  ],
                ),
              ),
              if (toolsOpen) ...[
                Divider(height: 1, color: p.border),
                // Format row — horizontally scrollable so the chips never
                // overflow the width on narrow phones (was a fixed Row).
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(
                      horizontal: AppSpacing.page, vertical: 10),
                  child: Row(
                    children: [
                      _FormatChip(label: 'Table', onTap: onInsertTable),
                      const SizedBox(width: 8),
                      _FormatChip(label: 'Checklist', onTap: onInsertChecklist),
                      const SizedBox(width: 8),
                      _FormatChip(label: 'Image', onTap: onInsertImage),
                      const SizedBox(width: 8),
                      _FormatChip(label: 'Import', onTap: onImportRecent),
                      const SizedBox(width: 8),
                      _FormatChip(label: 'Share', onTap: onShare),
                      const SizedBox(width: 8),
                      _FormatChip(label: 'Move', onTap: onMoveFolder),
                    ],
                  ),
                ),
                Divider(height: 1, color: p.border),
                // Color + tags
                Padding(
                  padding: const EdgeInsets.fromLTRB(
                      AppSpacing.page, 14, AppSpacing.page, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('COLOR',
                          style: TextStyle(
                              fontFamily: 'Geist',
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1,
                              color: p.textFaint)),
                      const SizedBox(height: 10),
                      _ColorRow(selected: color, onSelected: onColorChanged),
                      const SizedBox(height: 18),
                      Text('FOLDER / TAGS',
                          style: TextStyle(
                              fontFamily: 'Geist',
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1,
                              color: p.textFaint)),
                      const SizedBox(height: 6),
                      TextField(
                        controller: tagsController,
                        textCapitalization: TextCapitalization.none,
                        style: TextStyle(
                            fontSize: 15,
                            color: p.textPrimary,
                            fontWeight: FontWeight.w500),
                        decoration: InputDecoration(
                          isDense: true,
                          hintText: 'folder, work, ideas',
                          hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
                          contentPadding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 12),
                          border: OutlineInputBorder(
                              borderRadius:
                                  BorderRadius.circular(AppRadii.button),
                              borderSide: BorderSide(color: p.border)),
                          enabledBorder: OutlineInputBorder(
                              borderRadius:
                                  BorderRadius.circular(AppRadii.button),
                              borderSide: BorderSide(color: p.border)),
                          focusedBorder: OutlineInputBorder(
                              borderRadius:
                                  BorderRadius.circular(AppRadii.button),
                              borderSide:
                                  BorderSide(color: p.ink, width: 1.5)),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _SaveStatus extends StatelessWidget {
  const _SaveStatus(
      {required this.autoSaving, required this.dirty, required this.lastSavedAt});
  final bool autoSaving;
  final bool dirty;
  final DateTime? lastSavedAt;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    String label;
    if (autoSaving) {
      label = 'Saving…';
    } else if (dirty) {
      label = 'Unsaved';
    } else if (lastSavedAt != null) {
      label = 'Saved';
    } else {
      label = '';
    }
    if (label.isEmpty) return const SizedBox.shrink();
    return Text(label,
        style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: autoSaving || dirty ? p.textFaint : p.textMuted));
  }
}

class _FormatToggle extends StatelessWidget {
  const _FormatToggle({required this.open, required this.onTap});
  final bool open;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: open ? p.ink : p.canvas,
          border: Border.all(color: open ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text('Aa',
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: open ? p.onInk : p.textMuted)),
      ),
    );
  }
}

class _CircleBtn extends StatelessWidget {
  const _CircleBtn(
      {required this.icon, required this.active, required this.onTap});
  final String icon;
  final bool active;
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
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: active ? p.ink : p.canvas,
          border: Border.all(color: active ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child:
            HeroIcon(icon, size: 16, color: active ? p.onInk : p.textMuted),
      ),
    );
  }
}

class _FormatChip extends StatelessWidget {
  const _FormatChip({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: p.canvas,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(label,
            style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: p.textPrimary)),
      ),
    );
  }
}

class _ColorRow extends StatelessWidget {
  const _ColorRow({required this.selected, required this.onSelected});
  final String? selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SizedBox(
      height: 36,
      child: ListView(
        scrollDirection: Axis.horizontal,
        children: [
          _ColorDot(
            border: p.border,
            selected: selected == null,
            onTap: () => onSelected(''),
            child: selected == null
                ? HeroIcon(HeroPaths.check, size: 14, color: p.ink)
                : HeroIcon(HeroPaths.xMark, size: 12, color: p.textFaint),
          ),
          const SizedBox(width: 10),
          for (final hex in kNoteColorSwatches) ...[
            _ColorDot(
              color: parseHex(hex),
              selected: selected == hex,
              onTap: () => onSelected(hex),
            ),
            const SizedBox(width: 10),
          ],
        ],
      ),
    );
  }
}

class _ColorDot extends StatelessWidget {
  const _ColorDot({
    this.color,
    this.border,
    required this.selected,
    required this.onTap,
    this.child,
  });
  final Color? color;
  final Color? border;
  final bool selected;
  final VoidCallback onTap;
  final Widget? child;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 36,
        height: 36,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: color ?? p.surface,
          shape: BoxShape.circle,
          border: Border.all(
              color: selected ? p.ink : (border ?? p.border),
              width: selected ? 2 : 1),
        ),
        child: child,
      ),
    );
  }
}
