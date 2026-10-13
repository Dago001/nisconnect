import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// The Android notification channels NISconnect alerts arrive on, and the tone
/// each one plays.
///
/// These ids and sounds must stay in step with the backend's
/// `App\Services\Push\NotificationChannels` — the server names the channel in
/// every FCM payload, and Android silently falls back to the default tone if
/// the channel does not exist on the device.
///
/// Android freezes a channel's sound the first time the app creates it: later
/// edits are ignored for anyone who already has it. Changing a tone therefore
/// means a NEW id, which is what the `_v1` suffix is for — bump it in both
/// places together.
class NotificationChannels {
  NotificationChannels._();

  static const _version = 'v1';

  static const calls = AndroidNotificationChannel(
    'calls_$_version',
    'Incoming calls',
    description: 'Rings when an officer calls you.',
    importance: Importance.max,
    sound: RawResourceAndroidNotificationSound('nis_ring'),
    playSound: true,
    enableVibration: true,
    // A call should be audible even when the phone is face down in a pocket.
    enableLights: true,
  );

  static const messages = AndroidNotificationChannel(
    'messages_$_version',
    'Messages',
    description: 'New messages in your chats and groups.',
    importance: Importance.high,
    sound: RawResourceAndroidNotificationSound('nis_alert'),
    playSound: true,
    enableVibration: true,
  );

  static const announcements = AndroidNotificationChannel(
    'announcements_$_version',
    'Announcements',
    description: 'Official channel posts and service-wide announcements.',
    importance: Importance.high,
    sound: RawResourceAndroidNotificationSound('nis_notice'),
    playSound: true,
    enableVibration: true,
  );

  static const security = AndroidNotificationChannel(
    'security_$_version',
    'Security alerts',
    description: 'New device sign-ins and changes to your account security.',
    importance: Importance.high,
    sound: RawResourceAndroidNotificationSound('nis_notice'),
    playSound: true,
    enableVibration: true,
  );

  /// Anything the server sends that does not match a known type.
  static const general = AndroidNotificationChannel(
    'general_$_version',
    'General',
    description: 'Other NISconnect notifications.',
    importance: Importance.defaultImportance,
    sound: RawResourceAndroidNotificationSound('nis_alert'),
    playSound: true,
  );

  static const all = <AndroidNotificationChannel>[
    calls,
    messages,
    announcements,
    security,
    general,
  ];

  /// Picks the channel for a server notification `type` such as `message` or
  /// `call.incoming`, mirroring the backend's stem matching.
  static AndroidNotificationChannel forType(String? type) {
    switch ((type ?? '').split('.').first) {
      case 'call':
        return calls;
      case 'message':
        return messages;
      case 'announcement':
        return announcements;
      case 'security':
        return security;
      default:
        return general;
    }
  }

  /// Registers every channel with Android. Safe to call on each launch —
  /// creating an existing channel is a no-op.
  static Future<void> register(FlutterLocalNotificationsPlugin plugin) async {
    if (kIsWeb) return;
    final android = plugin.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    if (android == null) return; // Not Android.
    for (final channel in all) {
      await android.createNotificationChannel(channel);
    }
  }
}
