import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../auth/auth_controller.dart';
import '../models/admin_payment.dart';
import '../repositories/admin_payments_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/skeleton.dart';

/// Admin payment approvals queue. Mirrors mobile/views/admin-payments.php:
/// status filter pills (Pending / Approved / Rejected / All), payment cards
/// showing plan name, user email, status badge, amount+currency, method,
/// submitted date, proof thumbnail, and any rejection reason. Pending
/// submissions expose Approve / Reject (reject requires a reason).
///
/// Admin screens are NOT primary tabs → plain Scaffold + AppBar (back arrow).
class AdminPaymentsScreen extends ConsumerStatefulWidget {
  const AdminPaymentsScreen({super.key});

  @override
  ConsumerState<AdminPaymentsScreen> createState() =>
      _AdminPaymentsScreenState();
}

class _AdminPaymentsScreenState extends ConsumerState<AdminPaymentsScreen> {
  String _status = 'pending'; // pending | approved | rejected | all

  static const _filters = [
    ('pending', 'Pending'),
    ('approved', 'Approved'),
    ('rejected', 'Rejected'),
    ('all', 'All'),
  ];

  @override
  Widget build(BuildContext context) {
    final payments = ref.watch(adminPaymentsProvider(_status));

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Payment Approvals'),
      ),
      body: Column(
        children: [
          Padding(
            padding:
                const EdgeInsets.fromLTRB(AppSpacing.page, 12, AppSpacing.page, 4),
            child: _StatusPills(
              value: _status,
              filters: _filters,
              onSelected: (v) => setState(() => _status = v),
            ),
          ),
          Expanded(
            child: payments.when(
              loading: () => ListView(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.page, 12, AppSpacing.page, 32),
                children: const [
                  _PaymentSkeleton(),
                  SizedBox(height: 12),
                  _PaymentSkeleton(),
                  SizedBox(height: 12),
                  _PaymentSkeleton(),
                ],
              ),
              error: (e, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: EmptyState(
                    iconPath: HeroPaths.banknotes,
                    message: e.toString(),
                    actionLabel: 'Retry',
                    onAction: () =>
                        ref.invalidate(adminPaymentsProvider(_status)),
                  ),
                ),
              ),
              data: (list) {
                if (list.isEmpty) {
                  return Center(
                    child: EmptyState(
                      iconPath: HeroPaths.banknotes,
                      message: _status == 'pending'
                          ? 'No payments awaiting approval'
                          : 'No ${_status == 'all' ? '' : _status} payments',
                    ),
                  );
                }
                return RefreshIndicator(
                  onRefresh: () =>
                      ref.refresh(adminPaymentsProvider(_status).future),
                  child: ListView.builder(
                    padding: const EdgeInsets.fromLTRB(
                        AppSpacing.page, 12, AppSpacing.page, 32),
                    itemCount: list.length,
                    itemBuilder: (_, i) {
                      final payment = list[i];
                      return FadeIn(
                        delay:
                            Duration(milliseconds: (i * 50).clamp(0, 300)),
                        child: Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _PaymentCard(payment: payment),
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
}

class _StatusPills extends StatelessWidget {
  const _StatusPills({
    required this.value,
    required this.filters,
    required this.onSelected,
  });

  final String value;
  final List<(String, String)> filters;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SizedBox(
      height: 40,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: filters.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (_, i) {
          final (key, label) = filters[i];
          final isSelected = key == value;
          final fill = isSelected ? p.ink : Colors.transparent;
          final fg = isSelected ? p.onInk : p.textPrimary;
          return GestureDetector(
            onTap: () => onSelected(key),
            behavior: HitTestBehavior.opaque,
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 120),
              padding:
                  const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
              decoration: BoxDecoration(
                color: fill,
                border: Border.all(color: isSelected ? p.ink : p.border),
                borderRadius: BorderRadius.circular(AppRadii.pill),
              ),
              alignment: Alignment.center,
              child: Text(
                label.toUpperCase(),
                style: TextStyle(
                  fontFamily: 'Geist',
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.5,
                  color: fg,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _PaymentSkeleton extends StatelessWidget {
  const _PaymentSkeleton();

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
          Skeleton(height: 12, width: 180),
          SizedBox(height: 12),
          Skeleton(height: 12, width: 100),
        ],
      ),
    );
  }
}

class _PaymentCard extends ConsumerWidget {
  const _PaymentCard({required this.payment});
  final AdminPayment payment;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final amount = NumberFormat.simpleCurrency(name: payment.currency)
        .format(payment.amount);
    final date = payment.submittedAt != null
        ? DateFormat.yMMMd().format(payment.submittedAt!.toLocal())
        : '—';
    final planName = payment.planSnapshot?.name ?? 'Unknown plan';
    final methodLabel = payment.methodSnapshot?.label;

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
              HeroIcon(HeroPaths.banknotes, size: 18, color: p.textMuted),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  planName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: p.textPrimary,
                  ),
                ),
              ),
              _StatusBadge(status: payment.status),
            ],
          ),
          const SizedBox(height: 10),
          if (payment.userEmail.isNotEmpty)
            _MetaRow(
              icon: HeroPaths.users,
              label: 'User',
              value: payment.userEmail,
            ),
          _MetaRow(
            icon: HeroPaths.banknotes,
            label: 'Amount',
            value: amount,
            strong: true,
          ),
          if (methodLabel != null && methodLabel.isNotEmpty)
            _MetaRow(
              icon: HeroPaths.receipt,
              label: 'Method',
              value: methodLabel,
            ),
          _MetaRow(
            icon: HeroPaths.calendar,
            label: 'Submitted',
            value: date,
          ),
          if (payment.hasProof) ...[
            const SizedBox(height: 12),
            _ProofAttachment(paymentId: payment.id, proof: payment.proof!),
          ],
          if (payment.isRejected && payment.rejectionReason != null) ...[
            const SizedBox(height: 12),
            _RejectionReason(reason: payment.rejectionReason!),
          ],
          if (payment.isPending) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: _ActionButton(
                    icon: HeroPaths.check,
                    label: 'Approve',
                    primary: true,
                    onTap: () => _approve(ref),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: _ActionButton(
                    icon: HeroPaths.xMark,
                    label: 'Reject',
                    destructive: true,
                    onTap: () => _reject(ref),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _approve(WidgetRef ref) async {
    final ok = await confirmDialog(ref.context,
        title: 'Approve payment',
        message: 'Approve this ${payment.currency} ${payment.amount.toStringAsFixed(2)} '
            'payment for "${payment.planSnapshot?.name ?? 'this plan'}"?',
        confirmLabel: 'Approve',
        destructive: false);
    if (!ok) return;
    try {
      await ref.read(adminPaymentsRepositoryProvider).approve(payment.id);
      ref.invalidate(adminPaymentsProvider);
    } catch (e) {
      _toast(ref, '$e');
    }
  }

  Future<void> _reject(WidgetRef ref) async {
    final reason = await _promptReason(ref.context);
    if (reason == null) return;
    if (reason.trim().isEmpty) {
      _toast(ref, 'A rejection reason is required');
      return;
    }
    try {
      await ref
          .read(adminPaymentsRepositoryProvider)
          .reject(payment.id, reason.trim());
      ref.invalidate(adminPaymentsProvider);
    } catch (e) {
      _toast(ref, '$e');
    }
  }

  void _toast(WidgetRef ref, String message) {
    if (ref.context.mounted) {
      ScaffoldMessenger.of(ref.context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }

  /// Prompt the admin for a rejection reason (required so the user knows why).
  Future<String?> _promptReason(BuildContext context) async {
    final controller = TextEditingController();
    return showDialog<String>(
      context: context,
      builder: (ctx) {
        final p = AppPalette.of(ctx);
        return AlertDialog(
          backgroundColor: p.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.card),
            side: BorderSide(color: p.border),
          ),
          title: Text('Reject payment',
              style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: p.textPrimary)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('The user will see this reason.',
                  style: TextStyle(fontSize: 13, color: p.textMuted)),
              const SizedBox(height: 12),
              TextField(
                controller: controller,
                autofocus: true,
                maxLines: 3,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(
                  hintText: 'e.g. Payment amount does not match the plan price.',
                  hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
                  contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12, vertical: 12),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.border),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(AppRadii.button),
                    borderSide: BorderSide(color: p.ink, width: 1.5),
                  ),
                ),
              ),
            ],
          ),
          actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text('CANCEL',
                  style: TextStyle(
                      color: p.textMuted,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 1)),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, controller.text),
              child: const Text(
                'REJECT',
                style: TextStyle(
                    color: Color(0xFFDC2626),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final (color, label) = switch (status) {
      'approved' => (const Color(0xFF16A34A), 'Approved'),
      'rejected' => (const Color(0xFFDC2626), 'Rejected'),
      _ => (const Color(0xFFEAB308), 'Pending'),
    };
    final fg = status == 'pending' ? Colors.black : Colors.white;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.6,
          color: fg,
        ),
      ),
    );
  }
}

class _MetaRow extends StatelessWidget {
  const _MetaRow({
    required this.icon,
    required this.label,
    required this.value,
    this.strong = false,
  });

