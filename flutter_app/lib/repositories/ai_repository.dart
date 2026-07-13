import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../api/api_client.dart';
import '../auth/auth_controller.dart';
import '../models/chat_message.dart';

/// Provider for the AI Assistant repository. Backs the simple chat mode
/// (api/ai.php) and the agent/function-calling mode (api/ai-agent.php).
final aiRepositoryProvider = Provider<AIRepository>(
    (ref) => AIRepository(ref.watch(apiClientProvider)));

/// Available models per provider (groq/openrouter/gemini/ollama → list of model
/// ids), loaded from `GET /api/models.php`. Returns an empty map on failure so
/// the UI can fall back to a plain model text field.
final modelsProvider =
    FutureProvider.autoDispose<Map<String, List<String>>>((ref) async {
  // Stays cached while the AI screen is mounted (autoDispose releases it once
  // no widget is watching). Re-fetch on next mount keeps models fresh.
  return ref.watch(aiRepositoryProvider).fetchModels();
});

/// Result of an agent-mode chat: the textual reply plus any tool actions the
/// agent executed. `followUp` is empty unless the backend supplies suggestions.
class AgentChatResult {
  const AgentChatResult({
    required this.response,
    this.actions = const [],
    this.followUp = const [],
  });

  final String response;
  final List<ChatAction> actions;
  final List<String> followUp;
}

class AIRepository {
  AIRepository(this._api);

  final ApiClient _api;

  /// Simple chat (api/ai.php?action=chat). Returns the assistant's reply as a
  /// string, normalized from whichever key the backend used
  /// (`response`/`reply`/`message`/`content`).
  ///
  /// [history] is the prior `{role, content}` turns (excluding [message]); the
  /// repository composes the full `messages` array the backend expects.
  Future<String> chat(
    String message,
    List<Map<String, String>> history, {
    String provider = 'groq',
    String? model,
  }) async {
    final messages = <Map<String, String>>[
      ...history,
      {'role': 'user', 'content': message},
    ];
    final data = await _api.post('/api/ai.php', query: {'action': 'chat'}, body: {
      'messages': messages,
      if (model != null && model.isNotEmpty) 'model': model,
      'provider': provider,
    });
    return _extractReply(data);
  }

  /// Notes AI chat (api/ai.php?action=notes_chat). Like [chat], but the backend
  /// grounds the reply in a specific note — it loads the note by [noteId] and
  /// injects its title + content (and, when [includeAllNotes], every note title)
  /// as a system prompt. The client only sends the note id, not its body.
  Future<String> notesChat(
    String message,
    List<Map<String, String>> history, {
    String? noteId,
    bool includeAllNotes = false,
    String provider = 'groq',
    String? model,
  }) async {
    final messages = <Map<String, String>>[
      ...history,
      {'role': 'user', 'content': message},
    ];
    final data = await _api.post('/api/ai.php',
        query: {'action': 'notes_chat'},
        body: {
          if (noteId != null && noteId.isNotEmpty) 'noteId': noteId,
          'messages': messages,
          'includeAllNotes': includeAllNotes,
          if (model != null && model.isNotEmpty) 'model': model,
          'provider': provider,
        });
    return _extractReply(data);
  }

  /// Agent / function-calling chat (api/ai-agent.php?action=chat). Creates a
  /// conversation on demand if [conversationId] is null.
  Future<AgentChatResult> agentChat(
    String message, {
    String? conversationId,
    String provider = 'groq',
    String? model,
  }) async {
    final data = await _api.post('/api/ai-agent.php', query: {'action': 'chat'}, body: {
      'message': message,
      'conversationId': conversationId,
      'provider': provider,
      if (model != null && model.isNotEmpty) 'model': model,
      'autoConfirm': true,
    });
    final map = data is Map ? data.cast<String, dynamic>() : <String, dynamic>{};
    final reply = _extractReply(map);
    final actions = ChatMessage.actionsFromBackend(
      (map['functionCalls'] ?? map['actions']) as List?,
    );
    final followUp = _stringList(map['followUp'] ?? map['follow_up']);
    return AgentChatResult(response: reply, actions: actions, followUp: followUp);
  }

  /// Clear a single conversation's messages
  /// (api/ai-agent.php?action=clear_history).
  Future<void> clearHistory(String conversationId) async {
    await _api.post('/api/ai-agent.php', query: {'action': 'clear_history'},
        body: {'conversationId': conversationId});
  }

  /// Start a fresh conversation and return its id
  /// (api/ai-agent.php?action=start_conversation).
  Future<String> startConversation() async {
    final data = await _api.post('/api/ai-agent.php',
        query: {'action': 'start_conversation'}, body: <String, dynamic>{});
    final map = data is Map ? data.cast<String, dynamic>() : <String, dynamic>{};
    final id = (map['conversationId'] ?? map['id']).toString();
    if (id.isEmpty || id == 'null') {
      throw Exception('No conversationId returned');
    }
    return id;
  }

  /// Fetch available models grouped by provider (GET /api/models.php).
  /// Each provider maps to its enabled model ids (display name preferred when
  /// present). Returns an empty map on failure.
  Future<Map<String, List<String>>> fetchModels() async {
    try {
      final data = await _api.get('/api/models.php');
      final map = data is Map ? data.cast<String, dynamic>() : <String, dynamic>{};
      final out = <String, List<String>>{};
      for (final provider in const ['groq', 'openrouter', 'gemini', 'ollama']) {
        final raw = map[provider];
        if (raw is! List) continue;
        final ids = <String>[];
        for (final entry in raw) {
          if (entry is! Map) continue;
          final enabled = entry['enabled'];
          if (enabled == false) continue; // omit disabled models
          final id = (entry['modelId'] ?? entry['id'] ?? '').toString();
          if (id.isNotEmpty) ids.add(id);
        }
        if (ids.isNotEmpty) out[provider] = ids;
      }
      return out;
    } catch (e) {
      debugPrint('[AI] fetchModels failed: $e');
      return {};
    }
  }

  /// The backend replies under varying keys depending on the endpoint and the
  /// upstream provider wrapper. Try them in priority order.
  static String _extractReply(dynamic data) {
    if (data is String) return data;
    if (data is! Map) return '';
    for (final key in const ['response', 'reply', 'message', 'content']) {
      final v = data[key];
      if (v is String && v.trim().isNotEmpty) return v;
      if (v is Map) {
        // Some providers nest under choices[].message.content; fall through.
      }
    }
    // Fallback: unwrap an OpenAI-style payload if present.
    final choices = data['choices'];
    if (choices is List && choices.isNotEmpty) {
      final first = choices.first;
      if (first is Map) {
        final msg = first['message'];
        if (msg is Map) {
          final c = msg['content'];
          if (c is String && c.isNotEmpty) return c;
        }
      }
    }
    return '';
  }

  static List<String> _stringList(dynamic v) {
    if (v is! List) return const [];
    return v.map((e) => e.toString()).where((s) => s.isNotEmpty).toList();
  }
}
