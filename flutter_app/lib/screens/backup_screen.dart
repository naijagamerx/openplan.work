import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../repositories/backup_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/mono_button.dart';
import '../widgets/skeleton.dart';

/// Backup management screen — list/create/restore/delete AES-256 backups.
/// Mirrors the PHP app's Backup & Export section. Plain Scaffold (no bottom
/// nav) reached via the /backup route.
class BackupScreen extends ConsumerWidget {
  const BackupScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final backups = ref.watch(backupsProvider);

    return Scaffold(
      backgroundColor: p.canvas,
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => Navigator.of(context).maybePop(),
        ),
        title: const Text('Backup'),
      ),
      body: SafeArea(
        child: backups.when(
          loading: () => ListView(
            padding: const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 40),
            children: const [
              Skeleton(height: 48, radius: AppRadii.button),
              SizedBox(height: AppSpacing.gap),
              Skeleton(height: 76, radius: AppRadii.card),
              SizedBox(height: 10),
              Skeleton(height: 76, radius: AppRadii.card),
              SizedBox(height: 10),
              Skeleton(height: 76, radius: AppRadii.card),
            ],
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyState(
                iconPath: HeroPaths.cloud,
                message: 'Could not load backups',
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(backupsProvider),
              ),
            ),
          ),
          data: (list) => RefreshIndicator(
            onRefresh: () => ref.refresh(backupsProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 40),
              children: [
                FadeIn(
                  child: MonoButton(
                    'Create backup',
                    onPressed: () => _create(context, ref),
                  ),
                ),
                const SizedBox(height: AppSpacing.gap),
                if (list.isEmpty)
                  const FadeIn(
                    delay: Duration(milliseconds: 60),
                    child: Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: EmptyState(
                          iconPath: HeroPaths.archive, message: 'No backups yet'),
                    ),
                  )
                else
                  for (var i = 0; i < list.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 50).clamp(0, 300)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _BackupRow(
                          backup: list[i],
                          onRestore: () => _restore(context, ref, list[i]),
                          onDelete: () => _delete(context, ref, list[i]),
                        ),
                      ),
                    ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _create(BuildContext context, WidgetRef ref) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      final filename =
          await ref.read(backupRepositoryProvider).create();
      ref.invalidate(backupsProvider);
      messenger.showSnackBar(SnackBar(
        content: Text(filename.isEmpty ? 'Backup created' : 'Created $filename'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to create backup: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  Future<void> _restore(
      BuildContext context, WidgetRef ref, BackupRecord b) async {
    final messenger = ScaffoldMessenger.of(context);
    final ok = await confirmDialog(
      context,
      title: 'Restore backup?',
      message:
          'This replaces all current data with the snapshot from ${b.date}. '
          'This cannot be undone.',
      confirmLabel: 'Restore',
      destructive: false,
    );
    if (!ok) return;
    try {
      await ref.read(backupRepositoryProvider).restore(b.filename);
      ref.invalidate(backupsProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Backup restored'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Restore failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  Future<void> _delete(
      BuildContext context, WidgetRef ref, BackupRecord b) async {
    final messenger = ScaffoldMessenger.of(context);
    final ok = await confirmDialog(
      context,
      title: 'Delete backup?',
      message: 'Permanently delete ${b.filename}? This cannot be undone.',
      confirmLabel: 'Delete',
      destructive: true,
    );
    if (!ok) return;
    try {
      await ref.read(backupRepositoryProvider).delete(b.filename);
      ref.invalidate(backupsProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Backup deleted'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Delete failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }
}

class _BackupRow extends StatelessWidget {
  const _BackupRow({
    required this.backup,
    required this.onRestore,
    required this.onDelete,
  });

  final BackupRecord backup;
  final VoidCallback onRestore;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final size = backup.sizeFormatted.isEmpty
        ? _formatBytes(backup.sizeBytes)
        : backup.sizeFormatted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
        // DESIGN.md §7: borders, not shadows.
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: p.canvas,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: p.border),
            ),
            alignment: Alignment.center,
            child: HeroIcon(HeroPaths.archive, size: 20, color: p.textMuted),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  backup.filename,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: p.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '$size · ${_formatDate(backup)}',
                  style: TextStyle(fontSize: 12, color: p.textFaint),
                ),
              ],
            ),
          ),
          GestureDetector(
            onTap: onRestore,
            behavior: HitTestBehavior.opaque,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: HeroIcon(HeroPaths.arrowPath, size: 18, color: p.textMuted),
            ),
          ),
          const SizedBox(width: 4),
          GestureDetector(
            onTap: onDelete,
            behavior: HitTestBehavior.opaque,
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: HeroIcon(HeroPaths.trash,
                  size: 18, color: Color(0xFFDC2626)),
            ),
          ),
        ],
      ),
    );
  }

  static String _formatDate(BackupRecord b) {
    // Prefer the created_at ISO timestamp; fall back to the parsed date/time.
    final iso = b.createdAt;
    if (iso.isNotEmpty) {
      final dt = DateTime.tryParse(iso);
      if (dt != null) {
        final d = dt.toLocal();
        String two(int n) => n.toString().padLeft(2, '0');
        return '${d.year}-${two(d.month)}-${two(d.day)} ${two(d.hour)}:${two(d.minute)}';
      }
    }
    if (b.date.isNotEmpty) return b.date;
    return '—';
  }

  static String _formatBytes(int bytes) {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    var i = 0;
    var size = bytes.toDouble();
    while (size >= 1024 && i < units.length - 1) {
      size /= 1024;
      i++;
    }
    return size >= 100 || i == 0
        ? '${size.round()} ${units[i]}'
        : '${size.toStringAsFixed(1)} ${units[i]}';
  }
}
