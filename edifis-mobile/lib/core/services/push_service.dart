import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:edifis/core/network/dio_client.dart';

class PushService {
  bool _initialised = false;

  Future<void> init(Ref ref) async {
    if (_initialised) return;
    _initialised = true;

    try {
      // TODO: replace with real firebase_options.dart once Firebase project exists
      await Firebase.initializeApp(
        options: const FirebaseOptions(
          apiKey: 'PLACEHOLDER',
          appId: 'PLACEHOLDER',
          messagingSenderId: 'PLACEHOLDER',
          projectId: 'PLACEHOLDER',
        ),
      );
    } catch (_) {
      // Firebase not configured yet — app still works
      _initialised = false;
      return;
    }

    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
    } catch (_) {
      // No permission — continue without
    }

    try {
      final messaging = FirebaseMessaging.instance;
      final token = await messaging.getToken();
      if (token != null) await _register(ref, token);

      messaging.onTokenRefresh.listen((t) => _register(ref, t));

      // Foreground messages: Android shows system notifications automatically
      // when the app is backgrounded/closed. Foreground display can be added
      // later if needed; for now we just keep the stream alive.
      FirebaseMessaging.onMessage.listen((_) {});
    } catch (_) {
      // Silently fail
    }
  }

  Future<void> tryRegisterOnStart(Ref ref) async {
    try {
      // If already initialised via Firebase init, attempt re-register
      final messaging = FirebaseMessaging.instance;
      final token = await messaging.getToken();
      if (token != null) await _register(ref, token);
    } catch (_) {}
  }

  Future<void> _register(Ref ref, String token) async {
    try {
      await ref.read(dioProvider).post('/fcm/register', data: {
        'token': token,
        'device_name': 'flutter-android',
      });
    } catch (_) {}
  }
}

final pushServiceProvider = Provider<PushService>((ref) => PushService());
