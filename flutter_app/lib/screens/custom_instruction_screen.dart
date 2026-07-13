import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/config_repository.dart';
import '../theme/app_tokens.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// AI Custom Instruction screen — feature parity with the PHP mobile app's
/// custom-instruction page. Plain Scaffold + AppBar (not a primary tab); the
/// settings menu pushes `/custom-instruction`.
///
/// The textarea is bound to `config.customInstructions` and persists via
/// `POST /api/settings.php?action=save` (section `customInstructions`), which
/// also stamps a `customInstructionsUpdatedAt` timestamp server-side.
class CustomInstructionScreen extends ConsumerStatefulWidget {
  const CustomInstructionScreen({super.key});

  @override
  ConsumerState<CustomInstructionScreen> createState() =>
      _CustomInstructionScreenState();
}

class _CustomInstructionScreenState extends ConsumerState<CustomInstructionScreen> {
  final _controller = TextEditingController();
  bool _loaded = false; // seed the field once the config arrives
  bool _saving = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _seed(Map<String, dynamic> config) {
    if (_loaded) return;
    final value = (config['customInstructions'] ?? '').toString();
    _controller.text = value;
    _controller.selection =
        TextSelection.collapsed(offset: value.length);
    _loaded = true;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(configRepositoryProvider)
          .saveCustomInstructions(_controller.text.trim());
      ref.invalidate(configProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Custom instructions saved'),
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
    final configAsync = ref.watch(configProvider);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Custom Instruction'),
      ),
      body: SafeArea(
        child: configAsync.when(
          loading: () => const Padding(
            padding: EdgeInsets.all(AppSpacing.page),
            child: _Loading(),
          ),
          error: (e, _) => _ErrorState(message: 'Failed to load: $e'),
          data: (config) {
            _seed(config);
            return ListView(
              padding: const EdgeInsets.fromLTRB(
                  AppSpacing.page, 16, AppSpacing.page, 100),
              children: [
                FadeIn(
                  child: SectionCard(
                    heading: 'AI Behavior',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'This system prompt is prepended to your AI '
                          'conversations, shaping how the assistant responds.',
                          style: TextStyle(
                              fontSize: 13, color: AppPalette.of(context).textMuted),
                        ),
                        const SizedBox(height: 16),
                        LabeledField(
                          label: 'Custom instructions',
                          controller: _controller,
                          maxLines: 12,
                          hint:
                              'Example: Prioritize security checks before feature work.',
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: AppSpacing.gap),
                const FadeIn(
                  delay: Duration(milliseconds: 60),
                  child: SectionCard(
                    heading: 'Notes',
                    child: _NotesList(),
                  ),
                ),
              ],
            );
          },
        ),
      ),
      bottomNavigationBar: StickySaveBar(
        onSave: _save,
        saving: _saving,
      ),
    );
  }
}

/// Static guidance bullets mirroring the PHP page's "Notes" section.
class _NotesList extends StatelessWidget {
  const _NotesList();

  static const _items = <String>[
    'Keep instructions short and actionable.',
    'Avoid putting secrets in this field.',
    'Use this as your quick-edit source for AI behavior text.',
  ];

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final item in _items) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Container(
                  width: 4,
                  height: 4,
                  decoration:
                      BoxDecoration(color: p.textMuted, shape: BoxShape.circle),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  item,
                  style: TextStyle(fontSize: 13, color: p.textMuted),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _Loading extends StatelessWidget {
  const _Loading();

  @override
  Widget build(BuildContext context) {
    return const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Skeleton(width: 120, height: 12),
        SizedBox(height: 16),
        Skeleton(height: 220, radius: 16),
      ],
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.page),
        child: Text(
          message,
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 14, color: p.textMuted),
        ),
      ),
    );
  }
}
