import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:share_plus/share_plus.dart';

import '../models/client.dart';
import '../models/invoice.dart';
import '../repositories/clients_repository.dart';
import '../repositories/config_repository.dart';
import '../repositories/invoices_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/app_bottom_nav.dart';
import '../widgets/empty_state.dart';
import '../widgets/fade_in.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Read-only invoice document — the "preview" the app was missing (tapping a
/// row used to drop straight into the edit form). Mirrors
/// mobile/views/invoice-view.php: business header + status, Billed-To client,
/// line items, subtotal/tax/amount-due, notes. Edit button routes to the form.
///
/// Composes three cached providers: the invoice (from the loaded list), the
/// client (clientId → name), and the business config (name/address/email/phone).
class InvoicePreviewScreen extends ConsumerWidget {
  const InvoicePreviewScreen({super.key, required this.invoiceId});

  final String invoiceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final invoicesAsync = ref.watch(invoicesProvider);
    final clientsAsync = ref.watch(clientsProvider);
    final configAsync = ref.watch(configProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Invoice'),
        actions: [
          IconButton(
            tooltip: 'Edit',
            icon: const HeroIcon(HeroPaths.pencil, size: 20),
            onPressed: () async {
              await context.push('/invoices/$invoiceId/edit');
              if (context.mounted) ref.invalidate(invoicesProvider);
            },
          ),
        ],
      ),
      bottomNavigationBar: const AppNavBar(),
      body: invoicesAsync.when(
        loading: () => const _PreviewSkeleton(),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: EmptyState(
              iconPath: HeroPaths.document,
              message: e.toString(),
              actionLabel: 'Retry',
              onAction: () => ref.invalidate(invoicesProvider),
            ),
          ),
        ),
        data: (invoices) {
          final invoice = _findById(invoices, invoiceId);
          if (invoice == null) {
            return const Center(
              child: EmptyState(
                iconPath: HeroPaths.document,
                message: 'Invoice not found',
              ),
            );
          }
          final client = _findClient(clientsAsync.asData?.value, invoice.clientId);
          final config = configAsync.asData?.value ?? const <String, dynamic>{};
          return _InvoiceDocument(
            invoice: invoice,
            client: client,
            config: config,
          );
        },
      ),
    );
  }

  static Invoice? _findById(List<Invoice> list, String id) {
    for (final i in list) {
      if (i.id == id) return i;
    }
    return null;
  }

  static Client? _findClient(List<Client>? clients, String clientId) {
    if (clients == null || clientId.isEmpty) return null;
    for (final c in clients) {
      if (c.id == clientId) return c;
    }
    return null;
  }
}

/// Formats an amount in the invoice's currency, falling back to the locale
/// default if the ISO code isn't recognised by intl.
String _money(double v, String currency) {
  try {
    return NumberFormat.simpleCurrency(name: currency).format(v);
  } catch (_) {
    return NumberFormat.simpleCurrency().format(v);
  }
}

/// Flattens a config address value (String or nested Map) into readable lines,
/// mirroring the PHP invoice-view flattening.
String _businessAddress(Map<String, dynamic> config) {
  final raw = config['businessAddress'];
  if (raw is String) return raw.trim();
  if (raw is Map) {
    const keys = ['street', 'city', 'state', 'zip', 'country'];
    final parts = <String>[];
    for (final k in keys) {
      final part = raw[k]?.toString().trim();
      if (part != null && part.isNotEmpty) parts.add(part);
    }
    return parts.join('\n');
  }
  return '';
}

String _cfg(Map<String, dynamic> config, String key) =>
    (config[key] ?? '').toString().trim();

class _InvoiceDocument extends StatelessWidget {
  const _InvoiceDocument({
    required this.invoice,
    required this.client,
    required this.config,
  });

  final Invoice invoice;
  final Client? client;
  final Map<String, dynamic> config;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final businessName = _cfg(config, 'businessName').isEmpty
        ? 'OpenPlan'
        : _cfg(config, 'businessName');
    final businessAddress = _businessAddress(config);
    final businessEmail = _cfg(config, 'businessEmail');
    final businessPhone = _cfg(config, 'businessPhone');
    final dateFmt = DateFormat('MMM d, y');

