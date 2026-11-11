<?php

namespace App\Models;

use App\Enums\OfferStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasDataScope;
use App\Models\Scopes\DataScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job offer to a candidate. basic_salary is DECIMAL and restricted
 * (employee_financials.view). Status changes go through
 * App\Actions\Recruitment\ManageOffer; approval (when required) through the
 * approval engine, workflow "job_offer".
 */
#[Fillable([
    'job_application_id', 'position_id', 'job_title_id', 'employment_type_id', 'basic_salary', 'currency',
    'start_date', 'expires_on', 'probation_months', 'benefits', 'terms', 'created_by',
])]
#[Hidden(['basic_salary'])]
class JobOffer extends Model
{
    use Auditable, HasApprovals, HasDataScope;

    public const WORKFLOW = 'job_offer';

    /** @var list<string> */
    protected array $auditRedact = ['basic_salary'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'basic_salary' => 'decimal:2',
            'start_date' => 'date',
            'expires_on' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'probation_months' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'application.vacancy.branch_id',
            'department' => 'application.vacancy.department_id',
        ];
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id')->withoutGlobalScope(DataScope::class);
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
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
