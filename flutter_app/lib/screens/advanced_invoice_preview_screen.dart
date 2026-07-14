import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:share_plus/share_plus.dart';

import '../models/advanced_invoice.dart';
import '../repositories/advanced_invoices_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_bottom_nav.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Read-only advanced-invoice document. Mirrors the section order of
/// mobile/views/advanced-invoice-view.php: company block, contract period,
/// bill-to, invoice details, activity (line items), Balance Due bar, payment
/// details, notes, footer. View-only (editing bounces to the desktop form on
/// web), so no edit action.
class AdvancedInvoicePreviewScreen extends ConsumerWidget {
  const AdvancedInvoicePreviewScreen({super.key, required this.invoiceId});

  final String invoiceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(advancedInvoicesProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Advanced Invoice'),
      ),
      bottomNavigationBar: const AppNavBar(),
      body: async.when(
        loading: () => const _Skeleton(),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.document,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(advancedInvoicesProvider),
            ),
          ),
        ),
        data: (list) {
          AdvancedInvoice? invoice;
          for (final i in list) {
            if (i.id == invoiceId) {
              invoice = i;
              break;
            }
          }
          if (invoice == null) {
            return const Center(
              child: EmptyState(
                iconPath: HeroPaths.document,
                message: 'Invoice not found',
              ),
            );
          }
          return _Document(invoice: invoice);
        },
      ),
    );
  }
}

String _money(double v, String currency) {
  try {
    return NumberFormat.simpleCurrency(name: currency).format(v);
  } catch (_) {
    return NumberFormat.simpleCurrency().format(v);
  }
}

class _Document extends StatelessWidget {
  const _Document({required this.invoice});
  final AdvancedInvoice invoice;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final c = invoice.company;
    final cust = invoice.customer;
    final pay = invoice.payment;
    final companyName =
        c.companyName.isEmpty ? 'Company' : c.companyName;
    final dateStr = invoice.invoiceDate != null
        ? DateFormat('MMM d, y').format(invoice.invoiceDate!.toLocal())
        : null;

