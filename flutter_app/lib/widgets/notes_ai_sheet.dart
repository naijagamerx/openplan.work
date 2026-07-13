import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_markdown/flutter_markdown.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:markdown/markdown.dart' as md;

import '../models/chat_message.dart';
import '../repositories/ai_repository.dart';
import '../theme/app_tokens.dart';
import 'hero_icon.dart';

/// How an AI reply is written into the note.
enum NotesAiApply { insert, replace, append }

/// Open the Notes-AI chat as a keyboard-aware modal bottom sheet.
/// [noteId] grounds the chat in that note (backend loads its content). [onApply]
/// writes a chosen reply into the note's editor.
Future<void> showNotesAiSheet(
  BuildContext context, {
  required String? noteId,
  required void Function(String text, NotesAiApply mode) onApply,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => NotesAiSheet(noteId: noteId, onApply: onApply),
  );
}

class NotesAiSheet extends ConsumerStatefulWidget {
  const NotesAiSheet({super.key, required this.noteId, required this.onApply});

  final String? noteId;
  final void Function(String text, NotesAiApply mode) onApply;

  @override
  ConsumerState<NotesAiSheet> createState() => _NotesAiSheetState();
}

class _NotesAiSheetState extends ConsumerState<NotesAiSheet> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  final List<ChatMessage> _messages = [];
  final List<Map<String, String>> _history = [];
  bool _busy = false;
  bool _includeAllNotes = false;

  // '' provider + null model → backend uses the user's configured default.
  String _provider = '';
  String? _model;

  static const _quickActions = <(String, String)>[
    ('Improve', 'Improve the writing of this note — clearer, better structured.'),
    ('Summarize', 'Summarize this note into concise bullet points.'),
    ('Expand', 'Expand this note with more detail and useful examples.'),
    ('Make tasks',
        'Extract the actionable to-do items from this note as a markdown checklist, one per line as "- [ ] task".'),
  ];

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scroll.hasClients) {
        _scroll.animateTo(_scroll.position.maxScrollExtent,
            duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
      }
    });
  }

  Future<void> _send(String text) async {
    final msg = text.trim();
    if (_busy || msg.isEmpty) return;
    setState(() {
      _messages.add(ChatMessage(role: 'user', content: msg));
      _messages.add(const ChatMessage(role: 'assistant', content: '', isThinking: true));
      _busy = true;
      _input.clear();
    });
    _scrollToBottom();

    try {
      final reply = await ref.read(aiRepositoryProvider).notesChat(
            msg,
            _history,
            noteId: widget.noteId,
            includeAllNotes: _includeAllNotes,
            provider: _provider,
            model: _model,
          );
      _history.add({'role': 'user', 'content': msg});
      _history.add({'role': 'assistant', 'content': reply});
      if (!mounted) return;
      setState(() {
        _messages.removeLast(); // drop the thinking bubble
        _messages.add(ChatMessage(
            role: 'assistant',
            content: reply.trim().isEmpty ? '(no response)' : reply));
        _busy = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _messages.removeLast();
        _messages.add(
            ChatMessage(role: 'assistant', content: '⚠️ ${_friendly(e)}'));
        _busy = false;
      });
    }
    _scrollToBottom();
  }

  String _friendly(Object e) =>
      e.toString().replaceFirst('Exception: ', '').replaceFirst('ApiException: ', '');

  void _apply(String text, NotesAiApply mode) {
    // Capture the callback, close the sheet, THEN apply — so the write lands on
    // the now-visible note editor (not while this modal owns the focus/route).
    final cb = widget.onApply;
    Navigator.of(context).pop();
    cb(text, mode);
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    final height = MediaQuery.of(context).size.height * 0.9;

    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: Container(
        height: height,
        decoration: BoxDecoration(
          color: p.canvas,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: SafeArea(
          top: false,
          child: Column(
            children: [
              _handle(p),
              _header(p),
              _controls(p),
              Expanded(child: _transcript(p)),
              _composer(p),
            ],
          ),
        ),
      ),
    );
  }

  Widget _handle(AppPalette p) => Container(
        width: 40,
        height: 4,
        margin: const EdgeInsets.only(top: 10, bottom: 4),
        decoration: BoxDecoration(
            color: p.border, borderRadius: BorderRadius.circular(2)),
      );

  Widget _header(AppPalette p) => Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.page, 8, 8, 8),
        child: Row(
          children: [
            HeroIcon(HeroPaths.sparkles, size: 20, color: p.textPrimary),
            const SizedBox(width: 8),
            Text('Notes AI',
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary)),
            const Spacer(),
            IconButton(
              icon: HeroIcon(HeroPaths.xMark, size: 20, color: p.textMuted),
              onPressed: () => Navigator.of(context).pop(),
            ),
          ],
        ),
      );

  Widget _controls(AppPalette p) {
    final modelsAsync = ref.watch(modelsProvider);
    final entries = <(String label, String? provider, String? model)>[
      ('Auto', '', null),
    ];
    modelsAsync.whenData((map) {
      map.forEach((prov, models) {
        for (final m in models) {
          entries.add(('$prov · $m', prov, m));
        }
      });
    });
    final itemValues = <String>{'Auto'};
    for (final e in entries) {
      if (e.$1 != 'Auto') itemValues.add('${e.$2} · ${e.$3}');
    }
    final desired = _model == null ? 'Auto' : '$_provider · $_model';
    // Dropdown asserts value ∈ items; fall back to Auto if models reloaded.
    final current = itemValues.contains(desired) ? desired : 'Auto';

    return Padding(
      padding: const EdgeInsets.fromLTRB(AppSpacing.page, 0, AppSpacing.page, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  decoration: BoxDecoration(
                    border: Border.all(color: p.border),
                    borderRadius: BorderRadius.circular(AppRadii.button),
                  ),
                  child: DropdownButtonHideUnderline(
                    child: DropdownButton<String>(
                      isExpanded: true,
                      value: current,
                      icon: HeroIcon(HeroPaths.chevronDown,
                          size: 16, color: p.textMuted),
                      style: TextStyle(fontSize: 13, color: p.textPrimary),
                      items: [
                        for (final e in entries)
                          DropdownMenuItem(
                            value: e.$1 == 'Auto' ? 'Auto' : '${e.$2} · ${e.$3}',
                            child: Text(e.$1, overflow: TextOverflow.ellipsis),
                          ),
                      ],
                      onChanged: (v) {
                        final sel = entries.firstWhere(
                            (e) => (e.$1 == 'Auto' ? 'Auto' : '${e.$2} · ${e.$3}') == v,
                            orElse: () => ('Auto', '', null));
                        setState(() {
                          _provider = sel.$2 ?? '';
                          _model = sel.$3;
                        });
                      },
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: () => setState(() => _includeAllNotes = !_includeAllNotes),
                child: Row(children: [
                  Icon(
                    _includeAllNotes
                        ? Icons.check_box
                        : Icons.check_box_outline_blank,
                    size: 18,
                    color: _includeAllNotes ? p.ink : p.textFaint,
                  ),
                  const SizedBox(width: 4),
                  Text('All notes',
                      style: TextStyle(fontSize: 12, color: p.textMuted)),
                ]),
              ),
            ],
          ),
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(children: [
              for (final qa in _quickActions) ...[
                _Chip(label: qa.$1, onTap: _busy ? null : () => _send(qa.$2)),
                const SizedBox(width: 8),
              ],
            ]),
          ),
        ],
      ),
    );
  }

  Widget _transcript(AppPalette p) {
    if (_messages.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              HeroIcon(HeroPaths.sparkles, size: 36, color: p.border),
              const SizedBox(height: 12),
              Text('Brainstorm with this note',
                  style: TextStyle(
                      fontWeight: FontWeight.w600, color: p.textMuted)),
              const SizedBox(height: 4),
              Text('Ask a question or use a quick action above.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13, color: p.textFaint)),
            ],
          ),
        ),
      );
    }
    return ListView.builder(
      controller: _scroll,
      padding: const EdgeInsets.fromLTRB(AppSpacing.page, 8, AppSpacing.page, 8),
      itemCount: _messages.length,
      itemBuilder: (_, i) => _Bubble(
        message: _messages[i],
        onApply: _apply,
      ),
    );
  }

  Widget _composer(AppPalette p) => Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.page, 4, AppSpacing.page, 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                decoration: BoxDecoration(
                  border: Border.all(color: p.border),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: TextField(
                  controller: _input,
                  minLines: 1,
                  maxLines: 5,
                  textInputAction: TextInputAction.send,
                  onSubmitted: _send,
                  decoration: const InputDecoration(
                    isDense: true,
                    hintText: 'Ask, brainstorm, or edit…',
                    border: InputBorder.none,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: _busy ? null : () => _send(_input.text),
              child: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                    color: _busy ? p.textFaint : p.ink, shape: BoxShape.circle),
                child: Icon(Icons.arrow_upward, color: p.onInk, size: 20),
              ),
            ),
          ],
        ),
      );
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.onTap});
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(label,
            style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: onTap == null ? p.textFaint : p.textPrimary)),
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.onApply});
  final ChatMessage message;
  final void Function(String text, NotesAiApply mode) onApply;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final isUser = message.role == 'user';

    if (isUser) {
      return Align(
        alignment: Alignment.centerRight,
        child: Container(
          margin: const EdgeInsets.only(bottom: 10, left: 40),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: p.ink,
            borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(16),
              topRight: Radius.circular(16),
              bottomLeft: Radius.circular(16),
              bottomRight: Radius.circular(4),
            ),
          ),
          child: Text(message.content,
              style: TextStyle(color: p.onInk, fontSize: 14)),
        ),
      );
    }

    if (message.isThinking) {
      return Align(
        alignment: Alignment.centerLeft,
        child: Padding(
          padding: const EdgeInsets.only(bottom: 10, left: 4),
          child: Text('…', style: TextStyle(color: p.textFaint, fontSize: 20)),
        ),
      );
    }

    return Align(
      alignment: Alignment.centerLeft,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            margin: const EdgeInsets.only(bottom: 6, right: 24),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: p.surface,
              border: Border.all(color: p.border),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(16),
                topRight: Radius.circular(16),
                bottomLeft: Radius.circular(4),
                bottomRight: Radius.circular(16),
              ),
            ),
            child: MarkdownBody(
              data: message.content,
              selectable: true,
              extensionSet: md.ExtensionSet.gitHubWeb,
              styleSheet: MarkdownStyleSheet(
                p: TextStyle(fontSize: 14, height: 1.45, color: p.textPrimary),
                tableBorder: TableBorder.all(color: p.border),
                tableHead: const TextStyle(fontWeight: FontWeight.w700),
                tableCellsPadding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(bottom: 12, left: 2),
            child: Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                _Chip(
                    label: 'Insert',
                    onTap: () => onApply(message.content, NotesAiApply.insert)),
                _Chip(
                    label: 'Replace',
                    onTap: () => onApply(message.content, NotesAiApply.replace)),
                _Chip(
                    label: 'Append',
                    onTap: () => onApply(message.content, NotesAiApply.append)),
                _Chip(
                    label: 'Copy',
                    onTap: () => Clipboard.setData(
                        ClipboardData(text: message.content))),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
