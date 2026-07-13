import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';

/// Monochrome button with tactile press (scale 0.96, matches web `active:scale-95`).
/// Primary = ink fill + inverted text; secondary = 1px ink outline.
class MonoButton extends StatefulWidget {
  const MonoButton(
    this.label, {
    super.key,
    this.onPressed,
    this.primary = true,
    this.expand = true,
    this.dense = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool primary;
  final bool expand;
  final bool dense;

  @override
  State<MonoButton> createState() => _MonoButtonState();
}

class _MonoButtonState extends State<MonoButton> {
  bool _down = false;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTapDown: (_) => setState(() => _down = true),
      onTapUp: (_) => setState(() => _down = false),
      onTapCancel: () => setState(() => _down = false),
      onTap: widget.onPressed,
      child: AnimatedScale(
        scale: _down ? 0.96 : 1,
        duration: const Duration(milliseconds: 90),
        child: Container(
          width: widget.expand ? double.infinity : null,
          constraints: const BoxConstraints(minHeight: AppSpacing.touchTarget),
          padding: EdgeInsets.symmetric(
            vertical: widget.dense ? 10 : 14,
            horizontal: 20,
          ),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: widget.primary ? p.ink : Colors.transparent,
            border: widget.primary ? null : Border.all(color: p.ink),
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          child: Text(
            widget.label.toUpperCase(),
            style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 1,
              color: widget.primary ? p.onInk : p.textPrimary,
            ),
          ),
        ),
      ),
    );
  }
}
