import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/invoice.dart';

final invoicesRepositoryProvider =
    Provider<InvoicesRepository>((ref) => InvoicesRepository(ref.watch(apiClientProvider)));

/// All invoices (newest first). Auto-refreshable from the UI.
final invoicesProvider =
    FutureProvider<List<Invoice>>((ref) async {
  final list = await ref.watch(invoicesRepositoryProvider).list();
  list.sort((a, b) {
    final ac = a.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
    final bc = b.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
    return bc.compareTo(ac);
  });
  return list;
});

class InvoicesRepository {
  InvoicesRepository(this._api);
  final ApiClient _api;

  Future<List<Invoice>> list() async {
    final data = await _api.get('/api/invoices.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((m) => Invoice.fromJson(m.cast<String, dynamic>()))
        .toList();
  }

  Future<Invoice?> fetch(String id) async {
    final data = await _api.get('/api/invoices.php', query: {'id': id});
    if (data is Map<String, dynamic>) return Invoice.fromJson(data);
    if (data is Map) return Invoice.fromJson(data.cast<String, dynamic>());
    return null;
  }

  Future<Invoice> create(Map<String, dynamic> body) async {
    final data = await _api.post('/api/invoices.php', query: {'action': 'create'}, body: body);
    if (data is Map<String, dynamic>) return Invoice.fromJson(data);
    if (data is Map) return Invoice.fromJson(data.cast<String, dynamic>());
    throw Exception('Failed to create invoice');
  }

  Future<Invoice> update(String id, Map<String, dynamic> body) async {
    final data = await _api.put('/api/invoices.php', query: {'id': id}, body: body);
    if (data is Map<String, dynamic>) return Invoice.fromJson(data);
    if (data is Map) return Invoice.fromJson(data.cast<String, dynamic>());
    throw Exception('Failed to update invoice');
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/invoices.php', query: {'id': id});
  }
}
