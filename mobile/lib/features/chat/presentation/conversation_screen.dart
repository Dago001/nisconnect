import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/session/session.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../services/websocket/realtime_service.dart';
import '../../calls/presentation/call_controller.dart';
import '../../calls/presentation/in_call_screen.dart';
import '../../safety/data/safety_repository.dart';
import '../../settings/data/chat_wallpaper.dart';
import '../../settings/presentation/wallpaper_screen.dart';
import '../data/chat_repository.dart';
import 'message_actions_sheet.dart';

/// One-to-one / group conversation view. Loads history and sends messages via
/// the API. New messages arrive over the websocket when the server has one,
/// and by polling every few seconds otherwise, so chat always works.
class ConversationScreen extends ConsumerStatefulWidget {
  const ConversationScreen({super.key, required this.conversationId});
  final String conversationId;

  @override
  ConsumerState<ConversationScreen> createState() => _ConversationScreenState();
}

enum _MenuAction { wallpaper, block, report }

class _ConversationScreenState extends ConsumerState<ConversationScreen> {
  static const _pollInterval = Duration(seconds: 4);

  final _input = TextEditingController();
  final _scroll = ScrollController();
  final _markedRead = <String>{};
  List<ChatMessage> _messages = [];
  ChatSummary? _chat;
  bool _loading = true;
  bool _sending = false;
  String? _error;
  String? _meId;
  Timer? _poll;
  StreamSubscription<RealtimeEvent>? _rtSub;

  @override
  void initState() {
    super.initState();
    _load();
    _connectRealtime();
    _poll = Timer.periodic(_pollInterval, (_) => _refresh());
  }

  @override
  void dispose() {
    _poll?.cancel();
    _rtSub?.cancel();
    _input.dispose();
    _scroll.dispose();
    super.dispose();
  }

  Future<void> _connectRealtime() async {
    final rt = ref.read(realtimeServiceProvider);
    try {
      await rt.connect();
      await rt.subscribeConversation(widget.conversationId);
      _rtSub = rt.events.listen(_onRealtime);
    } catch (_) {
      // Realtime is best-effort; polling keeps the conversation current.
    }
  }

