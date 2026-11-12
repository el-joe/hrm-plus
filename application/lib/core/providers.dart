import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../features/auth/application/auth_controller.dart';
import 'config/app_config.dart';
import 'files/file_opener.dart';
import 'network/api_client.dart';
import 'network/connectivity.dart';
import 'storage/preferences.dart';
import 'storage/token_storage.dart';
import 'utils/formatters.dart';

/// Composition root. main.dart overrides [sharedPreferencesProvider];
/// tests override the rest with fakes.
final appConfigProvider = Provider<AppConfig>(
  (ref) => AppConfig.fromEnvironment(),
);

final sharedPreferencesProvider = Provider<SharedPreferences>(
  (ref) => throw UnimplementedError('Override in main() / tests'),
);

final appPreferencesProvider = Provider<AppPreferences>(
  (ref) => AppPreferences(ref.watch(sharedPreferencesProvider)),
);

final tokenStorageProvider = Provider<TokenStorage>(
  (ref) => SecureTokenStorage(),
);

final connectivityProvider = Provider<ConnectivityChecker>(
  (ref) => PlatformConnectivityChecker(),
);

final fileOpenerProvider = Provider<FileOpener>((ref) => createFileOpener());

final apiClientProvider = Provider<ApiClient>((ref) {
  return ApiClient.create(
    config: ref.watch(appConfigProvider),
    storage: ref.watch(tokenStorageProvider),
    languageCode: () => ref.read(settingsProvider).locale.languageCode,
    onSessionExpired: () =>
        ref.read(authControllerProvider.notifier).sessionExpired(),
  );
});

/// UI settings: language, theme and digits.
class AppSettings {
  const AppSettings({
    required this.locale,
    required this.themeMode,
    required this.digits,
  });

  final Locale locale;
  final ThemeMode themeMode;
  final DigitStyle digits;

  AppSettings copyWith({
    Locale? locale,
    ThemeMode? themeMode,
    DigitStyle? digits,
  }) => AppSettings(
    locale: locale ?? this.locale,
    themeMode: themeMode ?? this.themeMode,
    digits: digits ?? this.digits,
  );
}

class SettingsController extends Notifier<AppSettings> {
  AppPreferences get _prefs => ref.read(appPreferencesProvider);

  @override
  AppSettings build() {
    final prefs = ref.watch(appPreferencesProvider);
    return AppSettings(
      locale: prefs.locale,
      themeMode: prefs.themeMode,
      digits: prefs.digits,
    );
  }

  Future<void> setLocale(Locale locale) async {
    state = state.copyWith(locale: locale);
    await _prefs.setLocale(locale);
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    state = state.copyWith(themeMode: mode);
    await _prefs.setThemeMode(mode);
  }

  Future<void> setDigits(DigitStyle digits) async {
    state = state.copyWith(digits: digits);
    await _prefs.setDigits(digits);
  }
}

final settingsProvider = NotifierProvider<SettingsController, AppSettings>(
  SettingsController.new,
);

final formatterProvider = Provider<Formatter>((ref) {
  final settings = ref.watch(settingsProvider);
  return Formatter(
    languageCode: settings.locale.languageCode,
    digits: settings.digits,
  );
});
