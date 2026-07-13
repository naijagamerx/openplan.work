import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/transaction.dart';

final financeRepositoryProvider = Provider<FinanceRepository>(
    (ref) => FinanceRepository(ref.watch(apiClientProvider)));

/// All finance transactions. Auto-refreshable from the UI.
final financeProvider = FutureProvider<List<FinanceTransaction>>(
    (ref) => ref.watch(financeRepositoryProvider).list());

class FinanceRepository {
  FinanceRepository(this._api);
  final ApiClient _api;

  Future<List<FinanceTransaction>> list() async {
    final data = await _api.get('/api/finance.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((j) => FinanceTransaction.fromJson(j.cast<String, dynamic>()))
        .toList();
  }

  /// Fetch a single transaction by id (client-side filter over [list]).
  Future<FinanceTransaction?> fetch(String id) async {
    final all = await list();
    for (final t in all) {
      if (t.id == id) return t;
    }
    return null;
  }

  Future<void> create({
    required String type,
    required double amount,
    required DateTime date,
    String category = '',
    String description = '',
  }) async {
    await _api.post('/api/finance.php', query: {'action': 'add'}, body: {
      'type': type,
      'amount': amount,
      'date': date.toIso8601String(),
      if (category.isNotEmpty) 'category': category,
      if (description.isNotEmpty) 'description': description,
    });
  }

  Future<void> update({
    required String id,
    String? type,
    double? amount,
    DateTime? date,
    String? category,
    String? description,
  }) async {
    await _api.put('/api/finance.php', query: {'id': id}, body: {
      if (type != null) 'type': type,
      if (amount != null) 'amount': amount,
      if (date != null) 'date': date.toIso8601String(),
      if (category != null) 'category': category,
      if (description != null) 'description': description,
    });
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/finance.php', query: {'id': id});
  }
}
