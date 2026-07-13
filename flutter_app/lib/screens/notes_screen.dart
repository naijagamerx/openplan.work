import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/note.dart';
import '../repositories/notes_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Notes list. Mirrors mobile/views/notes.php. A primary bottom-nav tab, so it
/// uses AppScaffold (shared shell + footer) — also still reachable from the hub.
class NotesScreen extends ConsumerStatefulWidget {
  const NotesScreen({super.key});

  @override
  ConsumerState<NotesScreen> createState() => _NotesScreenState();
}

class _NotesScreenState extends ConsumerState<NotesScreen> {
  String _query = '';
  _NoteFilter _filter = _NoteFilter.all;
  String? _tagFilter;

  @override
  Widget build(BuildContext context) {
    final notes = ref.watch(notesProvider);
    final p = AppPalette.of(context);

    return AppScaffold(
      title: 'Notes',
      current: 'Notes',
      floatingActionButton: FloatingActionButton(
        backgroundColor: p.ink,
        foregroundColor: p.onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/notes/new');
          if (mounted) ref.invalidate(notesProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: p.onInk),
      ),
      body: notes.when(
        loading: () => ListView(
          padding:
              const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 120),
          children: const [
            Skeleton(height: 44, radius: AppRadii.button),
            SizedBox(height: 12),
            Skeleton(height: 88, radius: AppRadii.card),
            SizedBox(height: 12),
            Skeleton(height: 88, radius: AppRadii.card),
            SizedBox(height: 12),
            Skeleton(height: 88, radius: AppRadii.card),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.document,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(notesProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.document,
                message: 'No notes yet',
                actionLabel: 'New Note',
                onAction: () => context.push('/notes/new'),
              ),
            );
          }

          // Derive the tag set from the loaded notes.
          final allTags = <String>{};
          for (final n in list) {
            allTags.addAll(n.tags.where((t) => t.toLowerCase() != 'archived'));
          }
          final sortedTags = allTags.toList()..sort();

          // Apply filter tab.
          var filtered = list.where((n) {
            switch (_filter) {
              case _NoteFilter.pinned:
                return n.isPinned;
              case _NoteFilter.favorites:
                return n.isFavorite;
              case _NoteFilter.all:
                return true;
            }
          }).toList();

          // Apply tag filter.
          if (_tagFilter != null) {
            final tag = _tagFilter!.toLowerCase();
            filtered = filtered
                .where((n) => n.tags.any((t) => t.toLowerCase() == tag))
                .toList();
          }

          // Apply search (title + content).
          if (_query.isNotEmpty) {
            final q = _query.toLowerCase();
            filtered = filtered.where((n) {
              return n.title.toLowerCase().contains(q) ||
                  n.content.toLowerCase().contains(q);
            }).toList();
          }

          // The backend sorts pinned-first then updatedAt desc; keep that order
          // but ensure a stable recency fallback when timestamps are missing.
          filtered.sort((a, b) {
            if (a.isPinned != b.isPinned) return a.isPinned ? -1 : 1;
            final at = a.updatedAt ?? a.createdAt ?? DateTime(1970);
            final bt = b.updatedAt ?? b.createdAt ?? DateTime(1970);
            return bt.compareTo(at);
          });

          final pinnedCount = list.where((n) => n.isPinned).length;
          final favCount = list.where((n) => n.isFavorite).length;

          return RefreshIndicator(
            onRefresh: () => ref.refresh(notesProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 120),
              children: [
                _SearchField(
                  value: _query,
                  onChanged: (v) => setState(() => _query = v),
                ),
                const SizedBox(height: 12),
                _FilterTabs(
                  filter: _filter,
                  counts: {
                    _NoteFilter.all: list.length,
                    _NoteFilter.pinned: pinnedCount,
                    _NoteFilter.favorites: favCount,
                  },
                  onSelected: (f) =>
                      setState(() => _filter = f),
                ),
                if (sortedTags.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _TagRow(
                    tags: sortedTags,
                    selected: _tagFilter,
                    onSelected: (t) => setState(() =>
                        _tagFilter = _tagFilter == t ? null : t),
                  ),
                ],
                const SizedBox(height: 8),
                if (filtered.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No notes match'),
                  )
                else
                  for (var i = 0; i < filtered.length; i++)
                    FadeIn(
                      delay: Duration(milliseconds: (i * 60).clamp(0, 360)),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _NoteCard(note: filtered[i]),
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

enum _NoteFilter { all, pinned, favorites }

class _SearchField extends StatelessWidget {
  const _SearchField({required this.value, required this.onChanged});
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return TextField(
      onChanged: onChanged,
      textCapitalization: TextCapitalization.sentences,
      decoration: InputDecoration(
        isDense: true,
        hintText: 'Search notes…',
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

class _FilterTabs extends StatelessWidget {
  const _FilterTabs({
    required this.filter,
    required this.counts,
    required this.onSelected,
  });

  final _NoteFilter filter;
  final Map<_NoteFilter, int> counts;
  final ValueChanged<_NoteFilter> onSelected;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _FilterTab(
            label: 'All',
            count: counts[_NoteFilter.all] ?? 0,
            selected: filter == _NoteFilter.all,
            onTap: () => onSelected(_NoteFilter.all),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _FilterTab(
            label: 'Pinned',
            count: counts[_NoteFilter.pinned] ?? 0,
            selected: filter == _NoteFilter.pinned,
            onTap: () => onSelected(_NoteFilter.pinned),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _FilterTab(
            label: 'Favorites',
            count: counts[_NoteFilter.favorites] ?? 0,
            selected: filter == _NoteFilter.favorites,
            onTap: () => onSelected(_NoteFilter.favorites),
          ),
        ),
      ],
    );
  }
}

class _FilterTab extends StatelessWidget {
  const _FilterTab({
    required this.label,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final int count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: selected ? p.ink : p.surface,
          border: Border.all(color: selected ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(label.toUpperCase(),
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.5,
                    color: selected ? p.onInk : p.textPrimary)),
            const SizedBox(width: 6),
            Text('$count',
                style: TextStyle(
                    fontFamily: 'GeistMono',
                    fontSize: 11,
                    color: selected ? p.onInk : p.textFaint)),
          ],
        ),
      ),
    );
  }
}

class _TagRow extends StatelessWidget {
  const _TagRow({
    required this.tags,
    required this.selected,
    required this.onSelected,
  });

  final List<String> tags;
  final String? selected;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SizedBox(
      height: 30,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: tags.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final tag = tags[i];
          final active = selected?.toLowerCase() == tag.toLowerCase();
          return GestureDetector(
            onTap: () => onSelected(tag),
            behavior: HitTestBehavior.opaque,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: active ? p.ink : p.canvas,
                border: Border.all(color: active ? p.ink : p.border),
                borderRadius: BorderRadius.circular(AppRadii.pill),
              ),
              child: Text('#$tag',
                  style: TextStyle(
                      fontFamily: 'Geist',
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: active ? p.onInk : p.textMuted)),
            ),
          );
        },
      ),
    );
  }
}

