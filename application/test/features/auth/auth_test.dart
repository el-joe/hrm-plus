import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/errors/failures.dart';
import 'package:hrm_plus/core/providers.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';
import 'package:hrm_plus/features/auth/application/auth_controller.dart';
import 'package:hrm_plus/features/auth/data/auth_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../helpers/fake_http.dart';
import '../../helpers/fixtures.dart';

Future<ProviderContainer> _container(
  FakeAdapter adapter,
  InMemoryTokenStorage storage,
) async {
  SharedPreferences.setMockInitialValues({});
  final prefs = await SharedPreferences.getInstance();
  late ProviderContainer container;
  container = ProviderContainer(
    retry: (_, _) => null,
    overrides: [
      sharedPreferencesProvider.overrideWithValue(prefs),
      tokenStorageProvider.overrideWithValue(storage),
      appConfigProvider.overrideWithValue(testConfig),
      apiClientProvider.overrideWith(
        (ref) => testApiClient(
          adapter,
          storage: storage,
          onSessionExpired: () =>
              container.read(authControllerProvider.notifier).sessionExpired(),
        ),
      ),
    ],
  );
  addTearDown(container.dispose);
  return container;
}

void main() {
  group('AuthRepository', () {
    test('login stores the token and returns the user', () async {
      final storage = InMemoryTokenStorage();
      final adapter = FakeAdapter()
        ..on(
          'POST auth/login',
          (_) => FakeResponse.ok(loginJson(token: '7|tok')),
        );
      final repo = AuthRepository(
        testApiClient(adapter, storage: storage),
        storage,
        testConfig,
      );

      final user = await repo.login(' employee1@hrmplus.test ', 'password');

      expect(user.employeeId, 24);
      expect((await storage.read())!.token, '7|tok');
      expect((await storage.read())!.expiresAt, isNotNull);
      final body = jsonBody(adapter.requests.single);
      expect(body['email'], 'employee1@hrmplus.test');
      expect(body['device_name'], isNotEmpty);
      expect(adapter.requests.single.headers['Authorization'], isNull);
    });

    test('wrong credentials → ValidationFailure, nothing stored', () async {
      final storage = InMemoryTokenStorage();
      final adapter = FakeAdapter()
        ..on(
          'POST auth/login',
          (_) => FakeResponse.error(
            422,
            'validation_error',
            errors: {
              'email': ['These credentials do not match our records.'],
            },
          ),
        );
      final repo = AuthRepository(
        testApiClient(adapter, storage: storage),
        storage,
        testConfig,
      );

      await expectLater(
        repo.login('employee1@hrmplus.test', 'wrong'),
        throwsA(isA<ValidationFailure>()),
      );
      expect(await storage.read(), isNull);
    });

    test('refreshIfNeeded rotates a token that expires soon', () async {
      final storage = InMemoryTokenStorage(
        initial: AuthToken(
          token: 'old',
          expiresAt: DateTime.now().add(const Duration(days: 2)),
        ),
      );
      final adapter = FakeAdapter()
        ..on(
          'POST auth/refresh',
          (_) => FakeResponse.ok(loginJson(token: 'rotated')),
        );
      final repo = AuthRepository(
        testApiClient(adapter, storage: storage),
        storage,
        testConfig,
      );

      await repo.refreshIfNeeded();

      expect((await storage.read())!.token, 'rotated');
    });

    test('refreshIfNeeded leaves a fresh token alone', () async {
      final storage = InMemoryTokenStorage(
        initial: AuthToken(
          token: 'fresh',
          expiresAt: DateTime.now().add(const Duration(days: 25)),
        ),
      );
      final adapter = FakeAdapter();
      final repo = AuthRepository(
        testApiClient(adapter, storage: storage),
        storage,
        testConfig,
      );

      await repo.refreshIfNeeded();

      expect(adapter.requests, isEmpty);
    });

    test(
      'logout clears the token even when the server is unreachable',
      () async {
        final storage = InMemoryTokenStorage(
          initial: const AuthToken(token: 'tok'),
        );
        final adapter = FakeAdapter()..on('POST auth/logout', connectionError);
        final repo = AuthRepository(
          testApiClient(adapter, storage: storage),
          storage,
          testConfig,
        );

        await repo.logout();

        expect(await storage.read(), isNull);
        expect(adapter.sent('POST auth/logout'), hasLength(1));
      },
    );
  });

  group('AuthController', () {
    test('restore without a token → unauthenticated', () async {
      final container = await _container(FakeAdapter(), InMemoryTokenStorage());
      await container.read(authControllerProvider.notifier).restore();
      expect(
        container.read(authControllerProvider).status,
        AuthStatus.unauthenticated,
      );
    });

    test('restore with a valid token → authenticated with the user', () async {
      final adapter = FakeAdapter()
        ..on('GET auth/me', (_) => FakeResponse.ok(userJson()));
      final container = await _container(
        adapter,
        InMemoryTokenStorage(initial: const AuthToken(token: 'tok')),
      );

      await container.read(authControllerProvider.notifier).restore();

      final state = container.read(authControllerProvider);
      expect(state.status, AuthStatus.authenticated);
      expect(state.user!.name, 'Sherif Soliman');
    });

    test('restore with a revoked token → unauthenticated, expired', () async {
      final storage = InMemoryTokenStorage(
        initial: const AuthToken(token: 'revoked'),
      );
      final adapter = FakeAdapter()
        ..on('GET auth/me', (_) => FakeResponse.error(401, 'unauthenticated'))
        ..on(
          'POST auth/refresh',
          (_) => FakeResponse.error(401, 'unauthenticated'),
        );
      final container = await _container(adapter, storage);

      await container.read(authControllerProvider.notifier).restore();

      final state = container.read(authControllerProvider);
      expect(state.status, AuthStatus.unauthenticated);
      expect(state.sessionExpired, isTrue);
      expect(await storage.read(), isNull);
    });

    test('restore while offline keeps the session', () async {
      final adapter = FakeAdapter()..on('GET auth/me', connectionError);
      final container = await _container(
        adapter,
        InMemoryTokenStorage(initial: const AuthToken(token: 'tok')),
      );

      await container.read(authControllerProvider.notifier).restore();

      expect(
        container.read(authControllerProvider).status,
        AuthStatus.authenticated,
      );
    });

    test('login then logout runs the push hooks around the session', () async {
      final storage = InMemoryTokenStorage();
      final adapter = FakeAdapter()
        ..on('POST auth/login', (_) => FakeResponse.ok(loginJson()))
        ..on('POST auth/logout', (_) => FakeResponse.ok(null));
      final container = await _container(adapter, storage);
      final controller = container.read(authControllerProvider.notifier);
      final calls = <String>[];
      controller
        ..onAfterLogin(() async => calls.add('register'))
        ..onBeforeLogout(() async {
          // Still signed in: the device can be unregistered with the token.
          calls.add('unregister:${(await storage.read())?.token}');
        });

      await controller.login('employee1@hrmplus.test', 'password');
      await Future<void>.delayed(Duration.zero);
      await controller.logout();

      expect(calls, ['register', 'unregister:1|first']);
      expect(
        container.read(authControllerProvider).status,
        AuthStatus.unauthenticated,
      );
      expect(await storage.read(), isNull);
    });
  });
}
