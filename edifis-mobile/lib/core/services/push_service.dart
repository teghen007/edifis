import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
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

      await _initLocalNotifications();
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

  Future<void> _initLocalNotifications() async {
    try {
      const android = AndroidInitializationSettings('@mipmap/ic_launcher');
      await FlutterLocalNotificationsPlugin()
          .initialize(const InitializationSettings(android: android));

      FirebaseMessaging.onMessage.listen((msg) {
        final notif = msg.notification;
        if (notif == null) return;
        FlutterLocalNotificationsPlugin().show(
          notif.hashCode,
          notif.title,
          notif.body,
          const NotificationDetails(
            android: AndroidNotificationDetails(
              'edifis_push',
              'EDIFIS Notifications',
              importance: Importance.high,
            ),
          ),
        );
      });
    } catch (_) {}
  }
}

final pushServiceProvider = Provider<PushService>((ref) => PushService());
