import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart' show kIsWeb, debugPrint;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';
import '../../core/platform/client_platform.dart';
import 'notification_channels.dart';

/// Obtains the FCM/APNs push token and registers it with the backend so the
/// device receives message, call and security notifications. Firebase must be
/// initialised (Firebase.initializeApp) with real platform config first; all
/// calls are guarded so the app runs without it during development.
///
/// Web push is not wired up (it needs a Firebase web config, a VAPID key and a
/// service worker), so the browser client skips registration.
class PushService {
  PushService(this._api);
  final ApiClient _api;

  static final _local = FlutterLocalNotificationsPlugin();

  /// Creates the notification channels and starts listening for pushes.
  ///
  /// Call once at startup, before [registerForCurrentDevice]. The channels must
  /// exist before the first notification arrives, otherwise Android drops the
  /// alert onto a default channel with the stock tone.
  Future<void> initialise() async {
    if (kIsWeb) return;
    try {
      await _local.initialize(
        const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher'),
          iOS: DarwinInitializationSettings(
            // The token registration below asks for permission; don't prompt twice.
            requestAlertPermission: false,
            requestBadgePermission: false,
            requestSoundPermission: false,
          ),
        ),
      );
      await NotificationChannels.register(_local);

      // A push that lands while the app is open is delivered to the app rather
      // than drawn by the system, so present it here or it is silently lost.
      FirebaseMessaging.onMessage.listen(_showForeground);
    } catch (e) {
      debugPrint('Push init skipped: $e');
    }
  }

  Future<void> registerForCurrentDevice() async {
    if (kIsWeb) return;
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      final token = await messaging.getToken();
      if (token == null) return;

      await _api.post('/devices/push-token', data: {
        'provider': ClientPlatform.isIOS ? 'apns' : 'fcm',
        'token': token,
      });
    } catch (_) {
      // Push is best-effort; never block sign-in on it.
    }
  }

  /// Draws a notification for a push received while the app is in the
  /// foreground, on the channel its type belongs to so it plays the right tone.
  Future<void> _showForeground(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    final channel = NotificationChannels.forType(message.data['type'] as String?);
    final sound = channel.sound;

    try {
      await _local.show(
        message.hashCode,
        notification.title,
        notification.body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            channel.id,
            channel.name,
            channelDescription: channel.description,
            importance: channel.importance,
            priority: Priority.high,
            sound: sound is RawResourceAndroidNotificationSound ? sound : null,
            playSound: true,
          ),
          iOS: const DarwinNotificationDetails(presentSound: true),
        ),
        payload: message.data['conversation_id'] as String?,
      );
    } catch (e) {
      debugPrint('Could not show notification: $e');
    }
  }
}

final pushServiceProvider = Provider<PushService>((ref) {
  return PushService(ref.watch(apiClientProvider));
});
