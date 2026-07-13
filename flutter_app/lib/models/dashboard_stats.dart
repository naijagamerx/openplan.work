import 'habit.dart';
import 'project.dart';
import 'task.dart';

/// Aggregated dashboard metrics, computed client-side to mirror the PHP logic in
/// mobile/views/dashboard.php (lines 106-184).
class DashboardStats {
  DashboardStats({
    required this.pendingTasks,
    required this.completedTasks,
    required this.activeProjects,
    required this.totalProjects,
    required this.habitsToday,
    required this.totalHabits,
    required this.tasksThisWeek,
    required this.completedThisWeek,
    required this.recentTasks,
  });

  final int pendingTasks;
  final int completedTasks;
  final int activeProjects;
  final int totalProjects;
  final int habitsToday;
  final int totalHabits;
  final int tasksThisWeek; // created + completed in the last 7 days
  final int completedThisWeek;
  final List<Task> recentTasks;

  double get habitsProgress => totalHabits > 0 ? habitsToday / totalHabits : 0;

  static const _priorityOrder = {'urgent': 0, 'high': 1, 'medium': 2, 'low': 3};

  factory DashboardStats.compute({
    required List<Project> projects,
    required List<Habit> habits,
    DateTime? now,
  }) {
    final today = now ?? DateTime.now();
    final weekAgo = today.subtract(const Duration(days: 7));
    bool within(DateTime? d) => d != null && d.isAfter(weekAgo);

    final allTasks = projects.expand((p) => p.tasks).toList();
    final pending = allTasks.where((t) => !t.done).length;
    final completed = allTasks.where((t) => t.done).length;

    final activeProjects =
        projects.where((p) => p.status.toLowerCase() == 'active').length;

    final activeHabits = habits.where((h) => !h.archived).toList();
    final habitsToday = activeHabits.where((h) => h.todayCompleted).length;

    final createdThisWeek = allTasks.where((t) => within(t.createdAt)).length;
    final completedThisWeek = allTasks.where((t) => within(t.completedAt)).length;

    final recent = allTasks.where((t) => !t.done).toList()
      ..sort((a, b) => (_priorityOrder[a.priority] ?? 2)
          .compareTo(_priorityOrder[b.priority] ?? 2));

    return DashboardStats(
      pendingTasks: pending,
      completedTasks: completed,
      activeProjects: activeProjects,
      totalProjects: projects.length,
      habitsToday: habitsToday,
      totalHabits: activeHabits.length,
      tasksThisWeek: createdThisWeek + completedThisWeek,
      completedThisWeek: completedThisWeek,
      recentTasks: recent.take(5).toList(),
    );
  }
}
