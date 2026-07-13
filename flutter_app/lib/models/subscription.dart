/// A subscription as returned by `GET /api/subscriptions.php?all=1`.
///
/// Mirrors the backend schema (see SubscriptionsAPI::getAll and the record built
/// in PaymentsAPI::activateSubscription): a user's plan entitlement with status
/// lifecycle active → cancelled|expired. The plan name is denormalized inside a
/// `planSnapshot` (copied at activation time), and the user is referenced by id.
class Subscription {
  Subscription({
    required this.id,
    required this.userId,
    required this.planSnapshot,
    required this.status,
    this.startsAt,
    this.expiresAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final PlanSnapshot planSnapshot;
  final String status; // active | cancelled | expired
  final DateTime? startsAt;
  final DateTime? expiresAt;
  final DateTime? updatedAt;

  /// Whether the subscription can still be acted on (extend/cancel).
  bool get isActive => status == 'active';

  factory Subscription.fromJson(Map<String, dynamic> j) {
    return Subscription(
      id: (j['id'] ?? '').toString(),
      userId: (j['userId'] ?? '').toString(),
      planSnapshot: PlanSnapshot.fromJson(
          (j['planSnapshot'] as Map?)?.cast<String, dynamic>() ?? const {}),
      status: (j['status'] ?? 'expired').toString(),
      startsAt: _parseDate(j['startsAt']),
      expiresAt: _parseDate(j['expiresAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}

/// A snapshot of the plan taken when the subscription was activated. Only the
/// fields needed for display are modelled (the backend stores more).
class PlanSnapshot {
  const PlanSnapshot({required this.name});

  final String name;

  factory PlanSnapshot.fromJson(Map<String, dynamic> j) =>
      PlanSnapshot(name: (j['name'] ?? '').toString());
}
