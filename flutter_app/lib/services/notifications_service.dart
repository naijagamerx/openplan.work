import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/data/latest.dart' as tzdata;
import 'package:timezone/timezone.dart' as tz;

import '../models/habit.dart';

/// Process-scoped singleton exposed to the widget tree.
final notificationsServiceProvider =
    Provider<NotificationsService>((ref) => NotificationsService.instance);

/// The most recent pending route requested by a notification tap, consumed by
/// the app on the next frame (handles the cold-start case where the router
/// isn't ready when the tap fires).
String? pendingNotificationRoute;

/// Thin wrapper around `flutter_local_notifications` for scheduling OS-level
/// daily habit reminders. Mirrors the PHP `Mobile.habits` module (mobile.js
/// :658-780): each active habit with a `reminderTime` gets a daily local
/// notification at that wall-clock time.
///
/// All methods are best-effort: failures are caught and logged so a
/// notification problem can never crash habit loading.
class NotificationsService {
  NotificationsService._();
  static final NotificationsService instance = NotificationsService._();

  final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();

  static const String _channelId = 'habit_reminders';
  static const String _channelName = 'Habit reminders';
  static const String _channelDesc =
      'Daily reminders to complete your habits.';

  bool _initialized = false;

  /// Initialize the plugin, the timezone database, the Android channel, and
  /// request iOS permission. Safe to call multiple times (no-ops after the
  /// first success). Returns true if ready to schedule.
  Future<bool> init() async {
    if (_initialized) return true;
    try {
      // 1. Timezone db + local zone (needed by zonedSchedule).
      tzdata.initializeTimeZones();
      try {
        // Detect the device's IANA timezone without a dedicated plugin (the
        // flutter_timezone plugin uses the removed v1 embedding on this
        // Flutter version). We map the current UTC offset to the most likely
        // common zone; scheduling falls back to UTC if this fails.
        final offset = DateTime.now().timeZoneOffset;
        final name = _guessTimeZone(offset);
        tz.setLocalLocation(tz.getLocation(name));
      } catch (e) {
        debugPrint('[Notifications] timezone resolve failed: $e');
      }

      // 2. Platform init settings.
      const android = AndroidInitializationSettings('@mipmap/ic_launcher');
      const darwin = DarwinInitializationSettings(
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      );
      const settings = InitializationSettings(
        android: android,
        iOS: darwin,
        macOS: darwin,
      );

      await _plugin.initialize(
        settings,
        onDidReceiveNotificationResponse: _onTap,
      );

      // 3. iOS/macOS permission (Android grants on install).
      if (defaultTargetPlatform == TargetPlatform.iOS ||
          defaultTargetPlatform == TargetPlatform.macOS) {
        try {
          await _plugin
              .resolvePlatformSpecificImplementation<
                  IOSFlutterLocalNotificationsPlugin>()
              ?.requestPermissions(alert: true, badge: true, sound: true);
        } catch (_) {
          // best-effort
        }
      }

      _initialized = true;
      return true;
    } catch (e) {
      debugPrint('[Notifications] init failed: $e');
      return false;
    }
  }

  /// Called when the user taps a delivered notification. Routes the app to the
  /// screen named in the notification payload (e.g. "/habits/:id", "/water").
  /// On cold start the router may not be ready yet — in that case the route is
  /// stashed in [pendingNotificationRoute] and consumed once the app is up.
  static void _onTap(NotificationResponse response) {
    final payload = response.payload;
    debugPrint('[Notifications] tapped: ${response.id} payload=$payload');
    if (payload == null || payload.isEmpty) return;
    // Stash the route; the foreground tap is handled by a top-level listener in
    // main.dart (via ProviderScope) when the app is already running, and the
    // cold-start case is consumed in OpenPlanApp.initState.
    pendingNotificationRoute = payload;
    _onTapController?.call(payload);
  }

  /// Optional foreground callback set by the app shell so a tap navigates
  /// immediately while the app is in the foreground.
  static void Function(String route)? _onTapController;
  static void registerTapHandler(void Function(String route)? handler) {
    _onTapController = handler;
  }

