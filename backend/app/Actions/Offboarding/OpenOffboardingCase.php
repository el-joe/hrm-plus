<?php

namespace App\Actions\Offboarding;

use App\Enums\OffboardingSource;
use App\Enums\OffboardingStatus;
use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Services\Offboarding\Contracts\HandoverItemProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Open an offboarding case for an employee (resignation, dismissal,
 * contract end, ...) and fill its handover checklist from the registered
 * providers. One open case per employee.
 */
class OpenOffboardingCase
{
    /**
     * @param  array{type: string, notice_date?: string|null, last_working_date: string, reason?: string|null, eligible_for_rehire?: bool}  $data
     */
    public function handle(Employee $employee, array $data, ?User $actor = null, OffboardingSource $source = OffboardingSource::Manual, ?EmployeeContract $contract = null): OffboardingCase
    {
        if ($employee->isTerminated()) {
            throw ValidationException::withMessages(['employee_id' => __('onboarding.validation.employee_terminated')]);
        }

        return DB::transaction(function () use ($employee, $data, $actor, $source, $contract): OffboardingCase {
            $open = OffboardingCase::query()->withoutGlobalScopes()
                ->where('employee_id', $employee->getKey())->where('status', OffboardingStatus::Open)
                ->lockForUpdate()->exists();

            if ($open) {
                throw ValidationException::withMessages(['employee_id' => __('onboarding.validation.case_open')]);
            }

            $case = OffboardingCase::create([
                'employee_id' => $employee->getKey(),
                'type' => $data['type'],
                'source' => $source,
                'employee_contract_id' => $contract?->getKey(),
                'notice_date' => $data['notice_date'] ?? null,
                'last_working_date' => $data['last_working_date'],
                'reason' => $data['reason'] ?? null,
                'eligible_for_rehire' => (bool) ($data['eligible_for_rehire'] ?? true),
                'status' => OffboardingStatus::Open,
                'created_by' => $actor?->getKey(),
            ]);

            $order = 0;

            foreach (config('lifecycle.handover_providers') as $providerClass) {
                /** @var HandoverItemProvider $provider */
                $provider = app($providerClass);

                foreach ($provider->itemsFor($employee, $case) as $item) {
                    $case->items()->create([...$item, 'status' => TaskStatus::Pending, 'sort_order' => $order += 10]);
                }
            }

            return $case;
        });
    }
}
