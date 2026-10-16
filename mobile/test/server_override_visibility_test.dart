import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nisconnect/core/config/app_config.dart';
import 'package:nisconnect/core/network/api_client.dart';
import 'package:nisconnect/shared/widgets/server_settings_dialog.dart';

/// The "Server address" control must not reach officers. A build made for them
/// names its server with --dart-define=API_BASE_URL, and the screen after this
/// one collects a Service Number and PIN — so a build pointed at the wrong
/// server would hand those to it.
///
/// `canChangeServer` is decided at compile time, so these tests check whichever
/// way this particular build was compiled, and both branches of the widget.
void main() {
  testWidgets('the button is hidden when the build names its own server',
      (tester) async {
    await tester.pumpWidget(const MaterialApp(
      home: Scaffold(body: Center(child: ServerAddressButton())),
    ));

    if (AppConfig.canChangeServer) {
      // A dev/test build (no API_BASE_URL): the control is meant to be there.
      expect(find.text('Server address'), findsOneWidget);
    } else {
      expect(find.text('Server address'), findsNothing);
      expect(find.byType(TextButton), findsNothing);
    }
  });

  test('a build without a baked-in server allows switching', () {
    // `flutter test` passes no --dart-define, so this is the dev case and the
    // setting has to stay reachable or local development breaks.
    expect(AppConfig.canChangeServer, isTrue);
  });

  test('the unreachable message only points at a control that exists', () {
    final message = unreachableMessage();

    // It should always name the host the app could not reach.
    expect(message, contains('NISconnect server'));

    if (AppConfig.canChangeServer) {
      expect(message, contains('Server address'));
    } else {
      // Telling an officer to use a hidden button is a dead end.
      expect(message, isNot(contains('Server address')));
    }
  });
}
