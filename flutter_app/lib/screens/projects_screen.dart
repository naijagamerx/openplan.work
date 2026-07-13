import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/project.dart';
import '../repositories/tasks_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Projects list screen — mirrors mobile/views/projects.php.
/// Cards show color avatar, name, status badge, client, task count, progress.
class ProjectsScreen extends ConsumerStatefulWidget {
  const ProjectsScreen({super.key});

  @override
  ConsumerState<ProjectsScreen> createState() => _ProjectsScreenState();
}

class _ProjectsScreenState extends ConsumerState<ProjectsScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final projects = ref.watch(projectsProvider);

    return AppScaffold(
      title: 'Projects',
      current: 'Projects',
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppPalette.of(context).ink,
        foregroundColor: AppPalette.of(context).onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/projects/new');
          if (mounted) ref.invalidate(projectsProvider);
        },
        child:
            HeroIcon(HeroPaths.plus, size: 24, color: AppPalette.of(context).onInk),
      ),
      body: projects.when(
        loading: () => ListView(
          padding:
              const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 120),
          children: const [
            Skeleton(height: 140, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.section),
            SizedBox(height: 12),
            Skeleton(height: 140, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.folder,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(projectsProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.folder,
                message: 'No projects yet',
                actionLabel: 'New Project',
                onAction: () => context.push('/projects/new'),
              ),
            );
          }

          final filtered = list.where((p) {
            if (_query.isEmpty) return true;
            final q = _query.toLowerCase();
            return p.name.toLowerCase().contains(q) ||
                Project.statusLabel(p.status).toLowerCase().contains(q) ||
                (p.clientName ?? '').toLowerCase().contains(q) ||
                p.description.toLowerCase().contains(q);
          }).toList();

          final activeCount = list
              .where((p) =>
                  !const ['completed', 'cancelled']
                      .contains(Project.normalizeStatus(p.status)))
              .length;
          final completedCount = list
              .where((p) => Project.normalizeStatus(p.status) == 'completed')
              .length;

          return RefreshIndicator(
            onRefresh: () => ref.refresh(projectsProvider.future),
            child: ListView(
              padding:
                  const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 120),
              children: [
                _SearchField(
                  query: _query,
                  onChanged: (v) => setState(() => _query = v),
                ),
                const SizedBox(height: 12),
                _StatStrip(
                  total: list.length,
                  active: activeCount,
                  completed: completedCount,
                ),
                const SizedBox(height: 12),
                if (filtered.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No projects match'),
                  )
                else
                  for (var i = 0; i < filtered.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 70).clamp(0, 420)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ProjectCard(project: filtered[i]),
                      ),
                    ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.query, required this.onChanged});

  final String query;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return TextField(
      onChanged: onChanged,
      textCapitalization: TextCapitalization.sentences,
      decoration: InputDecoration(
        isDense: true,
        hintText: 'Search projects…',
        hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
        prefixIcon: HeroIcon(HeroPaths.search, size: 18, color: p.textFaint),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.border)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.border)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
            borderSide: BorderSide(color: p.ink, width: 1.5)),
      ),
    );
  }
}

class _StatStrip extends StatelessWidget {
  const _StatStrip(
      {required this.total, required this.active, required this.completed});

  final int total;
  final int active;
  final int completed;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _StatTile(label: 'Total', value: total)),
        const SizedBox(width: 8),
        Expanded(child: _StatTile(label: 'Active', value: active)),
        const SizedBox(width: 8),
        Expanded(child: _StatTile(label: 'Done', value: completed)),
      ],
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value});
  final String label;
  final int value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        children: [
          Text('$value', style: AppType.mono(size: 22, color: p.textPrimary)),
          const SizedBox(height: 2),
          Text(label.toUpperCase(), style: AppType.label(context, size: 9)),
        ],
      ),
    );
  }
}

class _ProjectCard extends StatelessWidget {
  const _ProjectCard({required this.project});
  final Project project;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final avatarColor = _parseColor(project.color) ?? p.ink;
    final progressPct = (project.progress * 100).round();

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () async {
        await context.push('/projects/${project.id}');
      },
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.cardPad),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.section),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _Avatar(initial: project.initial, color: avatarColor),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        project.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          letterSpacing: -0.2,
                          color: p.textPrimary,
                        ),
                      ),
                      if (project.clientName != null &&
                          project.clientName!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          project.clientName!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              TextStyle(fontSize: 12, color: p.textMuted),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                _StatusBadge(status: project.status),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Text('TASKS', style: AppType.label(context, size: 9)),
                const SizedBox(width: 6),
                Text(
                  '${project.completedCount}/${project.taskCount}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary,
                  ),
                ),
                const Spacer(),
                Text('$progressPct%',
                    style: AppType.label(context,
                        color: p.textMuted, size: 10)),
              ],
            ),
            if (project.taskCount > 0) ...[
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadii.pill),
                child: LinearProgressIndicator(
                  value: project.progress,
                  minHeight: 6,
                  backgroundColor: p.border,
                  valueColor: AlwaysStoppedAnimation<Color>(p.ink),
                ),
              ),
            ],
            if (project.hasDescription) ...[
              const SizedBox(height: 12),
              Text(
                project.description,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 12, height: 1.4, color: p.textMuted),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.initial, required this.color});
  final String initial;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Text(
        initial,
        style: const TextStyle(
          fontFamily: 'Geist',
          fontSize: 16,
          fontWeight: FontWeight.w800,
          color: Colors.white,
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final key = Project.normalizeStatus(status);
    // Completed/active get an ink fill; others are bordered pills.
    final filled = key == 'completed' || key == 'active';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: filled ? p.ink : Colors.transparent,
        border: Border.all(color: p.ink),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        Project.statusLabel(status).toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.6,
          color: filled ? p.onInk : p.textPrimary,
        ),
      ),
    );
  }
}

/// Parse a hex color string (#RGB / #RRGGBB), returning null if invalid.
Color? _parseColor(String? hex) {
  if (hex == null || hex.isEmpty) return null;
  var h = hex.replaceFirst('#', '');
  if (h.length == 3) {
    h = h.split('').map((c) => '$c$c').join();
  }
  if (h.length != 6) return null;
  final value = int.tryParse('FF$h', radix: 16);
  if (value == null) return null;
  return Color(value);
}
