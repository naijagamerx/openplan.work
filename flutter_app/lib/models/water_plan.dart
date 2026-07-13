/// A water hydration plan. Mirrors the PHP backend schema stored in the
/// `water_plans` (active/manual) and `water_plan_history` (archived) collections.
///
/// See AUDIT_03 §1 and `api/habits.php` (create_manual_plan / generate_water_plan).
class WaterPlan {
  WaterPlan({
    required this.id,
    required this.name,
    required this.dailyGoalMl,
    required this.glassSizeMl,
    required this.isActive,
    required this.schedule,
    this.type,
    this.source,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final int dailyGoalMl; // ml
  final int glassSizeMl; // ml
  final bool isActive;
  final List<WaterReminder> schedule;
  final String? type; // 'manual' | null
  final String? source; // 'manual' | 'history' (added by list_water_plan_history)
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// Total ml planned across all reminders.
  int get plannedMl =>
      schedule.fold(0, (sum, r) => sum + r.resolvedAmountMl(glassSizeMl));

  /// Total ml completed across all reminders.
  int get completedMl => schedule
      .where((r) => r.completed)
      .fold(0, (sum, r) => sum + r.resolvedAmountMl(glassSizeMl));

  /// Count of missed reminders.
  int get missedCount => schedule.where((r) => r.missed).length;

  /// Count of completed reminders.
  int get completedCount => schedule.where((r) => r.completed).length;

  /// Completion fraction 0..1 of planned intake.
  double get progress {
    final planned = plannedMl;
    if (planned <= 0) return 0;
    final r = completedMl / planned;
    if (r < 0) return 0;
    if (r > 1) return 1;
    return r;
  }

  /// Goal-attainment fraction 0..1 (completed vs daily goal).
  double get goalProgress {
    if (dailyGoalMl <= 0) return 0;
    final r = completedMl / dailyGoalMl;
    if (r < 0) return 0;
    if (r > 1) return 1;
    return r;
  }

  double get litersPlanned => plannedMl / 1000;
  double get litersCompleted => completedMl / 1000;
  double get litersGoal => dailyGoalMl / 1000;

  factory WaterPlan.fromJson(Map<String, dynamic> j) {
    final rawSchedule = (j['schedule'] as List?) ?? const [];
    return WaterPlan(
      id: (j['id'] ?? '').toString(),
      name: (j['name'] ?? 'My Water Plan').toString(),
      dailyGoalMl: _toInt(j['dailyGoal'], 2000),
      glassSizeMl: _toInt(j['glassSize'], 250),
      isActive: j['isActive'] == true,
      type: j['type']?.toString(),
      source: j['source']?.toString(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
      schedule: rawSchedule
          .whereType<Map>()
          .map((s) => WaterReminder.fromJson(s.cast<String, dynamic>()))
          .toList(),
    );
  }

  /// Build a JSON body for create/update_manual_plan.
  /// `schedule[].amount` is sent in ml (the server normalizes legacy glass
  /// units ≤20, so ml is always safe).
  Map<String, dynamic> toBody({String? planId}) => {
        if (planId != null) 'planId': planId,
        'name': name,
        'dailyGoal': dailyGoalMl,
        'glassSize': glassSizeMl,
        'schedule': schedule
            .map((r) => {
                  if (r.id.isNotEmpty) 'id': r.id,
                  'time': r.time,
                  'amount': r.amountMl,
                })
            .toList(),
      };

  WaterPlan copyWith({
    String? name,
    int? dailyGoalMl,
    int? glassSizeMl,
    bool? isActive,
    List<WaterReminder>? schedule,
  }) =>
      WaterPlan(
        id: id,
        name: name ?? this.name,
        dailyGoalMl: dailyGoalMl ?? this.dailyGoalMl,
        glassSizeMl: glassSizeMl ?? this.glassSizeMl,
        isActive: isActive ?? this.isActive,
        schedule: schedule ?? this.schedule,
        type: type,
        source: source,
        createdAt: createdAt,
        updatedAt: updatedAt,
      );

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static int _toInt(dynamic v, [int def = 0]) {
    if (v == null) return def;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? def;
  }
}

/// A single scheduled drink reminder inside a [WaterPlan].
class WaterReminder {
  WaterReminder({
    required this.id,
    required this.time, // "HH:mm"
    required this.amountMl,
    this.completed = false,
    this.missed = false,
    this.completedAt,
    this.lastNotifiedAt,
  });

  final String id;
  final String time;
  final int amountMl; // stored ml
  final bool completed;
  final bool missed;
  final DateTime? completedAt;
  final DateTime? lastNotifiedAt;

  factory WaterReminder.fromJson(Map<String, dynamic> j) {
    final glass = _toInt(j['glassSize'], 250);
    return WaterReminder(
      id: (j['id'] ?? '').toString(),
      time: (j['time'] ?? '').toString(),
      amountMl: _resolveAmount(j['amount'], glass),
      completed: j['completed'] == true,
      missed: j['missed'] == true,
      completedAt: _parseDate(j['completedAt']),
      lastNotifiedAt: _parseDate(j['lastNotifiedAt']),
    );
  }

  /// Resolve the backend's ambiguous `amount` field to ml.
  /// Legacy plans stored glasses (1,2,…) — values ≤20 are treated as glass
  /// multiples; anything larger is already ml. Mirrors the PHP
  /// `normalizeWaterAmountMl()` quirk (api/habits.php:28).
  static int _resolveAmount(dynamic raw, int glassSizeMl) {
    final glass = glassSizeMl > 0 ? glassSizeMl : 250;
    final value = (raw is num ? raw.toDouble() : double.tryParse('$raw') ?? 0);
    if (value <= 0) return glass;
    if (value <= 20) return (value * glass).round();
    return value.round();
  }

  /// Amount in ml given a plan's glass size (used when amountMl is 0/unknown).
  int resolvedAmountMl(int glassSizeMl) =>
      amountMl > 0 ? amountMl : (glassSizeMl > 0 ? glassSizeMl : 250);

  double get liters => amountMl / 1000;

  WaterReminder copyWith({
    String? time,
    int? amountMl,
    bool? completed,
    bool? missed,
  }) =>
      WaterReminder(
        id: id,
        time: time ?? this.time,
        amountMl: amountMl ?? this.amountMl,
        completed: completed ?? this.completed,
        missed: missed ?? this.missed,
        completedAt: completedAt,
        lastNotifiedAt: lastNotifiedAt,
      );

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }

  static int _toInt(dynamic v, [int def = 0]) {
    if (v == null) return def;
    if (v is num) return v.toInt();
    return int.tryParse(v.toString()) ?? def;
  }
}
