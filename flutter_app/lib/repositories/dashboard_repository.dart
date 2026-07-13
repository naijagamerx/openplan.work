import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/dashboard_stats.dart';
import '../models/habit.dart';
import '../models/project.dart';

final dashboardRepositoryProvider = Provider<DashboardRepository>(
  (ref) => DashboardRepository(ref.watch(apiClientProvider)),
);

final dashboardProvider = FutureProvider<DashboardStats>(
  (ref) => ref.watch(dashboardRepositoryProvider).load(),
);

class DashboardRepository {
  DashboardRepository(this._api);

  final ApiClient _api;

  Future<DashboardStats> load() async {
    // Projects (with embedded tasks) are required — let errors propagate.
    // api/projects.php returns the full projects collection with tasks[] embedded
    // (same source the PHP dashboard reads); api/tasks.php returns a FLAT task list.
    final projectsData = await _api.get('/api/projects.php');
    final projects = ((projectsData as List?) ?? const [])
        .whereType<Map>()
        .map((p) => Project.fromJson(p.cast<String, dynamic>()))
        .toList();

    // Habits are best-effort: the dashboard still renders task/project stats
    // even if the habits call fails.
    List<Habit> habits = const [];
    try {
      final habitsData = await _api.get('/api/habits.php');
      habits = ((habitsData as List?) ?? const [])
          .whereType<Map>()
          .map((h) => Habit.fromJson(h.cast<String, dynamic>()))
          .toList();
    } catch (_) {
      // ignore — habitsToday will read 0/0
    }

    return DashboardStats.compute(projects: projects, habits: habits);
  }
}
