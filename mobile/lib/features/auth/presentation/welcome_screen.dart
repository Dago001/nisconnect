import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import '../../../shared/widgets/server_settings_dialog.dart';
import '../../../shared/widgets/primary_button.dart';

/// Welcome screen with NIS branding and entry points.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Image.asset('assets/images/hq-exterior-night.jpg', fit: BoxFit.cover),
                  Container(color: AppColors.darkGreen.withValues(alpha: 0.55)),
                  Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Image.asset('assets/logos/nis-logo.jpg', width: 96),
                        const SizedBox(height: 12),
                        Text('NISconnect',
                            style: AppTypography.display.copyWith(color: AppColors.white)),
                        Text('Secure officer communication',
                            style: AppTypography.body.copyWith(color: AppColors.lightGreen)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  PrimaryButton(
                    label: 'Create Account',
                    onPressed: () => context.go('/onboarding'),
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton(
                    onPressed: () => context.go('/login'),
                    style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                    child: const Text('I already have an account'),
                  ),
                  const SizedBox(height: 4),
                  const Center(child: ServerAddressButton()),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
