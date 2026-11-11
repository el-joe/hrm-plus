<?php

namespace App\Actions\Employees;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * The employee's own login (portal + mobile app): an "employee" account
 * linked to the profile. It is created with an unusable random password and
 * the employee sets their own through the standard reset link (invitation),
 * so nobody else ever knows it.
 */
class ManageEmployeeAccount
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Employee $employee, string $email, ?string $locale = null): User
    {
        $user = DB::transaction(function () use ($employee, $email, $locale): User {
            $user = new User(['name' => $employee->name_en ?: $employee->name_ar, 'email' => $email, 'locale' => $locale]);
            $user->forceFill([
                'account_type' => AccountType::Employee,
                'employee_id' => $employee->getKey(),
                'status' => $employee->isTerminated() ? UserStatus::Inactive : UserStatus::Active,
                'password' => Str::password(40),
            ])->save();

            return $user;
        });

        $this->invite($user);

        return $user;
    }

    /**
     * Send (or resend) the "set your password" link.
     */
    public function invite(User $user): string
    {
        $status = Password::sendResetLink(['email' => $user->email]);
        $this->audit->log('employee_account.invited', $user, meta: ['status' => $status]);

        return $status;
    }

    public function setStatus(User $user, UserStatus $status): void
    {
        $user->update(['status' => $status]);
    }
}
