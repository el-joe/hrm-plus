import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';

import '../../helpers/fake_http.dart';

void main() {
  test('attaches the bearer token to API requests', () async {
    final adapter = FakeAdapter()
      ..on('GET auth/me', (_) => FakeResponse.ok({'id': 1}));
    final api = testApiClient(
      adapter,
      storage: InMemoryTokenStorage(initial: const AuthToken(token: 'abc')),
    );

    await api.get('auth/me');

    expect(adapter.requests.single.headers['Authorization'], 'Bearer abc');
  });

  test('does not attach the token when auth is skipped (login)', () async {
    final adapter = FakeAdapter()
      ..on('POST auth/login', (_) => FakeResponse.ok({'token': 'x'}));
    final api = testApiClient(
      adapter,
      storage: InMemoryTokenStorage(initial: const AuthToken(token: 'abc')),
    );

    await api.post('auth/login', auth: false);

    expect(adapter.requests.single.headers['Authorization'], isNull);
  });

  test('never sends the token to another host', () async {
    final adapter = FakeAdapter();
    final api = testApiClient(
      adapter,
      storage: InMemoryTokenStorage(initial: const AuthToken(token: 'abc')),
    );

    await api
        .download('https://files.example.com/doc.pdf')
        .then((_) {}, onError: (_) {});

    expect(adapter.requests.single.headers['Authorization'], isNull);
  });

  test('on 401 refreshes once, stores the new token and replays', () async {
    final storage = InMemoryTokenStorage(
      initial: const AuthToken(token: 'old'),
    );
    final adapter = FakeAdapter()
      ..on('GET me/profile', (request) {
        return request.headers['Authorization'] == 'Bearer new'
            ? FakeResponse.ok({'name': 'Sherif'})
            : FakeResponse.error(401, 'unauthenticated');
      })
      ..on(
        'POST auth/refresh',
        (_) => FakeResponse.ok({
          'token': 'new',
          'token_type': 'Bearer',
          'expires_at': '2026-12-31T00:00:00+00:00',
        }),
      );
    final api = testApiClient(adapter, storage: storage);

    final response = await api.get('me/profile');

    expect(response.json['name'], 'Sherif');
    expect((await storage.read())!.token, 'new');
    expect(adapter.sent('POST auth/refresh'), hasLength(1));
    expect(
      adapter.sent('POST auth/refresh').single.headers['Authorization'],
      'Bearer old',
    );
  });

  test('refresh failure ends the session', () async {
    var expired = 0;
    final adapter = FakeAdapter()
      ..on('GET me/profile', (_) => FakeResponse.error(401, 'unauthenticated'))
      ..on(
        'POST auth/refresh',
        (_) => FakeResponse.error(401, 'unauthenticated'),
      );
    final api = testApiClient(
      adapter,
      storage: InMemoryTokenStorage(initial: const AuthToken(token: 'old')),
      onSessionExpired: () async => expired++,
    );

    await api.get('me/profile').then((_) {}, onError: (_) {});

    expect(expired, 1);
  });

  test('a 401 on a request without a stored token does not refresh', () async {
    final adapter = FakeAdapter()
      ..on('GET me/profile', (_) => FakeResponse.error(401, 'unauthenticated'));
    final api = testApiClient(adapter);

    await api.get('me/profile').then((_) {}, onError: (_) {});

    expect(adapter.sent('POST auth/refresh'), isEmpty);
  });
}