    return ListView(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.page, 16, AppSpacing.page, 24),
      children: [
        FadeIn(
          child: SectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Business header + status ──────────────────────────────
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            businessName.toUpperCase(),
                            style: TextStyle(
                              fontFamily: 'Geist',
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              letterSpacing: -0.2,
                              color: p.textPrimary,
                            ),
                          ),
                          if (businessAddress.isNotEmpty) ...[
                            const SizedBox(height: 6),
                            Text(businessAddress,
                                style: _meta(p)),
                          ],
                          if (businessEmail.isNotEmpty)
                            Text(businessEmail, style: _meta(p)),
                          if (businessPhone.isNotEmpty)
                            Text(businessPhone, style: _meta(p)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    _StatusChip(status: invoice.status),
                  ],
                ),
                const SizedBox(height: 20),
                _Divider(p),
                const SizedBox(height: 16),

                // ── Invoice number + dates ────────────────────────────────
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: _LabelValue(
                        label: 'Invoice',
                        value: invoice.invoiceNumber.toUpperCase(),
                      ),
                    ),
                    if (invoice.issueDate != null)
                      Expanded(
                        child: _LabelValue(
                          label: 'Issued',
                          value: dateFmt.format(invoice.issueDate!.toLocal()),
                        ),
                      ),
                    if (invoice.dueDate != null)
                      Expanded(
                        child: _LabelValue(
                          label: 'Due',
                          value: dateFmt.format(invoice.dueDate!.toLocal()),
                          valueColor: invoice.status == 'overdue'
                              ? Invoice.overdueColor
                              : null,
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 16),

                // ── Billed To ─────────────────────────────────────────────
                Text('BILLED TO', style: AppType.label(context, color: p.textFaint)),
                const SizedBox(height: 6),
                Text(
                  client?.name.isNotEmpty == true
                      ? client!.name
                      : (invoice.clientId.isEmpty ? 'No client' : invoice.clientId),
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: p.textPrimary,
                  ),
                ),
                if (client != null) ...[
                  if (client!.company.isNotEmpty) Text(client!.company, style: _meta(p)),
                  if (client!.email.isNotEmpty) Text(client!.email, style: _meta(p)),
                  if (client!.hasAddress) Text(client!.address, style: _meta(p)),
                ],
                const SizedBox(height: 20),
                _Divider(p),
                const SizedBox(height: 12),

                // ── Line items ────────────────────────────────────────────
                Row(
                  children: [
                    Expanded(
                      child: Text('DESCRIPTION',
                          style: AppType.label(context, color: p.textFaint)),
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
                    _LineItemRow(item: item, currency: invoice.currency, p: p),
                const SizedBox(height: 12),
                _Divider(p),
                const SizedBox(height: 12),

                // ── Totals ────────────────────────────────────────────────
                _TotalRow(
                  label: 'Subtotal',
                  value: _money(invoice.subtotal, invoice.currency),
                  p: p,
                ),
                const SizedBox(height: 6),
                _TotalRow(
                  label: 'Tax (${_pct(invoice.taxRate)}%)',
                  value: _money(invoice.taxAmount, invoice.currency),
                  p: p,
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
                  decoration: BoxDecoration(
                    color: p.ink,
                    borderRadius: BorderRadius.circular(AppRadii.button),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('AMOUNT DUE',
                          style: TextStyle(
                            fontFamily: 'Geist',
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.2,
                            color: p.onInk,
                          )),
                      Text(
                        _money(invoice.total, invoice.currency),
                        style: AppType.mono(size: 20, color: p.onInk),
                      ),
                    ],
                  ),
                ),

                // ── Notes ─────────────────────────────────────────────────
                if (invoice.notes.trim().isNotEmpty) ...[
                  const SizedBox(height: 20),
                  _Divider(p),
                  const SizedBox(height: 12),
                  Text('NOTES', style: AppType.label(context, color: p.textFaint)),
                  const SizedBox(height: 6),
                  Text(invoice.notes.trim(),
                      style: TextStyle(
                          fontSize: 13, height: 1.5, color: p.textMuted)),
                ],
              ],
            ),
          ),
        ),
        const SizedBox(height: 16),
        // ── Actions ──────────────────────────────────────────────────────
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _share(context),
                icon: HeroIcon(HeroPaths.paperAirplane, size: 18, color: p.textPrimary),
                label: const Text('Share'),
              ),
            ),
          ],
        ),
      ],
    );
  }

  void _share(BuildContext context) {
    final b = StringBuffer();
    b.writeln('Invoice ${invoice.invoiceNumber}');
    if (client?.name.isNotEmpty == true) b.writeln('Billed to: ${client!.name}');
    b.writeln('Status: ${Invoice.statusLabel(invoice.status)}');
    if (invoice.dueDate != null) {
      b.writeln('Due: ${DateFormat('MMM d, y').format(invoice.dueDate!.toLocal())}');
    }
    b.writeln('Amount due: ${_money(invoice.total, invoice.currency)}');
    SharePlus.instance.share(
        ShareParams(text: b.toString(), subject: 'Invoice ${invoice.invoiceNumber}'));
  }

  static TextStyle _meta(AppPalette p) => TextStyle(
        fontSize: 11,
        height: 1.5,
        color: p.textFaint,
      );

  static String _pct(double v) {
    // Trim trailing zeros: 15.00 → 15, 8.50 → 8.5
    final s = v.toStringAsFixed(2);
    return s.replaceFirst(RegExp(r'\.?0+$'), '');
  }
}

