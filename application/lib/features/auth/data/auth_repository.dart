import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../../../core/storage/token_storage.dart';
import '../domain/user.dart';

/// Token lifecycle and password recovery (`/api/v1/auth/*`, `app/config`).
class AuthRepository {
  AuthRepository(this._api, this._storage, this._config);

  final ApiClient _api;
  final TokenStorage _storage;
  final AppConfig _config;

  /// Proactively rotate the token when it expires within this window (an
  /// already-expired token cannot be refreshed: Sanctum rejects it).
  static const refreshWindow = Duration(days: 7);

  Future<bool> hasToken() async => (await _storage.read()) != null;

  /// POST auth/login → stores the token, returns the user.
  Future<User> login(String email, String password) async {
    final response = await _api.post(
      'auth/login',
      auth: false,
      data: {
        'email': email.trim(),
        'password': password,
        'device_name': _deviceName(),
      },
    );
    final data = response.json;
    await _storage.save(
      AuthToken(token: data.str('token'), expiresAt: data.date('expires_at')),
    );
    return User.fromJson(data.obj('user') ?? const {});
  }

  /// GET auth/me.
  Future<User> me() async => User.fromJson((await _api.get('auth/me')).json);

  /// Rotates the token if it is close to expiry. Returns false only when the
  /// server refused (the session is gone).
  Future<void> refreshIfNeeded({DateTime? now}) async {
    final token = await _storage.read();
    if (token == null || !token.expiresWithin(refreshWindow, now: now)) return;
    await refresh();
  }

  /// POST auth/refresh (rotates: the old token is revoked).
  Future<void> refresh() async {
    final data = (await _api.post('auth/refresh')).json;
    await _storage.save(
      AuthToken(token: data.str('token'), expiresAt: data.date('expires_at')),
    );
  }

  /// POST auth/logout (the server also forgets this device's push token).
  /// The local token is cleared even if the request fails (offline).
  Future<void> logout() async {
    try {
      if (await hasToken()) await _api.post('auth/logout');
    } catch (_) {
      // Signing out locally must always succeed.
    } finally {
      await _storage.clear();
    }
  }

  Future<void> clearLocal() => _storage.clear();

  /// Public app configuration; null when not reachable or not deployed yet.
  Future<AppRemoteConfig?> appConfig(String languageCode) async {
    try {
      final response = await _api.get('app/config', auth: false);
      return AppRemoteConfig.fromJson(response.json, languageCode);
    } catch (_) {
      return null;
    }
  }

  /// POST auth/password/forgot → always 200 (no account probing).
  Future<ForgotPasswordResult> forgotPassword(String email) async {
    final response = await _api.post(
      'auth/password/forgot',
      auth: false,
      data: {'email': email.trim()},
    );
    final data = response.json;
    return ForgotPasswordResult(
      message: response.message,
      codeLength: data.integer('code_length', 6),
      resendAfterSeconds: data.integer('resend_after', 60),
    );
  }

  /// POST auth/password/verify-otp → 422 `errors.code` when wrong.
  Future<void> verifyOtp(String email, String code) => _api.post(
    'auth/password/verify-otp',
    auth: false,
    data: {'email': email.trim(), 'code': code},
  );

  /// POST auth/password/reset → every token of the account is revoked.
  Future<String?> resetPassword({
    required String email,
    required String code,
    required String password,
  }) async {
    final response = await _api.post(
      'auth/password/reset',
      auth: false,
      data: {
        'email': email.trim(),
        'code': code,
        'password': password,
        'password_confirmation': password,
      },
    );
    return response.message;
  }

  /// PATCH me/profile {locale} — server texts and pushes follow it.
  Future<void> updateLocale(String locale) async {
    await _api.patch('me/profile', data: {'locale': locale});
  }

  String _deviceName() {
    final platform = kIsWeb ? 'web' : defaultTargetPlatform.name;
    return 'HRM Plus $platform ${_config.appVersion}';
  }
}

class ForgotPasswordResult {
  const ForgotPasswordResult({
    this.message,
    required this.codeLength,
    required this.resendAfterSeconds,
  });

  final String? message;
  final int codeLength;
  final int resendAfterSeconds;
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStorageProvider),
    ref.watch(appConfigProvider),
  ),
);

final remoteConfigProvider = FutureProvider<AppRemoteConfig>((ref) async {
  final language = ref.watch(
    settingsProvider.select((s) => s.locale.languageCode),
  );
  return await ref.watch(authRepositoryProvider).appConfig(language) ??
      AppRemoteConfig.fallback;
});
