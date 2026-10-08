import 'package:flutter/foundation.dart';

/// Build-time configuration. Override with --dart-define at build time; never
/// bake secrets into the app (there are none here — only the public API base).
///
/// The server can also be changed at runtime from the app's "Server address"
/// setting (see [ServerSettings]); that value wins over the build-time one.
class AppConfig {
  AppConfig._();

  /// Server root set by the user at runtime, e.g. `https://nisconnect.onrender.com`.
  /// Loaded from secure storage before the app starts.
  static String? serverOverride;

  /// The development machine as seen from the client: the Android emulator
  /// reaches the host via 10.0.2.2; browsers and the iOS simulator use localhost.
  static String get _devHost =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android ? '10.0.2.2' : 'localhost';

  static Uri? get _override {
    final value = serverOverride;
    if (value == null || value.isEmpty) return null;
    return Uri.tryParse(value);
  }

  static const String _apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  static String get apiBaseUrl {
    final o = _override;
    if (o != null) return '${o.toString().replaceFirst(RegExp(r'/+$'), '')}/api/v1';
    return _apiBaseUrl.isNotEmpty ? _apiBaseUrl : 'http://$_devHost:8000/api/v1';
  }

  /// The server root (API base without `/api/v1`), shown in the settings screen.
  static String get serverRoot => apiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');

  /// `ws` for local/emulator Reverb; override to `wss` for TLS deployments.
  static const String _wsScheme = String.fromEnvironment('WS_SCHEME', defaultValue: 'ws');
  static String get wsScheme {
    final o = _override;
    if (o != null) return o.scheme == 'https' ? 'wss' : 'ws';
    return _wsScheme;
  }

  static const String _wsHost = String.fromEnvironment('WS_HOST');
  static String get wsHost => _override?.host ?? (_wsHost.isNotEmpty ? _wsHost : _devHost);

  static const int _wsPort = int.fromEnvironment('WS_PORT', defaultValue: 8080);
  static int get wsPort {
    final o = _override;
    if (o != null) return o.hasPort ? o.port : (o.scheme == 'https' ? 443 : 80);
    return _wsPort;
  }

  static const String wsKey = String.fromEnvironment('WS_KEY', defaultValue: 'nisconnect');

  static const String livekitUrl = String.fromEnvironment('LIVEKIT_URL', defaultValue: '');
}
