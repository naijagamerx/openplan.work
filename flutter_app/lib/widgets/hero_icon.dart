import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Renders a Heroicons (outline) path as an inline SVG — the app's only icon
/// system (hard rule in DESIGN.md). Strokes in the given color (defaults to the
/// current onSurface ink), matching the web's `stroke="currentColor"`.
class HeroIcon extends StatelessWidget {
  const HeroIcon(
    this.path, {
    super.key,
    this.size = 20,
    this.color,
    this.strokeWidth = 2,
  });

  final String path;
  final double size;
  final Color? color;
  final double strokeWidth;

  @override
  Widget build(BuildContext context) {
    final c = color ?? Theme.of(context).colorScheme.onSurface;
    final hex = '#${(c.toARGB32() & 0xFFFFFF).toRadixString(16).padLeft(6, '0')}';
    final svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" '
        'fill="none" stroke="$hex" stroke-width="$strokeWidth" '
        'stroke-linecap="round" stroke-linejoin="round"><path d="$path"/></svg>';
    return SvgPicture.string(svg, width: size, height: size);
  }
}
