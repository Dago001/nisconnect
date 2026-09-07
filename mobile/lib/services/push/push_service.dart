import 'dart:io' show Platform;

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

/// Obtains the FCM/APNs push token and registers it with the backend so the
/// device receives message, call and security notifications. Firebase must be
/// initialised (Firebase.initializeApp) with real platform config first; all
/// calls are guarded so the app runs without it during development.
class PushService {
  PushService(this._api);
  final ApiClient _api;

  Future<void> registerForCurrentDevice() async {
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      final token = await messaging.getToken();
      if (token == null) return;

      await _api.post('/devices/push-token', data: {
        'provider': Platform.isIOS ? 'apns' : 'fcm',
        'token': token,
      });
    } catch (_) {
      // Push is best-effort; never block sign-in on it.
    }
  }
}

final pushServiceProvider = Provider<PushService>((ref) {
  return PushService(ref.watch(apiClientProvider));
});
