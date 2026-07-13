import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/shared_key_status.dart';
import '../repositories/admin_shared_keys_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/mono_button.dart';
import '../widgets/skeleton.dart';

/// Admin shared AI keys screen. Per-provider card shows masked status + model
/// counts; an "Import from your Settings" CTA and a manual entry form let the
/// admin set keys without raw material ever leaving the server.
/// Mirrors mobile/views/admin-shared-keys.php.
class AdminSharedKeysScreen extends ConsumerStatefulWidget {
  const AdminSharedKeysScreen({super.key});

  @override
  ConsumerState<AdminSharedKeysScreen> createState() =>
      _AdminSharedKeysScreenState();
}

class _AdminSharedKeysScreenState extends ConsumerState<AdminSharedKeysScreen> {
  bool _importing = false;

  Future<void> _import() async {
    setState(() => _importing = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(adminSharedKeysRepositoryProvider).import();
      ref.invalidate(adminSharedKeysProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Imported keys from your settings'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Import failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _importing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = ref.watch(adminSharedKeysProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Shared AI Keys'),
      ),
      body: SafeArea(
        child: status.when(
          loading: () => ListView(
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 40),
            children: const [
              Skeleton(height: 48, radius: AppRadii.button),
              SizedBox(height: AppSpacing.gap),
              Skeleton(height: 150, radius: AppRadii.card),
              SizedBox(height: 12),
              Skeleton(height: 150, radius: AppRadii.card),
              SizedBox(height: 12),
              Skeleton(height: 150, radius: AppRadii.card),
            ],
          ),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyState(
                iconPath: HeroPaths.sparkles,
                message: 'Could not load shared keys',
                actionLabel: 'Retry',
                onAction: () => ref.invalidate(adminSharedKeysProvider),
              ),
            ),
          ),
          data: (s) => RefreshIndicator(
            onRefresh: () => ref.refresh(adminSharedKeysProvider.future),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 16, AppSpacing.page, 40),
              children: [
                FadeIn(
                  child: _ImportCard(
                    onImport: _import,
                    importing: _importing,
                    updatedAt: s.updatedAt,
                  ),
                ),
                const SizedBox(height: AppSpacing.gap),
                for (var i = 0; i < s.providers.length; i++)
                  FadeIn(
                    delay: Duration(milliseconds: (i * 60).clamp(0, 240)),
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ProviderCard(
                        status: s,
                        provider: s.providers[i],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ImportCard extends StatelessWidget {
  const _ImportCard({
    required this.onImport,
    required this.importing,
    required this.updatedAt,
  });

  final VoidCallback onImport;
  final bool importing;
  final DateTime? updatedAt;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: p.canvas,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: p.border),
            ),
            alignment: Alignment.center,
            child:
                HeroIcon(HeroPaths.sparkles, size: 20, color: p.textPrimary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Import from your Settings',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  updatedAt == null
                      ? 'Copy your own API keys + models'
                      : 'Updated ${DateFormat('MMM d, y · HH:mm').format(updatedAt!.toLocal())}',
                  style: TextStyle(fontSize: 12, color: p.textFaint),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          SizedBox(
            width: 96,
            child: MonoButton(
              'Import',
              dense: true,
              onPressed: importing ? null : onImport,
            ),
          ),
        ],
      ),
    );
  }
}

class _ProviderCard extends ConsumerStatefulWidget {
  const _ProviderCard({required this.status, required this.provider});
  final SharedKeyStatus status;
  final ProviderKeyStatus provider;

  @override
  ConsumerState<_ProviderCard> createState() => _ProviderCardState();
}

class _ProviderCardState extends ConsumerState<_ProviderCard> {
  bool _editing = false;
  final TextEditingController _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final value = _controller.text.trim();
    if (value.isEmpty) {
      setState(() => _editing = false);
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(adminSharedKeysRepositoryProvider)
          .saveKeys({widget.provider.fieldKey: value});
      ref.invalidate(adminSharedKeysProvider);
      if (mounted) {
        setState(() {
          _editing = false;
          _controller.clear();
        });
      }
      messenger.showSnackBar(const SnackBar(
        content: Text('Key saved'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Save failed: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final provider = widget.provider;
    final models = widget.status.models[provider.slug];

    return Container(
      padding: const EdgeInsets.all(16),
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
                child: Text(
                  provider.providerName,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary,
                  ),
                ),
              ),
              _ConfiguredBadge(configured: provider.configured),
            ],
          ),
          const SizedBox(height: 10),
          Text('KEY',
              style: AppType.label(context, color: p.textMuted, size: 11)),
          const SizedBox(height: 4),
          if (_editing)
            LabeledField(
              label: 'New ${provider.providerName} key',
              controller: _controller,
              obscure: true,
              keyboardType: TextInputType.visiblePassword,
              hint: 'Paste API key',
              suffix: GestureDetector(
                onTap: _save,
                behavior: HitTestBehavior.opaque,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 6),
                  child: HeroIcon(HeroPaths.check,
                      size: 18, color: p.ink),
                ),
              ),
            )
          else
            Text(
              provider.configured
                  ? '••••${provider.lastFour ?? ''}'
                  : 'Not configured',
              style: TextStyle(
                fontFamily: 'GeistMono',
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: provider.configured ? p.textPrimary : p.textFaint,
              ),
            ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _MetaLine(
                  label: 'Models',
                  value: models == null
                      ? '—'
                      : '${models.enabled}/${models.total}',
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _MetaLine(
                  label: 'Default',
                  value: models?.defaultModel ?? '—',
                ),
              ),
            ],
          ),
          if (!_editing) ...[
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerRight,
              child: _GhostAction(
                label: provider.configured ? 'Replace' : 'Add key',
                onTap: () => setState(() => _editing = true),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ConfiguredBadge extends StatelessWidget {
  const _ConfiguredBadge({required this.configured});
  final bool configured;

  @override
  Widget build(BuildContext context) {
    final bg = configured
        ? const Color(0xFF16A34A)
        : const Color(0xFF6B7280);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        configured ? 'CONFIGURED' : 'MISSING',
        style: const TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 1,
          color: Colors.white,
        ),
      ),
    );
  }
}

class _MetaLine extends StatelessWidget {
  const _MetaLine({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 10)),
        const SizedBox(height: 2),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
              fontSize: 12, fontWeight: FontWeight.w600, color: p.textPrimary),
        ),
      ],
    );
  }
}

class _GhostAction extends StatelessWidget {
  const _GhostAction({required this.label, required this.onTap});
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
        child: Text(
          label.toUpperCase(),
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 11,
            fontWeight: FontWeight.w700,
            letterSpacing: 1,
            color: p.ink,
          ),
        ),
      ),
    );
  }
}
