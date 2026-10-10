import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';
import '../../../core/theme/app_typography.dart';

/// Who can see what: one choice per privacy setting (PUT /users/me/privacy).
class PrivacyScreen extends ConsumerStatefulWidget {
  const PrivacyScreen({super.key});

  @override
  ConsumerState<PrivacyScreen> createState() => _PrivacyScreenState();
}

class _PrivacyScreenState extends ConsumerState<PrivacyScreen> {
  static const _settings = <(String, String, IconData)>[
    ('last_seen', 'Last seen', Icons.schedule),
    ('online', 'Online status', Icons.circle_outlined),
    ('read_receipts', 'Read receipts', Icons.done_all),
    ('typing', 'Typing indicator', Icons.keyboard_outlined),
    ('profile_photo', 'Profile photo', Icons.account_circle_outlined),
    ('calls', 'Who can call me', Icons.call_outlined),
    ('group_invites', 'Who can add me to groups', Icons.group_add_outlined),
  ];
  static const _choices = {'everyone': 'Everyone', 'contacts': 'My contacts', 'nobody': 'Nobody'};

  Map<String, String>? _privacy;
  String? _saving;

  Future<void> _set(String key, String value) async {
    final before = Map<String, String>.from(_privacy!);
    setState(() {
      _privacy![key] = value;
      _saving = key;
    });
    try {
      final res = await ref.read(apiClientProvider).put('/users/me/privacy', data: {key: value});
      final saved = (res.data as Map<String, dynamic>)['privacy'];
      if (saved is Map && mounted) {
        setState(() => _privacy = saved.map((k, v) => MapEntry(k.toString(), v.toString())));
      }
      ref.invalidate(meProvider);
    } catch (e) {
      if (!mounted) return;
      setState(() => _privacy = before);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text("Couldn't save. $e")));
    } finally {
      if (mounted) setState(() => _saving = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final me = ref.watch(meProvider);
    if (_privacy == null && me.hasValue) {
      final p = me.value!['privacy'];
      _privacy = {
        for (final s in _settings) s.$1: 'everyone',
        if (p is Map) ...p.map((k, v) => MapEntry(k.toString(), v.toString())),
      };
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Privacy')),
      body: _privacy == null
          ? me.hasError
              ? Center(child: Text(me.error.toString()))
              : const Center(child: CircularProgressIndicator())
          : ListView(
              children: [
                for (final (key, label, icon) in _settings)
                  ListTile(
                    leading: Icon(icon),
                    title: Text(label, style: AppTypography.title),
                    trailing: _saving == key
                        ? const SizedBox(
                            width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                        : DropdownButton<String>(
                            value: _choices.containsKey(_privacy![key]) ? _privacy![key] : 'everyone',
                            underline: const SizedBox.shrink(),
                            items: [
                              for (final c in _choices.entries)
                                DropdownMenuItem(value: c.key, child: Text(c.value)),
                            ],
                            onChanged: (v) {
                              if (v != null && v != _privacy![key]) _set(key, v);
                            },
                          ),
                  ),
              ],
            ),
    );
  }
}
