import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../models/plan.dart';
import '../repositories/plans_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Create or edit a subscription plan. Mirrors mobile/views/admin-plans.php's
/// inline form: name, price, currency (USD/EUR/GBP/ZAR), durationDays,
/// monthlyRequestCap (0=∞), provider multi-select (groq/openrouter/gemini/ollama),
/// description, and an active toggle. StickySaveBar at the foot.
class PlanFormScreen extends ConsumerStatefulWidget {
  const PlanFormScreen({super.key, this.planId});

  final String? planId;
  bool get isEdit => planId != null;

  @override
  ConsumerState<PlanFormScreen> createState() => _PlanFormScreenState();
}

class _PlanFormScreenState extends ConsumerState<PlanFormScreen> {
  final _name = TextEditingController();
  final _price = TextEditingController();
  final _duration = TextEditingController();
  final _cap = TextEditingController();
  final _description = TextEditingController();

  String _currency = 'USD';
  List<String> _providers = const [];
  bool _active = true;
  bool _loading = false;
  bool _saving = false;

  static const _currencies = {
    'USD': 'USD',
    'EUR': 'EUR',
    'GBP': 'GBP',
    'ZAR': 'ZAR',
  };

  @override
  void initState() {
    super.initState();
    _duration.text = '30';
    _cap.text = '0';
    if (widget.isEdit) _loadForEdit();
  }

  @override
  void dispose() {
    _name.dispose();
    _price.dispose();
    _duration.dispose();
    _cap.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _loadForEdit() async {
    setState(() => _loading = true);
    try {
      final plan = await ref.read(plansRepositoryProvider).fetch(widget.planId!);
      if (plan != null && mounted) {
        _name.text = plan.name;
        _price.text = plan.price == 0 ? '' : plan.price.toString();
        _currency = plan.currency;
        _duration.text = plan.durationDays.toString();
        _cap.text = plan.monthlyRequestCap.toString();
        _providers = List.of(plan.providers);
        _description.text = plan.description;
        _active = plan.active;
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      _toast('Plan name is required');
      return;
    }
    final price = double.tryParse(_price.text.trim()) ?? 0;
    if (price < 0) {
      _toast('Price cannot be negative');
      return;
    }
    final duration = int.tryParse(_duration.text.trim()) ?? 0;
    if (duration <= 0) {
      _toast('Duration must be at least 1 day');
      return;
    }
    final cap = int.tryParse(_cap.text.trim()) ?? 0;

    setState(() => _saving = true);
    final repo = ref.read(plansRepositoryProvider);
    final plan = Plan(
      id: widget.planId ?? '',
      name: name,
      price: price,
      currency: _currency,
      durationDays: duration,
      monthlyRequestCap: cap < 0 ? 0 : cap,
      providers: _providers,
      description: _description.text.trim(),
      active: _active,
    );
    try {
      if (widget.isEdit) {
        await repo.update(widget.planId!, plan);
      } else {
        await repo.create(plan);
      }
      ref.invalidate(plansProvider);
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
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: Text(widget.isEdit ? 'Edit Plan' : 'New Plan'),
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
                          LabeledField(
                            label: 'Plan Name',
                            controller: _name,
                            hint: 'Pro',
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                flex: 3,
                                child: LabeledField(
                                  label: 'Price',
                                  controller: _price,
                                  hint: '0.00',
                                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  textInputAction: TextInputAction.next,
                                ),
                              ),
                              const SizedBox(width: 16),
                              Expanded(
                                flex: 2,
                                child: ChipSelector<String>(
                                  label: 'Currency',
                                  options: _currencies,
                                  selected: _currency,
                                  onSelected: (v) =>
                                      setState(() => _currency = v),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Duration (days)',
                            controller: _duration,
                            hint: '30',
                            keyboardType: TextInputType.number,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Monthly Request Cap (0 = unlimited)',
                            controller: _cap,
                            hint: '0',
                            keyboardType: TextInputType.number,
                            textInputAction: TextInputAction.next,
                          ),
                          const SizedBox(height: 24),
                          SectionCard(
                            heading: 'AI Providers',
                            child: _ProviderMultiSelect(
                              selected: _providers,
                              onChanged: (next) =>
                                  setState(() => _providers = next),
                            ),
                          ),
                          const SizedBox(height: 20),
                          LabeledField(
                            label: 'Description',
                            controller: _description,
                            hint: 'What subscribers get…',
                            maxLines: 4,
                          ),
                          const SizedBox(height: 20),
                          _ToggleRow(
                            label: 'Active',
                            sublabel: 'Available for new subscriptions',
                            value: _active,
                            onChanged: (v) => setState(() => _active = v),
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

/// Multi-select chip group for AI providers (ChipSelector is single-select).
class _ProviderMultiSelect extends StatelessWidget {
  const _ProviderMultiSelect({required this.selected, required this.onChanged});

  final List<String> selected;
  final ValueChanged<List<String>> onChanged;

  static const _all = ['groq', 'openrouter', 'gemini', 'ollama'];

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final provider in _all)
          _toggleChip(context, provider, p),
      ],
    );
  }

  Widget _toggleChip(BuildContext context, String provider, AppPalette p) {
    final isSelected = selected.contains(provider);
    final fill = isSelected ? p.ink : Colors.transparent;
    final fg = isSelected ? p.onInk : p.textPrimary;
    return GestureDetector(
      onTap: () {
        final next = List<String>.of(selected);
        if (isSelected) {
          next.remove(provider);
        } else {
          next.add(provider);
        }
        onChanged(next);
      },
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(color: isSelected ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(
          provider.toUpperCase(),
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
