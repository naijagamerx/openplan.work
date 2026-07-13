import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/subscription.dart';
import '../repositories/admin_subscriptions_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin subscribers screen — list all subscriptions with extend/cancel actions
/// on active ones. Mirrors mobile/views/admin-subscriptions.php.
class AdminSubscriptionsScreen extends ConsumerWidget {
  const AdminSubscriptionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final subs = ref.watch(adminSubscriptionsProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Subscribers'),
      ),
      body: SafeArea(
        child: subs.when(
          loading: () => ListView(
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 40),
            children: const [
              Skeleton(height: 110, radius: AppRadii.card),
              SizedBox(height: 12),
              Skeleton(height: 110, radius: AppRadii.card),
              SizedBox(height: 12),
              Skeleton(height: 110, radius: AppRadii.card),
            ],
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyState(
                iconPath: HeroPaths.bell,
                message: 'Could not load subscriptions',
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(adminSubscriptionsProvider),
              ),
            ),
          ),
          data: (list) {
            if (list.isEmpty) {
              return const Center(
                child: EmptyState(
                  iconPath: HeroPaths.gift,
                  message: 'No subscribers yet',
                ),
              );
            }
            return RefreshIndicator(
              onRefresh: () => ref.refresh(adminSubscriptionsProvider.future),
              child: ListView.builder(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page, 16, AppSpacing.page, 40),
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final s = list[i];
                  return FadeIn(
                    delay: Duration(milliseconds: (i * 50).clamp(0, 300)),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _SubscriptionRow(sub: s),
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

class _SubscriptionRow extends ConsumerWidget {
  const _SubscriptionRow({required this.sub});
  final Subscription sub;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      sub.planSnapshot.name.isEmpty
                          ? 'Untitled plan'
                          : sub.planSnapshot.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: p.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      sub.userId.isEmpty ? 'Unknown user' : 'User ${sub.userId}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(fontSize: 12, color: p.textFaint),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _StatusBadge(status: sub.status),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _DatePair(
                    label: 'Start', date: sub.startsAt),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DatePair(
                    label: 'Expires', date: sub.expiresAt),
              ),
            ],
          ),
          if (sub.isActive) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: _ActionChip(
                    label: 'Extend',
                    icon: HeroPaths.clock,
                    onTap: () => _extend(context, ref),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _ActionChip(
                    label: 'Cancel',
                    icon: HeroPaths.xMark,
                    destructive: true,
                    onTap: () => _cancel(context, ref),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _extend(BuildContext context, WidgetRef ref) async {
    final messenger = ScaffoldMessenger.of(context);
    final days = await _promptDays(context);
    if (days == null) return;
    try {
      await ref.read(adminSubscriptionsRepositoryProvider).extend(sub.id, days);
      ref.invalidate(adminSubscriptionsProvider);
      messenger.showSnackBar(SnackBar(
        content: Text('Extended by $days days'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Extend failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  Future<void> _cancel(BuildContext context, WidgetRef ref) async {
    final messenger = ScaffoldMessenger.of(context);
    final ok = await confirmDialog(
      context,
      title: 'Cancel subscription?',
      message:
          'This immediately sets the subscription to cancelled. The user keeps '
          'access until the current expiry.',
      confirmLabel: 'Cancel sub',
      destructive: true,
    );
    if (!ok) return;
    try {
      await ref.read(adminSubscriptionsRepositoryProvider).cancel(sub.id);
      ref.invalidate(adminSubscriptionsProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Subscription cancelled'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Cancel failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }
}

/// Prompts for an integer number of days (default 30). Returns null on dismiss.
Future<int?> _promptDays(BuildContext context) async {
  final controller = TextEditingController(text: '30');
  final formKey = GlobalKey<FormFieldState<String>>();
  final p = AppPalette.of(context);
  return showDialog<int>(
    context: context,
    builder: (ctx) => AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: p.border),
      ),
      title: Text('Extend subscription',
          style: TextStyle(
              fontSize: 16, fontWeight: FontWeight.w700, color: p.textPrimary)),
      content: FormField<String>(
        key: formKey,
        initialValue: '30',
        validator: (v) {
          final n = int.tryParse((v ?? controller.text).trim()) ?? 0;
          if (n <= 0) return 'Enter a positive number of days';
          return null;
        },
        builder: (field) => Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('DAYS TO ADD',
                style: AppType.label(context, color: p.textMuted, size: 11)),
            const SizedBox(height: 4),
            TextField(
              controller: controller,
              keyboardType: TextInputType.number,
              autofocus: true,
              decoration: InputDecoration(
                isDense: true,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border)),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border)),
                focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.ink, width: 1.5)),
                errorText: field.errorText,
              ),
            ),
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, null),
          child: Text('CANCEL',
              style: TextStyle(
                  color: p.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
        TextButton(
          onPressed: () {
            if (formKey.currentState?.validate() ?? false) {
              Navigator.pop(
                  ctx, int.tryParse(controller.text.trim()) ?? 0);
            }
          },
          child: Text('EXTEND',
              style: TextStyle(
                  color: p.ink,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
      ],
    ),
  );
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final isOk = status == 'active';
    final isCancelled = status == 'cancelled';
    final bg = isOk
        ? const Color(0xFF16A34A)
        : isCancelled
            ? const Color(0xFFEA580C)
            : const Color(0xFF6B7280);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        status.toUpperCase(),
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

class _DatePair extends StatelessWidget {
  const _DatePair({required this.label, required this.date});
  final String label;
  final DateTime? date;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 10)),
        const SizedBox(height: 2),
        Text(
          date == null ? '—' : DateFormat('MMM d, y').format(date!.toLocal()),
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: p.textPrimary),
        ),
      ],
    );
  }
}

class _ActionChip extends StatelessWidget {
  const _ActionChip({
    required this.label,
    required this.icon,
    required this.onTap,
    this.destructive = false,
  });

  final String label;
  final String icon;
  final VoidCallback onTap;
  final bool destructive;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final accent = destructive ? const Color(0xFFDC2626) : p.ink;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        constraints: const BoxConstraints(minHeight: 38),
        alignment: Alignment.center,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          border: Border.all(color: accent),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            HeroIcon(icon, size: 14, color: accent),
            const SizedBox(width: 6),
            Text(
              label.toUpperCase(),
              style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 1,
                color: accent,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
