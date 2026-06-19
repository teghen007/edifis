import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/auth/auth_state.dart';
import '../../core/theme/app_colors.dart';

class StaffHomeScreen extends ConsumerWidget {
  final String roleName;
  const StaffHomeScreen({super.key, required this.roleName});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AppColors.blue700,
        title: Text('myEDIFIS — $roleName'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) context.go('/login');
            },
          ),
        ],
      ),
      body: Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        const Icon(Icons.school, size: 64, color: AppColors.blue600),
        const SizedBox(height: 16),
        Text(roleName, style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: 8),
        const Text('Staff dashboard — coming in the next update'),
      ])),
    );
  }
}
