import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/auth_controller.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/habits_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';

class _Module {
  const _Module(this.label, this.icon, {this.route});
  final String label;
  final String icon;
  final String? route; // null = not built yet (shows "coming soon")
}

const _workspace = [
  _Module('Projects', HeroPaths.folder, route: '/projects'),
  _Module('Clients', HeroPaths.users, route: '/clients'),
  _Module('Invoices', HeroPaths.document, route: '/invoices'),
  _Module('Finance', HeroPaths.banknotes, route: '/finance'),
  _Module('Inventory', HeroPaths.cube, route: '/inventory'),
];
const _productivity = [
  _Module('Notes', HeroPaths.pencil, route: '/notes'),
  _Module('Knowledge Base', HeroPaths.book, route: '/knowledge-base'),
  _Module('AI Assistant', HeroPaths.chat, route: '/ai-assistant'),
  _Module('Calendar', HeroPaths.calendar, route: '/calendar'),
  _Module('Meetings', HeroPaths.video, route: '/calendar'),
];
const _system = [
  _Module('Settings', HeroPaths.settings, route: '/settings'),
  _Module('Data Management', HeroPaths.cloud, route: '/backup'),
];

// Health — Pomodoro + Water plan (PHP offcanvas groups these separately from
// the workspace/productivity modules).
const _health = [
  _Module('Pomodoro', HeroPaths.play, route: '/pomodoro'),
  _Module('Water Plan', HeroPaths.droplet, route: '/water'),
];

// Admin-only modules (PHP offcanvas admin section). Rendered conditionally
// when ref.watch(isAdminProvider) is true.
const _admin = [
  _Module('Admin Hub', HeroPaths.commandLine, route: '/admin'),
  _Module('Users', HeroPaths.users, route: '/admin/users'),
];

class MenuHubScreen extends ConsumerWidget {
  const MenuHubScreen({super.key});

  static const _perLevel = 25; // tasks completed per level

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final name = ref.watch(userNameProvider);
    final stats = ref.watch(dashboardProvider).asData?.value;
    final habits = ref.watch(habitsListProvider).asData?.value;
    final isAdmin = ref.watch(isAdminProvider);

    final completed = stats?.completedTasks ?? 0;
    final projects = stats?.totalProjects ?? 0;
    final streak = (habits == null || habits.isEmpty)
        ? 0
        : habits.map((h) => h.currentStreak).fold<int>(0, (a, b) => b > a ? b : a);

    return AppScaffold(
      title: 'Menu',
      current: 'Menu',
      body: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 20, AppSpacing.page, 24),
        children: [
          FadeIn(
            child: _ProfileCard(
              name: name,
              completed: completed,
              streak: streak,
              projects: projects,
              perLevel: _perLevel,
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          const FadeIn(
            delay: Duration(milliseconds: 90),
            child: _Group(title: 'Workspace', modules: _workspace),
          ),
          const SizedBox(height: AppSpacing.gap),
          const FadeIn(
            delay: Duration(milliseconds: 150),
            child: _Group(title: 'Productivity', modules: _productivity),
          ),
          const SizedBox(height: AppSpacing.gap),
          const FadeIn(
            delay: Duration(milliseconds: 210),
            child: _Group(title: 'Health', modules: _health),
          ),
          const SizedBox(height: AppSpacing.gap),
          const FadeIn(
            delay: Duration(milliseconds: 270),
            child: _Group(title: 'System', modules: _system),
          ),
          if (isAdmin) ...[
            const SizedBox(height: AppSpacing.gap),
            const FadeIn(
              delay: Duration(milliseconds: 330),
              child: _Group(title: 'Admin', modules: _admin),
            ),
          ],
        ],
      ),
    );
  }
}

class _ProfileCard extends StatelessWidget {
  const _ProfileCard({
    required this.name,
    required this.completed,
    required this.streak,
    required this.projects,
    required this.perLevel,
  });

