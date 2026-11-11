<?php

namespace Database\Seeders;

use App\Enums\ApproverType;
use App\Enums\AttendanceScope;
use App\Enums\AttendanceStatusCategory;
use App\Enums\OnboardingTaskAction;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceManualBatch;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendanceStatus;
use App\Models\OnboardingTemplateTask;
use App\Models\ScheduleChangeRequest;
use App\Services\Attendance\AttendancePolicyResolver;
use Illuminate\Database\Seeder;

/**
 * Reference data of the attendance core (Phase 5a), seeded in every
 * environment and idempotent: the configurable day statuses of manual
 * attendance (spec module 6 D), the approval workflows for corrections,
 * manual batches and schedule changes, and a company-wide "Standard"
 * attendance policy whose first version copies the attendance settings.
 * Everything is editable in the admin area afterwards.
 */
class AttendanceDefaultsSeeder extends Seeder
{
    public function run(AttendancePolicyResolver $policies): void
    {
        foreach ([
            ['absent', 'غائب', 'Absent', AttendanceStatusCategory::Absent, false, false, true],
            ['on_leave', 'إجازة', 'On leave', AttendanceStatusCategory::OnLeave, false, true, false],
            ['official_mission', 'مأمورية رسمية', 'Official mission', AttendanceStatusCategory::Present, true, true, false],
            ['remote_work', 'عمل عن بعد', 'Remote work', AttendanceStatusCategory::Present, true, true, false],
            ['day_off', 'يوم راحة', 'Day off', AttendanceStatusCategory::RestDay, false, true, false],
            ['sick_leave', 'إجازة مرضية', 'Sick leave', AttendanceStatusCategory::OnLeave, false, true, false],
        ] as $index => [$code, $ar, $en, $category, $worked, $paid, $system]) {
            $status = AttendanceStatus::query()->firstOrNew(['code' => $code]);

            if (! $status->exists) {
                $status->fill([
                    'name_ar' => $ar, 'name_en' => $en, 'category' => $category, 'counts_as_worked' => $worked,
                    'is_paid' => $paid, 'sort_order' => ($index + 1) * 10, 'is_active' => true,
                ]);
                $status->is_system = $system;
                $status->save();
            }
        }

        foreach ([
            [AttendanceCorrection::WORKFLOW, 'Attendance correction approval', 'Corrections of attendance days.', 'attendance.approve'],
            [AttendanceManualBatch::WORKFLOW, 'Manual attendance approval', 'Bulk manual attendance and imports, when the attendance policy requires approval.', 'attendance.approve'],
            [ScheduleChangeRequest::WORKFLOW, 'Schedule change approval', 'Shift changes and swaps.', 'schedules.approve'],
        ] as [$key, $name, $description, $permission]) {
            $workflow = ApprovalWorkflow::query()->firstOrCreate(['key' => $key], ['name' => $name, 'description' => $description, 'is_active' => true]);

            if ($workflow->steps()->doesntExist()) {
                $workflow->steps()->create([
                    'sequence' => 1, 'name' => 'Approval', 'approver_type' => ApproverType::Permission, 'approver_permission' => $permission,
                ]);
            }
        }

        if (AttendancePolicy::query()->doesntExist()) {
            $policy = AttendancePolicy::create([
                'code' => 'STD', 'name_ar' => 'سياسة الحضور القياسية', 'name_en' => 'Standard attendance policy', 'is_active' => true,
                'description' => 'Company-wide default. First version copied from the attendance settings.',
            ]);
            $policy->versions()->create([...$policies->defaults()->only(AttendancePolicyVersion::RULES), 'version' => 1, 'effective_from' => '2000-01-01']);
            $policy->assignments()->create(['scope_type' => AttendanceScope::Company, 'scope_id' => null, 'effective_from' => '2000-01-01']);
        }

        // Link the default onboarding task "Assign the work schedule" to the schedule assignment screen.
        OnboardingTemplateTask::query()->whereNull('action')->where('title_en', 'Assign the work schedule')
            ->update(['action' => OnboardingTaskAction::AssignSchedule->value]);
    }
}
