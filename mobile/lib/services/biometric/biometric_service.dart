import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';

/// Device-local biometric gate (Android BiometricPrompt / iOS Face ID/Touch ID).
/// Raw biometrics never leave the device and are never sent to the server — this
/// only unlocks access to the already-stored session token.
class BiometricService {
  final _auth = LocalAuthentication();

  Future<bool> isAvailable() async {
    // local_auth has no browser implementation; calling it on the web throws
    // and logs noise. Say so up front rather than relying on the catch below.
    if (kIsWeb) return false;
    try {
      return await _auth.canCheckBiometrics && await _auth.isDeviceSupported();
    } catch (_) {
      return false;
    }
  }

  Future<bool> authenticate({String reason = 'Unlock NISconnect'}) async {
    if (kIsWeb) return false;
    try {
      return await _auth.authenticate(
        localizedReason: reason,
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
        ),
      );
    } catch (_) {
      return false;
    }
  }
}

final biometricServiceProvider = Provider<BiometricService>((ref) => BiometricService());
