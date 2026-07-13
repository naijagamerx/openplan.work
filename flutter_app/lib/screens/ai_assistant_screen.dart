import 'package:flutter/material.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:markdown/markdown.dart' as md;

import '../models/chat_message.dart';
import '../repositories/ai_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';

/// AI Assistant chat screen — feature parity with the PHP mobile app's
/// ai-assistant page. Plain Scaffold + AppBar (not a primary tab); the menu hub
/// pushes `/ai-assistant`.
///
/// Conversation lives in local state only (not persisted). Two modes:
///  - Chat (api/ai.php?action=chat): stateless, history sent each turn.
///  - Agent (api/ai-agent.php): function-calling with a server conversationId.
class AiAssistantScreen extends ConsumerStatefulWidget {
  const AiAssistantScreen({super.key});

  @override
  ConsumerState<AiAssistantScreen> createState() => _AiAssistantScreenState();
}

class _AiAssistantScreenState extends ConsumerState<AiAssistantScreen> {
  final _input = TextEditingController();
  final _scrollController = ScrollController();
  final _modelTextController = TextEditingController();
  final _messages = <ChatMessage>[];
  final _history = <Map<String, String>>[]; // prior turns for simple chat

  String _provider = 'groq';
  String? _model; // null until a model is chosen / loaded
  bool _agentMode = false;
  bool _sending = false;
  String? _conversationId; // agent mode only
  bool _modelsFailed = false;

