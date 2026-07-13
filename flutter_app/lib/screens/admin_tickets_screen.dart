import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/ticket.dart';
import '../repositories/admin_tickets_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin support-ticket queue. Lists every ticket with subject, user, message
/// count, status badge, and last-updated time. Tapping a row opens the thread.
/// Mirrors mobile/views/admin-tickets.php.
class AdminTicketsScreen extends ConsumerWidget {
  const AdminTicketsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final tickets = ref.watch(adminTicketsProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Support Tickets'),
      ),
      body: SafeArea(
        child: tickets.when(
          loading: () => ListView(
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 40),
            children: const [
              Skeleton(height: 84, radius: AppRadii.card),
              SizedBox(height: 12),
              Skeleton(height: 84, radius: AppRadii.card),
              SizedBox(height: 12),
              Skeleton(height: 84, radius: AppRadii.card),
            ],
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyState(
                iconPath: HeroPaths.chat,
                message: 'Could not load tickets',
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(adminTicketsProvider),
              ),
            ),
          ),
          data: (list) {
            if (list.isEmpty) {
              return const Center(
                child: EmptyState(
                  iconPath: HeroPaths.chat,
                  message: 'No support tickets',
                ),
              );
            }
            return RefreshIndicator(
              onRefresh: () => ref.refresh(adminTicketsProvider.future),
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page, 16, AppSpacing.page, 40),
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final t = list[i];
                  return FadeIn(
                    delay: Duration(milliseconds: (i * 50).clamp(0, 300)),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _TicketRow(ticket: t),
                    ),
                  );
                },
              ),
            );
          },
        ),
      ),
    );
  }
}

class _TicketRow extends ConsumerWidget {
  const _TicketRow({required this.ticket});
  final Ticket ticket;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final needsReply = ticket.status == 'awaiting_admin';
    return InkWell(
      onTap: () async {
        await context.push('/admin/tickets/${ticket.id}');
        if (context.mounted) ref.invalidate(adminTicketsProvider);
      },
      borderRadius: BorderRadius.circular(AppRadii.card),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(
              color: needsReply ? p.ink : p.border,
              width: needsReply ? 1.5 : 1),
          borderRadius: BorderRadius.circular(AppRadii.card),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: p.canvas,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: p.border),
              ),
              alignment: Alignment.center,
              child: HeroIcon(HeroPaths.chat, size: 20, color: p.textPrimary),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    ticket.subject.isEmpty ? '(no subject)' : ticket.subject,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: p.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${ticket.userId.isEmpty ? "Unknown" : "User ${ticket.userId}"} · ${ticket.messages.length} msg',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 12, color: p.textFaint),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                _StatusBadge(status: ticket.status),
                if (ticket.updatedAt != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      _relative(ticket.updatedAt!.toLocal()),
                      style: TextStyle(fontSize: 11, color: p.textFaint),
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  static String _relative(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m';
    if (diff.inHours < 24) return '${diff.inHours}h';
    if (diff.inDays < 7) return '${diff.inDays}d';
    return DateFormat('MMM d').format(dt);
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final isClosed = status == 'closed';
    final needsReply = status == 'awaiting_admin';
    final bg = isClosed
        ? const Color(0xFF6B7280)
        : needsReply
            ? const Color(0xFFEA580C)
            : const Color(0xFF16A34A);
    final label = status.replaceAll('_', ' ');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 1,
          color: Colors.white,
        ),
      ),
    );
  }
}
