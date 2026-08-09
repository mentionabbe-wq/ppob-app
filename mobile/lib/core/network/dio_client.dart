import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:pretty_dio_logger/pretty_dio_logger.dart';

import '../config/app_config.dart';
import '../storage/secure_storage.dart';
import 'api_exception.dart';

/// Klien HTTP tunggal aplikasi: menyisipkan JWT, menyegarkan token
/// yang kedaluwarsa, dan menormalkan galat menjadi [ApiException].
class DioClient {
  DioClient({
    required SecureStorage storage,
    required FutureOr<void> Function() onSessionExpired,
    Dio? dio,
  })  : _storage = storage,
        _onSessionExpired = onSessionExpired,
        _dio = dio ?? Dio() {
    _dio.options = BaseOptions(
      baseUrl: AppConfig.apiBaseUrl,
      connectTimeout: AppConfig.connectTimeout,
      receiveTimeout: AppConfig.receiveTimeout,
      headers: {'Accept': 'application/json'},
      // 4xx ditangani interceptor agar pesan server tetap terbaca.
      validateStatus: (status) => status != null && status < 500,
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: _onRequest,
        onResponse: _onResponse,
        onError: _onError,
      ),
    );

    if (kDebugMode) {
      _dio.interceptors.add(PrettyDioLogger(
        requestBody: true,
        responseBody: true,
        compact: true,
      ));
    }
  }

  final Dio _dio;
  final SecureStorage _storage;
  final FutureOr<void> Function() _onSessionExpired;

  bool _isRefreshing = false;

  Dio get raw => _dio;

  Future<void> _onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await _storage.readToken();

    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }

    handler.next(options);
  }

  /// Status 4xx lolos validateStatus, jadi diubah menjadi error di sini.
  void _onResponse(Response response, ResponseInterceptorHandler handler) {
    final status = response.statusCode ?? 0;

    if (status >= 400) {
      handler.reject(DioException(
        requestOptions: response.requestOptions,
        response: response,
        type: DioExceptionType.badResponse,
      ));
      return;
    }

    handler.next(response);
  }

  Future<void> _onError(DioException error, ErrorInterceptorHandler handler) async {
    final isUnauthorized = error.response?.statusCode == 401;
    final isRefreshCall = error.requestOptions.path.contains('/auth/refresh');

    if (isUnauthorized && !isRefreshCall && !_isRefreshing) {
      final refreshed = await _refreshToken();

      if (refreshed) {
        try {
          // Ulangi permintaan asli dengan token baru.
          final retry = await _dio.fetch(error.requestOptions);
          return handler.resolve(retry);
        } on DioException catch (e) {
          return handler.reject(e);
        }
      }

      await _storage.clearToken();
      await _onSessionExpired();
    }

    handler.reject(error);
  }

  Future<bool> _refreshToken() async {
    _isRefreshing = true;

    try {
      final response = await Dio(BaseOptions(baseUrl: AppConfig.apiBaseUrl)).post(
        '/auth/refresh',
        options: Options(headers: {
          'Authorization': 'Bearer ${await _storage.readToken()}',
          'Accept': 'application/json',
        }),
      );

      final token = response.data?['data']?['token'] as String?;

      if (token == null || token.isEmpty) return false;

      await _storage.saveToken(token);
      return true;
    } catch (_) {
      return false;
    } finally {
      _isRefreshing = false;
    }
  }

  // ── Pembungkus verba HTTP ──────────────────────────────────

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, {Object? data}) =>
      _send(() => _dio.post(path, data: data));

  Future<Map<String, dynamic>> put(String path, {Object? data}) =>
      _send(() => _dio.put(path, data: data));

  Future<Map<String, dynamic>> delete(String path, {Object? data}) =>
      _send(() => _dio.delete(path, data: data));

  Future<Map<String, dynamic>> upload(String path, FormData form) =>
      _send(() => _dio.post(path, data: form));

  Future<Map<String, dynamic>> _send(Future<Response> Function() request) async {
    try {
      final response = await request();
      final data = response.data;

      return data is Map<String, dynamic> ? data : <String, dynamic>{'data': data};
    } on DioException catch (e) {
      throw ApiException.fromDio(e);
    }
  }
}