  @override
  void initState() {
    super.initState();
    _input.addListener(() {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _input.dispose();
    _scrollController.dispose();
    _modelTextController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 220),
        curve: Curves.easeOutCubic,
      );
    });
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    if (text.isEmpty || _sending) return;
    _input.clear();
    setState(() {
      _messages.add(ChatMessage(role: 'user', content: text));
      _messages.add(const ChatMessage(
          role: 'assistant', content: '', isThinking: true));
      _sending = true;
    });
    _scrollToBottom();

    final effectiveModel = _effectiveModel();
    final repo = ref.read(aiRepositoryProvider);
    try {
      if (_agentMode) {
        await _sendAgent(repo, text, effectiveModel);
      } else {
        await _sendChat(repo, text, effectiveModel);
      }
    } catch (e) {
      _replaceThinking(ChatMessage(
          role: 'assistant', content: 'Error: $e'));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _sendChat(AIRepository repo, String text, String? model) async {
    final reply =
        await repo.chat(text, _history, provider: _provider, model: model);
    _history.addAll([
      {'role': 'user', 'content': text},
      {'role': 'assistant', 'content': reply},
    ]);
    _replaceThinking(
        ChatMessage(role: 'assistant', content: reply));
  }

  Future<void> _sendAgent(
      AIRepository repo, String text, String? model) async {
    if (_conversationId == null) {
      try {
        _conversationId = await repo.startConversation();
      } catch (_) {
        // Some backends create the conversation on first chat; continue without.
      }
    }
    final result = await repo.agentChat(text,
        conversationId: _conversationId,
        provider: _provider,
        model: model);
    _conversationId =
        null; // backend returns the id inside the result envelope if needed
    _replaceThinking(ChatMessage(
      role: 'assistant',
      content: result.response.isEmpty
          ? '(No textual response)'
          : result.response,
      actions: result.actions,
    ));
  }

  void _replaceThinking(ChatMessage msg) {
    if (!mounted) return;
    setState(() {
      final i = _messages.lastIndexWhere((m) => m.isThinking);
      if (i >= 0) {
        _messages[i] = msg;
      } else {
        _messages.add(msg);
      }
    });
    _scrollToBottom();
  }

  /// Resolved model id: dropdown selection, manual text entry, or null to let
  /// the backend pick its default.
  String? _effectiveModel() {
    if (_modelsFailed) {
      final m = _modelTextController.text.trim();
      return m.isEmpty ? null : m;
    }
    return _model;
  }

  void _newChat() {
    setState(() {
      _messages.clear();
      _history.clear();
      _conversationId = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final modelsAsync = ref.watch(modelsProvider);

    return Scaffold(
      resizeToAvoidBottomInset: true,
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('AI Assistant'),
        actions: [
          IconButton(
            tooltip: 'New chat',
            onPressed: _sending ? null : _newChat,
            icon: const HeroIcon(HeroPaths.plus, size: 22),
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(132),
          child: _ConfigBar(
            provider: _provider,
            onProvider: _sending
                ? null
                : (v) => setState(() {
                      _provider = v;
                      _model = null;
                    }),
            model: _model,
            modelsAsync: modelsAsync,
            onModel: _sending
                ? null
                : (v) => setState(() => _model = v),
            modelTextController: _modelTextController,
            modelsFailed: _modelsFailed,
            onModelsFailed: () {
              if (mounted) setState(() => _modelsFailed = true);
            },
            agentMode: _agentMode,
            onAgentMode: _sending
                ? null
                : (v) => setState(() => _agentMode = v),
          ),
        ),
      ),
      body: Column(
        children: [
          Expanded(child: _buildMessageList(p)),
          _buildComposer(p),
        ],
      ),
    );
  }

  Widget _buildMessageList(AppPalette p) {
    if (_messages.isEmpty) {
      return const Center(
        child: EmptyState(
          iconPath: HeroPaths.chat,
          message: 'Start a conversation with the AI assistant.',
        ),
      );
    }
    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.page, 16, AppSpacing.page, 16),
      itemCount: _messages.length,
      itemBuilder: (context, i) {
        final msg = _messages[i];
        return FadeIn(
          delay: Duration(milliseconds: (i * 30).clamp(0, 240)),
          child: Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _MessageBubble(message: msg),
          ),
        );
      },
    );
  }

  Widget _buildComposer(AppPalette p) {
    final hasText = _input.text.trim().isNotEmpty;
    return SafeArea(
      top: false,
      child: Container(
        decoration: BoxDecoration(
          color: p.surface,
          border: Border(top: BorderSide(color: p.border)),
        ),
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 10, AppSpacing.page, 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: TextField(
                controller: _input,
                minLines: 1,
                maxLines: 5,
                textCapitalization: TextCapitalization.sentences,
                enabled: !_sending,
                textInputAction: TextInputAction.send,
                onSubmitted: (_) => _send(),
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w400,
                    color: p.textPrimary),
                decoration: InputDecoration(
                  isDense: true,
                  hintText: 'Type your prompt…',
                  hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
                  contentPadding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 12),
                  border: OutlineInputBorder(
                      borderRadius:
                          BorderRadius.circular(AppRadii.button),
                      borderSide: BorderSide(color: p.border)),
                  enabledBorder: OutlineInputBorder(
                      borderRadius:
                          BorderRadius.circular(AppRadii.button),
                      borderSide: BorderSide(color: p.border)),
                  focusedBorder: OutlineInputBorder(
                      borderRadius:
                          BorderRadius.circular(AppRadii.button),
                      borderSide:
                          BorderSide(color: p.ink, width: 1.5)),
                ),
              ),
            ),
            const SizedBox(width: 10),
            _SendButton(
              enabled: hasText && !_sending,
              onTap: _send,
            ),
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Config bar: provider dropdown, model dropdown (or text field), agent toggle.
// Sits under the AppBar (matches the PHP page's stacked controls).
// ---------------------------------------------------------------------------

class _ConfigBar extends StatelessWidget {
  const _ConfigBar({
    required this.provider,
    required this.onProvider,
    required this.model,
    required this.modelsAsync,
    required this.onModel,
    required this.modelTextController,
    required this.modelsFailed,
    required this.onModelsFailed,
    required this.agentMode,
    required this.onAgentMode,
  });

  final String provider;
  final ValueChanged<String>? onProvider;
  final String? model;
  final AsyncValue<Map<String, List<String>>> modelsAsync;
  final ValueChanged<String>? onModel;
  final TextEditingController modelTextController;
  final bool modelsFailed;
  final VoidCallback onModelsFailed;
  final bool agentMode;
  final ValueChanged<bool>? onAgentMode;

