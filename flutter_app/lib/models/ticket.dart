/// A support ticket + its message thread, as returned by
/// `GET /api/tickets.php?all=1` / `GET /api/tickets.php?id=X`.
///
/// Mirrors the backend schema (see TicketsAPI). Status lifecycle:
///   open → awaiting_admin → awaiting_user → closed
/// Each ticket carries a `messages[]` thread with the author's role, which
/// drives left/right alignment in the chat UI.
class Ticket {
  Ticket({
    required this.id,
    required this.userId,
    required this.subject,
    required this.status,
    required this.messages,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final String subject;
  final String status; // open|awaiting_admin|awaiting_user|closed
  final List<TicketMessage> messages;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  /// Whether the admin still needs to act (reply or close).
  bool get isOpen => status != 'closed';

  factory Ticket.fromJson(Map<String, dynamic> j) {
    final rawMsgs = (j['messages'] as List?) ?? const [];
    return Ticket(
      id: (j['id'] ?? '').toString(),
      userId: (j['userId'] ?? '').toString(),
      subject: (j['subject'] ?? '').toString(),
      status: (j['status'] ?? 'open').toString(),
      messages: rawMsgs
          .whereType<Map>()
          .map((m) => TicketMessage.fromJson(m.cast<String, dynamic>()))
          .toList(),
      createdAt: _parseDate(j['createdAt']),
      updatedAt: _parseDate(j['updatedAt']),
    );
  }

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}

/// One message in a ticket thread.
class TicketMessage {
  TicketMessage({
    required this.body,
    required this.authorRole,
    this.createdAt,
  });

  final String body;
  final String authorRole; // user | admin
  final DateTime? createdAt;

  /// Admin-authored messages align right in the chat UI.
  bool get fromAdmin => authorRole == 'admin';

  factory TicketMessage.fromJson(Map<String, dynamic> j) => TicketMessage(
        body: (j['body'] ?? '').toString(),
        authorRole: (j['authorRole'] ?? 'user').toString(),
        createdAt: _parseDate(j['createdAt']),
      );

  static DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    return DateTime.tryParse(v.toString());
  }
}
