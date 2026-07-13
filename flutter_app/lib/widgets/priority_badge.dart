import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

/// Semantic-colored priority pill (the only non-monochrome surface), matching
/// the web dashboard's `bg-red-600 / orange-500 / yellow-500 / green-500`.
class PriorityBadge extends StatelessWidget {
  const PriorityBadge(this.priority, {super.key});

  final String priority;

  @override
  Widget build(BuildContext context) {
    final s = priorityStyle(priority);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: s.bg,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        priority.toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 10,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
          color: s.fg,
        ),
      ),
    );
  }
}
