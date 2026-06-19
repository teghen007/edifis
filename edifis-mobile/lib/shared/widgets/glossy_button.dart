import 'package:flutter/material.dart';
import 'package:edifis/core/theme/app_colors.dart';

class GlossyButton extends StatefulWidget {
  final String label;
  final IconData? icon;
  final VoidCallback onTap;
  const GlossyButton({super.key, required this.label, this.icon, required this.onTap});
  @override
  State<GlossyButton> createState() => _GlossyButtonState();
}

class _GlossyButtonState extends State<GlossyButton> {
  bool _pressed = false;
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapCancel: () => setState(() => _pressed = false),
      onTapUp: (_) { setState(() => _pressed = false); widget.onTap(); },
      child: AnimatedScale(
        scale: _pressed ? 0.96 : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
          decoration: BoxDecoration(
            gradient: AppGradients.button,
            borderRadius: BorderRadius.circular(14),
            boxShadow: [BoxShadow(
              color: AppColors.glow.withValues(alpha: _pressed ? .3 : .6),
              blurRadius: _pressed ? 12 : 24, offset: const Offset(0, 8))],
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            if (widget.icon != null) ...[
              Icon(widget.icon, color: const Color(0xFF06245E), size: 20),
              const SizedBox(width: 8),
            ],
            Text(widget.label, style: const TextStyle(
              color: Color(0xFF06245E), fontWeight: FontWeight.bold, fontSize: 16)),
          ]),
        ),
      ),
    );
  }
}
