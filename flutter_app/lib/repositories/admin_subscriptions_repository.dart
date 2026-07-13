import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/subscription.dart';

final adminSubscriptionsRepositoryProvider = Provider<AdminSubscriptionsRepository>(
    (ref) => AdminSubscriptionsRepository(ref.watch(apiClientProvider)));

/// All subscriptions (admin view). Auto-refreshable from the UI.
final adminSubscriptionsProvider =
    FutureProvider<List<Subscription>>((ref) async {
  final subs = await ref.watch(adminSubscriptionsRepositoryProvider).listAll();
  // Active first (server already orders this way), then most recently created.
  subs.sort((a, b) {
    final aActive = a.isActive ? 0 : 1;
    final bActive = b.isActive ? 0 : 1;
    if (aActive != bActive) return aActive - bActive;
    final at = b.updatedAt?.millisecondsSinceEpoch ?? 0;
    final bt = a.updatedAt?.millisecondsSinceEpoch ?? 0;
    return at - bt;
  });
  return subs;
});

class AdminSubscriptionsRepository {
  AdminSubscriptionsRepository(this._api);
  final ApiClient _api;

  /// `GET /api/subscriptions.php?all=1` — admin-only full list.
  Future<List<Subscription>> listAll() async {
    final data = await _api.get('/api/subscriptions.php', query: {'all': '1'});
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((s) => Subscription.fromJson(s.cast<String, dynamic>()))
        .toList();
  }

  /// `POST /api/subscriptions.php?id=X&action=extend { days }`.
  Future<void> extend(String id, int days) async {
    await _api.post('/api/subscriptions.php',
        query: {'id': id, 'action': 'extend'}, body: {'days': days});
  }

  /// `POST /api/subscriptions.php?id=X&action=cancel`.
  Future<void> cancel(String id) async {
    await _api.post('/api/subscriptions.php',
        query: {'id': id, 'action': 'cancel'});
  }
}
