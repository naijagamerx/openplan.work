import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/payment_method.dart';

final paymentMethodsRepositoryProvider = Provider<PaymentMethodsRepository>(
    (ref) => PaymentMethodsRepository(ref.watch(apiClientProvider)));

/// All payment methods. Auto-refreshable from the UI.
final paymentMethodsProvider = FutureProvider<List<PaymentMethod>>(
    (ref) => ref.watch(paymentMethodsRepositoryProvider).findAll());

/// Only active payment methods (for the pay screen / public surfaces).
final activePaymentMethodsProvider = FutureProvider<List<PaymentMethod>>(
    (ref) => ref.watch(paymentMethodsRepositoryProvider).findAll(activeOnly: true));

class PaymentMethodsRepository {
  PaymentMethodsRepository(this._api);
  final ApiClient _api;

  /// List payment methods, optionally restricted by active state or type.
  Future<List<PaymentMethod>> findAll({
    bool activeOnly = false,
    String? type,
  }) async {
    final query = <String, dynamic>{};
    if (activeOnly) query['activeOnly'] = '1';
    if (type != null) query['type'] = type;
    final data = await _api.get('/api/payment-methods.php',
        query: query.isEmpty ? null : query);
    final methods = ((data as List?) ?? const [])
        .whereType<Map>()
        .map((m) => PaymentMethod.fromJson(m.cast<String, dynamic>()))
        .toList();
    methods.sort((a, b) => a.label.toLowerCase().compareTo(b.label.toLowerCase()));
    return methods;
  }

  /// Fetch a single payment method by id.
  Future<PaymentMethod?> fetch(String id) async {
    final all = await findAll();
    for (final m in all) {
      if (m.id == id) return m;
    }
    return null;
  }

  Future<void> create(PaymentMethod method) async {
    await _api.post('/api/payment-methods.php', body: method.toBody());
  }

  Future<void> update(String id, PaymentMethod method) async {
    await _api.put('/api/payment-methods.php',
        query: {'id': id}, body: method.toBody());
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/payment-methods.php', query: {'id': id});
  }
}
