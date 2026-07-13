import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/project.dart';
import '../models/task.dart';

final tasksRepositoryProvider =
    Provider<TasksRepository>((ref) => TasksRepository(ref.watch(apiClientProvider)));

/// All projects (with embedded tasks). Auto-refreshable from the UI.
final projectsProvider =
    FutureProvider<List<Project>>((ref) => ref.watch(tasksRepositoryProvider).fetchProjects());

/// Flat list of every task across all projects, each tagged with its project
/// name. Convenient for the flat tasks list / dashboard recent-tasks.
final allTasksProvider = FutureProvider<List<Task>>((ref) async {
  final projects = await ref.watch(projectsProvider.future);
  return [
    for (final p in projects)
      for (final t in p.tasks)
        Task(
          id: t.id,
          title: t.title,
          status: t.status,
          priority: t.priority,
          done: t.done,
          description: t.description,
          subtasks: t.subtasks,
          dueDate: t.dueDate,
          startDate: t.startDate,
          estimatedMinutes: t.estimatedMinutes,
          actualMinutes: t.actualMinutes,
          createdAt: t.createdAt,
          completedAt: t.completedAt,
          updatedAt: t.updatedAt,
          projectId: p.id,
          projectName: p.name,
        ),
  ];
});

class TasksRepository {
  TasksRepository(this._api);

  final ApiClient _api;

  Future<List<Project>> fetchProjects() async {
    // Projects (with embedded tasks) come from projects.php, not tasks.php (flat list).
    final data = await _api.get('/api/projects.php');
    final list = (data as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((p) => Project.fromJson(p.cast<String, dynamic>()))
        .toList();
  }

  /// Find a single task by id across the loaded projects. Returns the task
  /// plus its projectId; null if not found.
  Future<({Task task, String projectId})?> fetchTask(
      String taskId) async {
    final projects = await fetchProjects();
    for (final p in projects) {
      for (final t in p.tasks) {
        if (t.id == taskId) {
          return (task: t, projectId: p.id);
        }
      }
    }
    return null;
  }

  /// Create a task under a project.
  Future<void> createTask({
    required String projectId,
    required String title,
    String description = '',
    String status = 'todo',
    String priority = 'medium',
    DateTime? dueDate,
    DateTime? startDate,
    int estimatedMinutes = 0,
    List<Subtask> subtasks = const [],
  }) async {
    await _api.post('/api/tasks.php', query: {'action': 'add'}, body: {
      'projectId': projectId,
      'title': title,
      if (description.isNotEmpty) 'description': description,
      'status': status,
      'priority': priority,
      if (dueDate != null) 'dueDate': dueDate.toUtc().toIso8601String(),
      if (startDate != null) 'startDate': startDate.toUtc().toIso8601String(),
      'estimatedMinutes': estimatedMinutes,
      'subtasks': subtasks.map((s) => s.toBody()).toList(),
    });
  }

  /// Update an existing task. Pass only the fields being changed.
  Future<void> updateTask({
    required String taskId,
    required String projectId,
    String? title,
    String? description,
    String? status,
    String? priority,
    DateTime? dueDate,
    DateTime? startDate,
    int? estimatedMinutes,
    int? actualMinutes,
  }) async {
    await _api.put('/api/tasks.php', query: {'id': taskId}, body: {
      if (projectId.isNotEmpty) 'projectId': projectId,
      if (title != null) 'title': title,
      if (description != null) 'description': description,
      if (status != null) 'status': status,
      if (priority != null) 'priority': priority,
      if (dueDate != null) 'dueDate': dueDate.toUtc().toIso8601String(),
      if (startDate != null) 'startDate': startDate.toUtc().toIso8601String(),
      if (estimatedMinutes != null) 'estimatedMinutes': estimatedMinutes,
      if (actualMinutes != null) 'actualMinutes': actualMinutes,
    });
  }

  /// Toggle completion by flipping the status between done and the first
  /// non-done status. Mirrors mobile/views/tasks.php toggleComplete.
  Future<void> toggleComplete({
    required String taskId,
    required String projectId,
    required bool done,
  }) async {
    await updateTask(
      taskId: taskId,
      projectId: projectId,
      status: done ? 'done' : 'todo',
    );
  }

  /// Add a subtask to an existing task.
  Future<void> addSubtask({
    required String taskId,
    required String projectId,
    required String title,
    int estimatedMinutes = 0,
  }) async {
    await _api.post('/api/tasks.php', query: {
      'action': 'subtask',
      'id': taskId,
      'projectId': projectId,
    }, body: {
      'title': title,
      'estimatedMinutes': estimatedMinutes,
    });
  }

  /// Update a subtask's completed state by index (backend locates by index
  /// in the task's subtask array). See TasksAPI::updateSubtask.
  Future<void> setSubtaskComplete({
    required String taskId,
    required String projectId,
    required int index,
    required bool completed,
  }) async {
    await _api.put('/api/tasks.php', query: {
      'action': 'subtask',
      'id': taskId,
      'projectId': projectId,
      'index': index.toString(),
    }, body: {
      'completed': completed,
    });
  }

  /// Append a time entry (minutes) to the task's timeEntries log.
  Future<void> logTime({
    required String taskId,
    required String projectId,
    required int minutes,
    String description = '',
  }) async {
    await _api.post('/api/tasks.php', query: {
      'action': 'log_time',
      'id': taskId,
      'projectId': projectId,
    }, body: {
      'minutes': minutes,
      if (description.isNotEmpty) 'description': description,
    });
  }

  /// Delete a task (and its subtasks) from a project.
  Future<void> deleteTask({required String taskId, required String projectId}) async {
    await _api.delete('/api/tasks.php',
        query: {'id': taskId, 'projectId': projectId});
  }
}
