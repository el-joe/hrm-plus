<?php

namespace App\Enums;

/**
 * Admin screen an onboarding checklist task links to, so the person doing the
 * task lands where the work is done.
 */
enum OnboardingTaskAction: string
{
    case AssignSchedule = 'assign_schedule';

    public function label(): string
    {
        return __('onboarding.task_actions.'.$this->value);
    }

    /**
     * Route of the screen for the given employee, and the permission it needs.
     *
     * @return array{route: string, parameters: array<string, mixed>, ability: string}
     */
    public function target(int $employeeId): array
    {
        return match ($this) {
            self::AssignSchedule => [
                'route' => 'admin.schedule-assignments.create',
                'parameters' => ['scope_type' => AttendanceScope::Employee->value, 'scope_id' => $employeeId],
                'ability' => 'schedules.create',
            ],
        };
    }
}
