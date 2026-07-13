import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/audit_log.dart';
import '../repositories/audit_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Audit logs screen — stats cards (total, today), a search field, an
/// event-type filter dropdown, and a scrollable log list. Mirrors
/// mobile/views/audit-logs.php.
class AuditLogsScreen extends ConsumerStatefulWidget {
  const AuditLogsScreen({super.key});

  @override
  ConsumerState<AuditLogsScreen> createState() => _AuditLogsScreenState();
}

class _AuditLogsScreenState extends ConsumerState<AuditLogsScreen> {
  final TextEditingController _searchController = TextEditingController();
  Timer? _debounce;
  String _search = '';
  String? _event;

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.removeListener(_onSearchChanged);
    _searchController.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearchChanged() {
    final v = _searchController.text;
    if (_debounce?.isActive ?? false) _debounce!.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      final trimmed = v.trim();
      if (trimmed != _search) setState(() => _search = trimmed);
    });
  }

  AuditLogFilter get _filter => AuditLogFilter(
        search: _search.isEmpty ? null : _search,
        event: _event,
      );

  @override
  Widget build(BuildContext context) {
    // Debounced search/filter → recomputes the parametrized provider.
    final filter = _filter;
    final page = ref.watch(auditLogsProvider(filter));
    final stats = ref.watch(_auditStatsProvider);
    final types = ref.watch(_auditEventTypesProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Audit Logs'),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 4),
              child: Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      label: 'Total',
                      icon: HeroPaths.clipboard,
                      value: stats.maybeWhen(
                        data: (s) => '${s.total}',
                        orElse: () => '—',
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatTile(
                      label: 'Today',
                      icon: HeroPaths.clock,
                      value: stats.maybeWhen(
                        data: (s) => '${s.today}',
                        orElse: () => '—',
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(
                  horizontal: AppSpacing.page, vertical: 8),
              child: _SearchField(controller: _searchController),
            ),
            Padding(
              padding: const EdgeInsets.only(
                  left: AppSpacing.page, right: AppSpacing.page, bottom: 4),
              child: types.when(
                loading: () => const SizedBox(height: 44, child: Skeleton()),
                error: (_, __) => const SizedBox.shrink(),
                data: (list) => _EventFilter(
                  value: _event,
                  events: list,
                  onChanged: (v) => setState(() => _event = v),
                ),
              ),
            ),
            Expanded(child: _buildList(page)),
          ],
        ),
      ),
    );
  }

  Widget _buildList(AsyncValue<AuditPage> page) {
    return page.when(
      loading: () => ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 8, AppSpacing.page, 40),
        children: const [
          Skeleton(height: 64, radius: AppRadii.card),
          SizedBox(height: 10),
          Skeleton(height: 64, radius: AppRadii.card),
          SizedBox(height: 10),
          Skeleton(height: 64, radius: AppRadii.card),
          SizedBox(height: 10),
          Skeleton(height: 64, radius: AppRadii.card),
        ],
      ),
      error: (e, _) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: EmptyState(
            iconPath: HeroPaths.clipboard,
            message: 'Could not load logs',
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(auditLogsProvider(_filter)),
          ),
        ),
      ),
      data: (data) {
        if (data.logs.isEmpty) {
          return const Center(
            child: EmptyState(
                iconPath: HeroPaths.search, message: 'No matching logs'),
          );
        }
        return RefreshIndicator(
          onRefresh: () => ref.refresh(auditLogsProvider(_filter).future),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(
                AppSpacing.page, 8, AppSpacing.page, 40),
            itemCount: data.logs.length,
            itemBuilder: (_, i) {
              final log = data.logs[i];
              return FadeIn(
                delay: Duration(milliseconds: (i * 40).clamp(0, 280)),
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _LogRow(log: log),
                ),
              );
            },
          ),
        );
      },
    );
  }
}

/// Aggregate stats (total + today) for the header tiles.
final _auditStatsProvider = FutureProvider<AuditStats>((ref) async {
  return ref.watch(auditRepositoryProvider).stats();
});

/// The distinct event-type keys, for the filter dropdown.
final _auditEventTypesProvider = FutureProvider<List<String>>((ref) async {
  return ref.watch(auditRepositoryProvider).types();
});

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.icon,
    required this.value,
  });

  final String label;
  final String icon;
  final String value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(label.toUpperCase(),
                    style: AppType.label(context)),
              ),
              HeroIcon(icon, size: 18, color: p.textPrimary),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: AppType.mono(size: 22, color: p.textPrimary),
          ),
        ],
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.controller});
  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return TextField(
      controller: controller,
      textCapitalization: TextCapitalization.words,
      decoration: InputDecoration(
        isDense: true,
        hintText: 'Search user, email, or description…',
        hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
        prefixIcon:
            HeroIcon(HeroPaths.search, size: 18, color: p.textFaint),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
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

class _EventFilter extends StatelessWidget {
  const _EventFilter({
    required this.value,
    required this.events,
    required this.onChanged,
  });

  final String? value;
  final List<String> events;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return LabeledDropdown<String>(
      label: 'Event type',
      value: value,
      hint: 'All events',
      onChanged: onChanged,
      items: [
        const DropdownMenuItem<String>(
          value: null,
          child: Text('All events'),
        ),
        for (final e in events)
          DropdownMenuItem<String>(
            value: e,
            child: Text(e),
          ),
      ],
    );
  }
}

class _LogRow extends StatelessWidget {
  const _LogRow({required this.log});
  final AuditLog log;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final failed = !log.success;
    final iconColor = failed
        ? const Color(0xFFDC2626)
        : _isCreate(log.event)
            ? const Color(0xFF16A34A)
            : _isDelete(log.event)
                ? const Color(0xFFDC2626)
                : p.textPrimary;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: p.canvas,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: p.border),
            ),
            alignment: Alignment.center,
            child: HeroIcon(_iconFor(log.event),
                size: 18, color: iconColor),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        log.description ?? log.eventLabel,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: p.textPrimary,
                        ),
                      ),
                    ),
                    if (failed)
                      Container(
                        margin: const EdgeInsets.only(left: 6),
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFDC2626),
                          borderRadius: BorderRadius.circular(AppRadii.pill),
                        ),
                        child: const Text(
                          'FAILED',
                          style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 8,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1,
                            color: Colors.white,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _MetaChip(label: log.eventLabel),
                    if (log.userName != null)
                      Text(
                        log.userName!,
                        style:
                            TextStyle(fontSize: 11, color: p.textMuted),
                      ),
                    if (log.ipAddress != null)
                      Text(
                        log.ipAddress!,
                        style:
                            TextStyle(fontSize: 11, color: p.textFaint),
                      ),
                  ],
                ),
                if (log.timestamp != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      DateFormat('MMM d, y · HH:mm:ss')
                          .format(log.timestamp!.toLocal()),
                      style: TextStyle(fontSize: 11, color: p.textFaint),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _iconFor(String event) {
    if (event.contains('login') || event.contains('logout')) return HeroPaths.lockClosed;
    if (_isCreate(event)) return HeroPaths.plus;
    if (_isDelete(event)) return HeroPaths.trash;
    if (event.contains('backup')) return HeroPaths.archive;
    if (event.contains('restore')) return HeroPaths.arrowPath;
    return HeroPaths.document;
  }

  static bool _isCreate(String e) =>
      e.contains('create') || e.contains('register');
  static bool _isDelete(String e) => e.contains('delete');
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: p.canvas,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        label.toLowerCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
          color: p.textMuted,
        ),
      ),
    );
  }
}
