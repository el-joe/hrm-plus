import '../../features/auth/application/auth_controller.dart';
import 'routes.dart';

/// The router's redirect rule, as a pure function (unit-tested).
///
/// - session unknown → splash (it restores the session),
/// - signed out → login (remembering where the user wanted to go),
/// - signed in on a public page → home (or the remembered target).
String? authRedirect(AuthStatus status, Uri location) {
  final path = location.path;
  final isPublic = Routes.public.contains(path);

  switch (status) {
    case AuthStatus.unknown:
      if (path == Routes.splash) return null;
      final from = path == '/' ? null : location.toString();
      return from == null
          ? Routes.splash
          : Uri(
              path: Routes.splash,
              queryParameters: {'from': from},
            ).toString();
    case AuthStatus.unauthenticated:
      if (isPublic && path != Routes.splash) return null;
      final from =
          location.queryParameters['from'] ??
          (isPublic || path == '/' ? null : location.toString());
      return from == null
          ? Routes.login
          : Uri(path: Routes.login, queryParameters: {'from': from}).toString();
    case AuthStatus.authenticated:
      if (!isPublic && path != '/') return null;
      final from = location.queryParameters['from'];
      if (from != null && from.startsWith('/') && !_isPublicTarget(from)) {
        return from;
      }
      return Routes.home;
  }
}

bool _isPublicTarget(String target) =>
    Routes.public.contains(Uri.parse(target).path);
