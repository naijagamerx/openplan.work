import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';

/// A large bordered container (rounded-3xl) with an uppercase heading and an
/// optional trailing action (e.g. "View all").
class SectionCard extends StatelessWidget {
  const SectionCard({
    super.key,
    this.heading,
    required this.child,
    this.trailing,
  });

  final String? heading;
  final Widget child;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.sectionPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.section),
        // DESIGN.md §7: cards use borders, never shadows.
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (heading != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(heading!.toUpperCase(),
                      style: AppType.label(context,
                          color: p.textPrimary, size: 13)),
                  if (trailing != null) trailing!,
                ],
              ),
            ),
          child,
        ],
      ),
    );
  }
}
