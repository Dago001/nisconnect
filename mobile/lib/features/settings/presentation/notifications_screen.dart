import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_typography.dart';

final notificationsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final res = await ref.watch(apiClientProvider).get('/notifications');
  return res.data as Map<String, dynamic>;
});

/// In-app notification inbox.
class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inbox = ref.watch(notificationsProvider);
    final api = ref.read(apiClientProvider);
    final unread = (inbox.valueOrNull?['unread'] as num?)?.toInt() ?? 0;

    Future<void> act(Future<void> Function() call) async {
      try {
        await call();
        ref.invalidate(notificationsProvider);
      } catch (e) {
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
        }
      }
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: [
          if (unread > 0)
            TextButton(
              onPressed: () => act(() => api.post('/notifications/read-all')),
              child: const Text('Mark all read'),
            ),
        ],
      ),
      body: inbox.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(e.toString(), textAlign: TextAlign.center),
            TextButton(onPressed: () => ref.invalidate(notificationsProvider), child: const Text('Retry')),
          ]),
        ),
        data: (body) {
          final items = ((body['data'] as List<dynamic>?) ?? const []).cast<Map<String, dynamic>>();
          if (items.isEmpty) {
            return const Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.notifications_none, size: 56),
                SizedBox(height: 12),
                Text("You're all caught up", style: AppTypography.title),
              ]),
            );
          }
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(notificationsProvider.future),
            child: ListView.separated(
              itemCount: items.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, i) {
                final n = items[i];
                final isUnread = n['read_at'] == null;
                final at = DateTime.tryParse(n['created_at'] as String? ?? '')?.toLocal();
                return ListTile(
                  leading: Icon(isUnread ? Icons.notifications_active : Icons.notifications_none,
                      color: isUnread ? Theme.of(context).colorScheme.primary : null),
                  title: Text(n['title'] as String? ?? 'Notification',
                      style: AppTypography.title.copyWith(
                          fontWeight: isUnread ? FontWeight.w700 : FontWeight.w500)),
                  subtitle: Text(
                    [n['body'], if (at != null) DateFormat('d MMM, HH:mm').format(at)]
                        .whereType<String>()
                        .where((s) => s.isNotEmpty)
                        .join('\n'),
                    style: AppTypography.caption,
                  ),
                  onTap: isUnread ? () => act(() => api.post('/notifications/${n['id']}/read')) : null,
                );
              },
            ),
          );
        },
      ),
    );
  }
}
