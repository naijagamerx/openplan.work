import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/client.dart';
import '../repositories/clients_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Clients list screen. Mirrors mobile/views/clients.php.
///
/// Clients is NOT a primary tab, so this uses a plain Scaffold with an AppBar
/// (back arrow) rather than AppScaffold. The menu hub navigates here via
/// `context.push('/clients')`.
class ClientsScreen extends ConsumerStatefulWidget {
  const ClientsScreen({super.key});

  @override
  ConsumerState<ClientsScreen> createState() => _ClientsScreenState();
}

class _ClientsScreenState extends ConsumerState<ClientsScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final clients = ref.watch(clientsProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Clients'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: p.ink,
        foregroundColor: p.onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/clients/new');
          if (mounted) ref.invalidate(clientsProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: p.onInk),
      ),
      body: Column(
        children: [
          Padding(
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 8),
            child: _SearchField(
              onChanged: (v) => setState(() => _query = v),
            ),
          ),
          Expanded(
            child: clients.when(
              loading: () => ListView(
                padding:
                    const EdgeInsets.fromLTRB(AppSpacing.page, 4, AppSpacing.page, 120),
                children: const [
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                  SizedBox(height: 12),
                  _RowSkeleton(),
                ],
              ),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: EmptyState(
                    iconPath: HeroPaths.users,
                    message: e.toString(),
                    actionLabel: 'Retry',
                    onAction: () => ref.invalidate(clientsProvider),
                  ),
                ),
              ),
              data: (list) {
                if (list.isEmpty) {
                  return Center(
                    child: EmptyState(
                      iconPath: HeroPaths.users,
                      message: 'No clients yet',
                      actionLabel: 'New Client',
                      onAction: () => context.push('/clients/new'),
                    ),
                  );
                }
                final filtered = _filter(list, _query);
                if (filtered.isEmpty) {
                  return const Center(
                    child: EmptyState(
                        iconPath: HeroPaths.search, message: 'No clients match'),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () => ref.refresh(clientsProvider.future),
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(
                        AppSpacing.page, 4, AppSpacing.page, 120),
                    itemCount: filtered.length,
                    itemBuilder: (_, i) {
                      final c = filtered[i];
                      return FadeIn(
                        delay: Duration(milliseconds: (i * 50).clamp(0, 350)),
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _ClientRow(client: c),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  List<Client> _filter(List<Client> list, String q) {
    final query = q.toLowerCase().trim();
    if (query.isEmpty) return list;
    return list.where((c) {
      return c.name.toLowerCase().contains(query) ||
          c.email.toLowerCase().contains(query) ||
          c.company.toLowerCase().contains(query);
    }).toList();
  }
}

class _SearchField extends StatelessWidget {
  const _SearchField({required this.onChanged});
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return TextField(
      onChanged: onChanged,
      textCapitalization: TextCapitalization.words,
      decoration: InputDecoration(
        isDense: true,
        hintText: 'Search clients…',
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

class _RowSkeleton extends StatelessWidget {
  const _RowSkeleton();

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: const Row(
        children: [
          Skeleton(width: 48, height: 48, radius: 999),
          SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Skeleton(height: 14, width: 140),
                SizedBox(height: 8),
                Skeleton(height: 10, width: 100),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ClientRow extends ConsumerWidget {
  const _ClientRow({required this.client});
  final Client client;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    return InkWell(
      onTap: () async {
        await context.push('/clients/${client.id}');
        if (context.mounted) ref.invalidate(clientsProvider);
      },
      borderRadius: BorderRadius.circular(AppRadii.section),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.section),
        ),
        child: Row(
          children: [
            _Avatar(initials: client.initials),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    client.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: p.textPrimary,
                    ),
                  ),
                  if (client.email.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        client.email,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12, color: p.textFaint),
                      ),
                    ),
                  if (client.company.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        client.company.toUpperCase(),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: AppType.label(context, color: p.textMuted, size: 10),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            HeroIcon(HeroPaths.chevronRight, size: 18, color: p.textFaint),
          ],
        ),
      ),
    );
  }
}

/// A solid ink-filled circle showing the client's initials. Mirrors the PHP
/// app's ink avatar (mobile/views/clients.php).
class _Avatar extends StatelessWidget {
  const _Avatar({required this.initials});
  final String initials;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      width: 48,
      height: 48,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: p.ink,
        shape: BoxShape.circle,
      ),
      child: Text(
        initials,
        style: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w800,
          color: p.onInk,
        ),
      ),
    );
  }
}
