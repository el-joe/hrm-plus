<?php

/*
|--------------------------------------------------------------------------
| Recruitment (Phase 4)
|--------------------------------------------------------------------------
|
| Technical constants and the seed of the default pipeline. Business rules the
| client may change (career page on/off, CV size and types, reminder lead
| time, offer approval) are system settings (config/settings.php, group
| "recruitment"); the pipeline itself is edited under Recruitment → Pipeline.
|
*/

return [

    // Private disk for CVs and candidate attachments (config/filesystems.php).
    'disk' => 'recruitment_files',

    // Scorecards rate every criterion from 1 to this value.
    'score_scale' => 5,

    // Largest attachment accepted on a candidate (KB) — CVs use the setting.
    'attachment_max_kb' => 10240,

    /*
     | The company default pipeline (spec module 4), created by
     | RecruitmentStageSeeder when no default stage exists yet.
     | [code, name_ar, name_en, category, notify_candidate]
     */
    'default_stages' => [
        ['new', 'طلب جديد', 'New application', 'open', false],
        ['cv_review', 'مراجعة السيرة الذاتية', 'CV review', 'open', false],
        ['shortlisted', 'القائمة المختصرة', 'Shortlisted', 'open', true],
        ['screening', 'المقابلة الأولية', 'Initial screening', 'interview', false],
        ['interview_scheduled', 'تم تحديد مقابلة', 'Interview scheduled', 'interview', false],
        ['technical_interview', 'المقابلة الفنية', 'Technical interview', 'interview', false],
        ['hr_interview', 'مقابلة الموارد البشرية', 'HR interview', 'interview', false],
        ['final_interview', 'المقابلة النهائية', 'Final interview', 'interview', false],
        ['offer_sent', 'تم إرسال العرض', 'Offer sent', 'offer', false],
        ['hired', 'تم التعيين', 'Hired', 'hired', true],
        ['rejected', 'مرفوض', 'Rejected', 'rejected', true],
        ['withdrawn', 'انسحب', 'Withdrawn', 'withdrawn', false],
    ],

    // Reference numbers of vacancies and offers (number_sequences keys).
    'references' => [
        'vacancy' => ['prefix' => 'VAC-', 'padding' => 5],
        'offer' => ['prefix' => 'OFR-', 'padding' => 5],
    ],

];