  static const _providerLabels = <String, String>{
    'groq': 'Groq',
    'openrouter': 'OpenRouter',
    'gemini': 'Gemini',
    'ollama': 'Ollama',
  };

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      color: p.canvas,
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.page, 8, AppSpacing.page, 12),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _UnderlineDropdown(
                  label: 'Provider',
                  value: provider,
                  items: _providerLabels.keys
                      .map((k) => DropdownMenuItem(
                            value: k,
                            child: Text(_providerLabels[k]!,
                                style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    letterSpacing: 0.5)),
                          ))
                      .toList(),
                  onChanged: onProvider == null
                      ? null
                      : (v) {
                          if (v != null) onProvider!(v);
                        },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(child: _buildModelField(context, p)),
            ],
          ),
          const SizedBox(height: 8),
          _AgentToggle(
            enabled: onAgentMode != null,
            value: agentMode,
            onChanged: onAgentMode,
          ),
        ],
      ),
    );
  }

  Widget _buildModelField(BuildContext context, AppPalette p) {
    if (modelsFailed) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('MODEL',
              style:
                  AppType.label(context, color: p.textMuted, size: 11)),
          const SizedBox(height: 4),
          TextField(
            controller: modelTextController,
            enabled: onModel != null,
            style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.5,
                color: p.textPrimary),
            decoration: InputDecoration(
              isDense: true,
              hintText: 'model id',
              hintStyle: TextStyle(color: p.textFaint, fontSize: 13),
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadii.button),
                  borderSide: BorderSide(color: p.border)),
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadii.button),
                  borderSide: BorderSide(color: p.border)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(AppRadii.button),
                  borderSide: BorderSide(color: p.ink, width: 1.5)),
            ),
          ),
        ],
      );
    }
    return modelsAsync.when(
      loading: () => const _UnderlineDropdown(
        label: 'Model',
        value: null,
        items: [],
        onChanged: null,
        hint: 'Loading…',
      ),
      error: (_, __) {
        // Trigger fallback once, then render the manual field next frame.
        WidgetsBinding.instance
            .addPostFrameCallback((_) => onModelsFailed());
        return const _UnderlineDropdown(
          label: 'Model',
          value: null,
          items: [],
          onChanged: null,
          hint: 'Enter manually',
        );
      },
      data: (models) {
        final list = models[provider] ?? const <String>[];
        if (list.isEmpty) {
          WidgetsBinding.instance
              .addPostFrameCallback((_) => onModelsFailed());
          return const _UnderlineDropdown(
            label: 'Model',
            value: null,
            items: [],
            onChanged: null,
            hint: 'Enter manually',
          );
        }
        final selected = (model != null && list.contains(model))
            ? model
            : list.first;
        return _UnderlineDropdown(
          label: 'Model',
          value: selected,
          items: list
              .map((m) => DropdownMenuItem(
                    value: m,
                    child: Text(m,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 0.5)),
                  ))
              .toList(),
          onChanged: onModel == null
              ? null
              : (v) {
                  if (v != null) onModel!(v);
                },
        );
      },
    );
  }
}

/// A compact bordered dropdown with an uppercase micro-label — the chat
/// screen's variant of LabeledDropdown, sized to sit two-up in the config row.
class _UnderlineDropdown extends StatelessWidget {
  const _UnderlineDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
    this.hint,
  });

  final String label;
  final String? value;
  final List<DropdownMenuItem<String>> items;
  final ValueChanged<String?>? onChanged;
  final String? hint;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 4),
        DropdownButtonFormField<String>(
          initialValue: value,
          items: items,
          onChanged: onChanged,
          icon: HeroIcon(HeroPaths.chevronDown, size: 16, color: p.textMuted),
          isExpanded: true,
          decoration: InputDecoration(
            isDense: true,
            hintText: hint,
            hintStyle: TextStyle(color: p.textFaint, fontSize: 13),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.ink, width: 1.5)),
          ),
          style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.5,
              color: p.textPrimary),
          dropdownColor: p.surface,
        ),
      ],
    );
  }
}

