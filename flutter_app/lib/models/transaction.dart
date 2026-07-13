/// A finance transaction as returned by `GET /api/finance.php`.
/// Mirrors the backend schema (see api/finance.php + BaseAPI::getAllowedFields).
/// Named `FinanceTransaction` to avoid clashing with Flutter's `Transaction`.
class FinanceTransaction {
  FinanceTransaction({
    required this.id,
    required this.type,
    required this.amount,
    required this.date,
    this.category = '',
    this.description = '',
    this.createdAt,
  });

  final String id;
  final String type; // income|expense
  final double amount;
  final DateTime date;
  final String category;
  final String description;
  final DateTime? createdAt;

  /// True for income rows (drives +/- prefix and green/red tint).
  bool get isIncome => type.toLowerCase() == 'income';

  factory FinanceTransaction.fromJson(Map<String, dynamic> j) {
    return FinanceTransaction(
      id: (j['id'] ?? '').toString(),
      type: (j['type'] ?? 'expense').toString().toLowerCase(),
      amount: _toDouble(j['amount']),
      // `date` is authoritative; fall back to createdAt (mirrors finance.php).
      date: _parseDate(j['date']) ??
          _parseDate(j['createdAt']) ??
          DateTime.now(),
      category: (j['category'] ?? '').toString(),
      description: (j['description'] ?? '').toString(),
      createdAt: _parseDate(j['createdAt']),
    );
  }

  /// Build a JSON body suitable for POST/PUT to /api/finance.php.
  Map<String, dynamic> toBody() => {
        'type': type,
        'amount': amount,
        'date': date.toIso8601String(),
        if (category.isNotEmpty) 'category': category,
        if (description.isNotEmpty) 'description': description,
      };

  FinanceTransaction copyWith({
    String? type,
    double? amount,
    DateTime? date,
    String? category,
    String? description,
  }) {
    return FinanceTransaction(
      id: id,
      type: type ?? this.type,
      amount: amount ?? this.amount,
      date: date ?? this.date,
      category: category ?? this.category,
      description: description ?? this.description,
      createdAt: createdAt,
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString()) ?? 0;
  }
}
