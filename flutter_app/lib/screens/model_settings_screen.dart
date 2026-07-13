import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../repositories/config_repository.dart';
import '../repositories/models_repository.dart';
import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import '../widgets/fade_in.dart';
import '../widgets/form_widgets.dart';
import '../widgets/hero_icon.dart';
import '../widgets/section_card.dart';
import '../widgets/skeleton.dart';

/// Model Settings screen — feature parity with the PHP mobile app's
/// model-settings page. Plain Scaffold + AppBar (not a primary tab); the
/// settings menu pushes `/model-settings`.
///
/// Two concerns are combined per provider (Groq / OpenRouter / Gemini / Ollama):
///  - API key (or Ollama URL) → stored on `config` via settings.php section `api`.
///    Loaded masked (`abcd...wxyz`); only written when the user changes it.
///  - Model list → stored on the `models` collection via models.php: per-model
///    enable toggle (`update`), default selection (`set-default`), add/delete.
class ModelSettingsScreen extends ConsumerStatefulWidget {
  const ModelSettingsScreen({super.key});

  @override
  ConsumerState<ModelSettingsScreen> createState() =>
      _ModelSettingsScreenState();
}

class _ModelSettingsScreenState extends ConsumerState<ModelSettingsScreen> {
  /// Per-provider API-key controllers, seeded once from config (which arrives
  /// masked). Keyed by the config field name (groqApiKey, openrouterApiKey…).
  final _keyControllers = <String, TextEditingController>{};

  /// Track whether each key field was edited so we only POST the changed ones.
  final _dirtyKeys = <String>{};

  bool _keysSeeded = false;
  bool _savingKeys = false;