  void _onRealtime(RealtimeEvent e) {
    if (e.data['conversation_id'] != widget.conversationId) return;
    if (e.name == 'message.new') {
      final msg = ChatMessage.fromJson(e.data);
      if (_messages.any((m) => m.id == msg.id)) return;
      setState(() => _messages = [..._messages, msg]);
      _markIncomingRead();
      _scrollToBottom();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final me = await ref.read(meProvider.future);
      _meId = me['id'] as String?;
      final repo = ref.read(chatRepositoryProvider);
      final results = await Future.wait([
        repo.chat(widget.conversationId),
        repo.messages(widget.conversationId),
      ]);
      if (!mounted) return;
      setState(() {
        _chat = results[0] as ChatSummary;
        _messages = (results[1] as List<ChatMessage>).reversed.toList(); // API is newest-first
        _loading = false;
      });
      _markIncomingRead();
      _scrollToBottom(animate: false);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  /// Re-fetches the latest page and swaps it in when anything changed
  /// (new, edited, pinned, read or deleted messages).
  Future<void> _refresh() async {
    if (_loading || _error != null) return;
    try {
      final latest =
          (await ref.read(chatRepositoryProvider).messages(widget.conversationId)).reversed.toList();
      if (!mounted || _sameMessages(latest, _messages)) return;
      final grew = latest.isNotEmpty &&
          (_messages.isEmpty || latest.last.id != _messages.last.id);
      final nearBottom = !_scroll.hasClients ||
          _scroll.position.maxScrollExtent - _scroll.position.pixels < 120;
      setState(() => _messages = latest);
      _markIncomingRead();
      if (grew && nearBottom) _scrollToBottom();
    } catch (_) {
      // Transient network failure; the next poll retries.
    }
  }

  bool _sameMessages(List<ChatMessage> a, List<ChatMessage> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      final x = a[i], y = b[i];
      if (x.id != y.id ||
          x.status != y.status ||
          x.body != y.body ||
          x.editedAt != y.editedAt ||
          x.pinnedAt != y.pinnedAt) {
        return false;
      }
    }
    return true;
  }

  /// Sends read receipts for messages from others (once per message).
  void _markIncomingRead() {
    final repo = ref.read(chatRepositoryProvider);
    for (final m in _messages) {
      if (m.senderId == _meId || m.status == 'read' || !_markedRead.add(m.id)) continue;
      repo.markRead(m.id).catchError((Object _) {});
    }
  }

  void _scrollToBottom({bool animate = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      final end = _scroll.position.maxScrollExtent;
      if (animate) {
        _scroll.animateTo(end, duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
      } else {
        _scroll.jumpTo(end);
      }
    });
  }

  Future<void> _send() async {
    final text = _input.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      final msg = await ref.read(chatRepositoryProvider).send(widget.conversationId, text);
      _input.clear();
      if (!mounted) return;
      setState(() => _messages = [..._messages.where((m) => m.id != msg.id), msg]);
      _scrollToBottom();
    } catch (e) {
      _snack("Message not sent. $e");
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _startCall(String type) async {
    try {
      await ref.read(callControllerProvider.notifier).startOutgoing(widget.conversationId, type);
    } catch (e) {
      _snack("Couldn't start the call. $e");
      return;
    }
    if (mounted) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => InCallScreen(title: _chat?.displayTitle(_meId) ?? 'Call')),
      );
    }
  }

  void _snack(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  Future<void> _onMenu(_MenuAction action) async {
    final other = _chat?.otherMember(_meId);
    switch (action) {
      case _MenuAction.wallpaper:
        await Navigator.of(context).push(MaterialPageRoute(builder: (_) => const WallpaperScreen()));
      case _MenuAction.block:
        if (other == null) return;
        final ok = await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text('Block ${other.label}?'),
            content: const Text(
                'Blocked officers cannot message or call you. You can unblock them from Profile > Blocked officers.'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
              TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Block')),
            ],
          ),
        );
        if (ok != true) return;
        try {
          await ref.read(safetyRepositoryProvider).block(other.userId);
          ref.invalidate(blockedOfficersProvider);
          _snack('${other.label} has been blocked.');
        } catch (e) {
          _snack("Couldn't block. $e");
        }
      case _MenuAction.report:
        if (other == null) return;
        final reason = await _pickReportReason();
        if (reason == null) return;
        try {
          await ref.read(safetyRepositoryProvider).report(
                targetType: 'user',
                targetId: other.userId,
                reason: reason,
              );
          _snack('Report sent. Thank you.');
        } catch (e) {
          _snack("Couldn't send the report. $e");
        }
    }
  }

  Future<String?> _pickReportReason() {
    const reasons = ['Harassment or abuse', 'Spam', 'Impersonation', 'Sharing restricted information', 'Other'];
    return showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text('Why are you reporting this?', style: AppTypography.title),
            ),
            for (final r in reasons)
              ListTile(title: Text(r), onTap: () => Navigator.pop(ctx, r)),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final chat = _chat;
    final other = chat?.isDirect == true ? chat!.otherMember(_meId) : null;
    final subtitle = other != null
        ? [other.rank, if (other.serviceNumber != null) 'Service No. ${other.serviceNumber}']
            .whereType<String>()
            .where((s) => s.isNotEmpty)
            .join(' · ')
        : chat != null
            ? '${chat.members.length} members'
            : null;
    final wallpaper = ChatWallpapers.decoration(ref.watch(chatWallpaperProvider));
    final isGroup = chat != null && !chat.isDirect;

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(chat?.displayTitle(_meId) ?? 'Conversation',
                maxLines: 1, overflow: TextOverflow.ellipsis),
            if (subtitle != null && subtitle.isNotEmpty)
              Text(subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: AppTypography.caption.copyWith(
                      color: Theme.of(context).appBarTheme.foregroundColor?.withValues(alpha: 0.8))),
          ],
        ),
        actions: [
          IconButton(
              tooltip: 'Voice call',
              icon: const Icon(Icons.call_outlined),
              onPressed: () => _startCall('voice')),
          IconButton(
              tooltip: 'Video call',
              icon: const Icon(Icons.videocam_outlined),
              onPressed: () => _startCall('video')),
          PopupMenuButton<_MenuAction>(
            onSelected: _onMenu,
            itemBuilder: (_) => [
              const PopupMenuItem(value: _MenuAction.wallpaper, child: Text('Wallpaper')),
              if (other != null) ...[
                const PopupMenuItem(value: _MenuAction.block, child: Text('Block officer')),
                const PopupMenuItem(value: _MenuAction.report, child: Text('Report officer')),
              ],
            ],
          ),
        ],
      ),
      body: DecoratedBox(
        decoration: wallpaper ?? const BoxDecoration(),
        child: Column(
          children: [
            Expanded(child: _buildMessages(isGroup)),
            _Composer(controller: _input, sending: _sending, onSend: _send),
          ],
        ),
      ),
    );
  }

  Widget _buildMessages(bool isGroup) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center, style: AppTypography.body),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('Try again')),
            ],
          ),
        ),
      );
    }
    if (_messages.isEmpty) {
      return Center(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.9),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Text('No messages yet. Say hello.', style: AppTypography.body),
        ),
      );
    }
    return ListView.builder(
      controller: _scroll,
      padding: const EdgeInsets.all(12),
      itemCount: _messages.length,
      itemBuilder: (context, i) {
        final msg = _messages[i];
        final mine = _meId != null && msg.senderId == _meId;
        final showSender = isGroup &&
            !mine &&
            (i == 0 || _messages[i - 1].senderId != msg.senderId);
        return GestureDetector(
          onLongPress: msg.isDeleted
              ? null
              : () => MessageActionsSheet.show(
                    context,
                    message: msg,
                    isMine: mine,
                    onChanged: _refresh,
                  ),
          child: _Bubble(
            message: msg,
            mine: mine,
            senderLabel: showSender ? _chat?.member(msg.senderId)?.label : null,
          ),
        );
      },
    );
  }
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.message, required this.mine, this.senderLabel});
  final ChatMessage message;
  final bool mine;
  final String? senderLabel;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final bg = mine
        ? (dark ? AppColors.dPrimaryGreen : AppColors.lightGreen)
        : (dark ? AppColors.dLightGreen : AppColors.white);
    final fg = dark ? AppColors.dText : AppColors.darkText;
    final meta = fg.withValues(alpha: 0.65);
    final time = DateTime.tryParse(message.createdAt ?? '')?.toLocal();

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 3),
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        decoration: BoxDecoration(
          color: bg,
          border: Border.all(color: dark ? AppColors.dBorder : AppColors.borderGrey),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (senderLabel != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 2),
                child: Text(senderLabel!,
                    style: AppTypography.label.copyWith(
                        color: dark ? AppColors.dSecondaryGreen : AppColors.primaryGreen,
                        fontWeight: FontWeight.w700)),
              ),
            Text(
              message.preview,
              style: AppTypography.body.copyWith(
                color: message.isDeleted ? meta : fg,
                fontStyle: message.isDeleted ? FontStyle.italic : null,
              ),
            ),
            const SizedBox(height: 2),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (message.pinnedAt != null) Icon(Icons.push_pin, size: 12, color: meta),
                if (message.editedAt != null)
                  Text('edited  ', style: AppTypography.caption.copyWith(color: meta)),
                if (time != null)
                  Text(DateFormat.Hm().format(time), style: AppTypography.caption.copyWith(color: meta)),
                if (mine) ...[
                  const SizedBox(width: 4),
                  Icon(
                    message.status == 'sent' ? Icons.done : Icons.done_all,
                    size: 14,
                    color: message.status == 'read'
                        ? (dark ? AppColors.dSecondaryGreen : AppColors.secondaryGreen)
                        : meta,
                  ),
                ],
              ],
            ),
          ],
        ),
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
    return Material(
      color: Theme.of(context).scaffoldBackgroundColor,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  minLines: 1,
                  maxLines: 5,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(hintText: 'Message'),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                tooltip: 'Send',
                onPressed: sending ? null : onSend,
                style: IconButton.styleFrom(backgroundColor: AppColors.primaryGreen),
                icon: sending
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.send, color: Colors.white),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
