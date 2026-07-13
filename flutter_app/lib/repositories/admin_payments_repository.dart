import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/admin_payment.dart';

final adminPaymentsRepositoryProvider = Provider<AdminPaymentsRepository>(
    (ref) => AdminPaymentsRepository(ref.watch(apiClientProvider)));

/// Status filter for the payment approvals queue.
typedef PaymentStatusFilter = String; // 'pending' | 'approved' | 'rejected' | 'all'

/// Parametrized by the status filter — watching this with a different filter
/// re-fetches automatically (mirrors how the admin-payments.php pills work).
final adminPaymentsProvider =
    FutureProvider.family<List<AdminPayment>, PaymentStatusFilter>(
  (ref, status) => ref.watch(adminPaymentsRepositoryProvider).list(status: status),
);

class AdminPaymentsRepository {
  AdminPaymentsRepository(this._api);
  final ApiClient _api;

  /// List all payment submissions, optionally filtered by status.
  /// `status = 'all'` (or null) returns every submission.
  Future<List<AdminPayment>> list({String status = 'pending'}) async {
    final query = <String, dynamic>{'all': '1'};
    if (status != 'all') query['status'] = status;
    final data = await _api.get('/api/payments.php', query: query);
    final payments = ((data as List?) ?? const [])
        .whereType<Map>()
        .map((p) => AdminPayment.fromJson(p.cast<String, dynamic>()))
        .toList();
    // Newest first (admin-payments.php shows newest submissions on top).
    payments.sort((a, b) {
      final ad = a.submittedAt;
      final bd = b.submittedAt;
      if (ad == null && bd == null) return 0;
      if (ad == null) return 1;
      if (bd == null) return -1;
      return bd.compareTo(ad);
    });
    return payments;
  }

  /// Approve a pending payment. An optional internal note may be recorded.
  Future<void> approve(String id, {String? note}) async {
    await _api.post('/api/payments.php', query: {'id': id, 'action': 'approve'},
        body: {
          if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        });
  }

  /// Reject a pending payment with a required reason (shown to the user).
  Future<void> reject(String id, String reason) async {
    await _api.post('/api/payments.php', query: {'id': id, 'action': 'reject'},
        body: {'reason': reason});
  }
}
