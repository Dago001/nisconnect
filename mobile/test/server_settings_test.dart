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

  group('AppConfig same-origin web build', () {
    tearDown(() {
      AppConfig.debugPageUri = null;
      AppConfig.serverOverride = null;
    });

    test('uses the page origin when served from a real domain', () {
      AppConfig.debugPageUri = Uri.parse('https://connect.example.gov.ng/#/login');
      expect(AppConfig.apiBaseUrl, 'https://connect.example.gov.ng/api/v1');
      expect(AppConfig.serverRoot, 'https://connect.example.gov.ng');
      expect(AppConfig.wsScheme, 'wss');
      expect(AppConfig.wsHost, 'connect.example.gov.ng');
      expect(AppConfig.wsPort, 443);
    });

    test('keeps a non-default port from the origin', () {
      AppConfig.debugPageUri = Uri.parse('http://10.1.2.3:8081/');
      expect(AppConfig.apiBaseUrl, 'http://10.1.2.3:8081/api/v1');
      expect(AppConfig.wsScheme, 'ws');
      expect(AppConfig.wsPort, 8081);
    });

    test('localhost keeps the development defaults', () {
      AppConfig.debugPageUri = Uri.parse('http://localhost:5000/');
      expect(AppConfig.apiBaseUrl, endsWith(':8000/api/v1'));
      expect(AppConfig.wsPort, 8080);
    });

    test('a server chosen in the app still wins', () {
      AppConfig.debugPageUri = Uri.parse('https://connect.example.gov.ng/');
      AppConfig.serverOverride = 'https://other.example.gov.ng';
      expect(AppConfig.apiBaseUrl, 'https://other.example.gov.ng/api/v1');
    });
  });
}
