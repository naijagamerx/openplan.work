/// A payment submission as returned by `GET api/payments.php?all=1` (admin only).
/// Mirrors the backend schema (see api/payments.php + mobile/views/admin-payments.php).
///
/// `planSnapshot` and `methodSnapshot` are denormalized snapshots captured at
/// submission time (the plan/method may have been edited or deleted since), so
/// the admin always sees what the user actually paid for. `proof` carries the
/// uploaded proof's mime type so the UI can render a thumbnail vs a PDF icon.
class AdminPayment {
  AdminPayment({
    required this.id,
    required this.userId,
    required this.amount,
    this.currency = 'USD',
    this.status = 'pending',
    this.planSnapshot,
    this.methodSnapshot,
    this.userEmail = '',
    this.submittedAt,
    this.rejectionReason,
    this.proof,
  });

  final String id;
  final String userId;
  final double amount;
  final String currency; // USD | EUR | GBP | ZAR
  final String status; // pending | approved | rejected
  final PlanSnapshot? planSnapshot;
  final MethodSnapshot? methodSnapshot;
  final String userEmail; // denormalized for the admin list
  final DateTime? submittedAt;
  final String? rejectionReason;
  final Proof? proof;

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';

  /// True when a proof attachment exists.
  bool get hasProof => proof != null;

  factory AdminPayment.fromJson(Map<String, dynamic> j) {
    return AdminPayment(
      id: (j['id'] ?? '').toString(),
      userId: (j['userId'] ?? j['user_id'] ?? '').toString(),
      amount: _toDouble(j['amount']),
      currency: (j['currency'] ?? 'USD').toString().toUpperCase(),
      status: (j['status'] ?? 'pending').toString(),
      planSnapshot: _planSnapshot(j['planSnapshot'] ?? j['plan_snapshot']),
      methodSnapshot:
          _methodSnapshot(j['methodSnapshot'] ?? j['method_snapshot']),
      userEmail: (j['userEmail'] ?? j['user_email'] ?? j['email'] ?? '')
          .toString(),
      submittedAt: _parseDate(j['submittedAt'] ?? j['submitted_at'] ?? j['createdAt']),
      rejectionReason:
          (j['rejectionReason'] ?? j['rejection_reason'])?.toString(),
      proof: _proof(j['proof']),
    );
  }

  static PlanSnapshot? _planSnapshot(dynamic v) {
    if (v is! Map) return null;
    final m = v.cast<String, dynamic>();
    return PlanSnapshot(name: (m['name'] ?? '').toString());
  }

  static MethodSnapshot? _methodSnapshot(dynamic v) {
    if (v is! Map) return null;
    final m = v.cast<String, dynamic>();
    return MethodSnapshot(label: (m['label'] ?? '').toString());
  }

  static Proof? _proof(dynamic v) {
    if (v is! Map) return null;
    final m = v.cast<String, dynamic>();
    final hasFile = (m['mimeType'] ?? m['filename'] ?? m['url']) != null;
    if (!hasFile) return null;
    return Proof(
      mimeType: (m['mimeType'] ?? m['mime_type'] ?? '').toString(),
      filename: (m['filename'] ?? '').toString(),
    );
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString()) ?? 0;
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}

/// Snapshot of the plan a payment was submitted against.
class PlanSnapshot {
  PlanSnapshot({required this.name});
  final String name;
}

/// Snapshot of the payment method a submission used.
class MethodSnapshot {
  MethodSnapshot({required this.label});
  final String label;
}

/// Metadata about the uploaded proof attachment.
class Proof {
  Proof({this.mimeType = '', this.filename = ''});
  final String mimeType;
  final String filename;

  bool get isImage => mimeType.startsWith('image/');
  bool get isPdf => mimeType == 'application/pdf';
}
