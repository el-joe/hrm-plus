import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/misc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hrm_plus/core/l10n/l10n.dart';
import 'package:hrm_plus/core/providers.dart';
import 'package:hrm_plus/core/theme/app_theme.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'fake_http.dart';

/// Pumps [screen] inside the app's theme and localisations, with
/// SharedPreferences mocked and the given provider overrides.
Future<void> pumpScreen(
  WidgetTester tester,
  Widget screen, {
  List<Override> overrides = const [],
  Locale locale = const Locale('ar'),
}) async {
  SharedPreferences.setMockInitialValues({'ui.locale': locale.languageCode});
  final prefs = await SharedPreferences.getInstance();
  await tester.pumpWidget(
    ProviderScope(
      retry: (_, _) => null,
      overrides: [
        sharedPreferencesProvider.overrideWithValue(prefs),
        appConfigProvider.overrideWithValue(testConfig),
        ...overrides,
      ],
      child: MaterialApp(
        locale: locale,
        supportedLocales: supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: AppTheme.light(),
        home: screen,
      ),
    ),
  );
}
