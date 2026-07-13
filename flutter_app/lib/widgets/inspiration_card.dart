import 'package:flutter/material.dart';

import '../theme/app_tokens.dart';
import 'mono_button.dart';
import 'section_card.dart';

/// Daily hydration inspiration — cycles a local quote list (matches the web's
/// New Quote behavior).
class InspirationCard extends StatefulWidget {
  const InspirationCard({super.key});

  @override
  State<InspirationCard> createState() => _InspirationCardState();
}

class _InspirationCardState extends State<InspirationCard> {
  static const _quotes = [
    ['Water is the driving force of all nature.', 'Leonardo da Vinci'],
    ['Drinking water is like washing out your insides.', 'Unknown'],
    ["Water is life's matter and matrix.", 'Albert Szent-Györgyi'],
    ['The cure for anything is salt water: sweat, tears, or the sea.', 'Isak Dinesen'],
    ["Pure water is the world's first and foremost medicine.", 'Slovakian Proverb'],
  ];
  int _i = 0;

  void _next() => setState(() => _i = (_i + 1) % _quotes.length);

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final q = _quotes[_i];
    return SectionCard(
      heading: 'Daily Hydration Inspiration',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '"${q[0]}"',
            style: TextStyle(
              fontSize: 18,
              fontStyle: FontStyle.italic,
              fontWeight: FontWeight.w500,
              height: 1.4,
              color: p.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          Text('— ${q[1]}',
              style: TextStyle(fontSize: 12, color: p.textFaint)),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _tag(p, 'Keep a water bottle at your desk'),
              _tag(p, 'Your body is 60% water'),
            ],
          ),
          const SizedBox(height: 16),
          MonoButton('New Quote', primary: false, dense: true, onPressed: _next),
        ],
      ),
    );
  }

  Widget _tag(AppPalette p, String t) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(
        color: p.canvas,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        border: Border.all(color: p.border),
      ),
      child: Text(
        t.toUpperCase(),
        style: TextStyle(
          fontSize: 9,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
          color: p.textMuted,
        ),
      ),
    );
  }
}
