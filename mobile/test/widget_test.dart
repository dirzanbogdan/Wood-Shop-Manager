// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:wsm_mobile/main.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('Shows login screen when no token', (WidgetTester tester) async {
    await tester.pumpWidget(const WsmApp());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));

    expect(find.text('Wood Shop Manager'), findsOneWidget);
    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Parola'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));

    await tester.tap(find.text('Setari conexiune'));
    await tester.pump();
    expect(find.text('API Base URL'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(3));
  });
}
