import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/audit_log.dart';

final auditRepositoryProvider =
    Provider<AuditRepository>((ref) => AuditRepository(ref.watch(apiClientProvider)));

/// A page of audit logs + the total matching count, returned by the list API.
class AuditPage {
  const AuditPage({required this.logs, required this.total});
  final List<AuditLog> logs;
  final int total;
}

/// Aggregate counts from the stats API (`GET ?action=stats`).
class AuditStats {
  const AuditStats({required this.total, required this.today});
  final int total;
  final int today;
}

/// Parametrized log list provider. Watches [AuditLogFilter] and returns the
/// current page; the screen refreshes it as the search/filter changes.
final auditLogsProvider =
    FutureProvider.family<AuditPage, AuditLogFilter>((ref, filter) async {
  return ref.watch(auditRepositoryProvider).list(filter: filter);
});

class AuditRepository {
  AuditRepository(this._api);
  final ApiClient _api;

  /// `GET /api/audit.php?action=list { search, event, limit, offset }`.
  Future<AuditPage> list({AuditLogFilter filter = const AuditLogFilter()}) async {
    final data = await _api.get('/api/audit.php', query: {
      'action': 'list',
      if (filter.search != null) 'search': filter.search,
      if (filter.event != null) 'event': filter.event,
      'limit': filter.limit,
      'offset': filter.offset,
    });
    final map = data is Map ? data : <String, dynamic>{};
    final raw = (map['logs'] as List?) ?? const [];
    final logs = raw
        .whereType<Map>()
        .map((l) => AuditLog.fromJson(l.cast<String, dynamic>()))
        .toList();
    final total = (map['total'] is num)
        ? (map['total'] as num).toInt()
        : int.tryParse(map['total']?.toString() ?? '') ?? logs.length;
    return AuditPage(logs: logs, total: total);
  }

  /// `GET /api/audit.php?action=stats` — total + today's count.
  Future<AuditStats> stats() async {
    final data = await _api.get('/api/audit.php', query: {'action': 'stats'});
    final map = data is Map ? data : <String, dynamic>{};
    final total = _toInt(map['total_logs']);
    // Count today's events from by_day (keyed YYYY-MM-DD).
    final byDay = (map['by_day'] as Map?)?.cast<String, dynamic>() ?? const {};
    final todayKey = _todayKey();
    final today = _toInt(byDay[todayKey]);
    return AuditStats(total: total, today: today);
  }

  /// `GET /api/audit.php?action=types` — available event-type values.
  Future<List<String>> types() async {
    final data = await _api.get('/api/audit.php', query: {'action': 'types'});
    final map = data is Map ? data : <String, dynamic>{};
    final events = (map['events'] as Map?)?.cast<String, dynamic>() ?? const {};
    return events.keys.toList()..sort();
  }

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? 0;
  }

  static String _todayKey() {
    final n = DateTime.now();
    String two(int x) => x.toString().padLeft(2, '0');
    return '${n.year}-${two(n.month)}-${two(n.day)}';
  }
}

/// Immutable filter for the audit log list (search text + event type + paging).
class AuditLogFilter {
  const AuditLogFilter({
    this.search,
    this.event,
    this.limit = 100,
    this.offset = 0,
  });

  final String? search;
  final String? event;
  final int limit;
  final int offset;

  /// Equality so the provider recomputes only when the filter actually changes.
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is AuditLogFilter &&
          runtimeType == other.runtimeType &&
          search == other.search &&
          event == other.event &&
          limit == other.limit &&
          offset == other.offset;

  @override
  int get hashCode =>
      Object.hash(search, event, limit, offset);
}
