import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../errors/error_mapper.dart';
import '../errors/failures.dart';
import '../storage/token_storage.dart';
import 'api_response.dart';
import 'auth_interceptor.dart';
import 'json.dart';

/// The single entry point to `/api/v1`. Parses the envelope and turns every
/// error into a typed [Failure]; repositories only deal with [ApiResponse].
class ApiClient {
  ApiClient(this.dio);

  /// Production wiring: base URL, timeouts, JSON headers, the request
  /// language and the bearer / refresh interceptor.
  factory ApiClient.create({
    required AppConfig config,
    required TokenStorage storage,
    required String Function() languageCode,
    required Future<void> Function() onSessionExpired,
    HttpClientAdapter? adapter,
  }) {
    BaseOptions options() => BaseOptions(
      baseUrl: '${config.apiBaseUrl}/',
      connectTimeout: config.connectTimeout,
      receiveTimeout: config.receiveTimeout,
      sendTimeout: kIsWeb ? null : config.sendTimeout,
      headers: {'Accept': 'application/json'},
      responseType: ResponseType.json,
    );

    final language = InterceptorsWrapper(
      onRequest: (request, handler) {
        request.headers['Accept-Language'] = languageCode();
        handler.next(request);
      },
    );

    final refreshDio = Dio(options())..interceptors.add(language);
    final dio = Dio(options())
      ..interceptors.addAll([
        language,
        AuthInterceptor(
          storage: storage,
          refreshClient: refreshDio,
          onSessionExpired: onSessionExpired,
        ),
      ]);
    if (adapter != null) {
      dio.httpClientAdapter = adapter;
      refreshDio.httpClientAdapter = adapter;
    }
    return ApiClient(dio);
  }

  final Dio dio;

  Future<ApiResponse> get(
    String path, {
    Map<String, dynamic>? query,
    bool auth = true,
  }) => _send(
    () => dio.get<dynamic>(
      path,
      queryParameters: _clean(query),
      options: _options(auth),
    ),
  );

  Future<ApiResponse> post(
    String path, {
    Object? data,
    Map<String, dynamic>? query,
    bool auth = true,
    ProgressCallback? onSendProgress,
  }) => _send(
    () => dio.post<dynamic>(
      path,
      data: data,
      queryParameters: _clean(query),
      options: _options(auth),
      onSendProgress: onSendProgress,
    ),
  );

  Future<ApiResponse> put(String path, {Object? data}) =>
      _send(() => dio.put<dynamic>(path, data: data));

  Future<ApiResponse> patch(String path, {Object? data}) =>
      _send(() => dio.patch<dynamic>(path, data: data));

  Future<ApiResponse> delete(String path, {Object? data}) =>
      _send(() => dio.delete<dynamic>(path, data: data));

  /// Binary download (PDFs, attachments). [path] may be an absolute URL
  /// returned by the API (the token is only attached for the API host).
  Future<Uint8List> download(String path, {Map<String, dynamic>? query}) async {
    try {
      final response = await dio.get<List<int>>(
        path,
        queryParameters: _clean(query),
        options: Options(
          responseType: ResponseType.bytes,
          headers: {'Accept': 'application/pdf, application/octet-stream, */*'},
        ),
      );
      return Uint8List.fromList(response.data ?? const []);
    } on DioException catch (error) {
      throw _mapBytesError(error);
    }
  }

  Options? _options(bool auth) =>
      auth ? null : Options(extra: {ApiExtra.skipAuth: true});

  Map<String, dynamic>? _clean(Map<String, dynamic>? query) {
    if (query == null) return null;
    return {
      for (final entry in query.entries)
        if (entry.value != null && entry.value != '') entry.key: entry.value,
    };
  }

  Future<ApiResponse> _send(Future<Response<dynamic>> Function() call) async {
    final Response<dynamic> response;
    try {
      response = await call();
    } on DioException catch (error) {
      throw mapDioException(error);
    }
    return parseEnvelope(response.statusCode ?? 200, response.data);
  }

  /// Parses a 2xx body. A body with `"success": false` is still an error.
  static ApiResponse parseEnvelope(int status, Object? body) {
    if (body is! Map) {
      if (status == 204) return ApiResponse(statusCode: status);
      throw UnexpectedFailure(statusCode: status);
    }
    final json = body.cast<String, dynamic>();
    if (json['success'] == false) throw mapErrorResponse(status, json);
    return ApiResponse(
      statusCode: status,
      data: json['data'],
      message: json.strOrNull('message'),
      meta: json.obj('meta') ?? const {},
    );
  }

  Failure _mapBytesError(DioException error) {
    final data = error.response?.data;
    if (data is List<int>) {
      // Error bodies of byte requests arrive as bytes: decode the envelope.
      try {
        final decoded = utf8.decode(data, allowMalformed: true);
        final status = error.response?.statusCode ?? 0;
        if (decoded.trimLeft().startsWith('{')) {
          return mapErrorResponse(status, _tryDecode(decoded));
        }
        return mapErrorResponse(status, null);
      } catch (_) {
        // fall through
      }
    }
    return mapDioException(error);
  }

  static Object? _tryDecode(String text) {
    try {
      return jsonDecode(text);
    } catch (_) {
      return null;
    }
  }
}
