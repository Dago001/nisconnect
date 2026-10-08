import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/session/session.dart';
import '../../../core/storage/secure_storage.dart';
import '../domain/personnel_record.dart';

/// Talks to /api/v1/auth. The app never contacts the NIS personnel source
/// directly — verification is always mediated by the backend.
class AuthRepository {
  AuthRepository(this._api, this._storage);

  final ApiClient _api;
  final SecureStorage _storage;

  /// Step 1 — verify a numeric Service Number.
  /// Returns null when not verified (generic, anti-enumeration response).
  Future<({String verificationId, PersonnelRecord record})?> verifyServiceNumber(
      String serviceNumber) async {
    final res = await _api.post('/auth/verify-service-number',
        data: {'service_number': serviceNumber});
    final body = res.data as Map<String, dynamic>;
    if (body['verified'] != true) return null;
    return (
      verificationId: body['verification_id'] as String,
      record: PersonnelRecord.fromJson(body['record'] as Map<String, dynamic>),
    );
  }

  /// Step 2 — confirm identity + phone; OTP is sent.
  /// Returns the OTP only when the server is a test deployment that exposes
  /// it (`OTP_EXPOSE_IN_RESPONSE=true`, never in production); otherwise null.
  Future<String?> confirmIdentity(String verificationId, String phone) async {
    final res = await _api.post<Map<String, dynamic>>('/auth/confirm-identity',
        data: {'verification_id': verificationId, 'phone': phone});
    return res.data?['debug_code']?.toString();
  }

  Future<void> resendOtp(String verificationId) async {
    await _api.post('/auth/resend-otp', data: {'verification_id': verificationId});
  }

  /// Step 3 — verify OTP.
  Future<void> verifyOtp(String verificationId, String code) async {
    await _api.post('/auth/verify-otp',
        data: {'verification_id': verificationId, 'code': code});
  }

  /// Step 4 — set credentials + register device; persists the token.
  Future<void> setCredentials({
    required String verificationId,
    required String pin,
    String? password,
    required Map<String, dynamic> device,
  }) async {
    final res = await _api.post('/auth/set-credentials', data: {
      'verification_id': verificationId,
      'pin': pin,
      if (password != null) 'password': password,
      'device': device,
    });
    final body = res.data as Map<String, dynamic>;
    await _storage.saveToken(body['access_token'] as String);
    final serviceNumber = (body['user'] as Map<String, dynamic>)['service_number'] as String;
    await _storage.saveServiceNumber(serviceNumber);
    currentServiceNumber.value = serviceNumber;
  }

  Future<void> login({
    required String serviceNumber,
    required String pin,
    required Map<String, dynamic> device,
  }) async {
    final res = await _api.post('/auth/login',
        data: {'service_number': serviceNumber, 'pin': pin, 'device': device});
    final body = res.data as Map<String, dynamic>;
    await _storage.saveToken(body['access_token'] as String);
    await _storage.saveServiceNumber(serviceNumber);
    currentServiceNumber.value = serviceNumber;
  }

  /// Changes the sign-in PIN. Throws [ApiException] (422) on a wrong current PIN.
  Future<void> changePin(String currentPin, String newPin) async {
    await _api.put('/users/me/pin', data: {'current_pin': currentPin, 'new_pin': newPin});
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } finally {
      currentServiceNumber.value = null;
      await _storage.clearAll();
    }
  }
}

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(ref.watch(apiClientProvider), ref.watch(secureStorageProvider));
});
