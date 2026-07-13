import 'task.dart';

/// A project with its embedded tasks (the backend stores tasks inside projects).
class Project {
  Project({
    required this.id,
    required this.name,
    required this.status,
    required this.tasks,
    this.color,
    this.description = '',
    this.clientId,
    this.clientName,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final String status; // active|planning|in_progress|completed|on_hold|cancelled
  final List<Task> tasks;
  final String? color;
  final String description;
  final String? clientId;
  final String? clientName; // denormalized for display (resolved client-side)
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// Total number of tasks in the project.
  int get taskCount => tasks.length;

  /// Number of completed (done) tasks.
  int get completedCount =>
      tasks.where((t) => t.status == 'done' || t.done).length;

  /// Number of tasks not yet done.
  int get pendingCount => tasks.length - completedCount;

  /// Completion fraction 0..1 (done tasks / total tasks).
  double get progress {
    if (tasks.isEmpty) return 0;
    return completedCount / tasks.length;
  }

  /// True if there's a description beyond whitespace.
  bool get hasDescription => description.trim().isNotEmpty;

  /// First letter of the name, uppercased, for the color avatar.
  String get initial =>
      name.trim().isEmpty ? 'P' : name.trim().substring(0, 1).toUpperCase();

  factory Project.fromJson(Map<String, dynamic> j) {
    final rawTasks = (j['tasks'] as List?) ?? const [];
    return Project(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? 'Untitled').toString(),
      status: (j['status'] ?? 'active').toString(),
      color: j['color']?.toString(),
      description: (j['description'] ?? '').toString(),
      clientId: j['clientId']?.toString(),
      clientName: j['clientName']?.toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
      tasks: rawTasks
          .whereType<Map>()
          .map((t) => Task.fromJson(t.cast<String, dynamic>()))
          .toList(),
    );
  }

  /// Build a JSON body suitable for POST/PUT to /api/projects.php.
  /// Only non-null fields are included so partial updates work.
  Map<String, dynamic> toBody() => {
        'name': name,
        if (description.isNotEmpty) 'description': description,
        if (clientId != null && clientId!.isNotEmpty) 'clientId': clientId,
        'status': status,
        if (color != null && color!.isNotEmpty) 'color': color,
      };

  Project copyWith({
    String? name,
    String? status,
    List<Task>? tasks,
    String? color,
    String? description,
    String? clientId,
    String? clientName,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Project(
      id: id,
      name: name ?? this.name,
      status: status ?? this.status,
      tasks: tasks ?? this.tasks,
      color: color ?? this.color,
      description: description ?? this.description,
      clientId: clientId ?? this.clientId,
      clientName: clientName ?? this.clientName,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  /// All project status values the backend uses (mobile/views/projects.php).
  static const statuses = <String>[
    'active',
    'planning',
    'in_progress',
    'on_hold',
    'completed',
    'cancelled',
  ];

  /// Status value → human-readable, title-cased label.
  static const statusLabels = <String, String>{
    'active': 'Active',
    'planning': 'Planning',
    'in_progress': 'In Progress',
    'on_hold': 'On Hold',
    'completed': 'Completed',
    'cancelled': 'Cancelled',
  };

  /// Title-case label for a status, normalising legacy variants
  /// ("On Hold" / "In Progress" / "Done") to the canonical key first.
  static String statusLabel(String raw) {
    final key = normalizeStatus(raw);
    return statusLabels[key] ??
        raw
            .replaceAll('_', ' ')
            .split(' ')
            .map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}')
            .join(' ');
  }

  /// Normalise legacy status strings to their canonical key.
  /// Mirrors mobile/views/view-project.php normalisation.
  static String normalizeStatus(String raw) {
    final s = raw.trim().toLowerCase();
    switch (s) {
      case 'in progress':
        return 'in_progress';
      case 'on hold':
        return 'on_hold';
      case 'done':
        return 'completed';
      default:
        return s;
    }
  }
}
