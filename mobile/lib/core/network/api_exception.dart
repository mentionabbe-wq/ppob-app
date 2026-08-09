import 'package:dio/dio.dart';

/// Kesalahan API dalam bentuk yang siap ditampilkan ke pengguna.
/// Widget tidak perlu tahu soal DioException.
class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.code = 'ERROR',
    this.statusCode,
    this.errors = const {},
  });

  final String message;
  final String code;
  final int? statusCode;
  final Map<String, List<String>> errors;

  bool get isUnauthenticated => statusCode == 401 || code == 'UNAUTHENTICATED';
  bool get isInsufficientBalance => code == 'INSUFFICIENT_BALANCE';
  bool get isValidation => statusCode == 422;

  /// Pesan galat pertama untuk sebuah field, dipakai form.
  String? fieldError(String field) => errors[field]?.firstOrNull;

  factory ApiException.fromDio(DioException e) {
    final response = e.response;
    final data = response?.data;

    if (data is Map<String, dynamic>) {
      return ApiException(
        message: (data['message'] as String?) ?? _defaultMessage(e),
        code: (data['code'] as String?) ?? 'ERROR',
        statusCode: response?.statusCode,
        errors: _parseErrors(data['errors']),
      );
    }

    return ApiException(
      message: _defaultMessage(e),
      statusCode: response?.statusCode,
    );
  }

  static Map<String, List<String>> _parseErrors(dynamic raw) {
    if (raw is! Map) return const {};

    return raw.map(
      (key, value) => MapEntry(
        key.toString(),
        (value is List ? value : [value]).map((e) => e.toString()).toList(),
      ),
    );
  }

  static String _defaultMessage(DioException e) => switch (e.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout =>
          'Koneksi timeout. Periksa jaringan Anda lalu coba lagi.',
        DioExceptionType.connectionError =>
          'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.',
        DioExceptionType.badCertificate =>
          'Sertifikat server tidak valid.',
        DioExceptionType.cancel => 'Permintaan dibatalkan.',
        _ => 'Terjadi kesalahan. Silakan coba beberapa saat lagi.',
      };

  @override
  String toString() => message;
}
