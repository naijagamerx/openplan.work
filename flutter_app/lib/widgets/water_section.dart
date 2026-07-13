import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../repositories/water_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'mono_button.dart';
import 'section_card.dart';
import 'water_cup.dart';

class WaterSection extends ConsumerWidget {
  const WaterSection({super.key});

  static const _blue = Color(0xFF2563EB);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final water = ref.watch(waterProvider);
    final p = AppPalette.of(context);
    final status = water.asData?.value;
    final progress = status?.progress ?? 0;
    final litersIn = status?.litersIn ?? 0;
    final litersGoal = status?.litersGoal ?? 2;

    return SectionCard(
      heading: 'Stay Hydrated',
      trailing: Text('${litersIn.toStringAsFixed(2)} L',
          style: AppType.label(context, color: _blue)),
      child: Column(
        children: [
          Row(
            children: [
              WaterCup(progress: progress, size: 86),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${(progress * 100).round()}%',
                        style: AppType.mono(size: 36, color: p.textPrimary)),
                    const SizedBox(height: 2),
                    Text('OF ${litersGoal.toStringAsFixed(1)} L GOAL',
                        style: AppType.label(context)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                  child: MonoButton('+0.25 L',
                      dense: true, onPressed: () => _add(ref, 250))),
              const SizedBox(width: 8),
              Expanded(
                  child: MonoButton('+0.50 L',
                      dense: true, onPressed: () => _add(ref, 500))),
              const SizedBox(width: 8),
              Expanded(
                  child: MonoButton('+1.00 L',
                      primary: false, dense: true, onPressed: () => _add(ref, 1000))),
            ],
          ),
        ],
      ),
    );
  }

  Future<void> _add(WidgetRef ref, int ml) async {
    try {
      await ref.read(waterRepositoryProvider).add(ml);
      ref.invalidate(waterProvider);
    } catch (_) {
      // ignore; next refresh reflects truth
    }
  }
}
