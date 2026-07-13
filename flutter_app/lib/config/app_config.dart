/// Environment + base-URL config for the API client.
///
/// Pick the right base URL for where the app runs (override at build time with
/// `flutter run --dart-define=API_BASE=...`):
///   - Android emulator -> --dart-define=API_BASE=http://10.0.2.2/taskmanager
///     (10.0.2.2 = host loopback, only valid inside an emulator)
///   - iOS simulator    -> --dart-define=API_BASE=http://localhost/taskmanager
///   - Physical device  -> --dart-define=API_BASE=http://<your-LAN-ip>/taskmanager
///
/// The default points at production so physical-device builds work out of the
/// box. `10.0.2.2` was the previous default and is unreachable from a real
/// phone, which produced `ApiException(null): connection took longer than
/// 0:00:15` on every request.
class AppConfig {
  AppConfig._();

  /// Build-time override: --dart-define=API_BASE=http://192.168.1.10/taskmanager
  static const String _override = String.fromEnvironment('API_BASE');

  static String get baseUrl {
    if (_override.isNotEmpty) return _override;
    // Default = production. Matches the Android WebView app
    // (android/.../MainActivity.java APP_URL). Use --dart-define=API_BASE for
    // local dev against MAMP/an emulator.
    return 'https://openplan.work';
  }

  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 20);
}
