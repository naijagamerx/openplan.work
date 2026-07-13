import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../auth/token_store.dart';
import '../models/api_envelope.dart';
import '../models/api_exception.dart';

/// Thin wrapper over Dio that encodes the backend's conventions in one place:
///  - attaches the device token as `Authorization: Bearer` (+ `X-Device-Token`
///    fallback for servers that strip Authorization),
///  - unwraps the `{success, data}` envelope, returning `data` on success and
///    throwing [ApiException] on `success:false`,
///  - surfaces 401 so callers can drop the token and return to login.
class ApiClient {
  ApiClient(this._tokenStore, {Dio? dio}) : _dio = dio ?? Dio() {
    _dio.options
      ..baseUrl = AppConfig.baseUrl
      ..connectTimeout = AppConfig.connectTimeout
      ..receiveTimeout = AppConfig.receiveTimeout
      ..headers['Accept'] = 'application/json'
      // Let 4xx flow through to _send so we can read the JSON body; only 5xx
      // becomes a DioException.
      ..validateStatus = (s) => s != null && s < 500;

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _tokenStore.readToken();
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
          options.headers['X-Device-Token'] = token;
        }
        debugPrint('[API] ${options.method} ${options.path} '
            'token=${token == null || token.isEmpty ? 'NULL' : 'present(${token.length})'}');
        handler.next(options);
      },
    ));
  }

  final Dio _dio;
  final TokenStore _tokenStore;

  Future<dynamic> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get(path, queryParameters: query));

  Future<dynamic> post(String path, {Object? body, Map<String, dynamic>? query}) =>
      _send(() => _dio.post(path, data: body, queryParameters: query));

  Future<dynamic> put(String path, {Object? body, Map<String, dynamic>? query}) =>
      _send(() => _dio.put(path, data: body, queryParameters: query));

  Future<dynamic> delete(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.delete(path, queryParameters: query));

  /// Upload a file via multipart/form-data. The backend reads `$_FILES[field]`,
  /// so [field] must match the expected upload field name (e.g. `file`).
  /// Optional text [fields] are sent alongside the file (e.g. `name`).
  Future<dynamic> upload(
    String path, {
    required String field,
    required String filePath,
    String? fileName,
    String? contentType,
    Map<String, dynamic>? fields,
    Map<String, dynamic>? query,
  }) =>
      _send(() async {
        final form = FormData();
        if (fields != null) {
          fields.forEach((k, v) => form.fields.add(MapEntry(k, v.toString())));
        }
        form.files.add(MapEntry(
          field,
          await MultipartFile.fromFile(filePath,
              filename: fileName,
              contentType: contentType != null
                  ? DioMediaType.parse(contentType)
                  : null),
        ));
        return _dio.post(path, data: form, queryParameters: query);
      });

  /// Fetch raw response bytes (token attached via the interceptor). Used for
  /// binary downloads such as admin payment-proof images, which must NOT be
  /// fetched via Image.network (that wouldn't carry the Authorization header).
  Future<List<int>> getBytes(String path, {Map<String, dynamic>? query}) async {
    try {
      final res = await _dio.get<List<int>>(
        path,
        queryParameters: query,
        options: Options(responseType: ResponseType.bytes),
      );
      return res.data ?? const [];
    } on DioException catch (e) {
      throw ApiException(
        e.message ?? 'Network error',
        statusCode: e.response?.statusCode,
      );
    }
  }

  Future<dynamic> _send(Future<Response<dynamic>> Function() run) async {
    Response<dynamic> res;
    try {
      res = await run();
    } on DioException catch (e) {
      throw ApiException(
        e.message ?? 'Network error',
        statusCode: e.response?.statusCode,
      );
    }

    final status = res.statusCode ?? 0;
    final raw = res.data;
    final map = raw is Map<String, dynamic> ? raw : <String, dynamic>{};
    final env = ApiEnvelope.fromJson(map);

    if (status == 401) {
      debugPrint('[API] 401 ${res.requestOptions.path} -> ${env.message}');
      throw ApiException(env.message ?? 'Unauthorized',
          statusCode: 401, code: env.errorCode);
    }
    if (!env.success) {
      debugPrint('[API] fail($status) ${res.requestOptions.path} -> ${env.message}');
      throw ApiException(env.message ?? 'Request failed',
          statusCode: status, code: env.errorCode);
    }
    return env.data;
  }
}
