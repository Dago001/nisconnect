import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:livekit_client/livekit_client.dart';

import '../data/call_repository.dart';

enum CallPhase { idle, connecting, connected, ended, failed }

class CallUiState {
  const CallUiState({
    this.phase = CallPhase.idle,
    this.room,
    this.micEnabled = true,
    this.cameraEnabled = false,
    this.speakerOn = true,
    this.error,
    this.session,
  });

  final CallPhase phase;
  final Room? room;
  final bool micEnabled;
  final bool cameraEnabled;
  final bool speakerOn;
  final String? error;
  final CallSession? session;

  CallUiState copyWith({
    CallPhase? phase,
    Room? room,
    bool? micEnabled,
    bool? cameraEnabled,
    bool? speakerOn,
    String? error,
    CallSession? session,
  }) =>
      CallUiState(
        phase: phase ?? this.phase,
        room: room ?? this.room,
        micEnabled: micEnabled ?? this.micEnabled,
        cameraEnabled: cameraEnabled ?? this.cameraEnabled,
        speakerOn: speakerOn ?? this.speakerOn,
        error: error,
        session: session ?? this.session,
      );
}

/// Owns the active call: signals the backend, connects to the LiveKit room and
/// exposes controls. Media itself flows through the SFU, not the API.
class CallController extends StateNotifier<CallUiState> {
  CallController(this._repo) : super(const CallUiState());

  final CallRepository _repo;

  Future<void> startOutgoing(String conversationId, String type) async {
    state = state.copyWith(phase: CallPhase.connecting);
    try {
      final session = await _repo.initiate(conversationId, type);
      await _connect(session);
    } catch (e) {
      state = state.copyWith(phase: CallPhase.failed, error: e.toString());
    }
  }

  Future<void> acceptIncoming(String callId) async {
    state = state.copyWith(phase: CallPhase.connecting);
    try {
      final session = await _repo.answer(callId);
      await _connect(session);
    } catch (e) {
      state = state.copyWith(phase: CallPhase.failed, error: e.toString());
    }
  }

  Future<void> _connect(CallSession session) async {
    final room = Room();
    await room.connect(session.livekitUrl, session.token);
    final isVideo = session.type == 'video';
    await room.localParticipant?.setMicrophoneEnabled(true);
    if (isVideo) {
      await room.localParticipant?.setCameraEnabled(true);
    }
    state = state.copyWith(
      phase: CallPhase.connected,
      room: room,
      session: session,
      cameraEnabled: isVideo,
    );
  }

  Future<void> toggleMic() async {
    final next = !state.micEnabled;
    await state.room?.localParticipant?.setMicrophoneEnabled(next);
    state = state.copyWith(micEnabled: next);
  }

  Future<void> toggleCamera() async {
    final next = !state.cameraEnabled;
    await state.room?.localParticipant?.setCameraEnabled(next);
    state = state.copyWith(cameraEnabled: next);
  }

  Future<void> hangUp() async {
    final session = state.session;
    try {
      await state.room?.disconnect();
      if (session != null) await _repo.end(session.callId);
    } finally {
      state = state.copyWith(phase: CallPhase.ended);
    }
  }

  Future<void> decline(String callId) async {
    await _repo.decline(callId);
    state = state.copyWith(phase: CallPhase.ended);
  }

  @override
  void dispose() {
    state.room?.dispose();
    super.dispose();
  }
}

final callControllerProvider =
    StateNotifierProvider.autoDispose<CallController, CallUiState>((ref) {
  return CallController(ref.watch(callRepositoryProvider));
});
