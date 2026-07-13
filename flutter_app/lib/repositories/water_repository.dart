import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/water_plan.dart';

class WaterStatus {
  WaterStatus({required this.intakeMl, required this.goalMl});

  final int intakeMl;
  final int goalMl;

  double get progress {
    if (goalMl <= 0) return 0;
    final r = intakeMl / goalMl;
    if (r < 0) return 0;
    if (r > 1) return 1;
    return r;
  }

  double get litersIn => intakeMl / 1000;
  double get litersGoal => goalMl / 1000;
}

final waterRepositoryProvider =
    Provider<WaterRepository>((ref) => WaterRepository(ref.watch(apiClientProvider)));

final waterProvider =
    FutureProvider<WaterStatus>((ref) => ref.watch(waterRepositoryProvider).status());

/// The active water plan (manual, isActive) for today, or null when none exists.
final waterPlanProvider =
    FutureProvider<WaterPlan?>((ref) async {
  try {
    return await ref.watch(waterRepositoryProvider).getActivePlan();
  } catch (_) {
    // 404 ("No active water plan found") is the normal no-plan state.
    return null;
  }
});

/// All water plans (manual + history), newest first.
final waterHistoryProvider =
    FutureProvider<List<WaterPlan>>((ref) => ref.watch(waterRepositoryProvider).listHistory());

class WaterRepository {
  WaterRepository(this._api);
  final ApiClient _api;

  Future<WaterStatus> status() async {
    final data =
        await _api.get('/api/habits.php', query: {'action': 'get_water_tracker'});
    final m = data is Map ? data : const {};
    int n(dynamic v, [int def = 0]) => v is num ? v.toInt() : def;
    return WaterStatus(intakeMl: n(m['intakeMl']), goalMl: n(m['goalMl'], 2000));
  }

  Future<void> add(int amountMl) async {
    await _api.post(
      '/api/habits.php',
      query: {'action': 'add_water_glass'},
      body: {
        'amountMl': amountMl,
        'count': amountMl / 250,
        'glassSizeMl': 250,
      },
    );
  }

  // ───────────────────────── Water plan methods ─────────────────────────

  /// Fetch the currently-active manual plan (get_water_plan). Returns null if
  /// the backend reports no active plan (404).
  Future<WaterPlan?> getActivePlan() async {
    final data = await _api
        .get('/api/habits.php', query: {'action': 'get_water_plan'});
    if (data is! Map) return null;
    return WaterPlan.fromJson(data.cast<String, dynamic>());
  }

  /// Fetch a specific plan by id (searches manual + history + tracker).
  Future<WaterPlan?> getPlan(String planId) async {
    final data = await _api.get('/api/habits.php',
        query: {'action': 'get_water_plan', 'planId': planId});
    if (data is! Map) return null;
    return WaterPlan.fromJson(data.cast<String, dynamic>());
  }

  /// All plans (manual + history), newest first (list_water_plan_history).
  Future<List<WaterPlan>> listHistory() async {
    final data =
        await _api.get('/api/habits.php', query: {'action': 'list_water_plan_history'});
    final list = (data is List ? data : const [])
        .whereType<Map>()
        .map((p) => WaterPlan.fromJson(p.cast<String, dynamic>()))
        .toList();
    return list;
  }

  /// AI-generate a body-metric hydration plan (generate_water_plan) and make it
  /// the active plan. Returns the created plan.
  Future<WaterPlan> generatePlan({
    required double weight,
    String activity = 'moderate',
    String climate = 'temperate',
    int glassSizeMl = 250,
    String wakeTime = '07:00',
    String sleepTime = '23:00',
    String? name,
  }) async {
    final data = await _api.post('/api/habits.php',
        query: {'action': 'generate_water_plan'},
        body: {
          'weight': weight,
          'activity': activity,
          'climate': climate,
          'glassSize': glassSizeMl,
          'wakeTime': wakeTime,
          'sleepTime': sleepTime,
          if (name != null) 'name': name,
        });
    return WaterPlan.fromJson((data as Map).cast<String, dynamic>());
  }

  /// Create a manual plan (create_manual_plan). Returns the created plan.
  Future<WaterPlan> createManualPlan(WaterPlan plan) async {
    final data = await _api.post('/api/habits.php',
        query: {'action': 'create_manual_plan'}, body: plan.toBody());
    return WaterPlan.fromJson((data as Map).cast<String, dynamic>());
  }

  /// Update an existing manual plan (update_manual_plan). Pass the planId.
  Future<WaterPlan> updateManualPlan(WaterPlan plan) async {
    final data = await _api.post('/api/habits.php',
        query: {'action': 'update_manual_plan'},
        body: plan.toBody(planId: plan.id));
    return WaterPlan.fromJson((data as Map).cast<String, dynamic>());
  }

  /// Set a plan as active for today and reset its schedule (activate_plan).
  Future<void> activatePlan(String planId) async {
    await _api.post('/api/habits.php', query: {'action': 'activate_plan'},
        body: {'planId': planId});
  }

  /// Delete a plan from the given collection (delete_plan).
  /// `collection` is 'water_plans' (default) or 'water_plan_history'.
  Future<void> deletePlan(String planId, {String collection = 'water_plans'}) async {
    await _api.post('/api/habits.php', query: {'action': 'delete_plan'}, body: {
      'planId': planId,
      'collection': collection,
    });
  }

  /// Mark a scheduled reminder as completed (complete_reminder).
  Future<void> completeReminder(String planId, String itemId) async {
    await _api.post('/api/habits.php', query: {'action': 'complete_reminder'},
        body: {
          'planId': planId,
          'scheduleItemId': itemId,
        });
  }

  /// Mark a scheduled reminder as missed after the grace window
  /// (mark_reminder_missed).
  Future<void> markMissed(String planId, String itemId) async {
    await _api.post('/api/habits.php', query: {'action': 'mark_reminder_missed'},
        body: {
          'planId': planId,
          'scheduleItemId': itemId,
        });
  }

  /// Set the daily goal in ml (set_water_goal).
  Future<void> setGoal(int goalMl) async {
    await _api.post('/api/habits.php', query: {'action': 'set_water_goal'},
        body: {'goalMl': goalMl});
  }
}
