import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/providers.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';
import 'package:hrm_plus/features/attendance/application/check_flow_controller.dart';
import 'package:hrm_plus/features/attendance/data/attendance_repository.dart';
import 'package:hrm_plus/features/attendance/domain/models.dart';
import 'package:hrm_plus/features/attendance/services/camera_permission_service.dart';
import 'package:hrm_plus/features/attendance/services/face_camera.dart';
import 'package:hrm_plus/features/attendance/services/location_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../helpers/fake_http.dart';
import '../../helpers/fake_services.dart';
import '../../helpers/fixtures.dart';

/// Every failure path of the check-in / check-out flow, with fake location,
/// camera, connectivity and HTTP. Success is only ever reached from a 201.
class Harness {
  Harness({
    Map<String, Object?>? today,
    FakeLocationService? location,
    FakeCameraPermissionService? camera,
    FakeConnectivity? connectivity,
  }) : location = location ?? FakeLocationService(),
       camera = camera ?? FakeCameraPermissionService(),
       connectivity = connectivity ?? FakeConnectivity() {
    adapter.on(
      'GET me/attendance/today',
      (_) => FakeResponse.ok(today ?? todayJson()),
    );
  }

  final adapter = FakeAdapter();
  final FakeLocationService location;
  final FakeCameraPermissionService camera;
  final FakeConnectivity connectivity;
  final storage = InMemoryTokenStorage(initial: const AuthToken(token: 't'));
  var _uuid = 0;
  late ProviderContainer container;

  Future<void> init() async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    container = ProviderContainer(
      retry: (_, _) => null,
      overrides: [
        sharedPreferencesProvider.overrideWithValue(prefs),
        appConfigProvider.overrideWithValue(testConfig),
        tokenStorageProvider.overrideWithValue(storage),
        apiClientProvider.overrideWithValue(
          testApiClient(adapter, storage: storage),
        ),
        locationServiceProvider.overrideWithValue(location),
        cameraPermissionServiceProvider.overrideWithValue(camera),
        connectivityProvider.overrideWithValue(connectivity),
        uuidGeneratorProvider.overrideWithValue(
          () => '00000000-0000-4000-8000-00000000000${++_uuid}',
        ),
      ],
    );
    // Keep the auto-dispose flow alive like the screen does.
    container.listen(checkFlowProvider(AttendanceDirection.checkIn), (_, _) {});
    addTearDown(container.dispose);
  }

  CheckFlowController get flow =>
      container.read(checkFlowProvider(AttendanceDirection.checkIn).notifier);
  CheckFlowState get state =>
      container.read(checkFlowProvider(AttendanceDirection.checkIn));

  void answerCheckIn(FakeHandler handler) =>
      adapter.on('POST me/attendance/check-in', handler);

  List<Map<String, dynamic>> get submitted => [
    for (final r in adapter.sent('POST me/attendance/check-in')) jsonBody(r),
  ];
}

Future<Harness> harness({
  Map<String, Object?>? today,
  FakeLocationService? location,
  FakeCameraPermissionService? camera,
  FakeConnectivity? connectivity,
}) async {
  final h = Harness(
    today: today,
    location: location,
    camera: camera,
    connectivity: connectivity,
  );
  await h.init();
  return h;
}

