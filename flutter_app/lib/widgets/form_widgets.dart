import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../theme/app_tokens.dart';
import 'hero_icon.dart';

/// Reusable form primitives matching the PHP app's form language:
/// underlined inputs with uppercase micro-labels, chip selectors, sticky save
/// bars, and confirm dialogs. Every CRUD screen composes these (DESIGN.md §3-5).

/// A labeled, underlined text field (the login-screen pattern, generalized).
class LabeledField extends StatelessWidget {
  const LabeledField({
    super.key,
    required this.label,
    required this.controller,
    this.obscure = false,
    this.keyboardType = TextInputType.text,
    this.maxLines = 1,
    this.hint,
    this.suffix,
    this.onChanged,
    this.textInputAction,
  });

  final String label;
  final TextEditingController controller;
  final bool obscure;
  final TextInputType keyboardType;
  final int maxLines;
  final String? hint;
  final Widget? suffix;
  final ValueChanged<String>? onChanged;
  final TextInputAction? textInputAction;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 4),
        TextField(
          controller: controller,
          obscureText: obscure,
          keyboardType: keyboardType,
          maxLines: maxLines,
          textInputAction: textInputAction,
          onChanged: onChanged,
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w500,
            color: p.textPrimary,
          ),
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
            suffixIcon: suffix,
          ),
        ),
      ],
    );
  }
}

/// A selectable chip group (status, priority, type). Single-select.
class ChipSelector<T> extends StatelessWidget {
  const ChipSelector({
    super.key,
    required this.label,
    required this.options,
    required this.selected,
    required this.onSelected,
    this.colorFor,
  });

  final String label;
  final Map<T, String> options; // value → display label
  final T selected;
  final ValueChanged<T> onSelected;
  final Color Function(T)? colorFor; // optional semantic tint (e.g. priority)

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final entry in options.entries)
              _Chip(
                label: entry.value,
                selected: entry.key == selected,
                color: colorFor?.call(entry.key),
                onTap: () => onSelected(entry.key),
              ),
          ],
        ),
      ],
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.color,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Color? color; // semantic override; ignored when not selected

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    final fill = selected ? (color ?? p.ink) : Colors.transparent;
    final fg = selected
        ? (color != null ? Colors.white : p.onInk)
        : p.textPrimary;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 120),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: fill,
          border: Border.all(
            color: selected ? (color ?? p.ink) : p.border,
          ),
          borderRadius: BorderRadius.circular(AppRadii.pill),
        ),
        child: Text(
          label,
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

/// A bordered dropdown selector with an uppercase label.
class LabeledDropdown<T> extends StatelessWidget {
  const LabeledDropdown({
    super.key,
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
    this.hint,
  });

  final String label;
  final T? value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;
  final String? hint;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label.toUpperCase(),
            style: AppType.label(context, color: p.textMuted, size: 11)),
        const SizedBox(height: 4),
        DropdownButtonFormField<T>(
          initialValue: value,
          items: items,
          onChanged: onChanged,
          icon: HeroIcon(HeroPaths.chevronDown, size: 18, color: p.textMuted),
          isExpanded: true,
          decoration: InputDecoration(
            isDense: true,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            border: UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
            enabledBorder:
                UnderlineInputBorder(borderSide: BorderSide(color: p.border)),
            focusedBorder: UnderlineInputBorder(
                borderSide: BorderSide(color: p.ink, width: 1.5)),
            hintText: hint,
            hintStyle: TextStyle(color: p.textFaint, fontSize: 15),
          ),
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w500,
            color: p.textPrimary,
          ),
          dropdownColor: p.surface,
        ),
      ],
    );
  }
}

/// Sticky bottom save/cancel bar — mirrors the PHP forms' sticky footer.
class StickySaveBar extends StatelessWidget {
  const StickySaveBar({
    super.key,
    required this.onSave,
    this.onCancel,
    this.saveLabel = 'Save',
    this.cancelLabel = 'Cancel',
    this.saving = false,
  });

  final VoidCallback onSave;
  final VoidCallback? onCancel;
  final String saveLabel;
  final String cancelLabel;
  final bool saving;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return SafeArea(
      top: false,
      child: Container(
        decoration: BoxDecoration(
          color: p.surface,
          border: Border(top: BorderSide(color: p.border)),
        ),
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.page, 12, AppSpacing.page, 12),
        child: Row(
          children: [
            if (onCancel != null)
              Expanded(
                child: _BarButton(
                  label: cancelLabel,
                  primary: false,
                  onTap: onCancel,
                ),
              ),
            if (onCancel != null) const SizedBox(width: 12),
            Expanded(
              child: _BarButton(
                label: saving ? 'Saving…' : saveLabel,
                primary: true,
                onTap: saving ? null : onSave,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _BarButton extends StatelessWidget {
  const _BarButton({
    required this.label,
    required this.primary,
    this.onTap,
  });
  final String label;
  final bool primary;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final p = AppPalette.of(context);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Opacity(
        opacity: onTap == null ? 0.6 : 1,
        child: Container(
          constraints: const BoxConstraints(minHeight: AppSpacing.touchTarget),
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: primary ? p.ink : Colors.transparent,
            border: primary ? null : Border.all(color: p.border),
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          child: Text(
            label.toUpperCase(),
            style: TextStyle(
              fontFamily: 'Geist',
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 1,
              color: primary ? p.onInk : p.textPrimary,
            ),
          ),
        ),
      ),
    );
  }
}

/// A destructive-confirm dialog matching the web's `confirmAction()`.
Future<bool> confirmDialog(
  BuildContext context, {
  required String title,
  required String message,
  String confirmLabel = 'Delete',
  bool destructive = true,
}) async {
  final p = AppPalette.of(context);
  final result = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      backgroundColor: p.surface,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: p.border),
      ),
      title: Text(title,
          style: TextStyle(
              fontSize: 16, fontWeight: FontWeight.w700, color: p.textPrimary)),
      content: Text(message,
          style: TextStyle(fontSize: 14, color: p.textMuted)),
      actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: Text('CANCEL',
              style: TextStyle(
                  color: p.textMuted,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
        TextButton(
          onPressed: () => Navigator.pop(ctx, true),
          child: Text(confirmLabel.toUpperCase(),
              style: TextStyle(
                  color: destructive ? const Color(0xFFDC2626) : p.ink,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1)),
        ),
      ],
    ),
  );
  return result ?? false;
}
