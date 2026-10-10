import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';

class BlockedOfficer {
  BlockedOfficer({required this.id, required this.serviceNumber, required this.displayName});
  final String id;
  final String serviceNumber;
  final String displayName;

  factory BlockedOfficer.fromJson(Map<String, dynamic> j) => BlockedOfficer(
        id: j['id'] as String,
        serviceNumber: j['service_number'] as String,
        displayName: (j['display_name'] ?? '') as String,
      );
}

class SafetyRepository {
  SafetyRepository(this._api);
  final ApiClient _api;

  Future<List<BlockedOfficer>> blocked() async {
    final res = await _api.get('/safety/blocked');
    final data = (res.data as Map<String, dynamic>)['data'] as List<dynamic>;
    return data.map((e) => BlockedOfficer.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<void> block(String userId) => _api.post('/safety/block', data: {'user_id': userId});
  Future<void> unblock(String userId) => _api.post('/safety/unblock', data: {'user_id': userId});

  Future<void> report({
    required String targetType,
    required String targetId,
    required String reason,
    String? details,
  }) =>
      _api.post('/safety/report', data: {
        'target_type': targetType,
        'target_id': targetId,
        'reason': reason,
        if (details != null) 'details': details,
      });
}

final safetyRepositoryProvider = Provider<SafetyRepository>((ref) {
  return SafetyRepository(ref.watch(apiClientProvider));
});

final blockedOfficersProvider = FutureProvider<List<BlockedOfficer>>((ref) {
  if (ref.watch(sessionServiceNumberProvider) == null) return Future.value(const []);
  return ref.watch(safetyRepositoryProvider).blocked();
});
