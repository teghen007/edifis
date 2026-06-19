import 'package:flutter/material.dart';
import 'package:edifis/core/theme/app_colors.dart';

class GlassCard extends StatelessWidget {
  final Widget child;
  final EdgeInsets padding;

  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(20),
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border, width: 1),
        boxShadow: [
          BoxShadow(
            color: AppColors.navy.withValues(alpha: .18),
            blurRadius: 30,
            offset: const Offset(0, 16),
          ),
        ],
      ),
      child: child,
    );
  }
}
