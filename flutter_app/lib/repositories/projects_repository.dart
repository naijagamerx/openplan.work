import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/project.dart';

/// Provider for project CRUD operations (create/update/delete). Listing is
/// handled separately by [projectsProvider] in tasks_repository.dart.
final projectsCrudProvider =
    Provider<ProjectsRepository>((ref) => ProjectsRepository(ref.watch(apiClientProvider)));

/// Project mutations against /api/projects.php (mirrors mobile/views/project-form.php).
class ProjectsRepository {
  ProjectsRepository(this._api);

  final ApiClient _api;

  /// Fetch a single project by id (GET /api/projects.php?id={id}).
  /// Returns null if not found.
  Future<Project?> fetchProject(String id) async {
    final data = await _api.get('/api/projects.php', query: {'id': id});
    if (data is! Map) return null;
    return Project.fromJson(data.cast<String, dynamic>());
  }

  /// Create a new project. Backend: POST /api/projects.php?action=add
  /// Returns the created project's id (from the response data).
  Future<String> create({
    required String name,
    String description = '',
    String? clientId,
    String status = 'planning',
    String? color,
  }) async {
    final data = await _api.post('/api/projects.php', query: {
      'action': 'add',
    }, body: {
      'name': name,
      if (description.isNotEmpty) 'description': description,
      if (clientId != null && clientId.isNotEmpty) 'clientId': clientId,
      'status': status,
      if (color != null && color.isNotEmpty) 'color': color,
    });
    // Response data is the created project object (has an 'id').
    if (data is Map) {
      return (data['id'] ?? '').toString();
    }
    return '';
  }

  /// Update an existing project. Pass only the fields being changed.
  /// Backend: PUT /api/projects.php?id={id}
  Future<void> update(
    String id, {
    String? name,
    String? description,
    String? clientId,
    String? status,
    String? color,
  }) async {
    await _api.put('/api/projects.php', query: {'id': id}, body: {
      if (name != null) 'name': name,
      if (description != null) 'description': description,
      if (clientId != null) 'clientId': clientId,
      if (status != null) 'status': status,
      if (color != null) 'color': color,
    });
  }

  /// Delete a project and all of its tasks.
  /// Backend: DELETE /api/projects.php?id={id}
  Future<void> delete(String id) async {
    await _api.delete('/api/projects.php', query: {'id': id});
  }
}
