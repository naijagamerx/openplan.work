/// A client as returned by `GET /api/clients.php`.
/// Mirrors the backend schema (see api/clients.php + BaseAPI::getAllowedFields:
/// name, email, phone, company, address, website, notes). The `address` field
/// is stored as a single string on the server (the PHP form flattens any nested
/// address shape into a comma-joined string), so it is modelled as a String.
class Client {
  Client({
    required this.id,
    required this.name,
    required this.email,
    this.phone = '',
    this.company = '',
    this.website = '',
    this.address = '',
    this.notes = '',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final String email;
  final String phone;
  final String company;
  final String website;
  final String address;
  final String notes;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// True if there are notes beyond whitespace.
  bool get hasNotes => notes.trim().isNotEmpty;

  /// True if there is an address beyond whitespace.
  bool get hasAddress => address.trim().isNotEmpty;

  /// First-letter initials of the first and last name (for the avatar circle).
  /// Falls back to the first character of the name, or '?' if empty.
  String get initials {
    final trimmed = name.trim();
    if (trimmed.isEmpty) return '?';
    final parts = trimmed.split(RegExp(r'\s+'));
    if (parts.length == 1) {
      return _firstChar(parts[0]).toUpperCase();
    }
    final first = _firstChar(parts.first);
    final last = _firstChar(parts.last);
    return '${first.toUpperCase()}${last.toUpperCase()}';
  }

  /// First grapheme of a string (safe for multi-byte names), or '' if empty.
  static String _firstChar(String s) {
    if (s.isEmpty) return '';
    final runes = s.runes;
    return runes.isEmpty ? '' : String.fromCharCode(runes.first);
  }

  factory Client.fromJson(Map<String, dynamic> j) {
    return Client(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? '').toString(),
      email: (j['email'] ?? '').toString(),
      phone: (j['phone'] ?? '').toString(),
      company: (j['company'] ?? '').toString(),
      website: (j['website'] ?? '').toString(),
      address: _flatten(j['address']),
      notes: (j['notes'] ?? '').toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  /// Build a JSON body suitable for POST/PUT to /api/clients.php.
  Map<String, dynamic> toBody() => {
        'name': name,
        'email': email,
        if (phone.isNotEmpty) 'phone': phone,
        if (company.isNotEmpty) 'company': company,
        if (website.isNotEmpty) 'website': website,
        if (address.isNotEmpty) 'address': address,
        if (notes.isNotEmpty) 'notes': notes,
      };

  Client copyWith({
    String? name,
    String? email,
    String? phone,
    String? company,
    String? website,
    String? address,
    String? notes,
  }) {
    return Client(
      id: id,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      company: company ?? this.company,
      website: website ?? this.website,
      address: address ?? this.address,
      notes: notes ?? this.notes,
      createdAt: createdAt,
      updatedAt: updatedAt,
    );
  }

  /// The server sometimes stores `address` as a nested object (street, city,
  /// state, zip, country). Mirror the PHP app's `$toDisplayValue` flattening so
  /// the value is always a readable single string.
  static String _flatten(dynamic v) {
    if (v == null) return '';
    if (v is String) return v.trim();
    if (v is Map) {
      const keys = ['street', 'city', 'state', 'zip', 'country'];
      final parts = <String>[];
      for (final k in keys) {
        final part = v[k]?.toString().trim();
        if (part != null && part.isNotEmpty) parts.add(part);
      }
      if (parts.isEmpty) {
        v.forEach((_, val) {
          final s = val?.toString().trim();
          if (s != null && s.isNotEmpty) parts.add(s);
        });
      }
      return parts.join(', ');
    }
    return v.toString().trim();
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}
