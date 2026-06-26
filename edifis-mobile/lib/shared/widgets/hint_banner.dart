import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import '../../core/theme/app_colors.dart';

/// A small "what this screen is for" hint banner.
class HintBanner extends StatelessWidget {
  final String text;
  const HintBanner(this.text, {super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.blue50,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.blue100),
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Icon(LucideIcons.info, size: 17, color: AppColors.blue600),
        const SizedBox(width: 10),
        Expanded(child: Text(text,
          style: const TextStyle(fontSize: 12.5, color: AppColors.blue800, height: 1.4))),
      ]),
    );
  }
}