  final String? name;
  final int completed;
  final int streak;
  final int projects;
  final int perLevel;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final level = 1 + completed ~/ perLevel;
    final xp = completed % perLevel;
    final progress = xp / perLevel;
    final display = (name == null || name!.trim().isEmpty) ? 'OpenPlan User' : name!;
    final initial = display.trim()[0].toUpperCase();

    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: p.ink,
        borderRadius: BorderRadius.circular(AppRadii.section),
        // DESIGN.md §7: borders, not shadows. The ink fill is enough.
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(color: p.onInk, shape: BoxShape.circle),
                alignment: Alignment.center,
                child: Text(initial,
                    style: TextStyle(
                        fontFamily: 'Geist',
                        fontSize: 22,
                        fontWeight: FontWeight.w700,
                        color: p.ink)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(display,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 17,
                            fontWeight: FontWeight.w700,
                            color: p.onInk)),
                    const SizedBox(height: 2),
                    Text('LEVEL $level',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 2,
                          color: p.onInk.withValues(alpha: 0.55),
                        )),
                  ],
                ),
              ),
              HeroIcon(HeroPaths.fire,
                  size: 22, color: p.onInk.withValues(alpha: 0.8)),
            ],
          ),
          const SizedBox(height: 20),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('$xp / $perLevel XP',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.5,
                    color: p.onInk.withValues(alpha: 0.55),
                  )),
              Text('LEVEL ${level + 1}',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.5,
                    color: p.onInk.withValues(alpha: 0.4),
                  )),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.pill),
            child: TweenAnimationBuilder<double>(
              tween: Tween(begin: 0, end: progress),
              duration: const Duration(milliseconds: 800),
              curve: Curves.easeOutCubic,
              builder: (_, v, __) => LinearProgressIndicator(
                value: v,
                minHeight: 8,
                backgroundColor: p.onInk.withValues(alpha: 0.15),
                valueColor: AlwaysStoppedAnimation(p.onInk),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Container(height: 1, color: p.onInk.withValues(alpha: 0.15)),
          const SizedBox(height: 16),
          Row(
            children: [
              _stat(p, '$completed', 'DONE'),
              _vline(p),
              _stat(p, '$streak', 'DAY STREAK'),
              _vline(p),
              _stat(p, '$projects', 'PROJECTS'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _stat(AppPalette p, String value, String label) => Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value,
                style: AppType.mono(
                    size: 20, weight: FontWeight.w600, color: p.onInk)),
            const SizedBox(height: 3),
            Text(label,
                style: TextStyle(
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                  color: p.onInk.withValues(alpha: 0.45),
                )),
          ],
        ),
      );

  Widget _vline(AppPalette p) => Container(
        width: 1,
        height: 30,
        margin: const EdgeInsets.symmetric(horizontal: 12),
        color: p.onInk.withValues(alpha: 0.15),
      );
}

class _Group extends StatelessWidget {
  const _Group({required this.title, required this.modules});
  final String title;
  final List<_Module> modules;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title.toUpperCase(),
            style: AppType.label(context,
                color: AppPalette.of(context).textPrimary, size: 13)),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, c) {
            final w = (c.maxWidth - 12) / 2;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                for (final m in modules)
                  SizedBox(width: w, child: _ModuleCard(module: m)),
              ],
            );
          },
        ),
      ],
    );
  }
}

class _ModuleCard extends StatefulWidget {
  const _ModuleCard({required this.module});
  final _Module module;

  @override
  State<_ModuleCard> createState() => _ModuleCardState();
}

class _ModuleCardState extends State<_ModuleCard> {
  bool _down = false;

  void _open() {
    final route = widget.module.route;
    if (route != null) {
      context.push(route);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${widget.module.label} coming soon'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTapDown: (_) => setState(() => _down = true),
      onTapUp: (_) => setState(() => _down = false),
      onTapCancel: () => setState(() => _down = false),
      onTap: _open,
      child: AnimatedScale(
        scale: _down ? 0.97 : 1,
        duration: const Duration(milliseconds: 90),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: p.surface,
            border: Border.all(color: p.border),
            borderRadius: BorderRadius.circular(AppRadii.card),
            // DESIGN.md §7: borders, not shadows.
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: p.canvas,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: p.border),
                ),
                alignment: Alignment.center,
                child: HeroIcon(widget.module.icon, size: 22, color: p.textPrimary),
              ),
              const SizedBox(height: 14),
              Text(widget.module.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: p.textPrimary)),
            ],
          ),
        ),
      ),
    );
  }
}
