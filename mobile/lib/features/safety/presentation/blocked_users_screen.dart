import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../data/safety_repository.dart';

class BlockedUsersScreen extends ConsumerWidget {
  const BlockedUsersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final blocked = ref.watch(blockedOfficersProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Blocked officers')),
      body: blocked.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString(), style: AppTypography.body)),
        data: (items) => items.isEmpty
            ? Center(
                child: Text('You have not blocked anyone.',
                    style: AppTypography.caption.copyWith(color: AppColors.neutralGrey)))
            : ListView.separated(
                itemCount: items.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, i) {
                  final o = items[i];
                  return ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: AppColors.lightGreen,
                      child: Icon(Icons.person_off_outlined, color: AppColors.primaryGreen),
                    ),
                    title: Text(o.displayName, style: AppTypography.title),
                    subtitle: Text('Service No. ${o.serviceNumber}', style: AppTypography.caption),
                    trailing: TextButton(
                      onPressed: () async {
                        await ref.read(safetyRepositoryProvider).unblock(o.id);
                        ref.invalidate(blockedOfficersProvider);
                      },
                      child: const Text('Unblock'),
                    ),
                  );
                },
              ),
      ),
    );
  }
}
