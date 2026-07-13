import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/ticket.dart';

final adminTicketsRepositoryProvider = Provider<AdminTicketsRepository>(
    (ref) => AdminTicketsRepository(ref.watch(apiClientProvider)));

/// All tickets (admin queue). Auto-refreshable from the UI.
final adminTicketsProvider =
    FutureProvider<List<Ticket>>((ref) async {
  final tickets = await ref.watch(adminTicketsRepositoryProvider).listAll();
  // Awaiting-admin first, then most recently updated (server also does this).
  tickets.sort((a, b) {
    final aWait = a.status == 'awaiting_admin' ? 0 : 1;
    final bWait = b.status == 'awaiting_admin' ? 0 : 1;
    if (aWait != bWait) return aWait - bWait;
    final at = b.updatedAt?.millisecondsSinceEpoch ?? 0;
    final bt = a.updatedAt?.millisecondsSinceEpoch ?? 0;
    return at - bt;
  });
  return tickets;
});

/// A single ticket by id, auto-refreshable (for the thread screen).
final ticketProvider =
    FutureProvider.family<Ticket?, String>((ref, id) async {
  return ref.watch(adminTicketsRepositoryProvider).fetch(id);
});

class AdminTicketsRepository {
  AdminTicketsRepository(this._api);
  final ApiClient _api;

  /// `GET /api/tickets.php?all=1` — admin-only full queue.
  Future<List<Ticket>> listAll() async {
    final data = await _api.get('/api/tickets.php', query: {'all': '1'});
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((t) => Ticket.fromJson(t.cast<String, dynamic>()))
        .toList();
  }

  /// `GET /api/tickets.php?id=X` — single ticket (owner or admin).
  Future<Ticket?> fetch(String id) async {
    final data = await _api.get('/api/tickets.php', query: {'id': id});
    if (data is Map) return Ticket.fromJson(data.cast<String, dynamic>());
    return null;
  }

  /// `POST /api/tickets.php?id=X&action=reply { body }`. The server sets
  /// `authorRole` from `Auth::isAdmin()`.
  Future<Ticket> reply(String id, String body) async {
    final data = await _api.post('/api/tickets.php',
        query: {'id': id, 'action': 'reply'}, body: {'body': body});
    final map = data is Map ? data : <String, dynamic>{};
    return Ticket.fromJson(map.cast<String, dynamic>());
  }

  /// `POST /api/tickets.php?id=X&action=close`.
  Future<Ticket> close(String id) async {
    final data = await _api.post('/api/tickets.php',
        query: {'id': id, 'action': 'close'});
    final map = data is Map ? data : <String, dynamic>{};
    return Ticket.fromJson(map.cast<String, dynamic>());
  }
}
