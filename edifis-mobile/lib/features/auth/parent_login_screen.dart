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

class ParentLoginScreen extends ConsumerStatefulWidget {
  const ParentLoginScreen({super.key});
  @override
  ConsumerState<ParentLoginScreen> createState() => _ParentLoginScreenState();
}

class _ParentLoginScreenState extends ConsumerState<ParentLoginScreen> {
  final _phone = TextEditingController();
  final _pin = TextEditingController();
  bool _loading = false, _obscure = true;
  String? _error;

  @override
  void dispose() { _phone.dispose(); _pin.dispose(); super.dispose(); }

  Future<void> _signIn() async {
    final phone = _phone.text.trim();
    final pin = _pin.text;
    if (phone.isEmpty || pin.isEmpty) {
      setState(() => _error = 'Enter your phone number and PIN'); return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final dt = ref.read(authProvider.notifier).parentDeviceToken(phone);
      final res = await ref.read(authApiProvider).parentLogin(phone, pin, deviceToken: dt);
      if (res['token'] != null) {
        await ref.read(authProvider.notifier).setParentSession(
          res['token'] as String, res['device_token'] as String?, phone);
        if (mounted) context.go('/parent');
      } else if (res['status'] == 'otp_required') {
        if (mounted) context.go('/parent-otp', extra: phone);
      } else {
        setState(() => _error = 'Unexpected response from server.');
      }
    } on DioException catch (e) {
      final code = e.response?.statusCode;
      setState(() => _error = (code == 401 || code == 422)
        ? 'Wrong phone or PIN.' : 'Couldn\'t reach the server.');
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
                const Text('Parent sign-in', style: TextStyle(
                  fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.ink)),
                const SizedBox(height: 16),
                TextField(controller: _phone, keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Phone (e.g. +237...)')),
                const SizedBox(height: 12),
                TextField(controller: _pin, obscureText: _obscure,
                  keyboardType: TextInputType.number,
                  inputFormatters: [LengthLimitingTextInputFormatter(6)],
                  onSubmitted: (_) => _signIn(),
                  decoration: InputDecoration(labelText: 'PIN',
                    suffixIcon: IconButton(
                      icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility),
                      onPressed: () => setState(() => _obscure = !_obscure)))),
                if (_error != null) ...[
                  const SizedBox(height: 10),
                  Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13)),
                ],
                const SizedBox(height: 18),
                _loading
                  ? const Center(child: Padding(padding: EdgeInsets.all(8),
                      child: CircularProgressIndicator()))
                  : Center(child: GlossyButton(
                      label: 'Sign in', icon: Icons.login, onTap: _signIn)),
              ])),
            const SizedBox(height: 16),
            TextButton(onPressed: () => context.go('/login'),
              child: const Text('Staff? Sign in here',
                style: TextStyle(color: AppColors.blue100))),
          ]),
        ))),
      ),
    );
  }
}
