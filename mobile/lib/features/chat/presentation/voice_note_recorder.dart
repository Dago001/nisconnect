import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:record/record.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_typography.dart';

/// Press-and-hold voice note recorder. Reports the recorded file path and
/// duration on completion; cancels cleanly if the user aborts. Microphone
/// permission is requested by the `record` package.
class VoiceNoteRecorder extends StatefulWidget {
  const VoiceNoteRecorder({super.key, required this.onRecorded});

  /// Called with the recorded file path and duration in ms.
  final void Function(String path, int durationMs) onRecorded;

  @override
  State<VoiceNoteRecorder> createState() => _VoiceNoteRecorderState();
}

class _VoiceNoteRecorderState extends State<VoiceNoteRecorder> {
  final _recorder = AudioRecorder();
  bool _recording = false;
  Duration _elapsed = Duration.zero;
  Timer? _timer;

  Future<void> _start() async {
    if (!await _recorder.hasPermission()) return;
    final dir = DateTime.now().millisecondsSinceEpoch;
    final path = '${Directory.systemTemp.path}/vn_$dir.m4a';
    await _recorder.start(const RecordConfig(encoder: AudioEncoder.aacLc), path: path);
    setState(() {
      _recording = true;
      _elapsed = Duration.zero;
    });
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      setState(() => _elapsed += const Duration(seconds: 1));
    });
  }

  Future<void> _stop({required bool send}) async {
    _timer?.cancel();
    final path = await _recorder.stop();
    final ms = _elapsed.inMilliseconds;
    setState(() => _recording = false);
    if (send && path != null && ms > 500) {
      widget.onRecorded(path, ms);
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _recorder.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_recording) {
      return IconButton(
        icon: const Icon(Icons.mic, color: AppColors.primaryGreen),
        onPressed: _start,
        tooltip: 'Record voice note',
      );
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(icon: const Icon(Icons.delete, color: AppColors.error), onPressed: () => _stop(send: false)),
        Text(_format(_elapsed), style: AppTypography.caption),
        const SizedBox(width: 4),
        const Icon(Icons.fiber_manual_record, color: AppColors.error, size: 14),
        IconButton(icon: const Icon(Icons.send, color: AppColors.primaryGreen), onPressed: () => _stop(send: true)),
      ],
    );
  }

  String _format(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }
}
