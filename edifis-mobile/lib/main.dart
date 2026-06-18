import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'edifis_app.dart';

void main() {
  runApp(
    const ProviderScope(
      child: EdifisApp(),
    ),
  );
}
