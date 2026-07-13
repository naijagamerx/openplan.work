import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/device.dart';

final devicesRepositoryProvider =
    Provider<DevicesRepository>((ref) => DevicesRepository(ref.watch(apiClientProvider)));

final devicesProvider =
    FutureProvider<List<DeviceSession>>((ref) => ref.watch(devicesRepositoryProvider).list());

class DevicesRepository {
  DevicesRepository(this._api);
  final ApiClient _api;

  Future<List<DeviceSession>> list() async {
    final data = await _api.get('/api/devices.php', query: {'action': 'list'});
    final raw = (data is Map ? data['devices'] : null) as List? ?? const [];
    return raw
        .whereType<Map>()
        .map((d) => DeviceSession.fromJson(d.cast<String, dynamic>()))
        .toList();
  }

  Future<void> revoke(String id) async {
    await _api.post('/api/devices.php', query: {'action': 'revoke'}, body: {'id': id});
  }
}
