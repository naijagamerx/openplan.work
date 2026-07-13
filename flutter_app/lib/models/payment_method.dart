/// A payment method as returned by `GET api/payment-methods.php`.
/// Mirrors the backend schema (see api/payment-methods.php + PaymentMethodsAPI.php).
///
/// A `type` discriminator (bank | crypto) selects which field group is in use:
/// bank → {bankName, accountName, accountNumber, branchCode, swift};
/// crypto → {network, walletAddress}. `instructions` + `active` apply to both.
class PaymentMethod {
  PaymentMethod({
    required this.id,
    required this.type,
    required this.label,
    this.bankName = '',
    this.accountName = '',
    this.accountNumber = '',
    this.branchCode = '',
    this.swift = '',
    this.network = '',
    this.walletAddress = '',
    this.instructions = '',
    this.active = true,
  });

  final String id;
  final String type; // bank | crypto
  final String label;
  final String bankName;
  final String accountName;
  final String accountNumber;
  final String branchCode;
  final String swift;
  final String network;
  final String walletAddress;
  final String instructions;
  final bool active;

  bool get isBank => type == 'bank';
  bool get isCrypto => type == 'crypto';

  /// True if there are instructions beyond whitespace.
  bool get hasInstructions => instructions.trim().isNotEmpty;

  factory PaymentMethod.fromJson(Map<String, dynamic> j) {
    return PaymentMethod(
      id: (j['id'] ?? '').toString(),
      type: (j['type'] ?? 'bank').toString(),
      label: (j['label'] ?? '').toString(),
      bankName: (j['bankName'] ?? j['bank_name'] ?? '').toString(),
      accountName: (j['accountName'] ?? j['account_name'] ?? '').toString(),
      accountNumber: (j['accountNumber'] ?? j['account_number'] ?? '').toString(),
      branchCode: (j['branchCode'] ?? j['branch_code'] ?? '').toString(),
      swift: (j['swift'] ?? j['iban'] ?? '').toString(),
      network: (j['network'] ?? '').toString(),
      walletAddress: (j['walletAddress'] ?? j['wallet_address'] ?? '').toString(),
      instructions: (j['instructions'] ?? '').toString(),
      active: j['active'] != false,
    );
  }

  /// Build a JSON body suitable for POST/PUT to api/payment-methods.php.
  Map<String, dynamic> toBody() => {
        'type': type,
        'label': label,
        if (bankName.isNotEmpty) 'bankName': bankName,
        if (accountName.isNotEmpty) 'accountName': accountName,
        if (accountNumber.isNotEmpty) 'accountNumber': accountNumber,
        if (branchCode.isNotEmpty) 'branchCode': branchCode,
        if (swift.isNotEmpty) 'swift': swift,
        if (network.isNotEmpty) 'network': network,
        if (walletAddress.isNotEmpty) 'walletAddress': walletAddress,
        if (instructions.isNotEmpty) 'instructions': instructions,
        'active': active,
      };

  PaymentMethod copyWith({
    String? type,
    String? label,
    String? bankName,
    String? accountName,
    String? accountNumber,
    String? branchCode,
    String? swift,
    String? network,
    String? walletAddress,
    String? instructions,
    bool? active,
  }) {
    return PaymentMethod(
      id: id,
      type: type ?? this.type,
      label: label ?? this.label,
      bankName: bankName ?? this.bankName,
      accountName: accountName ?? this.accountName,
      accountNumber: accountNumber ?? this.accountNumber,
      branchCode: branchCode ?? this.branchCode,
      swift: swift ?? this.swift,
      network: network ?? this.network,
      walletAddress: walletAddress ?? this.walletAddress,
      instructions: instructions ?? this.instructions,
      active: active ?? this.active,
    );
  }
}
