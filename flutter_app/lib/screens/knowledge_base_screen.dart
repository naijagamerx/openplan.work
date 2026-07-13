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
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Knowledge Base hub. A single screen with an internal navigation stack:
/// `currentFolderId == null` shows the folder list, a non-null value shows the
/// files for that folder (with a breadcrumb back). Feature parity with the PHP
/// mobile app's knowledge-base page.
class KnowledgeBaseScreen extends ConsumerStatefulWidget {
  const KnowledgeBaseScreen({super.key});

  @override
  ConsumerState<KnowledgeBaseScreen> createState() =>
      _KnowledgeBaseScreenState();
}

class _KnowledgeBaseScreenState extends ConsumerState<KnowledgeBaseScreen> {
  String? _folderId;
  String? _folderName;
  bool _loading = true;
  String? _error;
  List<KbFolder> _folders = const [];
  Map<String, List<KbFile>> _filesByFolder = const {};

  @override
  void initState() {
    super.initState();
    _loadFolders();
  }

  Future<void> _loadFolders() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = ref.read(kbRepositoryProvider);
      final folders = await repo.listFolders();
      if (!mounted) return;
      final byFolder = <String, List<KbFile>>{};
      // Eagerly load each folder's file list (file counts + recent files).
      final results = await Future.wait(
          folders.map((f) => repo.listFiles(f.id).catchError((_) => <KbFile>[])));
      for (var i = 0; i < folders.length; i++) {
        byFolder[folders[i].id] = results[i];
      }
      if (!mounted) return;
      setState(() {
        _folders = folders;
        _filesByFolder = byFolder;
        _loading = false;
      });
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = '$e';
          _loading = false;
        });
      }
    }
  }

  Future<void> _refreshCurrentFolder() async {
    if (_folderId == null) {
      await _loadFolders();
      return;
    }
    final id = _folderId!;
    try {
      final files = await ref.read(kbRepositoryProvider).listFiles(id);
      if (!mounted) return;
      setState(() {
        _filesByFolder = {..._filesByFolder, id: files};
      });
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _openFolder(KbFolder folder) async {
    setState(() {
      _folderId = folder.id;
      _folderName = folder.name;
    });
    if (!_filesByFolder.containsKey(folder.id)) {
      await _refreshCurrentFolder();
    }
  }

  void _backToOverview() {
    setState(() {
      _folderId = null;
      _folderName = null;
    });
    _loadFolders();
  }

  Future<void> _createFolder() async {
    final name = await _promptForName(
      title: 'New Folder',
      label: 'FOLDER NAME',
      hint: 'e.g. Recipes',
    );
    if (name == null || name.trim().isEmpty) return;
    try {
      await ref.read(kbRepositoryProvider).createFolder(name.trim());
      await _loadFolders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Folder created'),
          behavior: SnackBarBehavior.floating,
        ));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _deleteFolder(KbFolder folder) async {
    final ok = await confirmDialog(context,
        title: 'Delete folder',
        message: 'Delete "${folder.name}"? It must be empty first.');
    if (!ok) return;
    try {
      await ref.read(kbRepositoryProvider).deleteFolder(folder.id);
      await _loadFolders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Folder deleted'),
          behavior: SnackBarBehavior.floating,
        ));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _deleteFile(KbFile file) async {
    final ok = await confirmDialog(context,
        title: 'Delete file', message: 'Delete "${file.name}"?');
    if (!ok) return;
    try {
      await ref.read(kbRepositoryProvider).deleteFile(file.id);
      await _refreshCurrentFolder();
      await _loadFolders();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('File deleted'),
          behavior: SnackBarBehavior.floating,
        ));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }

  Future<void> _openFile(KbFile file) async {
    final changed = await context.pushNamed<bool>('knowledge-base-file',
        pathParameters: {'folderId': _folderId ?? file.folderId},
        extra: file.id);
    if (changed == true && mounted) {
      await _refreshCurrentFolder();
      await _loadFolders();
    }
  }

  Future<void> _createFile() async {
    if (_folderId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('Open a folder first'),
        behavior: SnackBarBehavior.floating,
      ));
      return;
    }
    final folderId = _folderId!;
    final changed = await context.pushNamed<bool>('knowledge-base-file-new',
        pathParameters: {'folderId': folderId});
    if (changed == true && mounted) {
      await _refreshCurrentFolder();
      await _loadFolders();
    }
  }

  Future<String?> _promptForName({
    required String title,
    required String label,
    String hint = '',
  }) async {
    final controller = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) {
        return _NameDialog(
          title: title,
          label: label,
          hint: hint,
          controller: controller,
        );
      },
    );
    controller.dispose();
    return result;
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _folderId == null,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && _folderId != null) _backToOverview();
      },
      child: Scaffold(
        appBar: _buildAppBar(),
        floatingActionButton: FloatingActionButton(
          backgroundColor: AppPalette.of(context).ink,
          foregroundColor: AppPalette.of(context).onInk,
          shape: const CircleBorder(),
          elevation: 0,
          focusElevation: 0,
          onPressed: _folderId == null ? _createFolder : _createFile,
          child: HeroIcon(HeroPaths.plus,
              size: 24, color: AppPalette.of(context).onInk),
        ),
        body: _buildBody(),
      ),
    );
  }

  PreferredSizeWidget _buildAppBar() {
    return AppBar(
      leading: IconButton(
        icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
        onPressed: () {
          if (_folderId != null) {
            _backToOverview();
          } else {
            context.pop();
          }
        },
      ),
      title: Text(_folderName ?? 'Knowledge Base'),
    );
  }

  Widget _buildBody() {
    if (_loading) return _LoadingView(folderMode: _folderId == null);
    if (_error != null) {
      return Center(
        child: EmptyState(
          iconPath: HeroPaths.book,
          message: _error!,
          actionLabel: 'Retry',
          onAction: _loadFolders,
        ),
      );
    }
    return _folderId == null ? _folderListView() : _fileListView();
  }

  // ---------------- Folder list (overview) ----------------

  Widget _folderListView() {
    final recent = _allFiles()
        .toList()
      ..sort((a, b) =>
          (b.updatedAt ?? b.createdAt ?? DateTime(0))
              .compareTo(a.updatedAt ?? a.createdAt ?? DateTime(0)));
    final topRecent = recent.take(10).toList();

    return RefreshIndicator(
      onRefresh: _loadFolders,
      child: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
        children: [
          SectionCard(
            heading: 'Directories',
            trailing: GestureDetector(
              onTap: _createFolder,
              behavior: HitTestBehavior.opaque,
              child: Text('NEW FOLDER',
                  style: AppType.label(context, color: AppPalette.of(context).ink, size: 10)),
            ),
            child: _folders.isEmpty
                ? EmptyState(
                    iconPath: HeroPaths.folder,
                    message: 'No folders yet',
                    actionLabel: 'New Folder',
                    onAction: _createFolder,
                  )
                : Column(
                    children: [
                      for (var i = 0; i < _folders.length; i++) ...[
                        if (i > 0)
                          Divider(
                              height: 1, color: AppPalette.of(context).border),
                        FadeIn(
                          delay: Duration(
                              milliseconds: (i * 50).clamp(0, 300)),
                          child: _FolderRow(
                            folder: _folders[i],
                            onTap: () => _openFolder(_folders[i]),
                            onDelete: () => _deleteFolder(_folders[i]),
                          ),
                        ),
                      ],
                    ],
                  ),
          ),
          const SizedBox(height: AppSpacing.gap),
          SectionCard(
            heading: 'Recent Files',
            child: topRecent.isEmpty
                ? const EmptyState(
                    iconPath: HeroPaths.document, message: 'No files yet')
                : Column(
                    children: [
                      for (var i = 0; i < topRecent.length; i++) ...[
                        if (i > 0)
                          Divider(
                              height: 1, color: AppPalette.of(context).border),
                        _RecentFileRow(
                          file: topRecent[i],
                          onTap: () => _openFile(topRecent[i]),
                        ),
                      ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  Iterable<KbFile> _allFiles() {
    return _filesByFolder.values.expand((list) => list);
  }

  // ---------------- File list (inside a folder) ----------------

  Widget _fileListView() {
    final files = _filesByFolder[_folderId!] ?? const [];
    return RefreshIndicator(
      onRefresh: _refreshCurrentFolder,
      child: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
        children: [
          // Breadcrumb.
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(
              children: [
                Text('ROOT',
                    style: AppType.label(context,
                        color: AppPalette.of(context).textFaint, size: 10)),
                HeroIcon(HeroPaths.chevronRight,
                    size: 12, color: AppPalette.of(context).textFaint),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    (_folderName ?? 'FOLDER').toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: AppType.label(context,
                        color: AppPalette.of(context).textPrimary, size: 10),
                  ),
                ),
              ],
            ),
          ),
          SectionCard(
            heading: '${files.length} ${files.length == 1 ? "FILE" : "FILES"}',
            child: files.isEmpty
                ? EmptyState(
                    iconPath: HeroPaths.document,
                    message: 'No files in this folder',
                    actionLabel: 'New File',
                    onAction: _createFile,
                  )
                : Column(
                    children: [
                      for (var i = 0; i < files.length; i++) ...[
                        if (i > 0)
                          Divider(
                              height: 1, color: AppPalette.of(context).border),
                        FadeIn(
                          delay: Duration(
                              milliseconds: (i * 50).clamp(0, 300)),
                          child: _FileRow(
                            file: files[i],
                            onTap: () => _openFile(files[i]),
                            onDelete: () => _deleteFile(files[i]),
                          ),
                        ),
                      ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}

// ---------------- View widgets ----------------

class _LoadingView extends StatelessWidget {
  const _LoadingView({required this.folderMode});
  final bool folderMode;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
      children: [
        const Skeleton(height: 32, radius: AppRadii.card),
        const SizedBox(height: 12),
        for (var i = 0; i < (folderMode ? 4 : 5); i++) ...[
          const Skeleton(height: 64, radius: AppRadii.card),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _FolderRow extends StatelessWidget {
  const _FolderRow({
    required this.folder,
    required this.onTap,
    required this.onDelete,
  });

  final KbFolder folder;
  final VoidCallback onTap;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final count = folder.fileCount;
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: [
            HeroIcon(HeroPaths.folder, size: 24, color: p.textPrimary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(folder.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: p.textPrimary)),
                  const SizedBox(height: 2),
                  Text('$count ${count == 1 ? "ITEM" : "ITEMS"}',
                      style: AppType.label(context,
                          color: p.textFaint, size: 10)),
                ],
              ),
            ),
            GestureDetector(
              onTap: onDelete,
              behavior: HitTestBehavior.opaque,
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: HeroIcon(HeroPaths.trash, size: 16, color: p.textFaint),
              ),
            ),
            const SizedBox(width: 4),
            HeroIcon(HeroPaths.chevronRight, size: 16, color: p.textFaint),
          ],
        ),
      ),
    );
  }
}

class _FileRow extends StatelessWidget {
  const _FileRow({
    required this.file,
    required this.onTap,
    required this.onDelete,
  });

  final KbFile file;
  final VoidCallback onTap;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: p.canvas,
                border: Border.all(color: p.border),
                borderRadius: BorderRadius.circular(AppRadii.button),
              ),
              alignment: Alignment.center,
              child:
                  HeroIcon(HeroPaths.document, size: 16, color: p.textPrimary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(file.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          color: p.textPrimary)),
                  const SizedBox(height: 2),
                  Text(
                    '${_formatBytes(file.size)} · ${_timeAgo(file.updatedAt ?? file.createdAt)}',
                    style: TextStyle(
                        fontFamily: 'GeistMono',
                        fontSize: 10,
                        color: p.textFaint),
                  ),
                ],
              ),
            ),
            GestureDetector(
              onTap: onDelete,
              behavior: HitTestBehavior.opaque,
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: HeroIcon(HeroPaths.trash, size: 16, color: p.textFaint),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _RecentFileRow extends StatelessWidget {
  const _RecentFileRow({required this.file, required this.onTap});
  final KbFile file;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(file.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: p.textPrimary)),
                  const SizedBox(height: 2),
                  Text(file.type.toUpperCase(),
                      style: AppType.label(context,
                          color: p.textFaint, size: 9)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Text(_timeAgo(file.updatedAt ?? file.createdAt),
                style: TextStyle(
                    fontFamily: 'GeistMono', fontSize: 10, color: p.textFaint)),
          ],
        ),
      ),
    );
  }
}

