import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/config/school_config.dart';
import 'firebase_options.dart';
import 'edifis_app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Fail-soft: the app must still run if Firebase isn't configured.
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  } catch (_) {}
  final prefs = await SharedPreferences.getInstance();
  runApp(
    ProviderScope(
      overrides: [sharedPrefsProvider.overrideWithValue(prefs)],
      child: const EdifisApp(),
    ),
  );
}
