import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/plan.dart';

final plansRepositoryProvider =
    Provider<PlansRepository>((ref) => PlansRepository(ref.watch(apiClientProvider)));

/// All plans. Auto-refreshable from the UI.
final plansProvider =
    FutureProvider<List<Plan>>((ref) => ref.watch(plansRepositoryProvider).findAll());

/// Only active plans (for the subscribe picker / public surfaces).
final activePlansProvider = FutureProvider<List<Plan>>(
    (ref) => ref.watch(plansRepositoryProvider).findAll(activeOnly: true));

class PlansRepository {
  PlansRepository(this._api);
  final ApiClient _api;

  /// List plans, optionally restricted to active ones.
  Future<List<Plan>> findAll({bool activeOnly = false}) async {
    final data = await _api.get('/api/plans.php',
        query: activeOnly ? {'activeOnly': '1'} : null);
    final plans = ((data as List?) ?? const [])
        .whereType<Map>()
        .map((p) => Plan.fromJson(p.cast<String, dynamic>()))
        .toList();
    plans.sort((a, b) => a.price.compareTo(b.price));
    return plans;
  }

  /// Fetch a single plan by id.
  Future<Plan?> fetch(String id) async {
    final all = await findAll();
    for (final p in all) {
      if (p.id == id) return p;
    }
    return null;
  }

  Future<void> create(Plan plan) async {
    await _api.post('/api/plans.php', body: plan.toBody());
  }

  Future<void> update(String id, Plan plan) async {
    await _api.put('/api/plans.php', query: {'id': id}, body: plan.toBody());
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/plans.php', query: {'id': id});
  }
}
