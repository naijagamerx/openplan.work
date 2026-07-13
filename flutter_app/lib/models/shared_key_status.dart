/// Masked status of the admin shared AI keys, as returned by
/// `GET /api/admin-shared-keys.php`.
///
/// The payload is keyed by provider field (`groqApiKey`, `openrouterApiKey`,
/// `geminiApiKey`) — raw key material NEVER leaves the server, only a
/// `configured` flag and the `lastFour` characters. A `models` map carries
/// per-provider model counts.
class SharedKeyStatus {
  SharedKeyStatus({
    required this.providers,
    required this.models,
    this.updatedAt,
  });

  /// Ordered by canonical provider display (Groq, OpenRouter, Gemini).
  final List<ProviderKeyStatus> providers;
  final Map<String, ProviderModelSummary> models;
  final DateTime? updatedAt;

  factory SharedKeyStatus.fromJson(Map<String, dynamic> j) {
    // Field key → human provider label, in display order.
    const order = <MapEntry<String, String>>[
      MapEntry('groqApiKey', 'Groq'),
      MapEntry('openrouterApiKey', 'OpenRouter'),
      MapEntry('geminiApiKey', 'Gemini'),
    ];
    final providers = <ProviderKeyStatus>[];
    for (final e in order) {
      final raw = (j[e.key] as Map?)?.cast<String, dynamic>() ?? const {};
      providers.add(ProviderKeyStatus.fromJson(
        fieldKey: e.key,
        providerName: e.value,
        j: raw,
      ));
    }
    final rawModels =
        (j['models'] as Map?)?.cast<String, dynamic>() ?? const {};
    final models = <String, ProviderModelSummary>{};
    for (final entry in rawModels.entries) {
      final m = (entry.value as Map?)?.cast<String, dynamic>() ?? const {};
      models[entry.key] = ProviderModelSummary.fromJson(m);
    }
    return SharedKeyStatus(
      providers: providers,
      models: models,
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}

/// Masked state of one provider's shared key.
class ProviderKeyStatus {
  const ProviderKeyStatus({
    required this.fieldKey,
    required this.providerName,
    required this.configured,
    this.lastFour,
  });

  /// The JSON body field used when saving (e.g. `groqApiKey`).
  final String fieldKey;

  /// Human label (Groq, OpenRouter, Gemini).
  final String providerName;

  final bool configured;
  final String? lastFour;

  /// Provider slug used to look up model counts (groq/openrouter/gemini).
  String get slug => switch (fieldKey) {
        'groqApiKey' => 'groq',
        'openrouterApiKey' => 'openrouter',
        'geminiApiKey' => 'gemini',
        _ => fieldKey,
      };

  factory ProviderKeyStatus.fromJson({
    required String fieldKey,
    required String providerName,
    required Map<String, dynamic> j,
  }) {
    return ProviderKeyStatus(
      fieldKey: fieldKey,
      providerName: providerName,
      configured: j['configured'] == true,
      lastFour: j['lastFour']?.toString(),
    );
  }
}

/// Model availability summary for a provider.
class ProviderModelSummary {
  const ProviderModelSummary({
    required this.total,
    required this.enabled,
    this.defaultModel,
  });

  final int total;
  final int enabled;
  final String? defaultModel;

  factory ProviderModelSummary.fromJson(Map<String, dynamic> j) =>
      ProviderModelSummary(
        total: _toInt(j['total']),
        enabled: _toInt(j['enabled']),
        defaultModel: j['default']?.toString(),
      );

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? 0;
  }
}
