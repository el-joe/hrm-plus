<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\DataScopeType;
use App\Enums\UserStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * account_type is deliberately not fillable: it decides which area an account
 * may enter, so it is only ever set explicitly (CreateAdminUser, seeders).
 */
#[Fillable(['name', 'email', 'password', 'status', 'locale', 'employee_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Attributes that change on every login and would only add noise to the audit log.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['last_login_at', 'last_login_ip', 'remember_token'];

    /**
     * Roles and permissions exist for admins only, on the "admin" guard.
     */
    public function guardName(): string
    {
        return config('permissions.guard');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return HasMany<AdminDataScope, $this>
     */
    public function dataScopes(): HasMany
    {
        return $this->hasMany(AdminDataScope::class);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * @return HasMany<LoginHistory, $this>
     */
    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('account_type', AccountType::Admin);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeEmployees(Builder $query): void
    {
        $query->where('account_type', AccountType::Employee);
    }

    /**
     * The employee profile this account is linked to (employee accounts, or admins linked to a profile).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * "Name · EMP-00012" for employee accounts, "Name · e-mail" otherwise; for pickers.
     */
    public function pickerLabel(): string
    {
        return $this->isEmployee() && $this->employee
            ? $this->name.' · '.$this->employee->employee_number
            : $this->name.' · '.$this->email;
    }

    public function isAdmin(): bool
    {
        return $this->account_type === AccountType::Admin;
    }

    public function isEmployee(): bool
    {
        return $this->account_type === AccountType::Employee;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->hasRole(config('permissions.super_admin_role'));
    }

    /**
     * Employees always use the portal; an admin may enter it only for the
     * employee profile linked to their own account.
     */
    public function canAccessPortal(): bool
    {
        return $this->isEmployee() || $this->employee_id !== null;
    }

    /**
     * The ids this admin is restricted to (ascending), grouped by scope type
     * (types in alphabetical order). An empty
     * array means the admin has no data restriction at all.
     *
     * @return array<string, list<int>>
     */
    public function dataScopeIds(): array
    {
        return $this->dataScopes
            ->groupBy(fn (AdminDataScope $scope): string => $scope->scope_type->value)
            ->map(fn ($scopes): array => $scopes->pluck('scope_id')->map(fn ($id): int => (int) $id)->sort()->values()->all())
            ->sortKeys()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function dataScopeIdsFor(DataScopeType $type): array
    {
        return $this->dataScopeIds()[$type->value] ?? [];
    }

    public function hasDataRestrictions(): bool
    {
        return ! $this->isSuperAdmin() && $this->dataScopes->isNotEmpty();
    }
}
