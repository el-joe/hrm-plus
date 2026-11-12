<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an employee's bank account (audited by the model). The first account, or one
 * marked primary, becomes the primary account and the previous primary loses the flag. Used by the
 * financial tab and by approved profile change requests (Phase 8b).
 */
class SaveEmployeeBankAccount
{
    /**
     * @param  array{bank_name?: string, account_holder?: string, account_number?: ?string, iban?: ?string, swift_code?: ?string}  $data  validated, normalised values
     */
    public function handle(Employee $employee, EmployeeBankAccount $account, array $data, bool $makePrimary = false): EmployeeBankAccount
    {
        return DB::transaction(function () use ($employee, $account, $data, $makePrimary): EmployeeBankAccount {
            $isPrimary = $makePrimary || ! $employee->bankAccounts()->whereKeyNot($account->getKey() ?? 0)->exists();

            $account->fill([...$data, 'is_primary' => $isPrimary, 'employee_id' => $employee->getKey()])->save();

            if ($isPrimary) {
                $employee->bankAccounts()->whereKeyNot($account->getKey())->where('is_primary', true)->get()
                    ->each(fn (EmployeeBankAccount $other) => $other->update(['is_primary' => false]));
            }

            return $account;
        });
    }
}
