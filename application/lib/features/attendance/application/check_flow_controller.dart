import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

import '../../../core/errors/failures.dart';
import '../../../core/providers.dart';
import '../../../core/utils/formatters.dart';
import '../data/attendance_repository.dart';
import '../domain/models.dart';
import '../services/camera_permission_service.dart';
import '../services/face_camera.dart';
import '../services/location_service.dart';

/// Where the check-in / check-out flow is (spec "Mobile Attendance UX").
enum CheckStage {
  /// Loading today's rules and locations.
  preparing,

  /// Explaining why location access is needed, before the OS prompt.
  rationale,

  /// Waiting for a GPS fix.
  locating,

  /// Location validated; the face capture screen should open.
  awaitingFace,

  /// Request in flight.
  submitting,

  /// The server answered (accepted / flagged / rejected) — see [CheckFlowState.result].
  finished,

  /// Something blocks the flow — see [CheckFlowState.issue].
  blocked,
}

enum IssueKind {
  offline,
  todayFailed,
  notEmployed,
  mobileDisabled,
  locationServicesOff,
  locationPermissionDenied,
  locationPermissionPermanentlyDenied,
  gpsTimeout,
  gpsUnavailable,
  poorAccuracy,
  outsideGeofence,
  noLocationConfigured,
  cameraPermissionDenied,
  cameraPermissionPermanentlyDenied,
  cameraUnavailable,
  noCamera,
  faceNotEnrolled,
  submitFailed,
}

class FlowIssue {
  const FlowIssue(
    this.kind, {
    this.failure,
    this.accuracy,
    this.maxAccuracy,
    this.distance,
    this.radius,
    this.locationName,
  });

  final IssueKind kind;
  final Object? failure;
  final double? accuracy;
  final int? maxAccuracy;
  final double? distance;
  final int? radius;
  final String? locationName;

  /// Issues whose fix is in the system settings.
  bool get opensSettings =>
      kind == IssueKind.locationPermissionPermanentlyDenied ||
      kind == IssueKind.locationServicesOff ||
      kind == IssueKind.cameraPermissionPermanentlyDenied;

  /// A retry is meaningful (vs. contacting HR).
  bool get retryable =>
      kind != IssueKind.notEmployed &&
      kind != IssueKind.mobileDisabled &&
      kind != IssueKind.noLocationConfigured;
}

/// Client-side geofence evaluation (display + early feedback only: the
/// server validates again on submission).
class GeofenceCheck {
  const GeofenceCheck({
    required this.allowAnywhere,
    this.nearest,
    this.distance,
    this.radius,
  });

  final bool allowAnywhere;
  final WorkLocation? nearest;
  final double? distance;
  final int? radius;

  bool get inside =>
      allowAnywhere ||
      (distance != null && radius != null && distance! <= radius!);
}

class CheckFlowState {
  const CheckFlowState({
    required this.stage,
    required this.requestUuid,
    this.today,
    this.fix,
    this.geofence,
    this.issue,
    this.result,
    this.faceImages,
  });

  final CheckStage stage;

  /// Generated per attempt; reused when re-sending after a network failure
  /// (the server returns the stored result for a known uuid).
  final String requestUuid;
  final TodayStatus? today;
  final LocationFix? fix;
  final GeofenceCheck? geofence;
  final FlowIssue? issue;
  final CheckResult? result;
  final List<String>? faceImages;

  bool get faceRequiredByPolicy => today?.face.isRequired ?? false;

  CheckFlowState copyWith({
    CheckStage? stage,
    String? requestUuid,
    TodayStatus? today,
    LocationFix? fix,
    GeofenceCheck? geofence,
    FlowIssue? issue,
    bool clearIssue = false,
    CheckResult? result,
    bool clearResult = false,
    List<String>? faceImages,
    bool clearFace = false,
  }) => CheckFlowState(
    stage: stage ?? this.stage,
    requestUuid: requestUuid ?? this.requestUuid,
    today: today ?? this.today,
    fix: fix ?? this.fix,
    geofence: geofence ?? this.geofence,
    issue: clearIssue ? null : (issue ?? this.issue),
    result: clearResult ? null : (result ?? this.result),
    faceImages: clearFace ? null : (faceImages ?? this.faceImages),
  );
}

