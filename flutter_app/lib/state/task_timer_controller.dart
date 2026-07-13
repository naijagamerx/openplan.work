import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// The phase the per-task timer is in.
///
/// Mirrors the PHP stopwatch on `view-task.php` (`Mobile.viewTaskTimer`):
/// countdown mode when the task has an `estimatedMinutes`, otherwise a pure
/// stopwatch; reaching 0 on a countdown flips into [overtime] with a haptic.
enum TaskTimerMode { idle, running, paused, overtime }

/// Extension of [TaskTimerMode] with human-readable labels.
extension TaskTimerModeLabel on TaskTimerMode {
  String get label {
    switch (this) {
      case TaskTimerMode.running:
        return 'Running';
      case TaskTimerMode.paused:
        return 'Paused';
      case TaskTimerMode.overtime:
        return 'Overtime';
      case TaskTimerMode.idle:
        return 'Ready';
    }
  }
}

/// Long-lived per-task timer engine. Survives navigation (it is a non-autoDispose
/// [ChangeNotifierProvider]) and survives app restart (state is persisted to
/// SharedPreferences and re-hydrated on construction).
///
/// Modeled on [PomodoroController]: a single 1s `Timer.periodic` drives the
/// elapsed/remaining counts. `start({taskId, ...})` binds the timer to a task;
/// `stop()` returns the elapsed whole minutes so the caller can `logTime()`.
class TaskTimerController extends ChangeNotifier {
  TaskTimerController();

  Timer? _timer;

  // --- Bound task identity (null when no timer is active) ---
  String? _taskId;
  String? _projectId;
  String _taskTitle = '';
  int _estimatedMinutes = 0; // 0 → stopwatch (no upper bound)

  // --- Live state ---
  TaskTimerMode _mode = TaskTimerMode.idle;
  int _elapsedSeconds = 0; // total seconds accumulated since start
  DateTime? _startedAt; // wall-clock anchor for the current running segment

  String? get taskId => _taskId;
  String? get projectId => _projectId;
  String get taskTitle => _taskTitle;
  int get estimatedMinutes => _estimatedMinutes;
  TaskTimerMode get mode => _mode;
  DateTime? get startedAt => _startedAt;

  /// True when a timer is bound to any task (running, paused or overtime).
  bool get isActive => _mode != TaskTimerMode.idle && _taskId != null;

  /// True when the ticker is currently advancing.
  bool get isRunning => _mode == TaskTimerMode.running;

  /// Whether the bound timer is for a different task than [otherTaskId].
  bool isForDifferentTask(String? otherTaskId) =>
      isActive && otherTaskId != null && _taskId != otherTaskId;

  /// Total seconds the timer should display right now. When running we fold in
  /// the live elapsed time since [_startedAt] so the value is accurate even
  /// between ticks (and correct after a cold-start restore).
  int get displaySeconds {
    var total = _elapsedSeconds;
    if (isRunning && _startedAt != null) {
      total += DateTime.now().difference(_startedAt!).inSeconds;
    }
    return total < 0 ? 0 : total;
  }

  /// Seconds remaining for a countdown (0 when stopwatch / overtime). Negative
  /// kept internal; surfaced as 0 by callers.
  int get remainingSeconds {
    if (_estimatedMinutes <= 0) return 0;
    final r = (_estimatedMinutes * 60) - displaySeconds;
    return r < 0 ? 0 : r;
  }

  /// Whole minutes elapsed (for logging / display).
  int get elapsedMinutes => displaySeconds ~/ 60;

  /// MM:SS for the active timer. In countdown mode this shows the time left
  /// (clamped at 0); in stopwatch/overtime it shows elapsed time.
  String get timeLabel {
    final secs = _estimatedMinutes > 0 ? remainingSeconds : displaySeconds;
    return _format(secs);
  }

  /// Same as [timeLabel] but always shows elapsed (used by the floating pill).
  String get elapsedLabel => _format(displaySeconds);