/// Chat vs Agent segmented toggle with an uppercase micro-label.
class _AgentToggle extends StatelessWidget {
  const _AgentToggle({
    required this.enabled,
    required this.value,
    required this.onChanged,
  });

  final bool enabled;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Opacity(
      opacity: enabled ? 1 : 0.5,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
        decoration: BoxDecoration(
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          children: [
            Text('MODE',
                style:
                    AppType.label(context, color: p.textMuted, size: 11)),
            const SizedBox(width: 10),
            Expanded(
              child: _Segment(
                label: 'Chat',
                selected: !value,
                onTap: enabled && onChanged != null
                    ? () => onChanged!(false)
                    : null,
              ),
            ),
            const SizedBox(width: 6),
            Expanded(
              child: _Segment(
                label: 'Agent',
                selected: value,
                onTap: enabled && onChanged != null
                    ? () => onChanged!(true)
                    : null,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Segment extends StatelessWidget {
  const _Segment({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(vertical: 7),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: selected ? p.ink : Colors.transparent,
          borderRadius: BorderRadius.circular(AppRadii.button - 2),
        ),
        child: Text(
          label.toUpperCase(),
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: selected ? p.onInk : p.textMuted,
          ),
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Message bubble: user = ink fill (right); assistant = bordered surface (left).
// User text is plain SelectableText. Assistant replies render markdown (code
// blocks, lists, tables, links) via MarkdownBody inside a SelectionArea so the
// body stays copyable. Agent actions/follow-ups appear as small chips below.
// ---------------------------------------------------------------------------

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message});
  final ChatMessage message;

  @override
  Widget build(BuildContext context) {
    final isUser = message.role == 'user';
    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.84,
        ),
        child: Column(
          crossAxisAlignment:
              isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            _bubble(context, isUser),
            if (!isUser && message.actions.isNotEmpty) ...[
              const SizedBox(height: 6),
              _ActionChips(actions: message.actions),
            ],
          ],
        ),
      ),
    );
  }

  Widget _bubble(BuildContext context, bool isUser) {
    final p = AppPalette.of(context);
    if (message.isThinking) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: const _ThinkingDots(),
      );
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: isUser ? p.ink : p.surface,
        border: Border.all(color: isUser ? p.ink : p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: isUser
          ? SelectableText(
              message.content,
              style: TextStyle(
                fontSize: 14,
                height: 1.45,
                color: p.onInk,
              ),
            )
          // Assistant replies render markdown (code blocks, lists, tables, links)
          // in a SelectionArea so the body stays copyable. Mermaid ```mermaid
          // blocks fall through to a normal fenced code block (rendering diagrams
          // is out of scope).
          : SelectionArea(
              child: MarkdownBody(
                data: message.content,
                selectable: false, // SelectionArea handles selection.
                extensionSet: md.ExtensionSet.gitHubWeb,
                styleSheet: _markdownStyle(p),
              ),
            ),
    );
  }
}

/// Three pulsing dots — the "Thinking…" indicator (no spinner, per DESIGN.md).
class _ThinkingDots extends StatefulWidget {
  const _ThinkingDots();

  @override
  State<_ThinkingDots> createState() => _ThinkingDotsState();
}

class _ThinkingDotsState extends State<_ThinkingDots>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: List.generate(3, (i) {
        return AnimatedBuilder(
          animation: _c,
          builder: (_, __) {
            // Stagger each dot by a third of the cycle.
            final t = (_c.value + i / 3) % 1.0;
            final scale = 0.6 + 0.4 * (0.5 - (t - 0.5).abs() * 2).abs();
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2),
              child: Transform.scale(
                scale: scale,
                child: Container(
                  width: 7,
                  height: 7,
                  decoration: BoxDecoration(
                    color: p.textMuted,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            );
          },
        );
      }),
    );
  }
}