  @override
  void dispose() {
    for (final c in _keyControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  TextEditingController _keyControllerFor(String field) {
    return _keyControllers.putIfAbsent(field, TextEditingController.new);
  }

  void _seedKeys(Map<String, dynamic> config) {
    if (_keysSeeded) return;
    for (final provider in aiProviders) {
      final field = providerApiKeyField[provider]!;
      final value = (config[field] ?? '').toString();
      final controller = _keyControllerFor(field);
      controller.text = value;
      controller.selection = TextSelection.collapsed(offset: value.length);
    }
    _keysSeeded = true;
  }

  Future<void> _saveKeys() async {
    setState(() => _savingKeys = true);
    final messenger = ScaffoldMessenger.of(context);
    try {
      final keys = <String, String>{};
      for (final field in _dirtyKeys) {
        keys[field] = _keyControllers[field]!.text;
      }
      if (keys.isNotEmpty) {
        await ref.read(configRepositoryProvider).saveApiKeys(keys);
      }
      ref.invalidate(configProvider);
      _dirtyKeys.clear();
      messenger.showSnackBar(const SnackBar(
        content: Text('API keys saved'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to save keys: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    } finally {
      if (mounted) setState(() => _savingKeys = false);
    }
  }

  Future<void> _toggleModel(WidgetRef ref, AiModel model, bool value) async {
    // Optimistically refresh the list after the round-trip.
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(modelsRepositoryProvider).setEnabled(model.id, value);
      ref.invalidate(modelsProvider);
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to update model: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  Future<void> _setDefault(WidgetRef ref, AiModel model) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref.read(modelsRepositoryProvider).setDefault(model.id, model.provider);
      ref.invalidate(modelsProvider);
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to set default: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  Future<void> _addModel(WidgetRef ref, String provider) async {
    final messenger = ScaffoldMessenger.of(context);
    final modelId = await _promptForModelId(provider);
    if (modelId == null || modelId.trim().isEmpty) return;
    try {
      await ref.read(modelsRepositoryProvider).add(
            provider: provider,
            modelId: modelId.trim(),
          );
      ref.invalidate(modelsProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Model added'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to add model: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  Future<void> _deleteModel(WidgetRef ref, AiModel model) async {
    final messenger = ScaffoldMessenger.of(context);
    final ok = await confirmDialog(
      context,
      title: 'Delete model?',
      message: 'Remove "${model.label}" from ${providerLabels[model.provider]}?',
      confirmLabel: 'Delete',
    );
    if (!ok) return;
    try {
      await ref.read(modelsRepositoryProvider).delete(model.id);
      ref.invalidate(modelsProvider);
      messenger.showSnackBar(const SnackBar(
        content: Text('Model deleted'),
        behavior: SnackBarBehavior.floating,
      ));
    } catch (e) {
      messenger.showSnackBar(SnackBar(
        content: Text('Failed to delete: $e'),
        behavior: SnackBarBehavior.floating,
      ));
    }
  }

  /// Simple text dialog for entering a new model id (matches the PHP add flow).
  Future<String?> _promptForModelId(String provider) async {
    final controller = TextEditingController();
    final p = AppPalette.of(context);
    final result = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: p.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.card),
          side: BorderSide(color: p.border),
        ),
        title: Text('Add ${providerLabels[provider]} model',
            style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: p.textPrimary)),
        content: TextField(
          controller: controller,
          autofocus: true,
          textInputAction: TextInputAction.done,
          onSubmitted: (v) => Navigator.pop(ctx, v),
          style: TextStyle(fontSize: 14, color: p.textPrimary),
          decoration: InputDecoration(
            isDense: true,
            hintText: 'model id, e.g. gpt-4o-mini',
            hintStyle: TextStyle(color: p.textFaint, fontSize: 14),
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.border)),
            focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(AppRadii.button),
                borderSide: BorderSide(color: p.ink, width: 1.5)),
          ),
        ),
        actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, null),
            child: Text('CANCEL',
                style: TextStyle(
                    color: p.textMuted,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1)),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, controller.text),
            child: Text('ADD',
                style: TextStyle(
                    color: p.ink,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1)),
          ),
        ],
      ),
    );
    controller.dispose();
    return result;
  }

  @override
  Widget build(BuildContext context) {
    final configAsync = ref.watch(configProvider);
    final modelsAsync = ref.watch(modelsProvider);

    // Seed API-key controllers as soon as config arrives.
    configAsync.whenData(_seedKeys);

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const HeroIcon(HeroPaths.arrowLeft, size: 22),
          onPressed: () => context.pop(),
        ),
        title: const Text('Model Settings'),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.page, 16, AppSpacing.page, 32),
          children: [
            FadeIn(
              child: SectionCard(
                heading: 'Providers',
                child: Text(
                  'Configure API keys and the available models per provider. '
                  'API keys are saved separately from the model list.',
                  style: TextStyle(
                      fontSize: 13, color: AppPalette.of(context).textMuted),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.gap),
            for (var i = 0; i < aiProviders.length; i++)
              FadeIn(
                delay: Duration(milliseconds: (i * 40).clamp(0, 240)),
                child: Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.gap),
                  child: _ProviderSection(
                    provider: aiProviders[i],
                    configLoading: configAsync.isLoading,
                    configError: configAsync.hasError,
                    keyController: _keyControllerFor(
                        providerApiKeyField[aiProviders[i]]!),
                    isOllama: aiProviders[i] == 'ollama',
                    onKeyChanged: () => _dirtyKeys
                        .add(providerApiKeyField[aiProviders[i]]!),
                    modelsAsync: modelsAsync,
                    onToggle: (m, v) => _toggleModel(ref, m, v),
                    onSetDefault: (m) => _setDefault(ref, m),
                    onAdd: () => _addModel(ref, aiProviders[i]),
                    onDelete: (m) => _deleteModel(ref, m),
                  ),
                ),
              ),
          ],
        ),
      ),
      bottomNavigationBar: StickySaveBar(
        onSave: _saveKeys,
        saving: _savingKeys,
        saveLabel: 'Save Keys',
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Per-provider section: API key field + model list (toggle / default / delete).
// -----------------------------------------------------------------------------

class _ProviderSection extends StatelessWidget {
  const _ProviderSection({
    required this.provider,
    required this.configLoading,
    required this.configError,
    required this.keyController,
    required this.isOllama,
    required this.onKeyChanged,
    required this.modelsAsync,
    required this.onToggle,
    required this.onSetDefault,
    required this.onAdd,
    required this.onDelete,
  });

  final String provider;
  final bool configLoading;
  final bool configError;
  final TextEditingController keyController;
  final bool isOllama;
  final VoidCallback onKeyChanged;
  final AsyncValue<Map<String, List<AiModel>>> modelsAsync;
  final void Function(AiModel model, bool value) onToggle;
  final void Function(AiModel model) onSetDefault;
  final VoidCallback onAdd;
  final void Function(AiModel model) onDelete;

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      heading: providerLabels[provider],
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildKeyField(context),
          const SizedBox(height: 20),
          _buildModelList(context),
        ],
      ),
    );
  }

  Widget _buildKeyField(BuildContext context) {
    if (configLoading) {
      return const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Skeleton(width: 80, height: 11),
          SizedBox(height: 8),
          Skeleton(height: 20, radius: 4),
        ],
      );
    }
    if (configError) {
      return Text('Could not load API key.',
          style: TextStyle(
              fontSize: 13, color: AppPalette.of(context).textMuted));
    }
    return LabeledField(
      label: isOllama ? 'Ollama URL' : 'API key',
      controller: keyController,
      obscure: !isOllama,
      keyboardType:
          isOllama ? TextInputType.url : TextInputType.visiblePassword,
      hint: isOllama ? 'http://localhost:11434' : 'sk-…',
      onChanged: (_) => onKeyChanged(),
    );
  }

  Widget _buildModelList(BuildContext context) {
    final p = AppPalette.of(context);
    return modelsAsync.when(
      loading: () => const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Skeleton(width: 60, height: 11),
          SizedBox(height: 10),
          Skeleton(height: 44, radius: 12),
          SizedBox(height: 8),
          Skeleton(height: 44, radius: 12),
        ],
      ),
      error: (e, _) => Text('Could not load models: $e',
          style: TextStyle(fontSize: 13, color: p.textMuted)),
      data: (allModels) {
        final models = allModels[provider] ?? const <AiModel>[];
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('MODELS',
                    style: AppType.label(context, color: p.textMuted, size: 11)),
                GestureDetector(
                  onTap: onAdd,
                  behavior: HitTestBehavior.opaque,
                  child: Row(
                    children: [
                      HeroIcon(HeroPaths.plus, size: 14, color: p.ink),
                      const SizedBox(width: 4),
                      Text('ADD',
                          style: TextStyle(
                              fontFamily: 'Geist',
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 1,
                              color: p.ink)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (models.isEmpty)
              Text('No models configured.',
                  style: TextStyle(fontSize: 13, color: p.textMuted))
            else
              for (final m in models)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _ModelRow(
                    model: m,
                    onToggle: (v) => onToggle(m, v),
                    onSetDefault: () => onSetDefault(m),
                    onDelete: () => onDelete(m),
                  ),
                ),
          ],
        );
      },
    );
  }
}

