/// A completed Pomodoro session as returned by `GET /api/pomodoro.php?action=list`.
///
/// Mirrors the backend record stored in `pomodoro_sessions` (see
/// api/pomodoro.php `complete` action):
///   `{id, mode, duration, date, status}` where `mode` is a string such as
///   "25 minutes", `duration` is the focus length in seconds, and `date` is a
///   "Y-m-d H:i:s" timestamp.
class PomodoroSession {
  PomodoroSession({
    required this.id,
    required this.mode,
    required this.durationSeconds,
    required this.date,
    this.status = 'completed',
  });

  final String id;
  final String mode; // e.g. "25 minutes"
  final int durationSeconds; // focus length in seconds
  final DateTime date;
  final String status;

  /// Focus length in whole minutes (rounded).
  int get focusMinutes => (durationSeconds / 60).round();

  /// Break length isn't stored by the backend; derived from the focus mode.
  int get breakMinutes => _breakFor(focusMinutes);

  factory PomodoroSession.fromJson(Map<String, dynamic> j) {
    final rawDuration = j['duration'];
    final durationSeconds = rawDuration is num
        ? rawDuration.toInt()
        : int.tryParse(rawDuration?.toString() ?? '') ?? 0;
    return PomodoroSession(
      id: (j['id'] ?? '').toString(),
      mode: (j['mode'] ?? '25 minutes').toString(),
      durationSeconds: durationSeconds,
      date: _parseDate(j['date']) ?? DateTime.now(),
      status: (j['status'] ?? 'completed').toString(),
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  /// The web app pairs each focus preset with a break length.
  static int _breakFor(int focus) {
    if (focus <= 5) return 1;
    if (focus <= 15) return 3;
    return 5;
  }
}
