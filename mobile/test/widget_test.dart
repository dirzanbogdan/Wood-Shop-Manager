// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:wsm_mobile/main.dart';

void main() {
  testWidgets('Shows login screen when no token', (WidgetTester tester) async {
    await tester.pumpWidget(const WsmApp());
    await tester.pumpAndSettle();

    expect(find.text('WSM Login'), findsOneWidget);
    expect(find.byType(TextField), findsNWidgets(2));
  });
}
