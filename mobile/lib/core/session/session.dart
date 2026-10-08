import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../network/api_client.dart';

/// Service Number of the signed-in officer, or null when signed out.
///
/// A plain notifier (not a provider) so the app-wide watermark, which sits
/// above the router in MaterialApp.builder, can listen to it directly.
final currentServiceNumber = ValueNotifier<String?>(null);

/// [currentServiceNumber] as a provider. Per-officer data providers watch it,
/// so signing out or switching officer discards the previous officer's data.
final sessionServiceNumberProvider = Provider<String?>((ref) {
  void onChange() => ref.invalidateSelf();
  currentServiceNumber.addListener(onChange);
  ref.onDispose(() => currentServiceNumber.removeListener(onChange));
  return currentServiceNumber.value;
});

/// The signed-in officer's profile (GET /users/me).
final meProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  if (ref.watch(sessionServiceNumberProvider) == null) {
    throw ApiException('You are signed out.', statusCode: 401);
  }
  final res = await ref.watch(apiClientProvider).get('/users/me');
  return (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>;
});