    return ListView(
      padding:
          const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 24),
      children: [
        FadeIn(
          child: SectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Company header + status ──────────────────────────────
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(companyName.toUpperCase(),
                              style: TextStyle(
                                fontFamily: 'Geist',
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                                letterSpacing: -0.2,
                                color: p.textPrimary,
                              )),
                          if (c.companyAddress.isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Text(c.companyAddress, style: _meta(p)),
                          ],
                          if (c.companyEmail.isNotEmpty)
                            Text(c.companyEmail, style: _meta(p)),
                          if (c.companyPhone.isNotEmpty)
                            Text(c.companyPhone, style: _meta(p)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    _StatusChip(status: invoice.status),
                  ],
                ),
                const SizedBox(height: 20),
                _line(p),
                const SizedBox(height: 16),

                // ── Invoice details ──────────────────────────────────────
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: _labelValue(context, 'Invoice',
                          invoice.invoiceNumber.isEmpty
                              ? '—'
                              : invoice.invoiceNumber.toUpperCase()),
                    ),
                    if (dateStr != null)
                      Expanded(
                          child: _labelValue(context, 'Date', dateStr)),
                  ],
                ),

                // ── Contract period ──────────────────────────────────────
                if (cust.hasContract) ...[
                  const SizedBox(height: 14),
                  _labelValue(
                    context,
                    'Contract Period',
                    '${cust.contractFrom.isEmpty ? '—' : cust.contractFrom}'
                        ' → ${cust.contractTo.isEmpty ? '—' : cust.contractTo}',
                  ),
                ],
                const SizedBox(height: 16),

                // ── Bill to ──────────────────────────────────────────────
                Text('BILL TO',
                    style: AppType.label(context, color: p.textFaint)),
                const SizedBox(height: 6),
                Text(cust.name.isEmpty ? 'No customer' : cust.name,
                    style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: p.textPrimary)),
                if (cust.propertyAddress.isNotEmpty)
                  Text(cust.propertyAddress, style: _meta(p)),
                if (cust.email.isNotEmpty) Text(cust.email, style: _meta(p)),
                if (cust.phone.isNotEmpty) Text(cust.phone, style: _meta(p)),
                const SizedBox(height: 20),
                _line(p),
                const SizedBox(height: 12),

                // ── Activity (line items) ────────────────────────────────
                Row(
                  children: [
                    Expanded(
                      child: Text('ACTIVITY',
                          style:
                              AppType.label(context, color: p.textFaint)),
                    ),
                    Text('AMOUNT',
                        style: AppType.label(context, color: p.textFaint)),
                  ],
                ),
                const SizedBox(height: 8),
                if (invoice.lineItems.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    child: Text('No line items', style: _meta(p)),
                  )
                else
                  for (final item in invoice.lineItems)
                    _ItemRow(item: item, currency: invoice.currency, p: p),
                const SizedBox(height: 12),

                // ── Balance Due ──────────────────────────────────────────
                Container(
                  padding:
                      const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
                  decoration: BoxDecoration(
                    color: p.ink,
                    borderRadius: BorderRadius.circular(AppRadii.button),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('BALANCE DUE',
                          style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.2,
                            color: p.onInk,
                          )),
                      Text(_money(invoice.totalDue, invoice.currency),
                          style: AppType.mono(size: 20, color: p.onInk)),
                    ],
                  ),
                ),

                // ── Payment details ──────────────────────────────────────
                if (pay.hasAny) ...[
                  const SizedBox(height: 20),
                  _line(p),
                  const SizedBox(height: 12),
                  Text('PAYMENT DETAILS',
                      style: AppType.label(context, color: p.textFaint)),
                  const SizedBox(height: 8),
                  if (pay.bankName.isNotEmpty)
                    _kv(context, 'Bank', pay.bankName),
                  if (pay.accountNumber.isNotEmpty)
                    _kv(context, 'Account', pay.accountNumber),
                  if (pay.branchCode.isNotEmpty)
                    _kv(context, 'Branch', pay.branchCode),
                  if (pay.paymentReference.isNotEmpty)
                    _kv(context, 'Reference', pay.paymentReference),
                  if (pay.dueDate.isNotEmpty)
                    _kv(context, 'Due', pay.dueDate),
                ],

                // ── Custom fields ────────────────────────────────────────
                if (invoice.customFields.any((f) => f.showOnPrint)) ...[
                  const SizedBox(height: 20),
                  _line(p),
                  const SizedBox(height: 12),
                  Text('ADDITIONAL',
                      style: AppType.label(context, color: p.textFaint)),
                  const SizedBox(height: 8),
                  for (final f in invoice.customFields.where((f) => f.showOnPrint))
                    _kv(context, f.label.isEmpty ? 'Field' : f.label, f.value),
                ],

                // ── Notes / footer ───────────────────────────────────────
                if (invoice.notes.trim().isNotEmpty) ...[
                  const SizedBox(height: 20),
                  _line(p),
                  const SizedBox(height: 12),
                  Text('NOTES',
                      style: AppType.label(context, color: p.textFaint)),
                  const SizedBox(height: 6),
                  Text(invoice.notes.trim(),
                      style: TextStyle(
                          fontSize: 13, height: 1.5, color: p.textMuted)),
                ],
                if (invoice.footerText.trim().isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Center(
                    child: Text(invoice.footerText.trim(),
                        textAlign: TextAlign.center,
                        style: TextStyle(
                            fontSize: 12,
                            fontStyle: FontStyle.italic,
                            color: p.textFaint)),
                  ),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: () => _share(),
          icon: HeroIcon(HeroPaths.paperAirplane, size: 18, color: p.textPrimary),
          label: const Text('Share'),
        ),
      ],
    );
  }

  void _share() {
    final b = StringBuffer();
    b.writeln('Invoice ${invoice.invoiceNumber}');
    if (invoice.customer.name.isNotEmpty) {
      b.writeln('For: ${invoice.customer.name}');
    }
    b.writeln('Status: ${AdvancedInvoice.statusLabel(invoice.status)}');
    b.writeln('Balance due: ${_money(invoice.totalDue, invoice.currency)}');
    SharePlus.instance.share(ShareParams(
        text: b.toString(), subject: 'Invoice ${invoice.invoiceNumber}'));
  }

  static TextStyle _meta(AppPalette p) =>
      TextStyle(fontSize: 11, height: 1.5, color: p.textFaint);

  Widget _line(AppPalette p) => Container(height: 1, color: p.border);

  Widget _labelValue(BuildContext context, String label, String value) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textFaint)),
        const SizedBox(height: 3),
        Text(value,
            style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: p.textPrimary)),
      ],
    );
  }

  Widget _kv(BuildContext context, String k, String v) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 96,
            child: Text(k.toUpperCase(),
                style: AppType.label(context, color: p.textFaint)),
          ),
          Expanded(
            child: Text(v,
                style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: p.textPrimary)),
          ),
        ],
      ),
    );
  }
}

class _ItemRow extends StatelessWidget {
  const _ItemRow({required this.item, required this.currency, required this.p});
  final AdvLineItem item;
  final String currency;
  final AppPalette p;

  @override
  Widget build(BuildContext context) {
    final sub = [item.date, item.reference]
        .where((s) => s.trim().isNotEmpty)
        .join(' · ');
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item.description.isEmpty ? 'Item' : item.description,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: p.textPrimary)),
                if (sub.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(sub,
                      style: TextStyle(fontSize: 11, color: p.textFaint)),
                ],
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(_money(item.amount, currency),
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: p.textPrimary)),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final lower = status.toLowerCase();
    final Color accent;
    switch (lower) {
      case 'paid':
        accent = AdvancedInvoice.paidColor;
      case 'overdue':
        accent = AdvancedInvoice.overdueColor;
      default:
        accent = Colors.transparent;
    }
    final filled = accent != Colors.transparent;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: filled ? accent : Colors.transparent,
        border: Border.all(color: filled ? accent : p.border),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        AdvancedInvoice.statusLabel(status).toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 1,
          color: filled ? Colors.white : p.textMuted,
        ),
      ),
    );
  }
}

class _Skeleton extends StatelessWidget {
  const _Skeleton();
  @override
  Widget build(BuildContext context) => ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 16, AppSpacing.page, 24),
        children: const [
          Skeleton(height: 360, radius: AppRadii.section),
          SizedBox(height: 16),
          Skeleton(height: 48, radius: AppRadii.button),
        ],
      );
}
