import 'package:flutter/material.dart';

class RolePlaceholderScreen extends StatelessWidget {
  final String roleName;

  const RolePlaceholderScreen({super.key, required this.roleName});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('EDIFIS — $roleName'),
      ),
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.school, size: 64, color: Colors.green),
            const SizedBox(height: 16),
            Text(
              roleName,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 8),
            Text(
              'Placeholder screen — implementation coming in Phase 1+',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}
