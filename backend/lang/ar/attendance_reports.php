<?php

/*
| لوحة الحضور والتقويم والتقارير (المرحلة 5ب).
*/

return [

    'categories' => [
        'present' => 'حاضر',
        'late' => 'متأخر',
        'absent' => 'غائب',
        'on_leave' => 'في إجازة',
        'not_in' => 'لم يحضر بعد',
    ],

    'dashboard' => [
        'title' => 'لوحة الحضور',
        'subtitle' => 'من حضر اليوم، حسب الفرع والقسم، من بيانات حية.',
        'expected' => 'المجدولون',
        'rate' => 'نسبة الحضور :rate%',
        'by_branch' => 'حسب الفرع',
        'by_department' => 'حسب القسم',
        'breakdown' => 'التوزيع',
        'nobody_scheduled' => 'لا أحد مجدول للعمل في هذا التاريخ.',
        'latest_punches' => 'أحدث البصمات',
        'latest_help' => 'آخر 24 ساعة، من كل المصادر.',
        'no_punches' => 'لا توجد بصمات في آخر 24 ساعة.',
        'pending' => 'بانتظار إجراء',
        'pending_items' => [
            'reviews' => 'محاولات جوال للمراجعة',
            'corrections' => 'طلبات التصحيح',
            'batches' => 'دفعات يدوية بانتظار الاعتماد',
            'overtime' => 'عمل إضافي بانتظار الاعتماد',
            'unmatched' => 'أحداث أجهزة غير مربوطة',
        ],
        'devices' => 'حالة الأجهزة',
        'not_in_list' => 'لم يحضروا بعد',
        'starts' => 'الوردية :time',
    ],

    'calendar' => [
        'title' => 'تقويم الحضور',
        'subtitle' => 'شهر لكل موظف، أو نظرة سريعة على قسم / فرع.',
        'month' => 'الشهر',
        'previous' => 'السابق',
        'next' => 'التالي',
        'team' => 'شهر الفريق',
        'pick' => 'اختر موظفًا أو قسمًا أو فرعًا',
        'pick_help' => 'يعرض التقويم الأيام المحتسبة بجانب الجدول المتوقع.',
        'short' => ['present' => 'ح', 'late' => 'م', 'absent' => 'غ', 'on_leave' => 'ج', 'incomplete' => 'ن', 'holiday' => 'ع', 'rest_day' => 'ر'],
        'legend' => [
            'present' => 'حاضر', 'late' => 'متأخر', 'absent' => 'غائب', 'on_leave' => 'إجازة', 'incomplete' => 'ناقص',
            'holiday' => 'عطلة رسمية', 'scheduled' => 'مجدول، لم يُحتسب', 'rest' => 'يوم راحة',
        ],
    ],

    'index' => [
        'title' => 'تقارير الحضور',
        'subtitle' => 'فلترة ومراجعة وتصدير (Excel / CSV). تُعرض البيانات ضمن نطاق صلاحياتك فقط.',
    ],

    'reports' => [
        'daily' => ['title' => 'الحضور اليومي', 'description' => 'كل يوم محتسب بالأوقات والدقائق والحالة والمصدر.'],
        'monthly_summary' => ['title' => 'الملخص الشهري', 'description' => 'لكل موظف: الأيام حسب الحالة وإجمالي الدقائق خلال الفترة.'],
        'lateness' => ['title' => 'التأخير', 'description' => 'أيام التأخر مقارنة ببداية الوردية.'],
        'absence' => ['title' => 'الغياب', 'description' => 'الأيام المجدولة بدون حضور.'],
        'overtime' => ['title' => 'العمل الإضافي', 'description' => 'دقائق العمل الإضافي وحالة اعتمادها ومعامل السياسة للرواتب.'],
        'manual_entries' => ['title' => 'الإدخالات اليدوية', 'description' => 'الحضور المُدخل يدويًا أو المستورد ومن سجّله.'],
        'device_sync' => ['title' => 'مزامنة الأجهزة', 'description' => 'كل عملية مزامنة للأجهزة مع العدادات والأخطاء.'],
    ],

    'columns' => [
        'manual' => 'يدوي',
        'scheduled_days' => 'الأيام المجدولة',
        'present_days' => 'حضور',
        'late_days' => 'تأخير',
        'absent_days' => 'غياب',
        'leave_days' => 'إجازة',
        'incomplete_days' => 'ناقص',
        'manual_days' => 'أيام يدوية',
        'scheduled_start' => 'بداية الوردية',
        'flags' => 'ملاحظات',
        'day_type' => 'نوع اليوم',
        'overtime_status' => 'حالة العمل الإضافي',
        'rate' => 'المعامل',
        'recorded_by' => 'سجّله',
        'recorded_at' => 'وقت التسجيل',
        'note' => 'ملاحظة',
        'device' => 'الجهاز',
        'serial' => 'الرقم التسلسلي',
        'sync_type' => 'النوع',
        'started_at' => 'البدء',
        'fetched' => 'مقروءة',
        'created' => 'جديدة',
        'duplicates' => 'مكررة',
        'unmatched' => 'غير مربوطة',
        'errors' => 'أخطاء',
        'simulated' => 'محاكاة',
        'message' => 'الرسالة',
    ],

    'day_types' => ['regular' => 'يوم عمل', 'rest_day' => 'يوم راحة', 'holiday' => 'عطلة رسمية'],

    'export_xlsx' => 'Excel',
    'export_csv' => 'CSV',
    'empty' => 'لا توجد صفوف لهذه الفلاتر',
    'empty_help' => 'غيّر الفترة أو الفلاتر.',
    'range_too_long' => 'لا يمكن أن يغطي التقرير أكثر من :days يومًا.',

];
