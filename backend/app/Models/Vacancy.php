<?php

namespace App\Models;

use App\Enums\VacancyStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\VacancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job vacancy (spec module 4). Bilingual title / description /
 * requirements; scoped by branch and department. The salary range is
 * restricted data (employee_financials.view) and never shown publicly.
 */
#[Fillable([
    'title_ar', 'title_en', 'description_ar', 'description_en', 'requirements_ar', 'requirements_en',
    'branch_id', 'department_id', 'position_id', 'job_title_id', 'employment_type_id', 'openings',
    'salary_min', 'salary_max', 'currency', 'status', 'is_public', 'publish_at', 'application_deadline',
    'evaluation_form_id', 'hiring_manager_id',
])]
#[Hidden(['salary_min', 'salary_max'])]
class Vacancy extends Model
{
    /** @use HasFactory<VacancyFactory> */
    use Auditable, HasDataScope, HasFactory, HasLocalizedName;

    /** @var list<string> */
    protected array $auditRedact = ['salary_min', 'salary_max'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VacancyStatus::class,
            'is_public' => 'boolean',
            'uses_custom_pipeline' => 'boolean',
            'publish_at' => 'date',
            'application_deadline' => 'date',
            'closed_at' => 'datetime',
            'openings' => 'integer',
            'filled_count' => 'integer',
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
        ];
    }

    protected function title(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->localized('title'));
    }

    public static function titleColumn(): string
    {
        return static::localizedColumn('title');
    }

    /**
     * Reference or title (either language) contains the term.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereLike($query->qualifyColumn('title_ar'), "%{$term}%")
            ->orWhereLike($query->qualifyColumn('title_en'), "%{$term}%")
            ->orWhereLike($query->qualifyColumn('reference'), "%{$term}%"));
    }

    /**
     * Listed on the public career page: open, public, published and not past its deadline.
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', VacancyStatus::Open)
            ->where('is_public', true)
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhereDate('publish_at', '<=', today()))
            ->where(fn (Builder $query) => $query->whereNull('application_deadline')->orWhereDate('application_deadline', '>=', today()));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', VacancyStatus::Open);
    }

    public function isPublished(): bool
    {
        return $this->status === VacancyStatus::Open && $this->is_public
            && ($this->publish_at === null || ! $this->publish_at->isFuture())
            && ($this->application_deadline === null || ! $this->application_deadline->isPast() || $this->application_deadline->isToday());
    }

    public function acceptsApplications(): bool
    {
        return $this->status->acceptsApplications();
    }

    public function remainingOpenings(): int
    {
        return max(0, $this->openings - $this->filled_count);
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
     * @return BelongsTo<EvaluationForm, $this>
     */
    public function evaluationForm(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * The vacancy's own pipeline (only used when uses_custom_pipeline).
     *
     * @return HasMany<RecruitmentStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(RecruitmentStage::class)->orderBy('sort_order')->orderBy('id');
    }
}
