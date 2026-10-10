import 'package:flutter/foundation.dart';

/// Build-time configuration. Override with --dart-define at build time; never
/// bake secrets into the app (there are none here — only the public API base).
///
/// The server can also be changed at runtime from the app's "Server address"
/// setting (see [ServerSettings]); that value wins over the build-time one.
///
/// A web build made without API_BASE_URL and served from a real domain talks
/// to the origin it was loaded from (`https://domain/api/v1`,
/// `wss://domain/app/...`), so one build works on any domain behind the
/// production proxy (infrastructure/production).
class AppConfig {
  AppConfig._();

  /// Server root set by the user at runtime, e.g. `https://nisconnect.onrender.com`.
  /// Loaded from secure storage before the app starts.
  /// Shown in About; keep in step with `version:` in pubspec.yaml.
  static const String appVersion = '1.0.0';

  static String? serverOverride;

  /// The development machine as seen from the client: the Android emulator
  /// reaches the host via 10.0.2.2; browsers and the iOS simulator use localhost.
  static String get _devHost =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android ? '10.0.2.2' : 'localhost';

  /// Replaces the page address in tests; null means [Uri.base] on the web.
  @visibleForTesting
  static Uri? debugPageUri;

  /// The page's origin when this is a web build without API_BASE_URL served
  /// from a non-local host; otherwise null (local development keeps using
  /// the dev host and ports below).
  static Uri? get _pageOrigin {
    if (_apiBaseUrl.isNotEmpty) return null;
    final page = debugPageUri ?? (kIsWeb ? Uri.base : null);
    if (page == null || (page.scheme != 'https' && page.scheme != 'http')) return null;
    const localHosts = {'localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'};
    if (page.host.isEmpty || localHosts.contains(page.host)) return null;
    return Uri(scheme: page.scheme, host: page.host, port: page.port);
  }

  /// The runtime server: the user's override, else the web page's origin.
  static Uri? get _override {
    final value = serverOverride;
    if (value == null || value.isEmpty) return _pageOrigin;
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
