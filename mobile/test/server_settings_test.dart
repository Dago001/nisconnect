import 'package:flutter_test/flutter_test.dart';
import 'package:nisconnect/core/config/app_config.dart';
import 'package:nisconnect/shared/widgets/server_settings_dialog.dart';

void main() {
  group('normaliseServerUrl', () {
    test('adds https and strips trailing slash and /api/v1', () {
      expect(normaliseServerUrl('nisconnect.onrender.com'), 'https://nisconnect.onrender.com');
      expect(normaliseServerUrl(' https://x.example/ '), 'https://x.example');
      expect(normaliseServerUrl('https://x.example/api/v1/'), 'https://x.example');
      expect(normaliseServerUrl('http://192.168.1.5:8000'), 'http://192.168.1.5:8000');
    });

    test('rejects unusable input', () {
      expect(normaliseServerUrl(''), isNull);
      expect(normaliseServerUrl('ftp://x.example'), isNull);
    });
  });

  group('AppConfig server override', () {
    tearDown(() => AppConfig.serverOverride = null);

    test('derives API and websocket settings from the override', () {
      AppConfig.serverOverride = 'https://nisconnect.onrender.com';
      expect(AppConfig.apiBaseUrl, 'https://nisconnect.onrender.com/api/v1');
      expect(AppConfig.serverRoot, 'https://nisconnect.onrender.com');
      expect(AppConfig.wsScheme, 'wss');
      expect(AppConfig.wsHost, 'nisconnect.onrender.com');
      expect(AppConfig.wsPort, 443);
    });
  });
}
