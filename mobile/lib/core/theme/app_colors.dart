import 'package:flutter/material.dart';

/// NISconnect colour system. Professional green brand; clean neutral surfaces.
/// Not a Telegram palette, not a generic AI palette. See docs/DESIGN_SYSTEM.md.
class AppColors {
  AppColors._();

  // Brand greens
  static const primaryGreen = Color(0xFF0B6B3A);
  static const darkGreen = Color(0xFF064A28);
  static const secondaryGreen = Color(0xFF2E9E63);
  static const lightGreen = Color(0xFFE6F4EC);
  static const gold = Color(0xFFC9A227); // sparing official accent

  // Neutrals (light)
  static const white = Color(0xFFFFFFFF);
  static const offWhite = Color(0xFFF5F7F5);
  static const neutralGrey = Color(0xFF6B7770);
  static const darkText = Color(0xFF14201A);
  static const borderGrey = Color(0xFFE2E7E3);

  // Status
  static const error = Color(0xFFC62828);
  static const warning = Color(0xFFE08600);
  static const success = Color(0xFF2E7D32);

  // Dark theme
  static const dPrimaryGreen = Color(0xFF1B8A54);
  static const dSecondaryGreen = Color(0xFF3FB574);
  static const dLightGreen = Color(0xFF123524);
  static const dSurface = Color(0xFF0E1512);
  static const dBackground = Color(0xFF131A16);
  static const dText = Color(0xFFEAF1EC);
  static const dNeutralGrey = Color(0xFF8A968F);
  static const dBorder = Color(0xFF26302B);
}
