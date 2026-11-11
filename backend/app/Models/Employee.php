<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\TerminationType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasLocalizedName;
use App\Support\SensitiveValue;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An employee. Personal data is edited in place (and audited); the job
 * placement columns (branch_id ... status, hire_date) and basic_salary are a
 * denormalised copy of the latest APPLIED employee_assignments /
 * employee_salaries rows and are only ever written by
 * App\Services\Employees\EmploymentHistory, never by a form directly.
 *
 * national_id, passport_number: encrypted; *_hash columns allow exact search.
 */
#[Fillable([
    'employee_number', 'name_ar', 'name_en', 'date_of_birth', 'gender', 'marital_status', 'nationality',
    'national_id', 'passport_number', 'passport_expiry_date', 'email', 'personal_email', 'phone', 'alternate_phone',
    'address', 'city', 'country_code', 'probation_end_date',
])]
#[Hidden(['national_id', 'national_id_hash', 'passport_number', 'passport_number_hash', 'basic_salary'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasDataScope, HasFactory, HasLocalizedName;

    /**
     * Placement columns copied from the assignment history.
     *
     * @var list<string>
     */
    public const PLACEMENT_COLUMNS = [
        'branch_id', 'department_id', 'team_id', 'position_id', 'job_title_id', 'job_grade_id',
        'employment_type_id', 'cost_center_id', 'manager_id', 'work_location_id', 'status',
    ];

    /**
     * Sensitive values keep the fact of a change in the audit log but never the value.
     *
     * @var list<string>
     */
    protected array $auditRedact = ['national_id', 'national_id_hash', 'passport_number', 'passport_number_hash', 'basic_salary'];

    protected static function booted(): void
    {
        static::saving(function (Employee $employee): void {
            if ($employee->isDirty('national_id')) {
                $employee->national_id_hash = SensitiveValue::hash($employee->national_id);
            }

            if ($employee->isDirty('passport_number')) {
                $employee->passport_number_hash = SensitiveValue::hash($employee->passport_number);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
            'hire_date' => 'date',
            'original_hire_date' => 'date',
            'probation_end_date' => 'date',
            'termination_date' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'status' => EmployeeStatus::class,
            'termination_type' => TerminationType::class,
            'national_id' => 'encrypted',
            'passport_number' => 'encrypted',
            'basic_salary' => 'decimal:2',
            'eligible_for_rehire' => 'boolean',
        ];
    }

    /**
     * Restricted by branch, department (with sub-departments, see
     * DataScopeManager) and employee group.
     *
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'department_id',
            'employee_group' => 'groups.id',
        ];
    }

    protected function hasCodeColumn(): bool
    {
        return false;
    }

    /**
     * Name (either language), employee number, email or phone contains the
     * term, or the term is exactly a national id / passport number (matched
     * through the keyed hash, the encrypted values are never searched).
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $hash = SensitiveValue::hash($term);

        $query->where(function (Builder $query) use ($term, $hash): void {
            $query->whereLike($query->qualifyColumn('name_ar'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('name_en'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('employee_number'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('email'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('phone'), "%{$term}%");

            if ($hash !== null) {
                $query->orWhere($query->qualifyColumn('national_id_hash'), $hash)
                    ->orWhere($query->qualifyColumn('passport_number_hash'), $hash);
            }
        });
    }

    /**
     * Employees still employed (active or inactive), i.e. holding their position.
     *
     * @param  Builder<static>  $query
     */
    public function scopeEmployed(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), '!=', EmployeeStatus::Terminated);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), EmployeeStatus::Active);
    }

    /**
     * Active employees, plus the given id even when not active (an edit form keeps the current value).
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveOrKeep(Builder $query, int|string|null $keepId): void
    {
        $query->where(function (Builder $query) use ($keepId): void {
            $query->where($query->qualifyColumn('status'), EmployeeStatus::Active);

            if (filled($keepId)) {
                $query->orWhere($query->qualifyColumn('id'), $keepId);
            }
        });
    }

    public function isTerminated(): bool
    {
        return $this->status === EmployeeStatus::Terminated;
    }

    public function isActive(): bool
    {
        return $this->status === EmployeeStatus::Active;
    }

    public function isOnProbation(): bool
    {
        return $this->probation_end_date !== null && $this->probation_end_date->isFuture() && ! $this->isTerminated();
    }

    /**
     * "EMP-00012 · Name", for pickers.
     */
    public function label(): string
    {
        return $this->employee_number.' · '.$this->name;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<JobTitle, $this>
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * @return BelongsTo<JobGrade, $this>
     */
    public function jobGrade(): BelongsTo
    {
        return $this->belongsTo(JobGrade::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * @return BelongsTo<BranchLocation, $this>
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(BranchLocation::class, 'work_location_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    /**
     * The login account (employee account, or an admin linked to this profile).
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<EmployeeAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class);
    }

    /**
     * @return HasMany<EmployeeSalary, $this>
     */
    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class);
    }

    /**
     * @return HasMany<EmployeeBankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    /**
     * @return HasMany<EmployeeEmergencyContact, $this>
     */
    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<EmployeeContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    /**
     * @return HasMany<EmployeeDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    /**
     * @return HasMany<EmployeeCustomFieldValue, $this>
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(EmployeeCustomFieldValue::class);
    }

    /**
     * @return BelongsToMany<EmployeeGroup, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(EmployeeGroup::class, 'employee_group_members')->withPivot('created_at');
    }

    /**
     * The candidate record this employee was hired from (recruitment lineage).
     *
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    /**
     * @return HasMany<Onboarding, $this>
     */
    public function onboardings(): HasMany
    {
        return $this->hasMany(Onboarding::class)->latest('id');
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<OffboardingCase, $this>
     */
    public function offboardingCases(): HasMany
    {
        return $this->hasMany(OffboardingCase::class)->latest('id');
    }

    /**
     * The contract in force today (latest started, not ended, not terminated).
     */
    public function currentContract(): ?EmployeeContract
    {
        return $this->contracts
            ->filter(fn (EmployeeContract $contract): bool => $contract->isCurrent())
            ->sortByDesc('start_date')
            ->first();
    }
}
