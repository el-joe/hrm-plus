// End-to-end scenarios 1 and 2 (doc.md "End-to-End Scenarios") against a
// REAL backend. Skipped unless enabled:
//
//   flutter test test/live --dart-define=LIVE_API=true \
//     --dart-define=API_BASE_URL=http://hrm-plus.loc/api/v1 \
//     --dart-define=LIVE_EMAIL=employee1@hrmplus.test \
//     --dart-define=LIVE_PASSWORD=password
//
// Scenario 2 records a real attendance attempt for today (development data
// only). It never asserts "success" without the server saying so: it accepts
// 201 (recorded), 202 (sent for review) or 422 attendance_rejected and checks
// the decision is consistent and that replaying the same request_uuid returns
// the stored result.
@Tags(['live'])
library;

import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/config/app_config.dart';
import 'package:hrm_plus/core/network/api_client.dart';
import 'package:hrm_plus/core/storage/token_storage.dart';
import 'package:hrm_plus/core/utils/formatters.dart';
import 'package:hrm_plus/features/attendance/data/attendance_repository.dart';
import 'package:hrm_plus/features/auth/data/auth_repository.dart';
import 'package:hrm_plus/features/home/data/home_repository.dart';
import 'package:hrm_plus/features/leave/data/leave_repository.dart';
import 'package:hrm_plus/features/payslips/data/payslip_repository.dart';
import 'package:uuid/uuid.dart';

const _enabled = bool.fromEnvironment('LIVE_API');
const _email = String.fromEnvironment(
  'LIVE_EMAIL',
  defaultValue: 'employee1@hrmplus.test',
);
// Development seed password (backend/docs/DEV_ACCOUNTS.md); never a real one.
const _password = String.fromEnvironment(
  'LIVE_PASSWORD',
  defaultValue: 'password',
);

void main() {
  final config = AppConfig.fromEnvironment();
  final storage = InMemoryTokenStorage();
  final api = ApiClient.create(
    config: config,
    storage: storage,
    languageCode: () => 'en',
    onSessionExpired: () async {},
  );
  final auth = AuthRepository(api, storage, config);

  group('Scenario 1 — employee signs in to the app', skip: !_enabled, () {
    test('login → token stored → auth/me → home', () async {
      final user = await auth.login(_email, _password);
      expect(user.employeeId, isNotNull);
      expect(await storage.read(), isNotNull);

      final me = await auth.me();
      expect(me.email, _email);

      final home = await HomeRepository(api).home();
      expect(home.employeeName, isNotEmpty);
    });

    test('app config is public', () async {
      final remote = await auth.appConfig('en');
      expect(remote, isNotNull);
      expect(remote!.otpLength, greaterThan(0));
    });
  });

  group('Scenario 2 — mobile attendance', skip: !_enabled, () {
    setUpAll(() async {
      if (await storage.read() == null) await auth.login(_email, _password);
    });

    test('today → check at the first allowed location → server decision', () async {
      final repo = AttendanceRepository(api);
      final today = await repo.today();
      expect(today.employed, isTrue);
      if (today.locations.isEmpty && !today.allowAnywhere) {
        markTestSkipped('No allowed location configured for this employee.');
        return;
      }
      final target = today.locations.isEmpty ? null : today.locations.first;
      final request = CheckRequest(
        requestUuid: const Uuid().v4(),
        latitude: target?.latitude ?? 30.0444,
        longitude: target?.longitude ?? 31.2357,
        accuracy: 8,
        isMockLocation: false,
        deviceTime: Formatter.isoWithOffset(DateTime.now()),
        deviceId: await storage.deviceId(),
        platform: 'android',
        appVersion: config.appVersion,
        // The simulated provider matches any image/token except the sim-* ones.
        faceImages: today.face.shouldCapture ? ['bGl2ZS10ZXN0'] : null,
      );

      final result = await repo.submit(today.nextDirection, request);
      switch (result) {
        case CheckAccepted():
          expect(result.attempt.recorded, isTrue);
        case CheckFlagged():
          expect(result.attempt.recorded, isFalse);
        case CheckRejected():
          expect(result.attempt.reasons, isNotEmpty);
      }
      expect(result.attempt.requestUuid, request.requestUuid);

      // Idempotency: the same uuid returns the stored decision.
      final replay = await repo.submit(today.nextDirection, request);
      expect(replay.attempt.outcome, result.attempt.outcome);
      expect(replay.attempt.serverTime, result.attempt.serverTime);

      final attempts = await repo.attempts();
      expect(
        attempts.items.any((a) => a.requestUuid == request.requestUuid),
        isTrue,
      );
    });

    test('leave preview and payslips follow the documented shapes', () async {
      final leave = LeaveRepository(api);
      final types = await leave.types();
      expect(types, isNotEmpty);
      final start = DateTime.now().add(const Duration(days: 30));
      final preview = await leave.preview(
        LeaveDraft(
          typeId: types.first.id,
          startDate: Formatter.apiDate(start),
          endDate: Formatter.apiDate(start.add(const Duration(days: 1))),
        ),
      );
      expect(preview.total, matches(RegExp(r'^\d+\.\d{2}$')));

      final payslips = PayslipRepository(api);
      final page = await payslips.list();
      if (page.items.isNotEmpty) {
        final detail = await payslips.show(page.items.first.id);
        expect(detail.lines, isNotNull);
        final pdf = await payslips.pdf(detail.id, 'ar');
        expect(String.fromCharCodes(pdf.take(4)), '%PDF');
      }
    });

    test('logout revokes the token', () async {
      await auth.logout();
      expect(await storage.read(), isNull);
    });
  });
}