void main() {
  group('happy path', () {
    test('inside the geofence, face off → 201 is shown as success', () async {
      final h = await harness();
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));

      await h.flow.start();

      expect(h.state.stage, CheckStage.finished);
      expect(h.state.result, isA<CheckAccepted>());
      final body = h.submitted.single;
      expect(body['request_uuid'], '00000000-0000-4000-8000-000000000001');
      expect(body['latitude'], mainBuildingLat);
      expect(body['accuracy'], 10);
      expect(body['is_mock_location'], isFalse);
      expect(body['device_time'], matches(RegExp(r'[+-]\d\d:\d\d$')));
      expect(body.containsKey('face'), isFalse);
    });

    test('face required and enrolled → camera step, images are sent', () async {
      final h = await harness(today: todayJson(faceMode: 'required'));
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));

      await h.flow.start();
      expect(h.state.stage, CheckStage.awaitingFace);
      expect(h.submitted, isEmpty, reason: 'nothing is sent before the face');

      expect(await h.flow.ensureCameraPermission(), isTrue);
      await h.flow.faceCaptured(['BASE64JPEG']);

      expect(h.state.result, isA<CheckAccepted>());
      expect(h.submitted.single['face'], {
        'images': ['BASE64JPEG'],
      });
    });

    test('a mock location is reported to the server', () async {
      final h = await harness(
        location: FakeLocationService(
          fix: fixAt(mainBuildingLat, mainBuildingLng, mocked: true),
        ),
      );
      h.answerCheckIn(
        (_) => FakeResponse.ok(
          attemptJson(
            outcome: 'flagged',
            reasons: [
              {'code': 'mock_location', 'message': 'Mock'},
            ],
          ),
          status: 202,
        ),
      );

      await h.flow.start();

      expect(h.submitted.single['is_mock_location'], isTrue);
      expect(h.state.result, isA<CheckFlagged>());
    });
  });

  group('server decisions', () {
    test('202 → flagged for review, never success', () async {
      final h = await harness();
      h.answerCheckIn(
        (_) => FakeResponse.ok(attemptJson(outcome: 'flagged'), status: 202),
      );

      await h.flow.start();

      expect(h.state.result, isA<CheckFlagged>());
      expect(h.state.result!.attempt.recorded, isFalse);
    });

    test(
      '422 attendance_rejected → reasons and distance from the server',
      () async {
        final h = await harness();
        h.answerCheckIn(
          (_) => FakeResponse.error(
            422,
            'attendance_rejected',
            message: 'You are outside the allowed location.',
            data: {
              ...attemptJson(
                outcome: 'rejected',
                distance: 830,
                withinGeofence: false,
                reasons: [
                  {'code': 'outside_geofence', 'message': 'Outside'},
                ],
              ),
              'face_remaining_attempts': null,
            },
          ),
        );

        await h.flow.start();

        final result = h.state.result! as CheckRejected;
        expect(result.attempt.hasReason('outside_geofence'), isTrue);
        expect(result.attempt.distanceMeters, 830);
      },
    );

    test(
      'face_retry with attempts left → new uuid, back to the camera',
      () async {
        final h = await harness(today: todayJson(faceMode: 'required'));
        h.answerCheckIn(
          (_) => FakeResponse.error(
            422,
            'attendance_rejected',
            data: {
              ...attemptJson(
                outcome: 'rejected',
                reasons: [
                  {'code': 'face_retry', 'message': 'Try again'},
                ],
              ),
              'face_remaining_attempts': 2,
            },
          ),
        );

        await h.flow.start();
        await h.flow.faceCaptured(['A']);
        final rejected = h.state.result! as CheckRejected;
        expect(rejected.faceRemainingAttempts, 2);
        final firstUuid = h.state.requestUuid;

        await h.flow.retryAfterRejection();

        expect(h.state.stage, CheckStage.awaitingFace);
        expect(h.state.requestUuid, isNot(firstUuid));
        expect(h.state.faceImages, isNull);
      },
    );
  });

  group('location failures', () {
    test(
      'permission not yet granted → explanation first, then OS prompt',
      () async {
        final location = FakeLocationService(
          permission: LocationPermissionState.denied,
        );
        final h = await harness(location: location);
        h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));

        await h.flow.start();
        expect(h.state.stage, CheckStage.rationale);
        expect(location.requests, 0);

        await h.flow.acceptRationale();
        expect(location.requests, 1);
        expect(h.state.result, isA<CheckAccepted>());
      },
    );

    test(
      'permission denied at the prompt → blocked, retry asks again',
      () async {
        final location = FakeLocationService(
          permission: LocationPermissionState.denied,
          permissionAfterRequest: LocationPermissionState.denied,
        );
        final h = await harness(location: location);

        await h.flow.start();
        await h.flow.acceptRationale();

        expect(h.state.stage, CheckStage.blocked);
        expect(h.state.issue!.kind, IssueKind.locationPermissionDenied);
        expect(h.submitted, isEmpty);

        await h.flow.retry();
        expect(location.requests, 2);
      },
    );

    test('permanently denied → open app settings', () async {
      final location = FakeLocationService(
        permission: LocationPermissionState.permanentlyDenied,
      );
      final h = await harness(location: location);

      await h.flow.start();

      expect(
        h.state.issue!.kind,
        IssueKind.locationPermissionPermanentlyDenied,
      );
      expect(h.state.issue!.opensSettings, isTrue);
      await h.flow.openSettings();
      expect(location.openedAppSettings, 1);
    });

    test('location services off → open location settings', () async {
      final location = FakeLocationService(serviceEnabled: false);
      final h = await harness(location: location);

      await h.flow.start();

      expect(h.state.issue!.kind, IssueKind.locationServicesOff);
      await h.flow.openSettings();
      expect(location.openedLocationSettings, 1);
    });

    test('GPS timeout → blocked with retry, nothing sent', () async {
      final h = await harness(
        location: FakeLocationService(error: const LocationTimeoutException()),
      );

      await h.flow.start();

      expect(h.state.issue!.kind, IssueKind.gpsTimeout);
      expect(h.submitted, isEmpty);
    });

    test('GPS failure → gpsUnavailable', () async {
      final h = await harness(
        location: FakeLocationService(
          error: const LocationUnavailableException('provider error'),
        ),
      );

      await h.flow.start();

      expect(h.state.issue!.kind, IssueKind.gpsUnavailable);
    });

    test('poor accuracy → shows the accuracy and the limit', () async {
      final h = await harness(
        location: FakeLocationService(
          fix: fixAt(mainBuildingLat, mainBuildingLng, accuracy: 180),
        ),
      );

      await h.flow.start();

      final issue = h.state.issue!;
      expect(issue.kind, IssueKind.poorAccuracy);
      expect(issue.accuracy, 180);
      expect(issue.maxAccuracy, 50);
      expect(h.submitted, isEmpty);
    });

    test('outside the geofence → distance and nearest location', () async {
      // ~1.1 km north of the main building.
      final h = await harness(
        location: FakeLocationService(
          fix: fixAt(mainBuildingLat + 0.01, mainBuildingLng),
        ),
      );

      await h.flow.start();

      final issue = h.state.issue!;
      expect(issue.kind, IssueKind.outsideGeofence);
      expect(issue.locationName, 'Main building');
      expect(issue.distance, greaterThan(1000));
      expect(issue.radius, 150);
      expect(h.submitted, isEmpty);
    });

    test('retry after moving inside proceeds to submit', () async {
      final location = FakeLocationService(
        fix: fixAt(mainBuildingLat + 0.01, mainBuildingLng),
      );
      final h = await harness(location: location);
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));

      await h.flow.start();
      location.fix = fixAt(mainBuildingLat, mainBuildingLng);
      await h.flow.retry();

      expect(h.state.result, isA<CheckAccepted>());
    });

    test('allow anywhere skips the geofence', () async {
      final h = await harness(
        today: todayJson(allowAnywhere: true),
        location: FakeLocationService(fix: fixAt(0, 0)),
      );
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));

      await h.flow.start();

      expect(h.state.result, isA<CheckAccepted>());
    });

    test('mobile attendance disabled by policy → blocked', () async {
      final h = await harness(today: todayJson(mobileEnabled: false));
      await h.flow.start();
      expect(h.state.issue!.kind, IssueKind.mobileDisabled);
    });
  });

  group('camera and face failures', () {
    test('face required but not enrolled → enrol first', () async {
      final h = await harness(
        today: todayJson(faceMode: 'required', enrolled: false),
      );
      await h.flow.start();
      expect(h.state.issue!.kind, IssueKind.faceNotEnrolled);
    });

    test('optional face not enrolled → submitted without face', () async {
      final h = await harness(
        today: todayJson(faceMode: 'optional', enrolled: false),
      );
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));
      await h.flow.start();
      expect(h.submitted.single.containsKey('face'), isFalse);
    });

    test(
      'camera permission denied → blocked, retry returns to the camera',
      () async {
        final camera = FakeCameraPermissionService(
          current: CameraPermissionState.denied,
          afterRequest: CameraPermissionState.denied,
        );
        final h = await harness(
          today: todayJson(faceMode: 'required'),
          camera: camera,
        );

        await h.flow.start();
        expect(await h.flow.ensureCameraPermission(), isFalse);
        expect(h.state.issue!.kind, IssueKind.cameraPermissionDenied);

        await h.flow.retry();
        expect(h.state.stage, CheckStage.awaitingFace);
      },
    );

    test('camera permanently denied → open settings', () async {
      final camera = FakeCameraPermissionService(
        current: CameraPermissionState.permanentlyDenied,
      );
      final h = await harness(
        today: todayJson(faceMode: 'required'),
        camera: camera,
      );

      await h.flow.start();
      await h.flow.ensureCameraPermission();
      await h.flow.openSettings();

      expect(h.state.issue!.kind, IssueKind.cameraPermissionPermanentlyDenied);
      expect(camera.openedSettings, 1);
    });

    test('camera error and missing camera are reported', () async {
      final h = await harness(today: todayJson(faceMode: 'required'));
      await h.flow.start();

      h.flow.faceCaptureFailed(FaceCameraError.failed);
      expect(h.state.issue!.kind, IssueKind.cameraUnavailable);

      await h.flow.retry();
      h.flow.faceCaptureFailed(FaceCameraError.noCamera);
      expect(h.state.issue!.kind, IssueKind.noCamera);
      expect(h.submitted, isEmpty);
    });

    test('optional face: the user may continue without it', () async {
      final h = await harness(today: todayJson(faceMode: 'optional'));
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));

      await h.flow.start();
      expect(h.flow.canSkipFace, isTrue);
      await h.flow.continueWithoutFace();

      expect(h.state.result, isA<CheckAccepted>());
    });

    test('required face cannot be skipped', () async {
      final h = await harness(today: todayJson(faceMode: 'required'));
      await h.flow.start();
      expect(h.flow.canSkipFace, isFalse);
      await h.flow.continueWithoutFace();
      expect(h.submitted, isEmpty);
    });
  });

  group('network failures', () {
    test('offline at start → blocked, nothing queued', () async {
      final h = await harness(connectivity: FakeConnectivity(online: false));

      await h.flow.start();

      expect(h.state.issue!.kind, IssueKind.offline);
      expect(h.adapter.requests, isEmpty);
    });

    test('offline when submitting → blocked, no fake success', () async {
      final connectivity = FakeConnectivity();
      final h = await harness(
        today: todayJson(faceMode: 'required'),
        connectivity: connectivity,
      );
      await h.flow.start();
      connectivity.online = false;

      await h.flow.faceCaptured(['A']);

      expect(h.state.stage, CheckStage.blocked);
      expect(h.state.issue!.kind, IssueKind.offline);
      expect(h.state.result, isNull);
      expect(h.submitted, isEmpty);
    });

    test('timeout on submit → retry re-sends the SAME request_uuid', () async {
      final h = await harness();
      var calls = 0;
      h.answerCheckIn((request) {
        calls++;
        if (calls == 1) timeoutError(request);
        return FakeResponse.ok(attemptJson(), status: 201);
      });

      await h.flow.start();
      expect(h.state.issue!.kind, IssueKind.submitFailed);
      expect(h.state.result, isNull);

      await h.flow.retry();

      expect(h.state.result, isA<CheckAccepted>());
      expect(h.submitted, hasLength(2));
      expect(h.submitted[0]['request_uuid'], h.submitted[1]['request_uuid']);
    });

    test('connection lost during submit → offline issue, uuid kept', () async {
      final h = await harness();
      h.answerCheckIn(connectionError);

      await h.flow.start();

      expect(h.state.issue!.kind, IssueKind.offline);
      final uuid = h.state.requestUuid;
      h.answerCheckIn((_) => FakeResponse.ok(attemptJson(), status: 201));
      await h.flow.retry();
      expect(h.submitted.last['request_uuid'], uuid);
    });

    test('server error → submitFailed', () async {
      final h = await harness();
      h.answerCheckIn((_) => FakeResponse.error(500, 'server_error'));
      await h.flow.start();
      expect(h.state.issue!.kind, IssueKind.submitFailed);
    });

    test('today cannot be loaded → todayFailed with retry', () async {
      final h = await harness();
      h.adapter.on(
        'GET me/attendance/today',
        (_) => FakeResponse.error(500, 'server_error'),
      );
      await h.flow.start();
      expect(h.state.issue!.kind, IssueKind.todayFailed);
      expect(h.state.issue!.retryable, isTrue);
    });
  });
}
