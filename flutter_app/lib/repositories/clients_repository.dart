import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/client.dart';

final clientsRepositoryProvider =
    Provider<ClientsRepository>((ref) => ClientsRepository(ref.watch(apiClientProvider)));

/// All clients. Auto-refreshable from the UI (mirrors habitsListProvider).
final clientsProvider =
    FutureProvider<List<Client>>((ref) => ref.watch(clientsRepositoryProvider).list());

class ClientsRepository {
  ClientsRepository(this._api);
  final ApiClient _api;

  /// List all clients (sorted client-side by name, like mobile/views/clients.php).
  Future<List<Client>> list() async {
    final data = await _api.get('/api/clients.php');
    final clients = ((data as List?) ?? const [])
        .whereType<Map>()
        .map((c) => Client.fromJson(c.cast<String, dynamic>()))
        .toList();
    clients.sort((a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()));
    return clients;
  }

  /// Fetch a single client by id.
  Future<Client?> fetch(String id) async {
    final data = await _api.get('/api/clients.php', query: {'id': id});
    if (data == null) return null;
    if (data is Map) {
      return Client.fromJson(data.cast<String, dynamic>());
    }
    // Fallback: some envelopes wrap a single record in a list.
    if (data is List && data.isNotEmpty) {
      final first = data.first;
      if (first is Map) return Client.fromJson(first.cast<String, dynamic>());
    }
    return null;
  }

  Future<void> create({
    required String name,
    required String email,
    String phone = '',
    String company = '',
    String website = '',
    String address = '',
    String notes = '',
  }) async {
    await _api.post('/api/clients.php', query: {'action': 'add'}, body: {
      'name': name,
      'email': email,
      if (phone.isNotEmpty) 'phone': phone,
      if (company.isNotEmpty) 'company': company,
      if (website.isNotEmpty) 'website': website,
      if (address.isNotEmpty) 'address': address,
      if (notes.isNotEmpty) 'notes': notes,
    });
  }

  Future<void> update(
    String id, {
    String? name,
    String? email,
    String? phone,
    String? company,
    String? website,
    String? address,
    String? notes,
  }) async {
    await _api.put('/api/clients.php', query: {'id': id}, body: {
      if (name != null) 'name': name,
      if (email != null) 'email': email,
      if (phone != null) 'phone': phone,
      if (company != null) 'company': company,
      if (website != null) 'website': website,
      if (address != null) 'address': address,
      if (notes != null) 'notes': notes,
    });
  }

  Future<void> delete(String id) async {
    await _api.delete('/api/clients.php', query: {'id': id});
  }
}
