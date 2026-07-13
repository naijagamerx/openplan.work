import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';
import 'hero_icon.dart';
import 'mono_button.dart';

/// Composed empty state: heroicon + message + optional CTA (DESIGN.md §7).
class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.iconPath,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final String iconPath;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 32),
      child: Column(
        children: [
          HeroIcon(iconPath, size: 40, color: p.textFaint, strokeWidth: 1.5),
          const SizedBox(height: 12),
          Text(message, style: TextStyle(color: p.textFaint, fontSize: 14)),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 16),
            MonoButton(actionLabel!,
                onPressed: onAction, expand: false, dense: true),
          ],
        ],
      ),
    );
  }
}
