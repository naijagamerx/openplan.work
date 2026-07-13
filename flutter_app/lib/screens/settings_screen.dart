import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/auth_controller.dart';
import '../models/device.dart';
import '../repositories/config_repository.dart';
import '../repositories/devices_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../theme/theme_controller.dart';
import '../widgets/app_scaffold.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/mono_button.dart';
import '../widgets/section_card.dart';

class SettingsScreen extends ConsumerWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = AppPalette.of(context);
    final name = ref.watch(userNameProvider);
    final mode = ref.watch(themeModeProvider);
    final devices = ref.watch(devicesProvider);

    return AppScaffold(
      title: 'Settings',
      current: 'Settings',
      body: ListView(
        padding:
            const EdgeInsets.fromLTRB(AppSpacing.page, 20, AppSpacing.page, 120),
        children: [
          // Account
          FadeIn(
            child: SectionCard(
              heading: 'Account',
              child: Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: p.ink,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      _initial(name),
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                          color: p.onInk),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(name?.isNotEmpty == true ? name! : 'Signed in',
                            style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                                color: p.textPrimary)),
                        const SizedBox(height: 2),
                        Text('OpenPlan Work',
                            style: AppType.label(context)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Appearance
          FadeIn(
            delay: const Duration(milliseconds: 80),
            child: SectionCard(
              heading: 'Appearance',
              child: _ThemeSelector(
                mode: mode,
                onChanged: (m) =>
                    ref.read(themeModeProvider.notifier).setAndPersist(m),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Security
          FadeIn(
            delay: const Duration(milliseconds: 120),
            child: SectionCard(
              heading: 'Security',
              child: Column(
                children: [
                  _ActionRow(
                    icon: HeroPaths.commandLine,
                    label: 'Change password',
                    onTap: () => _ChangePasswordDialog.show(context, ref),
                  ),
                  Divider(height: 1, color: p.border),
                  _ActionRow(
                    icon: HeroPaths.lockClosed,
                    label: 'Change master password',
                    danger: true,
                    onTap: () =>
                        _ChangeMasterPasswordDialog.show(context, ref),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Backup
          FadeIn(
            delay: const Duration(milliseconds: 160),
            child: SectionCard(
              heading: 'Backup',
              trailing: HeroIcon(HeroPaths.chevronRight,
                  size: 18, color: p.textFaint),
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => context.push('/backup'),
                child: Row(
                  children: [
                    HeroIcon(HeroPaths.archive, size: 20, color: p.textMuted),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Backups & restore',
                              style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w600,
                                  color: p.textPrimary)),
                          const SizedBox(height: 2),
                          Text('Create, restore, or delete backups',
                              style: TextStyle(
                                  fontSize: 12, color: p.textFaint)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Devices
          FadeIn(
            delay: const Duration(milliseconds: 160),
            child: SectionCard(
              heading: 'Devices',
              trailing: GestureDetector(
                onTap: () => ref.invalidate(devicesProvider),
                child: HeroIcon(
                    'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
                    size: 18,
                    color: p.textFaint),
              ),
              child: devices.when(
                loading: () => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text('Loading…',
                      style: TextStyle(color: p.textFaint, fontSize: 13)),
                ),
                error: (e, _) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text('Could not load devices',
                      style: TextStyle(color: p.textFaint, fontSize: 13)),
                ),
                data: (list) {
                  if (list.isEmpty) {
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text('No active device sessions',
                          style: TextStyle(color: p.textFaint, fontSize: 13)),
                    );
                  }
                  return Column(
                    children: [
                      for (var i = 0; i < list.length; i++) ...[
                        if (i > 0) Divider(height: 1, color: p.border),
                        _DeviceRow(
                          device: list[i],
                          onRevoke: () => _revoke(ref, list[i].id),
                        ),
                      ],
                    ],
                  );
                },
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Business Information
          const FadeIn(
            delay: Duration(milliseconds: 200),
            child: _BusinessSection(),
          ),
          const SizedBox(height: AppSpacing.gap),

          // AI API Keys
          const FadeIn(
            delay: Duration(milliseconds: 220),
            child: _AiKeysSection(),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Water
          const FadeIn(
            delay: Duration(milliseconds: 240),
            child: _WaterSection(),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Notifications
          const FadeIn(
            delay: Duration(milliseconds: 260),
            child: _NotificationsSection(),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Admin (only when the signed-in user is an admin)
          if (ref.watch(isAdminProvider)) ...[
            FadeIn(
              delay: const Duration(milliseconds: 280),
              child: _AdminSection(
                onTap: () => context.push('/admin'),
              ),
            ),
            const SizedBox(height: AppSpacing.gap),
          ],

          // About
          FadeIn(
            delay: const Duration(milliseconds: 300),
            child: SectionCard(
              heading: 'About',
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: p.ink,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      'O',
                      style: TextStyle(
                          fontFamily: 'Geist',
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: p.onInk),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('OpenPlan Work',
                            style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                                color: p.textPrimary)),
                        const SizedBox(height: 2),
                        Text('Version v0.1.0',
                            style: TextStyle(
                                fontSize: 12, color: p.textFaint)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.gap),

          // Sign out
          FadeIn(
            delay: const Duration(milliseconds: 320),
            child: MonoButton(
              'Sign out',
              primary: false,
              onPressed: () async {
                await ref.read(authStateProvider.notifier).logout();
                if (context.mounted) context.go('/login');
              },
            ),
          ),
          const SizedBox(height: 16),
          Center(
            child: Text('OpenPlan Work · v0.1.0',
                style: AppType.label(context, size: 9)),
          ),
        ],
      ),
    );
  }

  String _initial(String? name) {
    final n = (name ?? '').trim();
    return n.isEmpty ? 'O' : n[0].toUpperCase();
  }

  Future<void> _revoke(WidgetRef ref, String id) async {
    try {
      await ref.read(devicesRepositoryProvider).revoke(id);
      ref.invalidate(devicesProvider);
    } catch (_) {
      // ignore
    }
  }
}

class _ThemeSelector extends StatelessWidget {
  const _ThemeSelector({required this.mode, required this.onChanged});
  final ThemeMode mode;
  final ValueChanged<ThemeMode> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    Widget seg(ThemeMode m, String label) {
      final active = m == mode;
      return Expanded(
        child: GestureDetector(
          onTap: () => onChanged(m),
          child: Container(
            margin: const EdgeInsets.all(3),
            padding: const EdgeInsets.symmetric(vertical: 10),
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: active ? p.ink : Colors.transparent,
              borderRadius: BorderRadius.circular(AppRadii.button - 3),
            ),
            child: Text(
              label.toUpperCase(),
              style: TextStyle(
                fontFamily: 'Geist',
                fontSize: 11,
                fontWeight: FontWeight.w700,
                letterSpacing: 1,
                color: active ? p.onInk : p.textMuted,
              ),
            ),
          ),
        ),
      );
    }

    return Container(
      decoration: BoxDecoration(
        color: p.canvas,
        borderRadius: BorderRadius.circular(AppRadii.button),
        border: Border.all(color: p.border),
      ),
      child: Row(
        children: [
          seg(ThemeMode.system, 'System'),
          seg(ThemeMode.light, 'Light'),
          seg(ThemeMode.dark, 'Dark'),
        ],
      ),
    );
  }
}

class _DeviceRow extends StatelessWidget {
  const _DeviceRow({required this.device, required this.onRevoke});
  final DeviceSession device;
  final VoidCallback onRevoke;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 14),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(device.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: p.textPrimary)),
                const SizedBox(height: 2),
                Text(
                    '${device.platform.isEmpty ? 'device' : device.platform} · ••${device.last4}',
                    style: TextStyle(fontSize: 12, color: p.textFaint)),
              ],
            ),
          ),
          GestureDetector(
            onTap: onRevoke,
            child: const Text('REVOKE',
                style: TextStyle(
                  fontFamily: 'Geist',
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1,
                  color: Color(0xFFDC2626),
                )),
          ),
        ],
      ),
    );
  }
}

/// A tappable labeled row with a leading Heroicon and trailing chevron.
class _ActionRow extends StatelessWidget {
  const _ActionRow({
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
  });

  final String icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: [
            HeroIcon(icon,
                size: 20, color: danger ? const Color(0xFFDC2626) : p.textMuted),
            const SizedBox(width: 12),
            Expanded(
              child: Text(label,
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: danger ? const Color(0xFFDC2626) : p.textPrimary)),
            ),
            HeroIcon(HeroPaths.chevronRight, size: 18, color: p.textFaint),
          ],
        ),
      ),
    );
  }
}

