import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Secure token/credential storage backed by Android Keystore / iOS Keychain.
/// Never store auth material in plaintext SharedPreferences.
class SecureStorage {
  SecureStorage(this._storage);

  final FlutterSecureStorage _storage;

  static const _kAccessToken = 'access_token';
  static const _kServiceNumber = 'service_number';
  static const _kServerUrl = 'server_url';

  Future<void> saveToken(String token) => _storage.write(key: _kAccessToken, value: token);
  Future<String?> readToken() => _storage.read(key: _kAccessToken);
  Future<void> clearToken() => _storage.delete(key: _kAccessToken);

  Future<void> saveServiceNumber(String value) => _storage.write(key: _kServiceNumber, value: value);
  Future<String?> readServiceNumber() => _storage.read(key: _kServiceNumber);

  Future<void> saveServerUrl(String? value) => value == null || value.isEmpty
      ? _storage.delete(key: _kServerUrl)
      : _storage.write(key: _kServerUrl, value: value);
  Future<String?> readServerUrl() => _storage.read(key: _kServerUrl);

  /// Signs out: clears credentials but keeps the chosen server address.
  Future<void> clearAll() async {
    final server = await readServerUrl();
    await _storage.deleteAll();
    if (server != null) await saveServerUrl(server);
  }
}

const _flutterSecureStorage = FlutterSecureStorage(
  aOptions: AndroidOptions(encryptedSharedPreferences: true),
  iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
);

/// Usable before the ProviderScope exists (e.g. in main()).
final secureStorage = SecureStorage(_flutterSecureStorage);

final secureStorageProvider = Provider<SecureStorage>((ref) => secureStorage);
