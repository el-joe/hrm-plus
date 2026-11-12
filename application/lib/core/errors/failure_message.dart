import '../l10n/l10n.dart';
import 'failures.dart';

/// User-facing text for any error thrown by the data layer.
String failureMessage(AppLocalizations l, Object? error) {
  if (error is! Failure) return l.errorUnexpected;
  return switch (error) {
    OfflineFailure() => l.errorOffline,
    TimeoutFailure() => l.errorTimeout,
    UnauthorizedFailure() => l.errorSessionExpired,
    ForbiddenFailure() => error.message ?? l.errorForbidden,
    NotFoundFailure() => l.errorNotFound,
    ValidationFailure() =>
      error.all.isNotEmpty
          ? error.all.first
          : (error.message ?? l.errorValidation),
    RateLimitFailure() => l.errorRateLimited,
    ServerFailure() => l.errorServer,
    ApiFailure() => error.message ?? l.errorUnexpected,
    UnexpectedFailure() => l.errorUnexpected,
  };
}

/// True for errors the user can fix by retrying when the network is back.
bool isConnectivityFailure(Object? error) =>
    error is OfflineFailure || error is TimeoutFailure;
