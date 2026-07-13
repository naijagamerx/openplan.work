import '../api/api_client.dart';
import 'token_store.dart';

/// Talks to the Phase-0 device-token endpoint and persists the token.
class AuthRepository {
  AuthRepository(this._api, this._tokenStore);

  final ApiClient _api;
  final TokenStore _tokenStore;

  /// POST /api/auth.php?action=device_login.
  /// Returns the user map on success; persists the device token. No CSRF needed
  /// (token bootstrap — there is no ambient cookie).
  Future<Map<String, dynamic>> deviceLogin({
    required String email,
    required String password,
    required String masterPassword,
    required String deviceName,
    required String platform,
  }) async {
    final data = await _api.post(
      '/api/auth.php',
      query: {'action': 'device_login'},
      body: {
        'email': email,
        'password': password,
        'master_password': masterPassword,
        'device_name': deviceName,
        'platform': platform,
      },
    );

    final token = (data is Map ? data['device_token'] : null) as String?;
    if (token == null || token.isEmpty) {
      throw Exception('Login succeeded but no device token was returned');
    }
    await _tokenStore.saveToken(token);
    return ((data as Map)['user'] as Map).cast<String, dynamic>();
  }

  Future<void> logout() => _tokenStore.clear();

  Future<bool> isLoggedIn() => _tokenStore.hasToken();

  /// POST /api/auth.php?action=change_password.
  /// Device-token auth bypasses CSRF, so no token field is sent.
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await _api.post('/api/auth.php', query: {'action': 'change_password'}, body: {
      'current_password': currentPassword,
      'new_password': newPassword,
    });
  }

  /// POST /api/auth.php?action=change_master_password.
  /// Re-encrypts all data; the server typically forces a re-login after.
  Future<void> changeMasterPassword({
    required String currentMasterPassword,
    required String newMasterPassword,
  }) async {
    await _api.post('/api/auth.php', query: {'action': 'change_master_password'},
        body: {
          'current_master_password': currentMasterPassword,
          'new_master_password': newMasterPassword,
        });
  }
}
