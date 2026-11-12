import '../../../core/network/json.dart';

enum AttendanceDirection {
  checkIn('in', 'check-in'),
  checkOut('out', 'check-out');

  const AttendanceDirection(this.api, this.path);

  /// Value used by the API in attempts (`direction: in|out`).
  final String api;

  /// Endpoint suffix (`me/attendance/check-in`).
  final String path;
}

/// An allowed check-in location (geofence) from `today.locations.items`.
class WorkLocation {
  const WorkLocation({
    required this.id,
    required this.name,
    required this.latitude,
    required this.longitude,
    required this.radiusMeters,
    required this.checkoutRadiusMeters,
  });

  factory WorkLocation.fromJson(Json json) => WorkLocation(
    id: json.integer('id'),
    name: json.str('name'),
    // The API sends coordinates as decimal strings.
    latitude: json.doubleOrNull('latitude') ?? 0,
    longitude: json.doubleOrNull('longitude') ?? 0,
    radiusMeters: json.integer('radius_meters'),
    checkoutRadiusMeters: json.integer(
      'checkout_radius_meters',
      json.integer('radius_meters'),
    ),
  );

  final int id;
  final String name;
  final double latitude;
  final double longitude;
  final int radiusMeters;
  final int checkoutRadiusMeters;

  int radiusFor(AttendanceDirection direction) =>
      direction == AttendanceDirection.checkOut
      ? checkoutRadiusMeters
      : radiusMeters;
}

class AttendanceRules {
  const AttendanceRules({
    required this.mobileCheckinEnabled,
    required this.maxAccuracyMeters,
    required this.accuracyToleranceMeters,
    required this.mockLocationAction,
  });

  factory AttendanceRules.fromJson(Json json) => AttendanceRules(
    mobileCheckinEnabled: json.boolean('mobile_checkin_enabled', true),
    maxAccuracyMeters: json.integer('max_accuracy_meters', 50),
    accuracyToleranceMeters: json.integer('accuracy_tolerance_meters'),
    mockLocationAction: json.str('mock_location_action', 'review'),
  );

  final bool mobileCheckinEnabled;
  final int maxAccuracyMeters;
  final int accuracyToleranceMeters;
  final String mockLocationAction;
}

/// `face` block of `me/attendance/today` and `GET me/face`.
class FaceStatus {
  const FaceStatus({
    required this.mode,
    required this.maxAttempts,
    required this.remainingAttempts,
    required this.isSimulated,
    required this.consentGiven,
    required this.consentVersion,
    required this.enrolled,
    this.enrolledAt,
    this.expiresAt,
  });

  factory FaceStatus.fromJson(Json json) {
    final consent = json.obj('consent') ?? const {};
    final enrolment = json.obj('enrolment');
    return FaceStatus(
      mode: json.str('mode', 'off'),
      maxAttempts: json.integer('max_attempts', 3),
      remainingAttempts: json.integer('remaining_attempts', 3),
      isSimulated: json.boolean('is_simulated'),
      consentGiven: consent.boolean('given'),
      consentVersion: consent.str('current_version'),
      enrolled: json.boolean('enrolled'),
      enrolledAt: enrolment?.date('enrolled_at'),
      expiresAt: enrolment?.date('expires_at'),
    );
  }

  /// off | optional | required (policy-driven per employee).
  final String mode;
  final int maxAttempts;
  final int remainingAttempts;
  final bool isSimulated;
  final bool consentGiven;
  final String consentVersion;
  final bool enrolled;
  final DateTime? enrolledAt;
  final DateTime? expiresAt;

  bool get isOff => mode == 'off';
  bool get isRequired => mode == 'required';

  /// Face capture is part of the flow when the policy asks for it and the
  /// employee can actually be verified (or the policy requires it, in which
  /// case the server answers face_not_enrolled / face_required).
  bool get shouldCapture => !isOff && (enrolled || isRequired);
}

class TodayShift {
  const TodayShift({
    required this.name,
    required this.startLocal,
    required this.endLocal,
    this.checkInOpensAt,
    this.checkOutClosesAt,
  });

  factory TodayShift.fromJson(Json json) => TodayShift(
    name: json.str('name'),
    startLocal: json.str('start_local'),
    endLocal: json.str('end_local'),
    checkInOpensAt: json.date('check_in_opens_at'),
    checkOutClosesAt: json.date('check_out_closes_at'),
  );

  final String name;
  final String startLocal;
  final String endLocal;
  final DateTime? checkInOpensAt;
  final DateTime? checkOutClosesAt;
}

