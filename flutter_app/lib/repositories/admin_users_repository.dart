import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/admin_user.dart';

final adminUsersRepositoryProvider = Provider<AdminUsersRepository>(
    (ref) => AdminUsersRepository(ref.watch(apiClientProvider)));

/// All users (admin only). Auto-refreshable from the UI.
final adminUsersProvider = FutureProvider<List<AdminUser>>(
    (ref) => ref.watch(adminUsersRepositoryProvider).list());

class AdminUsersRepository {
  AdminUsersRepository(this._api);
  final ApiClient _api;

  /// List all users (sorted client-side by name, like clients.php).
  Future<List<AdminUser>> list() async {
    final data = await _api.get('/api/users.php', query: {'action': 'list'});
    final users = ((data as List?) ?? const [])
        .whereType<Map>()
        .map((u) => AdminUser.fromJson(u.cast<String, dynamic>()))
        .toList();
    users.sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
    return users;
  }

  /// Change a user's role ('admin' | 'user'). The server refuses the last admin.
  Future<void> updateRole(String id, String role) async {
    await _api.post('/api/users.php', query: {'action': 'update_role'}, body: {
      'user_id': id,
      'role': role,
    });
  }

  /// Ban or unban a user. The server refuses admins.
  Future<void> toggleBan(String id) async {
    await _api.post('/api/users.php', query: {'action': 'toggle_ban'}, body: {
      'user_id': id,
    });
  }

  /// Bulk-ban accounts flagged as spam.
  Future<void> bulkBanSpam() async {
    await _api.post('/api/users.php', query: {'action': 'bulk_ban_spam'});
  }

  /// Permanently delete a user. The server refuses admins + the last admin.
  Future<void> deleteUser(String id) async {
    await _api.post('/api/users.php', query: {'action': 'delete_user'}, body: {
      'user_id': id,
    });
  }
}
