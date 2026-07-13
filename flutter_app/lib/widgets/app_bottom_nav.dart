import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';
import 'hero_icon.dart';

class NavTab {
  const NavTab(this.label, this.iconPath, {this.route});
  final String label;
  final String iconPath;
  final String? route; // null = not yet built (shows "coming soon")
}

/// Total bottom clearance every primary screen's scrollable list must add so
/// its last item clears the floating footer. Includes the footer height + the
/// system-nav inset + breathing room. Screens add this to their ListView
/// padding.bottom.
const double kBottomNavClearance = 96;

/// Interactive floating icon footer. Icons float in a rounded pill above the
/// safe area with a border; labels are hidden by default and animate in only
/// for the active tab (or momentarily on press), so the bar feels alive.
///
/// Mirrors the design language of modern apps: minimal chrome, icon-forward,
/// text revealed on demand.
class AppBottomNav extends StatelessWidget {
  const AppBottomNav({super.key, required this.current, required this.onTap});

  final String current;
  final void Function(NavTab) onTap;

  static const tabs = [
    NavTab('Dashboard', HeroPaths.home, route: '/'),
    NavTab('Tasks', HeroPaths.tasks, route: '/tasks'),
    NavTab('Notes', HeroPaths.pencil, route: '/notes'),
    NavTab('Habits', HeroPaths.sparkles, route: '/habits'),
    NavTab('Menu', HeroPaths.menu, route: '/menu'),
  ];

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final bottomInset = MediaQuery.of(context).padding.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 12 + bottomInset),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        decoration: BoxDecoration(
          color: p.surface,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          border: Border.all(color: p.border, width: 1),
        ),
        child: Row(
          // Every item is wrapped in Expanded so the Row always sums to the
          // bar's width — no item can push another off the right edge (which
          // was producing "RIGHT overflowed by 21 pixels" when the active
          // label's natural width exceeded its fair share). The active item
          // gets a larger flex so its label has room; the label itself uses
          // FittedBox(scaleDown) so it shrinks gracefully if the slot is still
          // too tight, instead of overflowing.
          children: [
            for (final t in tabs)
              Expanded(
                flex: t.label == current ? 3 : 1,
                child: _FloatingItem(
                  tab: t,
                  active: t.label == current,
                  onTap: () => onTap(t),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// A single floating nav item. Collapsed = icon only. When active or pressed,
/// the label animates in beside the icon and the whole item gets an ink fill.
class _FloatingItem extends StatefulWidget {
  const _FloatingItem({
    required this.tab,
    required this.active,
    required this.onTap,
  });

  final NavTab tab;
  final bool active;
  final VoidCallback onTap;

  @override
  State<_FloatingItem> createState() => _FloatingItemState();
}

class _FloatingItemState extends State<_FloatingItem> {
  bool _pressed = false;

  void _setPressed(bool v) {
    if (_pressed != v) setState(() => _pressed = v);
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    // Reveal the label when this item is active OR while pressed.
    final reveal = widget.active || _pressed;
    final color = widget.active ? p.onInk : p.textFaint;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTapDown: (_) => _setPressed(true),
      onTapUp: (_) => _setPressed(false),
      onTapCancel: () => _setPressed(false),
      onTap: widget.onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOutCubic,
        padding: EdgeInsets.symmetric(
          horizontal: reveal ? 12 : 6,
          vertical: 10,
        ),
        decoration: BoxDecoration(
          color: widget.active ? p.ink : Colors.transparent,
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Row(
          // MainAxisSize.max so the pill fills its Expanded slot exactly.
          // With MainAxisSize.min the Row committed to its content's natural
          // width before the Flexible/FittedBox could shrink the label, which
          // made pressed inactive items overflow their slot by ~21px ("RIGHT
          // overflowed by 21 pixels"). Max lets the slot impose a hard width
          // and the FittedBox scales the label to fit.
          mainAxisSize: MainAxisSize.max,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            HeroIcon(
              widget.tab.iconPath,
              size: 22,
              color: color,
              strokeWidth: widget.active ? 1.8 : 1.5,
            ),
            // Animated label: grows in beside the icon. Wrapped in Flexible +
            // FittedBox(scaleDown) so that if the slot (Expanded with flex) is
            // narrower than the text's natural width, the text shrinks instead
            // of overflowing its sibling / the bar edge.
            Flexible(
              child: AnimatedSize(
                duration: const Duration(milliseconds: 180),
                curve: Curves.easeOutCubic,
                alignment: Alignment.centerLeft,
                child: reveal
                    ? Padding(
                        padding: const EdgeInsets.only(left: 8),
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerLeft,
                          child: Text(
                            widget.tab.label.toUpperCase(),
                            maxLines: 1,
                            softWrap: false,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                              color: color,
                            ),
                          ),
                        ),
                      )
                    : const SizedBox(width: 0),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