final uuidGeneratorProvider = Provider<String Function()>(
  (ref) =>
      () => const Uuid().v4(),
);

/// Drives one check-in or check-out. Success is only ever reported from a
/// 201 answer; nothing is queued or assumed while offline.
class CheckFlowController extends Notifier<CheckFlowState> {
  CheckFlowController(this.direction);

  final AttendanceDirection direction;

  AttendanceRepository get _repo => ref.read(attendanceRepositoryProvider);
  LocationService get _location => ref.read(locationServiceProvider);
  CameraPermissionService get _camera =>
      ref.read(cameraPermissionServiceProvider);

  static const gpsTimeLimit = Duration(seconds: 20);

  @override
  CheckFlowState build() => CheckFlowState(
    stage: CheckStage.preparing,
    requestUuid: ref.read(uuidGeneratorProvider)(),
  );

  void _block(IssueKind kind, {Object? failure}) {
    state = state.copyWith(
      stage: CheckStage.blocked,
      issue: FlowIssue(kind, failure: failure),
    );
  }

  /// Step 1–2: load rules, check connectivity and location permission.
  Future<void> start() async {
    state = state.copyWith(
      stage: CheckStage.preparing,
      clearIssue: true,
      clearResult: true,
    );
    if (!await ref.read(connectivityProvider).isOnline()) {
      if (!ref.mounted) return;
      return _block(IssueKind.offline);
    }
    final TodayStatus today;
    try {
      today = await _repo.today();
    } on OfflineFailure catch (failure) {
      if (!ref.mounted) return;
      return _block(IssueKind.offline, failure: failure);
    } catch (failure) {
      if (!ref.mounted) return;
      return _block(IssueKind.todayFailed, failure: failure);
    }
    if (!ref.mounted) return;
    state = state.copyWith(today: today);
    if (!today.employed) return _block(IssueKind.notEmployed);
    if (!today.rules.mobileCheckinEnabled) {
      return _block(IssueKind.mobileDisabled);
    }
    await _checkLocationAccess(askIfNeeded: false);
  }

  Future<void> _checkLocationAccess({required bool askIfNeeded}) async {
    if (!await _location.isServiceEnabled()) {
      if (!ref.mounted) return;
      return _block(IssueKind.locationServicesOff);
    }
    var permission = await _location.checkPermission();
    if (!ref.mounted) return;
    if (permission == LocationPermissionState.denied) {
      if (!askIfNeeded) {
        // Explain first, then show the OS prompt (acceptRationale).
        state = state.copyWith(stage: CheckStage.rationale, clearIssue: true);
        return;
      }
      permission = await _location.requestPermission();
      if (!ref.mounted) return;
    }
    switch (permission) {
      case LocationPermissionState.granted:
        await locate();
      case LocationPermissionState.denied:
        _block(IssueKind.locationPermissionDenied);
      case LocationPermissionState.permanentlyDenied:
        _block(IssueKind.locationPermissionPermanentlyDenied);
    }
  }

  /// The user read the explanation and continues to the OS prompt.
  Future<void> acceptRationale() => _checkLocationAccess(askIfNeeded: true);

