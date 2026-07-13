import 'dart:math';

import 'package:flutter/material.dart';

/// Animated glass of water (waves + rising bubbles + handle), matching the web
/// source's hydration visual. Blue water is the design's one sanctioned color.
class WaterCup extends StatefulWidget {
  const WaterCup({super.key, required this.progress, this.size = 86});

  final double progress; // 0..1
  final double size;

  @override
  State<WaterCup> createState() => _WaterCupState();
}

class _WaterCupState extends State<WaterCup>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 4),
  )..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: widget.size,
      height: widget.size * 1.2,
      child: AnimatedBuilder(
        animation: _c,
        builder: (_, __) => CustomPaint(
          painter: _CupPainter(
            progress: widget.progress.clamp(0, 1).toDouble(),
            t: _c.value,
          ),
        ),
      ),
    );
  }
}

class _CupPainter extends CustomPainter {
  _CupPainter({required this.progress, required this.t});

  final double progress;
  final double t;

  static const _water1 = Color(0xFF3B82F6);
  static const _water2 = Color(0xFF1D4ED8);
  static const _glass = Color(0xFF0EA5E9);
  static const _glassFill = Color(0x14BAE6FD);

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width, h = size.height;
    final bodyW = w * 0.78;
    final left = (w - bodyW) / 2;

    // Mug body (slightly tapered tumbler).
    final body = Path()
      ..moveTo(left + bodyW * 0.04, h * 0.05)
      ..lineTo(left + bodyW * 0.96, h * 0.05)
      ..lineTo(left + bodyW * 0.86, h * 0.95)
      ..quadraticBezierTo(
          left + bodyW * 0.84, h, left + bodyW * 0.78, h)
      ..lineTo(left + bodyW * 0.22, h)
      ..quadraticBezierTo(
          left + bodyW * 0.16, h, left + bodyW * 0.14, h * 0.95)
      ..close();

    // Handle.
    final handleP = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = w * 0.05
      ..strokeCap = StrokeCap.round
      ..color = _glass;
    final hx = left + bodyW * 0.96;
    final handle = Path()
      ..moveTo(hx, h * 0.22)
      ..quadraticBezierTo(w, h * 0.22, w, h * 0.42)
      ..quadraticBezierTo(w, h * 0.62, hx, h * 0.6);
    canvas.drawPath(handle, handleP);

    // Glass fill tint.
    canvas.drawPath(body, Paint()..color = _glassFill);

    // Water (clipped to body).
    canvas.save();
    canvas.clipPath(body);
    final topY = h * 0.05 + (h * 0.9) * (1 - progress);

    Path wave(double amp, double phase, double yOff) {
      final p = Path()..moveTo(0, topY + yOff);
      for (double x = 0; x <= w; x += 2) {
        final y = topY + yOff + sin((x / w * 2 * pi) + phase) * amp;
        p.lineTo(x, y);
      }
      return p
        ..lineTo(w, h)
        ..lineTo(0, h)
        ..close();
    }

    final shader = const LinearGradient(
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
      colors: [_water1, _water2],
    ).createShader(Rect.fromLTWH(0, topY, w, h - topY));
    canvas.drawPath(wave(4, t * 2 * pi, 0), Paint()..shader = shader);
    canvas.drawPath(
        wave(3, t * 2 * pi + pi, 4), Paint()..color = const Color(0x6693C5FD));

    // Rising bubbles.
    if (progress > 0.05) {
      final bubble = Paint()..color = Colors.white;
      final seeds = [
        [0.42, 0.0, 2.5],
        [0.58, 0.4, 2.0],
        [0.5, 0.75, 3.0],
      ];
      for (final s in seeds) {
        final frac = (t + s[1]) % 1.0;
        final by = h - frac * (h - topY) * 0.9;
        if (by <= topY) continue;
        final op = (1 - frac) * 0.5;
        canvas.drawCircle(Offset(left + bodyW * s[0], by), s[2],
            bubble..color = Colors.white.withValues(alpha: op));
      }
    }
    canvas.restore();

    // Glass outline + shine.
    canvas.drawPath(
      body,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2.5
        ..strokeJoin = StrokeJoin.round
        ..color = _glass,
    );
    final shine = Path()
      ..moveTo(left + bodyW * 0.22, h * 0.18)
      ..quadraticBezierTo(
          left + bodyW * 0.26, h * 0.4, left + bodyW * 0.22, h * 0.62);
    canvas.drawPath(
      shine,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3
        ..strokeCap = StrokeCap.round
        ..color = Colors.white.withValues(alpha: 0.45),
    );
  }

  @override
  bool shouldRepaint(covariant _CupPainter old) =>
      old.progress != progress || old.t != t;
}
