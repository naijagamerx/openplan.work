/// A task as returned embedded inside a project by `GET /api/tasks.php`.
/// Mirrors the backend schema (see includes/TasksAPI.php::getAllowedFields).
class Task {
  Task({
    required this.id,
    required this.title,
    required this.status,
    required this.priority,
    required this.done,
    this.description = '',
    this.subtasks = const [],
    this.dueDate,
    this.startDate,
    this.estimatedMinutes = 0,
    this.actualMinutes = 0,
    this.createdAt,
    this.completedAt,
    this.updatedAt,
    this.projectId,
    this.projectName,
  });

  final String id;
  final String title;
  final String status; // backlog|todo|in_progress|review|done
  final String priority; // low|medium|high|urgent
  final bool done;
  final String description;
  final List<Subtask> subtasks;
  final DateTime? dueDate;
  final DateTime? startDate;
  final int estimatedMinutes;
  final int actualMinutes;
  final DateTime? createdAt;
  final DateTime? completedAt;
  final DateTime? updatedAt;
  final String? projectId;
  final String? projectName; // denormalized for flat list display

  /// True if there's a description beyond whitespace.
  bool get hasDescription => description.trim().isNotEmpty;

  /// Subtask completion fraction 0..1.
  double get subtaskProgress {
    if (subtasks.isEmpty) return 0;
    final done = subtasks.where((s) => s.completed).length;
    return done / subtasks.length;
  }

  factory Task.fromJson(Map<String, dynamic> j) {
    final status = (j['status'] ?? 'todo').toString();
    final rawSubs = (j['subtasks'] as List?) ?? const [];
    return Task(
      id: (j['id'] ?? '').toString(),
      title: (j['title'] ?? '').toString(),
      status: status,
      priority: (j['priority'] ?? 'medium').toString(),
      done: status == 'done' || j['completedAt'] != null,
      description: (j['description'] ?? '').toString(),
      subtasks: rawSubs
          .whereType<Map>()
          .map((s) => Subtask.fromJson(s.cast<String, dynamic>()))
          .toList(),
      dueDate: _parseDate(j['dueDate']),
      startDate: _parseDate(j['startDate']),
      estimatedMinutes: _toInt(j['estimatedMinutes']),
      actualMinutes: _toInt(j['actualMinutes']),
      createdAt: _parseDate(j['createdAt']),
      completedAt: _parseDate(j['completedAt']),
      updatedAt: _parseDate(j['updatedAt']),
      projectId: j['projectId']?.toString(),
    );
  }

  /// Build a JSON body suitable for POST/PUT to /api/tasks.php.
  Map<String, dynamic> toBody({String? projectId}) => {
        if (projectId != null) 'projectId': projectId,
        'title': title,
        if (description.isNotEmpty) 'description': description,
        'status': status,
        'priority': priority,
        if (dueDate != null)
          'dueDate': dueDate!.toUtc().toIso8601String(),
        if (startDate != null)
          'startDate': startDate!.toUtc().toIso8601String(),
        'estimatedMinutes': estimatedMinutes,
        'actualMinutes': actualMinutes,
        'subtasks': subtasks.map((s) => s.toBody()).toList(),
      };

  Task copyWith({
    String? title,
    String? status,
    String? priority,
    String? description,
    List<Subtask>? subtasks,
    DateTime? dueDate,
    DateTime? startDate,
    int? estimatedMinutes,
    int? actualMinutes,
    bool clearDue = false,
    bool clearStart = false,
  }) {
    return Task(
      id: id,
      title: title ?? this.title,
      status: status ?? this.status,
      priority: priority ?? this.priority,
      done: (status ?? this.status) == 'done',
      description: description ?? this.description,
      subtasks: subtasks ?? this.subtasks,
      dueDate: clearDue ? null : (dueDate ?? this.dueDate),
      startDate: clearStart ? null : (startDate ?? this.startDate),
      estimatedMinutes: estimatedMinutes ?? this.estimatedMinutes,
      actualMinutes: actualMinutes ?? this.actualMinutes,
      createdAt: createdAt,
      completedAt: completedAt,
      updatedAt: updatedAt,
      projectId: projectId,
      projectName: projectName,
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
}

/// A subtask (checklist item) inside a task.
class Subtask {
  Subtask({
    required this.id,
    required this.title,
    this.completed = false,
    this.estimatedMinutes = 0,
  });

  final String id;
  final String title;
  final bool completed;
  final int estimatedMinutes;

  factory Subtask.fromJson(Map<String, dynamic> j) => Subtask(
        id: (j['id'] ?? '').toString(),
        title: (j['title'] ?? '').toString(),
        completed: j['completed'] == true,
        estimatedMinutes: j['estimatedMinutes'] is num
            ? (j['estimatedMinutes'] as num).toInt()
            : 0,
      );

  Map<String, dynamic> toBody() => {
        if (id.isNotEmpty) 'id': id,
        'title': title,
        'completed': completed,
        'estimatedMinutes': estimatedMinutes,
      };

  Subtask copyWith({String? title, bool? completed, int? estimatedMinutes}) =>
      Subtask(
        id: id,
        title: title ?? this.title,
        completed: completed ?? this.completed,
        estimatedMinutes: estimatedMinutes ?? this.estimatedMinutes,
      );
}
