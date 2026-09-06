import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../data/chat_repository.dart';

class ChatsScreen extends ConsumerWidget {
  const ChatsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final chats = ref.watch(chatsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Chats')),
      floatingActionButton: FloatingActionButton(
        backgroundColor: AppColors.primaryGreen,
        onPressed: () => context.go('/home/directory'),
        child: const Icon(Icons.edit_outlined, color: Colors.white),
      ),
      body: chats.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => _ErrorState(message: e.toString(), onRetry: () => ref.refresh(chatsProvider)),
        data: (items) {
          if (items.isEmpty) return const _EmptyState();
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(chatsProvider.future),
            child: ListView.separated(
              itemCount: items.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, i) {
                final c = items[i];
                return ListTile(
                  leading: CircleAvatar(
                    backgroundColor: AppColors.lightGreen,
                    child: Icon(
                      c.type == 'group' ? Icons.group_outlined : Icons.person_outline,
                      color: AppColors.primaryGreen,
                    ),
                  ),
                  title: Text(c.title ?? 'Direct chat', style: AppTypography.title),
                  subtitle: const Text('Tap to open', style: AppTypography.caption),
                  onTap: () => context.go('/home/chat/${c.id}'),
                );
              },
            ),
          );
        },
      ),
    );
  }
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
