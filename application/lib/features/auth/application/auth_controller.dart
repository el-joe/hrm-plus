import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failures.dart';
import '../data/auth_repository.dart';
import '../domain/user.dart';

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  const AuthState._(this.status, {this.user, this.sessionExpired = false});

  const AuthState.unknown() : this._(AuthStatus.unknown);
  const AuthState.authenticated(User? user)
    : this._(AuthStatus.authenticated, user: user);
  const AuthState.unauthenticated({bool sessionExpired = false})
    : this._(AuthStatus.unauthenticated, sessionExpired: sessionExpired);

  final AuthStatus status;

  /// Null when the session was restored while the server was unreachable.
  final User? user;

  /// True when the server ended the session (shown on the login screen).
  final bool sessionExpired;

  bool get isAuthenticated => status == AuthStatus.authenticated;
}

/// Session state for the router guard and the screens.
class AuthController extends Notifier<AuthState> {
  AuthRepository get _repo => ref.read(authRepositoryProvider);

  /// Hooks run after sign-in and before sign-out (push registration).
  final List<Future<void> Function()> _afterLogin = [];
  final List<Future<void> Function()> _beforeLogout = [];

  void onAfterLogin(Future<void> Function() hook) => _afterLogin.add(hook);
  void onBeforeLogout(Future<void> Function() hook) => _beforeLogout.add(hook);

  @override
  AuthState build() => const AuthState.unknown();

  /// Called by the splash screen: is there a usable stored session?
  Future<void> restore() async {
    if (!await _repo.hasToken()) {
      state = const AuthState.unauthenticated();
      return;
    }
    try {
      await _repo.refreshIfNeeded();
      final user = await _repo.me();
      state = AuthState.authenticated(user);
      _runHooks(_afterLogin);
    } on UnauthorizedFailure {
      await _repo.clearLocal();
      state = const AuthState.unauthenticated(sessionExpired: true);
    } on ForbiddenFailure {
      // e.g. the account became an admin account or was disabled.
      await _repo.clearLocal();
      state = const AuthState.unauthenticated();
    } on Failure {
      // Offline / timeout / server error: keep the session, screens show
      // their own offline states with retry.
      state = const AuthState.authenticated(null);
    }
  }

  /// Throws a [Failure] (ValidationFailure for wrong credentials).
  Future<void> login(String email, String password) async {
    final user = await _repo.login(email, password);
    state = AuthState.authenticated(user);
    _runHooks(_afterLogin);
  }

  Future<void> logout() async {
    for (final hook in _beforeLogout) {
      try {
        await hook();
      } catch (_) {}
    }
    await _repo.logout();
    state = const AuthState.unauthenticated();
  }

  /// The API client gave up on a 401 (refresh failed).
  Future<void> sessionExpired() async {
    if (state.status == AuthStatus.unauthenticated) return;
    await _repo.clearLocal();
    state = const AuthState.unauthenticated(sessionExpired: true);
  }

  void _runHooks(List<Future<void> Function()> hooks) {
    for (final hook in hooks) {
      unawaited(hook().catchError((Object _) {}));
    }
  }
}

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);
