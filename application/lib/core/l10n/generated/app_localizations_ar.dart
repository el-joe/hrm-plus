// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class AppLocalizationsAr extends AppLocalizations {
  AppLocalizationsAr([String locale = 'ar']) : super(locale);

  @override
  String get appName => 'HRM Plus';

  @override
  String get appTagline => 'يوم عملك في مكان واحد';

  @override
  String get loading => 'جارٍ التحميل…';

  @override
  String get retry => 'إعادة المحاولة';

  @override
  String get refresh => 'تحديث';

  @override
  String get cancel => 'إلغاء';

  @override
  String get close => 'إغلاق';

  @override
  String get done => 'تم';

  @override
  String get save => 'حفظ';

  @override
  String get send => 'إرسال';

  @override
  String get next => 'التالي';

  @override
  String get previous => 'السابق';

  @override
  String get continueLabel => 'متابعة';

  @override
  String get tryAgain => 'حاول مرة أخرى';

  @override
  String get emptyGeneric => 'لا توجد بيانات بعد';

  @override
  String get selectDate => 'اختر التاريخ';

  @override
  String get switchLanguage => 'العربية';

  @override
  String get updateRequiredTitle => 'يلزم تحديث التطبيق';

  @override
  String get updateRequiredMessage =>
      'لم يعد هذا الإصدار مدعوماً. يرجى تثبيت أحدث إصدار من HRM Plus.';

  @override
  String get errorTitle => 'حدث خطأ ما';

  @override
  String get errorOfflineTitle => 'لا يوجد اتصال';

  @override
  String get errorOffline =>
      'لا يوجد اتصال بالإنترنت. تحقّق من الاتصال ثم أعد المحاولة.';

  @override
  String get errorTimeout => 'استغرق الخادم وقتاً طويلاً للرد. أعد المحاولة.';

  @override
  String get errorSessionExpired => 'انتهت جلستك. يرجى تسجيل الدخول مرة أخرى.';

  @override
  String get errorForbidden => 'ليست لديك صلاحية للوصول إلى هذا.';

  @override
  String get errorNotFound => 'العنصر غير موجود.';

  @override
  String get errorValidation => 'يرجى مراجعة الحقول المحددة.';

  @override
  String get errorRateLimited => 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.';

  @override
  String get errorServer =>
      'تعذّر على الخادم إكمال الطلب. أعد المحاولة لاحقاً.';

  @override
  String get errorUnexpected => 'حدث خطأ غير متوقع.';

  @override
  String get cannotOpenFile => 'تعذّر فتح الملف على هذا الجهاز.';

  @override
  String get downloading => 'جارٍ التنزيل…';

  @override
  String get validationRequired => 'هذا الحقل مطلوب.';

  @override
  String get validationEmail => 'أدخل بريداً إلكترونياً صحيحاً.';

  @override
  String get validationPasswordMatch => 'كلمتا المرور غير متطابقتين.';

  @override
  String get validationStartDate => 'اختر تاريخ البداية.';

  @override
  String get validationAmount => 'أدخل مبلغاً أكبر من صفر.';

  @override
  String get validationMaxAmount => 'المبلغ يتجاوز الحد المسموح لك.';

  @override
  String get loginTitle => 'تسجيل الدخول';

  @override
  String get loginSubtitle => 'استخدم بريدك الإلكتروني للعمل وكلمة المرور.';

  @override
  String get loginFailed => 'تعذّر تسجيل الدخول.';

  @override
  String get email => 'البريد الإلكتروني';

  @override
  String get password => 'كلمة المرور';

  @override
  String get showPassword => 'إظهار كلمة المرور';

  @override
  String get hidePassword => 'إخفاء كلمة المرور';

  @override
  String get forgotPassword => 'نسيت كلمة المرور؟';

  @override
  String get signIn => 'دخول';

  @override
  String get forgotPasswordTitle => 'نسيت كلمة المرور';

  @override
  String get forgotPasswordSubtitle =>
      'أدخل بريدك الإلكتروني للعمل وسنرسل إليك رمز تحقق.';

  @override
  String get sendCode => 'إرسال الرمز';

  @override
  String get backToLogin => 'العودة لتسجيل الدخول';

  @override
  String get otpTitle => 'رمز التحقق';

  @override
  String get otpCode => 'الرمز';

  @override
  String get verify => 'تحقق';

  @override
  String get otpResend => 'إعادة إرسال الرمز';

  @override
  String get otpResent => 'تم إرسال رمز جديد.';

  @override
  String get newPasswordTitle => 'كلمة مرور جديدة';

  @override
  String get newPasswordSubtitle => 'اختر كلمة مرور جديدة لحسابك.';

  @override
  String get newPassword => 'كلمة المرور الجديدة';

  @override
  String get confirmPassword => 'تأكيد كلمة المرور';

  @override
  String get resetPassword => 'إعادة تعيين كلمة المرور';

  @override
  String get passwordResetDone =>
      'تم تغيير كلمة المرور. سجّل الدخول بكلمة المرور الجديدة.';

  @override
  String get logout => 'تسجيل الخروج';

  @override
  String get logoutConfirm =>
      'هل تريد تسجيل الخروج من HRM Plus على هذا الجهاز؟';

  @override
  String get navHome => 'الرئيسية';

  @override
  String get navAttendance => 'الحضور';

  @override
  String get navLeave => 'الإجازات';

  @override
  String get navPayslips => 'الرواتب';

  @override
  String get navAccount => 'حسابي';

  @override
  String get goodMorning => 'صباح الخير';

  @override
  String get goodEvening => 'مساء الخير';

  @override
  String get restDay => 'يوم راحة';

  @override
  String get noShiftToday => 'لا توجد وردية اليوم';

  @override
  String get attendanceStatusUnavailable => 'تعذّر تحميل حالة الحضور.';

  @override
  String get checkInTime => 'الحضور';

  @override
  String get checkOutTime => 'الانصراف';

  @override
  String get workedHours => 'ساعات العمل';

  @override
  String get leaveBalance => 'رصيد الإجازات';

  @override
  String get lastNetSalary => 'صافي آخر راتب';

  @override
  String get pendingItems => 'بانتظارك';

  @override
  String get pendingLeaveRequests => 'طلبات إجازة قيد الانتظار';

  @override
  String get pendingHrRequests => 'طلبات موارد بشرية مفتوحة';

  @override
  String get pendingLoanRequests => 'طلبات سلف قيد الانتظار';

  @override
  String get pendingCorrections => 'تصحيحات حضور قيد الانتظار';

  @override
  String get pendingAcknowledgements => 'إعلانات بانتظار الإقرار';

  @override
  String get attendanceTitle => 'الحضور';

  @override
  String get checkIn => 'تسجيل الحضور';

  @override
  String get checkOut => 'تسجيل الانصراف';

  @override
  String get mobileAttendanceDisabled => 'الحضور عبر الجوال غير مفعّل';

  @override
  String get stateCheckedIn => 'تم تسجيل الحضور';

  @override
  String get stateCheckedOut => 'تم تسجيل الانصراف';

  @override
  String get stateNotCheckedIn => 'لم يُسجَّل الحضور';

  @override
  String get assignedShift => 'الوردية المحددة';

  @override
  String get shift => 'الوردية';

  @override
  String get holiday => 'عطلة';

  @override
  String get allowedLocations => 'المواقع المسموح بها';

  @override
  String get locationAnywhere => 'أي موقع';

  @override
  String get outsideShiftWindowHint =>
      'أنت خارج نافذة تسجيل الحضور لورديتك. قد تُرسل المحاولة الآن للمراجعة.';

  @override
  String get todayPunches => 'بصمات اليوم';

  @override
  String get noPunchesYet => 'لا توجد بصمات مسجلة اليوم.';

  @override
  String get attendanceHistoryTitle => 'سجل الحضور';

  @override
  String get scheduleTitle => 'جدول العمل';

  @override
  String get gpsTitle => 'الموقع الجغرافي';

  @override
  String get faceTitle => 'التحقق بالوجه';

  @override
  String get faceVerification => 'التحقق بالوجه';

  @override
  String get noLocationConfigured => 'لم يُحدَّد لك موقع مسموح';

  @override
  String get gpsChecking => 'جارٍ فحص GPS…';

  @override
  String get gpsOff => 'خدمات الموقع متوقفة';

  @override
  String get gpsPermissionNeeded => 'يلزم إذن الموقع';

  @override
  String get gpsUnavailable => 'GPS غير متاح';

  @override
  String get gpsReady => 'GPS جاهز';

  @override
  String get faceOff => 'التحقق بالوجه غير مستخدم';

  @override
  String get faceRequiredReady => 'التحقق بالوجه مطلوب · مسجّل';

  @override
  String get faceOptionalReady => 'التحقق بالوجه · مسجّل';

  @override
  String get faceNotEnrolled => 'الوجه غير مسجّل';

  @override
  String get stepLocation => 'الموقع';

  @override
  String get stepFace => 'الوجه';

  @override
  String get stepSubmit => 'الإرسال';

  @override
  String get preparing => 'جارٍ التحضير…';

  @override
  String get locating => 'جارٍ تحديد موقعك…';

  @override
  String get submitting => 'جارٍ الإرسال إلى الخادم… لا تغلق التطبيق.';

  @override
  String get locationRationaleTitle => 'الوصول إلى الموقع';

  @override
  String get locationRationaleBody =>
      'يتحقق HRM Plus من موقعك فقط عند تسجيل الحضور أو الانصراف، للتأكد من وجودك في موقع عمل مسموح. لا يتم تتبع موقعك في الخلفية.';

  @override
  String get faceStepTitle => 'تحقق من وجهك';

  @override
  String get cameraRationale =>
      'تلتقط الكاميرا الأمامية صورة واحدة للتأكد من هويتك. تُرسل الصورة إلى خدمة التحقق ولا تُحفظ على الهاتف.';

  @override
  String get faceSimulatedNotice =>
      'يعمل التحقق بالوجه في وضع المحاكاة (للتطوير). لا يُجرى تحقق حيوي فعلي.';

  @override
  String get openCamera => 'فتح الكاميرا';

  @override
  String get continueWithoutFace => 'المتابعة دون التحقق بالوجه';

  @override
  String get faceCaptureHint =>
      'ضع وجهك داخل الإطار في إضاءة جيدة ثم اضغط التقاط.';

  @override
  String get capture => 'التقاط';

  @override
  String get issueOfflineTitle => 'لا يوجد اتصال';

  @override
  String get issueOfflineBody =>
      'أنت غير متصل. لا يُسجَّل الحضور إلا بعد تأكيد الخادم — اتصل بالإنترنت ثم أعد المحاولة.';

  @override
  String get contactHr => 'يرجى التواصل مع الموارد البشرية.';

  @override
  String get issueLocationOffTitle => 'خدمات الموقع متوقفة';

  @override
  String get issueLocationOffBody =>
      'شغّل خدمات الموقع لتسجيل الحضور أو الانصراف.';

  @override
  String get issueLocationDeniedTitle => 'تم رفض إذن الموقع';

  @override
  String get issueLocationDeniedBody =>
      'يحتاج HRM Plus إلى موقعك للتأكد من وجودك في موقع عمل مسموح.';

  @override
  String get issueLocationBlockedBody =>
      'الوصول إلى الموقع محظور. اسمح به لتطبيق HRM Plus من إعدادات النظام.';

  @override
  String get issueGpsTimeoutTitle => 'تعذّر تحديد الموقع';

  @override
  String get issueGpsTimeoutBody =>
      'انتقل إلى مكان مفتوح أو بالقرب من نافذة ثم أعد المحاولة.';

  @override
  String get issueGpsFailedTitle => 'الموقع غير متاح';

  @override
  String get issueGpsFailedBody => 'تعذّرت قراءة موقعك. أعد المحاولة.';

  @override
  String get issuePoorAccuracyTitle => 'دقة GPS منخفضة';

  @override
  String get issueOutsideTitle => 'خارج الموقع المسموح';

  @override
  String get issueCameraDeniedTitle => 'تم رفض إذن الكاميرا';

  @override
  String get issueCameraDeniedBody =>
      'يحتاج التحقق بالوجه إلى الكاميرا. اسمح بالوصول إلى الكاميرا للمتابعة.';

  @override
  String get issueCameraBlockedBody =>
      'الوصول إلى الكاميرا محظور. اسمح به لتطبيق HRM Plus من إعدادات النظام.';

  @override
  String get issueCameraErrorTitle => 'خطأ في الكاميرا';

  @override
  String get issueCameraErrorBody =>
      'تعذّر تشغيل الكاميرا. أغلق تطبيقات الكاميرا الأخرى ثم أعد المحاولة.';

  @override
  String get issueNoCameraBody => 'لم يتم العثور على كاميرا في هذا الجهاز.';

  @override
  String get issueFaceNotEnrolledBody =>
      'تتطلب سياستك التحقق بالوجه. سجّل وجهك أولاً.';

  @override
  String get issueSubmitFailedTitle => 'لم يتم الإرسال';

  @override
  String get retrySafeNote =>
      'إعادة المحاولة آمنة: لن يُسجَّل الطلب نفسه مرتين.';

  @override
  String get notRecordedNote => 'لم يتم تسجيل أي شيء.';

  @override
  String get openLocationSettings => 'فتح إعدادات الموقع';

  @override
  String get openAppSettings => 'فتح إعدادات التطبيق';

  @override
  String get enrolFace => 'تسجيل الوجه';

  @override
  String get checkInRecorded => 'تم تسجيل الحضور';

  @override
  String get checkOutRecorded => 'تم تسجيل الانصراف';

  @override
  String get sentForReviewTitle => 'أُرسل للمراجعة';

  @override
  String get sentForReviewBody =>
      'تم استلام محاولتك لكنها لم تُسجَّل بعد. ستراجعها الموارد البشرية وسيصلك إشعار.';

  @override
  String get rejectedTitle => 'لم يتم التسجيل';

  @override
  String get rejectedBody => 'لم يقبل الخادم هذه المحاولة.';

  @override
  String get distance => 'المسافة';

  @override
  String get allowedRadius => 'النطاق المسموح';

  @override
  String get faceAttemptsLeft => 'محاولات الوجه المتبقية';

  @override
  String get accuracy => 'الدقة';

  @override
  String get maxAccuracy => 'الدقة المطلوبة';

  @override
  String get nearestLocation => 'أقرب موقع';

  @override
  String get mockLocationDetected =>
      'تم اكتشاف موقع وهمي. قد تُرسل المحاولة للمراجعة.';

  @override
  String get reasonNotEmployed => 'لست على رأس العمل في هذا التاريخ';

  @override
  String get reasonMobileDisabled => 'الحضور عبر الجوال غير مفعّل لك';

  @override
  String get reasonPoorAccuracy => 'كانت دقة GPS منخفضة';

  @override
  String get reasonNoLocation => 'لم يُحدَّد لك موقع مسموح';

  @override
  String get reasonOutsideGeofence => 'أنت خارج الموقع المسموح';

  @override
  String get reasonFaceRequired => 'التحقق بالوجه مطلوب';

  @override
  String get reasonFaceRetry => 'لم يتم التعرف على الوجه — أعد المحاولة';

  @override
  String get reasonMockLocation => 'تم اكتشاف موقع وهمي';

  @override
  String get reasonOutsideShiftWindow => 'خارج نافذة الحضور لورديتك';

  @override
  String get reasonFaceFailed => 'فشل التحقق بالوجه';

  @override
  String get reasonFaceNotEnrolled => 'وجهك غير مسجّل';

  @override
  String get reasonTimeDrift => 'ساعة هاتفك تختلف عن وقت الخادم';

  @override
  String get reasonRepeatedAttempts => 'محاولات كثيرة في وقت قصير';

  @override
  String get gpsExplanation =>
      'تعرض هذه الشاشة ما سيتحقق منه الخادم: دقة GPS والمسافة إلى مواقع العمل المسموحة. يتحقق الخادم مرة أخرى عند تسجيل الحضور.';

  @override
  String get gpsStatus => 'حالة GPS';

  @override
  String get coordinates => 'الإحداثيات';

  @override
  String get poorAccuracyHint =>
      'الدقة أعلى من الحد المسموح. انتقل إلى مكان مفتوح ثم حدّث.';

  @override
  String get allowLocation => 'السماح بالموقع';

  @override
  String get inside => 'داخل النطاق';

  @override
  String get faceStatus => 'الحالة';

  @override
  String get facePolicy => 'سياستك';

  @override
  String get faceModeRequired => 'مطلوب';

  @override
  String get faceModeOptional => 'اختياري';

  @override
  String get faceModeOff => 'غير مستخدم';

  @override
  String get faceConsent => 'الموافقة';

  @override
  String get faceEnrolment => 'التسجيل';

  @override
  String get given => 'ممنوحة';

  @override
  String get notGiven => 'غير ممنوحة';

  @override
  String get notEnrolled => 'غير مسجّل';

  @override
  String get expires => 'ينتهي في';

  @override
  String get faceConsentTitle => 'الموافقة على التحقق بالوجه';

  @override
  String get faceGiveConsent => 'أوافق';

  @override
  String get faceConsentSaved => 'تم تسجيل الموافقة.';

  @override
  String get faceEnrolled => 'تم تسجيل وجهك.';

  @override
  String get faceReEnrol => 'إعادة التسجيل';

  @override
  String get faceWithdraw => 'سحب الموافقة';

  @override
  String get faceWithdrawConfirm =>
      'سحب الموافقة يحذف بياناتك الحيوية فوراً. إذا كانت سياستك تتطلب التحقق بالوجه فلن تتمكن من تسجيل الحضور من التطبيق.';

  @override
  String get faceWithdrawn => 'تم سحب الموافقة وحذف البيانات.';

  @override
  String get tabDays => 'الأيام';

  @override
  String get tabAttempts => 'محاولات الجوال';

  @override
  String get tabCorrections => 'التصحيحات';

  @override
  String get noAttendanceDays => 'لا توجد سجلات حضور لهذا الشهر.';

  @override
  String get presentDays => 'حضور';

  @override
  String get lateDays => 'تأخير';

  @override
  String get absentDays => 'غياب';

  @override
  String get leaveDays => 'إجازة';

  @override
  String get overtime => 'عمل إضافي';

  @override
  String get noAttempts => 'لا توجد محاولات من الجوال بعد.';

  @override
  String get outcomeAccepted => 'مسجّل';

  @override
  String get outcomeFlagged => 'قيد المراجعة';

  @override
  String get outcomeRejected => 'مرفوض';

  @override
  String get outcomeReviewApproved => 'اعتُمد بعد المراجعة';

  @override
  String get outcomeReviewRejected => 'رُفض بعد المراجعة';

  @override
  String get noCorrections => 'لا توجد طلبات تصحيح.';

  @override
  String get noSchedule => 'لا يوجد جدول لهذه التواريخ.';

  @override
  String get noShift => 'بدون وردية';

  @override
  String get workingDay => 'يوم عمل';

  @override
  String get leaveHistoryTitle => 'سجل الإجازات';

  @override
  String get newLeaveRequest => 'طلب إجازة جديد';

  @override
  String get noLeaveTypes => 'لا توجد أنواع إجازات متاحة لك.';

  @override
  String get notTracked => 'بدون رصيد';

  @override
  String get entitlement => 'المستحق';

  @override
  String get carriedIn => 'المرحّل';

  @override
  String get used => 'المستخدم';

  @override
  String get pending => 'قيد الانتظار';

  @override
  String get leaveType => 'نوع الإجازة';

  @override
  String get dayPartFull => 'يوم كامل';

  @override
  String get dayPartFirstHalf => 'النصف الأول';

  @override
  String get dayPartSecondHalf => 'النصف الثاني';

  @override
  String get dayPartHours => 'ساعات';

  @override
  String get startDate => 'من';

  @override
  String get endDate => 'إلى';

  @override
  String get date => 'التاريخ';

  @override
  String get hours => 'عدد الساعات';

  @override
  String get reasonOptional => 'السبب (اختياري)';

  @override
  String get attachments => 'المرفقات';

  @override
  String get addAttachment => 'إضافة ملف';

  @override
  String get attachmentRequiredHint =>
      'يتطلب هذا النوع من الإجازة مستنداً داعماً.';

  @override
  String get attachmentRequired => 'يلزم إرفاق مستند داعم.';

  @override
  String get previewDays => 'احسب الأيام';

  @override
  String get previewTitle => 'معاينة';

  @override
  String get daysCounted => 'الأيام المحتسبة';

  @override
  String get daysNotCounted => 'أيام غير محتسبة';

  @override
  String get submitRequest => 'إرسال الطلب';

  @override
  String get leaveSubmitted => 'تم إرسال طلب الإجازة للاعتماد.';

  @override
  String get noLeaveRequests => 'ليست لديك طلبات إجازة.';

  @override
  String get leaveRequestTitle => 'طلب إجازة';

  @override
  String get duration => 'المدة';

  @override
  String get currentStep => 'المرحلة الحالية';

  @override
  String get reason => 'السبب';

  @override
  String get rejectionReason => 'سبب الرفض';

  @override
  String get submittedOn => 'تاريخ الإرسال';

  @override
  String get dayBreakdown => 'تفاصيل الأيام';

  @override
  String get notCounted => 'غير محتسب';

  @override
  String get approvalTrail => 'مسار الاعتماد';

  @override
  String get noAttachments => 'لا توجد مرفقات.';

  @override
  String get attachmentsUploaded => 'تم رفع الملفات.';

  @override
  String get cancelRequest => 'إلغاء الطلب';

  @override
  String get requestCancellation => 'طلب الإلغاء';

  @override
  String get cancelLeaveConfirm => 'هل تريد إلغاء طلب الإجازة هذا؟';

  @override
  String get requestCancelled => 'تم إلغاء الطلب.';

  @override
  String get payslipsTitle => 'قسائم الرواتب';

  @override
  String get noPayslips => 'لا توجد قسائم رواتب لهذا العام.';

  @override
  String get noPayslipsHint => 'تظهر القسائم هنا بعد إصدار الرواتب.';

  @override
  String get gross => 'الإجمالي';

  @override
  String get deductions => 'الاستقطاعات';

  @override
  String get loanBalance => 'رصيد السلف';

  @override
  String get salaryBreakdown => 'تفاصيل الراتب';

  @override
  String get downloadPdf => 'تنزيل PDF';

  @override
  String get earnings => 'الاستحقاقات';

  @override
  String get statutory => 'الاستقطاعات القانونية';

  @override
  String get loanRepayments => 'أقساط السلف';

  @override
  String get totals => 'الإجماليات';

  @override
  String get totalDeductions => 'إجمالي الاستقطاعات';

  @override
  String get netPay => 'صافي الراتب';

  @override
  String get payDate => 'تاريخ الصرف';

  @override
  String get demoStatutoryNotice =>
      'المبالغ القانونية محسوبة بقواعد تجريبية وللتوضيح فقط.';

  @override
  String get loansTitle => 'السلف والقروض';

  @override
  String get newLoanRequest => 'طلب جديد';

  @override
  String get yourLimits => 'الحدود المتاحة لك';

  @override
  String get loansDisabled => 'طلبات السلف مغلقة حالياً.';

  @override
  String get noLoans => 'ليست لديك سلف أو قروض.';

  @override
  String get requestedAmount => 'المبلغ المطلوب';

  @override
  String get approvedAmount => 'المبلغ المعتمد';

  @override
  String get instalments => 'الأقساط';

  @override
  String get outstanding => 'المتبقي';

  @override
  String get amount => 'المبلغ';

  @override
  String get startMonth => 'شهر أول استقطاع';

  @override
  String get loanSubmitted => 'تم إرسال طلبك للاعتماد.';

  @override
  String get cancelLoanConfirm => 'هل تريد إلغاء طلب السلفة هذا؟';

  @override
  String get hrRequestsTitle => 'طلبات الموارد البشرية';

  @override
  String get newRequest => 'طلب جديد';

  @override
  String get tabOpen => 'مفتوحة';

  @override
  String get tabClosed => 'مغلقة';

  @override
  String get noOpenRequests => 'لا توجد طلبات مفتوحة.';

  @override
  String get noClosedRequests => 'لا توجد طلبات مغلقة.';

  @override
  String get requestsDisabled => 'طلبات الموارد البشرية غير متاحة.';

  @override
  String get category => 'الفئة';

  @override
  String get subjectOptional => 'الموضوع (اختياري)';

  @override
  String get letterLanguage => 'لغة الخطاب';

  @override
  String get arabic => 'العربية';

  @override
  String get english => 'English';

  @override
  String get bothLanguages => 'كلتاهما';

  @override
  String get description => 'الوصف';

  @override
  String get notesOptional => 'ملاحظات (اختياري)';

  @override
  String get profileChangeHint =>
      'املأ الحقول التي تريد تغييرها فقط. تراجع الموارد البشرية الطلب قبل أي تحديث.';

  @override
  String get profileChangeEmpty => 'أدخل قيمة جديدة واحدة على الأقل.';

  @override
  String get requestSubmitted => 'تم إرسال طلبك.';

  @override
  String get requestDetailsTitle => 'تفاصيل الطلب';

  @override
  String get reference => 'المرجع';

  @override
  String get priority => 'الأولوية';

  @override
  String get resolution => 'الحل';

  @override
  String get conversation => 'المحادثة';

  @override
  String get noMessages => 'لا توجد رسائل بعد.';

  @override
  String get you => 'أنت';

  @override
  String get writeReply => 'اكتب رداً…';

  @override
  String get replySent => 'تم إرسال الرد.';

  @override
  String get history => 'السجل';

  @override
  String get cancelRequestConfirm => 'هل تريد إلغاء هذا الطلب؟';

  @override
  String get documentsTitle => 'المستندات';

  @override
  String get noDocuments => 'لا توجد مستندات مشاركة معك بعد.';

  @override
  String get docsShared => 'مستنداتي';

  @override
  String get docsContracts => 'العقود';

  @override
  String get docsCertificates => 'الشهادات';

  @override
  String get docsLetters => 'الخطابات';

  @override
  String get expired => 'منتهي';

  @override
  String get announcementsTitle => 'الإعلانات';

  @override
  String get noAnnouncements => 'لا توجد إعلانات.';

  @override
  String get needsAcknowledgement => 'يتطلب إقراراً';

  @override
  String get unread => 'جديد';

  @override
  String get acknowledge => 'قرأت وفهمت';

  @override
  String get acknowledged => 'تم الإقرار.';

  @override
  String get notificationsTitle => 'الإشعارات';

  @override
  String get noNotifications => 'ليست لديك إشعارات.';

  @override
  String get markAllRead => 'تعليم الكل كمقروء';

  @override
  String get profileTitle => 'الملف الشخصي';

  @override
  String get employment => 'بيانات العمل';

  @override
  String get status => 'الحالة';

  @override
  String get hireDate => 'تاريخ التعيين';

  @override
  String get branch => 'الفرع';

  @override
  String get department => 'القسم';

  @override
  String get position => 'المنصب';

  @override
  String get jobGrade => 'الدرجة';

  @override
  String get employmentType => 'نوع التوظيف';

  @override
  String get workLocation => 'موقع العمل';

  @override
  String get manager => 'المدير';

  @override
  String get contact => 'التواصل';

  @override
  String get workEmail => 'البريد الإلكتروني للعمل';

  @override
  String get personalEmail => 'البريد الإلكتروني الشخصي';

  @override
  String get phone => 'الهاتف';

  @override
  String get address => 'العنوان';

  @override
  String get city => 'المدينة';

  @override
  String get personal => 'البيانات الشخصية';

  @override
  String get dateOfBirth => 'تاريخ الميلاد';

  @override
  String get nationalId => 'الرقم القومي';

  @override
  String get nationality => 'الجنسية';

  @override
  String get emergencyContacts => 'جهات اتصال الطوارئ';

  @override
  String get requestProfileChange => 'طلب تعديل';

  @override
  String get settingsTitle => 'الإعدادات';

  @override
  String get language => 'اللغة';

  @override
  String get theme => 'المظهر';

  @override
  String get themeSystem => 'النظام';

  @override
  String get themeLight => 'فاتح';

  @override
  String get themeDark => 'داكن';

  @override
  String get digits => 'الأرقام';

  @override
  String get digitsWestern => '0123';

  @override
  String get digitsArabicIndic => '٠١٢٣';

  @override
  String get notificationPreferences => 'تفضيلات الإشعارات';

  @override
  String get preferencesSaved => 'تم حفظ التفضيلات.';

  @override
  String get mandatoryNotification => 'يُرسل دائماً';

  @override
  String get pushDisabled =>
      'الإشعارات الفورية غير مهيأة في هذا الإصدار. ستظل ترى الإشعارات داخل التطبيق.';

  @override
  String acknowledgedOn(String date) {
    return 'تم الإقرار في $date';
  }

  @override
  String appVersion(String version) {
    return 'الإصدار $version';
  }

  @override
  String balanceAfter(String year) {
    return 'الرصيد بعد الطلب ($year)';
  }

  @override
  String currentValue(String value) {
    return 'الحالي: $value';
  }

  @override
  String daysValue(String days) {
    return '$days يوم';
  }

  @override
  String distanceFromLocation(String meters, String location) {
    return 'على بعد $meters م من $location';
  }

  @override
  String enrolledOn(String date) {
    return 'مسجّل في $date';
  }

  @override
  String faceConsentBody(String version) {
    return 'يقارن التحقق بالوجه صورة تُلتقط عند تسجيل الحضور بوجهك المسجّل للتأكد من هويتك. تُحفظ البيانات الحيوية مشفرة وتُستخدم للحضور فقط، وتُحذف عند سحب الموافقة أو ترك الشركة. نسخة نص الموافقة $version.';
  }

  @override
  String gpsAccuracy(String meters) {
    return 'GPS ±$meters م';
  }

  @override
  String greetingName(String greeting, String name) {
    return '$greeting، $name';
  }

  @override
  String holidayToday(String name) {
    return 'عطلة: $name';
  }

  @override
  String insideLocation(String location) {
    return 'داخل نطاق $location';
  }

  @override
  String issueOutsideBody(String distance, String location, String radius) {
    return 'أنت على بعد $distance م من $location، والنطاق المسموح $radius م. اقترب ثم أعد المحاولة.';
  }

  @override
  String issuePoorAccuracyBody(String accuracy, String max) {
    return 'الدقة الحالية ±$accuracy م، والمطلوب $max م كحد أقصى. انتقل إلى مكان مفتوح ثم أعد المحاولة.';
  }

  @override
  String lateBy(String minutes) {
    return 'تأخير $minutes د';
  }

  @override
  String loanLimitValue(String amount, String count) {
    return 'حتى $amount · $count قسط';
  }

  @override
  String maxAmount(String amount) {
    return 'الحد الأقصى $amount';
  }

  @override
  String maxInstalments(String count) {
    return 'حتى $count';
  }

  @override
  String metersAway(String meters) {
    return 'على بعد $meters م';
  }

  @override
  String metersShort(String meters) {
    return '$meters م';
  }

  @override
  String netThisYear(String year) {
    return 'صافي رواتب $year';
  }

  @override
  String noticeDays(String days) {
    return 'قدّم الطلب قبل $days أيام على الأقل.';
  }

  @override
  String otpResendIn(String seconds) {
    return 'إعادة الإرسال بعد $seconds ث';
  }

  @override
  String otpSubtitle(String email) {
    return 'أدخل الرمز المرسل إلى $email.';
  }

  @override
  String radiusValue(String meters) {
    return 'النطاق $meters م';
  }

  @override
  String recordedAt(String time) {
    return 'سجّله الخادم في $time.';
  }

  @override
  String shiftRange(String name, String start, String end) {
    return 'وردية $name $start – $end';
  }

  @override
  String validationInstalments(String max) {
    return 'أدخل من 1 إلى $max أقساط.';
  }

  @override
  String pendingReviews(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count محاولة حضور قيد المراجعة',
      many: '$count محاولة حضور قيد المراجعة',
      few: '$count محاولات حضور قيد المراجعة',
      two: 'محاولتا حضور قيد المراجعة',
      one: 'محاولة حضور واحدة قيد المراجعة',
      zero: 'لا توجد محاولات قيد المراجعة',
    );
    return '$_temp0';
  }

  @override
  String validationPasswordLength(int min) {
    String _temp0 = intl.Intl.pluralLogic(
      min,
      locale: localeName,
      other: 'استخدم $min أحرف على الأقل.',
    );
    return '$_temp0';
  }

  @override
  String otpInvalidLength(int length) {
    String _temp0 = intl.Intl.pluralLogic(
      length,
      locale: localeName,
      other: 'أدخل الرمز المكوّن من $length أرقام.',
    );
    return '$_temp0';
  }
}
