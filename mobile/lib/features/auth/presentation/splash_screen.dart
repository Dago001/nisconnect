import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

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
    final token = await ref.read(secureStorageProvider).readToken();
    await Future<void>.delayed(const Duration(milliseconds: 600));
    if (!mounted) return;

    if (token == null) {
      context.go('/welcome');
      return;
    }

    // Biometric unlock gate: when the device supports it, require a successful
    // biometric before entering. Raw biometrics never leave the device.
    final biometric = ref.read(biometricServiceProvider);
    if (await biometric.isAvailable()) {
      final ok = await biometric.authenticate(reason: 'Unlock NISconnect');
      if (!mounted) return;
      if (!ok) {
        context.go('/welcome');
        return;
      }
    }
    if (!mounted) return;
    context.go('/home');
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
