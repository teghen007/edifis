import 'package:flutter/material.dart';

/// EDIFIS "Wisdom Blue" — mirrors edifis-brand/DESIGN-TOKENS.md
class AppColors {
  AppColors._();
  static const blue50  = Color(0xFFEFF5FF);
  static const blue100 = Color(0xFFDBE8FE);
  static const blue200 = Color(0xFFBFD7FE);
  static const blue300 = Color(0xFF93BBFD);
  static const blue400 = Color(0xFF6098FA);
  static const blue500 = Color(0xFF3B76F6);
  static const blue600 = Color(0xFF2563EB); // PRIMARY
  static const blue700 = Color(0xFF1D4ED8);
  static const blue800 = Color(0xFF1E40AF);
  static const blue900 = Color(0xFF1E3A8A);
  static const navy    = Color(0xFF0F2350); // blue-950
  static const glow    = Color(0xFF38BDF8);
  static const ink     = Color(0xFF0B1220);
  static const body    = Color(0xFF334155);
  static const muted   = Color(0xFF64748B);
  static const surface = Color(0xFFFFFFFF);
  static const bg      = Color(0xFFF4F7FE);
  static const border  = Color(0xFFE2E8F0);
  static const success = Color(0xFF16A34A);
  static const warning = Color(0xFFF59E0B);
  static const danger  = Color(0xFFDC2626);
  static const gold    = Color(0xFFC9A227); // premium accent (logo)
}

class AppGradients {
  AppGradients._();
  static const hero = LinearGradient(
    begin: Alignment.topLeft, end: Alignment.bottomRight,
    colors: [AppColors.navy, AppColors.blue800, AppColors.blue600], stops: [0, .46, 1]);
  static const button = LinearGradient(
    begin: Alignment.topCenter, end: Alignment.bottomCenter,
    colors: [Color(0xFFBFE6FF), AppColors.glow]);
}
