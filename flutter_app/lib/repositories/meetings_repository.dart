import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/meeting.dart';

final meetingsRepositoryProvider = Provider<MeetingsRepository>(
    (ref) => MeetingsRepository(ref.watch(apiClientProvider)));

/// All calendar meetings. Auto-refreshable from the UI (invalidate on mutation).
final meetingsProvider =
    FutureProvider<List<Meeting>>((ref) => ref.watch(meetingsRepositoryProvider).list());

class MeetingsRepository {
  MeetingsRepository(this._api);
  final ApiClient _api;

  Future<List<Meeting>> list() async {
    final data = await _api.get('/api/meetings.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((m) => Meeting.fromJson(m.cast<String, dynamic>()))
        .toList();
  }

  Future<Meeting?> fetch(String id) async {
    final data = await _api.get('/api/meetings.php', query: {'id': id});
    if (data is Map) {
      return Meeting.fromJson(data.cast<String, dynamic>());
    }
    return null;
  }

  Future<void> create({
    required String title,
    required DateTime date,
    String? startTime,
    String? endTime,
    String? location,
    String? color,
    List<String> attendees = const [],
    String notes = '',
  }) async {
    await _api.post('/api/meetings.php', body: {
      'title': title,
      'date': date.toUtc().toIso8601String(),
      if (startTime != null && startTime.isNotEmpty) 'startTime': startTime,
      if (endTime != null && endTime.isNotEmpty) 'endTime': endTime,
      if (location != null && location.isNotEmpty) 'location': location,
      if (color != null && color.isNotEmpty) 'color': color,
      'attendees': attendees,
      if (notes.isNotEmpty) 'description': notes,
    });
  }

  Future<void> update({
    required String id,
    String? title,
    DateTime? date,
    String? startTime,
    String? endTime,
    String? location,
    String? color,
    List<String>? attendees,
    String? notes,
  }) async {
    await _api.put('/api/meetings.php', query: {'id': id}, body: {
      if (title != null) 'title': title,
      if (date != null) 'date': date.toUtc().toIso8601String(),
      if (startTime != null) 'startTime': startTime,
      if (endTime != null) 'endTime': endTime,
      if (location != null) 'location': location,
      if (color != null) 'color': color,
      if (attendees != null) 'attendees': attendees,
      if (notes != null) 'description': notes,
    });
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/meetings.php', query: {'id': id});
  }
}
