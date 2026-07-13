import 'package:flutter/material.dart';

/// An invoice as returned by `GET /api/invoices.php` (BaseAPI schema).
/// Mirrors the backend allowed fields (see api/invoices.php::getAllowedFields).
class Invoice {
  Invoice({
    required this.id,
    required this.invoiceNumber,
    required this.clientId,
    required this.lineItems,
    required this.subtotal,
    required this.taxRate,
    required this.taxAmount,
    required this.total,
    required this.currency,
    required this.status,
    required this.dueDate,
    this.projectId = '',
    this.issueDate,
    this.notes = '',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String invoiceNumber;
  final String clientId;
  final String projectId;
  final List<LineItem> lineItems;
  final double subtotal;
  final double taxRate;
  final double taxAmount;
  final double total;
  final String currency; // USD|EUR|GBP|ZAR
  final String status; // draft|sent|paid|overdue|cancelled
  final DateTime? dueDate;
  final DateTime? issueDate;
  final String notes;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// A status is considered "paid" (no longer outstanding).
  bool get isPaid => status == 'paid' || status == 'cancelled';

  /// Semantic color for a status value — the ONLY non-monochrome surface on
  /// invoice rows (matches the PHP mobile invoices.php badge classes).
  static const Color paidColor = Color(0xFF16A34A);
  static const Color overdueColor = Color(0xFFDC2626);

  /// Maps a status to its accent color. draft→gray, sent→ink, paid→green,
  /// overdue→red, cancelled→faint. Uses Material defaults so it stays legible
  /// in both themes; callers pass the palette's muted/faint/ink as needed.
  static Color statusColor(String status) {
    switch (status.toLowerCase()) {
      case 'paid':
        return paidColor;
      case 'overdue':
        return overdueColor;
      default:
        return Colors.transparent; // callers resolve gray/ink from the palette
    }
  }

  /// Human-readable label for a status (mirrors statusLabelMobileInvoice).
  static String statusLabel(String status) {
    switch (status.toLowerCase()) {
      case 'paid':
        return 'Paid';
      case 'overdue':
        return 'Overdue';
      case 'sent':
        return 'Pending';
      case 'draft':
        return 'Draft';
      case 'cancelled':
        return 'Cancelled';
      default:
        return status.isEmpty ? 'Draft' : status[0].toUpperCase() + status.substring(1);
    }
  }

  factory Invoice.fromJson(Map<String, dynamic> j) {
    final rawItems = (j['lineItems'] as List?) ?? const [];
    return Invoice(
      id: (j['id'] ?? '').toString(),
      invoiceNumber:
          (j['invoiceNumber'] ?? 'INV-${(j['id'] ?? '').toString().padLeft(6, '0')}').toString(),
      clientId: (j['clientId'] ?? '').toString(),
      projectId: (j['projectId'] ?? '').toString(),
      lineItems: rawItems
          .whereType<Map>()
          .map((m) => LineItem.fromJson(m.cast<String, dynamic>()))
          .toList(),
      subtotal: _toDouble(j['subtotal']),
      taxRate: _toDouble(j['taxRate']),
      taxAmount: _toDouble(j['taxAmount']),
      total: _toDouble(j['total']),
      currency: (j['currency'] ?? 'USD').toString(),
      status: (j['status'] ?? 'draft').toString(),
      dueDate: _parseDate(j['dueDate']),
      issueDate: _parseDate(j['issueDate']),
      notes: (j['notes'] ?? '').toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  Map<String, dynamic> toBody() => {
        if (clientId.isNotEmpty) 'clientId': clientId,
        if (projectId.isNotEmpty) 'projectId': projectId,
        'lineItems': lineItems.map((i) => i.toBody()).toList(),
        'subtotal': subtotal,
        'taxRate': taxRate,
        'taxAmount': taxAmount,
        'total': total,
        'currency': currency,
        'status': status,
        if (dueDate != null)
          'dueDate':
              '${dueDate!.year}-${dueDate!.month.toString().padLeft(2, '0')}-${dueDate!.day.toString().padLeft(2, '0')}',
        if (issueDate != null)
          'issueDate':
              '${issueDate!.year}-${issueDate!.month.toString().padLeft(2, '0')}-${issueDate!.day.toString().padLeft(2, '0')}',
        if (notes.isNotEmpty) 'notes': notes,
      };

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

/// A single chargeable line on an invoice.
class LineItem {
  LineItem({
    required this.description,
    required this.quantity,
    required this.unitPrice,
    this.total,
  });

  final String description;
  final double quantity;
  final double unitPrice;
  final double? total;

  /// Computed line total (qty × unitPrice), falling back to the stored value.
  double get computedTotal => total ?? (quantity * unitPrice);

  factory LineItem.fromJson(Map<String, dynamic> j) => LineItem(
        description: (j['description'] ?? '').toString(),
        quantity: _toDouble(j['quantity']),
        unitPrice: _toDouble(j['unitPrice']),
        total: j['total'] != null ? _toDouble(j['total']) : null,
      );

  Map<String, dynamic> toBody() => {
        'description': description,
        'quantity': quantity,
        'unitPrice': unitPrice,
        'total': computedTotal,
      };

  LineItem copyWith({String? description, double? quantity, double? unitPrice}) =>
      LineItem(
        description: description ?? this.description,
        quantity: quantity ?? this.quantity,
        unitPrice: unitPrice ?? this.unitPrice,
      );

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString()) ?? 0;
  }
}
