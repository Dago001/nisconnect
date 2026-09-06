import 'dart:async';

import 'package:dart_pusher_channels/dart_pusher_channels.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/config/app_config.dart';
import '../../core/network/api_client.dart';

/// A decoded realtime event: the broadcast name plus its JSON payload.
class RealtimeEvent {
  RealtimeEvent(this.name, this.data);
  final String name;
  final Map<String, dynamic> data;
}

/// Authorises private channels through Laravel's `/broadcasting/auth`.
///
/// Reuses [ApiClient]'s Dio instance so the bearer token interceptor applies —
/// the endpoint rejects unauthenticated callers.
class _BroadcastingAuthDelegate
    implements
        EndpointAuthorizableChannelAuthorizationDelegate<
            PrivateChannelAuthorizationData> {
  _BroadcastingAuthDelegate(this._api);

  final ApiClient _api;

  @override
  EndpointAuthFailedCallback? get onAuthFailed => null;

  @override
  Future<PrivateChannelAuthorizationData> authorizationData(
    String socketId,
    String channelName,
  ) async {
    final res = await _api.dio.post<dynamic>(
      '${AppConfig.apiBaseUrl.replaceFirst('/api/v1', '')}/broadcasting/auth',
      data: {'socket_id': socketId, 'channel_name': channelName},
    );
    final body = res.data;
    final authKey = body is Map ? body['auth']?.toString() : null;
    if (authKey == null) {
      throw StateError(
        'broadcasting/auth returned no "auth" key for $channelName',
      );
    }
    return PrivateChannelAuthorizationData(authKey: authKey);
  }
}

/// Connects to the Laravel Reverb WebSocket server (Pusher protocol) and
/// exposes a single event stream. Handles authenticated private channels and
/// reconnection with backoff. One instance per authenticated session.
class RealtimeService {
  RealtimeService(this._api);

  final ApiClient _api;

  final _controller = StreamController<RealtimeEvent>.broadcast();
  final _channels = <String, PrivateChannel>{};
  final _channelSubs = <String, StreamSubscription<ChannelReadEvent>>{};

  PusherChannelsClient? _client;
  StreamSubscription<void>? _connectionSub;
  bool _connected = false;

  Stream<RealtimeEvent> get events => _controller.stream;
  bool get isConnected => _connected;

  Future<void> connect() async {
    if (_client != null) return;

    // Reverb is self-hosted, so the endpoint is built from an explicit
    // host/port rather than a Pusher cluster: {scheme}://{host}:{port}/app/{key}
    final client = PusherChannelsClient.websocket(
      options: const PusherChannelsOptions.fromHost(
        scheme: AppConfig.wsScheme,
        host: AppConfig.wsHost,
        port: AppConfig.wsPort,
        key: AppConfig.wsKey,
      ),
      connectionErrorHandler: (exception, trace, refresh) {
        _connected = false;
        // Retry with the client's built-in backoff.
        refresh();
      },
    );
    _client = client;

    // Subscriptions do not survive a dropped socket, so re-subscribe every time
    // the connection is (re-)established rather than only on first connect.
    _connectionSub = client.onConnectionEstablished.listen((_) {
      _connected = true;
      for (final channel in _channels.values) {
        channel.subscribe();
      }
    });

    await client.connect();
    _connected = true;
  }

  Future<void> subscribeConversation(String conversationId) =>
      _subscribe('private-conversation.$conversationId');

  Future<void> subscribeUser(String userId) => _subscribe('private-user.$userId');

  Future<void> _subscribe(String channelName) async {
    final client = _client;
    if (client == null) {
      throw StateError('connect() must be called before subscribing');
    }
    if (_channels.containsKey(channelName)) return;

    final channel = client.privateChannel(
      channelName,
      authorizationDelegate: _BroadcastingAuthDelegate(_api),
    );
    _channels[channelName] = channel;
    _channelSubs[channelName] = channel.bindToAll().listen(_onEvent);
    channel.subscribe();
  }

  void _onEvent(ChannelReadEvent event) {
    final name = event.name;
    if (name.startsWith('pusher:') || name.startsWith('pusher_internal:')) {
      return;
    }
    // Payloads arrive double-encoded; tryGetDataAsMap unwraps that and returns
    // null when the body is not a JSON object.
    final raw = event.data;
    final data = event.tryGetDataAsMap() ??
        (raw == null ? <String, dynamic>{} : <String, dynamic>{'raw': raw});
    _controller.add(RealtimeEvent(name, data));
  }

  Future<void> disconnect() async {
    for (final sub in _channelSubs.values) {
      await sub.cancel();
    }
    _channelSubs.clear();
    for (final channel in _channels.values) {
      channel.unsubscribe();
    }
    _channels.clear();
    await _connectionSub?.cancel();
    _connectionSub = null;
    await _client?.disconnect();
    _connected = false;
  }

  void dispose() {
    for (final sub in _channelSubs.values) {
      unawaited(sub.cancel());
    }
    _channelSubs.clear();
    _channels.clear();
    unawaited(_connectionSub?.cancel());
    _connectionSub = null;
    // dispose() also tears down the underlying connection; null the field so a
    // second dispose cannot throw PusherChannelsClientDisposedException.
    _client?.dispose();
    _client = null;
    _connected = false;
    _controller.close();
  }
}

final realtimeServiceProvider = Provider<RealtimeService>((ref) {
  final service = RealtimeService(ref.watch(apiClientProvider));
  ref.onDispose(service.dispose);
  return service;
});