class _NameDialog extends StatefulWidget {
  const _NameDialog({
    required this.title,
    required this.label,
    required this.hint,
    required this.controller,
  });

  final String title;
  final String label;
  final String hint;
  final TextEditingController controller;

  @override
  State<_NameDialog> createState() => _NameDialogState();
}

class _NameDialogState extends State<_NameDialog> {
  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: p.border),
      ),
      title: Text(widget.title,
          style: TextStyle(
              fontSize: 16, fontWeight: FontWeight.w700, color: p.textPrimary)),
      content: LabeledField(
        label: widget.label,
        controller: widget.controller,
        hint: widget.hint,
        keyboardType: TextInputType.text,
        textInputAction: TextInputAction.done,
      ),
      actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
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
          onPressed: () => Navigator.pop(context, widget.controller.text),
          child: Text('CREATE',
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

// ---------------- Helpers ----------------

String _formatBytes(int bytes) {
  if (bytes < 1024) return '$bytes B';
  if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
  return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
}

String _timeAgo(DateTime? when) {
  if (when == null) return '--';
  final now = DateTime.now();
  final diff = now.difference(when).inSeconds;
  if (diff < 60) return '${diff}s ago';
  if (diff < 3600) return '${diff ~/ 60}m ago';
  if (diff < 86400) return '${diff ~/ 3600}h ago';
  final days = diff ~/ 86400;
  if (days < 30) return '${days}d ago';
  return DateFormat.yMMMd().format(when);
}
