import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import 'auth_repository.dart';
import 'token_store.dart';

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

/// Display name of the signed-in user (set at login; null after a cold start
/// until next login). Used for the dashboard greeting.
final userNameProvider = StateProvider<String?>((ref) => null);

/// Role of the signed-in user ('admin' | 'user'). Set at login; hydrated from
/// secure storage on cold start. Used ONLY for UX gating of admin UI — the
/// server re-checks role on every admin request.
final userRoleProvider = StateProvider<String?>((ref) => null);

/// Convenience: true when the current user is an admin.
final isAdminProvider = Provider<bool>(
    (ref) => ref.watch(userRoleProvider) == 'admin');

final apiClientProvider =
    Provider<ApiClient>((ref) => ApiClient(ref.watch(tokenStoreProvider)));

final authRepositoryProvider = Provider<AuthRepository>((ref) =>
    AuthRepository(ref.watch(apiClientProvider), ref.watch(tokenStoreProvider)));

/// Auth state for the whole app. `true` = signed in. The router watches this.
final authStateProvider =
    AsyncNotifierProvider<AuthController, bool>(AuthController.new);

class AuthController extends AsyncNotifier<bool> {
  @override
  Future<bool> build() => _resolveStartupAuth();

  /// Decide the auth state when the app cold-starts.
  ///
  /// ─── CONTRIBUTION POINT (security vs UX) ──────────────────────────────────
  /// A device token already exists in secure storage. Choose the policy:
  ///   (a) require a biometric unlock before the session is considered valid —
  ///       a thief with an unlocked phone still cannot open the app, but every
  ///       launch costs a Face ID / fingerprint prompt; OR
  ///   (b) trust the stored token and go straight in — smoother, fewer prompts,
  ///       relies on the OS lock screen + server-side revocation for safety.
  ///
  /// `ref.read(tokenStoreProvider).biometricUnlock()` returns true on success or
  /// when biometrics are unavailable. Implement your choice below.
  ///
  /// CHOSEN: policy (a) — biometric gate. When a stored token exists we require a
  /// Face ID / fingerprint unlock before the session is treated as valid. A thief
  /// with an unlocked phone still cannot open the app. `biometricUnlock()` is
  /// fail-open (returns true when no biometric hardware is available), so a sensor
  /// problem never hard-locks the user out — server-side token revocation remains
  /// the real safety net.
  ///
  /// Requires native config (see flutter_app/MAC_SETUP.md "Biometric setup"):
  ///   - Android: MainActivity extends FlutterFragmentActivity + USE_BIOMETRIC perm
  ///   - iOS: NSFaceIDUsageDescription in Info.plist
  Future<bool> _resolveStartupAuth() async {
    final tokenStore = ref.read(tokenStoreProvider);
    if (!await tokenStore.hasToken()) return false;
    // Hydrate the role so admin UI gating works on cold start.
    ref.read(userRoleProvider.notifier).state = await tokenStore.readRole();
    return tokenStore.biometricUnlock();
  }

  Future<void> login({
    required String email,
    required String password,
    required String masterPassword,
    required String deviceName,
    required String platform,
  }) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final user = await ref.read(authRepositoryProvider).deviceLogin(
            email: email,
            password: password,
            masterPassword: masterPassword,
            deviceName: deviceName,
            platform: platform,
          );
      ref.read(userNameProvider.notifier).state = user['name'] as String?;
      final role = (user['role'] as String?) ?? 'user';
      ref.read(userRoleProvider.notifier).state = role;
      await ref.read(tokenStoreProvider).saveRole(role);
      return true;
    });
  }

  Future<void> logout() async {
    await ref.read(authRepositoryProvider).logout();
    ref.read(userNameProvider.notifier).state = null;
    ref.read(userRoleProvider.notifier).state = null;
    state = const AsyncData(false);
  }
}
