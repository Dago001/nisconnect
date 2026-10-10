import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/session/session.dart';
import '../data/chat_repository.dart';

/// Bottom sheet of actions for a message: copy, edit (own text), pin, forward,
/// react, delete (own). Returns after performing the action; the caller
/// refreshes the list.
class MessageActionsSheet extends ConsumerWidget {
  const MessageActionsSheet({
    super.key,
    required this.message,
    required this.isMine,
    required this.onChanged,
  });

  final ChatMessage message;
  final bool isMine;
  final VoidCallback onChanged;

  static Future<void> show(
    BuildContext context, {
    required ChatMessage message,
    required bool isMine,
    required VoidCallback onChanged,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      builder: (_) => MessageActionsSheet(message: message, isMine: isMine, onChanged: onChanged),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final repo = ref.read(chatRepositoryProvider);
    return SafeArea(
      child: Wrap(
        children: [
          if (message.body != null)
            ListTile(
              leading: const Icon(Icons.copy_outlined),
              title: const Text('Copy'),
              onTap: () async {
                await Clipboard.setData(ClipboardData(text: message.body!));
                if (context.mounted) Navigator.pop(context);
              },
            ),
          ListTile(
            leading: const Icon(Icons.push_pin_outlined),
            title: Text(message.pinnedAt == null ? 'Pin' : 'Unpin'),
            onTap: () async {
              await repo.pin(message.id, message.pinnedAt == null);
              onChanged();
              if (context.mounted) Navigator.pop(context);
            },
          ),
          ListTile(
            leading: const Icon(Icons.forward_outlined),
            title: const Text('Forward'),
            onTap: () async {
              Navigator.pop(context);
              final target = await _pickConversation(context, ref);
              if (target != null) {
                await repo.forward(message.id, target);
                onChanged();
              }
            },
          ),
          if (isMine && message.type == 'text')
            ListTile(
              leading: const Icon(Icons.edit_outlined),
              title: const Text('Edit'),
              onTap: () async {
                Navigator.pop(context);
                final edited = await _editDialog(context, message.body ?? '');
                if (edited != null && edited.isNotEmpty) {
                  await repo.edit(message.id, edited);
                  onChanged();
                }
              },
            ),
          if (isMine)
            ListTile(
              leading: const Icon(Icons.delete_outline, color: Colors.red),
              title: const Text('Delete', style: TextStyle(color: Colors.red)),
              onTap: () async {
                await repo.delete(message.id);
                onChanged();
                if (context.mounted) Navigator.pop(context);
              },
            ),
        ],
      ),
    );
  }

  Future<String?> _editDialog(BuildContext context, String initial) {
    final controller = TextEditingController(text: initial);
    return showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Edit message'),
        content: TextField(controller: controller, autofocus: true, maxLines: null),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, controller.text.trim()), child: const Text('Save')),
        ],
      ),
    );
  }

  Future<String?> _pickConversation(BuildContext context, WidgetRef ref) async {
    final chats = await ref.read(chatRepositoryProvider).chats();
    final meId = ref.read(meProvider).valueOrNull?['id'] as String?;
    if (!context.mounted) return null;
    return showModalBottomSheet<String>(
      context: context,
      builder: (_) => ListView(
        children: [
          for (final c in chats)
            ListTile(
              title: Text(c.displayTitle(meId)),
              onTap: () => Navigator.pop(context, c.id),
            ),
        ],
      ),
    );
  }
}
