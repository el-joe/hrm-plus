<?php

namespace App\Models;

use App\Enums\CandidateSource;
use App\Enums\Gender;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person in the talent pool. Applications link them to vacancies; the
 * data scope follows those vacancies (a restricted admin sees candidates who
 * applied to a vacancy in their branches / departments).
 *
 * expected_salary / current_salary are restricted (employee_financials.view);
 * every display is written to the sensitive-data access log.
 */
#[Fillable([
    'name_ar', 'name_en', 'email', 'phone', 'date_of_birth', 'gender', 'nationality', 'city',
    'current_job_title', 'current_employer', 'experience_years', 'qualifications', 'skills',
    'expected_salary', 'current_salary', 'source', 'source_detail', 'referred_by_employee_id',
    'linkedin_url', 'portfolio_url',
])]
#[Hidden(['expected_salary', 'current_salary'])]
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use Auditable, HasDataScope, HasFactory, HasLocalizedName, SoftDeletes;

    /** @var list<string> */
    protected array $auditRedact = ['expected_salary', 'current_salary'];

    protected static function booted(): void
    {
        static::saving(function (Candidate $candidate): void {
            if ($candidate->isDirty('phone')) {
                $candidate->phone_normalized = static::normalisePhone($candidate->phone);
            }

            if ($candidate->isDirty('email') && $candidate->email !== null) {
                $candidate->email = mb_strtolower(trim($candidate->email));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'source' => CandidateSource::class,
            'skills' => 'array',
            'experience_years' => 'decimal:1',
            'expected_salary' => 'decimal:2',
            'current_salary' => 'decimal:2',
            'consent_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'applications.vacancy.branch_id',
            'department' => 'applications.vacancy.department_id',
        ];
    }

    protected function hasCodeColumn(): bool
    {
        return false;
    }

    /**
     * Digits only, the last 10 — so "+20 100 123 4567", "0020 100…" and
     * "0100 123 4567" all match on their significant tail.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : substr($digits, -10);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $phone = static::normalisePhone($term);

        $query->where(function (Builder $query) use ($term, $phone): void {
            $query->whereLike($query->qualifyColumn('name_ar'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('name_en'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('email'), "%{$term}%")
                ->orWhereLike($query->qualifyColumn('current_job_title'), "%{$term}%");

            if ($phone !== null && strlen($phone) >= 6) {
                $query->orWhereLike($query->qualifyColumn('phone_normalized'), "%{$phone}%");
            }
        });
    }

    public function isHired(): bool
    {
        return $this->employee_id !== null;
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * @return HasMany<CandidateNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CandidateNote::class)->latest('id');
    }

    /**
     * @return HasMany<CandidateAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CandidateAttachment::class)->latest('id');
    }

    /**
     * @return BelongsToMany<CandidateTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CandidateTag::class, 'candidate_tag')->orderBy('name');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'referred_by_employee_id');
    }
}
