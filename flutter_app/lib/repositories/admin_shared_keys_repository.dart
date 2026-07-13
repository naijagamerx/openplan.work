import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/shared_key_status.dart';

final adminSharedKeysRepositoryProvider = Provider<AdminSharedKeysRepository>(
    (ref) => AdminSharedKeysRepository(ref.watch(apiClientProvider)));

/// Masked shared-key status. Auto-refreshable from the UI.
final adminSharedKeysProvider =
    FutureProvider<SharedKeyStatus>((ref) async {
  return ref.watch(adminSharedKeysRepositoryProvider).getStatus();
});

class AdminSharedKeysRepository {
  AdminSharedKeysRepository(this._api);
  final ApiClient _api;

  /// `GET /api/admin-shared-keys.php` — masked key state + model counts.
  Future<SharedKeyStatus> getStatus() async {
    final data = await _api.get('/api/admin-shared-keys.php');
    if (data is! Map) {
      throw StateError('Unexpected shared-keys payload');
    }
    return SharedKeyStatus.fromJson(data.cast<String, dynamic>());
  }

  /// `POST /api/admin-shared-keys.php?action=import` — copy keys + models from
  /// the admin's own per-user settings. Returns the refreshed status.
  Future<SharedKeyStatus> import() async {
    await _api.post('/api/admin-shared-keys.php', query: {'action': 'import'});
    return getStatus();
  }

  /// `POST /api/admin-shared-keys.php { groqApiKey?, openrouterApiKey?,
  /// geminiApiKey? }` — partial save; blank fields are omitted (kept).
  Future<SharedKeyStatus> saveKeys(Map<String, String> keys) async {
    final body = <String, dynamic>{};
    keys.forEach((k, v) {
      if (v.trim().isNotEmpty) body[k] = v.trim();
    });
    if (body.isEmpty) {
      throw ArgumentError('No key fields provided');
    }
    await _api.post('/api/admin-shared-keys.php', body: body);
    return getStatus();
  }
}
