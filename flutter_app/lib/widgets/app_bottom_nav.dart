import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/app_tokens.dart';
import 'hero_icon.dart';

class NavTab {
  const NavTab(this.label, this.iconPath, {this.route});
  final String label;
  final String iconPath;
  final String? route; // null = not yet built (shows "coming soon")
}

/// Drop-in floating footer for secondary/pushed screens (Pomodoro, Water Plan,
/// Calendar, …) that keep their OWN AppBar + back arrow, so they can't use the
/// full [AppScaffold] shell. Encapsulates the tab→route navigation once so any
/// Scaffold just adds `bottomNavigationBar: const AppNavBar()`.
///
/// Pass [current] only when the screen corresponds to a nav tab; on a pushed
/// section page leave it '' so nothing is falsely highlighted.
///
/// NOTE: the pill floats over content — a scrolling body must pad its bottom by
/// at least [kBottomNavClearance] so the last item clears it.
class AppNavBar extends StatelessWidget {
  const AppNavBar({super.key, this.current = ''});

  final String current;

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.transparent,
      child: AppBottomNav(
        current: current,
        onTap: (tab) {
          if (tab.label == current) return;
          if (tab.route != null) {
            context.go(tab.route!);
          } else {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('${tab.label} coming soon'),
                behavior: SnackBarBehavior.floating,
              ),
            );
          }
        },
      ),
    );
  }
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
        // FittedBox scales the WHOLE item (icon + optional label) down to fit
        // its flex slot, so a revealed label can never overflow — this is what
        // produced the "RIGHT overflowed by ~1.7px" stripe: a pressed inactive
        // tab (narrow 1-flex slot) revealed its label, and the fixed icon +
        // paddings alone were ~2px wider than the slot. Scaling only the label
        // couldn't recover that fixed width; scaling the whole row can, on any
        // screen width. mainAxisSize.min gives FittedBox the row's natural size
        // to scale from.
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              HeroIcon(
                widget.tab.iconPath,
                size: 22,
                color: color,
                strokeWidth: widget.active ? 1.8 : 1.5,
              ),
              if (reveal)
                Padding(
                  padding: const EdgeInsets.only(left: 8),
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
            ],
          ),
        ),
      ),
    );
  }
}
