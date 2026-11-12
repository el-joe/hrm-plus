import 'dart:async';

import 'package:dio/dio.dart';

import '../network/json.dart';
import 'failures.dart';

/// Maps a transport error or an error envelope
/// (`{"success": false, "message", "code", "errors"?, "data"?}`) to a [Failure].
Failure mapDioException(DioException error) {
  switch (error.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.sendTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.transformTimeout:
      return const TimeoutFailure();
    case DioExceptionType.connectionError:
      return const OfflineFailure();
    case DioExceptionType.cancel:
      return const UnexpectedFailure(message: 'cancelled');
    case DioExceptionType.badCertificate:
      return const UnexpectedFailure(message: 'bad_certificate');
    case DioExceptionType.badResponse:
      final response = error.response;
      if (response == null) return const UnexpectedFailure();
      return mapErrorResponse(
        response.statusCode ?? 0,
        response.data,
        retryAfter: response.headers.value('retry-after'),
      );
    case DioExceptionType.unknown:
      final inner = error.error;
      if (inner is Failure) return inner;
      if (inner is TimeoutException) return const TimeoutFailure();
      // SocketException / HttpException / XMLHttpRequest error on web.
      final text = inner?.toString() ?? '';
      if (text.contains('SocketException') ||
          text.contains('HttpException') ||
          text.contains('Failed host lookup') ||
          text.contains('XMLHttpRequest')) {
        return const OfflineFailure();
      }
      return UnexpectedFailure(message: error.message);
  }
}

Failure mapErrorResponse(int status, Object? body, {String? retryAfter}) {
  final json = asJson(body);
  final message = json.strOrNull('message');
  final code = json.strOrNull('code');

  if (status == 422 && (code == null || code == 'validation_error')) {
    final errors = <String, List<String>>{};
    final raw = json.obj('errors') ?? const <String, dynamic>{};
    raw.forEach((key, value) {
      errors[key] = value is List
          ? [for (final item in value) item.toString()]
          : [value.toString()];
    });
    return ValidationFailure(message: message, errors: errors);
  }

  if (code != null &&
      code != 'unauthenticated' &&
      code != 'forbidden' &&
      code != 'not_found' &&
      code != 'too_many_requests' &&
      code != 'server_error' &&
      code != 'http_error' &&
      code != 'method_not_allowed') {
    return ApiFailure(
      code: code,
      message: message,
      statusCode: status,
      data: json['data'],
    );
  }

  return switch (status) {
    401 => UnauthorizedFailure(message: message),
    403 => ForbiddenFailure(message: message),
    404 => NotFoundFailure(message: message),
    429 => RateLimitFailure(
      message: message,
      retryAfterSeconds: int.tryParse(retryAfter ?? ''),
    ),
    >= 500 => ServerFailure(message: message, statusCode: status),
    _ => ApiFailure(
      code: code ?? 'http_error',
      message: message,
      statusCode: status,
      data: json['data'],
    ),
  };
}
