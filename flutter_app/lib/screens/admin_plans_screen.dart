import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../models/plan.dart';
import '../repositories/plans_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin subscription plans CRUD. Mirrors mobile/views/admin-plans.php: a list
/// of plans with FAB to create and Edit/Delete per plan.
///
/// Admin screens are NOT primary tabs → plain Scaffold + AppBar (back arrow).
class AdminPlansScreen extends ConsumerWidget {
  const AdminPlansScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final plans = ref.watch(plansProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Plans'),
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: p.ink,
        foregroundColor: p.onInk,
        shape: const CircleBorder(),
        elevation: 0,
        focusElevation: 0,
        onPressed: () async {
          await context.push('/admin/plans/new');
          if (context.mounted) ref.invalidate(plansProvider);
        },
        child: HeroIcon(HeroPaths.plus, size: 24, color: p.onInk),
      ),
      body: plans.when(
        loading: () => ListView(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.page, 12, AppSpacing.page, 120),
          children: const [
            _PlanSkeleton(),
            SizedBox(height: 12),
            _PlanSkeleton(),
            SizedBox(height: 12),
            _PlanSkeleton(),
          ],
        ),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.tag,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(plansProvider),
            ),
          ),
        ),
        data: (list) {
          if (list.isEmpty) {
            return Center(
              child: EmptyState(
                iconPath: HeroPaths.tag,
                message: 'No plans yet',
                actionLabel: 'New Plan',
                onAction: () async {
                  await context.push('/admin/plans/new');
                  if (context.mounted) ref.invalidate(plansProvider);
                },
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: () => ref.refresh(plansProvider.future),
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 12, AppSpacing.page, 120),
              itemCount: list.length,
              itemBuilder: (_, i) {
                final plan = list[i];
                return FadeIn(
                  delay: Duration(milliseconds: (i * 50).clamp(0, 300)),
                  child: Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _PlanCard(plan: plan),
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

class _PlanSkeleton extends StatelessWidget {
  const _PlanSkeleton();

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
          Skeleton(height: 16, width: 120),
          SizedBox(height: 12),
          Skeleton(height: 12, width: 80),
        ],
      ),
    );
  }
}

class _PlanCard extends ConsumerWidget {
  const _PlanCard({required this.plan});
  final Plan plan;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final price = NumberFormat.simpleCurrency(name: plan.currency)
        .format(plan.price);

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
              HeroIcon(HeroPaths.tag, size: 20, color: p.textPrimary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  plan.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary,
                  ),
                ),
              ),
              if (!plan.active)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: p.border,
                    borderRadius: BorderRadius.circular(AppRadii.pill),
                  ),
                  child: Text('INACTIVE',
                      style: AppType.label(context, color: p.textMuted, size: 9)),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 16,
            runSpacing: 8,
            children: [
              _Meta(label: 'Price', value: price),
              _Meta(
                label: 'Duration',
                value: '${plan.durationDays} days',
              ),
              _Meta(
                label: 'Requests',
                value: plan.isUnlimited ? '∞' : '${plan.monthlyRequestCap}/mo',
              ),
            ],
          ),
          if (plan.providers.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final provider in plan.providers)
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: p.canvas,
                      border: Border.all(color: p.border),
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                    ),
                    child: Text(
                      provider.toUpperCase(),
                      style: AppType.label(context, color: p.textMuted, size: 9),
                    ),
                  ),
              ],
            ),
          ],
          if (plan.hasDescription) ...[
            const SizedBox(height: 12),
            Text(
              plan.description,
              maxLines: 2,
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
                    await context.push('/admin/plans/${plan.id}/edit');
                    if (context.mounted) ref.invalidate(plansProvider);
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

  Future<void> _delete(WidgetRef ref) async {
    final ok = await confirmDialog(ref.context,
        title: 'Delete plan',
        message: 'Delete "${plan.name}"? Subscribers on this plan keep their '
            'active subscription; new sign-ups will stop.');
    if (!ok) return;
    try {
      await ref.read(plansRepositoryProvider).delete(plan.id);
      ref.invalidate(plansProvider);
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textFaint, size: 9)),
        const SizedBox(height: 2),
        Text(
          value,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: p.textPrimary,
          ),
        ),
      ],
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
