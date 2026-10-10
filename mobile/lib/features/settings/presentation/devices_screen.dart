import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_typography.dart';

final devicesProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final res = await ref.watch(apiClientProvider).get('/devices');
  return ((res.data as Map<String, dynamic>)['data'] as List<dynamic>).cast<Map<String, dynamic>>();
});

/// Devices signed in to this account, with remote sign-out.
class DevicesScreen extends ConsumerWidget {
  const DevicesScreen({super.key});

  Future<bool> _confirm(BuildContext context, String title, String body, String action) async {
    return await showDialog<bool>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: Text(title),
            content: Text(body),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
              TextButton(onPressed: () => Navigator.pop(ctx, true), child: Text(action)),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _run(BuildContext context, WidgetRef ref, Future<void> Function() call, String done) async {
    try {
      await call();
      ref.invalidate(devicesProvider);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(done)));
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    }
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final devices = ref.watch(devicesProvider);
    final api = ref.read(apiClientProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('My Devices')),
      body: devices.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(e.toString(), textAlign: TextAlign.center),
            TextButton(onPressed: () => ref.invalidate(devicesProvider), child: const Text('Retry')),
          ]),
        ),
        data: (items) {
          final others = items.where((d) => d['current'] != true).length;
          return RefreshIndicator(
            onRefresh: () async => ref.refresh(devicesProvider.future),
            child: ListView(
              children: [
                for (final d in items)
                  ListTile(
                    leading: Icon(_icon(d['platform'] as String?)),
                    title: Text(
                      [d['name'], if (d['current'] == true) '(this device)']
                          .whereType<String>()
                          .join(' '),
                      style: AppTypography.title,
                    ),
                    subtitle: Text(_subtitle(d), style: AppTypography.caption),
                    trailing: d['current'] == true
                        ? null
                        : IconButton(
                            tooltip: 'Sign out this device',
                            icon: const Icon(Icons.logout),
                            onPressed: () async {
                              if (!await _confirm(context, 'Sign out this device?',
                                  '${d['name']} will need the PIN to sign in again.', 'Sign out')) {
                                return;
                              }
                              if (!context.mounted) return;
                              await _run(context, ref, () => api.delete('/devices/${d['id']}'),
                                  'Device signed out.');
                            },
                          ),
                  ),
                if (others > 0)
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.phonelink_erase_outlined),
                      label: const Text('Sign out all other devices'),
                      onPressed: () async {
                        if (!await _confirm(context, 'Sign out all other devices?',
                            'Every device except this one will be signed out.', 'Sign out all')) {
                          return;
                        }
                        if (!context.mounted) return;
                        await _run(context, ref, () => api.delete('/devices/all'),
                            'All other devices signed out.');
                      },
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  static IconData _icon(String? platform) => switch (platform) {
        'web' => Icons.computer_outlined,
        'ios' => Icons.phone_iphone,
        _ => Icons.phone_android,
      };

  static String _subtitle(Map<String, dynamic> d) {
    final parts = <String>[
      if (d['model'] is String) d['model'] as String,
      if (d['os_version'] is String) d['os_version'] as String,
    ];
    final last = DateTime.tryParse(d['last_active_at'] as String? ?? '')?.toLocal();
    if (last != null) parts.add('Active ${DateFormat('d MMM, HH:mm').format(last)}');
    return parts.isEmpty ? (d['platform'] as String? ?? '') : parts.join(' · ');
  }
}
