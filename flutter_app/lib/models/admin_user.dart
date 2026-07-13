/// A user as returned by `GET api/users.php?action=list` (admin only).
/// Mirrors the backend schema (see api/users.php + mobile/views/users.php).
///
/// `canDelete` is false for the last remaining admin — the server refuses to
/// demote or delete that account, so the UI must disable the destructive
/// actions on it (the "last-admin protection").
class AdminUser {
  AdminUser({
    required this.id,
    required this.email,
    required this.name,
    required this.role,
    this.canDelete = true,
    this.isBanned = false,
    this.emailVerifiedAt,
    this.createdAt,
    this.lastLogin,
  });

  final String id;
  final String email;
  final String name;
  final String role; // admin | user
  final bool canDelete;
  final bool isBanned;
  final DateTime? emailVerifiedAt;
  final DateTime? createdAt;
  final DateTime? lastLogin;

  /// True when the email has been verified.
  bool get isVerified => emailVerifiedAt != null;

  bool get isAdmin => role == 'admin';

  /// First-letter initials of the first and last name (for the avatar circle).
  /// Mirrors Client.initials.
  String get initials {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return '?';
    final parts = trimmed.split(RegExp(r'\s+'));
    if (parts.length == 1) return _firstChar(parts[0]).toUpperCase();
    final first = _firstChar(parts.first);
    final last = _firstChar(parts.last);
    return '${first.toUpperCase()}${last.toUpperCase()}';
  }

  static String _firstChar(String s) {
    if (s.isEmpty) return '';
    final runes = s.runes;
    return runes.isEmpty ? '' : String.fromCharCode(runes.first);
  }

  factory AdminUser.fromJson(Map<String, dynamic> j) {
    return AdminUser(
      id: (j['id'] ?? '').toString(),
      email: (j['email'] ?? '').toString(),
      name: (j['name'] ?? '').toString(),
      role: (j['role'] ?? 'user').toString(),
      // Backend flags the protected (last-admin) accounts as `canDelete:false`.
      canDelete: j['canDelete'] != false,
      isBanned: j['banned'] == true || j['isBanned'] == true,
      emailVerifiedAt: _parseDate(j['emailVerifiedAt'] ?? j['email_verified_at']),
      createdAt: _parseDate(j['createdAt'] ?? j['created_at']),
      lastLogin: _parseDate(j['lastLogin'] ?? j['last_login']),
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}
