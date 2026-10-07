import 'package:flutter/foundation.dart';

/// Build-time configuration. Override with --dart-define at build time; never
/// bake secrets into the app (there are none here — only the public API base).
class AppConfig {
  AppConfig._();

  /// The development machine as seen from the client: the Android emulator
  /// reaches the host via 10.0.2.2; browsers and the iOS simulator use localhost.
  static String get _devHost =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android ? '10.0.2.2' : 'localhost';

  static const String _apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  static String get apiBaseUrl =>
      _apiBaseUrl.isNotEmpty ? _apiBaseUrl : 'http://$_devHost:8000/api/v1';

  /// `ws` for local/emulator Reverb; override to `wss` for TLS deployments.
  static const String wsScheme = String.fromEnvironment('WS_SCHEME', defaultValue: 'ws');
  static const String _wsHost = String.fromEnvironment('WS_HOST');
  static String get wsHost => _wsHost.isNotEmpty ? _wsHost : _devHost;
  static const int wsPort = int.fromEnvironment('WS_PORT', defaultValue: 8080);
  static const String wsKey = String.fromEnvironment('WS_KEY', defaultValue: 'nisconnect');

  static const String livekitUrl = String.fromEnvironment('LIVEKIT_URL', defaultValue: '');
}
