import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/admin_payments_repository.dart';
import '../repositories/admin_users_repository.dart';
import '../repositories/payment_methods_repository.dart';
import '../repositories/plans_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin Hub — launcher grid with live counts (fetched in parallel), mirroring
/// mobile/views/admin-hub.php. Shows a "Needs Attention" banner when there are
/// pending payments awaiting approval.
///
/// Admin screens are NOT primary tabs → plain Scaffold + AppBar (back arrow).
class AdminHubScreen extends ConsumerWidget {
  const AdminHubScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final overview = ref.watch(_adminOverviewProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Admin'),
      ),
      body: overview.when(
        loading: () => ListView(
          padding:
              const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 32),
          children: const [
            _CardSkeleton(),
            SizedBox(height: AppSpacing.gap),
            _GridSkeleton(),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.adjustments,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(_adminOverviewProvider),
            ),
          ),
        ),
        data: (o) {
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(_adminOverviewProvider),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 32),
              children: [
                if (o.pendingPayments > 0)
                  FadeIn(child: _NeedsAttentionBanner(count: o.pendingPayments)),
                FadeIn(
                  delay: const Duration(milliseconds: 40),
                  child: _HubGrid(overview: o),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Reduced counts from the parallel admin GETs.
class _Overview {
  _Overview({
    required this.pendingPayments,
    required this.users,
    required this.activePlans,
    required this.activeMethods,
  });

  final int pendingPayments;
  final int users;
  final int activePlans;
  final int activeMethods;

  bool get needsAttention => pendingPayments > 0;
}

/// Fetch every admin list in parallel and reduce to counts. Each leg is
/// fault-tolerant: a failed endpoint degrades to 0 rather than failing the hub.
final _adminOverviewProvider = FutureProvider.autoDispose<_Overview>((ref) async {
  final usersRepo = ref.watch(adminUsersRepositoryProvider);
  final plansRepo = ref.watch(plansRepositoryProvider);
  final methodsRepo = ref.watch(paymentMethodsRepositoryProvider);
  final paymentsRepo = ref.watch(adminPaymentsRepositoryProvider);

  // Each leg is awaited individually and wrapped so one endpoint's failure
  // (e.g. a 403 on a not-yet-CSRF-bypassed route) degrades to 0, not an error.
  int users = 0, activePlans = 0, activeMethods = 0, pendingPayments = 0;
  await Future.wait([
    Future<void>(() async {
      users = (await usersRepo.list()).length;
    }).catchError((_) {}),
    Future<void>(() async {
      activePlans = (await plansRepo.findAll(activeOnly: true)).length;
    }).catchError((_) {}),
    Future<void>(() async {
      activeMethods = (await methodsRepo.findAll(activeOnly: true)).length;
    }).catchError((_) {}),
    Future<void>(() async {
      pendingPayments = (await paymentsRepo.list(status: 'pending')).length;
    }).catchError((_) {}),
  ]);

  return _Overview(
    pendingPayments: pendingPayments,
    users: users,
    activePlans: activePlans,
    activeMethods: activeMethods,
  );
});

class _NeedsAttentionBanner extends StatelessWidget {
  const _NeedsAttentionBanner({required this.count});
  final int count;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.gap),
      child: GestureDetector(
        onTap: () => context.push('/admin/payments'),
        behavior: HitTestBehavior.opaque,
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.cardPad),
          decoration: BoxDecoration(
            color: const Color(0xFFFEF2F2), // red-50
            border: Border.all(color: const Color(0xFFFCA5A5)), // red-300
            borderRadius: BorderRadius.circular(AppRadii.card),
          ),
          child: Row(
            children: [
              const HeroIcon(HeroPaths.bell, size: 22, color: Color(0xFFDC2626)),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('NEEDS ATTENTION',
                        style: AppType.label(
                          context,
                          color: const Color(0xFFDC2626),
                          size: 11,
                        )),
                    const SizedBox(height: 2),
                    Text(
                      '$count payment${count == 1 ? '' : 's'} awaiting approval',
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: p.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),
              const HeroIcon(HeroPaths.chevronRight, size: 18, color: Color(0xFFDC2626)),
            ],
          ),
        ),
      ),
    );
  }
}

class _HubGrid extends StatelessWidget {
  const _HubGrid({required this.overview});
  final _Overview overview;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.05,
      children: [
        _HubCard(
          label: 'Payment Approvals',
          icon: HeroPaths.banknotes,
          count: overview.pendingPayments,
          countLabel: 'pending',
          highlight: overview.pendingPayments > 0,
          onTap: () => context.push('/admin/payments'),
        ),
        _HubCard(
          label: 'Users',
          icon: HeroPaths.users,
          count: overview.users,
          countLabel: 'total',
          onTap: () => context.push('/admin/users'),
        ),
        _HubCard(
          label: 'Plans',
          icon: HeroPaths.tag,
          count: overview.activePlans,
          countLabel: 'active',
          onTap: () => context.push('/admin/plans'),
        ),
        _HubCard(
          label: 'Payment Methods',
          icon: HeroPaths.receipt,
          count: overview.activeMethods,
          countLabel: 'active',
          onTap: () => context.push('/admin/payment-methods'),
        ),
      ],
    );
  }
}

class _HubCard extends StatelessWidget {
  const _HubCard({
    required this.label,
    required this.icon,
    required this.count,
    required this.countLabel,
    required this.onTap,
    this.highlight = false,
  });

  final String label;
  final String icon;
  final int count;
  final String countLabel;
  final VoidCallback onTap;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final accent = highlight ? const Color(0xFFDC2626) : p.textPrimary;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.cardPad),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(
            color: highlight ? const Color(0xFFFCA5A5) : p.border,
          ),
          borderRadius: BorderRadius.circular(AppRadii.card),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                HeroIcon(icon, size: 22, color: accent),
                if (highlight)
                  Container(
                    width: 8,
                    height: 8,
                    decoration: const BoxDecoration(
                      color: Color(0xFFDC2626),
                      shape: BoxShape.circle,
                    ),
                  ),
              ],
            ),
            const Spacer(),
            Text(
              '$count',
              style: AppType.mono(size: 28, color: accent),
            ),
            const SizedBox(height: 2),
            Text(countLabel.toUpperCase(),
                style: AppType.label(context, color: p.textFaint, size: 9)),
            const SizedBox(height: 6),
            Text(
              label,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: p.textPrimary,
                height: 1.2,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CardSkeleton extends StatelessWidget {
  const _CardSkeleton();

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      height: 76,
      padding: const EdgeInsets.all(AppSpacing.cardPad),
      decoration: BoxDecoration(
        color: p.surface,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: const Row(
        children: [
          Skeleton(width: 22, height: 22, radius: 6),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Skeleton(height: 10, width: 110),
                SizedBox(height: 8),
                Skeleton(height: 12, width: 180),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GridSkeleton extends StatelessWidget {
  const _GridSkeleton();

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.05,
      children: List.filled(4, const _CardTileSkeleton()),
    );
  }
}

class _CardTileSkeleton extends StatelessWidget {
  const _CardTileSkeleton();

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
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Skeleton(width: 22, height: 22, radius: 6),
          Spacer(),
          Skeleton(height: 28, width: 50),
          SizedBox(height: 4),
          Skeleton(height: 10, width: 40),
        ],
      ),
    );
  }
}
