import 'package:flutter/material.dart';

import 'app_colors.dart';
import 'app_typography.dart';

/// Light and dark themes for NISconnect. Both use Arial throughout.
class AppTheme {
  AppTheme._();

  static ThemeData get light {
    const scheme = ColorScheme.light(
      primary: AppColors.primaryGreen,
      secondary: AppColors.secondaryGreen,
      surface: AppColors.white,
      error: AppColors.error,
      onPrimary: AppColors.white,
      onSurface: AppColors.darkText,
    );
    return _base(scheme, AppColors.offWhite, AppColors.darkText, AppColors.borderGrey);
  }

  static ThemeData get dark {
    const scheme = ColorScheme.dark(
      primary: AppColors.dPrimaryGreen,
      secondary: AppColors.dSecondaryGreen,
      surface: AppColors.dSurface,
      error: AppColors.error,
      onPrimary: AppColors.white,
      onSurface: AppColors.dText,
    );
    return _base(scheme, AppColors.dBackground, AppColors.dText, AppColors.dBorder);
  }

  static ThemeData _base(ColorScheme scheme, Color bg, Color text, Color border) {
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: bg,
      fontFamily: AppTypography.fontFamily,
      textTheme: AppTypography.textTheme(text),
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: text,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: AppTypography.h2.copyWith(color: text),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: scheme.primary, width: 2),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: scheme.primary,
          foregroundColor: scheme.onPrimary,
          minimumSize: const Size.fromHeight(48),
          textStyle: AppTypography.label,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      dividerColor: border,
    );
  }
}
