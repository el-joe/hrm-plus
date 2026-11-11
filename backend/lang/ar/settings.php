<?php

/*
| Labels for the settings registry (config/settings.php): groups, keys and option values.
*/

return [

    'groups' => [
        'localisation' => 'اللغة والمنطقة',
        'attendance' => 'الحضور',
        'payroll' => 'الرواتب',
        'leave' => 'الإجازات',
        'loans' => 'القروض والسلف',
        'notifications' => 'الإشعارات',
        'documents' => 'المستندات',
        'employee_number' => 'رقم الموظف',
        'security' => 'الأمان',
        'approvals' => 'الاعتمادات',
        'recruitment' => 'التوظيف',
        'lifecycle' => 'الانضمام وإنهاء الخدمة',
        'face_verification' => 'التحقق بالوجه',
    ],
    'keys' => [
        'loans' => [
            'employee_requests_enabled' => 'يمكن للموظفين طلب القروض والسلف',
            'min_service_months' => 'الحد الأدنى للخدمة (بالأشهر)',
            'max_active_loans' => 'عدد القروض / السلف المفتوحة لكل موظف',
            'max_loan_salary_multiple' => 'أكبر قرض (× الراتب الأساسي الشهري)',
            'max_loan_instalments' => 'أقصى عدد أقساط للقرض',
            'max_advance_percent' => 'أكبر سلفة (% من الراتب الأساسي الشهري)',
            'max_advance_instalments' => 'أقصى عدد أقساط للسلفة',
            'max_instalment_percent' => 'أكبر قسط (% من الراتب الأساسي الشهري)',
        ],
        'localisation' => [
            'default_locale' => 'اللغة الافتراضية',
            'timezone' => 'المنطقة الزمنية',
            'date_format' => 'صيغة التاريخ',
            'time_format' => 'صيغة الوقت',
            'currency_code' => 'رمز العملة (ISO)',
            'currency_decimals' => 'المنازل العشرية للعملة',
            'use_arabic_digits' => 'استخدام الأرقام العربية الهندية',
            'first_day_of_week' => 'أول أيام الأسبوع',
        ],
        'attendance' => [
            'working_days' => 'أيام العمل',
            'late_grace_minutes' => 'فترة السماح للتأخير (دقائق)',
            'early_leave_grace_minutes' => 'فترة السماح للانصراف المبكر (دقائق)',
            'overtime_minimum_minutes' => 'الحد الأدنى للعمل الإضافي (دقائق)',
            'geofence_default_radius_meters' => 'نصف قطر النطاق الجغرافي الافتراضي (متر)',
            'gps_max_accuracy_meters' => 'أقل دقة GPS مقبولة (متر)',
            'face_verification_required' => 'اشتراط التحقق بالوجه عند التسجيل من الجوال',
            'face_verification_max_retries' => 'عدد محاولات التحقق بالوجه',
            'manual_entry_requires_approval' => 'الحضور اليدوي يتطلب اعتماداً',
            'gps_accuracy_tolerance_meters' => 'دقة GPS المضافة إلى نصف القطر (م، افتراضي للسياسات الجديدة)',
            'gps_max_time_drift_seconds' => 'فرق ساعة الهاتف المسموح (ثانية)',
            'gps_repeated_attempts_threshold' => 'المحاولات المرفوضة في الساعة قبل المراجعة',
            'device_stale_after_hours' => 'الجهاز بلا اتصال لمدة (ساعات) يحتاج انتباهًا',
        ],
        'payroll' => [
            'period_start_day' => 'يوم بداية فترة الرواتب',
            'working_days_per_month' => 'أيام العمل في الشهر',
            'working_hours_per_day' => 'ساعات العمل في اليوم',
            'overtime_rate_multiplier' => 'معامل أجر العمل الإضافي',
            'rounding_precision' => 'منازل التقريب العشرية',
            'country_code' => 'دولة القواعد النظامية (رمز ISO، فارغ = لا شيء)',
        ],
        'leave' => [
            'year_start_month' => 'شهر بداية سنة الإجازات',
            'allow_half_day' => 'السماح بإجازة نصف يوم',
            'allow_negative_balance' => 'السماح برصيد سالب',
            'count_weekends_as_leave' => 'احتساب أيام الراحة الأسبوعية ضمن الإجازة',
            'max_past_days' => 'الحد الأقصى لطلب إجازة بتاريخ سابق (أيام)',
            'cancel_before_start_directly' => 'إلغاء الإجازة المعتمدة قبل بدئها دون موافقة',
            'calendar_conflict_threshold' => 'تقويم الإجازات: تنبيه عند غياب هذا العدد من القسم نفسه',
        ],
        'notifications' => [
            'email_enabled' => 'إرسال إشعارات البريد الإلكتروني',
            'push_enabled' => 'إرسال إشعارات الجوال',
            'contract_expiry_reminder_days' => 'تذكير انتهاء العقد (أيام قبل)',
            'document_expiry_reminder_days' => 'تذكير انتهاء المستند (أيام قبل)',
        ],
        'documents' => [
            'max_upload_size_kb' => 'الحد الأقصى لحجم الملف (كيلوبايت)',
            'allowed_extensions' => 'أنواع الملفات المسموحة',
        ],
        'employee_number' => [
            'pattern' => 'النمط ({prefix}، {number}، {year}، {branch})',
            'prefix' => 'البادئة',
            'padding' => 'عدد الخانات',
            'next_sequence' => 'الرقم التالي',
        ],
        'security' => [
            'password_min_length' => 'الحد الأدنى لطول كلمة المرور',
        ],
        'approvals' => [
            'allow_self_approval' => 'السماح باعتماد الشخص لطلبه',
        ],
        'recruitment' => [
            'career_page_enabled' => 'تفعيل صفحة الوظائف العامة',
            'career_applications_per_hour' => 'عدد الطلبات المسموح بها لكل زائر في الساعة',
            'cv_max_size_kb' => 'أقصى حجم للسيرة الذاتية (كيلوبايت)',
            'cv_allowed_extensions' => 'أنواع ملفات السيرة الذاتية المسموح بها',
            'candidate_emails_enabled' => 'إرسال رسائل بريد للمرشحين',
            'interview_reminder_hours' => 'التذكير بالمقابلة (ساعات قبلها)',
            'offer_requires_approval' => 'العروض الوظيفية تتطلب اعتمادًا',
            'offer_validity_days' => 'مدة صلاحية العرض الافتراضية (أيام)',
        ],
        'lifecycle' => [
            'auto_start_onboarding' => 'بدء إجراءات الانضمام تلقائيًا عند تعيين مرشح',
            'contract_expiry_case_days' => 'فتح ملفات إنهاء الخدمة للعقود المنتهية خلال (أيام)',
        ],
        'face_verification' => [
            'provider' => 'مزوّد التحقق بالوجه',
            'consent_version' => 'إصدار نص الموافقة',
            'enrolment_max_age_days' => 'إعادة التسجيل بعد (يوم)',
            'verification_log_retention_days' => 'الاحتفاظ بنتائج التحقق (يوم)',
            'store_enrolment_image' => 'الاحتفاظ بصورة تسجيل مشفّرة',
        ],
    ],
    'options' => [
        'saturday' => 'السبت',
        'sunday' => 'الأحد',
        'monday' => 'الاثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'ar' => 'العربية',
        'en' => 'English',
        'simulated' => 'محاكاة (للتطوير فقط)',
    ],

];
