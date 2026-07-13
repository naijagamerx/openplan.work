import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// App-wide theme mode (System / Light / Dark), toggled from Settings.
///
/// Persists the choice to `shared_preferences` so it survives a cold start.
/// The provider stays a synchronous [ThemeMode] so [MaterialApp] can read it
/// directly on the first frame; the persisted value is hydrated eagerly in
/// [initTheme] (called once from `main` before runApp, when available) and any
/// later preference load updates it.
final themeModeProvider =
    StateProvider<ThemeMode>((ref) => _bootMode);

/// Shared-preference key holding one of: "system", "light", "dark".
const _kThemeModeKey = 'app_theme_mode';

/// The mode used before preferences have been read. Defaults to System.
ThemeMode _bootMode = ThemeMode.system;

/// True once we've read the persisted value at least once, so we don't
/// overwrite a restored choice with the default on a slow hydrate.
bool _hydrated = false;

/// Hydrate the persisted theme mode before the first frame, if a preference
/// instance is available. Safe to call multiple times; no-op after the first
/// successful read.
Future<void> initTheme() async {
  if (_hydrated) return;
  final prefs = await SharedPreferences.getInstance();
  _bootMode = _decode(prefs.getString(_kThemeModeKey));
  _hydrated = true;
}

/// Persist a new theme choice. Called from the settings UI on change.
Future<void> _persist(ThemeMode mode) async {
  final prefs = await SharedPreferences.getInstance();
  await prefs.setString(_kThemeModeKey, _encode(mode));
}

ThemeMode _decode(String? raw) {
  switch (raw) {
    case 'light':
      return ThemeMode.light;
    case 'dark':
      return ThemeMode.dark;
    case 'system':
    default:
      return ThemeMode.system;
  }
}

String _encode(ThemeMode mode) {
  switch (mode) {
    case ThemeMode.light:
      return 'light';
    case ThemeMode.dark:
      return 'dark';
    case ThemeMode.system:
      return 'system';
  }
}

/// Sets the current theme mode and persists it. Use this from the UI:
/// `ref.read(themeModeProvider.notifier).state = m` continues to work for the
/// in-memory change, but persists when you also call this.
extension PersistedThemeMode on StateController<ThemeMode> {
  void setAndPersist(ThemeMode mode) {
    state = mode;
    _persist(mode);
  }
}
