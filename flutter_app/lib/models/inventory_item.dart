/// An inventory product as returned by `GET/POST/PUT/DELETE /api/inventory.php`.
///
/// The backend (`inventory.php`) stores these under the field names
/// `quantity`, `unitPrice`, `costPrice`, `reorderPoint`. The legacy PHP mobile
/// UI tolerated both the older (`stock`/`price`/`cost`/`minStock`) and current
/// names, so [fromJson] accepts either to stay robust against existing data.
class InventoryItem {
  InventoryItem({
    required this.id,
    required this.name,
    this.sku = '',
    required this.stock,
    required this.minStock,
    required this.price,
    required this.cost,
    this.category = '',
    this.description = '',
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final String sku;

  /// On-hand units.
  final int stock;

  /// Reorder threshold (low-stock when stock <= minStock).
  final int minStock;

  /// Sell price per unit.
  final double price;

  /// Acquisition cost per unit.
  final double cost;
  final String category;
  final String description;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// True when on-hand stock is at or below the reorder threshold.
  bool get isLowStock => stock <= minStock;

  /// Current inventory value: stock × sell price.
  double get value => stock * price;

  /// Unrealised profit if all units sold at cost: stock × (price − cost).
  double get potentialProfit => stock * (price - cost);

  factory InventoryItem.fromJson(Map<String, dynamic> j) {
    return InventoryItem(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? '').toString(),
      // SKU is sometimes stored under `code` in legacy rows.
      sku: (j['sku'] ?? j['code'] ?? '').toString(),
      stock: _toInt(j['stock'] ?? j['quantity']),
      minStock: _toInt(j['minStock'] ?? j['reorderPoint'] ?? 5),
      price: _toDouble(j['price'] ?? j['unitPrice']),
      cost: _toDouble(j['cost'] ?? j['costPrice']),
      category: (j['category'] ?? '').toString(),
      description: (j['description'] ?? '').toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  /// Build a JSON body suitable for POST/PUT to /api/inventory.php, using the
  /// backend's canonical field names.
  Map<String, dynamic> toBody() => {
        'name': name,
        if (sku.isNotEmpty) 'sku': sku,
        'quantity': stock,
        'reorderPoint': minStock,
        'unitPrice': price,
        'costPrice': cost,
        if (category.isNotEmpty) 'category': category,
        if (description.isNotEmpty) 'description': description,
      };

  InventoryItem copyWith({
    String? name,
    String? sku,
    int? stock,
    int? minStock,
    double? price,
    double? cost,
    String? category,
    String? description,
  }) {
    return InventoryItem(
      id: id,
      name: name ?? this.name,
      sku: sku ?? this.sku,
      stock: stock ?? this.stock,
      minStock: minStock ?? this.minStock,
      price: price ?? this.price,
      cost: cost ?? this.cost,
      category: category ?? this.category,
      description: description ?? this.description,
      createdAt: createdAt,
      updatedAt: updatedAt,
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static int _toInt(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? 0;
  }

  static double _toDouble(dynamic v) {
    if (v == null) return 0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString()) ?? 0;
  }
}
