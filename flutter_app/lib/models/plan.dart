/// A subscription plan as returned by `GET api/plans.php`.
/// Mirrors the backend schema (see api/plans.php + PlansAPI.php).
///
/// `monthlyRequestCap == 0` means unlimited (the PHP UI renders this as "∞").
class Plan {
  Plan({
    required this.id,
    required this.name,
    required this.price,
    this.currency = 'USD',
    this.durationDays = 30,
    this.monthlyRequestCap = 0,
    this.providers = const [],
    this.description = '',
    this.active = true,
  });

  final String id;
  final String name;
  final double price;
  final String currency; // USD | EUR | GBP | ZAR
  final int durationDays;
  final int monthlyRequestCap; // 0 = unlimited
  final List<String> providers; // groq | openrouter | gemini | ollama
  final String description;
  final bool active;

  /// True if there's a description beyond whitespace.
  bool get hasDescription => description.trim().isNotEmpty;

  /// True when the request cap is unlimited (0 on the server = ∞).
  bool get isUnlimited => monthlyRequestCap <= 0;

  factory Plan.fromJson(Map<String, dynamic> j) {
    final rawProviders = j['providers'];
    return Plan(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? '').toString(),
      price: _toDouble(j['price']),
      currency: (j['currency'] ?? 'USD').toString().toUpperCase(),
      durationDays: _toInt(j['durationDays'] ?? j['duration_days']),
      monthlyRequestCap: _toInt(j['monthlyRequestCap'] ?? j['monthly_request_cap']),
      providers: rawProviders is List
          ? rawProviders.map((p) => p.toString()).toList()
          : (rawProviders.toString().isNotEmpty
              ? rawProviders
                  .toString()
                  .split(',')
                  .map((p) => p.trim())
                  .where((p) => p.isNotEmpty)
                  .toList()
              : const []),
      description: (j['description'] ?? '').toString(),
      active: j['active'] != false,
    );
  }

  /// Build a JSON body suitable for POST/PUT to api/plans.php.
  Map<String, dynamic> toBody() => {
        'name': name,
        'price': price,
        'currency': currency,
        'durationDays': durationDays,
        'monthlyRequestCap': monthlyRequestCap,
        'providers': providers,
        'description': description,
        'active': active,
      };

  Plan copyWith({
    String? name,
    double? price,
    String? currency,
    int? durationDays,
    int? monthlyRequestCap,
    List<String>? providers,
    String? description,
    bool? active,
  }) {
    return Plan(
      id: id,
      name: name ?? this.name,
      price: price ?? this.price,
      currency: currency ?? this.currency,
      durationDays: durationDays ?? this.durationDays,
      monthlyRequestCap: monthlyRequestCap ?? this.monthlyRequestCap,
      providers: providers ?? this.providers,
      description: description ?? this.description,
      active: active ?? this.active,
    );
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString()) ?? 0;
  }

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? 0;
  }
}