class TodayPunch {
  const TodayPunch({
    required this.local,
    required this.direction,
    required this.source,
  });

  factory TodayPunch.fromJson(Json json) => TodayPunch(
    local: json.str('local'),
    direction: json.str('direction'),
    source: json.str('source'),
  );

  final String local;
  final String direction;
  final String source;
}

/// `GET me/attendance/today`.
class TodayStatus {
  const TodayStatus({
    required this.date,
    required this.serverTime,
    required this.employed,
    required this.isWorkingDay,
    required this.isRestDay,
    required this.holiday,
    required this.state,
    required this.nextAction,
    required this.withinShiftWindow,
    required this.shifts,
    required this.punches,
    required this.allowAnywhere,
    required this.locations,
    required this.rules,
    required this.face,
    required this.pendingReviews,
    required this.day,
  });

  factory TodayStatus.fromJson(Json json) {
    final locations = json.obj('locations') ?? const {};
    final days = json.objList('days');
    return TodayStatus(
      date: json.str('date'),
      serverTime: json.date('server_time'),
      employed: json.boolean('employed', true),
      isWorkingDay: json.boolean('is_working_day'),
      isRestDay: json.boolean('is_rest_day'),
      holiday: json.strOrNull('holiday'),
      state: json.str('state', 'unknown'),
      nextAction: json.str('next_action', 'check_in'),
      withinShiftWindow: json.boolean('within_shift_window'),
      shifts: json.objList('shifts').map(TodayShift.fromJson).toList(),
      punches: json.objList('punches').map(TodayPunch.fromJson).toList(),
      allowAnywhere: locations.boolean('allow_anywhere'),
      locations: locations.objList('items').map(WorkLocation.fromJson).toList(),
      rules: AttendanceRules.fromJson(json.obj('rules') ?? const {}),
      face: FaceStatus.fromJson(json.obj('face') ?? const {}),
      pendingReviews: json.integer('pending_reviews'),
      day: days.isEmpty ? null : AttendanceDay.fromJson(days.first),
    );
  }

  final String date;
  final DateTime? serverTime;
  final bool employed;
  final bool isWorkingDay;
  final bool isRestDay;
  final String? holiday;

  /// not_checked_in | checked_in | checked_out | unknown
  final String state;

  /// check_in | check_out
  final String nextAction;
  final bool withinShiftWindow;
  final List<TodayShift> shifts;
  final List<TodayPunch> punches;
  final bool allowAnywhere;
  final List<WorkLocation> locations;
  final AttendanceRules rules;
  final FaceStatus face;
  final int pendingReviews;
  final AttendanceDay? day;

  AttendanceDirection get nextDirection => nextAction == 'check_out'
      ? AttendanceDirection.checkOut
      : AttendanceDirection.checkIn;

  bool get canUseMobile => employed && rules.mobileCheckinEnabled;

  String? get firstIn {
    for (final p in punches) {
      if (p.direction == 'in') return p.local;
    }
    return day?.checkInLocal;
  }

  String? get lastOut {
    for (final p in punches.reversed) {
      if (p.direction == 'out') return p.local;
    }
    return day?.checkOutLocal;
  }
}

class AttemptReason {
  const AttemptReason({required this.code, required this.message});

  factory AttemptReason.fromJson(Json json) =>
      AttemptReason(code: json.str('code'), message: json.str('message'));

  final String code;
  final String message;
}

/// `MobileAttemptResource`: the server's decision on a check-in/out.
class AttendanceAttempt {
  const AttendanceAttempt({
    required this.requestUuid,
    required this.direction,
    required this.outcome,
    required this.reviewStatus,
    required this.recorded,
    required this.serverTimeLocal,
    required this.reasons,
    required this.locationName,
    required this.distanceMeters,
    required this.allowedRadiusMeters,
    required this.withinGeofence,
    required this.accuracyMeters,
    required this.faceResult,
    required this.faceSimulated,
    this.serverTime,
    this.workDate,
  });

  factory AttendanceAttempt.fromJson(Json json) {
    final location = json.obj('location') ?? const {};
    final face = json.obj('face') ?? const {};
    return AttendanceAttempt(
      requestUuid: json.str('request_uuid'),
      direction: json.str('direction'),
      outcome: json.str('outcome'),
      reviewStatus: json.strOrNull('review_status'),
      recorded: json.boolean('recorded'),
      serverTime: json.date('server_time'),
      serverTimeLocal: json.str('server_time_local'),
      workDate: json.strOrNull('work_date'),
      reasons: json.objList('reasons').map(AttemptReason.fromJson).toList(),
      locationName: location.strOrNull('name'),
      distanceMeters: location.intOrNull('distance_meters'),
      allowedRadiusMeters: location.intOrNull('allowed_radius_meters'),
      withinGeofence: location['within_geofence'] as bool?,
      accuracyMeters: location.doubleOrNull('accuracy_meters'),
      faceResult: face.strOrNull('result'),
      faceSimulated: face.boolean('is_simulated'),
    );
  }

