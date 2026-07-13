/// A habit as returned by `GET /api/habits.php` — the server computes
/// `todayCompleted` per habit. Mirrors the backend schema (api/habits.php).
class Habit {
  Habit({
    required this.id,
    required this.name,
    required this.todayCompleted,
    this.category = 'general',
    this.frequency = 'daily',
    this.reminderTime,
    this.targetDuration = 0,
    this.currentStreak = 0,
    this.bestStreak = 0,
    this.totalCompletions = 0,
    this.active = true,
    this.archived = false,
    this.createdAt,
    // Per-day completion counts for the current month: 'yyyy-MM-dd' → count
    this.monthHistory = const {},
  });

  final String id;
  final String name;
  final bool todayCompleted;
  final String category;
  final String frequency; // daily|weekly|...
  final String? reminderTime;
  final int targetDuration;
  final int currentStreak;
  final int bestStreak;
  final int totalCompletions;
  final bool active;
  final bool archived;
  final DateTime? createdAt;
  final Map<String, int> monthHistory;

  factory Habit.fromJson(Map<String, dynamic> j) {
    final hist = <String, int>{};
    final rawHist = j['history'] ?? j['monthHistory'] ?? j['completions'];
    if (rawHist is Map) {
      rawHist.forEach((k, v) {
        if (v is num) hist[k.toString()] = v.toInt();
      });
    } else if (rawHist is List) {
      for (final entry in rawHist) {
        if (entry is Map) {
          final d = entry['date']?.toString();
          final c = entry['count'] ?? entry['completed'] ?? 1;
          if (d != null && c is num) hist[d] = c.toInt();
        }
      }
    }
    return Habit(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? j['title'] ?? '').toString(),
      todayCompleted: j['todayCompleted'] == true,
      category: (j['category'] ?? 'general').toString(),
      frequency: (j['frequency'] ?? 'daily').toString(),
      reminderTime: j['reminderTime']?.toString(),
      targetDuration: j['targetDuration'] is num
          ? (j['targetDuration'] as num).toInt()
          : 0,
      currentStreak: j['currentStreak'] is num
          ? (j['currentStreak'] as num).toInt()
          : 0,
      bestStreak:
          j['bestStreak'] is num ? (j['bestStreak'] as num).toInt() : 0,
      totalCompletions: j['totalCompletions'] is num
          ? (j['totalCompletions'] as num).toInt()
          : 0,
      active: j['isActive'] != false,
      archived: j['archived'] == true || j['isActive'] == false,
      createdAt: _parseDate(j['createdAt']),
      monthHistory: hist,
    );
  }

  /// Body for POST /api/habits.php (action=add) or PUT.
  Map<String, dynamic> toBody() => {
        'name': name,
        'category': category,
        'frequency': frequency,
        if (reminderTime != null) 'reminderTime': reminderTime,
        'targetDuration': targetDuration,
      };

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}
