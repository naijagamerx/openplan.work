import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/task.dart';
import 'tasks_repository.dart';

/// The kind of derived alert (mirrors the PHP `App.notificationCenter` rules in
/// assets/js/app.js:1127 — OVERDUE / DUE_SOON / NOT_STARTED from dueDate+status).
enum NotificationType { overdue, dueToday, notStarted }

/// Extension of [NotificationType] with display labels + severity ordering.
extension NotificationTypeMeta on NotificationType {
  String get label {
    switch (this) {
      case NotificationType.overdue:
        return 'Overdue';
      case NotificationType.dueToday:
        return 'Due today';
      case NotificationType.notStarted:
        return 'Not started';
    }
  }

  /// Higher = more urgent. Used to sort the panel (most urgent first).
  int get severity {
    switch (this) {
      case NotificationType.overdue:
        return 3;
      case NotificationType.dueToday:
        return 2;
      case NotificationType.notStarted:
        return 1;
    }
  }
}

/// A single derived alert: the offending task + its type/severity.
class AppNotification {
  const AppNotification({required this.task, required this.type});
  final Task task;
  final NotificationType type;

  int get severity => type.severity;
}

/// In-app task alerts derived from [allTasksProvider] (the flat list of every
/// task across all projects). Rules (PHP parity):
///  - dueDate < today            → OVERDUE   (only incomplete tasks)
///  - dueDate == today           → DUE_TODAY (only incomplete tasks)
///  - status == 'backlog' & no dueDate → NOT_STARTED
///
/// Excludes completed tasks. Refreshed by invalidating on screen focus (see the
/// bell widget / AppScaffold resume hook); the PHP app polls every 60s, which
/// we approximate cheaply with re-fetch-on-resume.
final notificationsProvider =
    FutureProvider.autoDispose<List<AppNotification>>((ref) async {
  final tasks = await ref.watch(allTasksProvider.future);
  return _derive(tasks);
});

List<AppNotification> _derive(List<Task> tasks) {
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final out = <AppNotification>[];
  for (final t in tasks) {
    if (t.done) continue;
    if (t.dueDate != null) {
      final due = DateTime(t.dueDate!.year, t.dueDate!.month, t.dueDate!.day);
      if (due.isBefore(today)) {
        out.add(AppNotification(task: t, type: NotificationType.overdue));
        continue;
      }
      if (due.isAtSameMomentAs(today)) {
        out.add(AppNotification(task: t, type: NotificationType.dueToday));
        continue;
      }
    } else if (t.status == 'backlog') {
      out.add(AppNotification(task: t, type: NotificationType.notStarted));
    }
  }
  out.sort((a, b) => b.severity.compareTo(a.severity));
  return out;
}
