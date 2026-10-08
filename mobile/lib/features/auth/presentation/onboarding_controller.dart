import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/auth_repository.dart';
import '../domain/personnel_record.dart';

enum OnboardingStep { serviceNumber, personnelRecord, phone, otp, credentials, done }

class OnboardingState {
  const OnboardingState({
    this.step = OnboardingStep.serviceNumber,
    this.loading = false,
    this.error,
    this.verificationId,
    this.record,
    this.phone,
    this.testCode,
  });

  final OnboardingStep step;
  final bool loading;
  final String? error;
  final String? verificationId;
  final PersonnelRecord? record;
  final String? phone;

  /// OTP echoed back by a test server (no SMS gateway); null in production.
  final String? testCode;

  OnboardingState copyWith({
    OnboardingStep? step,
    bool? loading,
    String? error,
    bool clearError = false,
    String? verificationId,
    PersonnelRecord? record,
    String? phone,
    String? testCode,
  }) {
    return OnboardingState(
      step: step ?? this.step,
      loading: loading ?? this.loading,
      error: clearError ? null : (error ?? this.error),
      verificationId: verificationId ?? this.verificationId,
      record: record ?? this.record,
      phone: phone ?? this.phone,
      testCode: testCode ?? this.testCode,
    );
  }
}

/// Drives the Service Number onboarding flow across its steps, calling the
/// backend and exposing loading/error state to the screens.
class OnboardingController extends StateNotifier<OnboardingState> {
  OnboardingController(this._repo) : super(const OnboardingState());

  final AuthRepository _repo;

  Future<void> verifyServiceNumber(String serviceNumber) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final result = await _repo.verifyServiceNumber(serviceNumber);
      if (result == null) {
        state = state.copyWith(
          loading: false,
          error: 'We could not verify this Service Number. Please check the number and try again.',
        );
        return;
      }
      state = state.copyWith(
        loading: false,
        step: OnboardingStep.personnelRecord,
        verificationId: result.verificationId,
        record: result.record,
      );
    } catch (e) {
      state = state.copyWith(loading: false, error: e.toString());
    }
  }

  void confirmIsMe() => state = state.copyWith(step: OnboardingStep.phone);

  Future<void> submitPhone(String phone) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final testCode = await _repo.confirmIdentity(state.verificationId!, phone);
      state = state.copyWith(
          loading: false, step: OnboardingStep.otp, phone: phone, testCode: testCode);
    } catch (e) {
      state = state.copyWith(loading: false, error: e.toString());
    }
  }

  Future<bool> verifyOtp(String code) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      await _repo.verifyOtp(state.verificationId!, code);
      state = state.copyWith(loading: false, step: OnboardingStep.credentials);
      return true;
    } catch (e) {
      state = state.copyWith(loading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> setCredentials(String pin, Map<String, dynamic> device) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      await _repo.setCredentials(
        verificationId: state.verificationId!,
        pin: pin,
        device: device,
      );
      state = state.copyWith(loading: false, step: OnboardingStep.done);
      return true;
    } catch (e) {
      state = state.copyWith(loading: false, error: e.toString());
      return false;
    }
  }
}

final onboardingControllerProvider =
    StateNotifierProvider<OnboardingController, OnboardingState>((ref) {
  return OnboardingController(ref.watch(authRepositoryProvider));
});
