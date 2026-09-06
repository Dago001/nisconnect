import 'dart:async';
import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_client.dart';

/// A decoded realtime event: the broadcast name plus its JSON payload.
class RealtimeEvent {
  RealtimeEvent(this.name, this.data);
  final String name;
  final Map<String, dynamic> data;
}

/// Connects to the Laravel Reverb WebSocket server (Pusher protocol) and
/// exposes a single event stream. Handles authenticated private channels and
/// reconnection with backoff. One instance per authenticated session.
class RealtimeService {
  RealtimeService(this._api);

  final ApiClient _api;

  final _pusher = PusherChannelsFlutter.getInstance();
  final _controller = StreamController<RealtimeEvent>.broadcast();
  final _subscribed = <String>{};
  bool _connected = false;

  Stream<RealtimeEvent> get events => _controller.stream;
  bool get isConnected => _connected;

  Future<void> connect() async {
    if (_connected) return;

    await _pusher.init(
      apiKey: AppConfig.wsKey,
      cluster: 'mt1',
      host: AppConfig.wsHost,
      wsPort: AppConfig.wsPort,
      useTLS: false,
      // Authorise private channels via the backend broadcasting/auth endpoint.
      onAuthorizer: (String channelName, String socketId, dynamic options) async {
        final res = await _api.dio.post(
          '${AppConfig.apiBaseUrl.replaceFirst('/api/v1', '')}/broadcasting/auth',
          data: {'socket_id': socketId, 'channel_name': channelName},
          options: null,
        );
        return res.data as Map<String, dynamic>;
      },
      onConnectionStateChange: (current, previous) {
        _connected = current == 'CONNECTED';
      },
      onEvent: _onEvent,
    );

    await _pusher.connect();
    _connected = true;
  }

  Future<void> subscribeConversation(String conversationId) =>
      _subscribe('private-conversation.$conversationId');

  Future<void> subscribeUser(String userId) => _subscribe('private-user.$userId');

  Future<void> _subscribe(String channel) async {
    if (_subscribed.contains(channel)) return;
    _subscribed.add(channel);
    await _pusher.subscribe(channelName: channel);
  }

  void _onEvent(PusherEvent event) {
    if (event.eventName.startsWith('pusher:') || event.eventName.startsWith('pusher_internal:')) {
      return;
    }
    Map<String, dynamic> data = {};
    if (event.data is String && (event.data as String).isNotEmpty) {
      try {
        data = jsonDecode(event.data as String) as Map<String, dynamic>;
      } catch (_) {
        data = {'raw': event.data};
      }
    }
    _controller.add(RealtimeEvent(event.eventName, data));
  }

  Future<void> disconnect() async {
    for (final c in _subscribed) {
      await _pusher.unsubscribe(channelName: c);
    }
    _subscribed.clear();
    await _pusher.disconnect();
    _connected = false;
  }

  void dispose() {
    _controller.close();
  }
}

final realtimeServiceProvider = Provider<RealtimeService>((ref) {
  final service = RealtimeService(ref.watch(apiClientProvider));
  ref.onDispose(service.dispose);
  return service;
});
