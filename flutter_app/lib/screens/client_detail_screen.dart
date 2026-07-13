import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/client.dart';
import '../repositories/clients_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Client detail screen. Mirrors mobile/views/view-client.php and takes
/// habit_detail_screen.dart as its structural template.
class ClientDetailScreen extends ConsumerStatefulWidget {
  const ClientDetailScreen({super.key, required this.clientId});
  final String clientId;

  @override
  ConsumerState<ClientDetailScreen> createState() => _ClientDetailScreenState();
}

class _ClientDetailScreenState extends ConsumerState<ClientDetailScreen> {
  late final FutureProvider<Client?> _provider;

  @override
  void initState() {
    super.initState();
    _provider = FutureProvider<Client?>(
        (ref) => ref.watch(clientsRepositoryProvider).fetch(widget.clientId));
  }

  @override
  Widget build(BuildContext context) {
    final client = ref.watch(_provider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Client'),
        actions: [
          IconButton(
            icon: const HeroIcon(HeroPaths.pencil, size: 20),
            onPressed: () async {
              await context.push('/clients/${widget.clientId}/edit');
              if (mounted) ref.invalidate(_provider);
            },
          ),
        ],
      ),
      body: client.when(
        loading: () => ListView(
          padding: const EdgeInsets.all(AppSpacing.page),
          children: const [
            Skeleton(height: 90, radius: AppRadii.card),
            SizedBox(height: 16),
            Skeleton(height: 200, radius: AppRadii.section),
          ],
        ),
        error: (e, _) => Center(
          child: EmptyState(
            iconPath: HeroPaths.users,
            message: e.toString(),
            actionLabel: 'Retry',
            onAction: () => ref.invalidate(_provider),
          ),
        ),
        data: (c) {
          if (c == null) {
            return const Center(
                child: EmptyState(
                    iconPath: HeroPaths.users, message: 'Client not found'));
          }
          return _Body(client: c, onChanged: () => ref.invalidate(_provider));
        },
      ),
    );
  }
}

class _Body extends ConsumerWidget {
  const _Body({required this.client, required this.onChanged});
  final Client client;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);

    return RefreshIndicator(
      onRefresh: () async => onChanged(),
      child: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 8, AppSpacing.page, 120),
        children: [
          FadeIn(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                _Avatar(initials: client.initials),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        client.name,
                        style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w700,
                            color: p.textPrimary),
                      ),
                      if (client.company.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(client.company.toUpperCase(),
                              style: AppType.label(
                                  context, color: p.textMuted, size: 11)),
                        ),
                      if (client.email.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(client.email,
                              style:
                                  TextStyle(fontSize: 13, color: p.textFaint)),
                        ),
                      if (client.createdAt != null)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            'Since ${DateFormat.yMMMd().format(client.createdAt!)}',
                            style: TextStyle(
                                color: p.textFaint, fontSize: 12),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 60),
            child: SectionCard(
              heading: 'Details',
              child: Column(
                children: [
                  if (client.email.isNotEmpty)
                    _MetaRow(label: 'Email', value: client.email),
                  if (client.phone.isNotEmpty)
                    _MetaRow(label: 'Phone', value: client.phone),
                  if (client.website.isNotEmpty)
                    _MetaRow(label: 'Website', value: client.website),
                  if (client.company.isNotEmpty)
                    _MetaRow(label: 'Company', value: client.company),
                  if (client.hasAddress)
                    _MetaRow(label: 'Address', value: client.address),
                  if (client.email.isEmpty &&
                      client.phone.isEmpty &&
                      client.website.isEmpty &&
                      client.company.isEmpty &&
                      !client.hasAddress)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Text('No contact details',
                          style:
                              TextStyle(fontSize: 14, color: p.textFaint)),
                    ),
                ],
              ),
            ),
          ),
          if (client.hasNotes) ...[
            const SizedBox(height: AppSpacing.gap),
            FadeIn(
              delay: const Duration(milliseconds: 120),
              child: SectionCard(
                heading: 'Notes',
                child: Text(
                  client.notes,
                  style: TextStyle(
                      fontSize: 14,
                      height: 1.5,
                      color: p.textPrimary),
                ),
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.gap),
          FadeIn(
            delay: const Duration(milliseconds: 180),
            child: Row(
              children: [
                Expanded(
                  child: _IconTile(
                    icon: HeroPaths.pencil,
                    label: 'Edit',
                    onTap: () async {
                      await context.push('/clients/${client.id}/edit');
                      if (context.mounted) onChanged();
                    },
                    primary: true,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _IconTile(
                    icon: HeroPaths.trash,
                    label: 'Delete',
                    destructive: true,
                    onTap: () => _delete(ref),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _delete(WidgetRef ref) async {
    final ok = await confirmDialog(ref.context,
        title: 'Delete client',
        message: 'Delete "${client.name}"? This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(clientsRepositoryProvider).delete(client.id);
      ref.invalidate(clientsProvider);
      if (ref.context.mounted) ref.context.pop();
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.initials});
  final String initials;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      width: 56,
      height: 56,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: p.ink,
        shape: BoxShape.circle,
      ),
      child: Text(
        initials,
        style: TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w800,
          color: p.onInk,
        ),
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 1),
            child: Text(label.toUpperCase(),
                style: AppType.label(context, color: p.textMuted)),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: p.textPrimary),
            ),
          ),
        ],
      ),
    );
  }
}

class _IconTile extends StatelessWidget {
  const _IconTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.destructive = false,
    this.primary = false,
  });
  final String icon;
  final String label;
  final VoidCallback onTap;
  final bool destructive;
  final bool primary;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final color = destructive
        ? const Color(0xFFDC2626)
        : (primary ? p.onInk : p.textPrimary);
    final fill = primary ? p.ink : p.surface;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: primary ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.card),
        ),
        child: Column(
          children: [
            HeroIcon(icon, size: 20, color: color),
            const SizedBox(height: 6),
            Text(label.toUpperCase(),
                style: AppType.label(context, color: color, size: 10),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}
