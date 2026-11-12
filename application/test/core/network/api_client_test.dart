import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/errors/failures.dart';
import 'package:hrm_plus/core/network/api_client.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';

import '../../helpers/fake_http.dart';

void main() {
  late FakeAdapter adapter;
  late ApiClient api;

  setUp(() {
    adapter = FakeAdapter();
    api = testApiClient(
      adapter,
      storage: InMemoryTokenStorage(initial: const AuthToken(token: 't1')),
    );
  });

  group('envelope', () {
    test('parses data, message and pagination meta', () async {
      adapter.on(
        'GET me/notifications',
        (_) => FakeResponse.ok(
          [
            {'id': 'a'},
          ],
          meta: {
            'pagination': {
              'current_page': 1,
              'per_page': 20,
              'total': 25,
              'last_page': 2,
            },
            'unread_count': 3,
          },
        ),
      );

      final response = await api.get('me/notifications');

      expect(response.list, hasLength(1));
      expect(response.pagination!.hasMore, isTrue);
      expect(response.meta['unread_count'], 3);
    });

    test('sends Accept and Accept-Language headers', () async {
      adapter.on('GET company', (_) => FakeResponse.ok({}));
      await api.get('company');
      final headers = adapter.requests.single.headers;
      expect(headers['Accept'], 'application/json');
      expect(headers['Accept-Language'], 'ar');
    });

    test('a 200 body with success=false is still an error', () async {
      adapter.on(
        'GET me/profile',
        (_) => const FakeResponse(200, {
          'success': false,
          'message': 'nope',
          'code': 'forbidden',
        }),
      );
      expect(api.get('me/profile'), throwsA(isA<ForbiddenFailure>()));
    });
  });

  group('error mapping', () {
    test(
      '422 validation_error → ValidationFailure with field errors',
      () async {
        adapter.on(
          'POST auth/login',
          (_) => FakeResponse.error(
            422,
            'validation_error',
            message: 'The given data was invalid.',
            errors: {
              'email': ['These credentials do not match our records.'],
            },
          ),
        );

        final failure = await api
            .post('auth/login', auth: false)
            .then<Object?>((_) => null, onError: (Object e) => e);

        expect(failure, isA<ValidationFailure>());
        final validation = failure! as ValidationFailure;
        expect(
          validation.first('email'),
          'These credentials do not match our records.',
        );
        expect(validation.statusCode, 422);
      },
    );

    test('ValidationFailure.first finds nested keys (attachments.0)', () {
      const failure = ValidationFailure(
        errors: {
          'attachments.0': ['Too large'],
        },
      );
      expect(failure.first('attachments'), 'Too large');
    });

    test('401 that cannot be refreshed → UnauthorizedFailure', () async {
      var expired = false;
      api = testApiClient(
        adapter,
        storage: InMemoryTokenStorage(initial: const AuthToken(token: 'old')),
        onSessionExpired: () async => expired = true,
      );
      adapter
        ..on(
          'GET me/profile',
          (_) => FakeResponse.error(401, 'unauthenticated'),
        )
        ..on(
          'POST auth/refresh',
          (_) => FakeResponse.error(401, 'unauthenticated'),
        );

      await expectLater(
        api.get('me/profile'),
        throwsA(isA<UnauthorizedFailure>()),
      );
      expect(expired, isTrue);
    });

    test('403 → ForbiddenFailure keeping the server message', () async {
      adapter.on(
        'POST auth/login',
        (_) => FakeResponse.error(
          403,
          'forbidden',
          message: 'This app is for employee accounts only.',
        ),
      );
      final failure = await api
          .post('auth/login', auth: false)
          .then<Object?>((_) => null, onError: (Object e) => e);
      expect(failure, isA<ForbiddenFailure>());
      expect(
        (failure! as ForbiddenFailure).message,
        'This app is for employee accounts only.',
      );
    });

    test('404 → NotFoundFailure', () async {
      adapter.on(
        'GET me/leave/requests/999',
        (_) => FakeResponse.error(404, 'not_found'),
      );
      expect(api.get('me/leave/requests/999'), throwsA(isA<NotFoundFailure>()));
    });

    test('422 attendance_rejected → ApiFailure with code and data', () async {
      adapter.on(
        'POST me/attendance/check-in',
        (_) => FakeResponse.error(
          422,
          'attendance_rejected',
          message: 'You are outside the allowed location.',
          data: {
            'outcome': 'rejected',
            'reasons': [
              {'code': 'outside_geofence', 'message': 'Outside'},
            ],
            'face_remaining_attempts': 2,
          },
        ),
      );
      final failure = await api
          .post('me/attendance/check-in')
          .then<Object?>((_) => null, onError: (Object e) => e);
      expect(failure, isA<ApiFailure>());
      final api422 = failure! as ApiFailure;
      expect(api422.code, 'attendance_rejected');
      expect(api422.statusCode, 422);
      expect((api422.data! as Map)['face_remaining_attempts'], 2);
    });

    test('429 → RateLimitFailure with Retry-After', () async {
      adapter.on(
        'POST me/attendance/check-in',
        (_) => FakeResponse.error(
          429,
          'too_many_requests',
          headers: {
            'retry-after': ['30'],
          },
        ),
      );
      final failure = await api
          .post('me/attendance/check-in')
          .then<Object?>((_) => null, onError: (Object e) => e);
      expect(failure, isA<RateLimitFailure>());
      expect((failure! as RateLimitFailure).retryAfterSeconds, 30);
    });

    test('500 → ServerFailure', () async {
      adapter.on('GET me/home', (_) => FakeResponse.error(500, 'server_error'));
      expect(api.get('me/home'), throwsA(isA<ServerFailure>()));
    });

    test('connection error → OfflineFailure', () async {
      adapter.on('GET me/home', connectionError);
      expect(api.get('me/home'), throwsA(isA<OfflineFailure>()));
    });

    test('timeout → TimeoutFailure', () async {
      adapter.on('GET me/home', timeoutError);
      expect(api.get('me/home'), throwsA(isA<TimeoutFailure>()));
    });

    test('binary download errors are decoded from the envelope', () async {
      adapter.on(
        'GET me/payslips/1/pdf',
        (_) => FakeResponse.error(404, 'not_found'),
      );
      expect(
        api.download('me/payslips/1/pdf'),
        throwsA(isA<NotFoundFailure>()),
      );
    });
  });
}