/// A single model row: default radio + label/id + enable switch + delete.
class _ModelRow extends StatelessWidget {
  const _ModelRow({
    required this.model,
    required this.onToggle,
    required this.onSetDefault,
    required this.onDelete,
  });

  final AiModel model;
  final ValueChanged<bool> onToggle;
  final VoidCallback onSetDefault;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: p.canvas,
        border: Border.all(color: p.border),
        borderRadius: BorderRadius.circular(AppRadii.button),
      ),
      child: Row(
        children: [
          // Default selector (radio dot).
          GestureDetector(
            onTap: model.isDefault ? null : onSetDefault,
            behavior: HitTestBehavior.opaque,
            child: Container(
              width: 18,
              height: 18,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                    color: model.isDefault ? p.ink : p.border, width: 1.5),
              ),
              alignment: Alignment.center,
              child: model.isDefault
                  ? Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                          color: p.ink, shape: BoxShape.circle),
                    )
                  : null,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(model.label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: p.textPrimary)),
                Text(model.modelId,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                        fontSize: 12, color: p.textFaint, fontFamily: 'GeistMono')),
              ],
            ),
          ),
          if (model.isDefault) ...[
            const SizedBox(width: 8),
            Text('DEFAULT',
                style: TextStyle(
                    fontFamily: 'Geist',
                    fontSize: 9,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1,
                    color: p.textMuted)),
          ],
          const SizedBox(width: 8),
          // Enable toggle (custom, border-only — no Material switch colors).
          _EnableToggle(value: model.enabled, onChanged: onToggle),
          IconButton(
            tooltip: 'Delete',
            onPressed: model.isDefault ? null : onDelete,
            icon: HeroIcon(HeroPaths.trash, size: 16, color: p.textMuted),
            visualDensity: VisualDensity.compact,
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints(
                minWidth: AppSpacing.touchTarget, minHeight: AppSpacing.touchTarget),
          ),
        ],
      ),
    );
  }
}

/// Minimal monochrome on/off toggle (ink knob, border track). Avoids the
/// colored Material [Switch] to stay within the monochrome palette.
class _EnableToggle extends StatefulWidget {
  const _EnableToggle({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  State<_EnableToggle> createState() => _EnableToggleState();
}

class _EnableToggleState extends State<_EnableToggle> {
  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    const width = 38.0;
    const height = 22.0;
    return GestureDetector(
      onTap: () => widget.onChanged(!widget.value),
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: width,
        height: height,
        padding: const EdgeInsets.all(2),
        decoration: BoxDecoration(
          color: widget.value ? p.ink : Colors.transparent,
          border: Border.all(color: widget.value ? p.ink : p.border),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        alignment:
            widget.value ? Alignment.centerRight : Alignment.centerLeft,
        child: Container(
          width: height - 4,
          height: height - 4,
          decoration: BoxDecoration(
            color: widget.value ? p.onInk : p.textMuted,
            shape: BoxShape.circle,
          ),
        ),
      ),
    );
  }
}
