import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Secure token/credential storage backed by Android Keystore / iOS Keychain.
/// Never store auth material in plaintext SharedPreferences.
class SecureStorage {
  SecureStorage(this._storage);

  final FlutterSecureStorage _storage;

  static const _kAccessToken = 'access_token';
  static const _kServiceNumber = 'service_number';

  Future<void> saveToken(String token) => _storage.write(key: _kAccessToken, value: token);
  Future<String?> readToken() => _storage.read(key: _kAccessToken);
  Future<void> clearToken() => _storage.delete(key: _kAccessToken);

  Future<void> saveServiceNumber(String value) => _storage.write(key: _kServiceNumber, value: value);
  Future<String?> readServiceNumber() => _storage.read(key: _kServiceNumber);

  Future<void> clearAll() => _storage.deleteAll();
}

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage(const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  ));
});