  final String requestUuid;
  final String direction;

  /// accepted | flagged | rejected
  final String outcome;
  final String? reviewStatus;
  final bool recorded;
  final DateTime? serverTime;
  final String serverTimeLocal;
  final String? workDate;
  final List<AttemptReason> reasons;
  final String? locationName;
  final int? distanceMeters;
  final int? allowedRadiusMeters;
  final bool? withinGeofence;
  final double? accuracyMeters;
  final String? faceResult;
  final bool faceSimulated;

  bool hasReason(String code) => reasons.any((r) => r.code == code);
}

/// `AttendanceDayResource`.
class AttendanceDay {
  const AttendanceDay({
    required this.date,
    required this.status,
    required this.statusLabel,
    required this.shiftName,
    required this.checkInLocal,
    required this.checkOutLocal,
    required this.workedMinutes,
    required this.lateMinutes,
    required this.earlyLeaveMinutes,
    required this.overtimeMinutes,
    required this.flags,
    required this.holiday,
  });

  factory AttendanceDay.fromJson(Json json) => AttendanceDay(
    date: json.str('date'),
    status: json.str('status'),
    statusLabel: json.str('status_label'),
    shiftName: json.obj('shift')?.strOrNull('name'),
    checkInLocal: json.strOrNull('check_in_local'),
    checkOutLocal: json.strOrNull('check_out_local'),
    workedMinutes: json.integer('worked_minutes'),
    lateMinutes: json.integer('late_minutes'),
    earlyLeaveMinutes: json.integer('early_leave_minutes'),
    overtimeMinutes: json.integer('overtime_minutes'),
    flags: [for (final f in json.objList('flags')) f.str('label')],
    holiday: json.strOrNull('holiday'),
  );

  final String date;
  final String status;
  final String statusLabel;
  final String? shiftName;
  final String? checkInLocal;
  final String? checkOutLocal;
  final int workedMinutes;
  final int lateMinutes;
  final int earlyLeaveMinutes;
  final int overtimeMinutes;
  final List<String> flags;
  final String? holiday;
}

class AttendanceMonth {
  const AttendanceMonth({
    required this.month,
    required this.days,
    required this.summary,
  });

  final String month;
  final List<AttendanceDay> days;

  /// present_days, late_days, absent_days, leave_days, worked_minutes …
  final Json summary;
}

class ScheduleShift {
  const ScheduleShift({
    required this.name,
    required this.startLocal,
    required this.endLocal,
    required this.expectedMinutes,
    required this.isNight,
  });

  factory ScheduleShift.fromJson(Json json) => ScheduleShift(
    name: json.str('name'),
    startLocal: json.str('start_local'),
    endLocal: json.str('end_local'),
    expectedMinutes: json.integer('expected_minutes'),
    isNight: json.boolean('is_night'),
  );

  final String name;
  final String startLocal;
  final String endLocal;
  final int expectedMinutes;
  final bool isNight;
}

class ScheduleDay {
  const ScheduleDay({
    required this.date,
    required this.isWorkingDay,
    required this.isRestDay,
    required this.holiday,
    required this.shifts,
  });

  factory ScheduleDay.fromJson(Json json) => ScheduleDay(
    date: json.str('date'),
    isWorkingDay: json.boolean('is_working_day'),
    isRestDay: json.boolean('is_rest_day'),
    holiday: json.obj('holiday')?.strOrNull('name'),
    shifts: json.objList('shifts').map(ScheduleShift.fromJson).toList(),
  );

  final String date;
  final bool isWorkingDay;
  final bool isRestDay;
  final String? holiday;
  final List<ScheduleShift> shifts;
}

class AttendanceCorrection {
  const AttendanceCorrection({
    required this.id,
    required this.date,
    required this.reason,
    required this.status,
    required this.statusLabel,
  });

  factory AttendanceCorrection.fromJson(Json json) => AttendanceCorrection(
    id: json.integer('id'),
    date: json.str('date'),
    reason: json.str('reason'),
    status: json.str('status'),
    statusLabel: json.str('status_label'),
  );

  final int id;
  final String date;
  final String reason;
  final String status;
  final String statusLabel;
}
