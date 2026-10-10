import 'package:flutter/foundation.dart';

/// Identifies which client the app is running as (web, iOS or Android)
/// without touching `dart:io`, which is unavailable in the browser.
class ClientPlatform {
  ClientPlatform._();

  static bool get isIOS => !kIsWeb && defaultTargetPlatform == TargetPlatform.iOS;

  /// Value sent as `device.platform`; the backend accepts android|ios|web.
  static String get id => kIsWeb ? 'web' : (isIOS ? 'ios' : 'android');

  static String get deviceName => kIsWeb ? 'Web browser' : (isIOS ? 'iPhone' : 'Android device');

  /// The `device` payload sent with login / account creation.
  static Map<String, String> get device => {'name': deviceName, 'platform': id};
}
