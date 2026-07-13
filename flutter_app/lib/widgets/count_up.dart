import 'package:flutter/material.dart';

/// Animated integer that counts up from 0 to [value] (premium stat reveal).
class CountUp extends StatelessWidget {
  const CountUp(
    this.value, {
    super.key,
    this.style,
    this.duration = const Duration(milliseconds: 650),
  });

  final int value;
  final TextStyle? style;
  final Duration duration;

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<int>(
      tween: IntTween(begin: 0, end: value),
      duration: duration,
      curve: Curves.easeOutCubic,
      builder: (_, v, __) => Text('$v', style: style),
    );
  }
}