class _LineItemRow extends StatelessWidget {
  const _LineItemRow({required this.item, required this.currency, required this.p});
  final LineItem item;
  final String currency;
  final AppPalette p;

  @override
  Widget build(BuildContext context) {
    final qty = item.quantity;
    final qtyLabel = qty == qty.roundToDouble()
        ? qty.toInt().toString()
        : qty.toString();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.description.isEmpty ? 'Item' : item.description,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: p.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Qty: $qtyLabel × ${_money(item.unitPrice, currency)}',
                  style: TextStyle(fontSize: 11, color: p.textFaint),
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Text(
            _money(item.computedTotal, currency),
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w800,
              color: p.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({required this.label, required this.value, required this.p});
  final String label;
  final String value;
  final AppPalette p;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(fontSize: 13, color: p.textMuted)),
        Text(value,
            style: TextStyle(
                fontSize: 13, fontWeight: FontWeight.w700, color: p.textPrimary)),
      ],
    );
  }
}

class _LabelValue extends StatelessWidget {
  const _LabelValue({required this.label, required this.value, this.valueColor});
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textFaint)),
        const SizedBox(height: 3),
        Text(
          value,
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: valueColor ?? p.textPrimary,
          ),
        ),
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final accent = Invoice.statusColor(status);
    final filled = accent != Colors.transparent;
    final color = filled
        ? accent
        : (status.toLowerCase() == 'sent' ? p.ink : p.textFaint);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: filled ? accent : Colors.transparent,
        border: Border.all(color: filled ? accent : p.border),
        borderRadius: BorderRadius.circular(AppRadii.pill),
      ),
      child: Text(
        Invoice.statusLabel(status).toUpperCase(),
        style: TextStyle(
          fontFamily: 'Geist',
          fontSize: 9,
          fontWeight: FontWeight.w800,
          letterSpacing: 1,
          color: filled ? Colors.white : color,
        ),
      ),
    );
  }
}

class _Divider extends StatelessWidget {
  const _Divider(this.p);
  final AppPalette p;
  @override
  Widget build(BuildContext context) =>
      Container(height: 1, color: p.border);
}

class _PreviewSkeleton extends StatelessWidget {
  const _PreviewSkeleton();
  @override
  Widget build(BuildContext context) => ListView(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 16, AppSpacing.page, 24),
        children: const [
          Skeleton(height: 320, radius: AppRadii.section),
          SizedBox(height: 16),
          Skeleton(height: 48, radius: AppRadii.button),
        ],
      );
}
