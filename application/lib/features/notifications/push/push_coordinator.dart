import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/providers.dart';
import '../../../core/router/deep_links.dart';
import '../../auth/application/auth_controller.dart';
import '../data/notifications_repository.dart';
import 'push_service.dart';

/// Overridden in main() with the initialised service.
final pushServiceProvider = Provider<PushService>(
  (ref) => const NoopPushService(),
);

/// Keeps the device token registered with `me/devices` for the signed-in
/// user (login, token refresh) and unregisters it on logout; turns taps on
/// notifications into routes.
class PushCoordinator {
  PushCoordinator(this._ref);

  final Ref _ref;
  final _routes = StreamController<String>.broadcast();
  StreamSubscription<String>? _tokenSub;
  StreamSubscription<PushMessage>? _openSub;
  StreamSubscription<PushMessage>? _foregroundSub;
  String? _pendingRoute;

  PushService get _push => _ref.read(pushServiceProvider);

  /// Routes to open (from notification taps).
  Stream<String> get routes => _routes.stream;

  /// A route from the notification that launched the app, consumed once the
  /// user is signed in.
  String? takePendingRoute() {
    final route = _pendingRoute;
    _pendingRoute = null;
    return route;
  }

  Future<void> start() async {
    final auth = _ref.read(authControllerProvider.notifier);
    auth.onAfterLogin(register);
    auth.onBeforeLogout(unregister);
    if (!_push.available) {
      debugPrint('[push] no provider configured; device not registered');
      return;
    }
    _tokenSub = _push.onTokenRefresh.listen((token) {
      if (_ref.read(authControllerProvider).isAuthenticated) {
        unawaited(_registerToken(token));
      }
    });
    _openSub = _push.onOpened.listen(
      (message) => _routes.add(routeForPayload(message.data)),
    );
    _foregroundSub = _push.onForeground.listen((_) {
      // Refresh the bell badge.
      _ref.invalidate(unreadCountProvider);
    });
    final initial = await _push.initialMessage();
    if (initial != null) _pendingRoute = routeForPayload(initial.data);
  }

  Future<void> register() async {
    if (!_push.available) return;
    final token = await _push.token();
    if (token == null) return;
    await _registerToken(token);
  }

  Future<void> _registerToken(String token) async {
    final config = _ref.read(appConfigProvider);
    final locale = _ref.read(settingsProvider).locale.languageCode;
    final platform = switch (defaultTargetPlatform) {
      TargetPlatform.iOS => 'ios',
      _ => 'android',
    };
    try {
      await _ref
          .read(notificationsRepositoryProvider)
          .registerDevice(
            token: token,
            platform: kIsWeb ? 'web' : platform,
            deviceName: 'HRM Plus ($platform)',
            appVersion: config.appVersion,
            locale: locale,
          );
      await _ref.read(tokenStorageProvider).savePushToken(token);
    } catch (error) {
      debugPrint('[push] device registration failed: $error');
    }
  }

  /// Called before auth/logout (which also drops devices of the token).
  Future<void> unregister() async {
    final storage = _ref.read(tokenStorageProvider);
    final token = await storage.pushToken();
    if (token == null) return;
    try {
      await _ref.read(notificationsRepositoryProvider).unregisterDevice(token);
    } catch (_) {
      // The server forgets the device with the token anyway.
    }
    await storage.savePushToken(null);
  }

  void dispose() {
    unawaited(_tokenSub?.cancel());
    unawaited(_openSub?.cancel());
    unawaited(_foregroundSub?.cancel());
    unawaited(_routes.close());
  }
}

final pushCoordinatorProvider = Provider<PushCoordinator>((ref) {
  final coordinator = PushCoordinator(ref);
  ref.onDispose(coordinator.dispose);
  return coordinator;
});
