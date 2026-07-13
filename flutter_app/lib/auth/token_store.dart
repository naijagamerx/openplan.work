import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

/// Stores the device token in the OS secure enclave (Keychain on iOS, Keystore-
/// backed EncryptedSharedPreferences on Android) and offers a biometric unlock.
///
/// The master password is NEVER stored on device — only the revocable device
/// token (which the server uses to unwrap the master server-side).
class TokenStore {
  TokenStore({FlutterSecureStorage? storage, LocalAuthentication? localAuth})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            ),
        _localAuth = localAuth ?? LocalAuthentication();

  static const _tokenKey = 'device_token';
  static const _roleKey = 'user_role';

  final FlutterSecureStorage _storage;
  final LocalAuthentication _localAuth;

  // In-memory cache for the current session. Secure storage can fail/return null
  // on some Android OEM builds; caching keeps every request authenticated within
  // a session and avoids an async storage read on every API call.
  String? _cachedToken;
  String? _cachedRole;

  Future<void> saveToken(String token) async {
    _cachedToken = token;
    try {
      await _storage.write(key: _tokenKey, value: token);
    } catch (e) {
      debugPrint('[TokenStore] secure write failed (kept in memory): $e');
    }
  }

  Future<String?> readToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) return _cachedToken;
    try {
      _cachedToken = await _storage.read(key: _tokenKey);
    } catch (e) {
      debugPrint('[TokenStore] secure read failed: $e');
      _cachedToken = null;
    }
    return _cachedToken;
  }

  /// Persist the user's role ('admin' | 'user') so a cold start knows it
  /// without a re-login. Used only for UX gating; the server re-checks on
  /// every admin request.
  Future<void> saveRole(String role) async {
    _cachedRole = role;
    try {
      await _storage.write(key: _roleKey, value: role);
    } catch (e) {
      debugPrint('[TokenStore] role write failed: $e');
    }
  }

  Future<String?> readRole() async {
    if (_cachedRole != null && _cachedRole!.isNotEmpty) return _cachedRole;
    try {
      _cachedRole = await _storage.read(key: _roleKey);
    } catch (e) {
      _cachedRole = null;
    }
    return _cachedRole;
  }

  Future<void> clear() async {
    _cachedToken = null;
    _cachedRole = null;
    try {
      await _storage.delete(key: _tokenKey);
      await _storage.delete(key: _roleKey);
    } catch (e) {
      debugPrint('[TokenStore] secure delete failed: $e');
    }
  }

  Future<bool> hasToken() async => (await readToken())?.isNotEmpty ?? false;

  /// Prompt for biometric unlock. Returns true on success OR when biometrics are
  /// unavailable / error (fail-open — never lock a user out due to a sensor
  /// problem; the server-side token revocation is the real safety net).
  Future<bool> biometricUnlock() async {
    try {
      final supported =
          await _localAuth.canCheckBiometrics || await _localAuth.isDeviceSupported();
      if (!supported) return true;
      return await _localAuth.authenticate(
        localizedReason: 'Unlock OpenPlan',
        options: const AuthenticationOptions(stickyAuth: true),
      );
    } catch (_) {
      return true;
    }
  }
}
