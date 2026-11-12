import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_en.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'generated/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('en'),
  ];

  /// No description provided for @appName.
  ///
  /// In en, this message translates to:
  /// **'HRM Plus'**
  String get appName;

  /// No description provided for @appTagline.
  ///
  /// In en, this message translates to:
  /// **'Your workday, in one place'**
  String get appTagline;

  /// No description provided for @loading.
  ///
  /// In en, this message translates to:
  /// **'Loading…'**
  String get loading;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get retry;

  /// No description provided for @refresh.
  ///
  /// In en, this message translates to:
  /// **'Refresh'**
  String get refresh;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @done.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get done;

  /// No description provided for @save.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get save;

  /// No description provided for @send.
  ///
  /// In en, this message translates to:
  /// **'Send'**
  String get send;

  /// No description provided for @next.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get next;

  /// No description provided for @previous.
  ///
  /// In en, this message translates to:
  /// **'Previous'**
  String get previous;

  /// No description provided for @continueLabel.
  ///
  /// In en, this message translates to:
  /// **'Continue'**
  String get continueLabel;

  /// No description provided for @tryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get tryAgain;

  /// No description provided for @emptyGeneric.
  ///
  /// In en, this message translates to:
  /// **'Nothing here yet'**
  String get emptyGeneric;

  /// No description provided for @selectDate.
  ///
  /// In en, this message translates to:
  /// **'Select a date'**
  String get selectDate;

  /// No description provided for @switchLanguage.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get switchLanguage;

  /// No description provided for @updateRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'Update required'**
  String get updateRequiredTitle;

  /// No description provided for @updateRequiredMessage.
  ///
  /// In en, this message translates to:
  /// **'This version is no longer supported. Please install the latest version of HRM Plus.'**
  String get updateRequiredMessage;

  /// No description provided for @errorTitle.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong'**
  String get errorTitle;

  /// No description provided for @errorOfflineTitle.
  ///
  /// In en, this message translates to:
  /// **'You are offline'**
  String get errorOfflineTitle;

  /// No description provided for @errorOffline.
  ///
  /// In en, this message translates to:
  /// **'No internet connection. Check your connection and try again.'**
  String get errorOffline;

  /// No description provided for @errorTimeout.
  ///
  /// In en, this message translates to:
  /// **'The server took too long to respond. Try again.'**
  String get errorTimeout;

  /// No description provided for @errorSessionExpired.
  ///
  /// In en, this message translates to:
  /// **'Your session has ended. Please sign in again.'**
  String get errorSessionExpired;

  /// No description provided for @errorForbidden.
  ///
  /// In en, this message translates to:
  /// **'You do not have access to this.'**
  String get errorForbidden;

  /// No description provided for @errorNotFound.
  ///
  /// In en, this message translates to:
  /// **'This item was not found.'**
  String get errorNotFound;

  /// No description provided for @errorValidation.
  ///
  /// In en, this message translates to:
  /// **'Please check the highlighted fields.'**
  String get errorValidation;

  /// No description provided for @errorRateLimited.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please wait a moment and try again.'**
  String get errorRateLimited;

  /// No description provided for @errorServer.
  ///
  /// In en, this message translates to:
  /// **'The server could not complete the request. Try again later.'**
  String get errorServer;

  /// No description provided for @errorUnexpected.
  ///
  /// In en, this message translates to:
  /// **'An unexpected error occurred.'**
  String get errorUnexpected;

  /// No description provided for @cannotOpenFile.
  ///
  /// In en, this message translates to:
  /// **'The file could not be opened on this device.'**
  String get cannotOpenFile;

  /// No description provided for @downloading.
  ///
  /// In en, this message translates to:
  /// **'Downloading…'**
  String get downloading;

  /// No description provided for @validationRequired.
  ///
  /// In en, this message translates to:
  /// **'This field is required.'**
  String get validationRequired;

  /// No description provided for @validationEmail.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid e-mail address.'**
  String get validationEmail;

  /// No description provided for @validationPasswordMatch.
  ///
  /// In en, this message translates to:
  /// **'The passwords do not match.'**
  String get validationPasswordMatch;

  /// No description provided for @validationStartDate.
  ///
  /// In en, this message translates to:
  /// **'Choose the start date.'**
  String get validationStartDate;

  /// No description provided for @validationAmount.
  ///
  /// In en, this message translates to:
  /// **'Enter an amount greater than zero.'**
  String get validationAmount;

  /// No description provided for @validationMaxAmount.
  ///
  /// In en, this message translates to:
  /// **'The amount is above your limit.'**
  String get validationMaxAmount;

  /// No description provided for @loginTitle.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get loginTitle;

  /// No description provided for @loginSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Use your work e-mail and password.'**
  String get loginSubtitle;

  /// No description provided for @loginFailed.
  ///
  /// In en, this message translates to:
  /// **'Sign-in failed.'**
  String get loginFailed;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'E-mail'**
  String get email;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @showPassword.
  ///
  /// In en, this message translates to:
  /// **'Show password'**
  String get showPassword;

  /// No description provided for @hidePassword.
  ///
  /// In en, this message translates to:
  /// **'Hide password'**
  String get hidePassword;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPassword;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// No description provided for @forgotPasswordTitle.
  ///
  /// In en, this message translates to:
  /// **'Forgot password'**
  String get forgotPasswordTitle;

  /// No description provided for @forgotPasswordSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your work e-mail and we will send you a verification code.'**
  String get forgotPasswordSubtitle;

  /// No description provided for @sendCode.
  ///
  /// In en, this message translates to:
  /// **'Send code'**
  String get sendCode;

  /// No description provided for @backToLogin.
  ///
  /// In en, this message translates to:
  /// **'Back to sign in'**
  String get backToLogin;

  /// No description provided for @otpTitle.
  ///
  /// In en, this message translates to:
  /// **'Verification code'**
  String get otpTitle;

  /// No description provided for @otpCode.
  ///
  /// In en, this message translates to:
  /// **'Code'**
  String get otpCode;

  /// No description provided for @verify.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verify;

  /// No description provided for @otpResend.
  ///
  /// In en, this message translates to:
  /// **'Resend code'**
  String get otpResend;

  /// No description provided for @otpResent.
  ///
  /// In en, this message translates to:
  /// **'A new code has been sent.'**
  String get otpResent;

  /// No description provided for @newPasswordTitle.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPasswordTitle;

  /// No description provided for @newPasswordSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Choose a new password for your account.'**
  String get newPasswordSubtitle;

  /// No description provided for @newPassword.
  ///
  /// In en, this message translates to:
  /// **'New password'**
  String get newPassword;

  /// No description provided for @confirmPassword.
  ///
  /// In en, this message translates to:
  /// **'Confirm password'**
  String get confirmPassword;

  /// No description provided for @resetPassword.
  ///
  /// In en, this message translates to:
  /// **'Reset password'**
  String get resetPassword;

  /// No description provided for @passwordResetDone.
  ///
  /// In en, this message translates to:
  /// **'Your password has been changed. Sign in with the new password.'**
  String get passwordResetDone;

  /// No description provided for @logout.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get logout;

  /// No description provided for @logoutConfirm.
  ///
  /// In en, this message translates to:
  /// **'Sign out of HRM Plus on this device?'**
  String get logoutConfirm;

  /// No description provided for @navHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// No description provided for @navAttendance.
  ///
  /// In en, this message translates to:
  /// **'Attendance'**
  String get navAttendance;

  /// No description provided for @navLeave.
  ///
  /// In en, this message translates to:
  /// **'Leave'**
  String get navLeave;

  /// No description provided for @navPayslips.
  ///
  /// In en, this message translates to:
  /// **'Payslips'**
  String get navPayslips;

  /// No description provided for @navAccount.
  ///
  /// In en, this message translates to:
  /// **'My account'**
  String get navAccount;

  /// No description provided for @goodMorning.
  ///
  /// In en, this message translates to:
  /// **'Good morning'**
  String get goodMorning;

  /// No description provided for @goodEvening.
  ///
  /// In en, this message translates to:
  /// **'Good evening'**
  String get goodEvening;

  /// No description provided for @restDay.
  ///
  /// In en, this message translates to:
  /// **'Rest day'**
  String get restDay;

  /// No description provided for @noShiftToday.
  ///
  /// In en, this message translates to:
  /// **'No shift today'**
  String get noShiftToday;

  /// No description provided for @attendanceStatusUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Attendance status could not be loaded.'**
  String get attendanceStatusUnavailable;

  /// No description provided for @checkInTime.
  ///
  /// In en, this message translates to:
  /// **'Check-in'**
  String get checkInTime;

  /// No description provided for @checkOutTime.
  ///
  /// In en, this message translates to:
  /// **'Check-out'**
  String get checkOutTime;

  /// No description provided for @workedHours.
  ///
  /// In en, this message translates to:
  /// **'Worked hours'**
  String get workedHours;

  /// No description provided for @leaveBalance.
  ///
  /// In en, this message translates to:
  /// **'Leave balance'**
  String get leaveBalance;

  /// No description provided for @lastNetSalary.
  ///
  /// In en, this message translates to:
  /// **'Last net salary'**
  String get lastNetSalary;

  /// No description provided for @pendingItems.
  ///
  /// In en, this message translates to:
  /// **'Waiting for you'**
  String get pendingItems;

  /// No description provided for @pendingLeaveRequests.
  ///
  /// In en, this message translates to:
  /// **'Leave requests pending'**
  String get pendingLeaveRequests;

  /// No description provided for @pendingHrRequests.
  ///
  /// In en, this message translates to:
  /// **'HR requests open'**
  String get pendingHrRequests;

  /// No description provided for @pendingLoanRequests.
  ///
  /// In en, this message translates to:
  /// **'Loan requests pending'**
  String get pendingLoanRequests;

  /// No description provided for @pendingCorrections.
  ///
  /// In en, this message translates to:
  /// **'Attendance corrections pending'**
  String get pendingCorrections;

  /// No description provided for @pendingAcknowledgements.
  ///
  /// In en, this message translates to:
  /// **'Announcements to acknowledge'**
  String get pendingAcknowledgements;

  /// No description provided for @attendanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Attendance'**
  String get attendanceTitle;

  /// No description provided for @checkIn.
  ///
  /// In en, this message translates to:
  /// **'Check in'**
  String get checkIn;

  /// No description provided for @checkOut.
  ///
  /// In en, this message translates to:
  /// **'Check out'**
  String get checkOut;

  /// No description provided for @mobileAttendanceDisabled.
  ///
  /// In en, this message translates to:
  /// **'Mobile attendance is off'**
  String get mobileAttendanceDisabled;

  /// No description provided for @stateCheckedIn.
  ///
  /// In en, this message translates to:
  /// **'Checked in'**
  String get stateCheckedIn;

  /// No description provided for @stateCheckedOut.
  ///
  /// In en, this message translates to:
  /// **'Checked out'**
  String get stateCheckedOut;

  /// No description provided for @stateNotCheckedIn.
  ///
  /// In en, this message translates to:
  /// **'Not checked in'**
  String get stateNotCheckedIn;

  /// No description provided for @assignedShift.
  ///
  /// In en, this message translates to:
  /// **'Assigned shift'**
  String get assignedShift;

  /// No description provided for @shift.
  ///
  /// In en, this message translates to:
  /// **'Shift'**
  String get shift;

  /// No description provided for @holiday.
  ///
  /// In en, this message translates to:
  /// **'Holiday'**
  String get holiday;

  /// No description provided for @allowedLocations.
  ///
  /// In en, this message translates to:
  /// **'Allowed locations'**
  String get allowedLocations;

  /// No description provided for @locationAnywhere.
  ///
  /// In en, this message translates to:
  /// **'Any location'**
  String get locationAnywhere;

  /// No description provided for @outsideShiftWindowHint.
  ///
  /// In en, this message translates to:
  /// **'You are outside the check-in window of your shift. An attempt now may be sent for review.'**
  String get outsideShiftWindowHint;

  /// No description provided for @todayPunches.
  ///
  /// In en, this message translates to:
  /// **'Today\'s punches'**
  String get todayPunches;

  /// No description provided for @noPunchesYet.
  ///
  /// In en, this message translates to:
  /// **'No punches recorded today.'**
  String get noPunchesYet;

  /// No description provided for @attendanceHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Attendance history'**
  String get attendanceHistoryTitle;

  /// No description provided for @scheduleTitle.
  ///
  /// In en, this message translates to:
  /// **'Work schedule'**
  String get scheduleTitle;

  /// No description provided for @gpsTitle.
  ///
  /// In en, this message translates to:
  /// **'GPS location'**
  String get gpsTitle;

  /// No description provided for @faceTitle.
  ///
  /// In en, this message translates to:
  /// **'Face verification'**
  String get faceTitle;

  /// No description provided for @faceVerification.
  ///
  /// In en, this message translates to:
  /// **'Face verification'**
  String get faceVerification;

  /// No description provided for @noLocationConfigured.
  ///
  /// In en, this message translates to:
  /// **'No allowed location is set for you'**
  String get noLocationConfigured;

  /// No description provided for @gpsChecking.
  ///
  /// In en, this message translates to:
  /// **'Checking GPS…'**
  String get gpsChecking;

  /// No description provided for @gpsOff.
  ///
  /// In en, this message translates to:
  /// **'Location services off'**
  String get gpsOff;

  /// No description provided for @gpsPermissionNeeded.
  ///
  /// In en, this message translates to:
  /// **'Location permission needed'**
  String get gpsPermissionNeeded;

  /// No description provided for @gpsUnavailable.
  ///
  /// In en, this message translates to:
  /// **'GPS unavailable'**
  String get gpsUnavailable;

  /// No description provided for @gpsReady.
  ///
  /// In en, this message translates to:
  /// **'GPS ready'**
  String get gpsReady;

  /// No description provided for @faceOff.
  ///
  /// In en, this message translates to:
  /// **'Face check not used'**
  String get faceOff;

  /// No description provided for @faceRequiredReady.
  ///
  /// In en, this message translates to:
  /// **'Face check required · enrolled'**
  String get faceRequiredReady;

  /// No description provided for @faceOptionalReady.
  ///
  /// In en, this message translates to:
  /// **'Face check · enrolled'**
  String get faceOptionalReady;

  /// No description provided for @faceNotEnrolled.
  ///
  /// In en, this message translates to:
  /// **'Face not enrolled'**
  String get faceNotEnrolled;

  /// No description provided for @stepLocation.
  ///
  /// In en, this message translates to:
  /// **'Location'**
  String get stepLocation;

  /// No description provided for @stepFace.
  ///
  /// In en, this message translates to:
  /// **'Face'**
  String get stepFace;

  /// No description provided for @stepSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit'**
  String get stepSubmit;

  /// No description provided for @preparing.
  ///
  /// In en, this message translates to:
  /// **'Preparing…'**
  String get preparing;

  /// No description provided for @locating.
  ///
  /// In en, this message translates to:
  /// **'Getting your location…'**
  String get locating;

  /// No description provided for @submitting.
  ///
  /// In en, this message translates to:
  /// **'Sending to the server… Do not close the app.'**
  String get submitting;

  /// No description provided for @locationRationaleTitle.
  ///
  /// In en, this message translates to:
  /// **'Location access'**
  String get locationRationaleTitle;

  /// No description provided for @locationRationaleBody.
  ///
  /// In en, this message translates to:
  /// **'HRM Plus checks your location only when you check in or out, to confirm you are at an allowed work location. Your location is not tracked in the background.'**
  String get locationRationaleBody;

  /// No description provided for @faceStepTitle.
  ///
  /// In en, this message translates to:
  /// **'Verify your face'**
  String get faceStepTitle;

  /// No description provided for @cameraRationale.
  ///
  /// In en, this message translates to:
  /// **'The front camera takes one photo to confirm it is you. The photo is sent to the verification service and not kept on the phone.'**
  String get cameraRationale;

  /// No description provided for @faceSimulatedNotice.
  ///
  /// In en, this message translates to:
  /// **'Face verification runs in simulated mode (development). No real biometric check is performed.'**
  String get faceSimulatedNotice;

  /// No description provided for @openCamera.
  ///
  /// In en, this message translates to:
  /// **'Open camera'**
  String get openCamera;

  /// No description provided for @continueWithoutFace.
  ///
  /// In en, this message translates to:
  /// **'Continue without face verification'**
  String get continueWithoutFace;

  /// No description provided for @faceCaptureHint.
  ///
  /// In en, this message translates to:
  /// **'Place your face inside the frame, in good light, and tap Capture.'**
  String get faceCaptureHint;

  /// No description provided for @capture.
  ///
  /// In en, this message translates to:
  /// **'Capture'**
  String get capture;

  /// No description provided for @issueOfflineTitle.
  ///
  /// In en, this message translates to:
  /// **'No connection'**
  String get issueOfflineTitle;

  /// No description provided for @issueOfflineBody.
  ///
  /// In en, this message translates to:
  /// **'You are offline. Attendance can only be recorded when the server confirms it — connect to the internet and try again.'**
  String get issueOfflineBody;

  /// No description provided for @contactHr.
  ///
  /// In en, this message translates to:
  /// **'Please contact HR.'**
  String get contactHr;

  /// No description provided for @issueLocationOffTitle.
  ///
  /// In en, this message translates to:
  /// **'Location services are off'**
  String get issueLocationOffTitle;

  /// No description provided for @issueLocationOffBody.
  ///
  /// In en, this message translates to:
  /// **'Turn on location services to check in or out.'**
  String get issueLocationOffBody;

  /// No description provided for @issueLocationDeniedTitle.
  ///
  /// In en, this message translates to:
  /// **'Location permission denied'**
  String get issueLocationDeniedTitle;

  /// No description provided for @issueLocationDeniedBody.
  ///
  /// In en, this message translates to:
  /// **'HRM Plus needs your location to confirm you are at an allowed work location.'**
  String get issueLocationDeniedBody;

  /// No description provided for @issueLocationBlockedBody.
  ///
  /// In en, this message translates to:
  /// **'Location access is blocked. Allow it for HRM Plus in the system settings.'**
  String get issueLocationBlockedBody;

  /// No description provided for @issueGpsTimeoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Could not get a GPS fix'**
  String get issueGpsTimeoutTitle;

  /// No description provided for @issueGpsTimeoutBody.
  ///
  /// In en, this message translates to:
  /// **'Move to an open area or near a window and try again.'**
  String get issueGpsTimeoutBody;

  /// No description provided for @issueGpsFailedTitle.
  ///
  /// In en, this message translates to:
  /// **'Location unavailable'**
  String get issueGpsFailedTitle;

  /// No description provided for @issueGpsFailedBody.
  ///
  /// In en, this message translates to:
  /// **'Your location could not be read. Try again.'**
  String get issueGpsFailedBody;

  /// No description provided for @issuePoorAccuracyTitle.
  ///
  /// In en, this message translates to:
  /// **'GPS accuracy is too low'**
  String get issuePoorAccuracyTitle;

  /// No description provided for @issueOutsideTitle.
  ///
  /// In en, this message translates to:
  /// **'Outside the allowed location'**
  String get issueOutsideTitle;

  /// No description provided for @issueCameraDeniedTitle.
  ///
  /// In en, this message translates to:
  /// **'Camera permission denied'**
  String get issueCameraDeniedTitle;

  /// No description provided for @issueCameraDeniedBody.
  ///
  /// In en, this message translates to:
  /// **'Face verification needs the camera. Allow camera access to continue.'**
  String get issueCameraDeniedBody;

  /// No description provided for @issueCameraBlockedBody.
  ///
  /// In en, this message translates to:
  /// **'Camera access is blocked. Allow it for HRM Plus in the system settings.'**
  String get issueCameraBlockedBody;

  /// No description provided for @issueCameraErrorTitle.
  ///
  /// In en, this message translates to:
  /// **'Camera error'**
  String get issueCameraErrorTitle;

  /// No description provided for @issueCameraErrorBody.
  ///
  /// In en, this message translates to:
  /// **'The camera could not be started. Close other camera apps and try again.'**
  String get issueCameraErrorBody;

  /// No description provided for @issueNoCameraBody.
  ///
  /// In en, this message translates to:
  /// **'No camera was found on this device.'**
  String get issueNoCameraBody;

  /// No description provided for @issueFaceNotEnrolledBody.
  ///
  /// In en, this message translates to:
  /// **'Your policy requires face verification. Enrol your face first.'**
  String get issueFaceNotEnrolledBody;

  /// No description provided for @issueSubmitFailedTitle.
  ///
  /// In en, this message translates to:
  /// **'Not sent'**
  String get issueSubmitFailedTitle;

  /// No description provided for @retrySafeNote.
  ///
  /// In en, this message translates to:
  /// **'Retrying is safe: the same request is not recorded twice.'**
  String get retrySafeNote;

  /// No description provided for @notRecordedNote.
  ///
  /// In en, this message translates to:
  /// **'Nothing has been recorded.'**
  String get notRecordedNote;

  /// No description provided for @openLocationSettings.
  ///
  /// In en, this message translates to:
  /// **'Open location settings'**
  String get openLocationSettings;

  /// No description provided for @openAppSettings.
  ///
  /// In en, this message translates to:
  /// **'Open app settings'**
  String get openAppSettings;

  /// No description provided for @enrolFace.
  ///
  /// In en, this message translates to:
  /// **'Enrol my face'**
  String get enrolFace;

  /// No description provided for @checkInRecorded.
  ///
  /// In en, this message translates to:
  /// **'Check-in recorded'**
  String get checkInRecorded;

  /// No description provided for @checkOutRecorded.
  ///
  /// In en, this message translates to:
  /// **'Check-out recorded'**
  String get checkOutRecorded;

  /// No description provided for @sentForReviewTitle.
  ///
  /// In en, this message translates to:
  /// **'Sent for review'**
  String get sentForReviewTitle;

  /// No description provided for @sentForReviewBody.
  ///
  /// In en, this message translates to:
  /// **'Your attempt was received but is NOT recorded yet. HR will review it and you will be notified.'**
  String get sentForReviewBody;

  /// No description provided for @rejectedTitle.
  ///
  /// In en, this message translates to:
  /// **'Not recorded'**
  String get rejectedTitle;

  /// No description provided for @rejectedBody.
  ///
  /// In en, this message translates to:
  /// **'The server did not accept this attempt.'**
  String get rejectedBody;

  /// No description provided for @distance.
  ///
  /// In en, this message translates to:
  /// **'Distance'**
  String get distance;

  /// No description provided for @allowedRadius.
  ///
  /// In en, this message translates to:
  /// **'Allowed radius'**
  String get allowedRadius;

  /// No description provided for @faceAttemptsLeft.
  ///
  /// In en, this message translates to:
  /// **'Face attempts left'**
  String get faceAttemptsLeft;

  /// No description provided for @accuracy.
  ///
  /// In en, this message translates to:
  /// **'Accuracy'**
  String get accuracy;

  /// No description provided for @maxAccuracy.
  ///
  /// In en, this message translates to:
  /// **'Required accuracy'**
  String get maxAccuracy;

  /// No description provided for @nearestLocation.
  ///
  /// In en, this message translates to:
  /// **'Nearest location'**
  String get nearestLocation;

  /// No description provided for @mockLocationDetected.
  ///
  /// In en, this message translates to:
  /// **'A mock location was detected. The attempt may be sent for review.'**
  String get mockLocationDetected;

  /// No description provided for @reasonNotEmployed.
  ///
  /// In en, this message translates to:
  /// **'You are not employed on this date'**
  String get reasonNotEmployed;

  /// No description provided for @reasonMobileDisabled.
  ///
  /// In en, this message translates to:
  /// **'Mobile attendance is not enabled for you'**
  String get reasonMobileDisabled;

  /// No description provided for @reasonPoorAccuracy.
  ///
  /// In en, this message translates to:
  /// **'GPS accuracy was too low'**
  String get reasonPoorAccuracy;

  /// No description provided for @reasonNoLocation.
  ///
  /// In en, this message translates to:
  /// **'No allowed location is configured for you'**
  String get reasonNoLocation;

  /// No description provided for @reasonOutsideGeofence.
  ///
  /// In en, this message translates to:
  /// **'You are outside the allowed location'**
  String get reasonOutsideGeofence;

  /// No description provided for @reasonFaceRequired.
  ///
  /// In en, this message translates to:
  /// **'Face verification is required'**
  String get reasonFaceRequired;

  /// No description provided for @reasonFaceRetry.
  ///
  /// In en, this message translates to:
  /// **'Face not recognised — try again'**
  String get reasonFaceRetry;

  /// No description provided for @reasonMockLocation.
  ///
  /// In en, this message translates to:
  /// **'A mock location was detected'**
  String get reasonMockLocation;

  /// No description provided for @reasonOutsideShiftWindow.
  ///
  /// In en, this message translates to:
  /// **'Outside your shift\'s check-in window'**
  String get reasonOutsideShiftWindow;

  /// No description provided for @reasonFaceFailed.
  ///
  /// In en, this message translates to:
  /// **'Face verification failed'**
  String get reasonFaceFailed;

  /// No description provided for @reasonFaceNotEnrolled.
  ///
  /// In en, this message translates to:
  /// **'Your face is not enrolled'**
  String get reasonFaceNotEnrolled;

  /// No description provided for @reasonTimeDrift.
  ///
  /// In en, this message translates to:
  /// **'Your phone clock differs from the server time'**
  String get reasonTimeDrift;

  /// No description provided for @reasonRepeatedAttempts.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts in a short time'**
  String get reasonRepeatedAttempts;

  /// No description provided for @gpsExplanation.
  ///
  /// In en, this message translates to:
  /// **'This screen shows what the server will check: your GPS accuracy and the distance to your allowed work locations. The server validates again when you check in.'**
  String get gpsExplanation;

  /// No description provided for @gpsStatus.
  ///
  /// In en, this message translates to:
  /// **'GPS status'**
  String get gpsStatus;

  /// No description provided for @coordinates.
  ///
  /// In en, this message translates to:
  /// **'Coordinates'**
  String get coordinates;

  /// No description provided for @poorAccuracyHint.
  ///
  /// In en, this message translates to:
  /// **'Accuracy is above the limit. Move to an open area and refresh.'**
  String get poorAccuracyHint;

  /// No description provided for @allowLocation.
  ///
  /// In en, this message translates to:
  /// **'Allow location'**
  String get allowLocation;

  /// No description provided for @inside.
  ///
  /// In en, this message translates to:
  /// **'Inside'**
  String get inside;

  /// No description provided for @faceStatus.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get faceStatus;

  /// No description provided for @facePolicy.
  ///
  /// In en, this message translates to:
  /// **'Your policy'**
  String get facePolicy;

  /// No description provided for @faceModeRequired.
  ///
  /// In en, this message translates to:
  /// **'Required'**
  String get faceModeRequired;

  /// No description provided for @faceModeOptional.
  ///
  /// In en, this message translates to:
  /// **'Optional'**
  String get faceModeOptional;

  /// No description provided for @faceModeOff.
  ///
  /// In en, this message translates to:
  /// **'Not used'**
  String get faceModeOff;

  /// No description provided for @faceConsent.
  ///
  /// In en, this message translates to:
  /// **'Consent'**
  String get faceConsent;

  /// No description provided for @faceEnrolment.
  ///
  /// In en, this message translates to:
  /// **'Enrolment'**
  String get faceEnrolment;

  /// No description provided for @given.
  ///
  /// In en, this message translates to:
  /// **'Given'**
  String get given;

  /// No description provided for @notGiven.
  ///
  /// In en, this message translates to:
  /// **'Not given'**
  String get notGiven;

  /// No description provided for @notEnrolled.
  ///
  /// In en, this message translates to:
  /// **'Not enrolled'**
  String get notEnrolled;

  /// No description provided for @expires.
  ///
  /// In en, this message translates to:
  /// **'Expires'**
  String get expires;

  /// No description provided for @faceConsentTitle.
  ///
  /// In en, this message translates to:
  /// **'Consent to face verification'**
  String get faceConsentTitle;

  /// No description provided for @faceGiveConsent.
  ///
  /// In en, this message translates to:
  /// **'I agree'**
  String get faceGiveConsent;

  /// No description provided for @faceConsentSaved.
  ///
  /// In en, this message translates to:
  /// **'Consent recorded.'**
  String get faceConsentSaved;

  /// No description provided for @faceEnrolled.
  ///
  /// In en, this message translates to:
  /// **'Your face has been enrolled.'**
  String get faceEnrolled;

  /// No description provided for @faceReEnrol.
  ///
  /// In en, this message translates to:
  /// **'Enrol again'**
  String get faceReEnrol;

  /// No description provided for @faceWithdraw.
  ///
  /// In en, this message translates to:
  /// **'Withdraw consent'**
  String get faceWithdraw;

  /// No description provided for @faceWithdrawConfirm.
  ///
  /// In en, this message translates to:
  /// **'Withdrawing consent deletes your biometric data immediately. If your policy requires face verification you will not be able to check in from the app.'**
  String get faceWithdrawConfirm;

  /// No description provided for @faceWithdrawn.
  ///
  /// In en, this message translates to:
  /// **'Consent withdrawn and data deleted.'**
  String get faceWithdrawn;

  /// No description provided for @tabDays.
  ///
  /// In en, this message translates to:
  /// **'Days'**
  String get tabDays;

  /// No description provided for @tabAttempts.
  ///
  /// In en, this message translates to:
  /// **'Mobile attempts'**
  String get tabAttempts;

  /// No description provided for @tabCorrections.
  ///
  /// In en, this message translates to:
  /// **'Corrections'**
  String get tabCorrections;

  /// No description provided for @noAttendanceDays.
  ///
  /// In en, this message translates to:
  /// **'No attendance records for this month.'**
  String get noAttendanceDays;

  /// No description provided for @presentDays.
  ///
  /// In en, this message translates to:
  /// **'Present'**
  String get presentDays;

  /// No description provided for @lateDays.
  ///
  /// In en, this message translates to:
  /// **'Late'**
  String get lateDays;

  /// No description provided for @absentDays.
  ///
  /// In en, this message translates to:
  /// **'Absent'**
  String get absentDays;

  /// No description provided for @leaveDays.
  ///
  /// In en, this message translates to:
  /// **'Leave'**
  String get leaveDays;

  /// No description provided for @overtime.
  ///
  /// In en, this message translates to:
  /// **'Overtime'**
  String get overtime;

  /// No description provided for @noAttempts.
  ///
  /// In en, this message translates to:
  /// **'No mobile attempts yet.'**
  String get noAttempts;

  /// No description provided for @outcomeAccepted.
  ///
  /// In en, this message translates to:
  /// **'Recorded'**
  String get outcomeAccepted;

  /// No description provided for @outcomeFlagged.
  ///
  /// In en, this message translates to:
  /// **'In review'**
  String get outcomeFlagged;

  /// No description provided for @outcomeRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected'**
  String get outcomeRejected;

  /// No description provided for @outcomeReviewApproved.
  ///
  /// In en, this message translates to:
  /// **'Approved after review'**
  String get outcomeReviewApproved;

  /// No description provided for @outcomeReviewRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected after review'**
  String get outcomeReviewRejected;

  /// No description provided for @noCorrections.
  ///
  /// In en, this message translates to:
  /// **'No correction requests.'**
  String get noCorrections;

  /// No description provided for @noSchedule.
  ///
  /// In en, this message translates to:
  /// **'No schedule for these dates.'**
  String get noSchedule;

  /// No description provided for @noShift.
  ///
  /// In en, this message translates to:
  /// **'No shift'**
  String get noShift;

  /// No description provided for @workingDay.
  ///
  /// In en, this message translates to:
  /// **'Working day'**
  String get workingDay;

  /// No description provided for @leaveHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Leave history'**
  String get leaveHistoryTitle;

  /// No description provided for @newLeaveRequest.
  ///
  /// In en, this message translates to:
  /// **'New leave request'**
  String get newLeaveRequest;

  /// No description provided for @noLeaveTypes.
  ///
  /// In en, this message translates to:
  /// **'No leave types are available to you.'**
  String get noLeaveTypes;

  /// No description provided for @notTracked.
  ///
  /// In en, this message translates to:
  /// **'No balance'**
  String get notTracked;

  /// No description provided for @entitlement.
  ///
  /// In en, this message translates to:
  /// **'Entitled'**
  String get entitlement;

  /// No description provided for @carriedIn.
  ///
  /// In en, this message translates to:
  /// **'Carried over'**
  String get carriedIn;

  /// No description provided for @used.
  ///
  /// In en, this message translates to:
  /// **'Used'**
  String get used;

  /// No description provided for @pending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get pending;

  /// No description provided for @leaveType.
  ///
  /// In en, this message translates to:
  /// **'Leave type'**
  String get leaveType;

  /// No description provided for @dayPartFull.
  ///
  /// In en, this message translates to:
  /// **'Full day'**
  String get dayPartFull;

  /// No description provided for @dayPartFirstHalf.
  ///
  /// In en, this message translates to:
  /// **'First half'**
  String get dayPartFirstHalf;

  /// No description provided for @dayPartSecondHalf.
  ///
  /// In en, this message translates to:
  /// **'Second half'**
  String get dayPartSecondHalf;

  /// No description provided for @dayPartHours.
  ///
  /// In en, this message translates to:
  /// **'Hours'**
  String get dayPartHours;

  /// No description provided for @startDate.
  ///
  /// In en, this message translates to:
  /// **'From'**
  String get startDate;

  /// No description provided for @endDate.
  ///
  /// In en, this message translates to:
  /// **'To'**
  String get endDate;

  /// No description provided for @date.
  ///
  /// In en, this message translates to:
  /// **'Date'**
  String get date;

  /// No description provided for @hours.
  ///
  /// In en, this message translates to:
  /// **'Hours'**
  String get hours;

  /// No description provided for @reasonOptional.
  ///
  /// In en, this message translates to:
  /// **'Reason (optional)'**
  String get reasonOptional;

  /// No description provided for @attachments.
  ///
  /// In en, this message translates to:
  /// **'Attachments'**
  String get attachments;

  /// No description provided for @addAttachment.
  ///
  /// In en, this message translates to:
  /// **'Add file'**
  String get addAttachment;

  /// No description provided for @attachmentRequiredHint.
  ///
  /// In en, this message translates to:
  /// **'This leave type needs a supporting document.'**
  String get attachmentRequiredHint;

  /// No description provided for @attachmentRequired.
  ///
  /// In en, this message translates to:
  /// **'A supporting document is required.'**
  String get attachmentRequired;

  /// No description provided for @previewDays.
  ///
  /// In en, this message translates to:
  /// **'Calculate days'**
  String get previewDays;

  /// No description provided for @previewTitle.
  ///
  /// In en, this message translates to:
  /// **'Preview'**
  String get previewTitle;

  /// No description provided for @daysCounted.
  ///
  /// In en, this message translates to:
  /// **'Days counted'**
  String get daysCounted;

  /// No description provided for @daysNotCounted.
  ///
  /// In en, this message translates to:
  /// **'Days not counted'**
  String get daysNotCounted;

  /// No description provided for @submitRequest.
  ///
  /// In en, this message translates to:
  /// **'Submit request'**
  String get submitRequest;

  /// No description provided for @leaveSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Your leave request was sent for approval.'**
  String get leaveSubmitted;

  /// No description provided for @noLeaveRequests.
  ///
  /// In en, this message translates to:
  /// **'You have no leave requests.'**
  String get noLeaveRequests;

  /// No description provided for @leaveRequestTitle.
  ///
  /// In en, this message translates to:
  /// **'Leave request'**
  String get leaveRequestTitle;

  /// No description provided for @duration.
  ///
  /// In en, this message translates to:
  /// **'Duration'**
  String get duration;

  /// No description provided for @currentStep.
  ///
  /// In en, this message translates to:
  /// **'Current step'**
  String get currentStep;

  /// No description provided for @reason.
  ///
  /// In en, this message translates to:
  /// **'Reason'**
  String get reason;

  /// No description provided for @rejectionReason.
  ///
  /// In en, this message translates to:
  /// **'Rejection reason'**
  String get rejectionReason;

  /// No description provided for @submittedOn.
  ///
  /// In en, this message translates to:
  /// **'Submitted'**
  String get submittedOn;

  /// No description provided for @dayBreakdown.
  ///
  /// In en, this message translates to:
  /// **'Days'**
  String get dayBreakdown;

  /// No description provided for @notCounted.
  ///
  /// In en, this message translates to:
  /// **'Not counted'**
  String get notCounted;

  /// No description provided for @approvalTrail.
  ///
  /// In en, this message translates to:
  /// **'Approval trail'**
  String get approvalTrail;

  /// No description provided for @noAttachments.
  ///
  /// In en, this message translates to:
  /// **'No attachments.'**
  String get noAttachments;

  /// No description provided for @attachmentsUploaded.
  ///
  /// In en, this message translates to:
  /// **'Files uploaded.'**
  String get attachmentsUploaded;

  /// No description provided for @cancelRequest.
  ///
  /// In en, this message translates to:
  /// **'Cancel request'**
  String get cancelRequest;

  /// No description provided for @requestCancellation.
  ///
  /// In en, this message translates to:
  /// **'Request cancellation'**
  String get requestCancellation;

  /// No description provided for @cancelLeaveConfirm.
  ///
  /// In en, this message translates to:
  /// **'Cancel this leave request?'**
  String get cancelLeaveConfirm;

  /// No description provided for @requestCancelled.
  ///
  /// In en, this message translates to:
  /// **'Request cancelled.'**
  String get requestCancelled;

  /// No description provided for @payslipsTitle.
  ///
  /// In en, this message translates to:
  /// **'Payslips'**
  String get payslipsTitle;

  /// No description provided for @noPayslips.
  ///
  /// In en, this message translates to:
  /// **'No payslips for this year.'**
  String get noPayslips;

  /// No description provided for @noPayslipsHint.
  ///
  /// In en, this message translates to:
  /// **'Payslips appear here once payroll is issued.'**
  String get noPayslipsHint;

  /// No description provided for @gross.
  ///
  /// In en, this message translates to:
  /// **'Gross'**
  String get gross;

  /// No description provided for @deductions.
  ///
  /// In en, this message translates to:
  /// **'Deductions'**
  String get deductions;

  /// No description provided for @loanBalance.
  ///
  /// In en, this message translates to:
  /// **'Loan balance'**
  String get loanBalance;

  /// No description provided for @salaryBreakdown.
  ///
  /// In en, this message translates to:
  /// **'Salary breakdown'**
  String get salaryBreakdown;

  /// No description provided for @downloadPdf.
  ///
  /// In en, this message translates to:
  /// **'Download PDF'**
  String get downloadPdf;

  /// No description provided for @earnings.
  ///
  /// In en, this message translates to:
  /// **'Earnings'**
  String get earnings;

  /// No description provided for @statutory.
  ///
  /// In en, this message translates to:
  /// **'Statutory deductions'**
  String get statutory;

  /// No description provided for @loanRepayments.
  ///
  /// In en, this message translates to:
  /// **'Loan repayments'**
  String get loanRepayments;

  /// No description provided for @totals.
  ///
  /// In en, this message translates to:
  /// **'Totals'**
  String get totals;

  /// No description provided for @totalDeductions.
  ///
  /// In en, this message translates to:
  /// **'Total deductions'**
  String get totalDeductions;

  /// No description provided for @netPay.
  ///
  /// In en, this message translates to:
  /// **'Net pay'**
  String get netPay;

  /// No description provided for @payDate.
  ///
  /// In en, this message translates to:
  /// **'Pay date'**
  String get payDate;

  /// No description provided for @demoStatutoryNotice.
  ///
  /// In en, this message translates to:
  /// **'Statutory amounts use demo rules and are for illustration only.'**
  String get demoStatutoryNotice;

  /// No description provided for @loansTitle.
  ///
  /// In en, this message translates to:
  /// **'Loans and advances'**
  String get loansTitle;

  /// No description provided for @newLoanRequest.
  ///
  /// In en, this message translates to:
  /// **'New request'**
  String get newLoanRequest;

  /// No description provided for @yourLimits.
  ///
  /// In en, this message translates to:
  /// **'Your limits'**
  String get yourLimits;

  /// No description provided for @loansDisabled.
  ///
  /// In en, this message translates to:
  /// **'Loan requests are currently closed.'**
  String get loansDisabled;

  /// No description provided for @noLoans.
  ///
  /// In en, this message translates to:
  /// **'You have no loans or advances.'**
  String get noLoans;

  /// No description provided for @requestedAmount.
  ///
  /// In en, this message translates to:
  /// **'Requested'**
  String get requestedAmount;

  /// No description provided for @approvedAmount.
  ///
  /// In en, this message translates to:
  /// **'Approved'**
  String get approvedAmount;

  /// No description provided for @instalments.
  ///
  /// In en, this message translates to:
  /// **'Instalments'**
  String get instalments;

  /// No description provided for @outstanding.
  ///
  /// In en, this message translates to:
  /// **'Outstanding'**
  String get outstanding;

  /// No description provided for @amount.
  ///
  /// In en, this message translates to:
  /// **'Amount'**
  String get amount;

  /// No description provided for @startMonth.
  ///
  /// In en, this message translates to:
  /// **'First deduction month'**
  String get startMonth;

  /// No description provided for @loanSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Your request was sent for approval.'**
  String get loanSubmitted;

  /// No description provided for @cancelLoanConfirm.
  ///
  /// In en, this message translates to:
  /// **'Cancel this loan request?'**
  String get cancelLoanConfirm;

  /// No description provided for @hrRequestsTitle.
  ///
  /// In en, this message translates to:
  /// **'HR requests'**
  String get hrRequestsTitle;

  /// No description provided for @newRequest.
  ///
  /// In en, this message translates to:
  /// **'New request'**
  String get newRequest;

  /// No description provided for @tabOpen.
  ///
  /// In en, this message translates to:
  /// **'Open'**
  String get tabOpen;

  /// No description provided for @tabClosed.
  ///
  /// In en, this message translates to:
  /// **'Closed'**
  String get tabClosed;

  /// No description provided for @noOpenRequests.
  ///
  /// In en, this message translates to:
  /// **'No open requests.'**
  String get noOpenRequests;

  /// No description provided for @noClosedRequests.
  ///
  /// In en, this message translates to:
  /// **'No closed requests.'**
  String get noClosedRequests;

  /// No description provided for @requestsDisabled.
  ///
  /// In en, this message translates to:
  /// **'HR requests are not available.'**
  String get requestsDisabled;

  /// No description provided for @category.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get category;

  /// No description provided for @subjectOptional.
  ///
  /// In en, this message translates to:
  /// **'Subject (optional)'**
  String get subjectOptional;

  /// No description provided for @letterLanguage.
  ///
  /// In en, this message translates to:
  /// **'Letter language'**
  String get letterLanguage;

  /// No description provided for @arabic.
  ///
  /// In en, this message translates to:
  /// **'العربية'**
  String get arabic;

  /// No description provided for @english.
  ///
  /// In en, this message translates to:
  /// **'English'**
  String get english;

  /// No description provided for @bothLanguages.
  ///
  /// In en, this message translates to:
  /// **'Both'**
  String get bothLanguages;

  /// No description provided for @description.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get description;

  /// No description provided for @notesOptional.
  ///
  /// In en, this message translates to:
  /// **'Notes (optional)'**
  String get notesOptional;

  /// No description provided for @profileChangeHint.
  ///
  /// In en, this message translates to:
  /// **'Fill in only the fields you want to change. HR reviews the request before anything is updated.'**
  String get profileChangeHint;

  /// No description provided for @profileChangeEmpty.
  ///
  /// In en, this message translates to:
  /// **'Enter at least one new value.'**
  String get profileChangeEmpty;

  /// No description provided for @requestSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Your request was submitted.'**
  String get requestSubmitted;

  /// No description provided for @requestDetailsTitle.
  ///
  /// In en, this message translates to:
  /// **'Request details'**
  String get requestDetailsTitle;

  /// No description provided for @reference.
  ///
  /// In en, this message translates to:
  /// **'Reference'**
  String get reference;

  /// No description provided for @priority.
  ///
  /// In en, this message translates to:
  /// **'Priority'**
  String get priority;

  /// No description provided for @resolution.
  ///
  /// In en, this message translates to:
  /// **'Resolution'**
  String get resolution;

  /// No description provided for @conversation.
  ///
  /// In en, this message translates to:
  /// **'Conversation'**
  String get conversation;

  /// No description provided for @noMessages.
  ///
  /// In en, this message translates to:
  /// **'No messages yet.'**
  String get noMessages;

  /// No description provided for @you.
  ///
  /// In en, this message translates to:
  /// **'You'**
  String get you;

  /// No description provided for @writeReply.
  ///
  /// In en, this message translates to:
  /// **'Write a reply…'**
  String get writeReply;

  /// No description provided for @replySent.
  ///
  /// In en, this message translates to:
  /// **'Reply sent.'**
  String get replySent;

  /// No description provided for @history.
  ///
  /// In en, this message translates to:
  /// **'History'**
  String get history;

  /// No description provided for @cancelRequestConfirm.
  ///
  /// In en, this message translates to:
  /// **'Cancel this request?'**
  String get cancelRequestConfirm;

  /// No description provided for @documentsTitle.
  ///
  /// In en, this message translates to:
  /// **'Documents'**
  String get documentsTitle;

  /// No description provided for @noDocuments.
  ///
  /// In en, this message translates to:
  /// **'No documents shared with you yet.'**
  String get noDocuments;

  /// No description provided for @docsShared.
  ///
  /// In en, this message translates to:
  /// **'My documents'**
  String get docsShared;

  /// No description provided for @docsContracts.
  ///
  /// In en, this message translates to:
  /// **'Contracts'**
  String get docsContracts;

  /// No description provided for @docsCertificates.
  ///
  /// In en, this message translates to:
  /// **'Certificates'**
  String get docsCertificates;

  /// No description provided for @docsLetters.
  ///
  /// In en, this message translates to:
  /// **'Letters'**
  String get docsLetters;

  /// No description provided for @expired.
  ///
  /// In en, this message translates to:
  /// **'Expired'**
  String get expired;

  /// No description provided for @announcementsTitle.
  ///
  /// In en, this message translates to:
  /// **'Announcements'**
  String get announcementsTitle;

  /// No description provided for @noAnnouncements.
  ///
  /// In en, this message translates to:
  /// **'No announcements.'**
  String get noAnnouncements;

  /// No description provided for @needsAcknowledgement.
  ///
  /// In en, this message translates to:
  /// **'Acknowledge'**
  String get needsAcknowledgement;

  /// No description provided for @unread.
  ///
  /// In en, this message translates to:
  /// **'New'**
  String get unread;

  /// No description provided for @acknowledge.
  ///
  /// In en, this message translates to:
  /// **'I have read and understood'**
  String get acknowledge;

  /// No description provided for @acknowledged.
  ///
  /// In en, this message translates to:
  /// **'Acknowledged.'**
  String get acknowledged;

  /// No description provided for @notificationsTitle.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get notificationsTitle;

  /// No description provided for @noNotifications.
  ///
  /// In en, this message translates to:
  /// **'You have no notifications.'**
  String get noNotifications;

  /// No description provided for @markAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark all as read'**
  String get markAllRead;

  /// No description provided for @profileTitle.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profileTitle;

  /// No description provided for @employment.
  ///
  /// In en, this message translates to:
  /// **'Employment'**
  String get employment;

  /// No description provided for @status.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get status;

  /// No description provided for @hireDate.
  ///
  /// In en, this message translates to:
  /// **'Hire date'**
  String get hireDate;

  /// No description provided for @branch.
  ///
  /// In en, this message translates to:
  /// **'Branch'**
  String get branch;

  /// No description provided for @department.
  ///
  /// In en, this message translates to:
  /// **'Department'**
  String get department;

  /// No description provided for @position.
  ///
  /// In en, this message translates to:
  /// **'Position'**
  String get position;

  /// No description provided for @jobGrade.
  ///
  /// In en, this message translates to:
  /// **'Grade'**
  String get jobGrade;

  /// No description provided for @employmentType.
  ///
  /// In en, this message translates to:
  /// **'Employment type'**
  String get employmentType;

  /// No description provided for @workLocation.
  ///
  /// In en, this message translates to:
  /// **'Work location'**
  String get workLocation;

  /// No description provided for @manager.
  ///
  /// In en, this message translates to:
  /// **'Manager'**
  String get manager;

  /// No description provided for @contact.
  ///
  /// In en, this message translates to:
  /// **'Contact'**
  String get contact;

  /// No description provided for @workEmail.
  ///
  /// In en, this message translates to:
  /// **'Work e-mail'**
  String get workEmail;

  /// No description provided for @personalEmail.
  ///
  /// In en, this message translates to:
  /// **'Personal e-mail'**
  String get personalEmail;

  /// No description provided for @phone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get phone;

  /// No description provided for @address.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get address;

  /// No description provided for @city.
  ///
  /// In en, this message translates to:
  /// **'City'**
  String get city;

  /// No description provided for @personal.
  ///
  /// In en, this message translates to:
  /// **'Personal'**
  String get personal;

  /// No description provided for @dateOfBirth.
  ///
  /// In en, this message translates to:
  /// **'Date of birth'**
  String get dateOfBirth;

  /// No description provided for @nationalId.
  ///
  /// In en, this message translates to:
  /// **'National ID'**
  String get nationalId;

  /// No description provided for @nationality.
  ///
  /// In en, this message translates to:
  /// **'Nationality'**
  String get nationality;

  /// No description provided for @emergencyContacts.
  ///
  /// In en, this message translates to:
  /// **'Emergency contacts'**
  String get emergencyContacts;

  /// No description provided for @requestProfileChange.
  ///
  /// In en, this message translates to:
  /// **'Request a change'**
  String get requestProfileChange;

  /// No description provided for @settingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settingsTitle;

  /// No description provided for @language.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get language;

  /// No description provided for @theme.
  ///
  /// In en, this message translates to:
  /// **'Theme'**
  String get theme;

  /// No description provided for @themeSystem.
  ///
  /// In en, this message translates to:
  /// **'System'**
  String get themeSystem;

  /// No description provided for @themeLight.
  ///
  /// In en, this message translates to:
  /// **'Light'**
  String get themeLight;

  /// No description provided for @themeDark.
  ///
  /// In en, this message translates to:
  /// **'Dark'**
  String get themeDark;

  /// No description provided for @digits.
  ///
  /// In en, this message translates to:
  /// **'Digits'**
  String get digits;

  /// No description provided for @digitsWestern.
  ///
  /// In en, this message translates to:
  /// **'0123'**
  String get digitsWestern;

  /// No description provided for @digitsArabicIndic.
  ///
  /// In en, this message translates to:
  /// **'٠١٢٣'**
  String get digitsArabicIndic;

  /// No description provided for @notificationPreferences.
  ///
  /// In en, this message translates to:
  /// **'Notification preferences'**
  String get notificationPreferences;

  /// No description provided for @preferencesSaved.
  ///
  /// In en, this message translates to:
  /// **'Preferences saved.'**
  String get preferencesSaved;

  /// No description provided for @mandatoryNotification.
  ///
  /// In en, this message translates to:
  /// **'Always sent'**
  String get mandatoryNotification;

  /// No description provided for @pushDisabled.
  ///
  /// In en, this message translates to:
  /// **'Push notifications are not configured on this build. You will still see notifications inside the app.'**
  String get pushDisabled;

  /// No description provided for @acknowledgedOn.
  ///
  /// In en, this message translates to:
  /// **'Acknowledged on {date}'**
  String acknowledgedOn(String date);

  /// No description provided for @appVersion.
  ///
  /// In en, this message translates to:
  /// **'Version {version}'**
  String appVersion(String version);

  /// No description provided for @balanceAfter.
  ///
  /// In en, this message translates to:
  /// **'Balance after ({year})'**
  String balanceAfter(String year);

  /// No description provided for @currentValue.
  ///
  /// In en, this message translates to:
  /// **'Current: {value}'**
  String currentValue(String value);

  /// No description provided for @daysValue.
  ///
  /// In en, this message translates to:
  /// **'{days} days'**
  String daysValue(String days);

  /// No description provided for @distanceFromLocation.
  ///
  /// In en, this message translates to:
  /// **'{meters} m from {location}'**
  String distanceFromLocation(String meters, String location);

  /// No description provided for @enrolledOn.
  ///
  /// In en, this message translates to:
  /// **'Enrolled on {date}'**
  String enrolledOn(String date);

  /// No description provided for @faceConsentBody.
  ///
  /// In en, this message translates to:
  /// **'Face verification compares a photo taken at check-in with your enrolled face to confirm it is you. Biometric data is stored encrypted, used only for attendance, and deleted when you withdraw consent or leave the company. Consent text version {version}.'**
  String faceConsentBody(String version);

  /// No description provided for @gpsAccuracy.
  ///
  /// In en, this message translates to:
  /// **'GPS ±{meters} m'**
  String gpsAccuracy(String meters);

  /// No description provided for @greetingName.
  ///
  /// In en, this message translates to:
  /// **'{greeting}, {name}'**
  String greetingName(String greeting, String name);

  /// No description provided for @holidayToday.
  ///
  /// In en, this message translates to:
  /// **'Holiday: {name}'**
  String holidayToday(String name);

  /// No description provided for @insideLocation.
  ///
  /// In en, this message translates to:
  /// **'Inside {location}'**
  String insideLocation(String location);

  /// No description provided for @issueOutsideBody.
  ///
  /// In en, this message translates to:
  /// **'You are {distance} m from {location}; the allowed radius is {radius} m. Move closer and try again.'**
  String issueOutsideBody(String distance, String location, String radius);

  /// No description provided for @issuePoorAccuracyBody.
  ///
  /// In en, this message translates to:
  /// **'Current accuracy is ±{accuracy} m; at most {max} m is required. Move to an open area and try again.'**
  String issuePoorAccuracyBody(String accuracy, String max);

  /// No description provided for @lateBy.
  ///
  /// In en, this message translates to:
  /// **'Late {minutes} min'**
  String lateBy(String minutes);

  /// No description provided for @loanLimitValue.
  ///
  /// In en, this message translates to:
  /// **'Up to {amount} · {count} instalments'**
  String loanLimitValue(String amount, String count);

  /// No description provided for @maxAmount.
  ///
  /// In en, this message translates to:
  /// **'Maximum {amount}'**
  String maxAmount(String amount);

  /// No description provided for @maxInstalments.
  ///
  /// In en, this message translates to:
  /// **'Up to {count}'**
  String maxInstalments(String count);

  /// No description provided for @metersAway.
  ///
  /// In en, this message translates to:
  /// **'{meters} m away'**
  String metersAway(String meters);

  /// No description provided for @metersShort.
  ///
  /// In en, this message translates to:
  /// **'{meters} m'**
  String metersShort(String meters);

  /// No description provided for @netThisYear.
  ///
  /// In en, this message translates to:
  /// **'Net pay {year}'**
  String netThisYear(String year);

  /// No description provided for @noticeDays.
  ///
  /// In en, this message translates to:
  /// **'Request at least {days} days in advance.'**
  String noticeDays(String days);

  /// No description provided for @otpResendIn.
  ///
  /// In en, this message translates to:
  /// **'Resend code in {seconds} s'**
  String otpResendIn(String seconds);

  /// No description provided for @otpSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter the code we sent to {email}.'**
  String otpSubtitle(String email);

  /// No description provided for @radiusValue.
  ///
  /// In en, this message translates to:
  /// **'Radius {meters} m'**
  String radiusValue(String meters);

  /// No description provided for @recordedAt.
  ///
  /// In en, this message translates to:
  /// **'Recorded by the server at {time}.'**
  String recordedAt(String time);

  /// No description provided for @shiftRange.
  ///
  /// In en, this message translates to:
  /// **'{name} {start} – {end}'**
  String shiftRange(String name, String start, String end);

  /// No description provided for @validationInstalments.
  ///
  /// In en, this message translates to:
  /// **'Enter 1 to {max} instalments.'**
  String validationInstalments(String max);

  /// No description provided for @pendingReviews.
  ///
  /// In en, this message translates to:
  /// **'{count, plural, =1{1 attendance attempt is waiting for review} other{{count} attendance attempts are waiting for review}}'**
  String pendingReviews(int count);

  /// No description provided for @validationPasswordLength.
  ///
  /// In en, this message translates to:
  /// **'{min, plural, other{Use at least {min} characters.}}'**
  String validationPasswordLength(int min);

  /// No description provided for @otpInvalidLength.
  ///
  /// In en, this message translates to:
  /// **'{length, plural, other{Enter the {length}-digit code.}}'**
  String otpInvalidLength(int length);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'en'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return AppLocalizationsAr();
    case 'en':
      return AppLocalizationsEn();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