class _NoteCard extends StatelessWidget {
  const _NoteCard({required this.note});
  final Note note;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final date = note.updatedAt ?? note.createdAt;
    // The note colour shows as a small dot indicator on the card — the card
    // itself stays the plain surface (no full-card wash).
    final tint = note.colorValue;
    return GestureDetector(
      onTap: () async {
        await context.push('/notes/${note.id}');
      },
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.card),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (tint != null) ...[
                  Padding(
                    padding: const EdgeInsets.only(top: 5, right: 8),
                    child: Container(
                      width: 9,
                      height: 9,
                      decoration:
                          BoxDecoration(color: tint, shape: BoxShape.circle),
                    ),
                  ),
                ],
                Expanded(
                  child: Text(
                    note.title.isEmpty ? 'Untitled' : note.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: p.textPrimary,
                    ),
                  ),
                ),
                if (note.isPinned) ...[
                  const SizedBox(width: 6),
                  HeroIcon(HeroPaths.bookmark,
                      size: 14, color: p.textMuted),
                ],
                if (note.isFavorite) ...[
                  const SizedBox(width: 4),
                  HeroIcon(HeroPaths.star, size: 14, color: p.textFaint),
                ],
              ],
            ),
            if (note.hasContent) ...[
              const SizedBox(height: 6),
              Text(
                note.preview,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(fontSize: 13, color: p.textMuted, height: 1.4),
              ),
            ],
            const SizedBox(height: 10),
            Row(
              children: [
                if (date != null)
                  Text(
                    DateFormat.yMMMd().format(date),
                    style: TextStyle(
                        fontSize: 11,
                        color: p.textFaint,
                        fontFamily: 'GeistMono'),
                  ),
                if (date != null && note.tags.isNotEmpty)
                  const SizedBox(width: 10),
                if (note.tags.isNotEmpty)
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      children: [
                        for (final tag in note.tags.take(3))
                          _TagPill(tag: tag),
                      ],
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _TagPill extends StatelessWidget {
  const _TagPill({required this.tag});
  final String tag;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: p.canvas,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text('#$tag',
          style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: p.textMuted)),
    );
  }
}
