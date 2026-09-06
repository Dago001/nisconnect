import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../services/websocket/realtime_service.dart';
import '../../calls/presentation/call_controller.dart';
import '../../calls/presentation/in_call_screen.dart';
import '../data/chat_repository.dart';

/// One-to-one / group conversation view. Loads history and sends messages via
/// the API; realtime delivery is layered on by the websocket service.
class ConversationScreen extends ConsumerStatefulWidget {
  const ConversationScreen({super.key, required this.conversationId});
  final String conversationId;

  @override
  ConsumerState<ConversationScreen> createState() => _ConversationScreenState();
}

class _ConversationScreenState extends ConsumerState<ConversationScreen> {
  final _input = TextEditingController();
  final _scroll = ScrollController();
  List<ChatMessage> _messages = [];
  bool _loading = true;
  bool _sending = false;
  String? _meId;
  StreamSubscription<RealtimeEvent>? _rtSub;

  @override
  void initState() {
    super.initState();
    _load();
    _connectRealtime();
  }

  Future<void> _connectRealtime() async {
    final rt = ref.read(realtimeServiceProvider);
    try {
      await rt.connect();
      await rt.subscribeConversation(widget.conversationId);
      _rtSub = rt.events.listen(_onRealtime);
    } catch (_) {
      // Realtime is best-effort; the screen still works via REST + pull-to-refresh.
    }
  }

  void _onRealtime(RealtimeEvent e) {
    if (e.name == 'message.new' && e.data['conversation_id'] == widget.conversationId) {
      if (e.data['sender_id'] == _meId) return; // already shown locally
      setState(() => _messages = [..._messages, ChatMessage.fromJson(e.data)]);
    }
  }

  Future<void> _startCall(String type) async {
    await ref.read(callControllerProvider.notifier).startOutgoing(widget.conversationId, type);
    if (mounted) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const InCallScreen(title: 'Call')),
      );
    }
  }

  Future<void> _load() async {
    final me = await ref.read(apiClientProvider).get('/users/me');
    _meId = ((me.data as Map<String, dynamic>)['data'] as Map<String, dynamic>)['id'] as String?;
    final msgs = await ref.read(chatRepositoryProvider).messages(widget.conversationId);
    if (!mounted) return;
    setState(() {
      _messages = msgs.reversed.toList(); // API returns newest-first
      _loading = false;
    });
  }

  @override
  void dispose() {
    _rtSub?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    try {
      final msg = await ref.read(chatRepositoryProvider).send(widget.conversationId, text);
      _input.clear();
      setState(() => _messages = [..._messages, msg]);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scroll.hasClients) {
          _scroll.animateTo(_scroll.position.maxScrollExtent,
              duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
        }
      });
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Conversation'),
        actions: [
          IconButton(icon: const Icon(Icons.call_outlined), onPressed: () => _startCall('voice')),
          IconButton(icon: const Icon(Icons.videocam_outlined), onPressed: () => _startCall('video')),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    controller: _scroll,
                    padding: const EdgeInsets.all(12),
                    itemCount: _messages.length,
                    itemBuilder: (context, i) => _Bubble(
                      message: _messages[i],
                      mine: _meId != null && _messages[i].senderId == _meId,
                    ),
                  ),
          ),
          _Composer(controller: _input, sending: _sending, onSend: _send),
        ],
      ),
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.mine});
  final ChatMessage message;
  final bool mine;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        decoration: BoxDecoration(
          color: mine ? AppColors.lightGreen : AppColors.white,
          border: Border.all(color: AppColors.borderGrey),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Text(message.body ?? '', style: AppTypography.body),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({required this.controller, required this.sending, required this.onSend});
  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                minLines: 1,
                maxLines: 5,
                decoration: const InputDecoration(hintText: 'Message'),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: sending ? null : onSend,
              style: IconButton.styleFrom(backgroundColor: AppColors.primaryGreen),
              icon: const Icon(Icons.send, color: Colors.white),
            ),
          ],
        ),
      ),
    );
  }
}
