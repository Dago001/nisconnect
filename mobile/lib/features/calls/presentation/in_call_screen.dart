import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:livekit_client/livekit_client.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';
import 'call_controller.dart';

/// Active voice/video call. Renders the first remote video track (if any) with
/// a self-view, plus mute/camera/hang-up controls.
class InCallScreen extends ConsumerWidget {
  const InCallScreen({super.key, required this.title});
  final String title;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(callControllerProvider);
    final controller = ref.read(callControllerProvider.notifier);

    ref.listen(callControllerProvider, (prev, next) {
      if (next.phase == CallPhase.ended && context.canPop()) context.pop();
    });

    return Scaffold(
      backgroundColor: AppColors.darkGreen,
      body: SafeArea(
        child: Column(
          children: [
            Expanded(child: _stage(state)),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Text(
                switch (state.phase) {
                  CallPhase.connecting => 'Connecting…',
                  CallPhase.connected => title,
                  CallPhase.failed => state.error ?? 'Call failed',
                  _ => 'Ended',
                },
                style: AppTypography.title.copyWith(color: AppColors.white),
              ),
            ),
            _controls(state, controller),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _stage(CallUiState state) {
    final room = state.room;
    if (room == null) {
      return const Center(child: CircularProgressIndicator(color: Colors.white));
    }
    // First remote participant's video track, if publishing.
    VideoTrack? remote;
    for (final p in room.remoteParticipants.values) {
      for (final pub in p.videoTrackPublications) {
        if (pub.track != null) {
          remote = pub.track as VideoTrack;
          break;
        }
      }
    }
    if (remote != null) {
      return VideoTrackRenderer(remote);
    }
    return const Center(
      child: Icon(Icons.person, size: 96, color: Colors.white54),
    );
  }

  Widget _controls(CallUiState state, CallController c) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
      children: [
        _btn(state.micEnabled ? Icons.mic : Icons.mic_off, AppColors.secondaryGreen, c.toggleMic),
        if (state.session?.type == 'video')
          _btn(state.cameraEnabled ? Icons.videocam : Icons.videocam_off, AppColors.secondaryGreen, c.toggleCamera),
        _btn(Icons.call_end, AppColors.error, c.hangUp),
      ],
    );
  }

  Widget _btn(IconData icon, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: CircleAvatar(radius: 30, backgroundColor: color, child: Icon(icon, color: Colors.white)),
    );
  }
}
