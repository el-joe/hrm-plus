<?php

namespace App\Enums;

/**
 * Admin screen an onboarding checklist task links to, so the person doing the
 * task lands where the work is done.
 */
enum OnboardingTaskAction: string
{
    case AssignSchedule = 'assign_schedule';
    case IssueAssets = 'issue_assets';

    public function label(): string
    {
        return __('onboarding.task_actions.'.$this->value);
    }

    /**
     * Route of the screen for the given employee (and task, so the screen can
     * complete it), and the permission it needs.
     *
     * @return array{route: string, parameters: array<string, mixed>, ability: string}
     */
    public function target(int $employeeId, ?int $taskId = null): array
    {
        return match ($this) {
            self::AssignSchedule => [
                'route' => 'admin.schedule-assignments.create',
                'parameters' => ['scope_type' => AttendanceScope::Employee->value, 'scope_id' => $employeeId],
                'ability' => 'schedules.create',
            ],
            // Phase 8a: the asset handover form; saving it marks the task done.
            self::IssueAssets => [
                'route' => 'admin.asset-assignments.create',
                'parameters' => array_filter(['employee' => $employeeId, 'onboarding_task' => $taskId]),
                'ability' => 'assets.update',
            ],
        };
    }
}
