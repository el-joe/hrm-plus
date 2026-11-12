<?php

namespace App\Notifications\Payroll;

use App\Models\Payslip;
use App\Notifications\CentreNotification;

/**
 * "Your payslip is ready" to the employee when a run is finalised (template "payslip_issued").
 */
class PayslipIssuedNotification extends CentreNotification
{
    public function __construct(public readonly Payslip $payslip) {}

    public function event(): string
    {
        return 'payslip_issued';
    }

    protected function actionLabelKey(): string
    {
        return 'payroll_runs.notifications.open_payslip';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $payslip = $this->payslip->loadMissing('period');

        return ['period' => $payslip->period->localized('name', $locale), 'number' => $payslip->number];
    }

    /**
     * @return array{kind: string, payslip_id: int, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => 'payslip_issued',
            'payslip_id' => $this->payslip->id,
            'url' => route('portal.payroll.show', $this->payslip->id),
        ];
    }
}
