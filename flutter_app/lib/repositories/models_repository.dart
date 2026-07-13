import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import 'config_repository.dart';

/// Provider for the models repository. Backs the `models` collection — the
/// per-provider model list with enabled/default flags — via `api/models.php`.
/// Distinct from `config` (API keys live there, see ConfigRepository).
final modelsRepositoryProvider = Provider<ModelsRepository>(
    (ref) => ModelsRepository(ref.watch(apiClientProvider)));

/// All models grouped by provider, loaded once and cached while the model
/// settings screen is mounted. Invalidate via `ref.invalidate(modelsProvider)`
/// after a toggle/default change so the list refreshes.
final modelsProvider =
    FutureProvider<Map<String, List<AiModel>>>((ref) async {
  return ref.watch(modelsRepositoryProvider).list();
});

/// One configured model entry on the `models` collection (api/models.php).
class AiModel {
  AiModel({
    required this.id,
    required this.provider,
    required this.modelId,
    this.displayName = '',
    this.description = '',
    this.enabled = true,
    this.isDefault = false,
  });

  final String id; // backend record id (used for update/set-default/delete)
  final String provider;
  final String modelId;
  final String displayName;
  final String description;
  final bool enabled;
  final bool isDefault;

  /// Display label: prefer displayName, fall back to the raw model id.
  String get label =>
      displayName.trim().isNotEmpty ? displayName.trim() : modelId;

  factory AiModel.fromJson(Map<String, dynamic> j, String provider) {
    return AiModel(
      id: (j['id'] ?? '').toString(),
      provider: provider,
      modelId: (j['modelId'] ?? j['id'] ?? '').toString(),
      displayName: (j['displayName'] ?? '').toString(),
      description: (j['description'] ?? '').toString(),
      enabled: j['enabled'] is bool ? j['enabled'] as bool : true,
      isDefault: j['isDefault'] is bool ? j['isDefault'] as bool : false,
    );
  }
}

class ModelsRepository {
  ModelsRepository(this._api);

  final ApiClient _api;

  /// Fetch all models grouped by provider (`GET /api/models.php?action=list`).
  /// Always returns a map with all four provider keys (empty list when none are
  /// configured) so the UI can render a consistent per-provider layout.
  Future<Map<String, List<AiModel>>> list() async {
    final data = await _api.get('/api/models.php', query: {'action': 'list'});
    final map = data is Map ? data.cast<String, dynamic>() : <String, dynamic>{};
    final out = <String, List<AiModel>>{};
    for (final provider in aiProviders) {
      final raw = map[provider];
      final items = <AiModel>[];
      if (raw is List) {
        for (final entry in raw) {
          if (entry is Map) {
            items.add(AiModel.fromJson(entry.cast<String, dynamic>(), provider));
          }
        }
      }
      out[provider] = items;
    }
    return out;
  }

  /// Toggle a model's `enabled` flag (`POST /api/models.php?action=update&id=…`).
  Future<void> setEnabled(String id, bool enabled) async {
    await _api.post('/api/models.php',
        query: {'id': id}, body: {'enabled': enabled});
  }

  /// Set a model as the default for its provider
  /// (`POST /api/models.php?action=set-default&id=…&provider=…`).
  Future<void> setDefault(String id, String provider) async {
    await _api.post('/api/models.php',
        query: {'id': id, 'provider': provider}, body: <String, dynamic>{});
  }

  /// Add a new model to a provider (`POST /api/models.php?action=add`).
  /// The backend rejects ids that look like API keys — those belong in the API
  /// key field (ConfigRepository.saveApiKeys).
  Future<void> add({
    required String provider,
    required String modelId,
    String displayName = '',
    bool enabled = true,
  }) async {
    await _api.post('/api/models.php', query: {'action': 'add'}, body: {
      'provider': provider,
      'modelId': modelId,
      if (displayName.isNotEmpty) 'displayName': displayName,
      'enabled': enabled,
    });
  }

  /// Delete a model (`DELETE /api/models.php?action=delete&id=…`).
  /// The backend refuses to delete a provider's default model.
  Future<void> delete(String id) async {
    await _api.delete('/api/models.php', query: {'id': id});
  }
}
