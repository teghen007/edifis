import 'package:flutter/material.dart';
import 'app_colors.dart';

class AppTheme {
  AppTheme._();
  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.blue600,
      brightness: Brightness.light,
    ).copyWith(primary: AppColors.blue600, surface: AppColors.surface);
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.bg,
      appBarTheme: const AppBarTheme(
          backgroundColor: Colors.transparent,
          foregroundColor: Colors.white,
          elevation: 0,
          centerTitle: false),
      cardTheme: CardThemeData(
          color: AppColors.surface,
          elevation: 0,
          shadowColor: AppColors.navy.withValues(alpha: .25),
          shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
              side: const BorderSide(color: AppColors.border))),
      filledButtonTheme: FilledButtonThemeData(
          style: FilledButton.styleFrom(
              backgroundColor: AppColors.blue600,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
              textStyle:
                  const TextStyle(fontWeight: FontWeight.w600, fontSize: 16),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14)))),
      inputDecorationTheme: InputDecorationTheme(
          filled: true,
          fillColor: Colors.white,
          enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide: const BorderSide(color: AppColors.border)),
          focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12),
              borderSide:
                  const BorderSide(color: AppColors.blue600, width: 2))),
    );
  }
}
