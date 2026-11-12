<?php

use App\Services\Assets\AssetClearanceHandover;
use App\Services\Offboarding\GenericHandoverChecklist;
use App\Services\Payroll\FinalSettlementHandover;
use App\Services\Payroll\LoanSettlementHandover;

/*
|--------------------------------------------------------------------------
| Onboarding & offboarding (Phase 4)
|--------------------------------------------------------------------------
|
| Offboarding handover items come from "handover providers"
| (App\Services\Offboarding\Contracts\HandoverItemProvider). Each provider
| returns the items to add when a case is opened; the generic checklist below
| covers documents and access; the final settlement item comes from the payroll
| provider (Phase 7b); the Assets module (Phase 8a) adds one "asset" item per
| asset assigned to the employee, linked to the assignment (offboarding_items.itemable).
|
*/

return [

    'handover_providers' => [
        GenericHandoverChecklist::class,
        // Phase 7a: one finance item per open loan / advance (settle in the final pay).
        LoanSettlementHandover::class,
        // Phase 7b: the "Final financial settlement" item, linked to the case; the final
        // settlement payroll run is prepared from it and marks it done when finalised.
        FinalSettlementHandover::class,
        // Phase 8a: one "asset" clearance item per asset the employee holds, linked to the
        // assignment; recording the return on the asset completes the item.
        AssetClearanceHandover::class,
    ],

    /*
     | Generic handover checklist: [category, assignee, title_ar, title_en].
     */
    'generic_handover' => [
        // Registered assets get their own items (AssetClearanceHandover); this covers anything else.
        ['asset', 'it', 'استلام أي عهد أخرى غير مسجلة في سجل الأصول', 'Collect any other custody items not in the asset register'],
        ['document', 'hr', 'تسليم المستندات والملفات الخاصة بالعمل', 'Hand over work documents and files'],
        ['document', 'hr', 'إصدار شهادة الخبرة وخطاب إخلاء الطرف', 'Issue experience certificate and clearance letter'],
        ['access', 'it', 'إلغاء صلاحيات الأنظمة والبريد الإلكتروني', 'Revoke system and e-mail access'],
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
