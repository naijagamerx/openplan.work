import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/note.dart';

final notesRepositoryProvider =
    Provider<NotesRepository>((ref) => NotesRepository(ref.watch(apiClientProvider)));

/// All notes. The backend returns them sorted (pinned first, then updatedAt
/// desc). Auto-refreshable from the UI via [ref.invalidate].
final notesProvider = FutureProvider<List<Note>>((ref) async {
  final all = await ref.watch(notesRepositoryProvider).list();
  // Hide notes tagged 'archived' from the default list (mirrors mobile/views/notes.php).
  return all
      .where((n) => !n.tags.any((t) => t.toLowerCase() == 'archived'))
      .toList();
});

class NotesRepository {
  NotesRepository(this._api);
  final ApiClient _api;

  Future<List<Note>> list() async {
    final data = await _api.get('/api/notes.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((n) => Note.fromJson(n.cast<String, dynamic>()))
        .toList();
  }

  Future<Note?> fetch(String id) async {
    final data = await _api.get('/api/notes.php', query: {'id': id});
    if (data is Map<String, dynamic>) return Note.fromJson(data);
    if (data is Map) return Note.fromJson(data.cast<String, dynamic>());
    // Fall back to scanning the list (some envelopes don't support GET ?id=).
    final all = await list();
    for (final n in all) {
      if (n.id == id) return n;
    }
    return null;
  }

  Future<Note> create({
    required String title,
    String content = '',
    List<String> tags = const [],
    String? color,
    bool isPinned = false,
    bool isFavorite = false,
  }) async {
    final data = await _api.post('/api/notes.php', body: {
      'title': title,
      'content': content,
      'tags': tags,
      if (color != null) 'color': color,
      'isPinned': isPinned,
      'isFavorite': isFavorite,
    });
    if (data is Map) return Note.fromJson(data.cast<String, dynamic>());
    // Re-fetch list to resolve the created note if no body returned.
    final all = await list();
    return all.first;
  }

  Future<void> update({
    required String id,
    String? title,
    String? content,
    List<String>? tags,
    String? color,
    bool? isPinned,
    bool? isFavorite,
  }) async {
    await _api.put('/api/notes.php', query: {'id': id}, body: {
      if (title != null) 'title': title,
      if (content != null) 'content': content,
      if (tags != null) 'tags': tags,
      if (color != null) 'color': color,
      if (isPinned != null) 'isPinned': isPinned,
      if (isFavorite != null) 'isFavorite': isFavorite,
    });
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/notes.php', query: {'id': id});
  }

  /// Toggle the pinned flag via the dedicated action endpoint.
  Future<void> togglePin(String id) async {
    await _api.post('/api/notes.php', query: {'action': 'pin'}, body: {'id': id});
  }

  /// Add a tag to a note via the dedicated action endpoint.
  Future<void> addTag(String id, String tag) async {
    await _api.post('/api/notes.php', query: {'action': 'add_tag'}, body: {
      'id': id,
      'tag': tag,
    });
  }
}