/// A 3-field password-change dialog (current / new / confirm) with show/hide
/// toggles, client-side validation, and a busy state on the submit button.
/// Callers pass an [onSubmit] that performs the API call and returns a message
/// on success, or throws on error.
class _PasswordDialog extends StatefulWidget {
  const _PasswordDialog({
    required this.title,
    required this.confirmLabel,
    required this.currentLabel,
    required this.newLabel,
    required this.minLength,
    required this.onSubmit,
  });

  final String title;
  final String confirmLabel;
  final String currentLabel;
  final String newLabel;
  final int minLength;
  final Future<String> Function(String current, String next) onSubmit;

  @override
  State<_PasswordDialog> createState() => _PasswordDialogState();
}

class _PasswordDialogState extends State<_PasswordDialog> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  bool _showCurrent = false;
  bool _showNext = false;
  bool _showConfirm = false;
  bool _busy = false;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: p.border),
      ),
      title: Text(widget.title,
          style: TextStyle(
              fontSize: 16, fontWeight: FontWeight.w700, color: p.textPrimary)),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _field(widget.currentLabel, _current, _showCurrent,
                () => setState(() => _showCurrent = !_showCurrent)),
            const SizedBox(height: 14),
            _field(
                'New ${widget.newLabel.toLowerCase()} (${widget.minLength}+ chars)',
                _next,
                _showNext,
                () => setState(() => _showNext = !_showNext)),
            const SizedBox(height: 14),
            _field(
                'Confirm new ${widget.newLabel.toLowerCase()}',
                _confirm,
                _showConfirm,
                () => setState(() => _showConfirm = !_showConfirm)),
          ],
        ),
      ),
      actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: Text('CANCEL',
              style: TextStyle(
                  color: p.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
        TextButton(
          onPressed: _busy ? null : _submit,
          child: Text(widget.confirmLabel.toUpperCase(),
              style: TextStyle(
                  color: p.ink,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
      ],
    );
  }

  Widget _field(String label, TextEditingController controller, bool show,
      VoidCallback onToggle) {
    return LabeledField(
      label: label,
      controller: controller,
      obscure: !show,
      suffix: GestureDetector(
        onTap: onToggle,
        child: HeroIcon(show ? HeroPaths.eyeSlash : HeroPaths.eye,
            size: 18, color: AppPalette.of(context).textFaint),
      ),
    );
  }

  Future<void> _submit() async {
    final messenger = ScaffoldMessenger.of(context);
    final current = _current.text.trim();
    final next = _next.text.trim();
    final confirm = _confirm.text.trim();

    if (current.isEmpty || next.isEmpty || confirm.isEmpty) {
      _toast(messenger, 'All fields are required', error: true);
      return;
    }
    if (next.length < widget.minLength) {
      _toast(messenger,
          '${widget.newLabel} must be at least ${widget.minLength} characters',
          error: true);
      return;
    }
    if (next != confirm) {
      _toast(messenger, '${widget.newLabel}s do not match', error: true);
      return;
    }

    setState(() => _busy = true);
    try {
      final message = await widget.onSubmit(current, next);
      if (!mounted) return;
      Navigator.pop(context);
      _toast(messenger, message);
    } catch (e) {
      if (!mounted) return;
      _toast(messenger, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(ScaffoldMessengerState messenger, String message,
      {bool error = false}) {
    messenger.showSnackBar(SnackBar(
      content: Text(message),
      behavior: SnackBarBehavior.floating,
      backgroundColor: error ? const Color(0xFFDC2626) : null,
    ));
  }
}

/// Change account password dialog — POST /api/auth.php?action=change_password.
class _ChangePasswordDialog {
  static Future<void> show(BuildContext context, WidgetRef ref) {
    return showDialog(
      context: context,
      builder: (_) => _PasswordDialog(
        title: 'Change password',
        confirmLabel: 'Update',
        currentLabel: 'Current password',
        newLabel: 'Password',
        minLength: 8,
        onSubmit: (current, next) async {
          await ref
              .read(authRepositoryProvider)
              .changePassword(currentPassword: current, newPassword: next);
          return 'Password updated successfully';
        },
      ),
    );
  }
}

/// Change master password dialog — POST /api/auth.php?action=change_master_password.
/// The server re-encrypts all data; user must sign in again afterwards.
class _ChangeMasterPasswordDialog {
  static Future<void> show(BuildContext context, WidgetRef ref) {
    return showDialog(
      context: context,
      builder: (_) => _PasswordDialog(
        title: 'Change master password',
        confirmLabel: 'Change',
        currentLabel: 'Current master password',
        newLabel: 'Master password',
        minLength: 4,
        onSubmit: (current, next) async {
          await ref.read(authRepositoryProvider).changeMasterPassword(
              currentMasterPassword: current, newMasterPassword: next);
          // Master password change re-encrypts data → force re-login.
          if (context.mounted) {
            await ref.read(authStateProvider.notifier).logout();
            if (context.mounted) context.go('/login');
          }
          return 'Master password changed. Please sign in again.';
        },
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────
// Config-backed settings sections (Business / AI Keys / Water / Notifications).
//
// Each is a self-contained [ConsumerStatefulWidget] that:
//   1. seeds its fields once from the cached `configProvider` (masked API keys
//      arrive as `abcd…wxyz` — the save guard ignores the mask),
//   2. holds local mutable state (controllers / toggles),
//   3. persists via [ConfigRepository] then invalidates `configProvider`.
// Mirrors `CustomInstructionScreen`'s load-once / edit / save flow.
// ─────────────────────────────────────────────────────────────────────────

/// Reads a config value as a trimmed [String] (never null).
String _cfgStr(Map<String, dynamic> c, String key) =>
    (c[key] ?? '').toString().trim();

/// Business Information — section `business`
/// (`POST /api/settings.php?action=save`, businessName/Email/Phone/Address/
/// currency/taxRate).
class _BusinessSection extends ConsumerStatefulWidget {
  const _BusinessSection();
  @override
  ConsumerState<_BusinessSection> createState() => _BusinessSectionState();
}

class _BusinessSectionState extends ConsumerState<_BusinessSection> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _address = TextEditingController();
  final _tax = TextEditingController();
  String _currency = 'USD';
  bool _loaded = false;
  bool _saving = false;

  static const _currencies = <String, String>{
    'USD': 'USD \$',
    'EUR': 'EUR €',
    'GBP': 'GBP £',
    'ZAR': 'ZAR R',
  };

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _address.dispose();
    _tax.dispose();
    super.dispose();
  }

  void _seed(Map<String, dynamic> c) {
    if (_loaded) return;
    _name.text = _cfgStr(c, 'businessName');
    _email.text = _cfgStr(c, 'businessEmail');
    _phone.text = _cfgStr(c, 'businessPhone');
    _address.text = _cfgStr(c, 'businessAddress');
    _tax.text = _cfgStr(c, 'taxRate');
    final cur = _cfgStr(c, 'currency');
    _currency = _currencies.containsKey(cur) ? cur : 'USD';
    _loaded = true;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(configRepositoryProvider).saveSection('business', {
        'businessName': _name.text,
        'businessEmail': _email.text,
        'businessPhone': _phone.text,
        'businessAddress': _address.text,
        'currency': _currency,
        'taxRate': _tax.text,
      });
      ref.invalidate(configProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Business information saved'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to save: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(configProvider).asData?.value ?? const {};
    _seed(config);
    return SectionCard(
      heading: 'Business',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LabeledField(label: 'Business name', controller: _name),
          const SizedBox(height: 14),
          LabeledField(
              label: 'Email',
              controller: _email,
              keyboardType: TextInputType.emailAddress),
          const SizedBox(height: 14),
          LabeledField(
              label: 'Phone',
              controller: _phone,
              keyboardType: TextInputType.phone),
          const SizedBox(height: 14),
          LabeledField(label: 'Address', controller: _address),
          const SizedBox(height: 14),
          ChipSelector<String>(
            label: 'Currency',
            options: _currencies,
            selected: _currency,
            onSelected: (v) => setState(() => _currency = v),
          ),
          const SizedBox(height: 14),
          LabeledField(
            label: 'Tax rate (%)',
            controller: _tax,
            keyboardType:
                const TextInputType.numberWithOptions(decimal: true),
          ),
          const SizedBox(height: 18),
          _SaveButton(label: 'Save business', saving: _saving, onSave: _save),
        ],
      ),
    );
  }
}

/// AI API Keys — section `api` (groqApiKey/openrouterApiKey). Keys arrive
/// MASKED from the server; [ConfigRepository.saveApiKeys] skips any value
/// still containing the `...` mask so the placeholder is never written back.
class _AiKeysSection extends ConsumerStatefulWidget {
  const _AiKeysSection();
  @override
  ConsumerState<_AiKeysSection> createState() => _AiKeysSectionState();
}

class _AiKeysSectionState extends ConsumerState<_AiKeysSection> {
  final _groq = TextEditingController();
  final _openrouter = TextEditingController();
  bool _showGroq = false;
  bool _showOpenrouter = false;
  bool _loaded = false;
  bool _saving = false;

  @override
  void dispose() {
    _groq.dispose();
    _openrouter.dispose();
    super.dispose();
  }

  void _seed(Map<String, dynamic> c) {
    if (_loaded) return;
    _groq.text = _cfgStr(c, 'groqApiKey');
    _openrouter.text = _cfgStr(c, 'openrouterApiKey');
    _loaded = true;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(configRepositoryProvider).saveApiKeys({
        'groqApiKey': _groq.text,
        'openrouterApiKey': _openrouter.text,
      });
      ref.invalidate(configProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('API keys saved'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to save: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(configProvider).asData?.value ?? const {};
    _seed(config);
    return SectionCard(
      heading: 'AI API Keys',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LabeledField(
            label: 'Groq API key',
            controller: _groq,
            obscure: !_showGroq,
            hint: 'gsk_…',
            suffix: _eyeToggle(() => setState(() => _showGroq = !_showGroq),
                _showGroq),
          ),
          const SizedBox(height: 14),
          LabeledField(
            label: 'OpenRouter API key',
            controller: _openrouter,
            obscure: !_showOpenrouter,
            hint: 'sk-or-…',
            suffix: _eyeToggle(
                () => setState(() => _showOpenrouter = !_showOpenrouter),
                _showOpenrouter),
          ),
          const SizedBox(height: 8),
          _LinkRow(
            icon: HeroPaths.sparkles,
            label: 'Model settings',
            onTap: () => context.push('/model-settings'),
          ),
          Divider(height: 1, color: AppPalette.of(context).border),
          _LinkRow(
            icon: HeroPaths.commandLine,
            label: 'Custom instruction',
            onTap: () => context.push('/custom-instruction'),
          ),
          const SizedBox(height: 18),
          _SaveButton(label: 'Save keys', saving: _saving, onSave: _save),
        ],
      ),
    );
  }

  Widget _eyeToggle(VoidCallback onTap, bool show) => GestureDetector(
        onTap: onTap,
        child: HeroIcon(
            show ? HeroPaths.eyeSlash : HeroPaths.eye,
            size: 18,
            color: AppPalette.of(context).textFaint),
      );
}

/// Water — daily goal + reminder interval. The backend persists these inside
/// the `notifications` section (`waterReminderGoal` glasses, default 8;
/// `waterReminderInterval` minutes, default 60).
class _WaterSection extends ConsumerStatefulWidget {
  const _WaterSection();
  @override
  ConsumerState<_WaterSection> createState() => _WaterSectionState();
}

class _WaterSectionState extends ConsumerState<_WaterSection> {
  final _goal = TextEditingController(text: '8');
  final _interval = TextEditingController(text: '60');
  bool _loaded = false;
  bool _saving = false;

  @override
  void dispose() {
    _goal.dispose();
    _interval.dispose();
    super.dispose();
  }

  void _seed(Map<String, dynamic> c) {
    if (_loaded) return;
    final goal = c['waterReminderGoal'];
    final interval = c['waterReminderInterval'];
    _goal.text = (goal == null ? '8' : goal.toString()).trim();
    _interval.text = (interval == null ? '60' : interval.toString()).trim();
    _loaded = true;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      // The backend co-locates water goal/interval in the notifications section.
      await ref.read(configRepositoryProvider).saveSection('notifications', {
        'waterReminderGoal': int.tryParse(_goal.text) ?? 8,
        'waterReminderInterval': int.tryParse(_interval.text) ?? 60,
      });
      ref.invalidate(configProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Water settings saved'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to save: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(configProvider).asData?.value ?? const {};
    _seed(config);
    return SectionCard(
      heading: 'Water',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LabeledField(
            label: 'Daily goal (glasses)',
            controller: _goal,
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 14),
          LabeledField(
            label: 'Reminder interval (min)',
            controller: _interval,
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 18),
          _SaveButton(label: 'Save water', saving: _saving, onSave: _save),
        ],
      ),
    );
  }
}

/// Notifications — alert-type toggles + master switch + sound/volume.
/// Persisted via the `notifications` section (see api/settings.php).
class _NotificationsSection extends ConsumerStatefulWidget {
  const _NotificationsSection();
  @override
  ConsumerState<_NotificationsSection> createState() =>
      _NotificationsSectionState();
}

class _NotificationsSectionState extends ConsumerState<_NotificationsSection> {
  bool _enabled = false;
  bool _overdue = true;
  bool _dueSoon = true;
  bool _notStarted = false;
  bool _sound = true;
  double _volume = 80;
  bool _loaded = false;
  bool _saving = false;

  void _seed(Map<String, dynamic> c) {
    if (_loaded) return;
    _enabled = _cfgBool(c, 'notificationsEnabled', false);
    _overdue = _cfgBool(c, 'overdueAlerts', true);
    _dueSoon = _cfgBool(c, 'dueSoonAlerts', true);
    _notStarted = _cfgBool(c, 'notStartedAlerts', false);
    _sound = _cfgBool(c, 'soundEnabled', true);
    final v = c['volume'];
    _volume = v is num ? v.toDouble().clamp(0, 100) : 80;
    _loaded = true;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(configRepositoryProvider).saveSection('notifications', {
        'notificationsEnabled': _enabled,
        'overdueAlerts': _overdue,
        'dueSoonAlerts': _dueSoon,
        'notStartedAlerts': _notStarted,
        'soundEnabled': _sound,
        'volume': _volume.round(),
      });
      ref.invalidate(configProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Notification settings saved'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to save: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(configProvider).asData?.value ?? const {};
    _seed(config);
    final p = AppPalette.of(context);
    return SectionCard(
      heading: 'Notifications',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _ToggleRow(
            icon: HeroPaths.bell,
            label: 'Enable notifications',
            value: _enabled,
            onChanged: (v) => setState(() => _enabled = v),
          ),
          const SizedBox(height: 10),
          _ToggleRow(
            icon: HeroPaths.flag,
            label: 'Overdue alerts',
            value: _overdue,
            onChanged: (v) => setState(() => _overdue = v),
          ),
          const SizedBox(height: 10),
          _ToggleRow(
            icon: HeroPaths.clock,
            label: 'Due soon alerts',
            value: _dueSoon,
            onChanged: (v) => setState(() => _dueSoon = v),
          ),
          const SizedBox(height: 10),
          _ToggleRow(
            icon: HeroPaths.clipboard,
            label: 'Not started alerts',
            value: _notStarted,
            onChanged: (v) => setState(() => _notStarted = v),
          ),
          const SizedBox(height: 10),
          _ToggleRow(
            icon: HeroPaths.sparkles,
            label: 'Notification sound',
            value: _sound,
            onChanged: (v) => setState(() => _sound = v),
          ),
          if (_sound) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                Text('VOLUME',
                    style: AppType.label(context, color: p.textMuted, size: 11)),
                const SizedBox(width: 12),
                Expanded(
                  child: Slider(
                    value: _volume,
                    min: 0,
                    max: 100,
                    divisions: 20,
                    activeColor: p.ink,
                    inactiveColor: p.border,
                    onChanged: (v) => setState(() => _volume = v),
                  ),
                ),
                SizedBox(
                  width: 42,
                  child: Text('${_volume.round()}%',
                      textAlign: TextAlign.right,
                      style: AppType.mono(
                          size: 13, weight: FontWeight.w600, color: p.textMuted)),
                ),
              ],
            ),
          ],
          const SizedBox(height: 18),
          _SaveButton(
              label: 'Save notifications', saving: _saving, onSave: _save),
        ],
      ),
    );
  }
}

