<?php

return [
    'widgets' => [
        'total_employees' => ['title' => 'إجمالي الموظفين'],
        'active_employees' => ['title' => 'الموظفون النشطون'],
        'inactive_employees' => ['title' => 'الموظفون غير النشطين'],
        'employees_on_leave' => ['title' => 'الموظفون في إجازة'],
        'absent_today' => ['title' => 'الغائبون اليوم'],
        'present_now' => ['title' => 'الموجودون حاليًا'],
        'late_arrivals' => ['title' => 'التأخيرات'],
        'pending_leave_requests' => ['title' => 'طلبات الإجازة المعلقة'],
        'pending_hr_requests' => ['title' => 'طلبات الموارد البشرية المعلقة'],
        'new_candidates' => ['title' => 'المرشحون الجدد'],
        'open_vacancies' => ['title' => 'الوظائف الشاغرة المفتوحة'],
        'contract_expirations' => ['title' => 'العقود القريبة من الانتهاء'],
        'upcoming_birthdays' => ['title' => 'أعياد الميلاد القادمة'],
        'upcoming_anniversaries' => ['title' => 'ذكرى التعيين القادمة'],
        'payroll_summary' => ['title' => 'ملخص الرواتب'],
        'monthly_salary_expenses' => ['title' => 'مصروفات الرواتب الشهرية'],
        'branch_comparison' => ['title' => 'مقارنة الفروع'],
        'department_comparison' => ['title' => 'مقارنة الإدارات'],
        'recruitment_statistics' => ['title' => 'إحصاءات التوظيف'],
        'attendance_trends' => ['title' => 'اتجاهات الحضور'],
        'employee_turnover' => ['title' => 'معدل دوران الموظفين'],
        'employee_distribution' => ['title' => 'توزيع الموظفين'],
        'payroll_pending' => ['title' => 'مسيرات رواتب بانتظار الاعتماد'],
        'active_admins' => ['title' => 'حسابات المشرفين النشطة'],
        'roles' => ['title' => 'الأدوار'],
        'audit_today' => ['title' => 'أحداث التدقيق اليوم'],
        'failed_logins' => ['title' => 'محاولات الدخول الفاشلة'],
    ],

    'actions' => [
        'refresh' => 'تحديث',
        'customise' => 'تخصيص',
        'details' => 'التفاصيل',
    ],

    'hints' => [
        'today' => 'اليوم',
        'as_of_today' => 'حتى اليوم — لا ينطبق نطاق التاريخ.',
        'of_scheduled' => 'من :count مجدولين اليوم',
        'came_in_today' => 'حضر اليوم :count',
        'today_count' => 'اليوم :count',
        'overdue' => 'متأخر :count',
        'in_period' => 'خلال الفترة المحددة',
        'openings_left' => 'متبقٍ :count شاغر',
        'next_days' => 'خلال :days يومًا القادمة',
        'employees_paid' => 'صُرف لـ :count موظف',
    ],

    'labels' => [
        'hires_in_period' => 'المعيَّنون في الفترة',
        'turnover_12_months' => 'معدل الدوران (12 شهرًا)',
        'years_of_service' => ':count سنة',
        'today' => 'اليوم',
    ],

    'empty' => [
        'none' => 'لا يوجد ما يُعرض.',
        'no_data' => 'لا توجد بيانات لهذه المرشحات.',
        'no_finalised_payroll' => 'لا توجد رواتب معتمدة نهائيًا بعد.',
        'no_widgets' => 'كل العناصر مخفية',
        'no_widgets_help' => 'اختر العناصر التي تظهر في لوحة التحكم.',
        'no_permitted_widgets' => 'لا تشمل صلاحياتك أي عنصر من عناصر لوحة التحكم.',
    ],

    'chart' => [
        'show_data' => 'عرض جدول البيانات',
        'category' => 'الفئة',
    ],

    'customise' => [
        'title' => 'تخصيص لوحة التحكم',
        'subtitle' => 'اختر العناصر التي تراها وترتيبها. تظهر فقط العناصر التي تسمح بها صلاحياتك.',
        'widgets' => 'العناصر',
        'help' => 'ألغِ تحديد عنصر لإخفائه، وغيّر أرقام الترتيب (أو استخدم الأسهم) لإعادة الترتيب.',
        'position' => 'الترتيب',
        'move_up' => 'تحريك لأعلى',
        'move_down' => 'تحريك لأسفل',
        'reset' => 'استعادة الترتيب الافتراضي',
        'saved' => 'تم حفظ ترتيب لوحة التحكم.',
        'reset_done' => 'تمت استعادة الترتيب الافتراضي للوحة التحكم.',
    ],
];
