import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/network/api_client.dart';

/// Reports app foreground/background as presence to the backend
/// (PUT /users/me/presence). Attach as a WidgetsBindingObserver.
class PresenceService with WidgetsBindingObserver {
  PresenceService(this._api);
  final ApiClient _api;

  void start() {
    WidgetsBinding.instance.addObserver(this);
    _set('online');
  }

  void stop() {
    _set('offline');
    WidgetsBinding.instance.removeObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        _set('online');
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.hidden:
        break;
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
        _set('away');
        break;
    }
  }

  Future<void> _set(String presence) async {
    try {
      await _api.put('/users/me/presence', data: {'presence': presence});
    } catch (_) {
      // Presence is advisory; ignore failures.
    }
  }
}

final presenceServiceProvider = Provider<PresenceService>((ref) {
  return PresenceService(ref.watch(apiClientProvider));
});
