import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/payment_method.dart';
import '../repositories/payment_methods_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Create or edit a payment method. Mirrors mobile/views/admin-payment-methods.php:
/// a `type` chip selector (bank | crypto) toggles which field group is shown —
/// bank → {bankName, accountName, accountNumber, branchCode, swift};
/// crypto → {network, walletAddress}. Plus instructions + active toggle.
/// StickySaveBar at the foot.
class PaymentMethodFormScreen extends ConsumerStatefulWidget {
  const PaymentMethodFormScreen({super.key, this.methodId});

  final String? methodId;
  bool get isEdit => methodId != null;

  @override
  ConsumerState<PaymentMethodFormScreen> createState() =>
      _PaymentMethodFormScreenState();
}

class _PaymentMethodFormScreenState
    extends ConsumerState<PaymentMethodFormScreen> {
  final _label = TextEditingController();
  final _bankName = TextEditingController();
  final _accountName = TextEditingController();
  final _accountNumber = TextEditingController();
  final _branchCode = TextEditingController();
  final _swift = TextEditingController();
  final _network = TextEditingController();
  final _walletAddress = TextEditingController();
  final _instructions = TextEditingController();

  String _type = 'bank';
  bool _active = true;
  bool _loading = false;
  bool _saving = false;

  static const _types = {'bank': 'Bank', 'crypto': 'Crypto'};

  @override
  void initState() {
    super.initState();
    if (widget.isEdit) _loadForEdit();
  }

  @override
  void dispose() {
    _label.dispose();
    _bankName.dispose();
    _accountName.dispose();
    _accountNumber.dispose();
    _branchCode.dispose();
    _swift.dispose();
    _network.dispose();
    _walletAddress.dispose();
    _instructions.dispose();
    super.dispose();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final m =
          await ref.read(paymentMethodsRepositoryProvider).fetch(widget.methodId!);
      if (m != null && mounted) {
        _label.text = m.label;
        _type = m.type;
        _bankName.text = m.bankName;
        _accountName.text = m.accountName;
        _accountNumber.text = m.accountNumber;
        _branchCode.text = m.branchCode;
        _swift.text = m.swift;
        _network.text = m.network;
        _walletAddress.text = m.walletAddress;
        _instructions.text = m.instructions;
        _active = m.active;
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    final label = _label.text.trim();
    if (label.isEmpty) {
      _toast('A label is required');
      return;
    }
    // Validate the active field group.
    if (_type == 'bank' && _accountName.text.trim().isEmpty) {
      _toast('Account name is required for bank methods');
      return;
    }
    if (_type == 'crypto' && _walletAddress.text.trim().isEmpty) {
      _toast('Wallet address is required for crypto methods');
      return;
    }

    setState(() => _saving = true);
    final repo = ref.read(paymentMethodsRepositoryProvider);
    final method = PaymentMethod(
      id: widget.methodId ?? '',
      type: _type,
      label: label,
      bankName: _bankName.text.trim(),
      accountName: _accountName.text.trim(),
      accountNumber: _accountNumber.text.trim(),
      branchCode: _branchCode.text.trim(),
      swift: _swift.text.trim(),
      network: _network.text.trim(),
      walletAddress: _walletAddress.text.trim(),
      instructions: _instructions.text.trim(),
      active: _active,
    );
    try {
      if (widget.isEdit) {
        await repo.update(widget.methodId!, method);
      } else {
        await repo.create(method);
      }
      ref.invalidate(paymentMethodsProvider);
      if (mounted) context.pop();
    } catch (e) {
      _toast('$e');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _toast(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message), behavior: SnackBarBehavior.floating));
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Method' : 'New Method'),
      ),
      body: SafeArea(
        top: false,
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: _loading
                  ? const _FormSkeleton()
                  : FadeIn(
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(
                            AppSpacing.page, 8, AppSpacing.page, 24),
                        children: [
                          ChipSelector<String>(
                            label: 'Type',
                            options: _types,
                            selected: _type,
                            onSelected: (v) => setState(() {
                              _type = v;
                            }),
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Label',
                            controller: _label,
                            hint: _type == 'bank'
                                ? 'Business Bank Account'
                                : 'USDT (TRC20)',
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 24),
                          SectionCard(
                            heading:
                                _type == 'bank' ? 'Bank Details' : 'Crypto Details',
                            child: _type == 'bank'
                                ? _BankFields(
                                    bankName: _bankName,
                                    accountName: _accountName,
                                    accountNumber: _accountNumber,
                                    branchCode: _branchCode,
                                    swift: _swift,
                                  )
                                : _CryptoFields(
                                    network: _network,
                                    walletAddress: _walletAddress,
                                  ),
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Instructions (optional)',
                            controller: _instructions,
                            hint: 'Reference / memo to show the payer…',
                            maxLines: 4,
                          ),
                          const SizedBox(height: 20),
                          _ToggleRow(
                            label: 'Active',
                            sublabel: 'Shown to users paying for a plan',
                            value: _active,
                            onChanged: (v) => setState(() => _active = v),
                          ),
                          const SizedBox(height: 8),
                          // Hint that switching type clears the other group.
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: p.canvas,
                              border: Border.all(color: p.border),
                              borderRadius: BorderRadius.circular(AppRadii.button),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                HeroIcon(HeroPaths.book, size: 16, color: p.textFaint),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    'Switching type saves only that group\'s fields; the '
                                    'other group is cleared.',
                                    style: TextStyle(
                                        fontSize: 12, color: p.textFaint, height: 1.4),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
            ),
            StickySaveBar(
              onSave: _save,
              onCancel: () => context.pop(),
              saving: _saving,
              saveLabel: widget.isEdit ? 'Save' : 'Create',
            ),
          ],
        ),
      ),
    );
  }
}

