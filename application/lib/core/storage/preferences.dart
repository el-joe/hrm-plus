import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// How numbers are written. Western digits are the default (DESIGN_REFERENCE);
/// Arabic-Indic is a user choice.
enum DigitStyle { western, arabicIndic }

/// Non-secret user preferences (language, theme, digits).
/// The token is NOT stored here — see TokenStorage.
class AppPreferences {
  AppPreferences(this._prefs);

  final SharedPreferences _prefs;

  static const _localeKey = 'ui.locale';
  static const _themeKey = 'ui.theme';
  static const _digitsKey = 'ui.digits';
  static const _rationaleKey = 'attendance.location_rationale_seen';

  /// Arabic is the default language.
  Locale get locale => Locale(_prefs.getString(_localeKey) ?? 'ar');
  Future<void> setLocale(Locale locale) =>
      _prefs.setString(_localeKey, locale.languageCode);

  ThemeMode get themeMode => switch (_prefs.getString(_themeKey)) {
    'light' => ThemeMode.light,
    'dark' => ThemeMode.dark,
    _ => ThemeMode.system,
  };
  Future<void> setThemeMode(ThemeMode mode) =>
      _prefs.setString(_themeKey, mode.name);

  DigitStyle get digits => _prefs.getString(_digitsKey) == 'arabic_indic'
      ? DigitStyle.arabicIndic
      : DigitStyle.western;
  Future<void> setDigits(DigitStyle style) => _prefs.setString(
    _digitsKey,
    style == DigitStyle.arabicIndic ? 'arabic_indic' : 'western',
  );

  bool get locationRationaleSeen => _prefs.getBool(_rationaleKey) ?? false;
  Future<void> setLocationRationaleSeen() =>
      _prefs.setBool(_rationaleKey, true);
}
