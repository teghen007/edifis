import 'dart:async';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/config/school_config.dart';
import 'firebase_options.dart';
import 'edifis_app.dart';

Future<void> main() async {
  // Any uncaught build/render error shows a readable screen instead of a grey box.
  ErrorWidget.builder = (FlutterErrorDetails details) =>
      _ErrorScreen(details.exceptionAsString());

  runZonedGuarded(() async {
    WidgetsFlutterBinding.ensureInitialized();

    // Fail-soft: the app must still run if Firebase isn't configured / no Play Services.
    try {
      await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    } catch (_) {}

    try {
      final prefs = await SharedPreferences.getInstance();
      runApp(
        ProviderScope(
          overrides: [sharedPrefsProvider.overrideWithValue(prefs)],
          child: const EdifisApp(),
        ),
      );
    } catch (e, st) {
      runApp(_ErrorApp('$e\n\n$st'));
    }
  }, (error, stack) {
    // Async / startup errors that escape everything else.
    runApp(_ErrorApp('$error\n\n$stack'));
  });
}

class _ErrorApp extends StatelessWidget {
  const _ErrorApp(this.message);
  final String message;

  @override
  Widget build(BuildContext context) => MaterialApp(
        debugShowCheckedModeBanner: false,
        home: _ErrorScreen(message),
      );
}

class _ErrorScreen extends StatelessWidget {
  const _ErrorScreen(this.message);
  final String message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0B1220),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 8),
              const Text('EDIFIS could not start',
                  style: TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.bold)),
              const SizedBox(height: 6),
              const Text('Please screenshot this screen and send it to support.',
                  style: TextStyle(color: Color(0xFFBFD7FE), fontSize: 13)),
              const SizedBox(height: 16),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.35),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: SingleChildScrollView(
                    child: SelectableText(
                      message,
                      style: const TextStyle(
                          color: Color(0xFFFCA5A5),
                          fontFamily: 'monospace',
                          fontSize: 12),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () =>
                    Clipboard.setData(ClipboardData(text: message)),
                icon: const Icon(Icons.copy),
                label: const Text('Copy error'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
