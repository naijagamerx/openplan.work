import 'package:flutter/material.dart';

import '../config/app_config.dart';

/// The brand logo, fetched from the admin-configured favicon on the backend.
///
/// The admin uploads a logo via Settings → Favicon (api/favicon.php), which
/// writes `assets/favicons/apple-touch-icon.png` (and the .svg/.ico variants).
/// This widget loads that file directly off the server, so the native app
/// always reflects the admin's chosen branding — exactly like the PHP web app,
/// which just references the same static path.
///
/// On any load failure (offline, no custom icon, 404) it falls back to a
/// monochrome "O" tile so the UI never shows a broken image.
class BrandLogo extends StatefulWidget {
  const BrandLogo({
    super.key,
    this.size = 64,
    this.radius = 18,
    this.dark = false,
    this.fallbackLetter = 'O',
  });

  /// Edge length of the square logo tile.
  final double size;
  final double radius;

  /// When true, the fallback tile uses an ink fill (for use on light surfaces).
  /// When false, the fallback uses a bordered transparent tile (for use on the
  /// dark hero band).
  final bool dark;

  final String fallbackLetter;

  @override
  State<BrandLogo> createState() => _BrandLogoState();
}

class _BrandLogoState extends State<BrandLogo> {
  bool _failed = false;

  /// Cache-busting query so a re-uploaded logo shows up after a reinstall /
  /// app restart rather than being served from the HTTP cache forever.
  static final String _cacheBust =
      DateTime.now().millisecondsSinceEpoch.toString();

  @override
  Widget build(BuildContext context) {
    if (_failed) return _fallback;

    final url =
        '${AppConfig.baseUrl}/assets/favicons/apple-touch-icon.png?cb=$_cacheBust';
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.radius),
      child: Image.network(
        url,
        width: widget.size,
        height: widget.size,
        fit: BoxFit.cover,
        loadingBuilder: (context, child, progress) {
          if (progress == null) return child;
          return SizedBox(
              width: widget.size,
              height: widget.size,
              child: const Center(child: SizedBox.shrink()));
        },
        errorBuilder: (_, __, ___) {
          // Defer the state flip so we don't rebuild during build.
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) setState(() => _failed = true);
          });
          return _fallback;
        },
      ),
    );
  }

  Widget get _fallback {
    final p = Theme.of(context).colorScheme;
    final ink = widget.dark ? (p.onSurface) : Colors.white;
    final onInk = widget.dark ? p.surface : Colors.white;
    return Container(
      width: widget.size,
      height: widget.size,
      decoration: BoxDecoration(
        color: widget.dark ? ink : Colors.transparent,
        border: Border.all(
            color: widget.dark ? ink : Colors.white24, width: 1.5),
        borderRadius: BorderRadius.circular(widget.radius),
      ),
      alignment: Alignment.center,
      child: Text(
        widget.fallbackLetter,
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: widget.size * 0.5,
          fontWeight: FontWeight.w900,
          color: widget.dark ? onInk : Colors.white,
        ),
      ),
    );
  }
}
