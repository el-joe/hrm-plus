// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'HRM Plus';

  @override
  String get appTagline => 'Your workday, in one place';

  @override
  String get loading => 'Loading…';

  @override
  String get retry => 'Try again';

  @override
  String get refresh => 'Refresh';

  @override
  String get cancel => 'Cancel';

  @override
  String get close => 'Close';

  @override
  String get done => 'Done';

  @override
  String get save => 'Save';

  @override
  String get send => 'Send';

  @override
  String get next => 'Next';

  @override
  String get previous => 'Previous';

  @override
  String get continueLabel => 'Continue';

  @override
  String get tryAgain => 'Try again';

  @override
  String get emptyGeneric => 'Nothing here yet';

  @override
  String get selectDate => 'Select a date';

  @override
  String get switchLanguage => 'English';

  @override
  String get updateRequiredTitle => 'Update required';

  @override
  String get updateRequiredMessage =>
      'This version is no longer supported. Please install the latest version of HRM Plus.';

  @override
  String get errorTitle => 'Something went wrong';

  @override
  String get errorOfflineTitle => 'You are offline';

  @override
  String get errorOffline =>
      'No internet connection. Check your connection and try again.';

  @override
  String get errorTimeout => 'The server took too long to respond. Try again.';

  @override
  String get errorSessionExpired =>
      'Your session has ended. Please sign in again.';

  @override
  String get errorForbidden => 'You do not have access to this.';

  @override
  String get errorNotFound => 'This item was not found.';

  @override
  String get errorValidation => 'Please check the highlighted fields.';

  @override
  String get errorRateLimited =>
      'Too many attempts. Please wait a moment and try again.';

  @override
  String get errorServer =>
      'The server could not complete the request. Try again later.';

  @override
  String get errorUnexpected => 'An unexpected error occurred.';

  @override
  String get cannotOpenFile => 'The file could not be opened on this device.';

  @override
  String get downloading => 'Downloading…';

  @override
  String get validationRequired => 'This field is required.';

  @override
  String get validationEmail => 'Enter a valid e-mail address.';

  @override
  String get validationPasswordMatch => 'The passwords do not match.';

  @override
  String get validationStartDate => 'Choose the start date.';

  @override
  String get validationAmount => 'Enter an amount greater than zero.';

  @override
  String get validationMaxAmount => 'The amount is above your limit.';

  @override
  String get loginTitle => 'Sign in';

  @override
  String get loginSubtitle => 'Use your work e-mail and password.';

  @override
  String get loginFailed => 'Sign-in failed.';

  @override
  String get email => 'E-mail';

  @override
  String get password => 'Password';

  @override
  String get showPassword => 'Show password';

  @override
  String get hidePassword => 'Hide password';

  @override
  String get forgotPassword => 'Forgot password?';

  @override
  String get signIn => 'Sign in';

  @override
  String get forgotPasswordTitle => 'Forgot password';

  @override
  String get forgotPasswordSubtitle =>
      'Enter your work e-mail and we will send you a verification code.';

  @override
  String get sendCode => 'Send code';

  @override
  String get backToLogin => 'Back to sign in';

  @override
  String get otpTitle => 'Verification code';

  @override
  String get otpCode => 'Code';

  @override
  String get verify => 'Verify';

  @override
  String get otpResend => 'Resend code';

  @override
  String get otpResent => 'A new code has been sent.';

  @override
  String get newPasswordTitle => 'New password';

  @override
  String get newPasswordSubtitle => 'Choose a new password for your account.';

  @override
  String get newPassword => 'New password';

  @override
  String get confirmPassword => 'Confirm password';

  @override
  String get resetPassword => 'Reset password';

  @override
  String get passwordResetDone =>
      'Your password has been changed. Sign in with the new password.';

  @override
  String get logout => 'Sign out';

  @override
  String get logoutConfirm => 'Sign out of HRM Plus on this device?';

  @override
  String get navHome => 'Home';

  @override
  String get navAttendance => 'Attendance';

  @override
  String get navLeave => 'Leave';

  @override
  String get navPayslips => 'Payslips';

  @override
  String get navAccount => 'My account';

  @override
  String get goodMorning => 'Good morning';

  @override
  String get goodEvening => 'Good evening';

  @override
  String get restDay => 'Rest day';

  @override
  String get noShiftToday => 'No shift today';

  @override
  String get attendanceStatusUnavailable =>
      'Attendance status could not be loaded.';

  @override
  String get checkInTime => 'Check-in';

  @override
  String get checkOutTime => 'Check-out';

  @override
  String get workedHours => 'Worked hours';

  @override
  String get leaveBalance => 'Leave balance';

  @override
  String get lastNetSalary => 'Last net salary';

  @override
  String get pendingItems => 'Waiting for you';

  @override
  String get pendingLeaveRequests => 'Leave requests pending';

  @override
  String get pendingHrRequests => 'HR requests open';

  @override
  String get pendingLoanRequests => 'Loan requests pending';

  @override
  String get pendingCorrections => 'Attendance corrections pending';

  @override
  String get pendingAcknowledgements => 'Announcements to acknowledge';

  @override
  String get attendanceTitle => 'Attendance';

  @override
  String get checkIn => 'Check in';

  @override
  String get checkOut => 'Check out';

  @override
  String get mobileAttendanceDisabled => 'Mobile attendance is off';

  @override
  String get stateCheckedIn => 'Checked in';

  @override
  String get stateCheckedOut => 'Checked out';

  @override
  String get stateNotCheckedIn => 'Not checked in';

  @override
  String get assignedShift => 'Assigned shift';

  @override
  String get shift => 'Shift';

  @override
  String get holiday => 'Holiday';

  @override
  String get allowedLocations => 'Allowed locations';

  @override
  String get locationAnywhere => 'Any location';

  @override
  String get outsideShiftWindowHint =>
      'You are outside the check-in window of your shift. An attempt now may be sent for review.';

  @override
  String get todayPunches => 'Today\'s punches';

  @override
  String get noPunchesYet => 'No punches recorded today.';

  @override
  String get attendanceHistoryTitle => 'Attendance history';

  @override
  String get scheduleTitle => 'Work schedule';

  @override
  String get gpsTitle => 'GPS location';

  @override
  String get faceTitle => 'Face verification';

  @override
  String get faceVerification => 'Face verification';

  @override
  String get noLocationConfigured => 'No allowed location is set for you';

  @override
  String get gpsChecking => 'Checking GPS…';

  @override
  String get gpsOff => 'Location services off';

  @override
  String get gpsPermissionNeeded => 'Location permission needed';

  @override
  String get gpsUnavailable => 'GPS unavailable';

  @override
  String get gpsReady => 'GPS ready';

  @override
  String get faceOff => 'Face check not used';

  @override
  String get faceRequiredReady => 'Face check required · enrolled';

  @override
  String get faceOptionalReady => 'Face check · enrolled';

  @override
  String get faceNotEnrolled => 'Face not enrolled';

  @override
  String get stepLocation => 'Location';

  @override
  String get stepFace => 'Face';

  @override
  String get stepSubmit => 'Submit';

  @override
  String get preparing => 'Preparing…';

  @override
  String get locating => 'Getting your location…';

  @override
  String get submitting => 'Sending to the server… Do not close the app.';

  @override
  String get locationRationaleTitle => 'Location access';

  @override
  String get locationRationaleBody =>
      'HRM Plus checks your location only when you check in or out, to confirm you are at an allowed work location. Your location is not tracked in the background.';

  @override
  String get faceStepTitle => 'Verify your face';

  @override
  String get cameraRationale =>
      'The front camera takes one photo to confirm it is you. The photo is sent to the verification service and not kept on the phone.';

  @override
  String get faceSimulatedNotice =>
      'Face verification runs in simulated mode (development). No real biometric check is performed.';

  @override
  String get openCamera => 'Open camera';

  @override
  String get continueWithoutFace => 'Continue without face verification';

  @override
  String get faceCaptureHint =>
      'Place your face inside the frame, in good light, and tap Capture.';

  @override
  String get capture => 'Capture';

  @override
  String get issueOfflineTitle => 'No connection';

  @override
  String get issueOfflineBody =>
      'You are offline. Attendance can only be recorded when the server confirms it — connect to the internet and try again.';

  @override
  String get contactHr => 'Please contact HR.';

  @override
  String get issueLocationOffTitle => 'Location services are off';

  @override
  String get issueLocationOffBody =>
      'Turn on location services to check in or out.';

  @override
  String get issueLocationDeniedTitle => 'Location permission denied';

  @override
  String get issueLocationDeniedBody =>
      'HRM Plus needs your location to confirm you are at an allowed work location.';

  @override
  String get issueLocationBlockedBody =>
      'Location access is blocked. Allow it for HRM Plus in the system settings.';

  @override
  String get issueGpsTimeoutTitle => 'Could not get a GPS fix';

  @override
  String get issueGpsTimeoutBody =>
      'Move to an open area or near a window and try again.';

  @override
  String get issueGpsFailedTitle => 'Location unavailable';

  @override
  String get issueGpsFailedBody =>
      'Your location could not be read. Try again.';

  @override
  String get issuePoorAccuracyTitle => 'GPS accuracy is too low';

  @override
  String get issueOutsideTitle => 'Outside the allowed location';

  @override
  String get issueCameraDeniedTitle => 'Camera permission denied';

  @override
  String get issueCameraDeniedBody =>
      'Face verification needs the camera. Allow camera access to continue.';

  @override
  String get issueCameraBlockedBody =>
      'Camera access is blocked. Allow it for HRM Plus in the system settings.';

  @override
  String get issueCameraErrorTitle => 'Camera error';

  @override
  String get issueCameraErrorBody =>
      'The camera could not be started. Close other camera apps and try again.';

  @override
  String get issueNoCameraBody => 'No camera was found on this device.';

  @override
  String get issueFaceNotEnrolledBody =>
      'Your policy requires face verification. Enrol your face first.';

  @override
  String get issueSubmitFailedTitle => 'Not sent';

  @override
  String get retrySafeNote =>
      'Retrying is safe: the same request is not recorded twice.';

  @override
  String get notRecordedNote => 'Nothing has been recorded.';

  @override
  String get openLocationSettings => 'Open location settings';

  @override
  String get openAppSettings => 'Open app settings';

  @override
  String get enrolFace => 'Enrol my face';

  @override
  String get checkInRecorded => 'Check-in recorded';

  @override
  String get checkOutRecorded => 'Check-out recorded';

  @override
  String get sentForReviewTitle => 'Sent for review';

  @override
  String get sentForReviewBody =>
      'Your attempt was received but is NOT recorded yet. HR will review it and you will be notified.';

  @override
  String get rejectedTitle => 'Not recorded';

  @override
  String get rejectedBody => 'The server did not accept this attempt.';

  @override
  String get distance => 'Distance';

  @override
  String get allowedRadius => 'Allowed radius';

  @override
  String get faceAttemptsLeft => 'Face attempts left';

  @override
  String get accuracy => 'Accuracy';

  @override
  String get maxAccuracy => 'Required accuracy';

  @override
  String get nearestLocation => 'Nearest location';

  @override
  String get mockLocationDetected =>
      'A mock location was detected. The attempt may be sent for review.';

  @override
  String get reasonNotEmployed => 'You are not employed on this date';

  @override
  String get reasonMobileDisabled => 'Mobile attendance is not enabled for you';

  @override
  String get reasonPoorAccuracy => 'GPS accuracy was too low';

  @override
  String get reasonNoLocation => 'No allowed location is configured for you';

  @override
  String get reasonOutsideGeofence => 'You are outside the allowed location';

  @override
  String get reasonFaceRequired => 'Face verification is required';

  @override
  String get reasonFaceRetry => 'Face not recognised — try again';

  @override
  String get reasonMockLocation => 'A mock location was detected';

  @override
  String get reasonOutsideShiftWindow =>
      'Outside your shift\'s check-in window';

  @override
  String get reasonFaceFailed => 'Face verification failed';

  @override
  String get reasonFaceNotEnrolled => 'Your face is not enrolled';

  @override
  String get reasonTimeDrift => 'Your phone clock differs from the server time';

  @override
  String get reasonRepeatedAttempts => 'Too many attempts in a short time';

  @override
  String get gpsExplanation =>
      'This screen shows what the server will check: your GPS accuracy and the distance to your allowed work locations. The server validates again when you check in.';

  @override
  String get gpsStatus => 'GPS status';

  @override
  String get coordinates => 'Coordinates';

  @override
  String get poorAccuracyHint =>
      'Accuracy is above the limit. Move to an open area and refresh.';

  @override
  String get allowLocation => 'Allow location';

  @override
  String get inside => 'Inside';

  @override
  String get faceStatus => 'Status';

  @override
  String get facePolicy => 'Your policy';

  @override
  String get faceModeRequired => 'Required';

  @override
  String get faceModeOptional => 'Optional';

  @override
  String get faceModeOff => 'Not used';

  @override
  String get faceConsent => 'Consent';

  @override
  String get faceEnrolment => 'Enrolment';

  @override
  String get given => 'Given';

  @override
  String get notGiven => 'Not given';

  @override
  String get notEnrolled => 'Not enrolled';

  @override
  String get expires => 'Expires';

  @override
  String get faceConsentTitle => 'Consent to face verification';

  @override
  String get faceGiveConsent => 'I agree';

  @override
  String get faceConsentSaved => 'Consent recorded.';

  @override
  String get faceEnrolled => 'Your face has been enrolled.';

  @override
  String get faceReEnrol => 'Enrol again';

  @override
  String get faceWithdraw => 'Withdraw consent';

  @override
  String get faceWithdrawConfirm =>
      'Withdrawing consent deletes your biometric data immediately. If your policy requires face verification you will not be able to check in from the app.';

  @override
  String get faceWithdrawn => 'Consent withdrawn and data deleted.';

  @override
  String get tabDays => 'Days';

  @override
  String get tabAttempts => 'Mobile attempts';

  @override
  String get tabCorrections => 'Corrections';

  @override
  String get noAttendanceDays => 'No attendance records for this month.';

  @override
  String get presentDays => 'Present';

  @override
  String get lateDays => 'Late';

  @override
  String get absentDays => 'Absent';

  @override
  String get leaveDays => 'Leave';

  @override
  String get overtime => 'Overtime';

  @override
  String get noAttempts => 'No mobile attempts yet.';

  @override
  String get outcomeAccepted => 'Recorded';

  @override
  String get outcomeFlagged => 'In review';

  @override
  String get outcomeRejected => 'Rejected';

  @override
  String get outcomeReviewApproved => 'Approved after review';

  @override
  String get outcomeReviewRejected => 'Rejected after review';

  @override
  String get noCorrections => 'No correction requests.';

  @override
  String get noSchedule => 'No schedule for these dates.';

  @override
  String get noShift => 'No shift';

  @override
  String get workingDay => 'Working day';

  @override
  String get leaveHistoryTitle => 'Leave history';

  @override
  String get newLeaveRequest => 'New leave request';

  @override
  String get noLeaveTypes => 'No leave types are available to you.';

  @override
  String get notTracked => 'No balance';

  @override
  String get entitlement => 'Entitled';

  @override
  String get carriedIn => 'Carried over';

  @override
  String get used => 'Used';

  @override
  String get pending => 'Pending';

  @override
  String get leaveType => 'Leave type';

  @override
  String get dayPartFull => 'Full day';

  @override
  String get dayPartFirstHalf => 'First half';

  @override
  String get dayPartSecondHalf => 'Second half';

  @override
  String get dayPartHours => 'Hours';

  @override
  String get startDate => 'From';

  @override
  String get endDate => 'To';

  @override
  String get date => 'Date';

  @override
  String get hours => 'Hours';

  @override
  String get reasonOptional => 'Reason (optional)';

  @override
  String get attachments => 'Attachments';

  @override
  String get addAttachment => 'Add file';

  @override
  String get attachmentRequiredHint =>
      'This leave type needs a supporting document.';

  @override
  String get attachmentRequired => 'A supporting document is required.';

  @override
  String get previewDays => 'Calculate days';

  @override
  String get previewTitle => 'Preview';

  @override
  String get daysCounted => 'Days counted';

  @override
  String get daysNotCounted => 'Days not counted';

  @override
  String get submitRequest => 'Submit request';

  @override
  String get leaveSubmitted => 'Your leave request was sent for approval.';

  @override
  String get noLeaveRequests => 'You have no leave requests.';

  @override
  String get leaveRequestTitle => 'Leave request';

  @override
  String get duration => 'Duration';

  @override
  String get currentStep => 'Current step';

  @override
  String get reason => 'Reason';

  @override
  String get rejectionReason => 'Rejection reason';

  @override
  String get submittedOn => 'Submitted';

  @override
  String get dayBreakdown => 'Days';

  @override
  String get notCounted => 'Not counted';

  @override
  String get approvalTrail => 'Approval trail';

  @override
  String get noAttachments => 'No attachments.';

  @override
  String get attachmentsUploaded => 'Files uploaded.';

  @override
  String get cancelRequest => 'Cancel request';

  @override
  String get requestCancellation => 'Request cancellation';

  @override
  String get cancelLeaveConfirm => 'Cancel this leave request?';

  @override
  String get requestCancelled => 'Request cancelled.';

  @override
  String get payslipsTitle => 'Payslips';

  @override
  String get noPayslips => 'No payslips for this year.';

  @override
  String get noPayslipsHint => 'Payslips appear here once payroll is issued.';

  @override
  String get gross => 'Gross';

  @override
  String get deductions => 'Deductions';

  @override
  String get loanBalance => 'Loan balance';

  @override
  String get salaryBreakdown => 'Salary breakdown';

  @override
  String get downloadPdf => 'Download PDF';

  @override
  String get earnings => 'Earnings';

  @override
  String get statutory => 'Statutory deductions';

  @override
  String get loanRepayments => 'Loan repayments';

  @override
  String get totals => 'Totals';

  @override
  String get totalDeductions => 'Total deductions';

  @override
  String get netPay => 'Net pay';

  @override
  String get payDate => 'Pay date';

  @override
  String get demoStatutoryNotice =>
      'Statutory amounts use demo rules and are for illustration only.';

  @override
  String get loansTitle => 'Loans and advances';

  @override
  String get newLoanRequest => 'New request';

  @override
  String get yourLimits => 'Your limits';

  @override
  String get loansDisabled => 'Loan requests are currently closed.';

  @override
  String get noLoans => 'You have no loans or advances.';

  @override
  String get requestedAmount => 'Requested';

  @override
  String get approvedAmount => 'Approved';

  @override
  String get instalments => 'Instalments';

  @override
  String get outstanding => 'Outstanding';

  @override
  String get amount => 'Amount';

  @override
  String get startMonth => 'First deduction month';

  @override
  String get loanSubmitted => 'Your request was sent for approval.';

  @override
  String get cancelLoanConfirm => 'Cancel this loan request?';

  @override
  String get hrRequestsTitle => 'HR requests';

  @override
  String get newRequest => 'New request';

  @override
  String get tabOpen => 'Open';

  @override
  String get tabClosed => 'Closed';

  @override
  String get noOpenRequests => 'No open requests.';

  @override
  String get noClosedRequests => 'No closed requests.';

  @override
  String get requestsDisabled => 'HR requests are not available.';

  @override
  String get category => 'Category';

  @override
  String get subjectOptional => 'Subject (optional)';

  @override
  String get letterLanguage => 'Letter language';

  @override
  String get arabic => 'العربية';

  @override
  String get english => 'English';

  @override
  String get bothLanguages => 'Both';

  @override
  String get description => 'Description';

  @override
  String get notesOptional => 'Notes (optional)';

  @override
  String get profileChangeHint =>
      'Fill in only the fields you want to change. HR reviews the request before anything is updated.';

  @override
  String get profileChangeEmpty => 'Enter at least one new value.';

  @override
  String get requestSubmitted => 'Your request was submitted.';

  @override
  String get requestDetailsTitle => 'Request details';

  @override
  String get reference => 'Reference';

  @override
  String get priority => 'Priority';

  @override
  String get resolution => 'Resolution';

  @override
  String get conversation => 'Conversation';

  @override
  String get noMessages => 'No messages yet.';

  @override
  String get you => 'You';

  @override
  String get writeReply => 'Write a reply…';

  @override
  String get replySent => 'Reply sent.';

  @override
  String get history => 'History';

  @override
  String get cancelRequestConfirm => 'Cancel this request?';

  @override
  String get documentsTitle => 'Documents';

  @override
  String get noDocuments => 'No documents shared with you yet.';

  @override
  String get docsShared => 'My documents';

  @override
  String get docsContracts => 'Contracts';

  @override
  String get docsCertificates => 'Certificates';

  @override
  String get docsLetters => 'Letters';

  @override
  String get expired => 'Expired';

  @override
  String get announcementsTitle => 'Announcements';

  @override
  String get noAnnouncements => 'No announcements.';

  @override
  String get needsAcknowledgement => 'Acknowledge';

  @override
  String get unread => 'New';

  @override
  String get acknowledge => 'I have read and understood';

  @override
  String get acknowledged => 'Acknowledged.';

  @override
  String get notificationsTitle => 'Notifications';

  @override
  String get noNotifications => 'You have no notifications.';

  @override
  String get markAllRead => 'Mark all as read';

  @override
  String get profileTitle => 'Profile';

  @override
  String get employment => 'Employment';

  @override
  String get status => 'Status';

  @override
  String get hireDate => 'Hire date';

  @override
  String get branch => 'Branch';

  @override
  String get department => 'Department';

  @override
  String get position => 'Position';

  @override
  String get jobGrade => 'Grade';

  @override
  String get employmentType => 'Employment type';

  @override
  String get workLocation => 'Work location';

  @override
  String get manager => 'Manager';

  @override
  String get contact => 'Contact';

  @override
  String get workEmail => 'Work e-mail';

  @override
  String get personalEmail => 'Personal e-mail';

  @override
  String get phone => 'Phone';

  @override
  String get address => 'Address';

  @override
  String get city => 'City';

  @override
  String get personal => 'Personal';

  @override
  String get dateOfBirth => 'Date of birth';

  @override
  String get nationalId => 'National ID';

  @override
  String get nationality => 'Nationality';

  @override
  String get emergencyContacts => 'Emergency contacts';

  @override
  String get requestProfileChange => 'Request a change';

  @override
  String get settingsTitle => 'Settings';

  @override
  String get language => 'Language';

  @override
  String get theme => 'Theme';

  @override
  String get themeSystem => 'System';

  @override
  String get themeLight => 'Light';

  @override
  String get themeDark => 'Dark';

  @override
  String get digits => 'Digits';

  @override
  String get digitsWestern => '0123';

  @override
  String get digitsArabicIndic => '٠١٢٣';

  @override
  String get notificationPreferences => 'Notification preferences';

  @override
  String get preferencesSaved => 'Preferences saved.';

  @override
  String get mandatoryNotification => 'Always sent';

  @override
  String get pushDisabled =>
      'Push notifications are not configured on this build. You will still see notifications inside the app.';

  @override
  String acknowledgedOn(String date) {
    return 'Acknowledged on $date';
  }

  @override
  String appVersion(String version) {
    return 'Version $version';
  }

  @override
  String balanceAfter(String year) {
    return 'Balance after ($year)';
  }

  @override
  String currentValue(String value) {
    return 'Current: $value';
  }

  @override
  String daysValue(String days) {
    return '$days days';
  }

  @override
  String distanceFromLocation(String meters, String location) {
    return '$meters m from $location';
  }

  @override
  String enrolledOn(String date) {
    return 'Enrolled on $date';
  }

  @override
  String faceConsentBody(String version) {
    return 'Face verification compares a photo taken at check-in with your enrolled face to confirm it is you. Biometric data is stored encrypted, used only for attendance, and deleted when you withdraw consent or leave the company. Consent text version $version.';
  }

  @override
  String gpsAccuracy(String meters) {
    return 'GPS ±$meters m';
  }

  @override
  String greetingName(String greeting, String name) {
    return '$greeting, $name';
  }

  @override
  String holidayToday(String name) {
    return 'Holiday: $name';
  }

  @override
  String insideLocation(String location) {
    return 'Inside $location';
  }

  @override
  String issueOutsideBody(String distance, String location, String radius) {
    return 'You are $distance m from $location; the allowed radius is $radius m. Move closer and try again.';
  }

  @override
  String issuePoorAccuracyBody(String accuracy, String max) {
    return 'Current accuracy is ±$accuracy m; at most $max m is required. Move to an open area and try again.';
  }

  @override
  String lateBy(String minutes) {
    return 'Late $minutes min';
  }

  @override
  String loanLimitValue(String amount, String count) {
    return 'Up to $amount · $count instalments';
  }

  @override
  String maxAmount(String amount) {
    return 'Maximum $amount';
  }

  @override
  String maxInstalments(String count) {
    return 'Up to $count';
  }

  @override
  String metersAway(String meters) {
    return '$meters m away';
  }

  @override
  String metersShort(String meters) {
    return '$meters m';
  }

  @override
  String netThisYear(String year) {
    return 'Net pay $year';
  }

  @override
  String noticeDays(String days) {
    return 'Request at least $days days in advance.';
  }

  @override
  String otpResendIn(String seconds) {
    return 'Resend code in $seconds s';
  }

  @override
  String otpSubtitle(String email) {
    return 'Enter the code we sent to $email.';
  }

  @override
  String radiusValue(String meters) {
    return 'Radius $meters m';
  }

  @override
  String recordedAt(String time) {
    return 'Recorded by the server at $time.';
  }

  @override
  String shiftRange(String name, String start, String end) {
    return '$name $start – $end';
  }

  @override
  String validationInstalments(String max) {
    return 'Enter 1 to $max instalments.';
  }

  @override
  String pendingReviews(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count attendance attempts are waiting for review',
      one: '1 attendance attempt is waiting for review',
    );
    return '$_temp0';
  }

  @override
  String validationPasswordLength(int min) {
    String _temp0 = intl.Intl.pluralLogic(
      min,
      locale: localeName,
      other: 'Use at least $min characters.',
    );
    return '$_temp0';
  }

  @override
  String otpInvalidLength(int length) {
    String _temp0 = intl.Intl.pluralLogic(
      length,
      locale: localeName,
      other: 'Enter the $length-digit code.',
    );
    return '$_temp0';
  }
}