  NotificationDetails _details() => const NotificationDetails(
        android: AndroidNotificationDetails(
          _channelId,
          _channelName,
          channelDescription: _channelDesc,
          importance: Importance.defaultImportance,
          priority: Priority.defaultPriority,
        ),
        iOS: DarwinNotificationDetails(),
        macOS: DarwinNotificationDetails(),
      );

  /// (Re)schedule a daily local notification for every active habit that has a
  /// `reminderTime` ("HH:mm"). Cancels all previously-scheduled habit
 /// reminders first so this is idempotent.
  ///
  /// Habit id is hashed into the 32-bit notification id space.
  Future<void> scheduleHabitReminders(List<Habit> habits) async {
    if (!await init()) return;
    try {
      await cancelAll();
      final details = _details();
      final now = tz.TZDateTime.now(tz.local);
      for (final h in habits) {
        if (h.archived || !h.active) continue;
        final time = h.reminderTime;
        final when = _parseTime(time);
        if (when == null) continue;

        var scheduled = tz.TZDateTime(
          tz.local,
          now.year,
          now.month,
          now.day,
          when.hour,
          when.minute,
        );
        // If today's slot already passed, start tomorrow.
        if (!scheduled.isAfter(now)) {
          scheduled = scheduled.add(const Duration(days: 1));
        }

        await _plugin.zonedSchedule(
          _idFor(h.id),
          'Habit reminder',
          h.name,
          scheduled,
          details,
          androidScheduleMode: AndroidScheduleMode.inexactAllowWhileIdle,
          uiLocalNotificationDateInterpretation:
              UILocalNotificationDateInterpretation.absoluteTime,
          matchDateTimeComponents: DateTimeComponents.time, // → daily repeat
          payload: '/habits/${h.id}', // tap → habit detail
        );
      }
    } catch (e) {
      debugPrint('[Notifications] scheduleHabitReminders failed: $e');
    }
  }

  /// Cancel every scheduled habit reminder.
  Future<void> cancelAll() async {
    if (!await init()) return;
    try {
      await _plugin.cancelAll();
    } catch (e) {
      debugPrint('[Notifications] cancelAll failed: $e');
    }
  }

  /// Parse an "HH:mm" (or "HH:mm:ss") reminder string into hour/minute.
  DateTime? _parseTime(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    final parts = raw.trim().split(':');
    if (parts.isEmpty) return null;
    final hour = int.tryParse(parts[0]);
    final minute = parts.length > 1 ? int.tryParse(parts[1]) ?? 0 : 0;
    if (hour == null || hour < 0 || hour > 23 || minute < 0 || minute > 59) {
      return null;
    }
    // Return as a DateTime purely to carry hour/minute.
    return DateTime(2000, 1, 1, hour, minute);
  }

  /// Stable 32-bit notification id derived from a habit id string.
  int _idFor(String id) {
    var hash = 0;
    for (final c in id.codeUnits) {
      hash = (hash * 31 + c) & 0x7FFFFFFF;
    }
    return hash;
  }

  /// Best-effort mapping of a Duration UTC offset to a common IANA zone name.
  /// Used only to schedule local notifications at the right wall-clock time;
  /// an imperfect guess still fires the reminder at roughly the right hour.
  String _guessTimeZone(Duration offset) {
    final minutes = offset.inMinutes;
    const byOffset = <int, String>{
      -600: 'Pacific/Honolulu',
      -480: 'America/Anchorage',
      -420: 'America/Los_Angeles',
      -360: 'America/Denver',
      -300: 'America/Chicago',
      -240: 'America/New_York',
      -180: 'America/Halifax',
      0: 'Europe/London',
      60: 'Europe/Paris',
      120: 'Europe/Athens',
      180: 'Europe/Moscow',
      330: 'Asia/Kolkata',
      480: 'Asia/Shanghai',
      540: 'Asia/Tokyo',
      600: 'Australia/Sydney',
    };
    return byOffset[minutes] ?? 'UTC';
  }
}