/// Admin section — a single tappable card linking to the admin hub. Only
/// rendered when `ref.watch(isAdminProvider)` is true.
class _AdminSection extends StatelessWidget {
  const _AdminSection({required this.onTap});
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SectionCard(
      heading: 'Admin',
      trailing:
          HeroIcon(HeroPaths.chevronRight, size: 18, color: p.textFaint),
      child: _ActionRow(
        icon: HeroPaths.commandLine,
        label: 'Admin hub',
        onTap: onTap,
      ),
    );
  }
}

/// Reads a config value as a bool with a default.
bool _cfgBool(Map<String, dynamic> c, String key, bool fallback) {
  final v = c[key];
  if (v is bool) return v;
  if (v is String) {
    if (v.isEmpty) return fallback;
    return v == '1' || v.toLowerCase() == 'true';
  }
  return fallback;
}

/// A monochrome toggle row with leading icon + trailing switch knob
/// (matches the note_form_screen convention, no Material glow).
class _ToggleRow extends StatelessWidget {
  const _ToggleRow({
    required this.icon,
    required this.label,
    required this.value,
    required this.onChanged,
  });

  final String icon;
  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: () => onChanged(!value),
      behavior: HitTestBehavior.opaque,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 14),
        decoration: BoxDecoration(
          color: p.surface,
          border: Border.all(color: p.border),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Row(
          children: [
            HeroIcon(icon, size: 18, color: value ? p.ink : p.textMuted),
            const SizedBox(width: 12),
            Expanded(
              child: Text(label.toUpperCase(),
                  style: AppType.label(
                      context, color: p.textPrimary, size: 12)),
            ),
            _SwitchKnob(value: value, onChanged: onChanged),
          ],
        ),
      ),
    );
  }
}

