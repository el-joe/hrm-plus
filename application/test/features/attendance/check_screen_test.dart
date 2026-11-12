import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/providers.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';
import 'package:hrm_plus/features/attendance/application/check_flow_controller.dart';
import 'package:hrm_plus/features/attendance/domain/models.dart';
import 'package:hrm_plus/features/attendance/presentation/check_screen.dart';
import 'package:hrm_plus/features/attendance/services/camera_permission_service.dart';
import 'package:hrm_plus/features/attendance/services/face_camera.dart';
import 'package:hrm_plus/features/attendance/services/location_service.dart';

import '../../helpers/fake_http.dart';
import '../../helpers/fake_services.dart';
import '../../helpers/fixtures.dart';
import '../../helpers/pump.dart';

/// Widget-level checks of the check-in screen: rationale before the OS
/// prompt, failure screens, and success only after the server's 201.
void main() {
  late FakeAdapter adapter;

  setUp(() => adapter = FakeAdapter());

  Future<void> pumpCheck(
    WidgetTester tester, {
    Map<String, Object?>? today,
    FakeLocationService? location,
    FakeConnectivity? connectivity,
    FaceCamera Function()? camera,
  }) async {
    adapter.on(
      'GET me/attendance/today',
      (_) => FakeResponse.ok(today ?? todayJson()),
    );
    final storage = InMemoryTokenStorage(initial: const AuthToken(token: 't'));
    await pumpScreen(
      tester,
      const CheckScreen(direction: AttendanceDirection.checkIn),
      locale: const Locale('en'),
      overrides: [
        tokenStorageProvider.overrideWithValue(storage),
        apiClientProvider.overrideWithValue(
          testApiClient(adapter, storage: storage),
        ),
        locationServiceProvider.overrideWithValue(
          location ?? FakeLocationService(),
        ),
        cameraPermissionServiceProvider.overrideWithValue(
          FakeCameraPermissionService(),
        ),
        connectivityProvider.overrideWithValue(
          connectivity ?? FakeConnectivity(),
        ),
        faceCameraFactoryProvider.overrideWithValue(
          camera ?? FakeFaceCamera.new,
        ),
        uuidGeneratorProvider.overrideWithValue(
          () => '00000000-0000-4000-8000-000000000001',
        ),
      ],
    );
    await tester.pumpAndSettle();
  }

  testWidgets('shows the location explanation before asking', (tester) async {
    await pumpCheck(
      tester,
      location: FakeLocationService(permission: LocationPermissionState.denied),
    );
    expect(find.byKey(const Key('location-rationale')), findsOneWidget);
  });

  testWidgets('success is shown only after the 201 answer', (tester) async {
    adapter.on(
      'POST me/attendance/check-in',
      (_) => FakeResponse.ok(attemptJson(), status: 201),
    );
    await pumpCheck(tester);
    expect(find.byKey(const Key('result-accepted')), findsOneWidget);
    expect(find.text('Check-in recorded'), findsOneWidget);
  });

  testWidgets('202 shows "sent for review", not success', (tester) async {
    adapter.on(
      'POST me/attendance/check-in',
      (_) => FakeResponse.ok(attemptJson(outcome: 'flagged'), status: 202),
    );
    await pumpCheck(tester);
    expect(find.byKey(const Key('result-flagged')), findsOneWidget);
    expect(find.text('Check-in recorded'), findsNothing);
  });

  testWidgets('rejection reasons are localised by code', (tester) async {
    adapter.on(
      'POST me/attendance/check-in',
      (_) => FakeResponse.error(
        422,
        'attendance_rejected',
        data: {
          ...attemptJson(
            outcome: 'rejected',
            reasons: [
              {'code': 'poor_accuracy', 'message': 'server text'},
            ],
          ),
          'face_remaining_attempts': null,
        },
      ),
    );
    await pumpCheck(tester);
    expect(find.byKey(const Key('result-rejected')), findsOneWidget);
    expect(find.text('GPS accuracy was too low'), findsOneWidget);
    expect(find.byKey(const Key('retry-after-rejection')), findsOneWidget);
  });

  testWidgets('offline blocks with a clear message', (tester) async {
    await pumpCheck(tester, connectivity: FakeConnectivity(online: false));
    expect(find.byKey(const Key('issue-offline')), findsOneWidget);
    expect(find.text('Nothing has been recorded.'), findsOneWidget);
  });

  testWidgets('outside the geofence shows the distance', (tester) async {
    await pumpCheck(
      tester,
      location: FakeLocationService(
        fix: fixAt(mainBuildingLat + 0.01, mainBuildingLng),
      ),
    );
    expect(find.byKey(const Key('issue-outsideGeofence')), findsOneWidget);
    expect(find.textContaining('Main building'), findsWidgets);
  });

  testWidgets('camera error during face capture is reported', (tester) async {
    await pumpCheck(
      tester,
      today: todayJson(faceMode: 'required'),
      camera: () => FakeFaceCamera(initError: FaceCameraError.failed),
    );
    expect(find.byKey(const Key('face-step')), findsOneWidget);
    await tester.tap(find.byKey(const Key('open-camera')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('issue-cameraUnavailable')), findsOneWidget);
  });

  testWidgets('face capture submits and shows the server result', (
    tester,
  ) async {
    adapter.on(
      'POST me/attendance/check-in',
      (_) => FakeResponse.ok(attemptJson(), status: 201),
    );
    await pumpCheck(tester, today: todayJson(faceMode: 'required'));
    await tester.tap(find.byKey(const Key('open-camera')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('face-capture')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('result-accepted')), findsOneWidget);
  });
}
