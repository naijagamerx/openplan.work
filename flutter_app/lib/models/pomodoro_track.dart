/// A Focus-Music track as returned by `GET /api/pomodoro.php?action=music_list`.
///
/// Mirrors the decorated backend record: `{id, name, filename, mime, storage,
/// size, uploadedAt, canDelete, canRename}`.
class PomodoroTrack {
  PomodoroTrack({
    required this.id,
    required this.name,
    required this.filename,
    this.mime = 'audio/mpeg',
    this.storage = 'shared',
    this.size = 0,
    this.uploadedAt,
    this.canDelete = false,
    this.canRename = false,
  });

  final String id;
  final String name;
  final String filename;
  final String mime;
  final String storage; // "shared" | "legacy"
  final int size; // bytes
  final DateTime? uploadedAt;
  final bool canDelete;
  final bool canRename;

  /// Whether this track came bundled in the shared library (vs. user-uploaded).
  bool get isShared => id.startsWith('shared_');

  /// Streaming URL — `music_download` streams the bytes (range-capable).
  String url(String baseUrl) =>
      '$baseUrl/api/pomodoro.php?action=music_download&id=${Uri.encodeComponent(id)}';

  factory PomodoroTrack.fromJson(Map<String, dynamic> j) {
    final rawSize = j['size'];
    return PomodoroTrack(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? 'Track').toString(),
      filename: (j['filename'] ?? '').toString(),
      mime: (j['mime'] ?? 'audio/mpeg').toString(),
      storage: (j['storage'] ?? 'shared').toString(),
      size: rawSize is num
          ? rawSize.toInt()
          : int.tryParse(rawSize?.toString() ?? '') ?? 0,
      uploadedAt: _parseDate(j['uploadedAt']),
      canDelete: j['canDelete'] == true,
      canRename: j['canRename'] == true,
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}
