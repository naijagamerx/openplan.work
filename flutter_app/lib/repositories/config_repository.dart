import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';

/// Provider for the config repository. Backs the user's `config` collection —
/// AI custom instructions, per-provider API keys, Ollama URL, business fields —
/// via `GET/POST /api/settings.php`. Not to be confused with the `models`
/// collection (api/models.php), which holds the per-provider model list.
final configRepositoryProvider = Provider<ConfigRepository>(
    (ref) => ConfigRepository(ref.watch(apiClientProvider)));

/// The user's config collection, loaded once and cached while a config screen
/// is mounted. `autoDispose` would re-fetch on every screen exit/entry; keeping
/// it long-lived mirrors the settings screens' "load once, edit, save" flow.
/// Callers invalidate via `ref.invalidate(configProvider)` after a save.
final configProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  return ref.watch(configRepositoryProvider).load();
});

/// The four AI providers the backend recognises (api/models.php / settings.php).
const aiProviders = ['groq', 'openrouter', 'gemini', 'ollama'];

/// Map of provider → human display label (matches the AI assistant's dropdown).
const providerLabels = <String, String>{
  'groq': 'Groq',
  'openrouter': 'OpenRouter',
  'gemini': 'Gemini',
  'ollama': 'Ollama',
};

/// Config field name holding each provider's API key on the config collection
/// (ollama uses a URL instead of a key). Verified against api/settings.php.
const providerApiKeyField = <String, String>{
  'groq': 'groqApiKey',
  'openrouter': 'openrouterApiKey',
  'gemini': 'geminiApiKey',
  'ollama': 'ollamaUrl',
};

class ConfigRepository {
  ConfigRepository(this._api);

  final ApiClient _api;

  /// Load the full config collection (`GET /api/settings.php?action=get`).
  /// API keys arrive MASKED (`abcd...wxyz`) for security; see [saveApiKeys]
  /// for the guard against overwriting the real value with the mask.
  Future<Map<String, dynamic>> load() async {
    final data = await _api.get('/api/settings.php', query: {'action': 'get'});
    if (data is Map) return data.cast<String, dynamic>();
    return <String, dynamic>{};
  }

  /// Persist the AI custom-instructions system prompt
  /// (`POST /api/settings.php?action=save`, section `customInstructions`).
  Future<void> saveCustomInstructions(String text) async {
    await _api.post('/api/settings.php', query: {'action': 'save'}, body: {
      'section': 'customInstructions',
      'customInstructions': text,
    });
  }

  /// Persist per-provider API keys / Ollama URL
  /// (`POST /api/settings.php?action=save`, section `api`).
  ///
  /// Only fields present in [keys] are sent, and only when the value does NOT
  /// contain the masking sentinel `...` — otherwise the masked placeholder
  /// returned by `get` would overwrite the real key server-side.
  Future<void> saveApiKeys(Map<String, String> keys) async {
    final body = <String, dynamic>{'section': 'api'};
    keys.forEach((field, value) {
      if (value.contains('...')) return; // unchanged masked placeholder
      body[field] = value.trim();
    });
    await _api.post('/api/settings.php', query: {'action': 'save'}, body: body);
  }

  /// Generic escape hatch: POST a raw section body to the save action.
  /// Prefer the typed helpers above in new code.
  Future<void> saveSection(String section, Map<String, dynamic> fields) async {
    debugPrint('[Config] saveSection $section -> ${fields.keys}');
    await _api.post('/api/settings.php', query: {'action': 'save'}, body: {
      'section': section,
      ...fields,
    });
  }
}