/// Border-only toggle knob (the monochrome switch from note_form_screen).
class _SwitchKnob extends StatelessWidget {
  const _SwitchKnob({required this.value, required this.onChanged});
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: () => onChanged(!value),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: 42,
        height: 24,
        padding: const EdgeInsets.all(2),
        alignment: value ? Alignment.centerRight : Alignment.centerLeft,
        decoration: BoxDecoration(
          color: value ? p.ink : p.canvas,
          border: Border.all(color: value ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Container(
          width: 18,
          height: 18,
          decoration: BoxDecoration(
            color: value ? p.onInk : p.textFaint,
            shape: BoxShape.circle,
          ),
        ),
      ),
    );
  }
}

/// A tappable row with a leading icon and trailing chevron, used for the
/// AI-keys section's links to model-settings / custom-instruction.
class _LinkRow extends StatelessWidget {
  const _LinkRow({required this.icon, required this.label, required this.onTap});
  final String icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14),
        child: Row(
          children: [
            HeroIcon(icon, size: 20, color: p.textMuted),
            const SizedBox(width: 12),
            Expanded(
              child: Text(label,
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: p.textPrimary)),
            ),
            HeroIcon(HeroPaths.chevronRight, size: 18, color: p.textFaint),
          ],
        ),
      ),
    );
  }
}

/// The inline save button used by each config section card.
class _SaveButton extends StatelessWidget {
  const _SaveButton({
    required this.label,
    required this.saving,
    required this.onSave,
  });
  final String label;
  final bool saving;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    // DESIGN.md: no CircularProgressIndicator. A textual "Saving…" state on a
    // disabled button keeps the monochrome language.
    return Align(
      alignment: Alignment.centerRight,
      child: MonoButton(
        saving ? 'Saving…' : label,
        dense: true,
        expand: false,
        onPressed: saving ? null : onSave,
      ),
    );
  }
}
