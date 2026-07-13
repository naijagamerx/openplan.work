import 'dart:async';

import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';

/// Inverted ink hero card with a 25-minute Pomodoro countdown (Start/Pause/Reset).
class PomodoroCard extends StatefulWidget {
  const PomodoroCard({super.key});

  @override
  State<PomodoroCard> createState() => _PomodoroCardState();
}

class _PomodoroCardState extends State<PomodoroCard> {
  static const _focus = 25 * 60;
  int _seconds = _focus;
  Timer? _timer;
  bool _running = false;

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _toggle() {
    if (_running) {
      _timer?.cancel();
      setState(() => _running = false);
    } else {
      setState(() => _running = true);
      _timer = Timer.periodic(const Duration(seconds: 1), (t) {
        if (_seconds <= 1) {
          t.cancel();
          setState(() {
            _seconds = 0;
            _running = false;
          });
        } else {
          setState(() => _seconds--);
        }
      });
    }
  }

  void _reset() {
    _timer?.cancel();
    setState(() {
      _seconds = _focus;
      _running = false;
    });
  }

  String get _label {
    final m = _seconds ~/ 60, s = _seconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 24),
      decoration: BoxDecoration(
        color: p.ink,
        borderRadius: BorderRadius.circular(AppRadii.section),
        boxShadow: [
          BoxShadow(
            color: p.ink.withValues(alpha: 0.2),
            blurRadius: 28,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        children: [
          Text(
            'POMODORO TIMER',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 3,
              color: p.onInk.withValues(alpha: 0.5),
            ),
          ),
          const SizedBox(height: 16),
          Text(_label,
              style: AppType.mono(
                  size: 64, weight: FontWeight.w300, color: p.onInk)),
          const SizedBox(height: 24),
          Row(
            children: [
              Expanded(
                  child: _btn(p, _running ? 'Pause' : 'Start',
                      filled: true, onTap: _toggle)),
              const SizedBox(width: 12),
              Expanded(child: _btn(p, 'Reset', filled: false, onTap: _reset)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _btn(AppPalette p, String label,
      {required bool filled, required VoidCallback onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 52,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: filled ? p.onInk : Colors.transparent,
          border: filled ? null : Border.all(color: p.onInk.withValues(alpha: 0.3)),
          borderRadius: BorderRadius.circular(AppRadii.button),
        ),
        child: Text(
          label.toUpperCase(),
          style: TextStyle(
            fontFamily: 'Geist',
            fontSize: 13,
            fontWeight: FontWeight.w700,
            letterSpacing: 2,
            color: filled ? p.ink : p.onInk,
          ),
        ),
      ),
    );
  }
}
