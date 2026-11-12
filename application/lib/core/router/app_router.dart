import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/announcements/presentation/announcement_screen.dart';
import '../../features/announcements/presentation/announcements_screen.dart';
import '../../features/attendance/domain/models.dart';
import '../../features/attendance/presentation/attendance_history_screen.dart';
import '../../features/attendance/presentation/attendance_screen.dart';
import '../../features/attendance/presentation/check_screen.dart';
import '../../features/attendance/presentation/face_screen.dart';
import '../../features/attendance/presentation/gps_validation_screen.dart';
import '../../features/attendance/presentation/schedule_screen.dart';
import '../../features/auth/application/auth_controller.dart';
import '../../features/auth/presentation/forgot_password_screen.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/auth/presentation/otp_screen.dart';
import '../../features/auth/presentation/splash_screen.dart';
import '../../features/documents/presentation/documents_screen.dart';
import '../../features/home/presentation/home_screen.dart';
import '../../features/home/presentation/main_shell.dart';
import '../../features/home/presentation/more_screen.dart';
import '../../features/leave/presentation/leave_balance_screen.dart';
import '../../features/leave/presentation/leave_history_screen.dart';
import '../../features/leave/presentation/leave_request_screen.dart';
import '../../features/leave/presentation/new_leave_screen.dart';
import '../../features/loans/presentation/loans_screen.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../../features/payslips/presentation/payslip_detail_screen.dart';
import '../../features/payslips/presentation/payslips_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/requests/presentation/new_request_screen.dart';
import '../../features/requests/presentation/request_details_screen.dart';
import '../../features/requests/presentation/requests_screen.dart';
import '../../features/settings/presentation/notification_preferences_screen.dart';
import '../../features/settings/presentation/settings_screen.dart';
import 'guard.dart';
import 'routes.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>();

/// Bridges the auth state to go_router's refreshListenable.
class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen<AuthState>(authControllerProvider, (_, _) => notifyListeners());
  }
}

int _id(GoRouterState state) =>
    int.tryParse(state.pathParameters['id'] ?? '') ?? 0;

final appRouterProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthListenable(ref);
  ref.onDispose(refresh.dispose);

  GoRoute page(String path, Widget Function(GoRouterState) build) => GoRoute(
    path: path,
    parentNavigatorKey: rootNavigatorKey,
    builder: (context, state) => build(state),
  );

  final router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: Routes.splash,
    refreshListenable: refresh,
    redirect: (context, state) =>
        authRedirect(ref.read(authControllerProvider).status, state.uri),
    routes: [
      page(Routes.splash, (_) => const SplashScreen()),
      page(
        Routes.login,
        (s) => LoginScreen(from: s.uri.queryParameters['from']),
      ),
      page(Routes.forgotPassword, (_) => const ForgotPasswordScreen()),
      page(
        Routes.otp,
        (s) => OtpScreen(email: s.uri.queryParameters['email'] ?? ''),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => MainShell(shell: shell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(path: Routes.home, builder: (_, _) => const HomeScreen()),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.attendance,
                builder: (_, _) => const AttendanceScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.leave,
                builder: (_, _) => const LeaveBalanceScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: Routes.payslips,
                builder: (_, _) => const PayslipsScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(path: Routes.more, builder: (_, _) => const MoreScreen()),
            ],
          ),
        ],
      ),
      page(
        Routes.checkIn,
        (_) => const CheckScreen(direction: AttendanceDirection.checkIn),
      ),
      page(
        Routes.checkOut,
        (_) => const CheckScreen(direction: AttendanceDirection.checkOut),
      ),
      page(Routes.gps, (_) => const GpsValidationScreen()),
      page(Routes.face, (_) => const FaceScreen()),
      page(Routes.attendanceHistory, (_) => const AttendanceHistoryScreen()),
      page(Routes.schedule, (_) => const ScheduleScreen()),
      page(Routes.newLeave, (_) => const NewLeaveScreen()),
      page(Routes.leaveHistory, (_) => const LeaveHistoryScreen()),
      page('/leave/requests/:id', (s) => LeaveRequestScreen(id: _id(s))),
      page('/payslips/:id', (s) => PayslipDetailScreen(id: _id(s))),
      page(Routes.loans, (_) => const LoansScreen()),
      page(Routes.requests, (_) => const RequestsScreen()),
      page(Routes.newRequest, (s) {
        final category = int.tryParse(s.uri.queryParameters['category'] ?? '');
        return NewRequestScreen(
          initialCategoryCode: s.uri.queryParameters['code'],
          initialCategoryId: category,
        );
      }),
      page('/requests/:id', (s) => RequestDetailsScreen(id: _id(s))),
      page(Routes.documents, (_) => const DocumentsScreen()),
      page(Routes.announcements, (_) => const AnnouncementsScreen()),
      page('/announcements/:id', (s) => AnnouncementScreen(id: _id(s))),
      page(Routes.notifications, (_) => const NotificationsScreen()),
      page(Routes.profile, (_) => const ProfileScreen()),
      page(Routes.settings, (_) => const SettingsScreen()),
      page(
        Routes.notificationPreferences,
        (_) => const NotificationPreferencesScreen(),
      ),
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});
