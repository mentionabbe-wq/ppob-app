import '../../../core/network/dio_client.dart';
import '../../../core/storage/secure_storage.dart';
import '../domain/user.dart';

/// Satu-satunya jalur akses data autentikasi. Presentation layer
/// tidak pernah menyentuh Dio secara langsung.
class AuthRepository {
  const AuthRepository(this._client, this._storage);

  final DioClient _client;
  final SecureStorage _storage;

  Future<User> login({
    required String email,
    required String password,
    String? fcmToken,
  }) async {
    final response = await _client.post('/auth/login', data: {
      'email': email,
      'password': password,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });

    return _persistSession(response['data'] as Map<String, dynamic>);
  }

  Future<User> loginWithGoogle(String idToken, {String? fcmToken}) async {
    final response = await _client.post('/auth/google', data: {
      'id_token': idToken,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });

    return _persistSession(response['data'] as Map<String, dynamic>);
  }

  Future<void> register({
    required String name,
    required String email,
    required String password,
    String? phone,
    String? referralCode,
  }) =>
      _client.post('/auth/register', data: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
        if (phone != null && phone.isNotEmpty) 'phone': phone,
        if (referralCode != null && referralCode.isNotEmpty) 'referral_code': referralCode,
      });

  Future<void> sendOtp({required String email, required String purpose}) =>
      _client.post('/auth/otp/send', data: {'email': email, 'purpose': purpose});

  Future<void> verifyOtp({required String email, required String otp}) =>
      _client.post('/auth/otp/verify', data: {'email': email, 'otp': otp});

  Future<void> forgotPassword(String email) =>
      _client.post('/auth/password/forgot', data: {'email': email});

  Future<void> resetPassword({
    required String email,
    required String otp,
    required String password,
  }) =>
      _client.post('/auth/password/reset', data: {
        'email': email,
        'otp': otp,
        'password': password,
        'password_confirmation': password,
      });

  Future<User> me() async {
    final response = await _client.get('/auth/me');

    return User.fromJson(response['data'] as Map<String, dynamic>);
  }

  Future<void> logout() async {
    try {
      await _client.post('/auth/logout');
    } finally {
      // Token lokal selalu dibersihkan, walau permintaan ke server gagal.
      await _storage.clearToken();
    }
  }

  Future<void> updateFcmToken(String token) =>
      _client.put('/profile/fcm-token', data: {'fcm_token': token});

  Future<User> _persistSession(Map<String, dynamic> data) async {
    await _storage.saveToken(data['token'] as String);

    return User.fromJson(data['user'] as Map<String, dynamic>);
  }
}
