<?php

namespace App\Services\Notifications\Automation;

use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Support\Collection;

/**
 * Who hears about an employee event: the employee's own account, their direct manager's account, and the
 * active admins holding a permission whose data scope covers the employee. Never the employee twice.
 */
class AutomationRecipients
{
    /**
     * @var array<string, Collection<int, User>>
     */
    private array $holders = [];

    public function __construct(private readonly DataScopeManager $scopes) {}

    public function accountOf(?int $employeeId): ?User
    {
        if ($employeeId === null) {
            return null;
        }

        return User::query()->where('employee_id', $employeeId)->where('status', UserStatus::Active)
            ->orderByRaw("account_type = 'employee' desc")->first();
    }

    /**
     * The direct manager's account plus admins with the permission in scope (not the employee themselves).
     *
     * @return Collection<int, User>
     */
    public function overseersOf(Employee $employee, string $permission): Collection
    {
        $recipients = collect();
        $manager = $this->accountOf($employee->manager_id);

        if ($manager !== null) {
            $recipients->push($manager);
        }

        $admins = $this->scopes->withoutScoping(fn (): Collection => $this->holdersOf($permission)
            ->filter(fn (User $admin): bool => $admin->employee_id !== $employee->id && $employee->isVisibleTo($admin)));

        return $recipients->merge($admins)->unique('id')->reject(fn (User $user): bool => $user->employee_id === $employee->id)->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function holdersOf(string $permission): Collection
    {
        return $this->holders[$permission] ??= User::query()->admins()->where('status', UserStatus::Active)->get()
            ->filter(fn (User $user): bool => $user->can($permission))->values();
    }
}
