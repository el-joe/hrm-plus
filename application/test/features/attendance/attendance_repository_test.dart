import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/errors/failures.dart';
import 'package:hrm_plus/features/attendance/application/check_flow_controller.dart';
import 'package:hrm_plus/features/attendance/data/attendance_repository.dart';
import 'package:hrm_plus/features/attendance/domain/models.dart';
import 'package:hrm_plus/features/attendance/services/location_service.dart';

import '../../helpers/fake_http.dart';
import '../../helpers/fake_services.dart';
import '../../helpers/fixtures.dart';

CheckRequest _request({List<String>? face}) => CheckRequest(
  requestUuid: 'a6d5c3c4-6f43-4d1f-9a55-6c6a6f0e2f11',
  latitude: mainBuildingLat,
  longitude: mainBuildingLng,
  accuracy: 8.5,
  isMockLocation: false,
  deviceTime: '2026-11-12T08:10:00+02:00',
  deviceId: 'device-1',
  platform: 'android',
  appVersion: '1.0.0',
  faceImages: face,
);

void main() {
  group('AttendanceRepository', () {
    test(
      'today parses locations (string coordinates), rules and face',
      () async {
        final adapter = FakeAdapter()
          ..on(
            'GET me/attendance/today',
            (_) => FakeResponse.ok(todayJson(faceMode: 'optional')),
          );
        final today = await AttendanceRepository(testApiClient(adapter))
            .today();

        expect(today.locations.single.latitude, closeTo(30.028531, 1e-9));
        expect(today.locations.single.radiusMeters, 150);
        expect(today.rules.maxAccuracyMeters, 50);
        expect(today.face.mode, 'optional');
        expect(today.face.isSimulated, isTrue);
        expect(today.nextDirection, AttendanceDirection.checkIn);
        expect(today.shifts.single.startLocal, '08:00');
      },
    );

    test('201 → CheckAccepted; body follows the API contract', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/attendance/check-in',
          (_) => FakeResponse.ok(attemptJson(), status: 201),
        );
      final result = await AttendanceRepository(testApiClient(adapter))
          .submit(AttendanceDirection.checkIn, _request(face: ['QUJD']));

      expect(result, isA<CheckAccepted>());
      expect(result.attempt.recorded, isTrue);
      final body = jsonBody(adapter.requests.single);
      expect(
        body,
        containsPair('request_uuid', 'a6d5c3c4-6f43-4d1f-9a55-6c6a6f0e2f11'),
      );
      expect(body, containsPair('accuracy', 8.5));
      expect(body, containsPair('platform', 'android'));
      expect(body['face'], {
        'images': ['QUJD'],
      });
    });

    test('check-out goes to the check-out endpoint', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/attendance/check-out',
          (_) => FakeResponse.ok(attemptJson(direction: 'out'), status: 201),
        );
      final result = await AttendanceRepository(testApiClient(adapter))
          .submit(AttendanceDirection.checkOut, _request());
      expect(result.attempt.direction, 'out');
    });

    test('202 → CheckFlagged (not recorded)', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/attendance/check-in',
          (_) => FakeResponse.ok(attemptJson(outcome: 'flagged'), status: 202),
        );
      final result = await AttendanceRepository(testApiClient(adapter))
          .submit(AttendanceDirection.checkIn, _request());
      expect(result, isA<CheckFlagged>());
      expect(result.attempt.recorded, isFalse);
    });

    test(
      '422 attendance_rejected → CheckRejected with remaining attempts',
      () async {
        final adapter = FakeAdapter()
          ..on(
            'POST me/attendance/check-in',
            (_) => FakeResponse.error(
              422,
              'attendance_rejected',
              message: 'Face not recognised',
              data: {
                ...attemptJson(
                  outcome: 'rejected',
                  reasons: [
                    {'code': 'face_retry', 'message': 'Face not recognised'},
                  ],
                ),
                'face_remaining_attempts': 1,
              },
            ),
          );
        final result = await AttendanceRepository(testApiClient(adapter))
            .submit(AttendanceDirection.checkIn, _request());

        expect(result, isA<CheckRejected>());
        expect((result as CheckRejected).faceRemainingAttempts, 1);
        expect(result.attempt.hasReason('face_retry'), isTrue);
      },
    );

    test('422 validation_error is not a decision: it is thrown', () async {
      final adapter = FakeAdapter()
        ..on(
          'POST me/attendance/check-in',
          (_) => FakeResponse.error(
            422,
            'validation_error',
            errors: {
              'latitude': ['Invalid'],
            },
          ),
        );
      expect(
        AttendanceRepository(testApiClient(adapter))
            .submit(AttendanceDirection.checkIn, _request()),
        throwsA(isA<ValidationFailure>()),
      );
    });
  });

  group('geofence', () {
    test('distanceMeters is ~1.11 km per 0.01° of latitude', () {
      final d = distanceMeters(30, 31, 30.01, 31);
      expect(d, closeTo(1112, 5));
    });

    test('evaluateGeofence picks the nearest location and its radius', () {
      final today = TodayStatus.fromJson(todayJson());
      final inside = evaluateGeofence(
        today,
        fixAt(mainBuildingLat + 0.0005, mainBuildingLng),
        AttendanceDirection.checkIn,
      );
      expect(inside.nearest!.name, 'Main building');
      expect(inside.inside, isTrue);

      final outside = evaluateGeofence(
        today,
        fixAt(mainBuildingLat + 0.01, mainBuildingLng),
        AttendanceDirection.checkIn,
      );
      expect(outside.inside, isFalse);
      expect(outside.radius, 150);
    });
  });
}
