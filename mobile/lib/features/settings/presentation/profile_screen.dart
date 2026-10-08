import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/data/auth_repository.dart';
import '../../safety/presentation/blocked_users_screen.dart';
import 'devices_screen.dart';
import 'notifications_screen.dart';
import 'privacy_screen.dart';
import 'security_screen.dart';
import 'wallpaper_screen.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  void _open(BuildContext context, Widget screen) =>
      Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));

  Future<void> _editName(BuildContext context, WidgetRef ref, String current) async {
    final controller = TextEditingController(text: current);
    final name = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Display name'),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLength: 80,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(hintText: 'How other officers see you'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, controller.text.trim()), child: const Text('Save')),
        ],
      ),
    );
    controller.dispose();
    if (name == null || name.isEmpty || name == current) return;
    try {
      await ref.read(apiClientProvider).patch('/users/me', data: {'display_name': name});
      ref.invalidate(meProvider);
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Couldn't save. $e")));
      }
    }
  }

  void _about(BuildContext context) {
    final host = Uri.tryParse(AppConfig.serverRoot)?.host ?? AppConfig.serverRoot;
    showAboutDialog(
      context: context,
      applicationName: 'NISconnect',
      applicationVersion: 'Version ${AppConfig.appVersion}',
      applicationIcon: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: Image.asset('assets/logos/nis-logo.jpg', width: 48, height: 48),
      ),
      applicationLegalese: 'Nigeria Immigration Service',
      children: [
        const SizedBox(height: 16),
        const Text(
          'Secure internal communication for NIS officers. Every account is tied to a '
          'verified Service Number.',
          style: AppTypography.body,
        ),
        const SizedBox(height: 12),
        Text('Server: $host', style: AppTypography.caption),
      ],
    );
  }

  Future<void> _signOut(BuildContext context, WidgetRef ref) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Sign out?'),
        content: const Text('You will need your Service Number and PIN to sign in again.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Sign out')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(authRepositoryProvider).logout();
    } catch (_) {
      // Signed out locally even if the server couldn't be reached.
    }
    if (context.mounted) context.go('/welcome');
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final me = ref.watch(meProvider);
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: me.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(e.toString(), textAlign: TextAlign.center),
            TextButton(onPressed: () => ref.invalidate(meProvider), child: const Text('Retry')),
            TextButton(onPressed: () => _signOut(context, ref), child: const Text('Sign out')),
          ]),
        ),
        data: (u) {
          final name = u['display_name'] as String? ?? '';
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(meProvider.future),
            child: ListView(
              children: [
                const SizedBox(height: 16),
                Center(
                  child: CircleAvatar(
                    radius: 40,
                    backgroundColor: scheme.primaryContainer,
                    child: Icon(Icons.person, size: 40, color: scheme.onPrimaryContainer),
                  ),
                ),
                const SizedBox(height: 12),
                Center(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: () => _editName(context, ref, name),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Text(name, style: AppTypography.h2),
                        const SizedBox(width: 6),
                        const Icon(Icons.edit_outlined, size: 18),
                      ]),
                    ),
                  ),
                ),
                Center(
                  child: Text(
                    [u['rank'], 'Service No. ${u['service_number']}']
                        .whereType<String>()
                        .where((s) => s.isNotEmpty)
                        .join(' · '),
                    style: AppTypography.caption,
                  ),
                ),
                const SizedBox(height: 24),
                _tile(context, Icons.security_outlined, 'Security', () => _open(context, const SecurityScreen())),
                _tile(context, Icons.devices_outlined, 'My Devices', () => _open(context, const DevicesScreen())),
                _tile(context, Icons.lock_outline, 'Privacy', () => _open(context, const PrivacyScreen())),
                _tile(context, Icons.wallpaper_outlined, 'Chat wallpaper',
                    () => _open(context, const WallpaperScreen())),
                _tile(context, Icons.block_outlined, 'Blocked officers',
                    () => _open(context, const BlockedUsersScreen())),
                _tile(context, Icons.notifications_outlined, 'Notifications',
                    () => _open(context, const NotificationsScreen())),
                _tile(context, Icons.info_outline, 'About NISconnect', () => _about(context)),
                const Divider(),
                ListTile(
                  leading: const Icon(Icons.logout, color: AppColors.error),
                  title: Text('Sign out', style: AppTypography.title.copyWith(color: AppColors.error)),
                  onTap: () => _signOut(context, ref),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _tile(BuildContext context, IconData icon, String label, VoidCallback onTap) {
    return ListTile(
      leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
      title: Text(label, style: AppTypography.title),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
