import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../auth/auth_controller.dart';
import '../models/api_exception.dart';
import '../repositories/dashboard_repository.dart';
import '../repositories/water_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/brand_logo.dart';
import '../widgets/hero_icon.dart';

/// Login styled after the web `/mobile` auth screen: black hero band (logo +
/// black-weight wordmark) over underlined fields + a black pill "Continue".
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _master = TextEditingController();
  bool _obscurePw = true;
  bool _obscureMaster = true;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _master.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_email.text.trim().isEmpty ||
        _password.text.isEmpty ||
        _master.text.isEmpty) {
      setState(() => _error = 'All fields are required.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(authStateProvider.notifier).login(
            email: _email.text.trim(),
            password: _password.text,
            masterPassword: _master.text,
            deviceName: 'Flutter device',
            platform: Theme.of(context).platform.name,
          );
      // Token is now stored — drop any stale (pre-login) provider state so the
      // dashboard's first fetch runs fresh with the token (fixes error-then-retry).
      ref.invalidate(dashboardProvider);
      ref.invalidate(waterProvider);
      if (mounted) context.go('/');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = 'Login failed: $e');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Scaffold(
      backgroundColor: p.surface,
      body: Column(
        children: [
          const _HeroBand(),
          Expanded(
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(
                  24, 28, 24, 28 + MediaQuery.of(context).padding.bottom),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 460),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null)
                      Container(
                        margin: const EdgeInsets.only(bottom: 20),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF2F2),
                          border: const Border(
                              left: BorderSide(
                                  color: Color(0xFFDC2626), width: 4)),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(_error!,
                            style: const TextStyle(
                                color: Color(0xFFB91C1C), fontSize: 13)),
                      ),
                    _UnderlinedField(
                      label: 'Email address',
                      controller: _email,
                      hint: 'your@email.com',
                      keyboard: TextInputType.emailAddress,
                    ),
                    const SizedBox(height: 28),
                    _UnderlinedField(
                      label: 'Password',
                      controller: _password,
                      hint: 'Enter password',
                      obscure: _obscurePw,
                      onToggle: () => setState(() => _obscurePw = !_obscurePw),
                    ),
                    const SizedBox(height: 28),
                    _UnderlinedField(
                      label: 'Master password',
                      controller: _master,
                      hint: 'Unlocks your encrypted data',
                      obscure: _obscureMaster,
                      onToggle: () =>
                          setState(() => _obscureMaster = !_obscureMaster),
                      onSubmit: (_) => _submit(),
                    ),
                    const SizedBox(height: 36),
                    _PillButton(
                      label: _busy ? 'Signing in…' : 'Continue',
                      onPressed: _busy ? null : _submit,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroBand extends StatelessWidget {
  const _HeroBand();

  @override
  Widget build(BuildContext context) {
    final topInset = MediaQuery.of(context).padding.top;
    return ClipRRect(
      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(32)),
      child: Container(
        width: double.infinity,
        color: const Color(0xFF0A0A0A), // ink — DESIGN.md §7 bans pure #000000
        child: Stack(
          children: [
            Positioned.fill(
              child: CustomPaint(painter: _DotPattern()),
            ),
            Padding(
              padding: EdgeInsets.fromLTRB(24, topInset + 56, 24, 56),
              child: Column(
                children: [
                  Container(
                    width: 64,
                    height: 64,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.white24, width: 1.5),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    // Logo is fetched from the admin-configured favicon on the
                    // backend (api/favicon.php uploads). Falls back to "O" tile.
                    clipBehavior: Clip.antiAlias,
                    alignment: Alignment.center,
                    child: const BrandLogo(size: 64, radius: 18),
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'OPENPLAN WORK',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontFamily: 'Geist',
                      fontSize: 34,
                      height: 1.0,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -1,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Text(
                    'PRODUCTIVITY · CRM · FINANCE',
                    style: TextStyle(
                      fontSize: 9,
                      fontWeight: FontWeight.w300,
                      letterSpacing: 4,
                      color: Colors.white.withValues(alpha: 0.4),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DotPattern extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = Colors.white.withValues(alpha: 0.08);
    const gap = 26.0;
    for (double y = 12; y < size.height; y += gap) {
      for (double x = 12; x < size.width; x += gap) {
        canvas.drawCircle(Offset(x, y), 1, paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _UnderlinedField extends StatelessWidget {
  const _UnderlinedField({
    required this.label,
    required this.controller,
    required this.hint,
    this.obscure = false,
    this.keyboard,
    this.onToggle,
    this.onSubmit,
  });

  final String label;
  final TextEditingController controller;
  final String hint;
  final bool obscure;
  final TextInputType? keyboard;
  final VoidCallback? onToggle;
  final ValueChanged<String>? onSubmit;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: TextStyle(
            fontSize: 10,
            fontWeight: FontWeight.w700,
            letterSpacing: 2,
            color: p.textFaint,
          ),
        ),
        TextField(
          controller: controller,
          obscureText: obscure,
          keyboardType: keyboard,
          onSubmitted: onSubmit,
          style: TextStyle(fontSize: 16, color: p.textPrimary),
          decoration: InputDecoration(
            isDense: true,
            hintText: hint,
            hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
            contentPadding: const EdgeInsets.only(top: 10, bottom: 8),
            border: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
            enabledBorder:
                UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
            focusedBorder: UnderlineInputBorder(
                borderSide: BorderSide(color: p.ink, width: 1.5)),
            suffixIcon: onToggle == null
                ? null
                : GestureDetector(
                    behavior: HitTestBehavior.opaque,
                    onTap: onToggle,
                    // DESIGN.md §7: Heroicons only, never Material glyphs.
                    child: Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: HeroIcon(
                        // eye / eye-slash outline paths
                        obscure
                            ? 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178zM15 12a3 3 0 11-6 0 3 3 0 016 0z'
                            : 'M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88',
                        size: 20,
                        color: p.textFaint,
                      ),
                    ),
                  ),
          ),
        ),
      ],
    );
  }
}

class _PillButton extends StatelessWidget {
  const _PillButton({required this.label, this.onPressed});
  final String label;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onPressed,
      child: Opacity(
        opacity: onPressed == null ? 0.7 : 1,
        child: Container(
          height: 56,
          decoration: BoxDecoration(
            color: p.ink,
            borderRadius: BorderRadius.circular(AppRadii.pill),
          ),
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label.toUpperCase(),
                style: TextStyle(
                  fontFamily: 'Geist',
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                  letterSpacing: 3,
                  color: p.onInk,
                ),
              ),
              const SizedBox(width: 10),
              HeroIcon('M14 5l7 7m0 0l-7 7m7-7H3', size: 18, color: p.onInk),
            ],
          ),
        ),
      ),
    );
  }
}
