import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/data/auth_repository.dart';
import '../../safety/presentation/blocked_users_screen.dart';

final meProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final res = await ref.watch(apiClientProvider).get('/users/me');
  return (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>;
});

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: me.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(e.toString())),
        data: (u) => ListView(
          children: [
            const SizedBox(height: 16),
            const Center(
              child: CircleAvatar(
                radius: 40,
                backgroundColor: AppColors.lightGreen,
                child: Icon(Icons.person, size: 40, color: AppColors.primaryGreen),
              ),
            ),
            const SizedBox(height: 12),
            Center(child: Text(u['display_name'] as String? ?? '', style: AppTypography.h2)),
            Center(
              child: Text('Service No. ${u['service_number']}',
                  style: AppTypography.caption.copyWith(color: AppColors.neutralGrey)),
            ),
            const SizedBox(height: 24),
            _tile(context, Icons.security_outlined, 'Security'),
            _tile(context, Icons.devices_outlined, 'My Devices', onTap: () {}),
            _tile(context, Icons.lock_outline, 'Privacy'),
            _tile(context, Icons.block_outlined, 'Blocked officers', onTap: () {
              Navigator.of(context).push(MaterialPageRoute(
                builder: (_) => const BlockedUsersScreen(),
              ));
            }),
            _tile(context, Icons.notifications_outlined, 'Notifications'),
            _tile(context, Icons.info_outline, 'About NISconnect'),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.logout, color: AppColors.error),
              title: Text('Sign out', style: AppTypography.title.copyWith(color: AppColors.error)),
              onTap: () async {
                await ref.read(authRepositoryProvider).logout();
                if (context.mounted) context.go('/welcome');
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _tile(BuildContext context, IconData icon, String label, {VoidCallback? onTap}) {
    return ListTile(
      leading: Icon(icon, color: AppColors.primaryGreen),
      title: Text(label, style: AppTypography.title),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
