import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:hrm_plus/core/config/app_config.dart';
import 'package:hrm_plus/core/network/api_client.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';

/// A canned HTTP answer.
class FakeResponse {
  const FakeResponse(this.status, [this.body, this.headers = const {}]);

  /// `{"success": true, "message": null, "data": data, "meta"?: meta}`.
  factory FakeResponse.ok(
    Object? data, {
    int status = 200,
    Map<String, Object?>? meta,
    String? message,
  }) => FakeResponse(status, {
    'success': true,
    'message': message,
    'data': data,
    'meta': ?meta,
  });

  /// The API error envelope.
  factory FakeResponse.error(
    int status,
    String code, {
    String message = 'error',
    Map<String, List<String>>? errors,
    Object? data,
    Map<String, List<String>> headers = const {},
  }) => FakeResponse(status, {
    'success': false,
    'message': message,
    'code': code,
    'errors': ?errors,
    'data': ?data,
  }, headers);

  final int status;
  final Object? body;
  final Map<String, List<String>> headers;
}

typedef FakeHandler = FutureOr<FakeResponse> Function(RequestOptions request);

/// Dio adapter answering from a route table keyed by `"METHOD path"`, where
/// path is relative to `/api/v1/` (e.g. `"POST auth/login"`). A handler may
/// throw a [DioException] to simulate transport errors.
class FakeAdapter implements HttpClientAdapter {
  FakeAdapter([Map<String, FakeHandler>? routes]) : routes = routes ?? {};

  final Map<String, FakeHandler> routes;
  final List<RequestOptions> requests = [];

  void on(String route, FakeHandler handler) => routes[route] = handler;

  /// Requests made to [route] ("METHOD path").
  List<RequestOptions> sent(String route) =>
      requests.where((r) => _key(r) == route).toList();

  static String _key(RequestOptions r) {
    final path = r.uri.path;
    final index = path.indexOf('/api/v1/');
    final relative = index >= 0 ? path.substring(index + 8) : path;
    return '${r.method} $relative';
  }

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    final handler = routes[_key(options)];
    if (handler == null) {
      return ResponseBody.fromString(
        jsonEncode({
          'success': false,
          'message': 'Not found',
          'code': 'not_found',
        }),
        404,
        headers: {
          Headers.contentTypeHeader: [Headers.jsonContentType],
        },
      );
    }
    final response = await handler(options);
    final body = response.body;
    if (body is List<int>) {
      return ResponseBody.fromBytes(
        body,
        response.status,
        headers: {
          Headers.contentTypeHeader: ['application/pdf'],
          ...response.headers,
        },
      );
    }
    return ResponseBody.fromString(
      jsonEncode(body),
      response.status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
        ...response.headers,
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

/// Throws a transport error from a handler.
Never connectionError(RequestOptions request) => throw DioException(
  requestOptions: request,
  type: DioExceptionType.connectionError,
  error: 'SocketException: Network is unreachable',
);

Never timeoutError(RequestOptions request) => throw DioException(
  requestOptions: request,
  type: DioExceptionType.receiveTimeout,
);

const testConfig = AppConfig(
  apiBaseUrl: 'https://api.hrm.test/api/v1',
  appVersion: '1.0.0',
);

/// An ApiClient wired exactly like production, over [adapter].
ApiClient testApiClient(
  FakeAdapter adapter, {
  TokenStorage? storage,
  Future<void> Function()? onSessionExpired,
  String language = 'ar',
}) => ApiClient.create(
  config: testConfig,
  storage: storage ?? InMemoryTokenStorage(),
  languageCode: () => language,
  onSessionExpired: onSessionExpired ?? () async {},
  adapter: adapter,
);

/// Decodes a JSON request body.
Map<String, dynamic> jsonBody(RequestOptions request) {
  final data = request.data;
  if (data is Map) return data.cast<String, dynamic>();
  if (data is String) return (jsonDecode(data) as Map).cast<String, dynamic>();
  return const {};
}
