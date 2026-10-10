import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../services/biometric/biometric_service.dart';

/// Branded splash. Decides where to go based on whether a token exists.
class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});
  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _decide();
  }

  Future<void> _decide() async {
    final storage = ref.read(secureStorageProvider);
    final token = await storage.readToken();
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (!mounted) return;

    if (token == null) {
      context.go('/welcome');
      return;
    }

    // Biometric unlock gate: when the officer has it on (the default) and the
    // device supports it, require a successful biometric before entering.
    // Raw biometrics never leave the device.
    final biometric = ref.read(biometricServiceProvider);
    if (await storage.readBiometricUnlock() && await biometric.isAvailable()) {
      final ok = await biometric.authenticate(reason: 'Unlock NISconnect');
      if (!mounted) return;
      if (!ok) {
        context.go('/welcome');
        return;
      }
    }
    currentServiceNumber.value = await storage.readServiceNumber() ?? await _fetchServiceNumber() ?? '';
    if (!mounted) return;
    context.go('/home');
  }

  /// For sessions saved before the Service Number was stored locally.
  Future<String?> _fetchServiceNumber() async {
    try {
      final res = await ref.read(apiClientProvider).get('/users/me');
      final number = ((res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>)['service_number'] as String?;
      if (number != null) await ref.read(secureStorageProvider).saveServiceNumber(number);
      return number;
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.darkGreen,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset('assets/logos/nis-logo.jpg', width: 120),
            const SizedBox(height: 16),
            Text('NISconnect',
                style: AppTypography.display.copyWith(color: AppColors.white)),
            const SizedBox(height: 4),
            Text('Nigeria Immigration Service',
                style: AppTypography.body.copyWith(color: AppColors.lightGreen)),
          ],
        ),
      ),
    );
  }
}
