# HRM Plus — employee app (Flutter)

The employee mobile application of HRM Plus. It talks only to the Laravel REST API under
`/api/v1` (Sanctum bearer tokens, employee accounts only). Arabic (RTL) is the default language,
English (LTR) is available; light, dark and system themes; Western digits by default with an
Arabic-Indic option.

- Flutter 3.47 / Dart 3.13, Material 3, Cairo font (bundled, OFL — `assets/fonts`).
- Bundle id / applicationId: **`com.hrmplus.employee`** (placeholder until the client supplies the real
  one — PROJECT_PLAN blocker B8).

## Run

```bash
flutter pub get
flutter run --dart-define=API_BASE_URL=http://hrm-plus.loc/api/v1
# Android emulator → host machine:
flutter run --dart-define=API_BASE_URL=http://10.0.2.2/api/v1
```

| dart-define | Default | Purpose |
|---|---|---|
| `API_BASE_URL` | `http://hrm-plus.loc/api/v1` | API base URL (no trailing slash) |
| `APP_VERSION` | `1.0.0` | Sent with attendance attempts / device registration; compared with `app/config → app.min_version` |
| `ANDROID_CHANNEL_ID` | `hrm_plus_default` | Notification channel; must equal the backend setting `push.android_channel_id` |

Development accounts: `backend/docs/DEV_ACCOUNTS.md` (e.g. `employee1@hrmplus.test`). Release builds must use
HTTPS; plain HTTP is allowed only in the Android **debug** manifest.

## Structure

```
lib/
  main.dart, app.dart              bootstrap (prefs, optional Firebase) and MaterialApp.router
  core/
    config/      AppConfig (dart-defines)
    network/     ApiClient (dio: envelope parsing, timeouts), AuthInterceptor (bearer + refresh on 401),
                 ConnectivityChecker, JSON helpers, pagination
    errors/      typed Failures, DioException → Failure mapping, user-facing messages
    storage/     TokenStorage (flutter_secure_storage), AppPreferences (shared_preferences: language/theme/digits)
    l10n/        ARB files (ar, en) + generated AppLocalizations
    theme/       tokens from DESIGN_REFERENCE.md (palette, semantic tokens light/dark, Cairo scale, sizes)
    router/      go_router (auth guard, bottom-nav shell), push deep links
    files/       save-and-open for PDFs/attachments (io / web stub), UploadFile
    widgets/     AsyncView (skeleton / empty / error + retry, pull-to-refresh), paged lists, cards, badges…
    utils/       Money (exact decimal from API strings), Formatter (dates, digits, money)
  features/<feature>/{data, domain, application, presentation}
    auth, home, attendance, leave, payslips, loans, requests, documents, announcements,
    notifications (+ push), profile, settings
```

State management: Riverpod 3 (providers per repository, `Notifier`/`AsyncNotifier` for flows and lists).
Money is never a `double`: the API sends decimal strings and the app keeps them as strings or `Money`.

## Screens (25)

Splash · Login · Forgot password · OTP (+ new password) · Home · Profile · Attendance · Check-in · Check-out ·
GPS validation · Face verification (consent, enrolment, capture) · Attendance history (days, mobile attempts,
corrections) · Work schedule · Leave balance · New leave (server preview) · Leave history (+ request details) ·
Payslips · Salary breakdown (+ PDF) · Loans and advances · HR requests · Request details (thread, attachments) ·
Documents · Announcements (+ acknowledge) · Notifications · Settings and language (+ theme, digits,
notification preferences, sign out).

## Check-in / check-out flow

`features/attendance/application/check_flow_controller.dart`

1. Offline? → blocked ("nothing has been recorded"). Nothing is queued and nothing is shown as approved.
2. `GET me/attendance/today` (rules, allowed locations, face policy).
3. Location services off → open location settings. Permission not granted → explanation screen first, then
   the OS prompt; denied → retry; permanently denied → open app settings.
4. GPS fix (20 s limit): timeout / failure → retry. Accuracy above `rules.max_accuracy_meters` → shows the
   accuracy and the limit. Outside every geofence → shows distance, nearest location and radius.
   The mock-location flag (Android `isMocked`) is sent to the server.
5. Face (policy `optional` / `required`): camera permission (denied / permanently denied), camera errors,
   no camera; optional mode may continue without the face. Required but not enrolled → enrol first.
