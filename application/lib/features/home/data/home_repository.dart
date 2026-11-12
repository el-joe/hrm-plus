import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/json.dart';
import '../../../core/providers.dart';

/// `GET me/home` — one call for the home cards.
class HomeSummary {
  const HomeSummary({
    required this.employeeName,
    required this.jobTitle,
    required this.photoUrl,
    required this.attendanceState,
    required this.checkInLocal,
    required this.checkOutLocal,
    required this.shiftName,
    required this.shiftStart,
    required this.shiftEnd,
    required this.month,
    required this.leaveBalances,
    required this.pending,
    required this.latestPayslip,
    required this.tasks,
    required this.unreadNotifications,
    required this.unreadAnnouncements,
  });

  factory HomeSummary.fromJson(Json json) {
    final employee = json.obj('employee') ?? const {};
    final today = json.obj('attendance_today') ?? const {};
    final shifts = today.objList('shifts');
    return HomeSummary(
      employeeName: employee.str('name'),
      jobTitle: employee.strOrNull('job_title'),
      photoUrl: employee.strOrNull('photo_url'),
      attendanceState: today.str('state', 'unknown'),
      checkInLocal: today.strOrNull('check_in_local'),
      checkOutLocal: today.strOrNull('check_out_local'),
      shiftName: shifts.isEmpty ? null : shifts.first.strOrNull('name'),
      shiftStart: shifts.isEmpty ? null : shifts.first.strOrNull('start_local'),
      shiftEnd: shifts.isEmpty ? null : shifts.first.strOrNull('end_local'),
      month: json.obj('attendance_month') ?? const {},
      leaveBalances: json.objList('leave_balances'),
      pending: json.obj('pending') ?? const {},
      latestPayslip: json.obj('latest_payslip'),
      tasks: json.objList('tasks'),
      unreadNotifications: json.integer('unread_notifications'),
      unreadAnnouncements: json.integer('unread_announcements'),
    );
  }

  final String employeeName;
  final String? jobTitle;
  final String? photoUrl;
  final String attendanceState;
  final String? checkInLocal;
  final String? checkOutLocal;
  final String? shiftName;
  final String? shiftStart;
  final String? shiftEnd;

  /// present_days, late_days, absent_days, worked_minutes …
  final Json month;
  final List<Json> leaveBalances;
  final Json pending;
  final Json? latestPayslip;
  final List<Json> tasks;
  final int unreadNotifications;
  final int unreadAnnouncements;

  /// The first balance-tracked leave type (annual leave in the demo data).
  Json? get primaryBalance =>
      leaveBalances.isEmpty ? null : leaveBalances.first;

  int get pendingTotal => pending.values.fold<int>(
    0,
    (sum, value) => sum + (value is int ? value : 0),
  );
}

class HomeRepository {
  HomeRepository(this._api);

  final ApiClient _api;

  Future<HomeSummary> home() async =>
      HomeSummary.fromJson((await _api.get('me/home')).json);
}

final homeRepositoryProvider = Provider<HomeRepository>(
  (ref) => HomeRepository(ref.watch(apiClientProvider)),
);

final homeProvider = FutureProvider.autoDispose<HomeSummary>(
  (ref) => ref.watch(homeRepositoryProvider).home(),
);
