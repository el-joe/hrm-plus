import 'package:dio/dio.dart';

import '../storage/token_storage.dart';
import 'json.dart';

/// Request option `extra` keys.
abstract final class ApiExtra {
  /// Do not attach the bearer token (login, forgot password…).
  static const skipAuth = 'skipAuth';

  /// Set on the replayed request after a refresh, so it is not refreshed twice.
  static const retried = 'retried';
}

/// Adds `Authorization: Bearer …` to requests for the API host and, on a 401,
/// tries `POST auth/refresh` once and replays the request with the new token.
/// When refreshing is impossible it calls [onSessionExpired] (the app then
/// clears the session and the router guard sends the user to login).
///
/// A [QueuedInterceptor] serialises error handling, so parallel 401s trigger
/// one refresh; the others see that the stored token changed and just replay.
class AuthInterceptor extends QueuedInterceptor {
  AuthInterceptor({
    required this.storage,
    required this.refreshClient,
    required this.onSessionExpired,
  });

  final TokenStorage storage;

  /// A Dio without this interceptor (same base options), used for the refresh
  /// call and for replaying the original request.
  final Dio refreshClient;

  final Future<void> Function() onSessionExpired;

  bool _isApiHost(RequestOptions options) {
    final base = Uri.tryParse(options.baseUrl);
    final target = options.uri;
    if (base == null || base.host.isEmpty) return true;
    return target.host == base.host && target.port == base.port;
  }

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    // Never send the token to another host (e.g. an absolute file URL).
    if (options.extra[ApiExtra.skipAuth] != true && _isApiHost(options)) {
      final token = await storage.read();
      if (token != null) {
        options.headers['Authorization'] = 'Bearer ${token.token}';
      }
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final options = err.requestOptions;
    final eligible =
        err.response?.statusCode == 401 &&
        options.extra[ApiExtra.skipAuth] != true &&
        options.extra[ApiExtra.retried] != true &&
        _isApiHost(options);
    if (!eligible) return handler.next(err);

    final current = await storage.read();
    if (current == null) {
      await onSessionExpired();
      return handler.next(err);
    }

    String? fresh;
    final sent = options.headers['Authorization'];
    if (sent != 'Bearer ${current.token}') {
      // Another request refreshed the token while this one was in flight.
      fresh = current.token;
    } else {
      fresh = await _refresh(current.token, options.baseUrl);
    }

    if (fresh == null) {
      await onSessionExpired();
      return handler.next(err);
    }

    options.headers['Authorization'] = 'Bearer $fresh';
    options.extra[ApiExtra.retried] = true;
    final data = options.data;
    if (data is FormData) options.data = data.clone();

    try {
      final response = await refreshClient.fetch<dynamic>(options);
      handler.resolve(response);
    } on DioException catch (replayError) {
      if (replayError.response?.statusCode == 401) await onSessionExpired();
      handler.next(replayError);
    }
  }

  /// Rotates the token. Returns the new token, or null when the server refused.
  Future<String?> _refresh(String token, String baseUrl) async {
    try {
      final response = await refreshClient.post<dynamic>(
        'auth/refresh',
        options: Options(
          headers: {'Authorization': 'Bearer $token'},
          extra: {ApiExtra.skipAuth: true},
        ),
      );
      final data = asJson(asJson(response.data)['data']);
      final next = data.strOrNull('token');
      if (next == null || next.isEmpty) return null;
      await storage.save(
        AuthToken(token: next, expiresAt: data.date('expires_at')),
      );
      return next;
    } on DioException {
      return null;
    }
  }
}
