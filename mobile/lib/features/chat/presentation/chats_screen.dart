import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/session/session.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../data/chat_repository.dart';

/// Conversation list. Refreshes itself every few seconds so new messages show
/// up even when the server has no live websocket connection.
class ChatsScreen extends ConsumerStatefulWidget {
  const ChatsScreen({super.key});

  @override
  ConsumerState<ChatsScreen> createState() => _ChatsScreenState();
}

class _ChatsScreenState extends ConsumerState<ChatsScreen> {
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(const Duration(seconds: 10), (_) => ref.invalidate(chatsProvider));
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  Future<void> _open(ChatSummary c) async {
    await context.push('/home/chat/${c.id}');
    ref.invalidate(chatsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final chats = ref.watch(chatsProvider);
    final meId = ref.watch(meProvider).valueOrNull?['id'] as String?;
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Chats')),
      floatingActionButton: FloatingActionButton(
        tooltip: 'New chat',
        backgroundColor: AppColors.primaryGreen,
        onPressed: () => context.go('/home/directory'),
        child: const Icon(Icons.edit_outlined, color: Colors.white),
      ),
      body: chats.when(
        skipError: true,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorState(message: e.toString(), onRetry: () => ref.invalidate(chatsProvider)),
        data: (items) {
          if (items.isEmpty) return const _EmptyState();
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(chatsProvider.future),
            child: ListView.separated(
              itemCount: items.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, i) {
                final c = items[i];
                final last = c.lastMessage;
                final other = c.isDirect ? c.otherMember(meId) : null;
                final subtitle = last == null
                    ? (other?.serviceNumber != null
                        ? 'Service No. ${other!.serviceNumber}'
                        : 'No messages yet')
                    : '${last.senderId == meId ? 'You: ' : ''}${last.preview}';
                return ListTile(
                  leading: CircleAvatar(
                    backgroundColor: scheme.primaryContainer,
                    child: c.isDirect
                        ? Text(_initials(c.displayTitle(meId)),
                            style: AppTypography.label.copyWith(color: scheme.onPrimaryContainer))
                        : Icon(Icons.group_outlined, color: scheme.onPrimaryContainer),
                  ),
                  title: Text(c.displayTitle(meId),
                      maxLines: 1, overflow: TextOverflow.ellipsis, style: AppTypography.title),
                  subtitle: Text(subtitle,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: AppTypography.caption.copyWith(
                          fontStyle: last?.isDeleted == true ? FontStyle.italic : null)),
                  trailing: Text(formatChatTime(last?.createdAt ?? c.updatedAt),
                      style: AppTypography.caption),
                  onTap: () => _open(c),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

String _initials(String name) {
  final parts = name.trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) return parts.first.characters.take(2).toString().toUpperCase();
  return (parts.first.characters.first + parts.last.characters.first).toUpperCase();
}

/// "14:05" for today, "Mon" within a week, otherwise "08/10/26".
String formatChatTime(String? iso) {
  if (iso == null) return '';
  final t = DateTime.tryParse(iso)?.toLocal();
  if (t == null) return '';
  final now = DateTime.now();
  if (t.year == now.year && t.month == now.month && t.day == now.day) {
    return DateFormat.Hm().format(t);
  }
  if (now.difference(t).inDays < 7) return DateFormat.E().format(t);
  return DateFormat('dd/MM/yy').format(t);
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.forum_outlined, size: 56, color: AppColors.neutralGrey),
          const SizedBox(height: 12),
          const Text('No conversations yet', style: AppTypography.title),
          const SizedBox(height: 4),
          Text('Find an officer in the Directory to start chatting.',
              style: AppTypography.caption.copyWith(color: AppColors.neutralGrey)),
        ],
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center, style: AppTypography.body),
          const SizedBox(height: 12),
          TextButton(onPressed: onRetry, child: const Text('Retry')),
        ],
      ),
    );
  }
}
