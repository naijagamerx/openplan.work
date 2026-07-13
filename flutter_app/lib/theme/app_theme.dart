import 'package:flutter/material.dart';

import 'app_tokens.dart';

/// Monochrome Material 3 theme. Single ink accent + zinc neutrals, Geist type,
/// border-defined cards (no shadow). Mirrors the web mobile UI.
class AppTheme {
  AppTheme._();

  static ThemeData light() => _build(Brightness.light, AppPalette.light);
  static ThemeData dark() => _build(Brightness.dark, AppPalette.dark);

  static ThemeData _build(Brightness brightness, AppPalette p) {
    final scheme = ColorScheme.fromSeed(
      seedColor: p.ink,
      brightness: brightness,
    ).copyWith(
      primary: p.ink,
      onPrimary: p.onInk,
      surface: p.surface,
      onSurface: p.textPrimary,
      outline: p.border,
      error: const Color(0xFFDC2626),
      onError: Colors.white,
    );

    final baseText =
        (brightness == Brightness.dark ? ThemeData.dark() : ThemeData.light())
            .textTheme;
    final textTheme = baseText.apply(
      fontFamily: 'Geist',
      bodyColor: p.textPrimary,
      displayColor: p.textPrimary,
    );

    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadii.button),
          borderSide: BorderSide(color: c, width: w),
        );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: p.canvas,
      textTheme: textTheme,
      dividerColor: p.border,
      extensions: [p],
      appBarTheme: AppBarTheme(
        backgroundColor: p.canvas,
        foregroundColor: p.textPrimary,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(fontFamily: 'Geist',
          fontSize: 18,
          fontWeight: FontWeight.w700,
          color: p.textPrimary,
        ),
      ),
      cardTheme: CardThemeData(
        color: p.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadii.section),
          side: BorderSide(color: p.border),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: p.ink,
          foregroundColor: p.onInk,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          textStyle: const TextStyle(fontFamily: 'Geist', fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: p.textPrimary,
          side: BorderSide(color: p.ink),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.button),
          ),
          textStyle: const TextStyle(fontFamily: 'Geist', fontWeight: FontWeight.w700),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: false,
        border: border(p.border),
        enabledBorder: border(p.border),
        focusedBorder: border(p.ink, 1.5),
        labelStyle: TextStyle(color: p.textMuted),
        helperStyle: TextStyle(color: p.textFaint),
      ),
    );
  }
}

/// Shared text helpers for the monochrome system.
class AppType {
  AppType._();

  /// Monospace for numbers / stats / timers (Geist Mono).
  static TextStyle mono({
    double size = 30,
    FontWeight weight = FontWeight.w700,
    Color? color,
  }) =>
      TextStyle(
          fontFamily: 'GeistMono',
          fontSize: size,
          fontWeight: weight,
          color: color);

  /// Uppercase tracked micro-label (the design's signature).
  static TextStyle label(BuildContext context, {Color? color, double size = 10}) =>
      TextStyle(fontFamily: 'Geist',
        fontSize: size,
        fontWeight: FontWeight.w700,
        letterSpacing: 1.5,
        color: color ?? AppPalette.of(context).textFaint,
      );
}
