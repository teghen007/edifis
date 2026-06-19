import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/auth/auth_state.dart';
import '../../core/services/auth_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class ParentOtpScreen extends ConsumerStatefulWidget {
  final String phone;
  const ParentOtpScreen({super.key, required this.phone});
  @override
  ConsumerState<ParentOtpScreen> createState() => _ParentOtpScreenState();
}

class _ParentOtpScreenState extends ConsumerState<ParentOtpScreen> {
  final _code = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() { _code.dispose(); super.dispose(); }

  Future<void> _verify() async {
    final code = _code.text.trim();
    if (code.isEmpty || code.length < 6) {
      setState(() => _error = 'Enter the 6-digit code.'); return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ref.read(authApiProvider).verifyOtp(widget.phone, code);
      await ref.read(authProvider.notifier).setParentSession(
        res['token'] as String, res['device_token'] as String?, widget.phone);
      if (res['must_reset_pin'] == true) {
        if (mounted) context.go('/parent');
      } else {
        if (mounted) context.go('/parent');
      }
    } on DioException catch (e) {
      setState(() => _error = (e.response?.statusCode == 422)
        ? 'Invalid or expired code.' : 'Couldn\'t reach the server.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.hero),
        child: SafeArea(child: Center(child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Image.asset('assets/brand/logo-white.png', height: 76),
            const SizedBox(height: 24),
            GlassCard(child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                const Text('Verification code', style: TextStyle(
                  fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.ink)),
                const SizedBox(height: 4),
                Text('Sent to ${widget.phone}',
                  style: const TextStyle(color: AppColors.muted)),
                const SizedBox(height: 16),
                TextField(controller: _code, keyboardType: TextInputType.number,
                  maxLength: 6,
                  inputFormatters: [LengthLimitingTextInputFormatter(6)],
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontSize: 28, letterSpacing: 8),
                  onSubmitted: (_) => _verify(),
                  decoration: const InputDecoration(counterText: '')),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13)),
                ],
                const SizedBox(height: 18),
                _loading
                  ? const Center(child: Padding(padding: EdgeInsets.all(8),
                      child: CircularProgressIndicator()))
                  : Center(child: GlossyButton(
                      label: 'Verify', icon: Icons.check, onTap: _verify)),
              ])),
          ]),
        ))),
      ),
    );
  }
}