class _BankFields extends StatelessWidget {
  const _BankFields({
    required this.bankName,
    required this.accountName,
    required this.accountNumber,
    required this.branchCode,
    required this.swift,
  });

  final TextEditingController bankName;
  final TextEditingController accountName;
  final TextEditingController accountNumber;
  final TextEditingController branchCode;
  final TextEditingController swift;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        LabeledField(
          label: 'Bank Name',
          controller: bankName,
          hint: 'First National Bank',
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),
        LabeledField(
          label: 'Account Name',
          controller: accountName,
          hint: 'Acme Corp',
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),
        LabeledField(
          label: 'Account Number',
          controller: accountNumber,
          hint: '0123456789',
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),
        LabeledField(
          label: 'Branch Code',
          controller: branchCode,
          hint: '250655',
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),
        LabeledField(
          label: 'SWIFT / IBAN',
          controller: swift,
          hint: 'FNBNZSZA',
          textInputAction: TextInputAction.done,
        ),
      ],
    );
  }
}

class _CryptoFields extends StatelessWidget {
  const _CryptoFields({required this.network, required this.walletAddress});

  final TextEditingController network;
  final TextEditingController walletAddress;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        LabeledField(
          label: 'Network',
          controller: network,
          hint: 'TRC20',
          textInputAction: TextInputAction.next,
        ),
        const SizedBox(height: 16),
        LabeledField(
          label: 'Wallet Address',
          controller: walletAddress,
          hint: 'T…',
          textInputAction: TextInputAction.done,
        ),
      ],
    );
  }
}

class _ToggleRow extends StatelessWidget {
  const _ToggleRow({
    required this.label,
    required this.value,
    required this.onChanged,
    this.sublabel,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
  final String? sublabel;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label.toUpperCase(),
                    style: AppType.label(context, color: p.textMuted, size: 11)),
                if (sublabel != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(sublabel!,
                        style: TextStyle(fontSize: 12, color: p.textFaint)),
                  ),
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeTrackColor: p.ink,
          ),
        ],
      ),
    );
  }
}

class _FormSkeleton extends StatelessWidget {
  const _FormSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(AppSpacing.page, 24, AppSpacing.page, 24),
      children: const [
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(),
        SizedBox(height: 20),
        _FieldSkeleton(),
      ],
    );
  }
}

class _FieldSkeleton extends StatelessWidget {
  const _FieldSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Skeleton(height: 12, width: 120),
        SizedBox(height: 10),
        Skeleton(height: 48, radius: AppRadii.button),
      ],
    );
  }
}
