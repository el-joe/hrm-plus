<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\CandidateSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Scopes\DataScope;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A candidate's application to a vacancy: the card on the pipeline board.
 * The stage only changes through App\Actions\Recruitment\MoveApplication,
 * which records the history row.
 */
#[Fillable(['candidate_id', 'vacancy_id', 'stage_id', 'status', 'source', 'cover_letter', 'applied_at', 'created_by'])]
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use Auditable, HasDataScope, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => CandidateSource::class,
            'applied_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'hired_at' => 'datetime',
            'average_score' => 'decimal:2',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'vacancy.branch_id',
            'department' => 'vacancy.department_id',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ApplicationStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === ApplicationStatus::Active;
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class)->withoutGlobalScope(DataScope::class);
    }

    /**
     * @return BelongsTo<Vacancy, $this>
     */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class)->withoutGlobalScope(DataScope::class);
    }

    /**
     * @return BelongsTo<RecruitmentStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(RecruitmentStage::class, 'stage_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<ApplicationStageChange, $this>
     */
    public function stageChanges(): HasMany
    {
        return $this->hasMany(ApplicationStageChange::class)->latest('id');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('scheduled_at');
    }

    /**
     * @return HasMany<JobOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class)->latest('id');
    }

    /**
     * @return HasOne<JobOffer, $this>
     */
    public function latestOffer(): HasOne
    {
        return $this->hasOne(JobOffer::class)->latestOfMany();
    }

    /**
     * @return HasMany<CandidateNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CandidateNote::class)->latest('id');
    }
}
