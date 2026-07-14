import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/advanced_invoice.dart';

final advancedInvoicesRepositoryProvider = Provider<AdvancedInvoicesRepository>(
    (ref) => AdvancedInvoicesRepository(ref.watch(apiClientProvider)));

/// All advanced invoices, newest first. Auto-refreshable from the UI.
final advancedInvoicesProvider =
    FutureProvider<List<AdvancedInvoice>>((ref) async {
  final list = await ref.watch(advancedInvoicesRepositoryProvider).list();
  list.sort((a, b) {
    final ac = a.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
    final bc = b.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
    return bc.compareTo(ac);
  });
  return list;
});

/// Talks to `api/advanced-invoices.php` (view-only client: list + fetch).
class AdvancedInvoicesRepository {
  AdvancedInvoicesRepository(this._api);
  final ApiClient _api;

  Future<List<AdvancedInvoice>> list() async {
    final data = await _api.get('/api/advanced-invoices.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((m) => AdvancedInvoice.fromJson(m.cast<String, dynamic>()))
        .toList();
  }

  Future<AdvancedInvoice?> fetch(String id) async {
    final data = await _api.get('/api/advanced-invoices.php', query: {'id': id});
    if (data is Map<String, dynamic>) return AdvancedInvoice.fromJson(data);
    if (data is Map) {
      return AdvancedInvoice.fromJson(data.cast<String, dynamic>());
    }
    return null;
  }
}
