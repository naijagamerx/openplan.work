/// An audit log entry, as returned by `GET /api/audit.php?action=list`.
///
/// Mirrors the backend schema (see Audit::log / getLogs). Each entry captures
/// one actor action: who (user), what (event + description), where (ip), and
/// the resource touched. `success` flags failed attempts (e.g. login_failed).
class AuditLog {
  AuditLog({
    required this.id,
    required this.timestamp,
    required this.event,
    this.userId,
    this.userEmail,
    this.userName,
    this.ipAddress,
    this.resourceType,
    this.resourceId,
    this.description,
    this.success = true,
  });

  final String id;
  final DateTime? timestamp;
  final String event;
  final String? userId;
  final String? userEmail;
  final String? userName;
  final String? ipAddress;
  final String? resourceType;
  final String? resourceId;
  final String? description;
  final bool success;

  /// Short human label for the event category (e.g. `user.login` → "Login").
  String get eventLabel {
    final dot = event.lastIndexOf('.');
    final tail = dot >= 0 ? event.substring(dot + 1) : event;
    return tail[0].toUpperCase() + tail.substring(1);
  }

  factory AuditLog.fromJson(Map<String, dynamic> j) => AuditLog(
        id: (j['id'] ?? '').toString(),
        timestamp: _parseDate(j['timestamp']),
        event: (j['event'] ?? '').toString(),
        userId: _nullIfEmpty(j['user_id']),
        userEmail: _nullIfEmpty(j['user_email']),
        userName: _nullIfEmpty(j['user_name']),
        ipAddress: _nullIfEmpty(j['ip_address']),
        resourceType: _nullIfEmpty(j['resource_type']),
        resourceId: _nullIfEmpty(j['resource_id']),
        description: _nullIfEmpty(j['description']),
        success: j['success'] != false,
      );

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static String? _nullIfEmpty(dynamic v) {
    if (v == null) return null;
    final s = v.toString();
    return s.isEmpty ? null : s;
  }
}
