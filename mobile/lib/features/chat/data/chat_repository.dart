import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';

class ChatSummary {
  ChatSummary({required this.id, required this.type, this.title, this.lastMessageId});
  final String id;
  final String type;
  final String? title;
  final String? lastMessageId;

  factory ChatSummary.fromJson(Map<String, dynamic> j) => ChatSummary(
        id: j['id'] as String,
        type: j['type'] as String,
        title: j['title'] as String?,
        lastMessageId: j['last_message_id'] as String?,
      );
}

class ChatMessage {
  ChatMessage({
    required this.id,
    required this.senderId,
    required this.type,
    this.body,
    required this.status,
    this.createdAt,
    this.editedAt,
    this.pinnedAt,
  });
  final String id;
  final String? senderId;
  final String type;
  final String? body;
  final String status;
  final String? createdAt;
  final String? editedAt;
  final String? pinnedAt;

  factory ChatMessage.fromJson(Map<String, dynamic> j) => ChatMessage(
        id: j['id'] as String,
        senderId: j['sender_id'] as String?,
        type: j['type'] as String,
        body: j['body'] as String?,
        status: j['status'] as String,
        createdAt: j['created_at'] as String?,
        editedAt: j['edited_at'] as String?,
        pinnedAt: j['pinned_at'] as String?,
      );
}

class ChatRepository {
  ChatRepository(this._api);
  final ApiClient _api;

  Future<List<ChatSummary>> chats() async {
    final res = await _api.get('/chats');
    final data = (res.data as Map<String, dynamic>)['data'] as List<dynamic>;
    return data.map((e) => ChatSummary.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<ChatSummary> startChat(String serviceNumber) async {
    final res = await _api.post('/chats', data: {'service_number': serviceNumber});
    return ChatSummary.fromJson((res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<List<ChatMessage>> messages(String conversationId, {String? cursor}) async {
    final res = await _api.get('/chats/$conversationId/messages',
        query: {if (cursor != null) 'cursor': cursor});
    final data = (res.data as Map<String, dynamic>)['data'] as List<dynamic>;
    return data.map((e) => ChatMessage.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<ChatMessage> send(String conversationId, String body) async {
    final res = await _api.post('/chats/$conversationId/messages',
        data: {'type': 'text', 'body': body});
    return ChatMessage.fromJson((res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<void> markRead(String messageId) => _api.post('/messages/$messageId/read');

  Future<ChatMessage> edit(String messageId, String body) async {
    final res = await _api.patch('/messages/$messageId', data: {'body': body});
    return ChatMessage.fromJson((res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<void> pin(String messageId, bool pinned) =>
      _api.post('/messages/$messageId/pin', data: {'pinned': pinned});

  Future<ChatMessage> forward(String messageId, String toConversationId) async {
    final res = await _api.post('/messages/$messageId/forward',
        data: {'conversation_id': toConversationId});
    return ChatMessage.fromJson((res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
  }

  Future<void> react(String messageId, String emoji) =>
      _api.post('/messages/$messageId/react', data: {'emoji': emoji});

  Future<void> delete(String messageId) => _api.delete('/messages/$messageId');

  Future<void> sendTyping(String conversationId) =>
      _api.post('/chats/$conversationId/typing');
}

final chatRepositoryProvider = Provider<ChatRepository>((ref) {
  return ChatRepository(ref.watch(apiClientProvider));
});

final chatsProvider = FutureProvider<List<ChatSummary>>((ref) {
  return ref.watch(chatRepositoryProvider).chats();
});
