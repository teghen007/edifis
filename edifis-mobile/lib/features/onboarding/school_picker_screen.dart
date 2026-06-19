import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:flutter_animate/flutter_animate.dart';
import '../../core/config/school_config.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class SchoolPickerScreen extends ConsumerStatefulWidget {
  const SchoolPickerScreen({super.key});
  @override
  ConsumerState<SchoolPickerScreen> createState() => _SchoolPickerScreenState();
}

class _SchoolPickerScreenState extends ConsumerState<SchoolPickerScreen> {
  final _controller = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() { _controller.dispose(); super.dispose(); }

  Future<void> _continue() async {
    final code = _controller.text.trim().toLowerCase();
    if (code.isEmpty) { setState(() => _error = 'Enter your school code'); return; }
    setState(() { _loading = true; _error = null; });
    try {
      final domain = '$code.myedifis.com';
      final resp = await Dio().get(
        '${schoolBaseUrl(code)}/api/tenancy/domain-allowed',
        queryParameters: {'domain': domain},
        options: Options(validateStatus: (s) => true),
      );
      if (resp.statusCode == 200) {
        await ref.read(schoolProvider.notifier).setSchool(code);
        if (mounted) context.go('/login');
      } else {
        setState(() => _error = 'School "$code" not found. Check the code with your school.');
      }
    } on DioException {
      setState(() => _error = 'Couldn\'t reach the server. Check your internet and try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.hero),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Image.asset('assets/brand/logo-white.png', height: 84).animate().fadeIn(duration: 500.ms),
                  const SizedBox(height: 10),
                  const Text('GOD · KNOWLEDGE · GROWTH',
                    style: TextStyle(color: AppColors.blue200, fontSize: 12,
                      letterSpacing: 3, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 28),
                  GlassCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text('Welcome', style: TextStyle(
                          fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.ink)),
                        const SizedBox(height: 4),
                        const Text('Enter your school code to get started.',
                          style: TextStyle(color: AppColors.muted)),
                        const SizedBox(height: 18),
                        TextField(
                          controller: _controller,
                          autocorrect: false,
                          textInputAction: TextInputAction.go,
                          onSubmitted: (_) => _continue(),
                          decoration: const InputDecoration(
                            hintText: 'e.g. pssnkwen',
                            suffixText: '.myedifis.com',
                          ),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 10),
                          Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13)),
                        ],
                        const SizedBox(height: 18),
                        _loading
                          ? const Center(child: Padding(
                              padding: EdgeInsets.all(8), child: CircularProgressIndicator()))
                          : Center(child: GlossyButton(
                              label: 'Continue', icon: Icons.arrow_forward, onTap: _continue)),
                      ],
                    ),
                  ).animate().fadeIn(duration: 400.ms, delay: 150.ms)
                      .slideY(begin: .1, end: 0, curve: Curves.easeOut),
                  const SizedBox(height: 16),
                  const Text('Ask your school for its myEDIFIS code.',
                    style: TextStyle(color: AppColors.blue200, fontSize: 12)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
