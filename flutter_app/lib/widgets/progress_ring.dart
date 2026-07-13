import 'dart:math';

import 'package:flutter/material.dart';

/// Apple Activity-style progress ring: rounded-cap sweep over a faint track,
/// animated on appear, with an optional centered child. Supports a single color
/// or a sweep gradient (used for the blue hydration ring).
class ProgressRing extends StatelessWidget {
  const ProgressRing({
    super.key,
    required this.progress,
    required this.trackColor,
    this.size = 80,
    this.stroke = 8,
    this.color,
    this.gradient,
    this.center,
    this.duration = const Duration(milliseconds: 900),
  });

  final double progress; // 0..1
  final Color trackColor;
  final double size;
  final double stroke;
  final Color? color;
  final List<Color>? gradient;
  final Widget? center;
  final Duration duration;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: progress.clamp(0, 1).toDouble()),
        duration: duration,
        curve: Curves.easeOutCubic,
        builder: (_, v, __) => CustomPaint(
          painter: _RingPainter(
            progress: v,
            stroke: stroke,
            track: trackColor,
            color: color,
            gradient: gradient,
          ),
          child: Center(child: center),
        ),
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter({
    required this.progress,
    required this.stroke,
    required this.track,
    this.color,
    this.gradient,
  });

  final double progress;
  final double stroke;
  final Color track;
  final Color? color;
  final List<Color>? gradient;

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = (size.width - stroke) / 2;

    final trackPaint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..color = track;
    canvas.drawCircle(center, radius, trackPaint);

    if (progress <= 0) return;

    final arc = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;
    if (gradient != null) {
      arc.shader = SweepGradient(
        startAngle: -pi / 2,
        endAngle: 3 * pi / 2,
        colors: gradient!,
      ).createShader(Rect.fromCircle(center: center, radius: radius));
    } else {
      arc.color = color ?? const Color(0xFF0A0A0A);
    }
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -pi / 2,
      2 * pi * progress,
      false,
      arc,
    );
  }

  @override
  bool shouldRepaint(covariant _RingPainter old) => old.progress != progress;
}
