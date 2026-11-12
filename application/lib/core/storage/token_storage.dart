import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

/// The Sanctum personal access token and its expiry.
class AuthToken {
  const AuthToken({required this.token, this.expiresAt});

  final String token;
  final DateTime? expiresAt;

  bool expiresWithin(Duration duration, {DateTime? now}) {
    final at = expiresAt;
    if (at == null) return false;
    return at.isBefore((now ?? DateTime.now()).add(duration));
  }
}

/// Where the bearer token lives. The production implementation uses the
/// platform keystore (Keychain / Android Keystore) via flutter_secure_storage.
abstract class TokenStorage {
  Future<AuthToken?> read();
  Future<void> save(AuthToken token);
  Future<void> clear();

  /// A random id for this installation (sent as `device_id`), created once.
  Future<String> deviceId();

  /// The FCM token last registered with the server (to unregister on logout).
  Future<String?> pushToken();
  Future<void> savePushToken(String? token);
}

class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage([FlutterSecureStorage? storage])
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth.token';
  static const _expiryKey = 'auth.expires_at';
  static const _deviceKey = 'device.id';
  static const _pushKey = 'push.token';

  // A small cache: the interceptor reads the token on every request.
  AuthToken? _cached;
  bool _loaded = false;

  @override
  Future<AuthToken?> read() async {
    if (_loaded) return _cached;
    final token = await _storage.read(key: _tokenKey);
    final expiry = await _storage.read(key: _expiryKey);
    _cached = token == null || token.isEmpty
        ? null
        : AuthToken(
            token: token,
            expiresAt: expiry == null ? null : DateTime.tryParse(expiry),
          );
    _loaded = true;
    return _cached;
  }

  @override
  Future<void> save(AuthToken token) async {
    await _storage.write(key: _tokenKey, value: token.token);
    if (token.expiresAt != null) {
      await _storage.write(
        key: _expiryKey,
        value: token.expiresAt!.toIso8601String(),
      );
    } else {
      await _storage.delete(key: _expiryKey);
    }
    _cached = token;
    _loaded = true;
  }

  @override
  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _expiryKey);
    _cached = null;
    _loaded = true;
  }

  @override
  Future<String> deviceId() async {
    final existing = await _storage.read(key: _deviceKey);
    if (existing != null && existing.isNotEmpty) return existing;
    final id = const Uuid().v4();
    await _storage.write(key: _deviceKey, value: id);
    return id;
  }

  @override
  Future<String?> pushToken() => _storage.read(key: _pushKey);

  @override
  Future<void> savePushToken(String? token) => token == null
      ? _storage.delete(key: _pushKey)
      : _storage.write(key: _pushKey, value: token);
}

/// For tests and for platforms without a keystore.
class InMemoryTokenStorage implements TokenStorage {
  InMemoryTokenStorage({AuthToken? initial}) : _token = initial;

  AuthToken? _token;
  String? _push;
  final String _deviceId = const Uuid().v4();

  @override
  Future<AuthToken?> read() async => _token;

  @override
  Future<void> save(AuthToken token) async => _token = token;

  @override
  Future<void> clear() async => _token = null;

  @override
  Future<String> deviceId() async => _deviceId;

  @override
  Future<String?> pushToken() async => _push;

  @override
  Future<void> savePushToken(String? token) async => _push = token;
}
