import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../repositories/pomodoro_repository.dart';

/// The phase the timer is currently in.
enum PomodoroMode { idle, focus, paused, focusBreak }

/// Extension of [PomodoroMode] with human-readable labels.
extension PomodoroModeLabel on PomodoroMode {
  String get label {
    switch (this) {
      case PomodoroMode.focus:
        return 'Focus in progress';
      case PomodoroMode.paused:
        return 'Paused';
      case PomodoroMode.focusBreak:
        return 'Break';
      case PomodoroMode.idle:
        return 'Ready';
    }
  }
}

/// Long-lived Pomodoro engine. Survives screen changes (it's a provider, not
/// tied to a widget): the running countdown persists across navigation, daily
/// completion count is persisted to SharedPreferences and resets at midnight,
/// and a completed focus session is recorded via the API and auto-switches into
/// a break.
///
/// Modeled as a [ChangeNotifier] exposed through [pomodoroControllerProvider]
/// (autoDispose: false so the timer keeps running when the screen is closed).
class PomodoroController extends ChangeNotifier {
  PomodoroController(this._repo);

  final PomodoroRepository _repo;
  Timer? _timer;

  // --- Tunable settings (persisted) ---
  int _focusMinutes = 25;
  int _breakMinutes = 5;

  // --- Live state ---
  PomodoroMode _mode = PomodoroMode.idle;
  int _remainingSeconds = 25 * 60;
  int _completedToday = 0;
  int _totalFocusMinutes = 0;
  int _currentSession = 0; // sessions completed in this continuous run

  int get focusMinutes => _focusMinutes;
  int get breakMinutes => _breakMinutes;
  PomodoroMode get mode => _mode;
  int get remainingSeconds => _remainingSeconds;
  int get completedToday => _completedToday;
  int get totalFocusMinutes => _totalFocusMinutes;
  int get currentSession => _currentSession;

  /// Total seconds for the active phase (focus or break).
  int get totalSeconds =>
      (_mode == PomodoroMode.focusBreak ? _breakMinutes : _focusMinutes) * 60;

  /// Ring fill 0..1 for the active phase.
  double get progress {
    final total = totalSeconds;
    if (total <= 0) return 0;
    return 1 - (_remainingSeconds / total);
  }

  /// Whether the timer ticker is currently advancing.
  bool get isRunning => _mode == PomodoroMode.focus || _mode == PomodoroMode.focusBreak;

  /// MM:SS for the remaining time.
  String get remainingLabel {
    final m = (_remainingSeconds.abs() ~/ 60);
    final s = (_remainingSeconds.abs() % 60);
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  // ---------------------------------------------------------------------------
  // Hydration / persistence
  // ---------------------------------------------------------------------------

  static const _kFocus = 'pomodoro_focus_minutes';
  static const _kBreak = 'pomodoro_break_minutes';
  static const _kCompleted = 'pomodoro_completed_today';
  static const _kDate = 'pomodoro_count_date';

  /// Restore persisted settings + daily count. Call once on startup. The daily
  /// count resets when the stored date no longer matches today.
  Future<void> hydrate() async {
    final prefs = await SharedPreferences.getInstance();
    _focusMinutes = prefs.getInt(_kFocus) ?? 25;
    _breakMinutes = prefs.getInt(_kBreak) ?? 5;

    final today = _todayKey();
    final storedDate = prefs.getString(_kDate);
    if (storedDate == today) {
      _completedToday = prefs.getInt(_kCompleted) ?? 0;
    } else {
      _completedToday = 0;
      await prefs.setInt(_kCompleted, 0);
      await prefs.setString(_kDate, today);
    }

    if (_mode == PomodoroMode.idle) {
      _remainingSeconds = _focusMinutes * 60;
    }
    notifyListeners();
  }

  Future<void> _persistSettings() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_kFocus, _focusMinutes);
    await prefs.setInt(_kBreak, _breakMinutes);
  }

  Future<void> _persistDailyCount() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_kCompleted, _completedToday);
    await prefs.setString(_kDate, _todayKey());
  }

  String _todayKey() {
    final n = DateTime.now();
    return '${n.year}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
  }

  // ---------------------------------------------------------------------------
  // Settings / presets
  // ---------------------------------------------------------------------------

  /// Apply a focus+break preset (e.g. 25/5, 15/3, 5/1). No-op while running.
  void setPreset({required int focus, required int breakLength}) {
    if (isRunning) return;
    _focusMinutes = focus.clamp(1, 180);
    _breakMinutes = breakLength.clamp(1, 60);
    _remainingSeconds = _focusMinutes * 60;
    notifyListeners();
    _persistSettings();
  }

  // ---------------------------------------------------------------------------
  // Timer controls
  // ---------------------------------------------------------------------------

  /// Begin a focus countdown from the configured focus length.
  void start() {
    if (isRunning) return;
    if (_mode != PomodoroMode.paused) {
      _remainingSeconds = _focusMinutes * 60;
    }
    _mode = PomodoroMode.focus;
    _beginTicking();
    notifyListeners();
  }

  void pause() {
    if (!isRunning) return;
    _timer?.cancel();
    _timer = null;
    _mode = PomodoroMode.paused;
    notifyListeners();
  }

  /// Resume after a pause (continues the focus phase).
  void resume() {
    if (_mode != PomodoroMode.paused) return;
    _mode = PomodoroMode.focus;
    _beginTicking();
    notifyListeners();
  }

  /// Stop and reset to a fresh focus countdown.
  void reset() {
    _timer?.cancel();
    _timer = null;
    _mode = PomodoroMode.idle;
    _currentSession = 0;
    _remainingSeconds = _focusMinutes * 60;
    notifyListeners();
  }

  void _beginTicking() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _onTick());
  }

  void _onTick() {
    if (_remainingSeconds <= 1) {
      _remainingSeconds = 0;
      _completePhase();
      return;
    }
    _remainingSeconds--;
    notifyListeners();
  }

  /// Phase ended: if it was focus, record the session + auto-switch to break;
  /// if it was a break, return to ready for the next focus.
  Future<void> _completePhase() async {
    _timer?.cancel();
    _timer = null;

    if (_mode == PomodoroMode.focus) {
      _completedToday++;
      _currentSession++;
      _totalFocusMinutes += _focusMinutes;
      await _persistDailyCount();
      notifyListeners();

      // Record the session server-side (fire and forget; surface errors in log).
      try {
        await _repo.complete(
          focusMinutes: _focusMinutes,
          breakMinutes: _breakMinutes,
        );
      } catch (e) {
        debugPrint('[Pomodoro] complete() failed: $e');
      }

      // Auto-switch into the break phase.
      _mode = PomodoroMode.focusBreak;
      _remainingSeconds = _breakMinutes * 60;
      notifyListeners();
      _beginTicking();
    } else if (_mode == PomodoroMode.focusBreak) {
      _mode = PomodoroMode.idle;
      _remainingSeconds = _focusMinutes * 60;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}

/// AutoDispose disabled so the timer survives screen changes; the controller is
/// cheap (a single periodic timer) and is naturally process-scoped.
final pomodoroControllerProvider =
    ChangeNotifierProvider<PomodoroController>((ref) {
  final c = PomodoroController(ref.watch(pomodoroRepositoryProvider));
  c.hydrate();
  ref.onDispose(c.dispose);
  return c;
});
