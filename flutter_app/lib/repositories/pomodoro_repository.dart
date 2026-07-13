import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../config/app_config.dart';
import '../models/pomodoro_session.dart';
import '../models/pomodoro_track.dart';

final pomodoroRepositoryProvider = Provider<PomodoroRepository>(
    (ref) => PomodoroRepository(ref.watch(apiClientProvider)));

/// Auto-refreshable recent-sessions list (newest first, capped at 8 by callers).
final pomodoroSessionsProvider =
    FutureProvider<List<PomodoroSession>>((ref) async {
  final sessions = await ref.watch(pomodoroRepositoryProvider).listSessions();
  // The backend already reverses to newest-first; keep a stable order.
  return sessions;
});

/// Auto-refreshable focus-music library.
final pomodoroMusicProvider =
    FutureProvider<List<PomodoroTrack>>((ref) =>
    ref.watch(pomodoroRepositoryProvider).listMusic());

/// Talks to `api/pomodoro.php` (complete / list / music_list / music_upload /
/// music_delete / music_download).
class PomodoroRepository {
  PomodoroRepository(this._api);
  final ApiClient _api;

  /// Record a completed session. [focusMinutes] drives the `mode` label and
  /// [duration] is sent in seconds (backend default 1500 = 25min).
  Future<String> complete({
    required int focusMinutes,
    int breakMinutes = 5,
  }) async {
    final data = await _api.post('/api/pomodoro.php',
        query: {'action': 'complete'},
        body: {
          'mode': focusMinutes,
          'duration': focusMinutes * 60,
        });
    final map = data is Map ? data : <String, dynamic>{};
    return (map['id'] ?? '').toString();
  }

  /// Recent sessions, newest first.
  Future<List<PomodoroSession>> listSessions() async {
    final data = await _api.get('/api/pomodoro.php', query: {'action': 'list'});
    final list = (data as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((s) => PomodoroSession.fromJson(s.cast<String, dynamic>()))
        .toList();
  }

  /// The focus-music library (user-uploaded + bundled shared tracks).
  Future<List<PomodoroTrack>> listMusic() async {
    final data =
        await _api.get('/api/pomodoro.php', query: {'action': 'music_list'});
    final list = (data as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((t) => PomodoroTrack.fromJson(t.cast<String, dynamic>()))
        .toList();
  }

  /// Upload a track (multipart `file`). Returns the new track record.
  Future<PomodoroTrack> uploadMusic({
    required String name,
    required String filePath,
  }) async {
    final data = await _api.upload(
      '/api/pomodoro.php?action=music_upload',
      field: 'file',
      filePath: filePath,
      fields: {'name': name},
    );
    final map = data is Map ? data.cast<String, dynamic>() : <String, dynamic>{};
    return PomodoroTrack.fromJson(map);
  }

  /// Delete a track by its id.
  Future<void> deleteMusic(String id) async {
    await _api.post('/api/pomodoro.php',
        query: {'action': 'music_delete'}, body: {'id': id});
  }

  /// Streaming base for building track playback URLs.
  String get baseUrl => AppConfig.baseUrl;
}
