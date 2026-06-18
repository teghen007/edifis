import 'package:flutter/material.dart';
import 'shared/routing/app_router.dart';

class EdifisApp extends StatelessWidget {
  const EdifisApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: 'EDIFIS',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorSchemeSeed: const Color(0xFF1B5E20),
        useMaterial3: true,
      ),
      routerConfig: appRouter,
    );
  }
}
