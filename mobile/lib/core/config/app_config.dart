/// Build-time configuration. Override with --dart-define at build time; never
/// bake secrets into the app (there are none here — only the public API base).
class AppConfig {
  AppConfig._();

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1', // Android emulator -> host
  );

  static const String wsHost = String.fromEnvironment('WS_HOST', defaultValue: '10.0.2.2');
  static const int wsPort = int.fromEnvironment('WS_PORT', defaultValue: 8080);
  static const String wsKey = String.fromEnvironment('WS_KEY', defaultValue: 'nisconnect');

  static const String livekitUrl = String.fromEnvironment('LIVEKIT_URL', defaultValue: '');
}
