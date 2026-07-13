import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';

final backupRepositoryProvider =
    Provider<BackupRepository>((ref) => BackupRepository(ref.watch(apiClientProvider)));

/// All backups (newest first). Auto-refreshable from the UI.
final backupsProvider =
    FutureProvider<List<BackupRecord>>((ref) => ref.watch(backupRepositoryProvider).list());

/// One snapshot in `GET /api/backup.php?action=list`.
///
/// The backend identifies a backup by its `filename` (used for restore/delete),
/// and returns a human-readable `sizeFormatted` alongside the raw byte `size`.
class BackupRecord {
  BackupRecord({
    required this.filename,
    required this.type,
    required this.sizeBytes,
    required this.sizeFormatted,
    required this.createdAt,
    required this.date,
    required this.time,
  });

  final String filename;
  final String type;
  final int sizeBytes;
  final String sizeFormatted;
  final String createdAt;
  final String date; // YYYY-MM-DD
  final String time; // HHMMSS

  factory BackupRecord.fromJson(Map<String, dynamic> j) {
    // `size` may arrive as int or numeric string.
    final rawSize = j['size'];
    final sizeBytes = rawSize is int
        ? rawSize
        : int.tryParse(rawSize?.toString() ?? '') ?? 0;
    return BackupRecord(
      filename: (j['filename'] ?? '').toString(),
      type: (j['type'] ?? 'full').toString(),
      sizeBytes: sizeBytes,
      sizeFormatted: (j['size_formatted'] ?? '').toString(),
      createdAt: (j['created_at'] ?? '').toString(),
      date: (j['date'] ?? '').toString(),
      time: (j['time'] ?? '').toString(),
    );
  }
}

class BackupRepository {
  BackupRepository(this._api);
  final ApiClient _api;

  Future<List<BackupRecord>> list() async {
    final data = await _api.get('/api/backup.php', query: {'action': 'list'});
    // Envelope unwraps to {backups: [...], count}.
    final list = (data is Map ? data['backups'] : data) as List? ?? const [];
    return list
        .whereType<Map>()
        .map((b) => BackupRecord.fromJson(b.cast<String, dynamic>()))
        .toList();
  }

  /// Create a full backup. Returns the new record's filename.
  Future<String> create() async {
    final data = await _api.post('/api/backup.php', query: {'action': 'create'});
    final map = data is Map ? data : <String, dynamic>{};
    return (map['filename'] ?? '').toString();
  }

  /// Restore from a backup identified by its filename.
  Future<void> restore(String filename) async {
    await _api.post('/api/backup.php', query: {'action': 'restore'}, body: {
      'filename': filename,
    });
  }

  /// Delete a backup identified by its filename.
  Future<void> delete(String filename) async {
    await _api.post('/api/backup.php', query: {'action': 'delete'}, body: {
      'filename': filename,
    });
  }
}
