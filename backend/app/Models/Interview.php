<?php

namespace App\Models;

use App\Enums\InterviewDecision;
use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Scopes\DataScope;
use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An interview round of an application. scheduled_at is stored in UTC;
 * localStart() shows it in the zone it was scheduled in.
 */
#[Fillable([
    'job_application_id', 'type', 'scheduled_at', 'timezone', 'duration_minutes', 'mode', 'location',
    'meeting_url', 'evaluation_form_id', 'status', 'decision', 'decision_note', 'notes', 'created_by',
])]
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use Auditable, HasDataScope, HasFactory;

    /** @var list<string> */
    protected array $auditExclude = ['reminder_sent_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InterviewType::class,
            'mode' => InterviewMode::class,
            'status' => InterviewStatus::class,
            'decision' => InterviewDecision::class,
            'scheduled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'average_score' => 'decimal:2',
            'duration_minutes' => 'integer',
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
     * Interviews the user sits on as an interviewer.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas('interviewers', fn (Builder $query) => $query->whereKey($user->getKey()));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', InterviewStatus::Scheduled)->where('scheduled_at', '>=', now()->subHours(2));
    }

    /**
     * Interviewers reach their interviews whatever their data scope;
     * InterviewPolicy decides who may open which interview.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query->withoutGlobalScope(DataScope::class), $value, $field);
    }

    public function localStart(): Carbon
    {
        return $this->scheduled_at->copy()->setTimezone($this->timezone);
    }

    public function localEnd(): Carbon
    {
        return $this->localStart()->addMinutes($this->duration_minutes);
    }

    public function isInterviewer(User $user): bool
    {
        return $this->interviewers->contains('id', $user->getKey());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [InterviewStatus::Scheduled, InterviewStatus::Completed], true);
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id')->withoutGlobalScope(DataScope::class);
    }

    /**
     * @return BelongsTo<EvaluationForm, $this>
     */
    public function evaluationForm(): BelongsTo
    {
        return $this->belongsTo(EvaluationForm::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function interviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'interview_interviewers');
    }

    /**
     * @return HasMany<InterviewScorecard, $this>
     */
    public function scorecards(): HasMany
    {
        return $this->hasMany(InterviewScorecard::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
