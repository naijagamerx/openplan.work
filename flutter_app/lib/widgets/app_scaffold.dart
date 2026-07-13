import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/notifications_repository.dart';
import 'app_bottom_nav.dart';
import 'notification_bell.dart';

/// Shared monochrome shell: flat AppBar + bottom nav. Wraps the primary screens.
///
/// The AppBar always includes the in-app notification bell (with a red count
/// badge) so it appears on every primary tab screen. The notifications provider
/// is re-fetched whenever the app resumes (PHP parity: app.js polls every 60s).
class AppScaffold extends ConsumerStatefulWidget {
  const AppScaffold({
    super.key,
    required this.title,
    required this.current,
    required this.body,
    this.actions,
    this.floatingActionButton,
    this.showBell = true,
  });

  final String title;
  final String current; // matching a NavTab label
  final Widget body;
  final List<Widget>? actions;
  final Widget? floatingActionButton;

  /// Whether to render the notification bell in the AppBar. True by default;
  /// screens that already surface their own notifications can opt out.
  final bool showBell;

  @override
  ConsumerState<AppScaffold> createState() => _AppScaffoldState();
}

class _AppScaffoldState extends ConsumerState<AppScaffold>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Refresh the bell badge when returning to the foreground.
    if (state == AppLifecycleState.resumed) {
      ref.invalidate(notificationsProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final extra = widget.actions ?? const <Widget>[];
    final actions = [
      ...extra,
      if (widget.showBell) const NotificationBell(),
    ];
    return Scaffold(
      appBar: AppBar(title: Text(widget.title), actions: actions),
      // body sits ABOVE the footer normally (no extendBody) so content never
      // scrolls under the floating pill. The pill floats visually thanks to its
      // own outer padding/transparent container.
      body: SafeArea(top: false, bottom: false, child: widget.body),
      // Lift the FAB above the floating footer so they don't collide.
      floatingActionButton: widget.floatingActionButton == null
          ? null
          : Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: widget.floatingActionButton,
            ),
      // The floating pill nav floats in a transparent host.
      bottomNavigationBar: Container(
        color: Colors.transparent,
        child: AppBottomNav(
          current: widget.current,
          onTap: (tab) {
            if (tab.label == widget.current) return;
            if (tab.route != null) {
              context.go(tab.route!);
            } else {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('${tab.label} coming soon'),
                  behavior: SnackBarBehavior.floating,
                ),
              );
            }
          },
        ),
      ),
    );
  }
}
