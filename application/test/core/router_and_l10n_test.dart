import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/l10n/l10n.dart';
import 'package:hrm_plus/core/router/deep_links.dart';
import 'package:hrm_plus/core/router/guard.dart';
import 'package:hrm_plus/core/router/routes.dart';
import 'package:hrm_plus/core/storage/preferences.dart';
import 'package:hrm_plus/core/utils/formatters.dart';
import 'package:hrm_plus/core/widgets/states.dart';
import 'package:hrm_plus/features/auth/application/auth_controller.dart';
import 'package:hrm_plus/features/auth/presentation/login_screen.dart';

import '../helpers/pump.dart';

void main() {
  group('router guard', () {
    Uri u(String s) => Uri.parse(s);

    test('unknown session goes to splash and remembers the target', () {
      expect(authRedirect(AuthStatus.unknown, u('/splash')), isNull);
      expect(
        authRedirect(AuthStatus.unknown, u('/payslips/82')),
        '/splash?from=%2Fpayslips%2F82',
      );
    });

    test('signed out: protected pages go to login, public pages are open', () {
      expect(
        authRedirect(AuthStatus.unauthenticated, u('/home')),
        '/login?from=%2Fhome',
      );
      expect(authRedirect(AuthStatus.unauthenticated, u('/login')), isNull);
      expect(
        authRedirect(AuthStatus.unauthenticated, u('/forgot-password')),
        isNull,
      );
      expect(
        authRedirect(AuthStatus.unauthenticated, u('/otp?email=a')),
        isNull,
      );
      expect(
        authRedirect(AuthStatus.unauthenticated, u('/splash')),
        Routes.login,
      );
      expect(
        authRedirect(AuthStatus.unauthenticated, u('/splash?from=/leave')),
        '/login?from=%2Fleave',
      );
    });

    test('signed in: public pages go home or to the remembered target', () {
      expect(authRedirect(AuthStatus.authenticated, u('/login')), Routes.home);
      expect(authRedirect(AuthStatus.authenticated, u('/splash')), Routes.home);
      expect(
        authRedirect(AuthStatus.authenticated, u('/login?from=/payslips/82')),
        '/payslips/82',
      );
      expect(authRedirect(AuthStatus.authenticated, u('/attendance')), isNull);
    });

    test('a remembered target cannot point back to a public page', () {
      expect(
        authRedirect(AuthStatus.authenticated, u('/login?from=/login')),
        Routes.home,
      );
    });
  });

  group('push deep links', () {
    test('record ids win', () {
      expect(routeForPayload({'announcement_id': '5'}), '/announcements/5');
      expect(routeForPayload({'leave_request_id': '46'}), '/leave/requests/46');
      expect(routeForPayload({'hr_request_id': '3'}), '/requests/3');
      expect(routeForPayload({'payslip_id': '82'}), '/payslips/82');
    });

    test('portal url is the fallback, else the notification centre', () {
      expect(
        routeForPayload({'url': 'http://hrm-plus.loc/portal/announcements'}),
        '/announcements',
      );
      expect(
        routeForPayload({'url': 'http://hrm-plus.loc/portal/requests/4'}),
        '/requests/4',
      );
      expect(
        routeForPayload({'event': 'something_else'}),
        Routes.notifications,
      );
      expect(
        routeForPayload({'event': 'attendance_attempt_reviewed'}),
        Routes.attendanceHistory,
      );
    });
  });

  group('localisation', () {
    test('Arabic and English ARB files have the same keys', () {
      Map<String, dynamic> read(String lang) => (jsonDecode(
        File('lib/core/l10n/arb/app_$lang.arb').readAsStringSync(),
      ) as Map).cast<String, dynamic>();
      final en = read('en').keys.where((k) => !k.startsWith('@')).toSet();
      final ar = read('ar').keys.where((k) => !k.startsWith('@')).toSet();
      expect(ar, en);
    });

    test('Arabic is the first (default) supported locale', () {
      expect(supportedLocales.first, const Locale('ar'));
    });

    testWidgets('login renders right-to-left in Arabic', (tester) async {
      await pumpScreen(tester, const LoginScreen());
      await tester.pumpAndSettle();
      final direction = Directionality.of(
        tester.element(find.byKey(const Key('login-email'))),
      );
      expect(direction, TextDirection.rtl);
      expect(find.text('تسجيل الدخول'), findsOneWidget);
    });

    testWidgets('login renders left-to-right in English', (tester) async {
      await pumpScreen(tester, const LoginScreen(), locale: const Locale('en'));
      await tester.pumpAndSettle();
      final direction = Directionality.of(
        tester.element(find.byKey(const Key('login-submit'))),
      );
      expect(direction, TextDirection.ltr);
    });

    testWidgets('login validates empty fields with localised messages', (
      tester,
    ) async {
      await pumpScreen(tester, const LoginScreen());
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('login-submit')));
      await tester.pump();
      expect(find.text('هذا الحقل مطلوب.'), findsNWidgets(2));
    });
  });

  group('formatting', () {
    test('Western digits by default, Arabic-Indic on request', () {
      const western = Formatter(languageCode: 'ar', digits: DigitStyle.western);
      const eastern = Formatter(
        languageCode: 'ar',
        digits: DigitStyle.arabicIndic,
      );
      expect(western.money('10998.99', 'EGP'), '10,998.99 EGP');
      expect(eastern.d('12:30'), '١٢:٣٠');
      expect(western.d('١٢:٣٠'), '12:30');
      expect(western.decimal('23.00'), '23');
      expect(western.decimal('0.50'), '0.5');
      expect(western.duration(3982), '66:22');
    });

    test('device_time carries the UTC offset', () {
      expect(
        Formatter.isoWithOffset(DateTime(2026, 11, 12, 8, 10)),
        matches(RegExp(r'^2026-11-12T08:10:00[+-]\d\d:\d\d$')),
      );
    });
  });

  group('AsyncView states', () {
    Future<void> pumpView(WidgetTester tester, AsyncValue<List<int>> value) =>
        pumpScreen(
          tester,
          Scaffold(
            body: AsyncView<List<int>>(
              value: value,
              onRefresh: () async {},
              isEmpty: (list) => list.isEmpty,
              builder: (list) =>
                  ListView(children: [for (final i in list) Text('item $i')]),
            ),
          ),
          locale: const Locale('en'),
        );

    testWidgets('loading', (tester) async {
      await pumpView(tester, const AsyncLoading());
      expect(find.byKey(const Key('skeleton-list')), findsOneWidget);
    });

    testWidgets('empty', (tester) async {
      await pumpView(tester, const AsyncData([]));
      expect(find.byKey(const Key('empty-state')), findsOneWidget);
    });

    testWidgets('error', (tester) async {
      await pumpView(tester, AsyncError(Exception('x'), StackTrace.empty));
      expect(find.byKey(const Key('error-state')), findsOneWidget);
      expect(find.text('Try again'), findsOneWidget);
    });

    testWidgets('data', (tester) async {
      await pumpView(tester, const AsyncData([1, 2]));
      expect(find.text('item 2'), findsOneWidget);
      expect(find.byType(RefreshIndicator), findsOneWidget);
    });
  });
}
