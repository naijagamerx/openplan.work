import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/inventory_item.dart';

final inventoryRepositoryProvider = Provider<InventoryRepository>(
    (ref) => InventoryRepository(ref.watch(apiClientProvider)));

/// All inventory products. Auto-refreshable from the UI.
final inventoryProvider =
    FutureProvider<List<InventoryItem>>((ref) async {
  return ref.watch(inventoryRepositoryProvider).list();
});

class InventoryRepository {
  InventoryRepository(this._api);
  final ApiClient _api;

  /// List every product (sorted A-Z by the backend).
  Future<List<InventoryItem>> list() async {
    final data = await _api.get('/api/inventory.php');
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((m) => InventoryItem.fromJson(m.cast<String, dynamic>()))
        .toList();
  }

  /// Fetch a single product by id (list-scanned; the API has no per-id GET).
  Future<InventoryItem?> fetch(String id) async {
    final all = await list();
    for (final item in all) {
      if (item.id == id) return item;
    }
    return null;
  }

  /// Create a new product.
  Future<void> create({
    required String name,
    String sku = '',
    int stock = 0,
    int minStock = 5,
    double price = 0,
    double cost = 0,
    String category = '',
    String description = '',
  }) async {
    await _api.post('/api/inventory.php', query: {'action': 'add'}, body: {
      'name': name,
      if (sku.isNotEmpty) 'sku': sku,
      'quantity': stock,
      'reorderPoint': minStock,
      'unitPrice': price,
      'costPrice': cost,
      if (category.isNotEmpty) 'category': category,
      if (description.isNotEmpty) 'description': description,
    });
  }

  /// Update an existing product's editable fields.
  Future<void> update({
    required String id,
    String? name,
    String? sku,
    int? stock,
    int? minStock,
    double? price,
    double? cost,
    String? category,
    String? description,
  }) async {
    await _api.put('/api/inventory.php', query: {'id': id}, body: {
      if (name != null) 'name': name,
      if (sku != null) 'sku': sku,
      if (stock != null) 'quantity': stock,
      if (minStock != null) 'reorderPoint': minStock,
      if (price != null) 'unitPrice': price,
      if (cost != null) 'costPrice': cost,
      if (category != null) 'category': category,
      if (description != null) 'description': description,
    });
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/inventory.php', query: {'id': id});
  }

  /// Adjust on-hand stock. [type] is `'in'` or `'out'`; [quantity] is a
  /// positive number of units. Returns when complete.
  Future<void> adjustStock(
    String id,
    String type,
    int quantity, {
    double? unitCost,
    String note = '',
  }) async {
    final isOut = type == 'out';
    final adjustment = isOut ? -quantity.abs() : quantity.abs();
    await _api.post('/api/inventory.php', query: {'action': 'adjust'}, body: {
      'id': id,
      'adjustment': adjustment,
      if (note.isNotEmpty) 'note': note,
      if (unitCost != null) 'unitCost': unitCost,
    });
  }

  /// Raw transaction history for a product (newest first).
  /// Each entry: {id, productId, productName, type('in'|'out'), quantity,
  /// unitCost, totalCost, note, createdAt}.
  Future<List<Map<String, dynamic>>> history(String id) async {
    final data = await _api.get('/api/inventory.php', query: {
      'action': 'transactions',
      'productId': id,
    });
    return ((data as List?) ?? const [])
        .whereType<Map>()
        .map((m) => Map<String, dynamic>.from(m))
        .toList();
  }
}