  /// Step 3–4: obtain the location and validate accuracy and geofence.
  Future<void> locate() async {
    final today = state.today;
    if (today == null) return start();
    state = state.copyWith(stage: CheckStage.locating, clearIssue: true);

    final LocationFix fix;
    try {
      fix = await _location.currentFix(timeLimit: gpsTimeLimit);
    } on LocationTimeoutException {
      if (!ref.mounted) return;
      return _block(IssueKind.gpsTimeout);
    } on LocationUnavailableException catch (error) {
      if (!ref.mounted) return;
      return _block(
        error.detail == 'service_disabled'
            ? IssueKind.locationServicesOff
            : IssueKind.gpsUnavailable,
      );
    }
    if (!ref.mounted) return;

    final geofence = evaluateGeofence(today, fix, direction);
    state = state.copyWith(fix: fix, geofence: geofence);

    final maxAccuracy = today.rules.maxAccuracyMeters;
    if (maxAccuracy > 0 && fix.accuracy > maxAccuracy) {
      state = state.copyWith(
        stage: CheckStage.blocked,
        issue: FlowIssue(
          IssueKind.poorAccuracy,
          accuracy: fix.accuracy,
          maxAccuracy: maxAccuracy,
        ),
      );
      return;
    }

    if (!geofence.allowAnywhere && geofence.nearest == null) {
      return _block(IssueKind.noLocationConfigured);
    }

    // Allow the accuracy tolerance the server also applies.
    final tolerance = today.rules.accuracyToleranceMeters;
    if (!geofence.allowAnywhere &&
        geofence.distance! > geofence.radius! + tolerance) {
      state = state.copyWith(
        stage: CheckStage.blocked,
        issue: FlowIssue(
          IssueKind.outsideGeofence,
          distance: geofence.distance,
          radius: geofence.radius,
          locationName: geofence.nearest!.name,
        ),
      );
      return;
    }

    if (today.face.shouldCapture) {
      if (!today.face.enrolled) {
        return _block(IssueKind.faceNotEnrolled);
      }
      state = state.copyWith(stage: CheckStage.awaitingFace, clearIssue: true);
      return;
    }
    await submit();
  }

  /// Step 5: called by the screen before opening the camera.
  /// Returns true when the camera may be opened.
  Future<bool> ensureCameraPermission() async {
    var status = await _camera.status();
    if (status == CameraPermissionState.denied) {
      status = await _camera.request();
    }
    if (!ref.mounted) return false;
    switch (status) {
      case CameraPermissionState.granted:
        return true;
      case CameraPermissionState.denied:
        _block(IssueKind.cameraPermissionDenied);
        return false;
      case CameraPermissionState.permanentlyDenied:
        _block(IssueKind.cameraPermissionPermanentlyDenied);
        return false;
    }
  }

  /// Step 6 done: frames captured (base64 JPEG).
  Future<void> faceCaptured(List<String> images) async {
    state = state.copyWith(faceImages: images);
    await submit();
  }

  void faceCaptureFailed(FaceCameraError error) {
    _block(switch (error) {
      FaceCameraError.permissionDenied => IssueKind.cameraPermissionDenied,
      FaceCameraError.noCamera => IssueKind.noCamera,
      FaceCameraError.failed => IssueKind.cameraUnavailable,
    });
  }

  /// The user closed the camera without capturing.
  void faceCaptureCancelled() {
    state = state.copyWith(stage: CheckStage.awaitingFace, clearIssue: true);
  }

  /// When face verification is optional, the user may submit without it
  /// (the server may send the attempt for review).
  bool get canSkipFace => !(state.today?.face.isRequired ?? true);

  Future<void> continueWithoutFace() async {
    if (!canSkipFace) return;
    state = state.copyWith(clearFace: true);
    await submit();
  }

  /// Step 7–8: submit and show the server's decision.
  Future<void> submit() async {
    final fix = state.fix;
    if (fix == null) return locate();
    state = state.copyWith(stage: CheckStage.submitting, clearIssue: true);

    if (!await ref.read(connectivityProvider).isOnline()) {
      if (!ref.mounted) return;
      return _block(IssueKind.offline);
    }

    final config = ref.read(appConfigProvider);
    final request = CheckRequest(
      requestUuid: state.requestUuid,
      latitude: fix.latitude,
      longitude: fix.longitude,
      accuracy: fix.accuracy,
      isMockLocation: fix.isMocked,
      deviceTime: Formatter.isoWithOffset(DateTime.now()),
      deviceId: await ref.read(tokenStorageProvider).deviceId(),
      platform: _platform(),
      appVersion: config.appVersion,
      faceImages: state.faceImages,
    );

    try {
      final result = await _repo.submit(direction, request);
      if (!ref.mounted) return;
      state = state.copyWith(stage: CheckStage.finished, result: result);
      if (result is CheckAccepted || result is CheckFlagged) {
        ref.invalidate(todayProvider);
      }
    } on OfflineFailure catch (failure) {
      if (!ref.mounted) return;
      _block(IssueKind.offline, failure: failure);
    } on Failure catch (failure) {
      if (!ref.mounted) return;
      _block(IssueKind.submitFailed, failure: failure);
    }
  }

