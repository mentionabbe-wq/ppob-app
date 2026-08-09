import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Token JWT disimpan di Keychain (iOS) / EncryptedSharedPreferences
/// (Android) — bukan di SharedPreferences biasa.
class SecureStorage {
  SecureStorage([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
              iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
            );

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';
  static const _pinBiometricKey = 'pin_biometric_enabled';

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<bool> hasToken() async => (await readToken())?.isNotEmpty ?? false;

  Future<bool> isBiometricEnabled() async =>
      (await _storage.read(key: _pinBiometricKey)) == '1';

  Future<void> setBiometricEnabled(bool enabled) =>
      _storage.write(key: _pinBiometricKey, value: enabled ? '1' : '0');

  Future<void> clearAll() => _storage.deleteAll();
}
