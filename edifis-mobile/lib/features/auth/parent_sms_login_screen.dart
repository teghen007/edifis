import 'package:dio/dio.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/auth/auth_state.dart';
import '../../core/services/auth_api.dart';
import '../../core/theme/app_colors.dart';
import '../../shared/widgets/glass_card.dart';
import '../../shared/widgets/glossy_button.dart';

/// Parent login by SMS code (Firebase Phone Auth) — for parents without email.
class ParentSmsLoginScreen extends ConsumerStatefulWidget {
  const ParentSmsLoginScreen({super.key});
  @override
  ConsumerState<ParentSmsLoginScreen> createState() => _ParentSmsLoginScreenState();
}

class _ParentSmsLoginScreenState extends ConsumerState<ParentSmsLoginScreen> {
  final _phone = TextEditingController();
  final _code = TextEditingController();
  String? _verificationId;
  bool _loading = false, _codeSent = false;
  String? _error;

  @override
  void dispose() { _phone.dispose(); _code.dispose(); super.dispose(); }

  String _normalise(String raw) {
    var p = raw.trim().replaceAll(' ', '');
    if (p.startsWith('00')) p = '+${p.substring(2)}';
    if (!p.startsWith('+')) p = '+237$p'; // default to Cameroon
    return p;
  }

  Future<void> _sendCode() async {
    final phone = _normalise(_phone.text);
    if (phone.length < 8) { setState(() => _error = 'Enter a valid phone number'); return; }
    setState(() { _loading = true; _error = null; });
    try {
      await FirebaseAuth.instance.verifyPhoneNumber(
        phoneNumber: phone,
        timeout: const Duration(seconds: 60),
        verificationCompleted: (cred) async => _exchange(cred),
        verificationFailed: (e) => setState(() { _loading = false; _error = e.message ?? 'Verification failed.'; }),
        codeSent: (id, _) => setState(() { _loading = false; _codeSent = true; _verificationId = id; }),
        codeAutoRetrievalTimeout: (id) => _verificationId = id,
      );
    } catch (e) {
      setState(() { _loading = false; _error = 'Could not send the code. Try again.'; });
    }
  }

  Future<void> _verifyCode() async {
    if (_verificationId == null || _code.text.trim().length < 6) {
      setState(() => _error = 'Enter the 6-digit code'); return;
    }
    setState(() { _loading = true; _error = null; });
    final cred = PhoneAuthProvider.credential(
      verificationId: _verificationId!, smsCode: _code.text.trim());
    await _exchange(cred);
  }

  Future<void> _exchange(PhoneAuthCredential cred) async {
    try {
      final userCred = await FirebaseAuth.instance.signInWithCredential(cred);
      final idToken = await userCred.user?.getIdToken();
      if (idToken == null) throw Exception('no token');
      final res = await ref.read(authApiProvider).parentFirebaseLogin(idToken);
      final phone = userCred.user?.phoneNumber ?? '';
      if (res['token'] != null) {
        await ref.read(authProvider.notifier).setParentSession(res['token'] as String, null, phone);
        if (mounted) context.go('/parent');
      } else {
        setState(() { _loading = false; _error = 'Login failed. Please try again.'; });
      }
    } on DioException catch (e) {
      setState(() { _loading = false; _error = e.response?.statusCode == 404
        ? 'No parent account for this number. Contact the school.'
        : 'Login failed. Try again.'; });
    } catch (_) {
      setState(() { _loading = false; _error = 'Wrong or expired code. Try again.'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: Colors.transparent, foregroundColor: Colors.white, elevation: 0),
      extendBodyBehindAppBar: true,
      body: Container(
        decoration: const BoxDecoration(gradient: AppGradients.hero),
        child: Center(child: SingleChildScrollView(padding: const EdgeInsets.all(24),
          child: GlassCard(child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Text('Login with SMS', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: AppColors.ink)),
            const SizedBox(height: 4),
            const Text('We\'ll text a code to your phone.', style: TextStyle(color: AppColors.muted, fontSize: 13)),
            const SizedBox(height: 20),
            if (!_codeSent) ...[
              TextField(controller: _phone, keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Phone number', hintText: '+237 6XX XXX XXX')),
            ] else ...[
              Text('Code sent to ${_normalise(_phone.text)}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
              const SizedBox(height: 8),
              TextField(controller: _code, keyboardType: TextInputType.number, maxLength: 6,
                decoration: const InputDecoration(labelText: '6-digit code')),
            ],
            if (_error != null) ...[const SizedBox(height: 8),
              Text(_error!, style: const TextStyle(color: AppColors.danger, fontSize: 13))],
            const SizedBox(height: 16),
            _loading
              ? const Center(child: CircularProgressIndicator())
              : GlossyButton(
                  label: _codeSent ? 'Verify & continue' : 'Send code',
                  icon: Icons.sms,
                  onTap: _codeSent ? _verifyCode : _sendCode),
            if (_codeSent) ...[const SizedBox(height: 8),
              TextButton(onPressed: () => setState(() { _codeSent = false; _code.clear(); }),
                child: const Text('Change number'))],
          ])),
        )),
      ),
    );
  }
}
