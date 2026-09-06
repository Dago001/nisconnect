import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';

/// Call history. Voice/video calling uses LiveKit (see docs/ARCHITECTURE §5);
/// the signalling API mints room tokens. History is loaded from /calls/history.
class CallsScreen extends StatelessWidget {
  const CallsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Calls')),
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.call_outlined, size: 56, color: AppColors.neutralGrey),
            const SizedBox(height: 12),
            Text('No recent calls', style: AppTypography.title),
            const SizedBox(height: 4),
            Text('Start a voice or video call from a chat or the directory.',
                textAlign: TextAlign.center,
                style: AppTypography.caption.copyWith(color: AppColors.neutralGrey)),
          ],
        ),
      ),
    );
  }
}
