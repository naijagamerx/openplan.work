import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

/// Skeletal shimmer block matching layout dimensions (no circular spinners,
/// per DESIGN.md motion philosophy).
class Skeleton extends StatefulWidget {
  const Skeleton({super.key, this.width, this.height = 16, this.radius = 8});

  final double? width;
  final double height;
  final double radius;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return AnimatedBuilder(
      animation: _c,
      builder: (_, __) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: Color.lerp(p.border, p.surface, _c.value),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

/// Skeleton placeholder shaped like the dashboard while data loads.
class DashboardSkeleton extends StatelessWidget {
  const DashboardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    Widget card() => Container(
          height: 104,
          decoration: BoxDecoration(
            color: AppPalette.of(context).surface,
            border: Border.all(color: AppPalette.of(context).border),
            borderRadius: BorderRadius.circular(AppRadii.card),
          ),
          padding: const EdgeInsets.all(AppSpacing.cardPad),
          child: const Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Skeleton(width: 80, height: 10),
              SizedBox(height: 16),
              Skeleton(width: 48, height: 28),
            ],
          ),
        );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Skeleton(width: 200, height: 26),
        const SizedBox(height: 8),
        const Skeleton(width: 120, height: 12),
        const SizedBox(height: AppSpacing.gap),
        Row(children: [Expanded(child: card()), const SizedBox(width: 12), Expanded(child: card())]),
        const SizedBox(height: 12),
        Row(children: [Expanded(child: card()), const SizedBox(width: 12), Expanded(child: card())]),
      ],
    );
  }
}
