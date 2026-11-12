/// Route paths. Detail routes take ids as path parameters so push deep links
/// can target them directly.
abstract final class Routes {
  static const splash = '/splash';
  static const login = '/login';
  static const forgotPassword = '/forgot-password';
  static const otp = '/otp';

  static const home = '/home';
  static const attendance = '/attendance';
  static const checkIn = '/attendance/check-in';
  static const checkOut = '/attendance/check-out';
  static const gps = '/attendance/gps';
  static const face = '/attendance/face';
  static const attendanceHistory = '/attendance/history';
  static const schedule = '/attendance/schedule';

  static const leave = '/leave';
  static const newLeave = '/leave/new';
  static const leaveHistory = '/leave/history';
  static String leaveRequest(Object id) => '/leave/requests/$id';

  static const payslips = '/payslips';
  static String payslip(Object id) => '/payslips/$id';
  static const loans = '/loans';

  static const requests = '/requests';
  static const newRequest = '/requests/new';
  static String request(Object id) => '/requests/$id';

  static const documents = '/documents';
  static const announcements = '/announcements';
  static String announcement(Object id) => '/announcements/$id';
  static const notifications = '/notifications';

  static const more = '/more';
  static const profile = '/profile';
  static const settings = '/settings';
  static const notificationPreferences = '/settings/notifications';

  /// Reachable without a session.
  static const public = {splash, login, forgotPassword, otp};
}
