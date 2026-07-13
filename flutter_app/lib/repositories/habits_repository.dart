import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/habit.dart';

final habitsRepositoryProvider =
    Provider<HabitsRepository>((ref) => HabitsRepository(ref.watch(apiClientProvider)));

/// Active (non-archived) habits. Auto-refreshable from the UI.
final habitsListProvider =
    FutureProvider<List<Habit>>((ref) async {
  final all = await ref.watch(habitsRepositoryProvider).list();
  return all.where((h) => !h.archived).toList();
});

/// All habits including archived (for the all-habits / management view).
final allHabitsProvider =
    FutureProvider<List<Habit>>((ref) => ref.watch(habitsRepositoryProvider).list());

class HabitsRepository {
  HabitsRepository(this._api);
  final ApiClient _api;

  Future<List<Habit>> list() async {
    final data = await _api.get('/api/habits.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((h) => Habit.fromJson(h.cast<String, dynamic>()))
        .toList();
  }

  Future<Habit?> fetch(String id) async {
    final all = await list();
    for (final h in all) {
      if (h.id == id) return h;
    }
    return null;
  }

  Future<void> create({
    required String name,
    String category = 'general',
    String frequency = 'daily',
    String? reminderTime,
    int targetDuration = 0,
  }) async {
    await _api.post('/api/habits.php', query: {'action': 'add'}, body: {
      'name': name,
      'category': category,
      'frequency': frequency,
      if (reminderTime != null) 'reminderTime': reminderTime,
      'targetDuration': targetDuration,
    });
  }

  Future<void> update({
    required String id,
    String? name,
    String? category,
    String? frequency,
    String? reminderTime,
    int? targetDuration,
  }) async {
    await _api.put('/api/habits.php', query: {'id': id}, body: {
      if (name != null) 'name': name,
      if (category != null) 'category': category,
      if (frequency != null) 'frequency': frequency,
      if (reminderTime != null) 'reminderTime': reminderTime,
      if (targetDuration != null) 'targetDuration': targetDuration,
    });
  }

  /// Toggle today's completion for a habit.
  Future<void> setComplete(String habitId, bool done) async {
    final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
    await _api.post(
      '/api/habits.php',
      query: {'action': 'complete'},
      body: {
        'habitId': habitId,
        'date': today,
        'status': done ? 'complete' : 'incomplete',
      },
    );
  }

  /// Increment the completion count for a habit on a given date.
  Future<void> incrementCount(String habitId, DateTime date) async {
    await _api.post('/api/habits.php', query: {'action': 'increment_count'}, body: {
      'habitId': habitId,
      'date': DateFormat('yyyy-MM-dd').format(date),
    });
  }

  /// Decrement the completion count for a habit on a given date.
  Future<void> decrementCount(String habitId, DateTime date) async {
    await _api.post('/api/habits.php', query: {'action': 'decrement_count'}, body: {
      'habitId': habitId,
      'date': DateFormat('yyyy-MM-dd').format(date),
    });
  }

  /// Archive (deactivate) or reactivate a habit.
  Future<void> setActive(String habitId, bool active) async {
    await _api.put('/api/habits.php', query: {'id': habitId}, body: {
      'isActive': active,
    });
  }

  Future<void> delete(String habitId) async {
    await _api.delete('/api/habits.php', query: {'id': habitId});
  }

  /// Ask the AI to suggest habit ideas. Returns the raw suggestion payload.
  Future<dynamic> aiSuggest({String? category, int count = 5}) async {
    return _api.post('/api/ai.php', query: {'action': 'suggest_habits'}, body: {
      if (category != null) 'category': category,
      'count': count,
    });
  }
}
