import 'package:flutter/material.dart';

/// Central typography. Arial everywhere — the single source of the font family
/// so no component ever hard-codes a typeface. See docs/DESIGN_SYSTEM.md §3.
class AppTypography {
  AppTypography._();

  static const String fontFamily = 'Arial';

  static const display = TextStyle(fontFamily: fontFamily, fontSize: 28, fontWeight: FontWeight.bold);
  static const h1 = TextStyle(fontFamily: fontFamily, fontSize: 22, fontWeight: FontWeight.bold);
  static const h2 = TextStyle(fontFamily: fontFamily, fontSize: 18, fontWeight: FontWeight.bold);
  static const title = TextStyle(fontFamily: fontFamily, fontSize: 16, fontWeight: FontWeight.w600);
  static const body = TextStyle(fontFamily: fontFamily, fontSize: 15, fontWeight: FontWeight.normal);
  static const label = TextStyle(fontFamily: fontFamily, fontSize: 13, fontWeight: FontWeight.w500);
  static const caption = TextStyle(fontFamily: fontFamily, fontSize: 12, fontWeight: FontWeight.normal);

  /// Full TextTheme with Arial applied to every role.
  static TextTheme textTheme(Color color) {
    TextStyle c(TextStyle s) => s.copyWith(color: color);
    return TextTheme(
      displayLarge: c(display),
      headlineLarge: c(h1),
      headlineMedium: c(h2),
      titleMedium: c(title),
      bodyLarge: c(body),
      bodyMedium: c(body),
      labelLarge: c(label),
      bodySmall: c(caption),
    );
  }
}
