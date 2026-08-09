/// Konfigurasi lingkungan. Nilai diberikan saat build:
/// `flutter run --dart-define=API_BASE_URL=https://api.ppob.id/api/v1`
class AppConfig {
  const AppConfig._();

  static const String appName = 'PPOB App';

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    // 10.0.2.2 = localhost mesin host dari emulator Android.
    defaultValue: 'http://10.0.2.2:8080/api/v1',
  );

  static const String googleClientId = String.fromEnvironment('GOOGLE_CLIENT_ID');

  static const bool enableGoogleSignIn = googleClientId != '';

  static const Duration connectTimeout = Duration(seconds: 20);
  static const Duration receiveTimeout = Duration(seconds: 30);

  /// Interval polling status transaksi yang masih diproses.
  static const Duration statusPollInterval = Duration(seconds: 5);
  static const int statusPollMaxAttempt = 24; // ±2 menit

  static const int otpLength = 6;
  static const int pinLength = 6;
}
