import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';

class ChatMember {
  ChatMember({
    required this.userId,
    this.displayName,
    this.serviceNumber,
    this.rank,
    this.role,
  });
  final String userId;
  final String? displayName;
  final String? serviceNumber;
  final String? rank;
  final String? role;

  /// Name to show, falling back to the Service Number.
  String get label => (displayName != null && displayName!.isNotEmpty)
      ? displayName!
      : 'Officer ${serviceNumber ?? ''}'.trim();

  factory ChatMember.fromJson(Map<String, dynamic> j) => ChatMember(
        userId: j['user_id'] as String,
        displayName: j['display_name'] as String?,
        serviceNumber: j['service_number'] as String?,
        rank: j['rank'] as String?,
        role: j['role'] as String?,
      );
}

class ChatSummary {
  ChatSummary({
    required this.id,
    required this.type,
    this.title,
    this.lastMessageId,
    this.members = const [],
    this.lastMessage,
    this.updatedAt,
  });
  final String id;
  final String type;
  final String? title;
  final String? lastMessageId;
  final List<ChatMember> members;
  final ChatMessage? lastMessage;
  final String? updatedAt;

  bool get isDirect => type == 'direct';

  /// For a direct chat, the other officer (anyone who is not [meId]).
  ChatMember? otherMember(String? meId) {
    for (final m in members) {
      if (m.userId != meId) return m;
    }
    return null;
  }

  /// Title for lists and app bars: the group/channel title, or the other
  /// officer's name in a direct chat.
  String displayTitle(String? meId) {
    if (title != null && title!.isNotEmpty) return title!;
    if (isDirect) return otherMember(meId)?.label ?? 'Direct chat';
    return 'Group chat';
  }

  ChatMember? member(String? userId) {
    for (final m in members) {
      if (m.userId == userId) return m;
    }
    return null;
  }

  factory ChatSummary.fromJson(Map<String, dynamic> j) => ChatSummary(
        id: j['id'] as String,
        type: j['type'] as String,
        title: j['title'] as String?,
        lastMessageId: j['last_message_id'] as String?,
        members: ((j['members'] as List<dynamic>?) ?? const [])
            .map((e) => ChatMember.fromJson(e as Map<String, dynamic>))
            .toList(),
        lastMessage: j['last_message'] is Map<String, dynamic>
            ? ChatMessage.fromJson(j['last_message'] as Map<String, dynamic>)
            : null,
        updatedAt: j['updated_at'] as String?,
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

  bool get isDeleted => status == 'deleted';

  /// One-line text for previews and the chat list.
  String get preview {
    if (isDeleted) return 'This message was deleted';
    return switch (type) {
      'voice' => 'Voice message',
      'image' => 'Photo',
      'video' => 'Video',
      'file' || 'document' => 'Document',
      _ => body ?? '',
    };
  }

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

  Future<ChatSummary> chat(String conversationId) async {
    final res = await _api.get('/chats/$conversationId');
    return ChatSummary.fromJson((res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>);
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
  if (ref.watch(sessionServiceNumberProvider) == null) return Future.value(const []);
  return ref.watch(chatRepositoryProvider).chats();
});