  final String icon;
  final String label;
  final String value;
  final bool strong;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          HeroIcon(icon, size: 14, color: p.textFaint),
          const SizedBox(width: 8),
          Text(label.toUpperCase(),
              style: AppType.label(context, color: p.textFaint, size: 9)),
          const Spacer(),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 13,
                fontWeight: strong ? FontWeight.w700 : FontWeight.w500,
                color: strong ? p.textPrimary : p.textMuted,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Proof attachment thumbnail. Images are fetched through the token-attached
/// ApiClient (NOT Image.network — that wouldn't carry the Authorization header)
/// and rendered from bytes. PDFs show a document icon instead.
class _ProofAttachment extends ConsumerStatefulWidget {
  const _ProofAttachment({required this.paymentId, required this.proof});
  final String paymentId;
  final Proof proof;

  @override
  ConsumerState<_ProofAttachment> createState() => _ProofAttachmentState();
}

class _ProofAttachmentState extends ConsumerState<_ProofAttachment> {
  Uint8List? _bytes;
  bool _loading = false;
  Object? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(apiClientProvider).getBytes(
            '/api/payment-proof.php',
            query: {'id': widget.paymentId},
          );
      if (mounted) {
        setState(() {
          _bytes = Uint8List.fromList(data);
          _loading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e;
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    // PDFs (and other non-images) show a doc icon rather than decoding bytes.
    if (!widget.proof.isImage) {
      return _ProofTile(
        icon: HeroPaths.document,
        label: widget.proof.filename.isEmpty
            ? 'Proof document'
            : widget.proof.filename,
        onTap: () => _showFull(context),
      );
    }

    if (_error != null) {
      return _ProofTile(
        icon: HeroPaths.xMark,
        label: 'Failed to load proof',
        onTap: _load,
        retry: true,
      );
    }
    if (_bytes == null) {
      if (!_loading) _load();
      return const SizedBox(
        width: 88,
        height: 88,
        child: Center(child: Skeleton(width: 88, height: 88, radius: AppRadii.button)),
      );
    }
    return GestureDetector(
      onTap: () => _showFull(context),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadii.button),
        child: Image.memory(
          _bytes!,
          width: 88,
          height: 88,
          fit: BoxFit.cover,
          gaplessPlayback: true,
          errorBuilder: (_, __, ___) => Container(
            width: 88,
            height: 88,
            color: p.canvas,
            alignment: Alignment.center,
            child: HeroIcon(HeroPaths.document, size: 24, color: p.textFaint),
          ),
        ),
      ),
    );
  }

