import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/payment_method.dart';
import '../repositories/payment_methods_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin payment methods CRUD. Mirrors mobile/views/admin-payment-methods.php:
/// a list of methods (bank / crypto) with a FAB to create and Edit/Delete each.
///
/// Admin screens are NOT primary tabs → plain Scaffold + AppBar (back arrow).
class AdminPaymentMethodsScreen extends ConsumerWidget {
  const AdminPaymentMethodsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final methods = ref.watch(paymentMethodsProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Payment Methods'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: p.ink,
        foregroundColor: p.onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/admin/payment-methods/new');
          if (context.mounted) ref.invalidate(paymentMethodsProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: p.onInk),
      ),
      body: methods.when(
        loading: () => ListView(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.page, 12, AppSpacing.page, 120),
          children: const [
            _MethodSkeleton(),
            SizedBox(height: 12),
            _MethodSkeleton(),
            SizedBox(height: 12),
            _MethodSkeleton(),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.receipt,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(paymentMethodsProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.receipt,
                message: 'No payment methods yet',
                actionLabel: 'New Method',
                onAction: () async {
                  await context.push('/admin/payment-methods/new');
                  if (context.mounted) ref.invalidate(paymentMethodsProvider);
                },
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () => ref.refresh(paymentMethodsProvider.future),
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 120),
              itemCount: list.length,
              itemBuilder: (_, i) {
                final m = list[i];
                return FadeIn(
                  delay: Duration(milliseconds: (i * 50).clamp(0, 300)),
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _MethodCard(method: m),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _MethodSkeleton extends StatelessWidget {
  const _MethodSkeleton();

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.section),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Skeleton(height: 16, width: 140),
          SizedBox(height: 12),
          Skeleton(height: 12, width: 100),
        ],
      ),
    );
  }
}

class _MethodCard extends ConsumerWidget {
  const _MethodCard({required this.method});
  final PaymentMethod method;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    return Container(
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
            children: [
              HeroIcon(
                method.isCrypto ? HeroPaths.commandLine : HeroPaths.banknotes,
                size: 20,
                color: p.textPrimary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  method.label.isEmpty ? method.type.toUpperCase() : method.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary,
                  ),
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: method.active ? p.ink : p.border,
                  borderRadius: BorderRadius.circular(AppRadii.pill),
                ),
                child: Text(
                  method.type.toUpperCase(),
                  style: AppType.label(
                    context,
                    color: method.active ? p.onInk : p.textMuted,
                    size: 9,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (method.isBank) ...[
            if (method.bankName.isNotEmpty) _Meta(label: 'Bank', value: method.bankName),
            if (method.accountName.isNotEmpty)
              _Meta(label: 'Account Name', value: method.accountName),
            if (method.accountNumber.isNotEmpty)
              _Meta(label: 'Account Number', value: method.accountNumber),
            if (method.branchCode.isNotEmpty)
              _Meta(label: 'Branch Code', value: method.branchCode),
            if (method.swift.isNotEmpty) _Meta(label: 'SWIFT / IBAN', value: method.swift),
          ] else ...[
            if (method.network.isNotEmpty) _Meta(label: 'Network', value: method.network),
            if (method.walletAddress.isNotEmpty)
              _Meta(
                label: 'Wallet Address',
                value: _shortenAddress(method.walletAddress),
              ),
          ],
          if (method.bankName.isEmpty &&
              method.accountName.isEmpty &&
              method.accountNumber.isEmpty &&
              method.network.isEmpty &&
              method.walletAddress.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Text('No details configured',
                  style: TextStyle(fontSize: 13, color: p.textFaint)),
            ),
          if (method.hasInstructions) ...[
            const SizedBox(height: 12),
            Text(
              method.instructions,
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(fontSize: 13, color: p.textMuted, height: 1.4),
            ),
          ],
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _ActionTile(
                  icon: HeroPaths.pencil,
                  label: 'Edit',
                  primary: true,
                  onTap: () async {
                    await context.push('/admin/payment-methods/${method.id}/edit');
                    if (context.mounted) ref.invalidate(paymentMethodsProvider);
                  },
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _ActionTile(
                  icon: HeroPaths.trash,
                  label: 'Delete',
                  destructive: true,
                  onTap: () => _delete(ref),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  /// Show the first and last few chars of a long wallet address (mask middle).
  static String _shortenAddress(String addr) {
    if (addr.length <= 16) return addr;
    return '${addr.substring(0, 8)}…${addr.substring(addr.length - 6)}';
  }

  Future<void> _delete(WidgetRef ref) async {
    final ok = await confirmDialog(ref.context,
        title: 'Delete payment method',
        message: 'Delete "${method.label.isEmpty ? method.type : method.label}"? '
            'This cannot be undone.');
    if (!ok) return;
    try {
      await ref.read(paymentMethodsRepositoryProvider).delete(method.id);
      ref.invalidate(paymentMethodsProvider);
    } catch (e) {
      if (ref.context.mounted) {
        ScaffoldMessenger.of(ref.context)
            .showSnackBar(SnackBar(content: Text('$e')));
      }
    }
  }
}

class _Meta extends StatelessWidget {
  const _Meta({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textFaint, size: 9)),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w500,
                color: p.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  const _ActionTile({
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
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: primary ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            HeroIcon(icon, size: 16, color: color),
            const SizedBox(width: 6),
            Text(label.toUpperCase(),
                style: AppType.label(context, color: color, size: 10)),
          ],
        ),
      ),
    );
  }
}