class _ActionChips extends StatelessWidget {
  const _ActionChips({required this.actions});
  final List<ChatAction> actions;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Wrap(
      spacing: 6,
      runSpacing: 6,
      children: [
        for (final a in actions)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: p.canvas,
              border: Border.all(
                  color: a.success ? p.border : const Color(0xFFDC2626)),
              borderRadius: BorderRadius.circular(AppRadii.pill),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                HeroIcon(
                  a.success ? HeroPaths.check : HeroPaths.xMark,
                  size: 12,
                  color: a.success ? p.textMuted : const Color(0xFFDC2626),
                ),
                const SizedBox(width: 4),
                Text(
                  a.name,
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.5,
                    color: p.textMuted,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

// ---------------------------------------------------------------------------
// Send button: ink circle with a paper-airplane Heroicon (HeroPaths.arrowLeft
// mirrored to point right), per the task spec.
// ---------------------------------------------------------------------------

class _SendButton extends StatelessWidget {
  const _SendButton({required this.enabled, required this.onTap});

  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: enabled ? onTap : null,
      behavior: HitTestBehavior.opaque,
      child: Opacity(
        opacity: enabled ? 1 : 0.4,
        child: Container(
          width: AppSpacing.touchTarget,
          height: AppSpacing.touchTarget,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: p.ink,
            shape: BoxShape.circle,
          ),
          child: Transform.flip(
            // Mirror the left arrow so it points right (paper-airplane / send).
            flipX: true,
            child: HeroIcon(HeroPaths.arrowLeft,
                size: 20, color: p.onInk),
          ),
        ),
      ),
    );
  }
}

/// Monochrome markdown stylesheet for assistant replies. Single ink accent +
/// zinc neutrals; fenced code blocks render in a canvas-colored rounded box
/// with the GeistMono font; links in ink. No colors beyond the palette.
MarkdownStyleSheet _markdownStyle(AppPalette p) {
  final base = TextStyle(
    fontSize: 14,
    height: 1.45,
    color: p.textPrimary,
  );
  return MarkdownStyleSheet(
    p: base,
    h1: base.copyWith(fontSize: 20, fontWeight: FontWeight.w700),
    h2: base.copyWith(fontSize: 18, fontWeight: FontWeight.w700),
    h3: base.copyWith(fontSize: 16, fontWeight: FontWeight.w700),
    h4: base.copyWith(fontSize: 15, fontWeight: FontWeight.w700),
    h5: base.copyWith(fontSize: 14, fontWeight: FontWeight.w700),
    h6: base.copyWith(
        fontSize: 13, fontWeight: FontWeight.w700, color: p.textMuted),
    strong: base.copyWith(fontWeight: FontWeight.w700),
    em: base.copyWith(fontStyle: FontStyle.italic),
    blockquote: base.copyWith(color: p.textMuted),
    blockquoteDecoration: BoxDecoration(
      border: Border(left: BorderSide(color: p.border, width: 2)),
    ),
    a: TextStyle(color: p.ink, decoration: TextDecoration.underline),
    listBullet: base.copyWith(color: p.textMuted),
    checkbox: TextStyle(color: p.ink),
    code: TextStyle(
      fontFamily: 'GeistMono',
      fontSize: 12.5,
      color: p.textPrimary,
      backgroundColor: p.canvas,
    ),
    codeblockDecoration: BoxDecoration(
      color: p.canvas,
      borderRadius: BorderRadius.circular(AppRadii.button),
      border: Border.all(color: p.border),
    ),
    codeblockPadding: const EdgeInsets.all(12),
    tableHead: base.copyWith(fontWeight: FontWeight.w700),
    tableBody: base,
    tableHeadAlign: TextAlign.left,
    tableBorder: TableBorder.all(
      color: p.border,
      width: 1,
      borderRadius: BorderRadius.circular(AppRadii.button),
    ),
    tableColumnWidth: const FlexColumnWidth(),
    blockSpacing: 8,
    listIndent: 24,
    horizontalRuleDecoration: BoxDecoration(
      border: Border(
        top: BorderSide(color: p.border, width: 1),
      ),
    ),
  );
}
