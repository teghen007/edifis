import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:edifis/features/auth/role_placeholder_screen.dart';

void main() {
  testWidgets('role router renders correct placeholder for each role', (tester) async {
    final roles = <String, String>{
      'Principal': 'Principal',
      'Vice Principal': 'Vice Principal',
      'Bursar': 'Bursar',
      'Class Master': 'Class Master',
      'Subject Teacher': 'Subject Teacher',
      'Discipline Master': 'Discipline Master',
      'Secretary': 'Secretary',
      'Parent': 'Parent',
    };

    for (final entry in roles.entries) {
      await tester.pumpWidget(
        ProviderScope(
          child: MaterialApp(
            home: RolePlaceholderScreen(roleName: entry.key),
          ),
        ),
      );

      expect(find.text('EDIFIS — ${entry.value}'), findsOneWidget);
      expect(find.text(entry.value), findsWidgets);
    }
  });
}