  String _format(int seconds) {
    final m = (seconds.abs() ~/ 60);
    final s = (seconds.abs() % 60);
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  // ---------------------------------------------------------------------------
  // Hydration / persistence
  //
  // We persist {taskId, projectId, title, estimatedMinutes, startedAt,
  // elapsedBeforePause, mode} so a timer running before the app was killed is
  // restored on the next launch. While running we persist the wall-clock anchor
  // and recompute elapsed from it on restore (so backgrounded time counts).
  // ---------------------------------------------------------------------------

  static const _kKey = 'mobile_task_timer';
  static const _kMode = 'mode';
  static const _kTaskId = 'taskId';
  static const _kProjectId = 'projectId';
  static const _kTitle = 'title';
  static const _kEstimated = 'estimatedMinutes';
  static const _kElapsed = 'elapsedSeconds';
  static const _kStartedAt = 'startedAt';

  /// Restore any persisted running/paused timer. Call once on startup.
  Future<void> hydrate() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kKey);
    if (raw == null) return;
    try {
      final j = Map<String, dynamic>.from(
          jsonDecode(raw) as Map? ?? const {});
      final modeName = (j[_kMode] ?? '').toString();
      final mode = TaskTimerMode.values.firstWhere(
        (m) => m.name == modeName,
        orElse: () => TaskTimerMode.idle,
      );
      if (mode == TaskTimerMode.idle) {
        await _clearPersisted();
        return;
      }
      _taskId = (j[_kTaskId] ?? '').toString();
      _projectId = (j[_kProjectId] ?? '').toString();
      _projectId = _projectId!.isEmpty ? null : _projectId;
      _taskTitle = (j[_kTitle] ?? '').toString();
      _estimatedMinutes = (j[_kEstimated] as num?)?.toInt() ?? 0;
      _elapsedSeconds = (j[_kElapsed] as num?)?.toInt() ?? 0;
      final startedMs = (j[_kStartedAt] as num?)?.toInt();
      _startedAt =
          startedMs != null ? DateTime.fromMillisecondsSinceEpoch(startedMs) : null;

      if (_taskId!.isEmpty) {
        await _clearPersisted();
        return;
      }

      if (mode == TaskTimerMode.running) {
        // Was running when killed: keep counting from the persisted anchor.
        // Flip into overtime if the countdown already elapsed.
        if (_estimatedMinutes > 0 && displaySeconds >= _estimatedMinutes * 60) {
          _mode = TaskTimerMode.overtime;
        } else {
          _mode = TaskTimerMode.running;
        }
        _beginTicking();
      } else {
        // Paused: keep elapsed frozen (drop the live anchor).
        _startedAt = null;
        _mode = TaskTimerMode.paused;
      }
      notifyListeners();
    } catch (e) {
      debugPrint('[TaskTimer] hydrate failed: $e');
      await _clearPersisted();
    }
  }

  Future<void> _persist() async {
    final prefs = await SharedPreferences.getInstance();
    final elapsed = displaySeconds;
    final body = <String, dynamic>{
      _kMode: _mode.name,
      _kTaskId: _taskId ?? '',
      _kProjectId: _projectId ?? '',
      _kTitle: _taskTitle,
      _kEstimated: _estimatedMinutes,
      _kElapsed: elapsed,
      _kStartedAt: _startedAt?.millisecondsSinceEpoch,
    };
    await prefs.setString(_kKey, jsonEncode(body));
  }

  Future<void> _clearPersisted() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kKey);
  }

  // ---------------------------------------------------------------------------
  // Timer controls
  // ---------------------------------------------------------------------------

  /// Bind + start a timer for a task. If a timer is already running for another
  /// task the caller is expected to have stopped it first; this still resets to
  /// the new task regardless.
  void start({
    required String taskId,
    required String projectId,
    required String title,
    required int estimatedMinutes,
  }) {
    _timer?.cancel();
    _timer = null;
    _taskId = taskId;
    _projectId = projectId;
    _taskTitle = title;
    _estimatedMinutes = estimatedMinutes < 0 ? 0 : estimatedMinutes;
    _elapsedSeconds = 0;
    _startedAt = DateTime.now();
    _mode = TaskTimerMode.running;
    _beginTicking();
    notifyListeners();
    _persist();
  }

  void pause() {
    if (_mode != TaskTimerMode.running && _mode != TaskTimerMode.overtime) return;
    // Fold the live segment into accumulated elapsed and drop the anchor.
    if (_startedAt != null) {
      _elapsedSeconds += DateTime.now().difference(_startedAt!).inSeconds;
      _startedAt = null;
    }
    _timer?.cancel();
    _timer = null;
    _mode = TaskTimerMode.paused;
    notifyListeners();
    _persist();
  }

  void resume() {
    if (_mode != TaskTimerMode.paused) return;
    _startedAt = DateTime.now();
    _mode = TaskTimerMode.running;
    _beginTicking();
    notifyListeners();
    _persist();
  }

  /// Stop the timer and reset to idle. Returns the whole minutes to log
  /// (rounded up so a few seconds still record a minute). Clears persistence.
  int stop() {
    final minutes = (displaySeconds / 60).ceil();
    _timer?.cancel();
    _timer = null;
    _taskId = null;
    _projectId = null;
    _taskTitle = '';
    _estimatedMinutes = 0;
    _elapsedSeconds = 0;
    _startedAt = null;
    _mode = TaskTimerMode.idle;
    notifyListeners();
    _clearPersisted();
    return minutes == 0 ? 0 : minutes;
  }

  void _beginTicking() {
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => onTick());
  }

  /// Advances the timer by one second. Public so tests / external callers can
  /// drive it deterministically.
  void onTick() {
    if (_mode != TaskTimerMode.running && _mode != TaskTimerMode.overtime) return;
    if (_startedAt == null) return;

    final secs = displaySeconds;
    // Countdown boundary: flip to overtime + vibrate once.
    if (_mode == TaskTimerMode.running &&
        _estimatedMinutes > 0 &&
        secs >= _estimatedMinutes * 60) {
      _mode = TaskTimerMode.overtime;
      HapticFeedback.vibrate();
    }
    notifyListeners();
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}

/// Non-autoDispose so the timer survives screen changes (mirrors
/// [pomodoroControllerProvider]). The controller is cheap (a single periodic
/// timer) and naturally process-scoped.
final taskTimerControllerProvider =
    ChangeNotifierProvider<TaskTimerController>((ref) {
  final c = TaskTimerController();
  c.hydrate();
  ref.onDispose(c.dispose);
  return c;
});
