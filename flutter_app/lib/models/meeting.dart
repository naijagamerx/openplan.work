/// A calendar meeting as returned by `GET /api/meetings.php`.
/// Mirrors the backend schema (see includes/MeetingsAPI.php::getAllowedFields).
class Meeting {
  Meeting({
    required this.id,
    required this.title,
    required this.date,
    this.startTime,
    this.endTime,
    this.location,
    this.color,
    this.attendees = const [],
    this.notes = '',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String title;
  final DateTime date;
  final String? startTime; // "HH:mm"
  final String? endTime; // "HH:mm"
  final String? location;
  final String? color; // hex, e.g. "#a855f7"
  final List<String> attendees;
  final String notes;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// True if there's a location beyond whitespace.
  bool get hasLocation => location != null && location!.trim().isNotEmpty;

  /// True if there are attendees.
  bool get hasAttendees => attendees.isNotEmpty;

  /// Composed time range label, e.g. "09:00 – 10:00", or just the start.
  String get timeLabel {
    final s = (startTime ?? '').trim();
    final e = (endTime ?? '').trim();
    if (s.isEmpty && e.isEmpty) return '--:--';
    if (e.isEmpty) return s;
    if (s.isEmpty) return e;
    return '$s – $e';
  }

  factory Meeting.fromJson(Map<String, dynamic> j) {
    return Meeting(
      id: (j['id'] ?? '').toString(),
      title: (j['title'] ?? '').toString(),
      date: _parseDate(j['date']) ?? DateTime.now(),
      startTime: j['startTime']?.toString(),
      endTime: j['endTime']?.toString(),
      location: j['location']?.toString(),
      color: j['color']?.toString(),
      attendees: _toStringList(j['attendees']),
      notes: (j['description'] ?? j['notes'] ?? '').toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  /// Build a JSON body suitable for POST/PUT to /api/meetings.php.
  Map<String, dynamic> toBody() => {
        'title': title,
        'date': date.toUtc().toIso8601String(),
        if (startTime != null && startTime!.isNotEmpty) 'startTime': startTime,
        if (endTime != null && endTime!.isNotEmpty) 'endTime': endTime,
        if (location != null && location!.isNotEmpty) 'location': location,
        if (color != null && color!.isNotEmpty) 'color': color,
        'attendees': attendees,
        if (notes.isNotEmpty) 'description': notes,
      };

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static List<String> _toStringList(dynamic v) {
    if (v == null) return const [];
    if (v is String) {
      return v
          .split(',')
          .map((s) => s.trim())
          .where((s) => s.isNotEmpty)
          .toList();
    }
    if (v is List) {
      return v.map((e) => e.toString()).where((s) => s.isNotEmpty).toList();
    }
    return const [];
  }
}
