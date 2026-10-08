import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';

final groupsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  if (ref.watch(sessionServiceNumberProvider) == null) return const [];
  final res = await ref.watch(apiClientProvider).get('/groups');
  return ((res.data as Map<String, dynamic>)['data'] as List<dynamic>).cast<Map<String, dynamic>>();
});

class GroupsScreen extends ConsumerWidget {
  const GroupsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final groups = ref.watch(groupsProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Groups')),
      body: groups.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString(), style: AppTypography.body)),
        data: (items) => items.isEmpty
            ? Center(
                child: Text('You are not in any groups yet.',
                    style: AppTypography.caption.copyWith(color: AppColors.neutralGrey)))
            : ListView.separated(
                itemCount: items.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, i) => ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: AppColors.lightGreen,
                    child: Icon(Icons.groups_outlined, color: AppColors.primaryGreen),
                  ),
                  title: Text(items[i]['name'] as String? ?? '', style: AppTypography.title),
                  subtitle: Text(items[i]['description'] as String? ?? '', style: AppTypography.caption),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: items[i]['conversation_id'] == null
                      ? null
                      : () => context.push('/home/chat/${items[i]['conversation_id']}'),
                ),
              ),
      ),
    );
  }
}
