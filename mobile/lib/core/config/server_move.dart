import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../storage/secure_storage.dart';
import 'app_config.dart';

/// Follows the service when it moves to a new address (e.g. from
/// apps.immigration.gov.ng to connect.immigration.gov.ng), so officers never
/// have to reinstall or retype the server address.
///
/// The current server announces its new home in `GET /api/v1/meta`
/// (`moved_to`). The new address is only accepted if it is https and
/// confirms itself (its own meta reports the same address), then saved as
/// the server address; it takes effect the next time the app starts.
/// The web app doesn't need this: the old website redirects browsers.
Future<void> followServerMove({Dio? dio}) async {
  if (kIsWeb) return;
  final client = dio ??
      Dio(BaseOptions(
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {'Accept': 'application/json'},
      ));
  try {
    final next = await resolveServerMove(AppConfig.serverRoot, (root) async {
      final res = await client.get<dynamic>('$root/api/v1/meta');
      final data = res.data;
      return data is Map ? data['moved_to'] as String? : null;
    });
    if (next != null) {
      await secureStorage.saveServerUrl(next);
    }
  } catch (_) {
    // Offline, old server without /meta, storage unavailable: try next launch.
  }
}

/// Returns the address to switch to, or null to stay. [movedTo] fetches a
/// server's `moved_to` value.
@visibleForTesting
Future<String?> resolveServerMove(
  String current,
  Future<String?> Function(String root) movedTo,
) async {
  final now = _root(current);
  if (now == null || !now.startsWith('https://')) return null;
  final target = _root(await movedTo(now) ?? '');
  if (target == null || target == now || !target.startsWith('https://')) return null;
  // The new server must confirm it is the service's home.
  final confirmed = _root(await movedTo(target) ?? '');
  return confirmed == target ? target : null;
}

String? _root(String url) {
  final uri = Uri.tryParse(url.trim());
  if (uri == null || uri.host.isEmpty || !(uri.scheme == 'https' || uri.scheme == 'http')) return null;
  return Uri(scheme: uri.scheme, host: uri.host.toLowerCase(), port: uri.hasPort ? uri.port : null).toString();
}
