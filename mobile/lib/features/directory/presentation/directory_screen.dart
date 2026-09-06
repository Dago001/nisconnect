import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../chat/data/chat_repository.dart';

/// Personnel directory search. Requires a term (backend enforces rate limits
/// and blocks bulk enumeration).
class DirectoryScreen extends ConsumerStatefulWidget {
  const DirectoryScreen({super.key});
  @override
  ConsumerState<DirectoryScreen> createState() => _DirectoryScreenState();
}

class _DirectoryScreenState extends ConsumerState<DirectoryScreen> {
  final _query = TextEditingController();
  List<Map<String, dynamic>> _results = [];
  bool _loading = false;
  String? _error;

  Future<void> _search() async {
    if (_query.text.trim().isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await ref.read(apiClientProvider).get('/directory/search', query: {'q': _query.text});
      final data = (res.data as Map<String, dynamic>)['data'] as List<dynamic>;
      setState(() => _results = data.cast<Map<String, dynamic>>());
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _startChat(String serviceNumber) async {
    final chat = await ref.read(chatRepositoryProvider).startChat(serviceNumber);
    if (mounted) context.go('/home/chat/${chat.id}');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Directory')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _query,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _search(),
              decoration: InputDecoration(
                hintText: 'Search name, rank or Service Number',
                prefixIcon: const Icon(Icons.search),
                errorText: _error,
                suffixIcon: IconButton(icon: const Icon(Icons.arrow_forward), onPressed: _search),
              ),
            ),
          ),
          if (_loading) const LinearProgressIndicator(),
          Expanded(
            child: ListView.separated(
              itemCount: _results.length,
              separatorBuilder: (_, __) => const Divider(height: 1),
              itemBuilder: (context, i) {
                final o = _results[i];
                return ListTile(
                  leading: const CircleAvatar(
                    backgroundColor: AppColors.lightGreen,
                    child: Icon(Icons.person_outline, color: AppColors.primaryGreen),
                  ),
                  title: Text(o['display_name'] as String? ?? '', style: AppTypography.title),
                  subtitle: Text(
                    [o['rank'], 'Service No. ${o['service_number']}', o['directorate']]
                        .where((e) => e != null && (e as String).isNotEmpty)
                        .join('\n'),
                    style: AppTypography.caption,
                  ),
                  isThreeLine: true,
                  trailing: IconButton(
                    icon: const Icon(Icons.chat_bubble_outline, color: AppColors.primaryGreen),
                    onPressed: () => _startChat(o['service_number'] as String),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
