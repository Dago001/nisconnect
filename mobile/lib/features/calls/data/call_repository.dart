import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';

/// Result of joining/initiating a call: the LiveKit room + a signed token.
class CallSession {
  CallSession({
    required this.callId,
    required this.room,
    required this.token,
    required this.livekitUrl,
    required this.type,
    required this.status,
  });

  final String callId;
  final String room;
  final String token;
  final String livekitUrl;
  final String type; // voice|video
  final String status;

  factory CallSession.fromResponse(Map<String, dynamic> body) {
    final call = body['call'] as Map<String, dynamic>;
    return CallSession(
      callId: call['id'] as String,
      room: call['room'] as String,
      token: body['token'] as String,
      livekitUrl: (body['livekit_url'] ?? '') as String,
      type: call['type'] as String,
      status: call['status'] as String,
    );
  }
}

class CallRepository {
  CallRepository(this._api);
  final ApiClient _api;

  Future<CallSession> initiate(String conversationId, String type) async {
    final res = await _api.post('/calls', data: {'conversation_id': conversationId, 'type': type});
    return CallSession.fromResponse(res.data as Map<String, dynamic>);
  }

  Future<CallSession> answer(String callId) async {
    final res = await _api.post('/calls/$callId/answer');
    return CallSession.fromResponse(res.data as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> token(String callId) async {
    final res = await _api.get('/calls/$callId/token');
    return res.data as Map<String, dynamic>;
  }

  Future<void> decline(String callId) => _api.post('/calls/$callId/decline');
  Future<void> end(String callId) => _api.post('/calls/$callId/end');
}

final callRepositoryProvider = Provider<CallRepository>((ref) {
  return CallRepository(ref.watch(apiClientProvider));
});
