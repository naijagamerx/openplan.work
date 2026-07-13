import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'count_up.dart';
import 'hero_icon.dart';

/// A 2×2-grid stat tile: uppercase micro-label + heroicon, big Geist-Mono value
/// (animated count-up when numeric), and either a sub-line or an animated ink
/// progress bar. Border-defined, no shadow (DESIGN.md §7).
class StatCard extends StatelessWidget {
  const StatCard({
    super.key,
    required this.label,
    required this.iconPath,
    this.valueInt,
    this.valueText,
    this.sub,
    this.progress,
  }) : assert(valueInt != null || valueText != null,
            'Provide valueInt or valueText');

  final String label;
  final String iconPath;
  final int? valueInt; // animated count-up
  final String? valueText; // static (e.g. "0/3")
  final String? sub;
  final double? progress; // 0..1 → animated ink progress bar instead of sub

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final numStyle = AppType.mono(size: 30, color: p.textPrimary);

    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
        // DESIGN.md §7: borders, not shadows.
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(label.toUpperCase(), style: AppType.label(context)),
              ),
              HeroIcon(iconPath, size: 20, color: p.textPrimary),
            ],
          ),
          const SizedBox(height: 8),
          valueInt != null
              ? CountUp(valueInt!, style: numStyle)
              : Text(valueText!, style: numStyle),
          if (progress != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AppRadii.pill),
                child: TweenAnimationBuilder<double>(
                  tween: Tween(begin: 0, end: progress!.clamp(0, 1)),
                  duration: const Duration(milliseconds: 700),
                  curve: Curves.easeOutCubic,
                  builder: (_, v, __) => LinearProgressIndicator(
                    value: v,
                    minHeight: 4,
                    backgroundColor: p.border,
                    valueColor: AlwaysStoppedAnimation(p.ink),
                  ),
                ),
              ),
            )
          else if (sub != null)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child:
                  Text(sub!, style: TextStyle(fontSize: 12, color: p.textFaint)),
            ),
        ],
      ),
    );
  }
}