  /// "Try again" from a blocked state. Keeps the request uuid when the
  /// previous submission got no server decision.
  Future<void> retry() async {
    final issue = state.issue;
    if (issue == null) return start();
    switch (issue.kind) {
      case IssueKind.offline:
      case IssueKind.submitFailed:
        if (state.fix != null && state.today != null) return submit();
        return start();
      case IssueKind.todayFailed:
      case IssueKind.notEmployed:
      case IssueKind.mobileDisabled:
      case IssueKind.noLocationConfigured:
      case IssueKind.faceNotEnrolled:
        return start();
      case IssueKind.locationServicesOff:
      case IssueKind.locationPermissionDenied:
      case IssueKind.locationPermissionPermanentlyDenied:
        return _checkLocationAccess(askIfNeeded: true);
      case IssueKind.gpsTimeout:
      case IssueKind.gpsUnavailable:
      case IssueKind.poorAccuracy:
      case IssueKind.outsideGeofence:
        return locate();
      case IssueKind.cameraPermissionDenied:
      case IssueKind.cameraPermissionPermanentlyDenied:
      case IssueKind.cameraUnavailable:
      case IssueKind.noCamera:
        state = state.copyWith(
          stage: CheckStage.awaitingFace,
          clearIssue: true,
        );
    }
  }

  /// After a rejection: a new attempt with a new uuid.
  Future<void> retryAfterRejection() async {
    final result = state.result;
    state = state.copyWith(
      requestUuid: ref.read(uuidGeneratorProvider)(),
      clearResult: true,
      clearFace: true,
    );
    if (result is CheckRejected &&
        result.attempt.hasReason('face_retry') &&
        (result.faceRemainingAttempts ?? 0) > 0) {
      state = state.copyWith(stage: CheckStage.awaitingFace, clearIssue: true);
      return;
    }
    await locate();
  }

  /// Opens the system settings the current issue needs.
  Future<void> openSettings() async {
    final issue = state.issue;
    if (issue == null) return;
    switch (issue.kind) {
      case IssueKind.locationServicesOff:
        await _location.openLocationSettings();
      case IssueKind.locationPermissionPermanentlyDenied:
        await _location.openAppSettings();
      case IssueKind.cameraPermissionPermanentlyDenied:
        await _camera.openSettings();
      default:
        break;
    }
  }

  static String? _platform() {
    if (kIsWeb) return null;
    return switch (defaultTargetPlatform) {
      TargetPlatform.android => 'android',
      TargetPlatform.iOS => 'ios',
      _ => null,
    };
  }
}

/// Nearest allowed location and whether [fix] is within its radius.
GeofenceCheck evaluateGeofence(
  TodayStatus today,
  LocationFix fix,
  AttendanceDirection direction,
) {
  if (today.allowAnywhere) return const GeofenceCheck(allowAnywhere: true);
  WorkLocation? nearest;
  double? best;
  for (final location in today.locations) {
    final d = distanceMeters(
      fix.latitude,
      fix.longitude,
      location.latitude,
      location.longitude,
    );
    if (best == null || d < best) {
      best = d;
      nearest = location;
    }
  }
  return GeofenceCheck(
    allowAnywhere: false,
    nearest: nearest,
    distance: best,
    radius: nearest?.radiusFor(direction),
  );
}

final checkFlowProvider = NotifierProvider.autoDispose
    .family<CheckFlowController, CheckFlowState, AttendanceDirection>(
      CheckFlowController.new,
    );
