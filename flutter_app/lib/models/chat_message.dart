/// A single message in the AI Assistant chat. A simple immutable value class
/// (mirrors the PHP mobile app's `chatHistory` rows + agent action metadata).
///
/// The backend replies under varying keys (`response` / `reply` / `message` /
/// `content`) — the repository normalizes those into [content] here.
class ChatMessage {
  const ChatMessage({
    required this.role,
    required this.content,
    this.isThinking = false,
    this.actions = const [],
  });

  /// `user` (the human) or `assistant` (the AI).
  final String role;

  /// Raw text body (markdown is rendered as plain text with line breaks).
  final String content;

  /// True only for the transient "Thinking…" placeholder bubble.
  final bool isThinking;

  /// Agent-mode tool calls attached to an assistant reply: `{name, success,
  /// message}`. Empty in simple chat mode. Rendered as small chips below the
  /// message bubble.
  final List<ChatAction> actions;

  ChatMessage copyWith({
    String? role,
    String? content,
    bool? isThinking,
    List<ChatAction>? actions,
  }) =>
      ChatMessage(
        role: role ?? this.role,
        content: content ?? this.content,
        isThinking: isThinking ?? this.isThinking,
        actions: actions ?? this.actions,
      );

  /// Serialize back to the `{role, content}` shape the chat API expects in its
  /// `history` / `messages` array.
  Map<String, dynamic> toHistoryJson() => {'role': role, 'content': content};

  /// Build actions from a raw backend action list. The agent returns each tool
  /// call as `{name, summary?, error?}` (see includes/AIAgent.php); we coerce
  /// that into the `{name, success, message}` triplet the UI renders.
  static List<ChatAction> actionsFromBackend(List? raw) {
    if (raw == null || raw.isEmpty) return const [];
    return raw
        .whereType<Map>()
        .map((m) {
          final map = m.cast<String, dynamic>();
          final hasError =
              (map['error']?.toString() ?? '').isNotEmpty ||
              map['status'] == 'failed' ||
              map['success'] == false;
          return ChatAction(
            name: (map['name'] ?? 'tool').toString(),
            success: !hasError,
            message: (map['summary'] ??
                    map['message'] ??
                    map['error'] ??
                    '')
                .toString(),
          );
        })
        .toList(growable: false);
  }
}

/// One agent-mode action/tool-call chip rendered under an assistant message.
class ChatAction {
  const ChatAction({
    required this.name,
    required this.success,
    required this.message,
  });

  final String name;
  final bool success;
  final String message;
}