  void _showFull(BuildContext context) {
    if (!widget.proof.isImage) {
      // Non-image proof: the device can't render a PDF inline here; surface a
      // hint instead (a future release could hand off to a system viewer).
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Proof is a non-image attachment.')),
      );
      return;
    }
    if (_bytes == null) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _ProofFullScreen(bytes: _bytes!),
      ),
    );
  }
}

class _ProofTile extends StatelessWidget {
  const _ProofTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.retry = false,
  });

  final String icon;
  final String label;
  final VoidCallback onTap;
  final bool retry;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Container(
        width: 88,
        height: 88,
        decoration: BoxDecoration(
          color: p.canvas,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            HeroIcon(icon, size: 24, color: p.textFaint),
            const SizedBox(height: 6),
            Text(
              retry ? 'TAP TO RETRY' : 'PROOF',
              style: AppType.label(context, color: p.textFaint, size: 8),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProofFullScreen extends StatelessWidget {
  const _ProofFullScreen({required this.bytes});
  final Uint8List bytes;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Scaffold(
      backgroundColor: p.canvas,
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.xMark, size: 22),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Proof'),
      ),
      body: Center(
        child: InteractiveViewer(
          child: Image.memory(
            bytes,
            gaplessPlayback: true,
            errorBuilder: (_, __, ___) =>
                HeroIcon(HeroPaths.document, size: 48, color: p.textFaint),
          ),
        ),
      ),
    );
  }
}

class _RejectionReason extends StatelessWidget {
  const _RejectionReason({required this.reason});
  final String reason;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2), // red-50
        border: Border.all(color: const Color(0xFFFCA5A5)), // red-300
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const HeroIcon(HeroPaths.xMark, size: 14, color: Color(0xFFDC2626)),
              const SizedBox(width: 6),
              Text('REJECTED',
                  style: AppType.label(
                    context,
                    color: const Color(0xFFDC2626),
                    size: 10,
                  )),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            reason,
            style: TextStyle(fontSize: 13, color: p.textPrimary, height: 1.4),
          ),
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
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
