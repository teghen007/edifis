// Firebase configuration for EDIFIS (project: edifis-4e3f1).
// Android values from google-services.json. Add other platforms via
// `flutterfire configure` if/when iOS/web builds are needed.
import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart' show defaultTargetPlatform, TargetPlatform;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      default:
        // Other platforms not configured yet; fall back to Android values.
        return android;
    }
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyBPnhchjKlr6w5st_l3qDFH5A3xFxgiAb8',
    appId: '1:1079994292052:android:6348e79ca48f2cf5cc9ac1',
    messagingSenderId: '1079994292052',
    projectId: 'edifis-4e3f1',
    storageBucket: 'edifis-4e3f1.firebasestorage.app',
  );
}
