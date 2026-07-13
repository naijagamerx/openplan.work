import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../repositories/notifications_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'empty_state.dart';
import 'hero_icon.dart';

/// A bell icon with a red count badge. Tapping opens a bottom sheet listing the
/// derived task alerts (overdue / due today / not started), each tappable to
/// open the task. Mirrors the PHP bell in header-mobile.php:101-131 +
/// App.notificationCenter (app.js:1127).
///
/// Place this in the AppBar `actions` of the primary tab screens.
class NotificationBell extends ConsumerWidget {
  const NotificationBell({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final alerts = ref.watch(notificationsProvider);
    final count = alerts.maybeWhen(
      data: (list) => list.length,
      orElse: () => 0,
    );

    return IconButton(
      tooltip: 'Notifications',
      icon: _BellWithBadge(count: count),
      onPressed: () => _showPanel(context, ref),
    );
  }

  Future<void> _showPanel(BuildContext context, WidgetRef ref) async {
    // Always re-fetch when opening so the panel is fresh (PHP polls 60s).
    ref.invalidate(notificationsProvider);
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _NotifPanel(),
    );
  }
}

class _BellWithBadge extends StatelessWidget {
  const _BellWithBadge({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Stack(
      clipBehavior: Clip.none,
      alignment: Alignment.center,
      children: [
        HeroIcon(HeroPaths.bell, size: 22, color: p.textPrimary),
        if (count > 0)
          Positioned(
            top: -4,
            right: -6,
            child: Container(
              constraints: const BoxConstraints(
                minWidth: 16,
                minHeight: 16,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: const Color(0xFFDC2626),
                borderRadius: BorderRadius.circular(AppRadii.pill),
                border: Border.all(color: p.canvas, width: 1.5),
              ),
              child: Text(
                count > 99 ? '99+' : '$count',
                style: const TextStyle(
                  fontFamily: 'Geist',
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                  height: 1.1,
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _NotifPanel extends ConsumerWidget {
  const _NotifPanel();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final alerts = ref.watch(notificationsProvider);

    return SafeArea(
      child: Container(
        margin: const EdgeInsets.all(8),
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.section),
        ),
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(context).size.height * 0.7,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  HeroIcon(HeroPaths.bell, size: 18, color: p.textPrimary),
                  const SizedBox(width: 8),
                  Text('NOTIFICATIONS',
                      style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.5,
                        color: p.textPrimary,
                      )),
                  const Spacer(),
                  GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: () => Navigator.pop(context),
                    child: HeroIcon(HeroPaths.xMark, size: 18, color: p.textMuted),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Divider(height: 1, color: p.border),
              const SizedBox(height: 8),
              Flexible(
                child: alerts.when(
                  loading: () => const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: Center(
                        child: Text('Loading…',
                            style: TextStyle(fontSize: 13))),
                  ),
                  error: (e, _) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 32),
                    child: EmptyState(
                        iconPath: HeroPaths.bell, message: '$e'),
                  ),
                  data: (list) {
                    if (list.isEmpty) {
                      return const Padding(
                        padding: EdgeInsets.symmetric(vertical: 40),
                        child: Center(
                          child: EmptyState(
                              iconPath: HeroPaths.checkCircle,
                              message: 'You’re all caught up'),
                        ),
                      );
                    }
                    return ListView.separated(
                      shrinkWrap: true,
                      itemCount: list.length,
                      separatorBuilder: (_, __) =>
                          Divider(height: 1, color: p.border),
                      itemBuilder: (_, i) {
                        final a = list[i];
                        return _NotifRow(
                          notif: a,
                          onTap: () {
                            Navigator.pop(context);
                            context.push('/tasks/${a.task.id}');
                          },
                        );
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NotifRow extends StatelessWidget {
  const _NotifRow({required this.notif, required this.onTap});
  final AppNotification notif;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final sev = notif.type;
    final sevColor = sev == NotificationType.overdue
        ? const Color(0xFFDC2626)
        : sev == NotificationType.dueToday
            ? const Color(0xFFEA580C)
            : p.textMuted;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Row(
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: sevColor,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notif.task.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: p.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Wrap(
                    spacing: 8,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(sev.label.toUpperCase(),
                          style: AppType.label(context,
                              color: sevColor, size: 10)),
                      if (notif.task.projectName != null)
                        Text(notif.task.projectName!.toUpperCase(),
                            style: AppType.label(
                                context, color: p.textFaint, size: 10)),
                      if (notif.task.dueDate != null)
                        Text(
                          DateFormat.yMMMd()
                              .format(notif.task.dueDate!.toLocal()),
                          style: TextStyle(
                              fontSize: 11, color: p.textFaint),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            HeroIcon(HeroPaths.chevronRight, size: 14, color: p.textFaint),
          ],
        ),
      ),
    );
  }
}
