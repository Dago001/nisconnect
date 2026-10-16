import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:nisconnect/core/config/app_config.dart';

/// `AppConfig.appVersion` is what the About screen shows, and pubspec's
/// `version:` is what Android and iOS put in the package. Nothing keeps the two
/// in step, so they drift silently and the app ends up reporting a version it
/// is not — which makes "which build is this officer running?" unanswerable
/// during a support call.
void main() {
  test('the version shown in the app matches pubspec', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    final match = RegExp(r'^version:\s*([0-9]+\.[0-9]+\.[0-9]+)\+([0-9]+)',
            multiLine: true)
        .firstMatch(pubspec);

    expect(match, isNotNull, reason: 'pubspec.yaml needs a "version: x.y.z+n" line');

    final pubspecVersion = match!.group(1);
    expect(
      AppConfig.appVersion,
      pubspecVersion,
      reason: 'AppConfig.appVersion must match pubspec version; update both together',
    );
  });

  test('the build number is present so installs can upgrade in place', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    final match = RegExp(r'^version:\s*[0-9]+\.[0-9]+\.[0-9]+\+([0-9]+)',
            multiLine: true)
        .firstMatch(pubspec);

    expect(match, isNotNull);
    // Android refuses to install a package whose versionCode is lower than the
    // installed one, and treats an equal one as already installed.
    expect(int.parse(match!.group(1)!), greaterThan(0));
  });
}
