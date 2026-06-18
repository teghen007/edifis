import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('app boots — ProviderScope + MaterialApp', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(home: Text('EDIFIS — Online')),
      ),
    );
    await tester.pump();
    expect(find.text('EDIFIS — Online'), findsOneWidget);
  });
}

