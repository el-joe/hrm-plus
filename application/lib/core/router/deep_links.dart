import 'routes.dart';

/// Turns a push payload / notification record into an app route.
///
/// Push `data` (all strings): `event`, `kind`, `notification_id`, `url`, and
/// record ids (`leave_request_id`, `hr_request_id`, `announcement_id`, …).
/// Record ids win; the web `url` (`…/portal/<section>/<id>`) is the fallback;
/// anything else opens the notification centre.
String routeForPayload(Map<String, Object?> data) {
  String? id(String key) {
    final value = data[key]?.toString();
    return value == null || value.isEmpty ? null : value;
  }

  final announcement = id('announcement_id');
  if (announcement != null) return Routes.announcement(announcement);
  final leave = id('leave_request_id');
  if (leave != null) return Routes.leaveRequest(leave);
  final hrRequest = id('hr_request_id');
  if (hrRequest != null) return Routes.request(hrRequest);
  final payslip = id('payslip_id');
  if (payslip != null) return Routes.payslip(payslip);
  if (id('loan_id') != null) return Routes.loans;

  final module =
      '${data['module'] ?? ''} ${data['kind'] ?? ''} ${data['event'] ?? ''}';
  if (module.contains('attendance')) return Routes.attendanceHistory;

  final url = id('url');
  if (url != null) {
    final fromUrl = routeForWebUrl(url);
    if (fromUrl != null) return fromUrl;
  }
  return Routes.notifications;
}

/// Maps a portal URL to the matching app screen.
String? routeForWebUrl(String url) {
  final uri = Uri.tryParse(url);
  if (uri == null) return null;
  final segments = uri.pathSegments.where((s) => s.isNotEmpty).toList();
  final portal = segments.indexOf('portal');
  final rest = portal >= 0 ? segments.sublist(portal + 1) : segments;
  if (rest.isEmpty) return Routes.home;
  final section = rest.first;
  final id = rest.length > 1 && int.tryParse(rest[1]) != null ? rest[1] : null;
  return switch (section) {
    'announcements' =>
      id != null ? Routes.announcement(id) : Routes.announcements,
    'leave' || 'leaves' => Routes.leaveHistory,
    'requests' ||
    'hr-requests' => id != null ? Routes.request(id) : Routes.requests,
    'payslips' ||
    'payroll' => id != null ? Routes.payslip(id) : Routes.payslips,
    'loans' => Routes.loans,
    'attendance' => Routes.attendanceHistory,
    'documents' => Routes.documents,
    'notifications' => Routes.notifications,
    'profile' => Routes.profile,
    _ => null,
  };
}
