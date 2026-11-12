import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/errors/failures.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_response.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';
import '../domain/models.dart';

/// The body of POST me/attendance/check-in | check-out.
class CheckRequest {
  const CheckRequest({
    required this.requestUuid,
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.isMockLocation,
    required this.deviceTime,
    required this.deviceId,
    required this.platform,
    required this.appVersion,
    this.faceImages,
  });

  final String requestUuid;
  final double latitude;
  final double longitude;
  final double accuracy;
  final bool isMockLocation;
  final String deviceTime;
  final String deviceId;
  final String? platform;
  final String appVersion;

  /// Base64 JPEG frames for the face provider (max 3).
  final List<String>? faceImages;

  Json toJson() => {
    'request_uuid': requestUuid,
    'latitude': latitude,
    'longitude': longitude,
    'accuracy': accuracy,
    'is_mock_location': isMockLocation,
    'device_time': deviceTime,
    'device_id': deviceId,
    'platform': ?platform,
    'app_version': appVersion,
    if (faceImages != null && faceImages!.isNotEmpty)
      'face': {'images': faceImages},
  };
}

/// The server's answer to a check-in / check-out.
sealed class CheckResult {
  const CheckResult(this.attempt);
  final AttendanceAttempt attempt;
}

/// 201: recorded. The only outcome shown as success.
class CheckAccepted extends CheckResult {
  const CheckAccepted(super.attempt);
}

/// 202: sent for review — NOT recorded yet.
class CheckFlagged extends CheckResult {
  const CheckFlagged(super.attempt);
}

/// 422 `attendance_rejected` with reasons and remaining face attempts.
class CheckRejected extends CheckResult {
  const CheckRejected(
    super.attempt, {
    this.faceRemainingAttempts,
    this.message,
  });
  final int? faceRemainingAttempts;
  final String? message;
}

class AttendanceRepository {
  AttendanceRepository(this._api);

  final ApiClient _api;

  Future<TodayStatus> today() async =>
      TodayStatus.fromJson((await _api.get('me/attendance/today')).json);

  /// Submits an attempt. Re-sending the same request_uuid returns the stored
  /// result, so network retries are safe. Throws [Failure] for anything that
  /// is not a server decision (offline, timeout, validation…).
  Future<CheckResult> submit(
    AttendanceDirection direction,
    CheckRequest request,
  ) async {
    try {
      final response = await _api.post(
        'me/attendance/${direction.path}',
        data: request.toJson(),
      );
      final attempt = AttendanceAttempt.fromJson(response.json);
      if (response.statusCode == 202 || attempt.outcome == 'flagged') {
        return CheckFlagged(attempt);
      }
      if (attempt.outcome == 'rejected') return CheckRejected(attempt);
      return CheckAccepted(attempt);
    } on ApiFailure catch (failure) {
      if (failure.code != 'attendance_rejected') rethrow;
      final data = asJson(failure.data);
      return CheckRejected(
        AttendanceAttempt.fromJson(data),
        faceRemainingAttempts: data.intOrNull('face_remaining_attempts'),
        message: failure.message,
      );
    }
  }

  Future<Paged<AttendanceAttempt>> attempts({int page = 1}) async {
    final response = await _api.get(
      'me/attendance/attempts',
      query: {'page': page},
    );
    return Paged(
      items: response.list.map(AttendanceAttempt.fromJson).toList(),
      pagination: response.pagination,
    );
  }

  /// GET me/attendance?month=YYYY-MM
  Future<AttendanceMonth> month(String month) async {
    final response = await _api.get('me/attendance', query: {'month': month});
    return AttendanceMonth(
      month: response.meta.str('month', month),
      days: response.list.map(AttendanceDay.fromJson).toList(),
      summary: response.meta.obj('summary') ?? const {},
    );
  }

  /// GET me/schedule?from&to (max 62 days).
  Future<List<ScheduleDay>> schedule(String from, String to) async {
    final response = await _api.get(
      'me/schedule',
      query: {'from': from, 'to': to},
    );
    return response.list.map(ScheduleDay.fromJson).toList();
  }

  Future<List<AttendanceCorrection>> corrections() async {
    final response = await _api.get('me/attendance/corrections');
    return response.list.map(AttendanceCorrection.fromJson).toList();
  }

  // Face (consent / enrolment).

  Future<FaceStatus> face() async =>
      FaceStatus.fromJson((await _api.get('me/face')).json);

  Future<FaceStatus> giveConsent(String version) async => FaceStatus.fromJson(
    (await _api.post(
      'me/face/consent',
      data: {'accepted': true, 'version': version},
    )).json,
  );

  Future<FaceStatus> withdrawConsent() async =>
      FaceStatus.fromJson((await _api.delete('me/face/consent')).json);

  Future<FaceStatus> enrol(List<String> images) async => FaceStatus.fromJson(
    (await _api.post('me/face/enrol', data: {'images': images})).json,
  );
}

final attendanceRepositoryProvider = Provider<AttendanceRepository>(
  (ref) => AttendanceRepository(ref.watch(apiClientProvider)),
);

final todayProvider = FutureProvider.autoDispose<TodayStatus>(
  (ref) => ref.watch(attendanceRepositoryProvider).today(),
);

final faceStatusProvider = FutureProvider.autoDispose<FaceStatus>(
  (ref) => ref.watch(attendanceRepositoryProvider).face(),
);

final attendanceMonthProvider = FutureProvider.autoDispose
    .family<AttendanceMonth, String>(
      (ref, month) => ref.watch(attendanceRepositoryProvider).month(month),
    );

final scheduleProvider = FutureProvider.autoDispose
    .family<List<ScheduleDay>, (String, String)>(
      (ref, range) =>
          ref.watch(attendanceRepositoryProvider).schedule(range.$1, range.$2),
    );

final correctionsProvider =
    FutureProvider.autoDispose<List<AttendanceCorrection>>(
      (ref) => ref.watch(attendanceRepositoryProvider).corrections(),
    );
