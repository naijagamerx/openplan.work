import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../state/task_timer_controller.dart';
import '../theme/app_tokens.dart';
import 'hero_icon.dart';

/// Tracks which task-detail screen is currently on top, so the floating pill
/// can hide itself when the user is already viewing the running timer's task.
/// Set by [TaskDetailScreen] on enter/exit. Null = no task detail visible.
final activeTaskDetailProvider = StateProvider<String?>((ref) => null);

/// Global floating pill, overlaid above every screen when a task timer is
/// active. Mirrors the PHP `Mobile.floatingTimer` (mobile.js:1225) — a black
/// pill pinned bottom-center showing the task title + MM:SS; tapping opens the
/// owning task's detail screen.
///
/// Hidden when the user is already viewing the timer's own task detail screen
/// (tracked via [activeTaskDetailProvider], since this widget lives above the
/// router and cannot read GoRouterState directly).
///
/// The pill self-rebuilds every second while visible (driven by the controller)
/// so the MM:SS stays live.
class TaskTimerPill extends ConsumerWidget {
  const TaskTimerPill({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.watch(taskTimerControllerProvider);
    if (!controller.isActive) return const SizedBox.shrink();
    final activeDetail = ref.watch(activeTaskDetailProvider);
    if (controller.taskId == activeDetail) return const SizedBox.shrink();

    final p = AppPalette.of(context);
    return Positioned(
      left: 0,
      right: 0,
      bottom: 0,
      child: SafeArea(
        top: false,
        child: Center(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: _Pill(
              title: controller.taskTitle,
              mode: controller.mode,
              time: controller.elapsedLabel,
              onTap: () {
                final id = controller.taskId;
                if (id != null && id.isNotEmpty) context.push('/tasks/$id');
              },
              ink: p.ink,
              onInk: p.onInk,
            ),
          ),
        ),
      ),
    );
  }
}

/// The pill itself, split out so it can use a tactile press feedback.
class _Pill extends StatefulWidget {
  const _Pill({
    required this.title,
    required this.mode,
    required this.time,
    required this.onTap,
    required this.ink,
    required this.onInk,
  });

  final String title;
  final TaskTimerMode mode;
  final String time;
  final VoidCallback onTap;
  final Color ink;
  final Color onInk;

  @override
  State<_Pill> createState() => _PillState();
}

class _PillState extends State<_Pill> {
  bool _down = false;

  @override
  Widget build(BuildContext context) {
    const playPath = 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z';
    return GestureDetector(
      onTapDown: (_) => setState(() => _down = true),
      onTapUp: (_) => setState(() => _down = false),
      onTapCancel: () => setState(() => _down = false),
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _down ? 0.96 : 1,
        duration: const Duration(milliseconds: 90),
        child: Container(
          constraints: const BoxConstraints(maxWidth: 360),
          padding: const EdgeInsets.fromLTRB(14, 9, 16, 9),
          decoration: BoxDecoration(
            color: widget.ink,
            // DESIGN.md §7: borders, never shadows. A pill keeps the ink fill.
            border: Border.all(color: widget.ink),
            borderRadius: BorderRadius.circular(AppRadii.pill),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              HeroIcon(
                widget.mode == TaskTimerMode.paused
                    ? HeroPaths.pause
                    : playPath,
                size: 14,
                color: widget.onInk,
              ),
              const SizedBox(width: 8),
              Flexible(
                child: Text(
                  widget.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: widget.onInk,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                widget.time,
                style: TextStyle(
                  fontFamily: 'GeistMono',
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  fontFeatures: const [FontFeature.tabularFigures()],
                  color: widget.onInk,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
