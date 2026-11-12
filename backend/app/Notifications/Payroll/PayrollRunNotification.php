<?php

namespace App\Notifications\Payroll;

use App\Models\PayrollRun;
use App\Notifications\CentreNotification;

/**
 * Payroll run events to admins (templates "payroll_run_*"): awaiting_approval, approved, rejected, finalised.
 */
class PayrollRunNotification extends CentreNotification
{
    public const KINDS = ['awaiting_approval', 'approved', 'rejected', 'finalised'];

    public function __construct(public readonly PayrollRun $run, public readonly string $kind, public readonly ?string $reason = null) {}

    public function event(): string
    {
        return 'payroll_run_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'payroll_runs.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $run = $this->run->loadMissing('period');

        return [
            'reference' => $run->reference,
            'name' => $run->name,
            'period' => $run->period->localized('name', $locale),
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{kind: string, payroll_run_id: int, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        return [
            'kind' => 'payroll_run_'.$this->kind,
            'payroll_run_id' => $this->run->id,
            'reason' => $this->reason,
            'url' => route('admin.payroll-runs.show', $this->run),
        ];
    }
}
