import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'router.dart';
import 'services/notifications_service.dart';
import 'theme/app_theme.dart';
import 'theme/theme_controller.dart';
import 'widgets/task_timer_pill.dart';

Future<void> main() async {
  // Required before any plugin/platform-channel call (e.g. SharedPreferences,
  // flutter_secure_storage) can happen in main(). Without this, the binary
  // messenger is null and the app crashes on the splash screen.
  WidgetsFlutterBinding.ensureInitialized();
  // Hydrate the persisted theme mode before the first frame so cold starts
  // respect the user's saved System/Light/Dark choice.
  await initTheme();
  runApp(const ProviderScope(child: OpenPlanApp()));
}

class OpenPlanApp extends ConsumerStatefulWidget {
  const OpenPlanApp({super.key});

  @override
  ConsumerState<OpenPlanApp> createState() => _OpenPlanAppState();
}

class _OpenPlanAppState extends ConsumerState<OpenPlanApp> {
  @override
  void initState() {
    super.initState();
    // Foreground: a notification tap navigates immediately via the GoRouter.
    NotificationsService.registerTapHandler((route) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) ref.read(routerProvider).push(route);
      });
    });
    // Cold start: if a notification tap fired before the router was mounted,
    // consume the pending route now.
    if (pendingNotificationRoute != null) {
      final route = pendingNotificationRoute!;
      pendingNotificationRoute = null;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        ref.read(routerProvider).push(route);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);
    final mode = ref.watch(themeModeProvider);
    return MaterialApp.router(
      title: 'OpenPlan Work',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: mode,
      routerConfig: router,
      // Global floating task-timer pill: overlaid above every route via a Stack
      // (mirrors the PHP mobile.js floatingTimer). The pill itself hides on the
      // active task's own detail screen via activeTaskDetailProvider.
      builder: (context, child) => Material(
        type: MaterialType.transparency,
        child: Stack(
          children: [
            child ?? const SizedBox(),
            const TaskTimerPill(),
          ],
        ),
      ),
    );
  }
}