6. `POST me/attendance/check-in|check-out` with a `request_uuid`:
   **201** → success (the only success state) · **202** → "sent for review, not recorded yet" ·
   **422 `attendance_rejected`** → reasons localised by code, distance, remaining face attempts.
   Timeouts / 5xx / connection loss → retry re-sends the **same** `request_uuid` (idempotent on the server);
   a new attempt after a server rejection uses a new uuid.

## Push notifications

Code: `features/notifications/push/` — `PushService` interface, `FirebasePushService`
(firebase_messaging + flutter_local_notifications for foreground display), `NoopPushService`, and
`PushCoordinator` which registers the FCM token with `POST me/devices` after sign-in and on token refresh,
calls `DELETE me/devices` before sign-out (the server also drops devices of a revoked token), and turns taps
into routes (`data.announcement_id`, `leave_request_id`, `hr_request_id`, `payslip_id`, `loan_id`, otherwise
the portal `url`, otherwise the notification centre).

**Without Firebase configuration the app runs normally**: `Firebase.initializeApp()` fails, a log line says
push is disabled, and Settings shows that push is not configured. Delivery is untested until a Firebase project
exists (PROJECT_PLAN blocker B5).

The app uses the classic FCM registration token (`getToken`, deprecated in firebase_messaging 17 in favour of
FID direct-send) because the backend sends with FCM HTTP v1 to registration tokens.

### Android setup

1. Create the Firebase project, add an Android app with package `com.hrmplus.employee`, download
   `google-services.json` to `android/app/`. The Google Services Gradle plugin is applied automatically
   when that file exists (`android/app/build.gradle.kts`).
2. Upload the service-account key in the admin: *Settings → Push* (backend side).
3. Notification channel id `hrm_plus_default` (manifest meta-data
   `com.google.firebase.messaging.default_notification_channel_id` + created at start-up); keep it equal to
   the backend setting `push.android_channel_id`.
4. Permissions in `android/app/src/main/AndroidManifest.xml`: `INTERNET`, `ACCESS_NETWORK_STATE`,
   `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`, `CAMERA`, `POST_NOTIFICATIONS` (asked at runtime on
   Android 13+). Notification taps use `click_action = FLUTTER_NOTIFICATION_CLICK` (intent filter present).
5. Core library desugaring is enabled (required by flutter_local_notifications).
6. Android builds need the SDK licences accepted (`flutter doctor --android-licenses`, blocker B9).

### iOS setup

1. Add an iOS app `com.hrmplus.employee` in Firebase, put `GoogleService-Info.plist` in `ios/Runner/`
   (add it to the Runner target in Xcode).
2. Upload an APNs authentication key (.p8) to Firebase (*Project settings → Cloud Messaging*).
3. Xcode → Runner → *Signing & Capabilities*: add **Push Notifications** and **Background Modes → Remote
   notifications** (`UIBackgroundModes = remote-notification` is already in Info.plist).
4. Usage strings already in `ios/Runner/Info.plist`: `NSLocationWhenInUseUsageDescription`,
   `NSCameraUsageDescription` (bilingual text; move them to `ar.lproj/en.lproj InfoPlist.strings` if per-language
   strings are wanted). `CFBundleLocalizations` = ar, en.
5. `pod install` in `ios/` after `flutter pub get`. If the API is plain HTTP in development, add an ATS exception
   for the dev host only — never in release.

## Face verification

The backend ships a **simulated** provider (blocker B4). The app shows a "simulated" notice whenever the API
reports `is_simulated: true`. One front-camera JPEG (medium resolution) is sent as `face.images[0]` (base64);
no liveness SDK is integrated yet — when a real provider is chosen, its SDK token goes in `face.token`.

## Tests

Written, **not run in this phase** (project decision). Unit, widget and repository tests with a fake dio
adapter (`test/helpers`):

```bash
flutter test                       # everything except live tests
flutter test test/live --dart-define=LIVE_API=true \
  --dart-define=API_BASE_URL=http://hrm-plus.loc/api/v1   # scenarios 1 and 2 against a real backend
```

`test/live` records a real attendance attempt for today — development data only.

## Quality gates

```bash
dart format lib test
flutter analyze          # must be clean
flutter build web        # compile check on Linux (Android build blocked by B9)
```
