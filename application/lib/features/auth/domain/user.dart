import '../../../core/network/json.dart';

/// `UserResource` (auth/me, login.user).
class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.locale,
    this.employeeId,
  });

  factory User.fromJson(Json json) => User(
    id: json.integer('id'),
    name: json.str('name'),
    email: json.str('email'),
    locale: json.str('locale', 'ar'),
    employeeId: json.intOrNull('employee_id'),
  );

  final int id;
  final String name;
  final String email;
  final String locale;
  final int? employeeId;
}

/// Public `GET app/config` (company, localisation, feature flags, OTP rules).
class AppRemoteConfig {
  const AppRemoteConfig({
    required this.companyName,
    required this.useArabicDigits,
    required this.passwordResetOtp,
    required this.otpLength,
    required this.otpResendSeconds,
    required this.passwordMinLength,
    required this.minVersion,
    required this.currency,
  });

  factory AppRemoteConfig.fromJson(Json json, String languageCode) {
    final company = json.obj('company') ?? const {};
    final translations = company.obj('name_translations') ?? const {};
    final localisation = json.obj('localisation') ?? const {};
    final features = json.obj('features') ?? const {};
    final auth = json.obj('auth') ?? const {};
    final app = json.obj('app') ?? const {};
    return AppRemoteConfig(
      companyName: translations.strOrNull(languageCode) ?? company.str('name'),
      useArabicDigits: localisation.boolean('use_arabic_digits'),
      passwordResetOtp: features.boolean('password_reset_otp', true),
      otpLength: auth.integer('otp_length', 6),
      otpResendSeconds: auth.integer('otp_resend_seconds', 60),
      passwordMinLength: auth.integer('password_min_length', 8),
      minVersion: app.strOrNull('min_version'),
      currency: localisation.strOrNull('currency_code'),
    );
  }

  static const fallback = AppRemoteConfig(
    companyName: '',
    useArabicDigits: false,
    passwordResetOtp: true,
    otpLength: 6,
    otpResendSeconds: 60,
    passwordMinLength: 8,
    minVersion: null,
    currency: null,
  );

  final String companyName;
  final bool useArabicDigits;
  final bool passwordResetOtp;
  final int otpLength;
  final int otpResendSeconds;
  final int passwordMinLength;
  final String? minVersion;
  final String? currency;

  /// True when [current] is below the server's minimum version.
  bool updateRequired(String current) {
    final min = minVersion;
    if (min == null) return false;
    List<int> parts(String v) => [
      for (final p in v.split('+').first.split('.')) int.tryParse(p) ?? 0,
    ];
    final a = parts(current);
    final b = parts(min);
    for (var i = 0; i < 3; i++) {
      final x = i < a.length ? a[i] : 0;
      final y = i < b.length ? b[i] : 0;
      if (x != y) return x < y;
    }
    return false;
  }
}
