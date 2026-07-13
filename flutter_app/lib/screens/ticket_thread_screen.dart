import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/ticket.dart';
import '../repositories/admin_tickets_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Chat-style ticket thread. Admin messages align right (ink); user messages
/// align left. Reply posts `?action=reply`; Close posts `?action=close`.
/// authorRole drives alignment. Reusable for a future user-side support screen.
class TicketThreadScreen extends ConsumerStatefulWidget {
  const TicketThreadScreen({super.key, required this.ticketId});
  final String ticketId;

  @override
  ConsumerState<TicketThreadScreen> createState() => _TicketThreadScreenState();
}

class _TicketThreadScreenState extends ConsumerState<TicketThreadScreen> {
  final TextEditingController _replyController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  bool _sending = false;

  @override
  void dispose() {
    _replyController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
        );
      }
    });
  }

  Future<void> _send() async {
    final body = _replyController.text.trim();
    if (body.isEmpty) return;
    setState(() => _sending = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(adminTicketsRepositoryProvider)
          .reply(widget.ticketId, body);
      _replyController.clear();
      ref.invalidate(ticketProvider(widget.ticketId));
      ref.invalidate(adminTicketsProvider);
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Reply failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _close(Ticket ticket) async {
    final messenger = ScaffoldMessenger.of(context);
    final ok = await confirmDialog(
      context,
      title: 'Close ticket?',
      message: 'This marks "${ticket.subject}" as closed. The user can no '
          'longer reply.',
      confirmLabel: 'Close',
      destructive: false,
    );
    if (!ok) return;
    try {
      await ref.read(adminTicketsRepositoryProvider).close(widget.ticketId);
      ref.invalidate(ticketProvider(widget.ticketId));
      ref.invalidate(adminTicketsProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Ticket closed'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Close failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    final ticket = ref.watch(ticketProvider(widget.ticketId));

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: ticket.maybeWhen(
          data: (t) =>
              Text(t == null ? 'Ticket' : _title(t)),
          orElse: () => const Text('Ticket'),
        ),
        actions: [
          ticket.maybeWhen(
            data: (t) => t == null || !t.isOpen
                ? const SizedBox.shrink()
                : Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: TextButton(
                      onPressed: () => _close(t),
                      child: const Text('CLOSE'),
                    ),
                  ),
            orElse: () => const SizedBox.shrink(),
          ),
        ],
      ),
      body: SafeArea(
        child: ticket.when(
          loading: () => ListView(
            padding: const EdgeInsets.all(AppSpacing.page),
            children: const [
              Align(
                alignment: Alignment.centerLeft,
                child: _BubbleSkeleton(),
              ),
              SizedBox(height: 12),
              Align(
                alignment: Alignment.centerRight,
                child: _BubbleSkeleton(width: 200),
              ),
              SizedBox(height: 12),
              Align(
                alignment: Alignment.centerLeft,
                child: _BubbleSkeleton(width: 220),
              ),
            ],
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyState(
                iconPath: HeroPaths.chat,
                message: 'Could not load ticket',
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(
                    ticketProvider(widget.ticketId)),
              ),
            ),
          ),
          data: (t) {
            if (t == null) {
              return const Center(
                child: EmptyState(
                    iconPath: HeroPaths.chat, message: 'Ticket not found'),
              );
            }
            // Jump to the newest message once the data settles.
            _scrollToBottom();
            final isClosed = !t.isOpen;
            return Column(
              children: [
                Expanded(
                  child: t.messages.isEmpty
                      ? const Center(
                          child: EmptyState(
                              iconPath: HeroPaths.chat,
                              message: 'No messages yet'),
                        )
                      : ListView.builder(
                          controller: _scrollController,
                          padding: const EdgeInsets.fromLTRB(
                              AppSpacing.page, 16, AppSpacing.page, 16),
                          itemCount: t.messages.length,
                          itemBuilder: (_, i) {
                            final m = t.messages[i];
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: FadeIn(
                                delay: Duration(
                                    milliseconds: (i * 30).clamp(0, 200)),
                                child: _MessageBubble(message: m),
                              ),
                            );
                          },
                        ),
                ),
                _ReplyBar(
                  controller: _replyController,
                  onSend: _send,
                  disabled: isClosed || _sending,
                  hint: isClosed ? 'Ticket closed' : 'Reply…',
                  onShown: _scrollToBottom,
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  static String _title(Ticket t) =>
      t.subject.isEmpty ? '(no subject)' : t.subject;
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({required this.message});
  final TicketMessage message;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final admin = message.fromAdmin;
    final bg = admin ? p.ink : p.surface;
    final fg = admin ? p.onInk : p.textPrimary;
    final align = admin ? CrossAxisAlignment.end : CrossAxisAlignment.start;

    return Row(
      mainAxisAlignment:
          admin ? MainAxisAlignment.end : MainAxisAlignment.start,
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        ConstrainedBox(
          constraints: BoxConstraints(
            maxWidth: MediaQuery.of(context).size.width * 0.76,
          ),
          child: Column(
            crossAxisAlignment: align,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: bg,
                  border: admin ? null : Border.all(color: p.border),
                  borderRadius: BorderRadius.only(
                    topLeft: const Radius.circular(AppRadii.card),
                    topRight: const Radius.circular(AppRadii.card),
                    bottomLeft:
                        Radius.circular(admin ? AppRadii.card : 4),
                    bottomRight:
                        Radius.circular(admin ? 4 : AppRadii.card),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: align,
                  children: [
                    Text(
                      message.body,
                      style: TextStyle(fontSize: 14, color: fg, height: 1.4),
                    ),
                    if (message.createdAt != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          DateFormat('MMM d, HH:mm')
                              .format(message.createdAt!.toLocal()),
                          style: TextStyle(
                            fontSize: 10,
                            color: admin ? p.onInk.withValues(alpha: 0.7) : p.textFaint,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _BubbleSkeleton extends StatelessWidget {
  const _BubbleSkeleton({this.width = 180});
  final double width;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      width: width,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Skeleton(height: 12, width: 140),
          SizedBox(height: 8),
          Skeleton(height: 12, width: 90),
        ],
      ),
    );
  }
}

class _ReplyBar extends StatefulWidget {
  const _ReplyBar({
    required this.controller,
    required this.onSend,
    required this.disabled,
    required this.hint,
    required this.onShown,
  });

  final TextEditingController controller;
  final VoidCallback onSend;
  final bool disabled;
  final String hint;
  final VoidCallback onShown;

  @override
  State<_ReplyBar> createState() => _ReplyBarState();
}

class _ReplyBarState extends State<_ReplyBar> {
  bool _hasText = false;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_listener);
  }

  @override
  void dispose() {
    widget.controller.removeListener(_listener);
    super.dispose();
  }

  void _listener() {
    final has = widget.controller.text.trim().isNotEmpty;
    if (has != _hasText) setState(() => _hasText = has);
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      decoration: BoxDecoration(
        color: p.surface,
        border: Border(top: BorderSide(color: p.border)),
      ),
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.page, 10, AppSpacing.page, 10),
      child: SafeArea(
        top: false,
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: widget.controller,
                minLines: 1,
                maxLines: 4,
                enabled: !widget.disabled,
                textCapitalization: TextCapitalization.sentences,
                onChanged: (_) => widget.onShown(),
                style: TextStyle(fontSize: 14, color: p.textPrimary),
                decoration: InputDecoration(
                  isDense: true,
                  hintText: widget.hint,
                  hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
                  contentPadding: const EdgeInsets.symmetric(
                      horizontal: 14, vertical: 10),
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
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: (widget.disabled || !_hasText) ? null : widget.onSend,
              behavior: HitTestBehavior.opaque,
              child: Container(
                width: 44,
                height: 44,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: (widget.disabled || !_hasText) ? p.border : p.ink,
                  borderRadius: BorderRadius.circular(AppRadii.button),
                ),
                child: HeroIcon(HeroPaths.paperAirplane,
                    size: 18, color: p.onInk),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
