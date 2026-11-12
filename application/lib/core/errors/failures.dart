/// Typed failures raised by the data layer. Screens never see a
/// `DioException`; they get one of these and render it with
/// `failureMessage()` (lib/core/errors/failure_message.dart).
sealed class Failure implements Exception {
  const Failure({this.message, this.statusCode});

  /// Message from the server envelope (already in the request language), if any.
  final String? message;
  final int? statusCode;

  @override
  String toString() => '$runtimeType($statusCode, $message)';
}

/// No network connection (or the host cannot be reached at all).
class OfflineFailure extends Failure {
  const OfflineFailure() : super();
}

/// Connect / send / receive timeout.
class TimeoutFailure extends Failure {
  const TimeoutFailure() : super();
}

/// 401 — the token is missing, expired or revoked, and refreshing failed.
class UnauthorizedFailure extends Failure {
  const UnauthorizedFailure({super.message}) : super(statusCode: 401);
}

/// 403.
class ForbiddenFailure extends Failure {
  const ForbiddenFailure({super.message}) : super(statusCode: 403);
}

/// 404.
class NotFoundFailure extends Failure {
  const NotFoundFailure({super.message}) : super(statusCode: 404);
}

/// 422 `validation_error`, with field errors keyed by the request field name.
class ValidationFailure extends Failure {
  const ValidationFailure({super.message, this.errors = const {}})
    : super(statusCode: 422);

  final Map<String, List<String>> errors;

  /// First error of [field], for an input's errorText.
  String? first(String field) {
    final list = errors[field];
    if (list != null && list.isNotEmpty) return list.first;
    // "attachments.0" style keys.
    for (final entry in errors.entries) {
      if (entry.key.startsWith('$field.') && entry.value.isNotEmpty) {
        return entry.value.first;
      }
    }
    return null;
  }

  List<String> get all => [for (final list in errors.values) ...list];
}

/// 429.
class RateLimitFailure extends Failure {
  const RateLimitFailure({super.message, this.retryAfterSeconds})
    : super(statusCode: 429);

  final int? retryAfterSeconds;
}

/// 5xx.
class ServerFailure extends Failure {
  const ServerFailure({super.message, super.statusCode});
}

/// Any other envelope error with a domain `code`, e.g. 422
/// `attendance_rejected` (its `data` carries the reasons).
class ApiFailure extends Failure {
  const ApiFailure({
    required this.code,
    super.message,
    super.statusCode,
    this.data,
  });

  final String code;
  final Object? data;
}

/// The response could not be understood (not the envelope we expect).
class UnexpectedFailure extends Failure {
  const UnexpectedFailure({super.message, super.statusCode});
}
