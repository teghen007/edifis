import 'package:flutter/material.dart';

class StaffHomeScreen extends StatelessWidget {
  final String roleName;

  const StaffHomeScreen({super.key, required this.roleName});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('EDIFIS — $roleName')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.school, size: 64, color: Colors.green),
            const SizedBox(height: 16),
            Text(roleName, style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 8),
            const Text('Staff dashboard — online mode'),
          ],
        ),
      ),
    );
  }
}
