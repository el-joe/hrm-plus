<?php

use App\Services\Offboarding\GenericHandoverChecklist;

/*
|--------------------------------------------------------------------------
| Onboarding & offboarding (Phase 4)
|--------------------------------------------------------------------------
|
| Offboarding handover items come from "handover providers"
| (App\Services\Offboarding\Contracts\HandoverItemProvider). Each provider
| returns the items to add when a case is opened; the generic checklist below
| covers documents, access and the final settlement. The Assets module (later
| phase) registers its own provider here so every asset assigned to the
| employee becomes an "asset" item linked to that asset (offboarding_items.itemable).
|
*/

return [

    'handover_providers' => [
        GenericHandoverChecklist::class,
    ],

    /*
     | Generic handover checklist: [category, assignee, title_ar, title_en].
     */
    'generic_handover' => [
        ['asset', 'it', 'استلام الأجهزة والعهد (حاسب، هاتف، بطاقة الدخول)', 'Return devices and custody items (laptop, phone, access card)'],
        ['document', 'hr', 'تسليم المستندات والملفات الخاصة بالعمل', 'Hand over work documents and files'],
        ['document', 'hr', 'إصدار شهادة الخبرة وخطاب إخلاء الطرف', 'Issue experience certificate and clearance letter'],
        ['access', 'it', 'إلغاء صلاحيات الأنظمة والبريد الإلكتروني', 'Revoke system and e-mail access'],
        ['finance', 'finance', 'التسوية المالية النهائية', 'Final financial settlement'],
    ],

    /*
     | Exit interview questions. "rating" answers are 1–5; "choice" answers one
     | of the options (labels in lang onboarding.exit.options.*).
     */
    'exit_interview' => [
        'primary_reason' => ['type' => 'choice', 'options' => ['compensation', 'career_growth', 'management', 'work_environment', 'relocation', 'personal', 'other']],
        'rating_management' => ['type' => 'rating'],
        'rating_environment' => ['type' => 'rating'],
        'rating_compensation' => ['type' => 'rating'],
        'rating_growth' => ['type' => 'rating'],
        'would_recommend' => ['type' => 'boolean'],
        'would_return' => ['type' => 'boolean'],
    ],

];
