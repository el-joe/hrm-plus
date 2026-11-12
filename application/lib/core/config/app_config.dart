/// Build-time configuration, supplied with `--dart-define`.
///
///   flutter run --dart-define=API_BASE_URL=http://10.0.2.2/api/v1
///
/// Nothing secret lives here: the API base URL and the version string are
/// public, the bearer token is kept in secure storage at runtime.
class AppConfig {
  const AppConfig({
    required this.apiBaseUrl,
    required this.appVersion,
    this.connectTimeout = const Duration(seconds: 15),
    this.receiveTimeout = const Duration(seconds: 30),
    this.sendTimeout = const Duration(seconds: 60),
  });

  factory AppConfig.fromEnvironment() => const AppConfig(
    apiBaseUrl: String.fromEnvironment(
      'API_BASE_URL',
      defaultValue: 'http://hrm-plus.loc/api/v1',
    ),
    appVersion: String.fromEnvironment('APP_VERSION', defaultValue: '1.0.0'),
  );

  /// Base of the versioned REST API, without a trailing slash.
  final String apiBaseUrl;

  /// Reported to the server on attendance attempts and device registration.
  final String appVersion;

  final Duration connectTimeout;
  final Duration receiveTimeout;
  final Duration sendTimeout;

  /// Android notification channel; must match the backend setting
  /// `push.android_channel_id` (default `hrm_plus_default`).
  static const String androidChannelId = String.fromEnvironment(
    'ANDROID_CHANNEL_ID',
    defaultValue: 'hrm_plus_default',
  );
}
