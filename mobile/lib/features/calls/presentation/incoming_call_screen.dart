import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import 'call_controller.dart';
import 'in_call_screen.dart';

/// Incoming call UI (voice/video). Triggered by a `call.incoming` realtime
/// event or a CallKit/FCM notification while the app is backgrounded.
class IncomingCallScreen extends ConsumerWidget {
  const IncomingCallScreen({
    super.key,
    required this.callId,
    required this.callerName,
    required this.type,
  });

  final String callId;
  final String callerName;
  final String type;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(callControllerProvider.notifier);

    return Scaffold(
      backgroundColor: AppColors.darkGreen,
      body: SafeArea(
        child: Column(
          children: [
            const Spacer(),
            const CircleAvatar(
              radius: 48,
              backgroundColor: AppColors.secondaryGreen,
              child: Icon(Icons.person, size: 56, color: Colors.white),
            ),
            const SizedBox(height: 16),
            Text(callerName, style: AppTypography.h1.copyWith(color: AppColors.white)),
            Text(
              'Incoming ${type == 'video' ? 'video' : 'voice'} call',
              style: AppTypography.body.copyWith(color: AppColors.lightGreen),
            ),
            const Spacer(),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 48, vertical: 40),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _action(Icons.call_end, AppColors.error, 'Decline', () async {
                    await controller.decline(callId);
                    if (context.mounted) context.pop();
                  }),
                  _action(Icons.call, AppColors.success, 'Accept', () async {
                    await controller.acceptIncoming(callId);
                    if (context.mounted) {
                      Navigator.of(context).pushReplacement(
                        MaterialPageRoute(builder: (_) => InCallScreen(title: callerName)),
                      );
                    }
                  }),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _action(IconData icon, Color color, String label, VoidCallback onTap) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        GestureDetector(
          onTap: onTap,
          child: CircleAvatar(radius: 34, backgroundColor: color, child: Icon(icon, color: Colors.white, size: 30)),
        ),
        const SizedBox(height: 8),
        Text(label, style: AppTypography.label.copyWith(color: AppColors.white)),
      ],
    );
  }
}
