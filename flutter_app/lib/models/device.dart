/// A device session from `GET /api/devices.php?action=list`.
class DeviceSession {
  DeviceSession({
    required this.id,
    required this.name,
    required this.platform,
    required this.last4,
    required this.lastUsedAt,
  });

  final String id;
  final String name;
  final String platform;
  final String last4;
  final String lastUsedAt;

  factory DeviceSession.fromJson(Map<String, dynamic> j) => DeviceSession(
        id: (j['id'] ?? '').toString(),
        name: (j['name'] ?? 'Device').toString(),
        platform: (j['platform'] ?? '').toString(),
        last4: (j['last4'] ?? '').toString(),
        lastUsedAt: (j['lastUsedAt'] ?? '').toString(),
      );
}
