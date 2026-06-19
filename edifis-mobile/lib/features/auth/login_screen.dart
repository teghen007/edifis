import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/auth/auth_state.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _id = TextEditingController();
  final _pw = TextEditingController();
  bool _loading = false, _obscure = true;
  String? _error;

  @override
  void dispose() { _id.dispose(); _pw.dispose(); super.dispose(); }

  Future<void> _signIn() async {
    if (_id.text.trim().isEmpty || _pw.text.isEmpty) {
      setState(() => _error = 'Enter your email and password'); return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      await ref.read(authProvider.notifier).login(_id.text.trim(), _pw.text);
      final role = ref.read(authProvider)!.role;
      if (mounted) context.go('/${role.jsonValue}');
    } on DioException catch (e) {
      final code = e.response?.statusCode;
      setState(() => _error = (code == 401 || code == 422)
        ? 'Wrong email or password.'
        : 'Couldn\'t reach the server. Check your internet.');
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
                const Text('Staff sign-in', style: TextStyle(
                  fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.ink)),
                const SizedBox(height: 16),
                TextField(controller: _id, keyboardType: TextInputType.emailAddress,
                  autocorrect: false,
                  decoration: const InputDecoration(labelText: 'Email')),
                const SizedBox(height: 12),
                TextField(controller: _pw, obscureText: _obscure,
                  onSubmitted: (_) => _signIn(),
                  decoration: InputDecoration(labelText: 'Password',
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
            const Text('Parent sign-in is coming in the next update.',
              style: TextStyle(color: AppColors.blue200, fontSize: 12)),
          ]),
        ))),
      ),
    );
  }
}
