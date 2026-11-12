<?php

namespace App\Notifications\Lifecycle;

use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Notifications\CentreNotification;

/**
 * An offboarding case was opened for an ending contract (template "offboarding_opened").
 */
class OffboardingCaseOpened extends CentreNotification
{
    public function __construct(public readonly OffboardingCase $case) {}

    public function event(): string
    {
        return 'offboarding_opened';
    }

    protected function actionLabelKey(): string
    {
        return 'onboarding.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $employee = $this->employee();

        return [
            'employee' => $employee->employee_number.' · '.$employee->localized('name', $locale),
            'date' => $this->case->last_working_date->toDateString(),
        ];
    }

    /**
     * @return array{kind: string, offboarding_case_id: int, employee_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => 'offboarding_opened',
            'offboarding_case_id' => $this->case->getKey(),
            'employee_id' => $this->case->employee_id,
            'url' => route('admin.offboarding.show', $this->case),
        ];
    }

    private function employee(): Employee
    {
        return $this->case->employee()->withoutGlobalScopes()->firstOrFail();
    }
}
