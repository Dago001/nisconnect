import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nisconnect/shared/widgets/service_number_field.dart';

void main() {
  testWidgets('ServiceNumberField accepts digits and rejects letters/symbols', (tester) async {
    final controller = TextEditingController();
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: ServiceNumberField(controller: controller)),
    ));

    await tester.enterText(find.byType(TextField), 'NIS/12-34 ab56');
    // digitsOnly formatter strips everything but 0-9.
    expect(controller.text, '123456');
  });

  testWidgets('ServiceNumberField preserves leading zeroes', (tester) async {
    final controller = TextEditingController();
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(body: ServiceNumberField(controller: controller)),
    ));

    await tester.enterText(find.byType(TextField), '001234');
    expect(controller.text, '001234');
  });
}
