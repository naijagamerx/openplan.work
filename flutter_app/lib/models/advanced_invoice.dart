import 'package:flutter/material.dart';

/// An "Advanced Invoice" as returned by `GET /api/advanced-invoices.php`.
///
/// This is NOT a normal [Invoice]: it's a parallel, CRM-decoupled (the customer
/// is a free-form embedded object, not a clientId), TAX-LESS letterhead /
/// statement document — its `totalDue` is a flat sum of line-item amounts.
/// Mirrors api/advanced-invoices.php::getAllowedFields and the nested shapes
/// the web form/view use.
class AdvancedInvoice {
  AdvancedInvoice({
    required this.id,
    required this.invoiceNumber,
    required this.invoiceDate,
    required this.company,
    required this.customer,
    required this.lineItems,
    required this.payment,
    required this.customFields,
    this.totalDue = 0,
    this.currency = 'USD',
    this.status = 'unpaid',
    this.notes = '',
    this.footerText = '',
    this.template = 'classic',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String invoiceNumber;
  final DateTime? invoiceDate;
  final AdvCompanyHeader company;
  final AdvCustomer customer;
  final List<AdvLineItem> lineItems;
  final AdvPaymentDetails payment;
  final List<AdvCustomField> customFields;
  final double totalDue;
  final String currency;
  final String status; // unpaid|paid|draft (overdue derived)
  final String notes;
  final String footerText;
  final String template; // classic|modern (mobile renders identically)
  final DateTime? createdAt;
  final DateTime? updatedAt;

  bool get isPaid => status.toLowerCase() == 'paid';

  /// Semantic status colours (green=paid, red=overdue) matching the web badges.
  static const paidColor = Color(0xFF16A34A);
  static const overdueColor = Color(0xFFDC2626);

  static String statusLabel(String status) {
    switch (status.toLowerCase()) {
      case 'paid':
        return 'Paid';
      case 'draft':
        return 'Draft';
      case 'overdue':
        return 'Overdue';
      case 'unpaid':
      case '':
        return 'Unpaid';
      default:
        return status[0].toUpperCase() + status.substring(1);
    }
  }

  factory AdvancedInvoice.fromJson(Map<String, dynamic> j) {
    final rawItems = (j['lineItems'] as List?) ?? const [];
    final rawCustom = (j['customFields'] as List?) ?? const [];
    return AdvancedInvoice(
      id: (j['id'] ?? '').toString(),
      invoiceNumber: (j['invoiceNumber'] ?? '').toString(),
      invoiceDate: _parseDate(j['invoiceDate']),
      company: AdvCompanyHeader.fromJson(_asMap(j['companyHeader'])),
      customer: AdvCustomer.fromJson(_asMap(j['customer'])),
      lineItems: rawItems
          .whereType<Map>()
          .map((m) => AdvLineItem.fromJson(m.cast<String, dynamic>()))
          .toList(),
      payment: AdvPaymentDetails.fromJson(_asMap(j['paymentDetails'])),
      customFields: rawCustom
          .whereType<Map>()
          .map((m) => AdvCustomField.fromJson(m.cast<String, dynamic>()))
          .toList(),
      totalDue: _toDouble(j['totalDue']),
      currency: (j['currency'] ?? 'USD').toString(),
      status: (j['status'] ?? 'unpaid').toString(),
      notes: (j['notes'] ?? '').toString(),
      footerText: (j['footerText'] ?? '').toString(),
      template: (j['template'] ?? 'classic').toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  static Map<String, dynamic> _asMap(dynamic v) =>
      v is Map ? v.cast<String, dynamic>() : <String, dynamic>{};

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

class AdvCompanyHeader {
  AdvCompanyHeader({
    this.companyName = '',
    this.companyEmail = '',
    this.companyPhone = '',
    this.companyAddress = '',
    this.logoUrl = '',
  });

  final String companyName;
  final String companyEmail;
  final String companyPhone;
  final String companyAddress;
  final String logoUrl;

  factory AdvCompanyHeader.fromJson(Map<String, dynamic> j) => AdvCompanyHeader(
        companyName: (j['companyName'] ?? '').toString(),
        companyEmail: (j['companyEmail'] ?? '').toString(),
        companyPhone: (j['companyPhone'] ?? '').toString(),
        companyAddress: (j['companyAddress'] ?? '').toString(),
        logoUrl: (j['logoUrl'] ?? '').toString(),
      );
}

class AdvCustomer {
  AdvCustomer({
    this.name = '',
    this.customerId = '',
    this.email = '',
    this.phone = '',
    this.propertyAddress = '',
    this.contractFrom = '',
    this.contractTo = '',
  });

  final String name;
  final String customerId;
  final String email;
  final String phone;
  final String propertyAddress;
  final String contractFrom;
  final String contractTo;

  bool get hasContract =>
      contractFrom.trim().isNotEmpty || contractTo.trim().isNotEmpty;

  factory AdvCustomer.fromJson(Map<String, dynamic> j) => AdvCustomer(
        name: (j['name'] ?? '').toString(),
        customerId: (j['customerId'] ?? '').toString(),
        email: (j['email'] ?? '').toString(),
        phone: (j['phone'] ?? '').toString(),
        propertyAddress: (j['propertyAddress'] ?? '').toString(),
        contractFrom: (j['contractFrom'] ?? '').toString(),
        contractTo: (j['contractTo'] ?? '').toString(),
      );
}

class AdvLineItem {
  AdvLineItem({
    this.date = '',
    this.reference = '',
    this.description = '',
    this.amount = 0,
  });

  final String date;
  final String reference;
  final String description;
  final double amount;

  factory AdvLineItem.fromJson(Map<String, dynamic> j) => AdvLineItem(
        date: (j['date'] ?? '').toString(),
        reference: (j['reference'] ?? '').toString(),
        description: (j['description'] ?? '').toString(),
        amount: AdvancedInvoice._toDouble(j['amount']),
      );
}

class AdvPaymentDetails {
  AdvPaymentDetails({
    this.bankName = '',
    this.accountNumber = '',
    this.branchCode = '',
    this.paymentReference = '',
    this.dueDate = '',
  });

  final String bankName;
  final String accountNumber;
  final String branchCode;
  final String paymentReference;
  final String dueDate;

  bool get hasAny =>
      [bankName, accountNumber, branchCode, paymentReference, dueDate]
          .any((s) => s.trim().isNotEmpty);

  factory AdvPaymentDetails.fromJson(Map<String, dynamic> j) =>
      AdvPaymentDetails(
        bankName: (j['bankName'] ?? '').toString(),
        accountNumber: (j['accountNumber'] ?? '').toString(),
        branchCode: (j['branchCode'] ?? '').toString(),
        paymentReference: (j['paymentReference'] ?? '').toString(),
        dueDate: (j['dueDate'] ?? '').toString(),
      );
}

class AdvCustomField {
  AdvCustomField({
    this.label = '',
    this.value = '',
    this.section = '',
    this.type = 'text',
    this.showOnPrint = true,
  });

  final String label;
  final String value;
  final String section;
  final String type;
  final bool showOnPrint;

  factory AdvCustomField.fromJson(Map<String, dynamic> j) => AdvCustomField(
        label: (j['label'] ?? '').toString(),
        value: (j['value'] ?? '').toString(),
        section: (j['section'] ?? '').toString(),
        type: (j['type'] ?? 'text').toString(),
        showOnPrint: j['showOnPrint'] != false,
      );
}
